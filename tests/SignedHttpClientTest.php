<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use Internal\ServiceSdk\Auth\InternalHmacSigner;
use Internal\ServiceSdk\Auth\InternalRequestContext;
use Internal\ServiceSdk\Exception\InternalServiceException;
use Internal\ServiceSdk\Http\QueryString;
use Internal\ServiceSdk\Http\SignedHttpClient;
use PHPUnit\Framework\TestCase;

final class SignedHttpClientTest extends TestCase
{
    public function testSignsExactQueryBodyAndActorContext(): void
    {
        $transport = new FakeTransport();
        $client = new SignedHttpClient(
            [
                'base_url' => 'http://data-mid:9501/',
                'client_id' => 'toolbox',
                'secret' => 'test-secret',
                'timeout' => 15,
            ],
            $transport,
            static function (): int {
                return 1784347200;
            },
            static function (): string {
                return '0123456789abcdef0123456789abcdef';
            },
            static function (): string {
                return 'fallback-request';
            }
        );
        $context = new InternalRequestContext(
            '42',
            'operator@example.com',
            'mid.rules.read,mid.rules.edit',
            'request-from-toolbox'
        );
        $path = QueryString::append('/admin/v1/rules', [
            'status' => 'ACTIVE',
            'country_code' => 'NG',
            'empty' => '',
        ]);

        $client->request(
            'GET',
            $path,
            '',
            $context,
            ['Idempotency-Key' => 'operation-123456']
        );

        $request = $transport->requests[0];
        self::assertSame('/admin/v1/rules?country_code=NG&status=ACTIVE', $path);
        self::assertSame('http://data-mid:9501' . $path, $request['url']);
        self::assertSame('', $request['body']);
        self::assertSame('42', $request['headers']['X-Internal-Actor-Id']);
        self::assertSame('operator@example.com', $request['headers']['X-Internal-Actor-Name']);
        self::assertSame('mid.rules.read,mid.rules.edit', $request['headers']['X-Internal-Scopes']);
        self::assertSame('request-from-toolbox', $request['headers']['X-Request-Id']);
        self::assertSame('operation-123456', $request['headers']['Idempotency-Key']);
        self::assertSame(
            InternalHmacSigner::sign(
                'test-secret',
                'GET',
                $path,
                '',
                '1784347200',
                '0123456789abcdef0123456789abcdef',
                'toolbox',
                '42',
                'operator@example.com',
                'mid.rules.read,mid.rules.edit'
            ),
            $request['headers']['X-Internal-Signature']
        );
    }

    public function testServiceRequestKeepsActorHeadersAbsent(): void
    {
        $transport = new FakeTransport();
        $client = new SignedHttpClient(
            [
                'base_url' => 'http://service-notification:9821',
                'client_id' => 'data-mid',
                'secret' => 'test-secret',
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

        $client->request('POST', '/v1/deliveries/action-1', '{}');

        $headers = $transport->requests[0]['headers'];
        self::assertArrayNotHasKey('X-Internal-Actor-Id', $headers);
        self::assertArrayNotHasKey('X-Internal-Actor-Name', $headers);
        self::assertArrayNotHasKey('X-Internal-Scopes', $headers);
    }

    public function testRejectsAnAttemptToOverrideSignedHeaders(): void
    {
        $client = new SignedHttpClient([
            'base_url' => 'http://data-mid:9501',
            'client_id' => 'toolbox',
            'secret' => 'test-secret',
        ], new FakeTransport());

        $this->expectException(InternalServiceException::class);
        $this->expectExceptionMessage('cannot be overridden');

        $client->request('GET', '/admin/v1/meta', '', null, [
            'X-Internal-Scopes' => 'admin',
        ]);
    }

    public function testContextRejectsHeaderInjection(): void
    {
        $this->expectException(InternalServiceException::class);
        $this->expectExceptionMessage('actor name');

        new InternalRequestContext('42', "operator\r\nX-Evil: true");
    }
}
