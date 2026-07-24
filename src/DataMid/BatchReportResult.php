<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\DataMid;

use Internal\ServiceSdk\Http\HttpResponse;

final class BatchReportResult
{
    private int $statusCode;

    private int $accepted;

    private int $rejected;

    private string $firstError;

    public function __construct(int $statusCode, int $accepted, int $rejected, string $firstError = '')
    {
        $this->statusCode = $statusCode;
        $this->accepted = $accepted;
        $this->rejected = $rejected;
        $this->firstError = $firstError;
    }

    public static function fromHttpResponse(HttpResponse $response): self
    {
        $decoded = $response->json();
        $data = isset($decoded['data']) && is_array($decoded['data'])
            ? $decoded['data']
            : [];
        $accepted = (int) ($data['accepted'] ?? -1);
        $rejected = (int) ($data['rejected'] ?? -1);
        $firstError = '';
        $errors = isset($data['errors']) && is_array($data['errors']) ? $data['errors'] : [];
        $first = isset($errors[0]) && is_array($errors[0]) ? $errors[0] : [];
        if (!empty($first['error'])) {
            $firstError = (string) $first['error'];
        }

        return new self($response->statusCode(), $accepted, $rejected, $firstError);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function accepted(): int
    {
        return $this->accepted;
    }

    public function rejected(): int
    {
        return $this->rejected;
    }

    public function firstError(): string
    {
        return $this->firstError;
    }

    public function isComplete(int $expected): bool
    {
        return $this->statusCode === 200
            && $this->accepted === $expected
            && $this->rejected === 0;
    }
}
