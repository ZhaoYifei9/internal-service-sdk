<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use Internal\ServiceSdk\Exception\RemoteServiceException;
use Internal\ServiceSdk\Http\HttpResponse;
use Internal\ServiceSdk\Toolbox\FeishuAlertClient;
use PHPUnit\Framework\TestCase;

final class FeishuAlertClientTest extends TestCase
{
    public function testSendsV2AlertWithBusinessContext(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(200, '{"code":0,"message":"success","data":{"sent":[1]}}'),
        ]);
        $client = $this->client($transport);

        $response = $client->sendAlert('NG-NEW-SYSTEM-ERROR', ['message' => ['error' => 'boom']], 1);

        $request = $transport->requests[0];
        $payload = json_decode($request['body'], true);
        self::assertSame('POST', $request['method']);
        self::assertSame(
            'http://toolbox-service/open/feishu/message/v2/custom',
            $request['url']
        );
        self::assertSame('NG-NEW-SYSTEM-ERROR', $payload['alertId']);
        self::assertSame('country-loan-api', $payload['data']['app_name']);
        self::assertSame('production', $payload['data']['env']);
        self::assertSame(1, $payload['data']['system']);
        self::assertSame('request-test', $request['headers']['X-Request-Id']);
        self::assertSame(0, $response['code']);
    }

    public function testCode200MeansTheAlertWasAcceptedButSkipped(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(200, '{"code":200,"message":"skipped","data":{"sent":[]}}'),
        ]);

        self::assertSame(200, $this->client($transport)->sendAlert('ALERT')['code']);
    }

    public function testRemoteFailureRaisesSanitizedException(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(400, '{"code":400,"message":"payload rejected"}'),
        ]);

        $this->expectException(RemoteServiceException::class);
        $this->expectExceptionMessage('Feishu alert request failed');
        $this->client($transport)->sendAlert('ALERT', ['secret_value' => 'must-not-leak']);
    }

    public function testSendsLegacyCustomFallback(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(200, '{"code":0,"message":"success"}'),
        ]);
        $client = $this->client($transport);

        $client->sendCustom(['content' => 'fallback', 'severity' => 'P1']);

        self::assertSame(
            'http://toolbox-service/open/feishu/message/custom',
            $transport->requests[0]['url']
        );
    }

    private function client(FakeTransport $transport): FeishuAlertClient
    {
        return new FeishuAlertClient(
            [
                'base_url' => 'http://toolbox-service/',
                'app_name' => 'country-loan-api',
                'environment' => 'production',
                'timeout' => 8,
            ],
            $transport,
            static function (): string {
                return 'request-test';
            }
        );
    }
}
