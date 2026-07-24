<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use Internal\ServiceSdk\Exception\InternalServiceException;
use Internal\ServiceSdk\Toolbox\AlertCatalog;
use PHPUnit\Framework\TestCase;

final class AlertCatalogTest extends TestCase
{
    public function testResolvesKeysIdsAndDescriptions(): void
    {
        $catalog = new AlertCatalog([
            'SYSTEM_ERROR' => ['id' => 'COUNTRY-SYSTEM', 'desc' => 'System error'],
            'BALANCE' => 'COUNTRY-BALANCE',
        ]);

        self::assertSame(['SYSTEM_ERROR', 'BALANCE'], $catalog->keys());
        self::assertSame('COUNTRY-SYSTEM', $catalog->get('SYSTEM_ERROR')->id());
        self::assertSame('System error', $catalog->description('COUNTRY-SYSTEM'));
        self::assertSame('Unknown', $catalog->description('MISSING', 'Unknown'));
    }

    public function testUnknownKeyIsRejected(): void
    {
        $catalog = new AlertCatalog(['SYSTEM_ERROR' => 'COUNTRY-SYSTEM']);

        $this->expectException(InternalServiceException::class);
        $catalog->get('MISSING');
    }
}
