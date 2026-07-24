<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Toolbox;

use Internal\ServiceSdk\Exception\InternalServiceException;
use Internal\ServiceSdk\Exception\RemoteServiceException;
use Internal\ServiceSdk\Http\HttpResponse;
use Internal\ServiceSdk\Http\SwooleHttpTransport;
use Internal\ServiceSdk\Http\TransportInterface;

final class FeishuAlertClient
{
    private string $baseUrl;

    private string $appName;

    private string $environment;

    private int $timeout;

    private TransportInterface $transport;

    /** @var callable */
    private $requestIdResolver;

    /**
     * @param array{base_url?:string,url?:string,app_name?:string,environment?:string,timeout?:int} $config
     */
    public function __construct(
        array $config,
        ?TransportInterface $transport = null,
        ?callable $requestIdResolver = null
    ) {
        $this->baseUrl = rtrim(trim((string) ($config['base_url'] ?? $config['url'] ?? '')), '/');
        $this->appName = trim((string) ($config['app_name'] ?? ''));
        $this->environment = trim((string) ($config['environment'] ?? ''));
        $this->timeout = max(1, min(120, (int) ($config['timeout'] ?? 10)));
        $this->transport = $transport ?? new SwooleHttpTransport();
        $this->requestIdResolver = $requestIdResolver ?? static function (): string {
            return bin2hex(random_bytes(12));
        };
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->appName !== '';
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sendAlert(string $alertId, array $data = [], int $system = 0): array
    {
        $alertId = trim($alertId);
        if ($alertId === '') {
            throw new InternalServiceException('Feishu alert ID is required');
        }

        $data = array_merge(['system' => $system], $data);
        $data['env'] = $this->environment;
        $data['app_name'] = $this->appName;

        $response = $this->request('/open/feishu/message/v2/custom', [
            'alertId' => $alertId,
            'data' => $data,
        ]);
        $decoded = $this->decodeResponse($response, 'Feishu alert request failed');
        $code = (int) ($decoded['code'] ?? -1);
        if ($code !== 0 && $code !== 200) {
            throw new RemoteServiceException(
                sprintf('Feishu alert request rejected code=%d', $code),
                $response->statusCode(),
                (string) $code
            );
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function sendCustom(array $payload): array
    {
        $response = $this->request('/open/feishu/message/custom', $payload);
        if (!$response->isSuccessful()) {
            throw new RemoteServiceException(
                'Feishu custom message request failed',
                $response->statusCode()
            );
        }

        // The legacy toolbox endpoint returns an empty JSON response on success.
        return $response->json();
    }

    /** @param array<string, mixed> $payload */
    private function request(string $path, array $payload): HttpResponse
    {
        if (!$this->isConfigured()) {
            throw new InternalServiceException('Feishu alert client is not configured');
        }

        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $requestId = trim((string) ($this->requestIdResolver)());
        if ($requestId === '') {
            $requestId = bin2hex(random_bytes(12));
        }

        return $this->transport->request(
            'POST',
            $this->baseUrl . $path,
            $body,
            [
                'Content-Type' => 'application/json',
                'X-Request-Id' => $requestId,
            ],
            $this->timeout
        );
    }

    /** @return array<string, mixed> */
    private function decodeResponse(HttpResponse $response, string $message): array
    {
        if (!$response->isSuccessful()) {
            throw new RemoteServiceException($message, $response->statusCode());
        }

        $decoded = $response->json();
        if ($decoded === [] || !array_key_exists('code', $decoded)) {
            throw new RemoteServiceException($message, $response->statusCode());
        }

        return $decoded;
    }
}
