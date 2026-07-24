<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\Tests;

use Internal\ServiceSdk\DataMid\OrderContext;
use PHPUnit\Framework\TestCase;

final class OrderContextTest extends TestCase
{
    public function testExtractsTemplateFieldsWithoutLeakingFullBankAccount(): void
    {
        $context = OrderContext::fromOrderRow([
            'productId' => 18,
            'productName' => 'Quick Loan',
            'disburseAmount' => '1350.00',
            'repayment' => '1500.00',
            'bankAccount' => '1234-5678-9012',
        ]);

        self::assertSame('18', $context['product_id']);
        self::assertSame('Quick Loan', $context['product_name']);
        self::assertSame(1350.0, $context['disburse_amount']);
        self::assertSame(1500.0, $context['repayment_amount']);
        self::assertSame('9012', $context['bank_last4']);
        self::assertStringNotContainsString('12345678', json_encode($context, JSON_THROW_ON_ERROR));
    }

    public function testNormalizeDropsUnknownAndSanitizesBankAccount(): void
    {
        self::assertSame([
            'product_id' => '18',
            'bank_last4' => '9012',
            'due_at' => 1784110783,
        ], OrderContext::normalize([
            'product_id' => 18,
            'bank_last4' => '1234-5678-9012',
            'due_at' => '1784110783',
            'full_bank_account' => 'must-not-leave',
        ]));
    }
}
