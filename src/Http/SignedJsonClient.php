<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Http;

use Internal\ServiceSdk\Auth\InternalRequestContext;

final class SignedJsonClient
{
    private SignedHttpClient $http;

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
        $this->http = new SignedHttpClient(
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
     * @param array<string, string> $headers
     */
    public function request(
        string $method,
        string $path,
        array $payload,
        ?int $timeout = null,
        ?InternalRequestContext $context = null,
        array $headers = []
    ): HttpResponse {
        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        return $this->http->request($method, $path, $body, $context, $headers, $timeout);
    }
}
