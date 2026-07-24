<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Toolbox;

final class FeishuNotifyResult
{
    /** @var array<string, mixed> */
    private array $primaryResponse;

    /** @var array<string, mixed> */
    private array $fallbackResponse;

    private string $error;

    private bool $fallbackAttempted;

    /**
     * @param array<string, mixed> $primaryResponse
     * @param array<string, mixed> $fallbackResponse
     */
    public function __construct(
        array $primaryResponse = [],
        array $fallbackResponse = [],
        string $error = '',
        bool $fallbackAttempted = false
    ) {
        $this->primaryResponse = $primaryResponse;
        $this->fallbackResponse = $fallbackResponse;
        $this->error = $error;
        $this->fallbackAttempted = $fallbackAttempted;
    }

    public function isSuccessful(): bool
    {
        return $this->error === '';
    }

    /** @return array<string, mixed> */
    public function primaryResponse(): array
    {
        return $this->primaryResponse;
    }

    /** @return array<string, mixed> */
    public function fallbackResponse(): array
    {
        return $this->fallbackResponse;
    }

    public function error(): string
    {
        return $this->error;
    }

    public function fallbackAttempted(): bool
    {
        return $this->fallbackAttempted;
    }
}
