<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\DataMid;

use InvalidArgumentException;
use Internal\ServiceSdk\Http\HttpResponse;
use Internal\ServiceSdk\Http\SignedJsonClient;
use Internal\ServiceSdk\Http\TransportInterface;

final class DataMidClient
{
    private string $countryCode;

    private SignedJsonClient $http;

    /**
     * @param array{base_url?:string,url?:string,country_code?:string,client_id?:string,secret?:string,timeout?:int} $config
     */
    public function __construct(
        array $config,
        ?TransportInterface $transport = null,
        ?callable $clock = null,
        ?callable $nonceFactory = null,
        ?callable $requestIdResolver = null
    ) {
        $this->countryCode = strtoupper(trim((string) ($config['country_code'] ?? '')));
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
        return $this->countryCode !== '' && $this->http->isConfigured();
    }

    /** @param array<string, mixed> $event */
    public function reportEvent(array $event, ?int $timeout = null): HttpResponse
    {
        $event['country_code'] = $event['country_code'] ?? $this->countryCode;

        return $this->http->request('POST', '/report/event', $event, $timeout);
    }

    /** @param array<int, array<string, mixed>> $events */
    public function reportBatch(array $events, ?int $timeout = null): HttpResponse
    {
        foreach ($events as $index => $event) {
            if (!is_array($event) || empty($event['phone'])) {
                throw new InvalidArgumentException("Missing phone for batch event at index {$index}");
            }
            $events[$index]['country_code'] = $event['country_code'] ?? $this->countryCode;
        }

        return $this->http->request('POST', '/report/batch', $events, $timeout);
    }

    /** @param array<int, array<string, mixed>> $events */
    public function reportBatchResult(array $events, ?int $timeout = null): BatchReportResult
    {
        return BatchReportResult::fromHttpResponse($this->reportBatch($events, $timeout));
    }
}
