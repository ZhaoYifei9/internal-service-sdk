<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\DataMid;

interface DeduplicatorInterface
{
    public function acquire(string $dedupKey, int $dedupTtl): DedupLease;

    public function isSuppressed(string $dedupKey): bool;

    public function markSucceeded(DedupLease $lease, int $dedupTtl): void;

    public function release(DedupLease $lease): void;
}
