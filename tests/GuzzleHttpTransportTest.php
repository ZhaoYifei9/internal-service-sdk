<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use Internal\ServiceSdk\Exception\TransportException;
use Internal\ServiceSdk\Http\GuzzleHttpTransport;
use PHPUnit\Framework\TestCase;

final class GuzzleHttpTransportTest extends TestCase
{
    public function testAdaptsAGuzzleCompatibleClient(): void
    {
        $client = new class() {
            /** @var array<string, mixed> */
            public array $captured = [];

            /** @param array<string, mixed> $options */
            public function request(string $method, string $url, array $options): object
            {
                $this->captured = compact('method', 'url', 'options');

                return new class() {
                    public function getStatusCode(): int
                    {
                        return 201;
                    }

                    public function getBody(): string
                    {
                        return '{"code":"OK"}';
                    }

                    /** @return array<string, array<int, string>> */
                    public function getHeaders(): array
                    {
                        return ['Content-Type' => ['application/json']];
                    }
                };
            }
        };
        $transport = new GuzzleHttpTransport($client);

        $response = $transport->request(
            'POST',
            'http://data-mid:9501/admin/v1/rules',
            '{}',
            ['Content-Type' => 'application/json'],
            12
        );

        self::assertSame(201, $response->statusCode());
        self::assertSame('{"code":"OK"}', $response->body());
        self::assertSame(12, $client->captured['options']['timeout']);
        self::assertFalse($client->captured['options']['http_errors']);
    }

    public function testWrapsTransportFailures(): void
    {
        $client = new class() {
            /** @param array<string, mixed> $options */
            public function request(string $method, string $url, array $options): object
            {
                throw new \RuntimeException('connection details');
            }
        };

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('internal service request failed');

        (new GuzzleHttpTransport($client))->request('GET', 'http://data-mid/health', '', [], 2);
    }
}
