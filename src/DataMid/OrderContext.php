<?php

declare(strict_types=1);

namespace Internal\ServiceSdk\DataMid;

/**
 * Allows only message-template order fields to leave a business database.
 */
final class OrderContext
{
    /** @return array<string, int|float|string> */
    public static function fromOrderRow(array $row): array
    {
        $context = [];
        foreach (['productId' => 'product_id', 'productName' => 'product_name'] as $source => $target) {
            $value = trim((string) ($row[$source] ?? ''));
            if ($value !== '') {
                $context[$target] = $value;
            }
        }
        foreach (['disburseAmount' => 'disburse_amount', 'repayment' => 'repayment_amount'] as $source => $target) {
            $value = $row[$source] ?? null;
            if (is_numeric($value) && (float) $value > 0) {
                $context[$target] = (float) $value;
            }
        }
        $bankLast4 = self::bankLast4((string) ($row['bankAccount'] ?? ''));
        if ($bankLast4 !== '') {
            $context['bank_last4'] = $bankLast4;
        }

        return $context;
    }

    /** @return array<string, int|float|string> */
    public static function normalize(array $context): array
    {
        $normalized = [];
        foreach (['product_id', 'product_name', 'bank_last4'] as $field) {
            $value = trim((string) ($context[$field] ?? ''));
            if ($value !== '') {
                $normalized[$field] = $field === 'bank_last4'
                    ? self::bankLast4($value)
                    : $value;
            }
        }
        foreach (['disburse_amount', 'repayment_amount', 'due_at'] as $field) {
            if (isset($context[$field]) && is_numeric($context[$field])) {
                $normalized[$field] = $field === 'due_at'
                    ? (int) $context[$field]
                    : (float) $context[$field];
            }
        }

        return $normalized;
    }

    public static function bankLast4(string $bankAccount): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9]/', '', $bankAccount) ?? '';
        if ($normalized === '') {
            return '';
        }

        return strlen($normalized) <= 4 ? $normalized : substr($normalized, -4);
    }
}
