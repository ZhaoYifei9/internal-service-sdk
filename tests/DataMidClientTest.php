<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use InvalidArgumentException;
use Internal\ServiceSdk\Auth\InternalHmacSigner;
use Internal\ServiceSdk\DataMid\DataMidClient;
use Internal\ServiceSdk\DataMid\EventType;
use Internal\ServiceSdk\Http\HttpResponse;
use PHPUnit\Framework\TestCase;

final class DataMidClientTest extends TestCase
{
    public function testReportsAnEventWithCountryAndExactSignature(): void
    {
        $transport = new FakeTransport([new HttpResponse(200, '{"code":"OK"}')]);
        $client = $this->client($transport);

        $response = $client->reportEvent([
            'event_type' => 'user.registered',
            'app_id' => '5001',
            'user_id' => '42',
            'phone' => 'test-phone',
            'event_id' => 'event-1',
            'timestamp' => 1784347200,
            'data' => ['source' => 'test'],
        ]);

        self::assertSame(200, $response->statusCode());
        self::assertCount(1, $transport->requests);
        $request = $transport->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame('http://data-mid:9801/report/event', $request['url']);
        self::assertSame('NG', json_decode($request['body'], true)['country_code']);
        self::assertSame('request-test', $request['headers']['X-Request-Id']);
        self::assertSame(
            InternalHmacSigner::sign(
                'test-secret',
                'POST',
                '/report/event',
                $request['body'],
                '1784347200',
                '0123456789abcdef0123456789abcdef',
                'country-loan-api'
            ),
            $request['headers']['X-Internal-Signature']
        );
    }

    public function testBatchAddsCountryAndUsesRequestedTimeout(): void
    {
        $transport = new FakeTransport([new HttpResponse(200, '{"data":{"accepted":1,"rejected":0}}')]);
        $client = $this->client($transport);

        $client->reportBatch([[
            'event_type' => 'user.overdue.daily',
            'phone' => 'test-phone',
        ]], 12);

        self::assertSame(12, $transport->requests[0]['timeout']);
        self::assertSame('NG', json_decode($transport->requests[0]['body'], true)[0]['country_code']);
    }

    public function testBatchResultDecodesPartialAcceptance(): void
    {
        $transport = new FakeTransport([new HttpResponse(
            200,
            '{"data":{"accepted":0,"rejected":1,"errors":[{"error":"invalid app"}]}}'
        )]);

        $result = $this->client($transport)->reportBatchResult([[
            'event_type' => EventType::USER_OVERDUE_DAILY,
            'phone' => 'test-phone',
        ]]);

        self::assertFalse($result->isComplete(1));
        self::assertSame('invalid app', $result->firstError());
    }

    public function testBatchRejectsAnEventWithoutPhone(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->client(new FakeTransport())->reportBatch([[
            'event_type' => 'user.overdue.daily',
        ]]);
    }

    private function client(FakeTransport $transport): DataMidClient
    {
        return new DataMidClient(
            [
                'base_url' => 'http://data-mid:9801/',
                'country_code' => 'ng',
                'client_id' => 'country-loan-api',
                'secret' => 'test-secret',
                'timeout' => 5,
            ],
            $transport,
            static function (): int {
                return 1784347200;
            },
            static function (): string {
                return '0123456789abcdef0123456789abcdef';
            },
            static function (): string {
                return 'request-test';
            }
        );
    }
}
