<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Http;

use Internal\ServiceSdk\Exception\TransportException;
use Swoole\Coroutine\Http\Client;

final class SwooleHttpTransport implements TransportInterface
{
    public function request(
        string $method,
        string $url,
        string $body,
        array $headers,
        int $timeout
    ): HttpResponse {
        if (!class_exists(Client::class)) {
            throw new TransportException('ext-swoole is required for SwooleHttpTransport');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if (($scheme !== 'http' && $scheme !== 'https') || $host === '') {
            throw new TransportException('invalid internal service URL');
        }

        $ssl = $scheme === 'https';
        $port = (int) ($parts['port'] ?? ($ssl ? 443 : 80));
        $path = (string) ($parts['path'] ?? '/');
        if (!empty($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        $client = new Client($host, $port, $ssl);
        try {
            $client->set(['timeout' => max(1, $timeout)]);
            $client->setHeaders($headers);
            $client->setData($body);
            $client->setMethod(strtoupper($method));
            if (!$client->execute($path)) {
                throw new TransportException(sprintf(
                    'internal service request failed err_code=%d',
                    (int) $client->errCode
                ));
            }

            return new HttpResponse(
                (int) $client->getStatusCode(),
                (string) $client->getBody(),
                (array) $client->getHeaders()
            );
        } finally {
            $client->close();
        }
    }
}
