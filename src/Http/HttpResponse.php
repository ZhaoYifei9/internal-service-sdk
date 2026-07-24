<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Http;

final class HttpResponse
{
    private int $statusCode;

    private string $body;

    /** @var array<string, mixed> */
    private array $headers;

    /** @param array<string, mixed> $headers */
    public function __construct(int $statusCode, string $body = '', array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->headers = $headers;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string, mixed> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : [];
    }
}
