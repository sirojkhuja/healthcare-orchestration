<?php

use App\Modules\Billing\Domain\Invoices\InvalidInvoiceTransition;
use App\Modules\Billing\Domain\Invoices\Invoice;
use App\Modules\Billing\Domain\Invoices\InvoiceActor;
use App\Modules\Billing\Domain\Invoices\InvoiceStatus;

it('issues and finalizes invoices through the documented lifecycle', function (): void {
    $actor = new InvoiceActor(type: 'user', id: 'user-1', name: 'Billing Admin');
    $invoice = Invoice::reconstitute(
        invoiceId: 'inv-1',
        tenantId: 'tenant-1',
        itemCount: 2,
        totalAmount: '150000.00',
        status: InvoiceStatus::DRAFT,
    );

    $invoice->issue(new DateTimeImmutable('2026-03-11T09:00:00+05:00'), $actor);
    $invoice->finalize(new DateTimeImmutable('2026-03-11T09:10:00+05:00'), $actor);

    expect($invoice->status())->toBe(InvoiceStatus::FINALIZED);
    expect($invoice->snapshot()['issued_at'])->toBe('2026-03-11T09:00:00+05:00');
    expect($invoice->snapshot()['finalized_at'])->toBe('2026-03-11T09:10:00+05:00');
});

it('requires positive totals and reasons for invoice transitions', function (): void {
    $actor = new InvoiceActor(type: 'user', id: 'user-2', name: 'Billing Admin');
    $invoice = Invoice::reconstitute(
        invoiceId: 'inv-2',
        tenantId: 'tenant-1',
        itemCount: 0,
        totalAmount: '0.00',
        status: InvoiceStatus::DRAFT,
    );

    expect(fn () => $invoice->issue(new DateTimeImmutable('2026-03-11T10:00:00+05:00'), $actor))
        ->toThrow(InvalidInvoiceTransition::class);

    $readyInvoice = Invoice::reconstitute(
        invoiceId: 'inv-3',
        tenantId: 'tenant-1',
        itemCount: 1,
        totalAmount: '75000.00',
        status: InvoiceStatus::ISSUED,
    );

    expect(fn () => $readyInvoice->void(new DateTimeImmutable('2026-03-11T10:05:00+05:00'), $actor, ''))
        ->toThrow(InvalidInvoiceTransition::class);

    $readyInvoice->void(new DateTimeImmutable('2026-03-11T10:10:00+05:00'), $actor, 'Entered in error');

    expect($readyInvoice->status())->toBe(InvoiceStatus::VOID);
    expect(fn () => $readyInvoice->finalize(new DateTimeImmutable('2026-03-11T10:15:00+05:00'), $actor))
        ->toThrow(InvalidInvoiceTransition::class);
});

it('exposes the documented invoice status catalog', function (): void {
    expect(InvoiceStatus::all())->toBe([
        'draft',
        'issued',
        'finalized',
        'void',
    ]);
    expect(InvoiceStatus::VOID->isTerminal())->toBeTrue();
    expect(InvoiceStatus::ISSUED->isTerminal())->toBeFalse();
});
