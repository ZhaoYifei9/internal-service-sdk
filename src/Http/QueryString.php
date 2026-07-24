<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Http;

use Internal\ServiceSdk\Exception\InternalServiceException;

final class QueryString
{
    /** @param array<string, mixed> $query */
    public static function append(string $path, array $query): string
    {
        if ($path === '' || $path[0] !== '/') {
            throw new InternalServiceException('internal service path must start with a slash');
        }
        if (strpos($path, '?') !== false && $query !== []) {
            throw new InternalServiceException('internal service path already contains a query string');
        }

        $query = array_filter($query, static function ($value): bool {
            return $value !== null && $value !== '';
        });
        if ($query === []) {
            return $path;
        }

        ksort($query);
        $encoded = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $encoded === '' ? $path : $path . '?' . $encoded;
    }
}
