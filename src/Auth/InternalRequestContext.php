<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Auth;

use Internal\ServiceSdk\Exception\InternalServiceException;

/**
 * Trusted caller context forwarded by an internal BFF such as toolbox-service.
 */
final class InternalRequestContext
{
    private string $actorId;

    private string $actorName;

    private string $scopes;

    private string $requestId;

    public function __construct(
        string $actorId = '',
        string $actorName = '',
        string $scopes = '',
        string $requestId = ''
    ) {
        $this->assertHeaderValue($actorId, 80, 'actor ID');
        $this->assertHeaderValue($actorName, 120, 'actor name');
        $this->assertHeaderValue($scopes, 4096, 'scopes');
        $this->assertHeaderValue($requestId, 191, 'request ID');

        $this->actorId = $actorId;
        $this->actorName = $actorName;
        $this->scopes = $scopes;
        $this->requestId = $requestId;
    }

    public function actorId(): string
    {
        return $this->actorId;
    }

    public function actorName(): string
    {
        return $this->actorName;
    }

    public function scopes(): string
    {
        return $this->scopes;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    private function assertHeaderValue(string $value, int $maxLength, string $name): void
    {
        if (strlen($value) > $maxLength || preg_match('/[\r\n]/', $value) === 1) {
            throw new InternalServiceException(sprintf('invalid internal request %s', $name));
        }
    }
}
