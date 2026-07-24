<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use Internal\ServiceSdk\DataMid\DedupStoreInterface;
use Internal\ServiceSdk\DataMid\ProducerDeduplicator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProducerDeduplicatorTest extends TestCase
{
    public function testAcquiresAndFinalizesAnOwnedLease(): void
    {
        $store = new MemoryDedupStore();
        $deduplicator = new ProducerDeduplicator(
            $store,
            45,
            'report:',
            static function (): string {
                return 'lease-token';
            }
        );

        $lease = $deduplicator->acquire('business-key', 300);

        self::assertTrue($lease->isAllowed());
        self::assertTrue($lease->isTracked());
        self::assertSame('acquired', $lease->reason());
        self::assertSame('report:' . hash('sha256', 'business-key'), $lease->key());
        self::assertSame(['lease-token', 45], $store->values[$lease->key()]);

        $deduplicator->markSucceeded($lease, 300);
        self::assertSame(['done', 300], $store->values[$lease->key()]);
        self::assertTrue($deduplicator->isSuppressed('business-key'));
    }

    public function testDuplicateIsSuppressedAndStoreFailureFailsOpen(): void
    {
        $store = new MemoryDedupStore();
        $deduplicator = new ProducerDeduplicator($store, 120, '', static function (): string {
            return 'token';
        });

        self::assertTrue($deduplicator->acquire('same', 60)->isAllowed());
        $duplicate = $deduplicator->acquire('same', 60);
        self::assertFalse($duplicate->isAllowed());
        self::assertSame('duplicate', $duplicate->reason());

        $store->fail = true;
        $bypass = $deduplicator->acquire('another', 60);
        self::assertTrue($bypass->isAllowed());
        self::assertFalse($bypass->isTracked());
        self::assertSame('store_unavailable', $bypass->reason());
        self::assertFalse($deduplicator->isSuppressed('same'));
    }

    public function testReleasesOnlyTheOwnedLease(): void
    {
        $store = new MemoryDedupStore();
        $deduplicator = new ProducerDeduplicator($store, 120, '', static function (): string {
            return 'owned';
        });
        $lease = $deduplicator->acquire('event', 60);

        $deduplicator->release($lease);

        self::assertFalse($store->has($lease->key()));
    }
}

final class MemoryDedupStore implements DedupStoreInterface
{
    /** @var array<string, array{0:string,1:int}> */
    public array $values = [];

    public bool $fail = false;

    public function putIfAbsent(string $key, string $token, int $ttl): bool
    {
        $this->guard();
        if (isset($this->values[$key])) {
            return false;
        }
        $this->values[$key] = [$token, $ttl];
        return true;
    }

    public function has(string $key): bool
    {
        $this->guard();
        return isset($this->values[$key]);
    }

    public function replaceIfOwned(string $key, string $token, int $ttl): void
    {
        $this->guard();
        if (($this->values[$key][0] ?? '') === $token) {
            $this->values[$key] = ['done', $ttl];
        }
    }

    public function deleteIfOwned(string $key, string $token): void
    {
        $this->guard();
        if (($this->values[$key][0] ?? '') === $token) {
            unset($this->values[$key]);
        }
    }

    private function guard(): void
    {
        if ($this->fail) {
            throw new RuntimeException('store unavailable');
        }
    }
}
