<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use Internal\ServiceSdk\DataMid\BatchReportResult;
use Internal\ServiceSdk\Http\HttpResponse;
use PHPUnit\Framework\TestCase;

final class BatchReportResultTest extends TestCase
{
    public function testCompleteBatchRequiresHttp200AndEveryEventAccepted(): void
    {
        $complete = BatchReportResult::fromHttpResponse(new HttpResponse(
            200,
            '{"data":{"accepted":500,"rejected":0,"errors":[]}}'
        ));
        $partial = BatchReportResult::fromHttpResponse(new HttpResponse(
            200,
            '{"data":{"accepted":499,"rejected":1,"errors":[{"error":"invalid app"}]}}'
        ));
        $failed = BatchReportResult::fromHttpResponse(new HttpResponse(503, ''));

        self::assertTrue($complete->isComplete(500));
        self::assertFalse($partial->isComplete(500));
        self::assertSame(499, $partial->accepted());
        self::assertSame(1, $partial->rejected());
        self::assertSame('invalid app', $partial->firstError());
        self::assertFalse($failed->isComplete(0));
        self::assertSame(503, $failed->statusCode());
    }
}
