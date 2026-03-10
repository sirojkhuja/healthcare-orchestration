<?php

namespace App\Modules\Billing\Domain\Invoices;

final class InvoiceTransitionRules
{
    public static function assertCanFinalize(InvoiceStatus $status): void
    {
        if ($status !== InvoiceStatus::ISSUED) {
            self::reject('Only issued invoices can be finalized.');
        }
    }

    public static function assertCanIssue(InvoiceStatus $status, int $itemCount, string $totalAmount): void
    {
        if ($status !== InvoiceStatus::DRAFT) {
            self::reject('Only draft invoices can be issued.');
        }

        if ($itemCount < 1) {
            self::reject('Invoices require at least one item before they can be issued.');
        }

        if ((float) $totalAmount <= 0.0) {
            self::reject('Invoices require a positive total before they can be issued.');
        }
    }

    public static function assertCanVoid(InvoiceStatus $status, string $reason): void
    {
        self::assertReason($reason, 'Voiding an invoice requires a reason.');

        if ($status === InvoiceStatus::VOID) {
            self::reject('Void invoices are terminal.');
        }
    }

    private static function assertReason(string $reason, string $message): void
    {
        if (trim($reason) === '') {
            self::reject($message);
        }
    }

    private static function reject(string $message): never
    {
        throw new InvalidInvoiceTransition($message);
    }
}
