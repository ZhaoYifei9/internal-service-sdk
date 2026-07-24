<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\DataMid;

final class NullDeduplicator implements DeduplicatorInterface
{
    public function acquire(string $dedupKey, int $dedupTtl): DedupLease
    {
        return new DedupLease(true, false, '', '', 'disabled');
    }

    public function isSuppressed(string $dedupKey): bool
    {
        return false;
    }

    public function markSucceeded(DedupLease $lease, int $dedupTtl): void
    {
    }

    public function release(DedupLease $lease): void
    {
    }
}
