<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use Internal\ServiceSdk\Http\HttpResponse;
use Internal\ServiceSdk\Http\TransportInterface;

final class FakeTransport implements TransportInterface
{
    /** @var array<int, array{method:string,url:string,body:string,headers:array<string,string>,timeout:int}> */
    public array $requests = [];

    /** @var array<int, HttpResponse> */
    private array $responses;

    /** @param array<int, HttpResponse> $responses */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    public function request(
        string $method,
        string $url,
        string $body,
        array $headers,
        int $timeout
    ): HttpResponse {
        $this->requests[] = compact('method', 'url', 'body', 'headers', 'timeout');

        return $this->responses === []
            ? new HttpResponse(200, '{"code":"OK"}')
            : array_shift($this->responses);
    }
}
