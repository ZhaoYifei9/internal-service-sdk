<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use Internal\ServiceSdk\Http\HttpResponse;
use Internal\ServiceSdk\Toolbox\AlertCatalog;
use Internal\ServiceSdk\Toolbox\FeishuAlertClient;
use Internal\ServiceSdk\Toolbox\FeishuNotifier;
use PHPUnit\Framework\TestCase;

final class FeishuNotifierTest extends TestCase
{
    public function testUsesCatalogAndReturnsPrimaryResponse(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(200, '{"code":0,"message":"success"}'),
        ]);
        $notifier = $this->notifier($transport);

        $result = $notifier->notify('SYSTEM_ERROR', ['message' => 'boom'], 1);

        self::assertTrue($result->isSuccessful());
        self::assertFalse($result->fallbackAttempted());
        self::assertSame(0, $result->primaryResponse()['code']);
        $payload = json_decode($transport->requests[0]['body'], true);
        self::assertSame('COUNTRY-SYSTEM', $payload['alertId']);
        self::assertSame(1, $payload['data']['system']);
    }

    public function testPrimaryFailureUsesSanitizedLegacyFallback(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(503, '{"message":"busy"}'),
            new HttpResponse(200, ''),
        ]);
        $notifier = $this->notifier($transport);

        $result = $notifier->notify('SYSTEM_ERROR', ['private' => 'not-in-fallback']);

        self::assertFalse($result->isSuccessful());
        self::assertTrue($result->fallbackAttempted());
        self::assertCount(2, $transport->requests);
        $fallback = json_decode($transport->requests[1]['body'], true);
        self::assertSame('P2', $fallback['severity']);
        self::assertSame('on-call', $fallback['at']);
        self::assertStringContainsString('COUNTRY-SYSTEM', $fallback['content']);
        self::assertStringNotContainsString('not-in-fallback', $fallback['content']);
    }

    private function notifier(FakeTransport $transport): FeishuNotifier
    {
        $client = new FeishuAlertClient([
            'base_url' => 'http://toolbox',
            'app_name' => 'country-api',
            'environment' => 'test',
        ], $transport);
        $catalog = new AlertCatalog([
            'SYSTEM_ERROR' => ['id' => 'COUNTRY-SYSTEM', 'description' => 'System error'],
        ]);

        return new FeishuNotifier($client, $catalog, true, 'P2', 'on-call');
    }
}
