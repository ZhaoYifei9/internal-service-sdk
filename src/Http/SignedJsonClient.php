<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Http;

use Internal\ServiceSdk\Auth\InternalHmacSigner;
use Internal\ServiceSdk\Exception\InternalServiceException;

final class SignedJsonClient
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

    /** @param array<string, mixed> $payload */
    public function request(string $method, string $path, array $payload, ?int $timeout = null): HttpResponse
    {
        if (!$this->isConfigured()) {
            throw new InternalServiceException('signed internal service client is not configured');
        }
        if ($path === '' || $path[0] !== '/') {
            throw new InternalServiceException('internal service path must start with a slash');
        }

        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $timestamp = (string) ($this->clock)();
        $nonce = (string) ($this->nonceFactory)();
        $requestId = trim((string) ($this->requestIdResolver)());
        if ($requestId === '') {
            $requestId = bin2hex(random_bytes(12));
        }
        $headers = [
            'Content-Type' => 'application/json',
            'X-Internal-Client-Id' => $this->clientId,
            'X-Internal-Timestamp' => $timestamp,
            'X-Internal-Nonce' => $nonce,
            'X-Internal-Signature' => InternalHmacSigner::sign(
                $this->secret,
                $method,
                $path,
                $body,
                $timestamp,
                $nonce,
                $this->clientId
            ),
            'X-Request-Id' => $requestId,
        ];

        return $this->transport->request(
            strtoupper($method),
            $this->baseUrl . $path,
            $body,
            $headers,
            $timeout === null ? $this->timeout : max(1, min(120, $timeout))
        );
    }
}
