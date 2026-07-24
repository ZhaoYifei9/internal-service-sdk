<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Http;

use Internal\ServiceSdk\Exception\InternalServiceException;
use Internal\ServiceSdk\Exception\TransportException;

/**
 * Optional Guzzle transport. The SDK keeps Guzzle as a suggested dependency.
 */
final class GuzzleHttpTransport implements TransportInterface
{
    /** @var object */
    private $client;

    /** @param object|null $client A Guzzle-compatible client with request(). */
    public function __construct($client = null)
    {
        if ($client === null) {
            if (!class_exists('GuzzleHttp\\Client')) {
                throw new InternalServiceException('guzzlehttp/guzzle is required for GuzzleHttpTransport');
            }
            $class = 'GuzzleHttp\\Client';
            $client = new $class(['http_errors' => false]);
        }
        if (!is_object($client) || !method_exists($client, 'request')) {
            throw new InternalServiceException('invalid Guzzle-compatible HTTP client');
        }

        $this->client = $client;
    }

    public function request(
        string $method,
        string $url,
        string $body,
        array $headers,
        int $timeout
    ): HttpResponse {
        try {
            $response = $this->client->request($method, $url, [
                'headers' => $headers,
                'body' => $body,
                'timeout' => max(1, $timeout),
                'connect_timeout' => min(3, max(1, $timeout)),
                'http_errors' => false,
            ]);
        } catch (\Throwable $exception) {
            throw new TransportException('internal service request failed', 0, $exception);
        }

        if (!is_object($response)
            || !method_exists($response, 'getStatusCode')
            || !method_exists($response, 'getBody')
        ) {
            throw new TransportException('invalid Guzzle-compatible HTTP response');
        }

        return new HttpResponse(
            (int) $response->getStatusCode(),
            (string) $response->getBody(),
            method_exists($response, 'getHeaders') ? (array) $response->getHeaders() : []
        );
    }
}
