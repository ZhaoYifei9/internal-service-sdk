<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use Internal\ServiceSdk\Aj\AjClient;
use Internal\ServiceSdk\Aj\AjDeviceRegistration;
use Internal\ServiceSdk\Aj\AjEvent;
use Internal\ServiceSdk\Exception\RemoteServiceException;
use Internal\ServiceSdk\Http\HttpResponse;
use PHPUnit\Framework\TestCase;

final class AjClientTest extends TestCase
{
    public function testSendsDeviceRegistrationContract(): void
    {
        $transport = new FakeTransport([new HttpResponse(200, '{"code":0}')]);
        $client = new AjClient(['base_url' => 'http://aj-service/'], $transport);

        $response = $client->registerDevice(new AjDeviceRegistration(
            'request-1',
            5001,
            'device-1',
            'aaid-1',
            1784851200,
            'firebase-1'
        ));

        self::assertSame(0, $response['code']);
        self::assertSame('http://aj-service/api/device/ma', $transport->requests[0]['url']);
        $payload = json_decode($transport->requests[0]['body'], true);
        self::assertSame(5001, $payload['appId']);
        self::assertSame('firebase-1', $payload['fireAppInstanceId']);
    }

    public function testEventRequiresExplicitCountryClientVersion(): void
    {
        $event = new AjEvent(
            'request-1',
            'phone',
            'id-number',
            'ORDER-1',
            'risk-a',
            5001,
            5000,
            'device-1',
            'loaned',
            1784851200,
            'country-api'
        );

        self::assertSame('country-api', $event->toArray()['clientVersion']);
    }

    public function testRetriesFailedResponsesWithInjectedSleeper(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(503, '{"message":"busy"}'),
            new HttpResponse(200, ''),
            new HttpResponse(200, '{"code":0}'),
        ]);
        $delays = [];
        $retries = [];
        $client = new AjClient([
            'base_url' => 'http://aj-service',
            'max_attempts' => 3,
            'retry_delay_ms' => 25,
        ], $transport, static function (int $delay) use (&$delays): void {
            $delays[] = $delay;
        }, static function (int $attempt, string $path, string $reason, int $status) use (&$retries): void {
            $retries[] = compact('attempt', 'path', 'reason', 'status');
        });

        $response = $client->sendEvent($this->event());

        self::assertSame(0, $response['code']);
        self::assertSame([25, 25], $delays);
        self::assertCount(2, $retries);
        self::assertSame(503, $retries[0]['status']);
        self::assertSame('invalid or empty JSON response', $retries[1]['reason']);
    }

    public function testFinalFailureIsSanitized(): void
    {
        $transport = new FakeTransport([new HttpResponse(500, 'secret response')]);
        $client = new AjClient([
            'base_url' => 'http://aj-service',
            'max_attempts' => 1,
        ], $transport);

        try {
            $client->sendEvent($this->event());
            self::fail('Expected an AJ request exception');
        } catch (RemoteServiceException $exception) {
            self::assertSame(500, $exception->statusCode());
            self::assertStringNotContainsString('secret response', $exception->getMessage());
        }
    }

    public function testDeterministicClientErrorIsNotRetried(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(422, '{"message":"invalid payload"}'),
            new HttpResponse(200, '{"code":0}'),
        ]);
        $client = new AjClient([
            'base_url' => 'http://aj-service',
            'max_attempts' => 4,
        ], $transport);

        try {
            $client->sendEvent($this->event());
            self::fail('Expected an AJ rejection');
        } catch (RemoteServiceException $exception) {
            self::assertSame(422, $exception->statusCode());
            self::assertCount(1, $transport->requests);
            self::assertStringNotContainsString('invalid payload', $exception->getMessage());
        }
    }

    private function event(): AjEvent
    {
        return new AjEvent(
            'request-1',
            'phone',
            'id-number',
            '',
            '',
            5001,
            0,
            'device-1',
            'login',
            1784851200,
            'country-api'
        );
    }
}
