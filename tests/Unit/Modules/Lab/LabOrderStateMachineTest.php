<?php

use App\Modules\Lab\Domain\LabOrders\InvalidLabOrderTransition;
use App\Modules\Lab\Domain\LabOrders\LabOrder;
use App\Modules\Lab\Domain\LabOrders\LabOrderActor;
use App\Modules\Lab\Domain\LabOrders\LabOrderStatus;

it('supports the documented forward specimen workflow', function (): void {
    $actor = new LabOrderActor(type: 'user', id: 'user-1', name: 'Lab Admin');
    $order = LabOrder::reconstitute(
        orderId: 'order-1',
        tenantId: 'tenant-1',
        status: LabOrderStatus::DRAFT,
    );

    $order->send(new DateTimeImmutable('2026-03-10T09:00:00+05:00'), $actor, 'external-1');
    $order->markSpecimenCollected(new DateTimeImmutable('2026-03-10T10:00:00+05:00'), $actor);
    $order->markSpecimenReceived(new DateTimeImmutable('2026-03-10T11:00:00+05:00'), $actor);
    $order->complete(new DateTimeImmutable('2026-03-10T12:00:00+05:00'), $actor);

    expect($order->status())->toBe(LabOrderStatus::COMPLETED);
    expect($order->snapshot()['external_order_id'])->toBe('external-1');
    expect($order->snapshot()['completed_at'])->toBe('2026-03-10T12:00:00+05:00');
});

it('rejects invalid transitions and requires cancel reasons', function (): void {
    $actor = new LabOrderActor(type: 'user', id: 'user-2', name: 'Lab Admin');
    $order = LabOrder::reconstitute(
        orderId: 'order-2',
        tenantId: 'tenant-1',
        status: LabOrderStatus::DRAFT,
    );

    expect(fn () => $order->complete(new DateTimeImmutable('2026-03-10T12:00:00+05:00'), $actor))
        ->toThrow(InvalidLabOrderTransition::class);

    $order->send(new DateTimeImmutable('2026-03-10T09:00:00+05:00'), $actor, 'external-2');

    expect(fn () => $order->cancel(new DateTimeImmutable('2026-03-10T10:00:00+05:00'), $actor, ''))
        ->toThrow(InvalidLabOrderTransition::class);

    $order->cancel(new DateTimeImmutable('2026-03-10T10:00:00+05:00'), $actor, 'Patient unavailable');

    expect(fn () => $order->markSpecimenCollected(new DateTimeImmutable('2026-03-10T11:00:00+05:00'), $actor))
        ->toThrow(InvalidLabOrderTransition::class);
});
