<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Notification;

use Internal\ServiceSdk\Exception\InternalServiceException;
use Internal\ServiceSdk\Exception\RemoteServiceException;
use Internal\ServiceSdk\Http\SignedJsonClient;
use Internal\ServiceSdk\Http\TransportInterface;

final class NotificationDeviceClient
{
    private SignedJsonClient $http;

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
        $this->http = new SignedJsonClient(
            $config,
            $transport,
            $clock,
            $nonceFactory,
            $requestIdResolver
        );
    }

    public function isConfigured(): bool
    {
        return $this->http->isConfigured();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function register(string $installUuid, array $payload): array
    {
        if (!$this->isConfigured()) {
            throw new InternalServiceException('notification device client is not configured');
        }

        $installUuid = trim($installUuid);
        if ($installUuid === '' || strlen($installUuid) > 128 || preg_match('/[\x00-\x1F\x7F]/', $installUuid)) {
            throw new InternalServiceException('invalid notification install UUID');
        }

        $response = $this->http->request(
            'PUT',
            '/v1/devices/' . rawurlencode($installUuid),
            $payload
        );
        if (!$response->isSuccessful()) {
            $decoded = $response->json();
            $code = preg_replace(
                '/[^A-Za-z0-9._-]/',
                '',
                (string) ($decoded['code'] ?? 'UNKNOWN')
            );
            throw new RemoteServiceException(
                sprintf(
                    'notification device register failed status=%d code=%s',
                    $response->statusCode(),
                    $code
                ),
                $response->statusCode(),
                $code
            );
        }

        return $response->json();
    }
}
