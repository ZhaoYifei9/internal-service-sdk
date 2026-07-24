<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Exception;

final class RemoteServiceException extends InternalServiceException
{
    private int $statusCode;

    private string $remoteCode;

    public function __construct(string $message, int $statusCode = 0, string $remoteCode = '')
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->remoteCode = $remoteCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function remoteCode(): string
    {
        return $this->remoteCode;
    }
}
