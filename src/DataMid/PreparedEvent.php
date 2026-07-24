<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\DataMid;

final class PreparedEvent
{
    /** @var array<string, mixed> */
    private array $payload;

    private string $dedupKey;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(array $payload, string $dedupKey = '')
    {
        $this->payload = $payload;
        $this->dedupKey = $dedupKey !== ''
            ? $dedupKey
            : (string) ($payload['event_id'] ?? '');
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    public function eventType(): string
    {
        return (string) ($this->payload['event_type'] ?? '');
    }

    public function eventId(): string
    {
        return (string) ($this->payload['event_id'] ?? '');
    }

    public function dedupKey(): string
    {
        return $this->dedupKey;
    }
}
