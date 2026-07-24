<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use Internal\ServiceSdk\Auth\InternalHmacSigner;
use Internal\ServiceSdk\Exception\RemoteServiceException;
use Internal\ServiceSdk\Http\HttpResponse;
use Internal\ServiceSdk\Notification\DeviceRegistration;
use Internal\ServiceSdk\Notification\NotificationDeviceClient;
use PHPUnit\Framework\TestCase;

final class NotificationDeviceClientTest extends TestCase
{
    public function testRegisterUsesPutAndSignsTheExactBody(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(200, '{"code":"OK","data":{"status":"ACTIVE"}}'),
        ]);
        $client = $this->client($transport);
        $payload = [
            'country_code' => 'NG',
            'app_id' => '5001',
            'platform' => 'ANDROID',
            'fcm_token' => str_repeat('t', 40),
            'aaid' => 'gaid-value',
            'token_updated_at' => '2026-07-24T12:00:00+01:00',
        ];

        $response = $client->register('install uuid', $payload);

        $request = $transport->requests[0];
        self::assertSame('PUT', $request['method']);
        self::assertSame(
            'http://service-notification:9821/v1/devices/install%20uuid',
            $request['url']
        );
        self::assertSame($payload, json_decode($request['body'], true));
        self::assertSame(
            InternalHmacSigner::sign(
                'test-secret',
                'PUT',
                '/v1/devices/install%20uuid',
                $request['body'],
                '1784347200',
                '0123456789abcdef0123456789abcdef',
                'country-loan-api'
            ),
            $request['headers']['X-Internal-Signature']
        );
        self::assertSame('ACTIVE', $response['data']['status']);
    }

    public function testFailureDoesNotExposeToken(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(401, '{"code":"AUTHENTICATION_FAILED"}'),
        ]);
        $token = str_repeat('secret-token-', 4);

        try {
            $this->client($transport)->register('install-uuid', [
                'country_code' => 'NG',
                'app_id' => '5001',
                'platform' => 'ANDROID',
                'fcm_token' => $token,
                'token_updated_at' => '2026-07-24T12:00:00+01:00',
            ]);
            self::fail('Expected register to fail.');
        } catch (RemoteServiceException $exception) {
            self::assertSame(401, $exception->statusCode());
            self::assertStringNotContainsString($token, $exception->getMessage());
        }
    }

    public function testRegisterDeviceUsesValidatedContract(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(200, '{"code":"OK","data":{"status":"ACTIVE"}}'),
        ]);
        $registration = new DeviceRegistration(
            'install-uuid',
            'ng',
            '5001',
            'android',
            str_repeat('t', 40),
            '2026-07-24T12:00:00+01:00'
        );

        $this->client($transport)->registerDevice($registration);

        self::assertSame(
            $registration->payload(),
            json_decode($transport->requests[0]['body'], true)
        );
    }

    private function client(FakeTransport $transport): NotificationDeviceClient
    {
        return new NotificationDeviceClient(
            [
                'base_url' => 'http://service-notification:9821/',
                'client_id' => 'country-loan-api',
                'secret' => 'test-secret',
                'timeout' => 4,
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
