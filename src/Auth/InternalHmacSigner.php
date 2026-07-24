<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Auth;

/**
 * Internal services share one fixed nine-line HMAC-SHA256 canonical request.
 */
final class InternalHmacSigner
{
    public static function canonical(
        string $method,
        string $pathAndQuery,
        string $body,
        string $timestamp,
        string $nonce,
        string $clientId,
        string $actorId = '',
        string $actorName = '',
        string $scopes = ''
    ): string {
        return implode("\n", [
            strtoupper($method),
            $pathAndQuery,
            hash('sha256', $body),
            $timestamp,
            $nonce,
            $clientId,
            $actorId,
            $actorName,
            $scopes,
        ]);
    }

    public static function sign(
        string $secret,
        string $method,
        string $pathAndQuery,
        string $body,
        string $timestamp,
        string $nonce,
        string $clientId,
        string $actorId = '',
        string $actorName = '',
        string $scopes = ''
    ): string {
        return hash_hmac('sha256', self::canonical(
            $method,
            $pathAndQuery,
            $body,
            $timestamp,
            $nonce,
            $clientId,
            $actorId,
            $actorName,
            $scopes
        ), $secret);
    }
}
