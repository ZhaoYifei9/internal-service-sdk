<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Http;

use Internal\ServiceSdk\Auth\InternalHmacSigner;
use Internal\ServiceSdk\Auth\InternalRequestContext;
use Internal\ServiceSdk\Exception\InternalServiceException;

/**
 * Low-level client for the shared nine-line HMAC contract.
 *
 * It signs the exact path, query and body bytes sent by the transport. JSON
 * encoding and service-specific response semantics belong to higher layers.
 */
final class SignedHttpClient
{
    private string $baseUrl;

    private string $clientId;

    private string $secret;

    private int $timeout;

    private TransportInterface $transport;

    /** @var callable */
    private $clock;

    /** @var callable */
    private $nonceFactory;

    /** @var callable */
    private $requestIdResolver;

    /**
     * @param array{base_url?:string,url?:string,client_id?:string,secret?:string,timeout?:int} $config
     */
    public function __construct(
        array $config,
        ?TransportInterface $transport = null,
        ?callable $clock = null,
        ?callable $nonceFactory = null,
        ?callable $requestIdResolver = null
    ) {
        $this->baseUrl = rtrim(trim((string) ($config['base_url'] ?? $config['url'] ?? '')), '/');
        $this->clientId = trim((string) ($config['client_id'] ?? ''));
        $this->secret = (string) ($config['secret'] ?? '');
        $this->timeout = max(1, min(120, (int) ($config['timeout'] ?? 10)));
        $this->transport = $transport ?? new SwooleHttpTransport();
        $this->clock = $clock ?? static function (): int {
            return time();
        };
        $this->nonceFactory = $nonceFactory ?? static function (): string {
            return bin2hex(random_bytes(16));
        };
        $this->requestIdResolver = $requestIdResolver ?? static function (): string {
            return bin2hex(random_bytes(12));
        };
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->clientId !== '' && $this->secret !== '';
    }

    /**
     * @param array<string, string> $headers
     */
    public function request(
        string $method,
        string $pathAndQuery,
        string $body = '',
        ?InternalRequestContext $context = null,
        array $headers = [],
        ?int $timeout = null
    ): HttpResponse {
        if (!$this->isConfigured()) {
            throw new InternalServiceException('signed internal service client is not configured');
        }
        if ($pathAndQuery === '' || $pathAndQuery[0] !== '/') {
            throw new InternalServiceException('internal service path must start with a slash');
        }

        $context = $context ?? new InternalRequestContext();
        $method = strtoupper(trim($method));
        if ($method === '') {
            throw new InternalServiceException('internal service method is required');
        }

        $timestamp = (string) ($this->clock)();
        $nonce = trim((string) ($this->nonceFactory)());
        if ($nonce === '' || preg_match('/[\r\n]/', $nonce) === 1) {
            throw new InternalServiceException('invalid internal request nonce');
        }

        $requestId = trim($context->requestId());
        if ($requestId === '') {
            $requestId = trim((string) ($this->requestIdResolver)());
        }
        if ($requestId === '') {
            $requestId = bin2hex(random_bytes(12));
        }
        if (strlen($requestId) > 191 || preg_match('/[\r\n]/', $requestId) === 1) {
            throw new InternalServiceException('invalid internal request ID');
        }

        $headers = $this->validateAdditionalHeaders($headers);
        $signedHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Internal-Client-Id' => $this->clientId,
            'X-Internal-Timestamp' => $timestamp,
            'X-Internal-Nonce' => $nonce,
            'X-Internal-Signature' => InternalHmacSigner::sign(
                $this->secret,
                $method,
                $pathAndQuery,
                $body,
                $timestamp,
                $nonce,
                $this->clientId,
                $context->actorId(),
                $context->actorName(),
                $context->scopes()
            ),
            'X-Request-Id' => $requestId,
        ];
        if ($context->actorId() !== '' || $context->actorName() !== '' || $context->scopes() !== '') {
            $signedHeaders['X-Internal-Actor-Id'] = $context->actorId();
            $signedHeaders['X-Internal-Actor-Name'] = $context->actorName();
            $signedHeaders['X-Internal-Scopes'] = $context->scopes();
        }

        return $this->transport->request(
            $method,
            $this->baseUrl . $pathAndQuery,
            $body,
            array_merge($headers, $signedHeaders),
            $timeout === null ? $this->timeout : max(1, min(120, $timeout))
        );
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function validateAdditionalHeaders(array $headers): array
    {
        foreach ($headers as $name => $value) {
            if (!is_string($name) || preg_match('/^[A-Za-z0-9-]+$/', $name) !== 1) {
                throw new InternalServiceException('invalid internal request header name');
            }
            $normalized = strtolower($name);
            if ($normalized === 'x-request-id' || strpos($normalized, 'x-internal-') === 0) {
                throw new InternalServiceException('signed internal request headers cannot be overridden');
            }
            if (!is_string($value) || preg_match('/[\r\n]/', $value) === 1) {
                throw new InternalServiceException('invalid internal request header value');
            }
        }

        return $headers;
    }
}
