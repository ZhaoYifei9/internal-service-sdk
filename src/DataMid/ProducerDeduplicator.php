<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\DataMid;

use Throwable;

/**
 * Fail-open producer deduplication. The downstream service remains the final
 * idempotency boundary when the optional store is unavailable.
 */
final class ProducerDeduplicator implements DeduplicatorInterface
{
    private DedupStoreInterface $store;

    private int $inflightTtl;

    private string $keyPrefix;

    /** @var callable */
    private $tokenFactory;

    public function __construct(
        DedupStoreInterface $store,
        int $inflightTtl = 120,
        string $keyPrefix = 'internal:report:dedup:',
        ?callable $tokenFactory = null
    ) {
        $this->store = $store;
        $this->inflightTtl = max(30, min(600, $inflightTtl));
        $this->keyPrefix = $keyPrefix;
        $this->tokenFactory = $tokenFactory ?? static function (): string {
            return bin2hex(random_bytes(16));
        };
    }

    public function acquire(string $dedupKey, int $dedupTtl): DedupLease
    {
        if ($dedupTtl <= 0) {
            return new DedupLease(true, false, '', '', 'disabled');
        }

        $key = $this->storageKey($dedupKey);
        $token = (string) ($this->tokenFactory)();

        try {
            if (!$this->store->putIfAbsent($key, $token, $this->inflightTtl)) {
                return new DedupLease(false, true, $key, '', 'duplicate');
            }
        } catch (Throwable $exception) {
            return new DedupLease(true, false, '', '', 'store_unavailable');
        }

        return new DedupLease(true, true, $key, $token, 'acquired');
    }

    public function isSuppressed(string $dedupKey): bool
    {
        try {
            return $this->store->has($this->storageKey($dedupKey));
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function markSucceeded(DedupLease $lease, int $dedupTtl): void
    {
        if (!$lease->isTracked() || $lease->token() === '') {
            return;
        }

        try {
            $this->store->replaceIfOwned(
                $lease->key(),
                $lease->token(),
                max(60, $dedupTtl)
            );
        } catch (Throwable $exception) {
            // The in-flight lease expires automatically; downstream remains idempotent.
        }
    }

    public function release(DedupLease $lease): void
    {
        if (!$lease->isTracked() || $lease->token() === '') {
            return;
        }

        try {
            $this->store->deleteIfOwned($lease->key(), $lease->token());
        } catch (Throwable $exception) {
            // The in-flight lease expires automatically and the event can be retried.
        }
    }

    private function storageKey(string $dedupKey): string
    {
        return $this->keyPrefix . hash('sha256', $dedupKey);
    }
}
