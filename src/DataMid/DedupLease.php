<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\DataMid;

final class DedupLease
{
    private bool $allowed;

    private bool $tracked;

    private string $key;

    private string $token;

    private string $reason;

    public function __construct(
        bool $allowed,
        bool $tracked,
        string $key = '',
        string $token = '',
        string $reason = ''
    ) {
        $this->allowed = $allowed;
        $this->tracked = $tracked;
        $this->key = $key;
        $this->token = $token;
        $this->reason = $reason;
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function isTracked(): bool
    {
        return $this->tracked;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
