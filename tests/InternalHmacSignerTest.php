<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use Internal\ServiceSdk\Auth\InternalHmacSigner;
use PHPUnit\Framework\TestCase;

final class InternalHmacSignerTest extends TestCase
{
    public function testSignatureMatchesTheCrossServiceVector(): void
    {
        $canonical = InternalHmacSigner::canonical(
            'get',
            '/admin/v1/tasks?country_code=BD&limit=20',
            '',
            '1784044800',
            '0123456789abcdef0123456789abcdef',
            'toolbox',
            '42',
            'operator@example.com',
            'mid.tasks.read'
        );

        self::assertSame(
            'c6b190fdcfb082f568986bb88a265ccd30cceed76ce632e5a584920d0c85bbb7',
            hash_hmac('sha256', $canonical, 'test-secret')
        );
        self::assertSame(
            hash_hmac('sha256', $canonical, 'test-secret'),
            InternalHmacSigner::sign(
                'test-secret',
                'get',
                '/admin/v1/tasks?country_code=BD&limit=20',
                '',
                '1784044800',
                '0123456789abcdef0123456789abcdef',
                'toolbox',
                '42',
                'operator@example.com',
                'mid.tasks.read'
            )
        );
    }
}
