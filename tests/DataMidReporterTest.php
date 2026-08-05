<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use InvalidArgumentException;
use Internal\ServiceSdk\DataMid\DataMidClient;
use Internal\ServiceSdk\DataMid\DataMidReporter;
use Internal\ServiceSdk\DataMid\EventFactory;
use Internal\ServiceSdk\DataMid\InlineDispatcher;
use Internal\ServiceSdk\DataMid\ProducerDeduplicator;
use Internal\ServiceSdk\Http\HttpResponse;
use PHPUnit\Framework\TestCase;

final class DataMidReporterTest extends TestCase
{
    public function testNamedEventUsesSharedDispatchAndDedupLifecycle(): void
    {
        $transport = new FakeTransport([new HttpResponse(200, '{"code":"OK"}')]);
        $store = new MemoryDedupStore();
        $reporter = $this->reporter($transport, $store);

        $reporter->userRegistered('42', '5001', [
            'phone' => 'test-phone',
            'source' => 'app',
        ]);

        self::assertCount(1, $transport->requests);
        $payload = json_decode($transport->requests[0]['body'], true);
        self::assertSame('user.registered', $payload['event_type']);
        self::assertSame('NG', $payload['country_code']);
        self::assertCount(1, $store->values);
        self::assertSame('done', reset($store->values)[0]);
        self::assertSame(600, reset($store->values)[1]);
    }

    public function testRejectedEventReleasesLeaseAndLogsContext(): void
    {
        $transport = new FakeTransport([new HttpResponse(503, '{"error":"busy"}')]);
        $store = new MemoryDedupStore();
        $logs = [];
        $reporter = $this->reporter($transport, $store, $logs);

        $reporter->profileCompleted('42', '5001', 'test-phone');

        self::assertSame([], $store->values);
        self::assertSame('Event report rejected', $logs[0]['message']);
        self::assertSame(503, $logs[0]['context']['status']);
    }

    public function testUserAssetSyncUsesNamedSdkContract(): void
    {
        $transport = new FakeTransport([new HttpResponse(200, '{"code":"OK"}')]);
        $reporter = $this->reporter($transport, new MemoryDedupStore());

        $reporter->userAssetSynced('42', '5001', 'test-phone', [
            'package_app_id' => '5002',
            'source_updated_at' => 1784851100,
            'sync_reason' => 'login',
        ]);

        $payload = json_decode($transport->requests[0]['body'], true);
        self::assertSame('user.asset.synced', $payload['event_type']);
        self::assertSame('5001', $payload['app_id']);
        self::assertSame('5002', $payload['data']['package_app_id']);
        self::assertSame('LOGIN', $payload['data']['sync_reason']);
    }

    public function testOverdueBatchReturnsOnlyCompleteAcceptance(): void
    {
        $transport = new FakeTransport([new HttpResponse(
            200,
            '{"data":{"accepted":1,"rejected":0}}'
        )]);
        $reporter = $this->reporter($transport, new MemoryDedupStore());

        $ok = $reporter->overdueDailyBatchSync([[
            'user_id' => '42',
            'app_id' => '5001',
            'phone' => 'test-phone',
            'order_no' => 'ORDER-1',
            'current_overdue_days' => 2,
            'max_overdue_days' => 3,
        ]], '2026-07-24');

        self::assertTrue($ok);
        self::assertSame(7, $transport->requests[0]['timeout']);
    }

    public function testInvalidAsyncBatchIsRejectedBeforeDispatch(): void
    {
        $reporter = $this->reporter(new FakeTransport(), new MemoryDedupStore());

        $this->expectException(InvalidArgumentException::class);
        $reporter->reportBatch([['event_type' => 'custom']]);
    }

    /**
     * @param array<int, array<string, mixed>> $logs
     */
    private function reporter(FakeTransport $transport, MemoryDedupStore $store, array &$logs = []): DataMidReporter
    {
        $client = new DataMidClient([
            'base_url' => 'http://data-mid',
            'country_code' => 'ng',
            'client_id' => 'country-api',
            'secret' => 'test-secret',
        ], $transport);
        $factory = new EventFactory('ng', static function (): int {
            return 1784851200;
        }, static function (): string {
            return 'nonce';
        });
        $deduplicator = new ProducerDeduplicator(
            $store,
            120,
            'test:',
            static function (): string {
                return 'lease';
            }
        );

        return new DataMidReporter(
            $client,
            $factory,
            $deduplicator,
            new InlineDispatcher(),
            ['dedup_ttl' => 600, 'batch_timeout' => 7],
            static function (string $channel, string $message, string $level, array $context) use (&$logs): void {
                $logs[] = compact('channel', 'message', 'level', 'context');
            }
        );
    }
}
