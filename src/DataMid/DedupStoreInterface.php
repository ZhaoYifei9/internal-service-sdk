<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\DataMid;

/**
 * Atomic storage operations required by producer-side event deduplication.
 */
interface DedupStoreInterface
{
    public function putIfAbsent(string $key, string $token, int $ttl): bool;

    public function has(string $key): bool;

    public function replaceIfOwned(string $key, string $token, int $ttl): void;

    public function deleteIfOwned(string $key, string $token): void;
}
