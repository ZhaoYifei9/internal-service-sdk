<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Aj;

use Internal\ServiceSdk\Exception\InternalServiceException;
use Internal\ServiceSdk\Exception\RemoteServiceException;
use Internal\ServiceSdk\Http\SwooleHttpTransport;
use Internal\ServiceSdk\Http\TransportInterface;
use Throwable;

final class AjClient
{
    private string $baseUrl;

    private int $timeout;

    private int $maxAttempts;

    private int $retryDelayMs;

    private TransportInterface $transport;

    /** @var callable */
    private $sleeper;

    /** @var null|callable */
    private $retryListener;

    /**
     * @param array{base_url?:string,url?:string,timeout?:int,max_attempts?:int,retry_delay_ms?:int} $config
     */
    public function __construct(
        array $config,
        ?TransportInterface $transport = null,
        ?callable $sleeper = null,
        ?callable $retryListener = null
    ) {
        $this->baseUrl = rtrim(trim((string) ($config['base_url'] ?? $config['url'] ?? '')), '/');
        $this->timeout = max(1, min(120, (int) ($config['timeout'] ?? 10)));
        $this->maxAttempts = max(1, min(10, (int) ($config['max_attempts'] ?? 4)));
        $this->retryDelayMs = max(0, min(60000, (int) ($config['retry_delay_ms'] ?? 1000)));
        $this->transport = $transport ?? new SwooleHttpTransport();
        $this->sleeper = $sleeper ?? static function (int $delayMs): void {
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        };
        $this->retryListener = $retryListener;
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '';
    }

    /** @return array<string, mixed> */
    public function registerDevice(AjDeviceRegistration $registration): array
    {
        return $this->post('/api/device/ma', $registration->toArray());
    }

    /** @return array<string, mixed> */
    public function sendEvent(AjEvent $event): array
    {
        return $this->post('/api/event', $event->toArray());
    }

    /**
     * @param array<string, int|string> $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        if (!$this->isConfigured()) {
            throw new InternalServiceException('AJ client is not configured');
        }

        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $lastStatus = 0;
        $lastReason = 'request failed';

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            $lastStatus = 0;
            try {
                $response = $this->transport->request(
                    'POST',
                    $this->baseUrl . $path,
                    $body,
                    ['Content-Type' => 'application/json'],
                    $this->timeout
                );
                $lastStatus = $response->statusCode();
                $decoded = json_decode($response->body(), true);
                if ($response->isSuccessful() && is_array($decoded)) {
                    return $decoded;
                }
                $lastReason = $response->isSuccessful()
                    ? 'invalid or empty JSON response'
                    : sprintf('HTTP %d', $lastStatus);
                if ($lastStatus >= 400 && $lastStatus < 500) {
                    throw new RemoteServiceException('AJ request rejected', $lastStatus);
                }
            } catch (RemoteServiceException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $lastReason = $exception->getMessage();
            }

            if ($attempt < $this->maxAttempts) {
                if ($this->retryListener !== null) {
                    try {
                        ($this->retryListener)($attempt, $path, $lastReason, $lastStatus);
                    } catch (Throwable $listenerException) {
                        // Observability hooks must not affect delivery.
                    }
                }
                ($this->sleeper)($this->retryDelayMs);
            }
        }

        throw new RemoteServiceException(
            sprintf('AJ request failed after %d attempts: %s', $this->maxAttempts, $lastReason),
            $lastStatus
        );
    }
}
