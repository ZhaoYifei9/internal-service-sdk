<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Http;

interface TransportInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function request(
        string $method,
        string $url,
        string $body,
        array $headers,
        int $timeout
    ): HttpResponse;
}
