<?php

use App\Modules\Pharmacy\Domain\Prescriptions\InvalidPrescriptionTransition;
use App\Modules\Pharmacy\Domain\Prescriptions\Prescription;
use App\Modules\Pharmacy\Domain\Prescriptions\PrescriptionActor;
use App\Modules\Pharmacy\Domain\Prescriptions\PrescriptionStatus;

it('issues and dispenses prescriptions through the documented lifecycle', function (): void {
    $actor = new PrescriptionActor(type: 'user', id: 'user-1', name: 'Doctor');
    $prescription = Prescription::reconstitute(
        prescriptionId: 'rx-1',
        tenantId: 'tenant-1',
        status: PrescriptionStatus::DRAFT,
    );

    $prescription->issue(new DateTimeImmutable('2026-03-10T09:00:00+05:00'), $actor);
    $prescription->dispense(new DateTimeImmutable('2026-03-10T10:00:00+05:00'), $actor);

    expect($prescription->status())->toBe(PrescriptionStatus::DISPENSED);
    expect($prescription->snapshot()['issued_at'])->toBe('2026-03-10T09:00:00+05:00');
    expect($prescription->snapshot()['dispensed_at'])->toBe('2026-03-10T10:00:00+05:00');
});

it('rejects invalid transitions and requires cancel reasons', function (): void {
    $actor = new PrescriptionActor(type: 'user', id: 'user-2', name: 'Doctor');
    $prescription = Prescription::reconstitute(
        prescriptionId: 'rx-2',
        tenantId: 'tenant-1',
        status: PrescriptionStatus::DRAFT,
    );

    expect(fn () => $prescription->dispense(new DateTimeImmutable('2026-03-10T12:00:00+05:00'), $actor))
        ->toThrow(InvalidPrescriptionTransition::class);

    expect(fn () => $prescription->cancel(new DateTimeImmutable('2026-03-10T12:10:00+05:00'), $actor, ''))
        ->toThrow(InvalidPrescriptionTransition::class);

    $prescription->issue(new DateTimeImmutable('2026-03-10T12:20:00+05:00'), $actor);
    $prescription->cancel(new DateTimeImmutable('2026-03-10T12:30:00+05:00'), $actor, 'Medication unavailable');

    expect(fn () => $prescription->dispense(new DateTimeImmutable('2026-03-10T12:40:00+05:00'), $actor))
        ->toThrow(InvalidPrescriptionTransition::class);
});

it('exposes the documented prescription status catalog', function (): void {
    expect(PrescriptionStatus::all())->toBe([
        'draft',
        'issued',
        'dispensed',
        'canceled',
    ]);
});
