<?php

use App\Modules\Treatment\Domain\TreatmentPlans\InvalidTreatmentPlanTransition;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentPlan;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentPlanActor;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentPlanEventType;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentPlanStatus;
use Illuminate\Support\Str;

it('approves a draft treatment plan and records the transition event', function (): void {
    $plan = draftTreatmentPlan();

    $plan->approve(treatmentAt('2026-03-10 09:00:00'), treatmentActor());

    expect($plan->status())->toBe(TreatmentPlanStatus::APPROVED);
    expect($plan->snapshot())->toMatchArray([
        'status' => 'approved',
        'approved_at' => '2026-03-10T09:00:00+05:00',
    ]);
    expect($plan->releaseRecordedEvents()[0]->type)->toBe(TreatmentPlanEventType::APPROVED);
});

it('moves through start pause resume and finish transitions in order', function (): void {
    $plan = draftTreatmentPlan();
    $plan->approve(treatmentAt('2026-03-10 09:00:00'), treatmentActor());
    $plan->start(treatmentAt('2026-03-10 09:15:00'), treatmentActor());
    $plan->pause(treatmentAt('2026-03-10 09:45:00'), treatmentActor(), 'Awaiting additional lab work');
    $plan->resume(treatmentAt('2026-03-11 10:00:00'), treatmentActor());
    $plan->finish(treatmentAt('2026-03-12 11:00:00'), treatmentActor());

    expect($plan->status())->toBe(TreatmentPlanStatus::FINISHED);
    expect($plan->snapshot())->toMatchArray([
        'status' => 'finished',
        'started_at' => '2026-03-10T09:15:00+05:00',
        'paused_at' => null,
        'finished_at' => '2026-03-12T11:00:00+05:00',
    ]);
    expect(array_map(
        static fn ($event): string => $event->type->value,
        $plan->releaseRecordedEvents(),
    ))->toBe([
        'treatment_plan.approved',
        'treatment_plan.started',
        'treatment_plan.paused',
        'treatment_plan.resumed',
        'treatment_plan.finished',
    ]);
});

it('requires explicit reasons and rejects invalid treatment plan transitions', function (): void {
    $plan = draftTreatmentPlan();

    expect(fn () => $plan->start(treatmentAt('2026-03-10 09:00:00'), treatmentActor()))
        ->toThrow(InvalidTreatmentPlanTransition::class, 'Only approved treatment plans may be started.');

    expect(fn () => $plan->reject(treatmentAt('2026-03-10 09:01:00'), treatmentActor(), ' '))
        ->toThrow(InvalidTreatmentPlanTransition::class, 'Rejecting a treatment plan requires a reason.');

    $plan->approve(treatmentAt('2026-03-10 09:05:00'), treatmentActor());
    $plan->start(treatmentAt('2026-03-10 09:10:00'), treatmentActor());

    expect(fn () => $plan->reject(treatmentAt('2026-03-10 09:15:00'), treatmentActor(), 'Superseded'))
        ->toThrow(InvalidTreatmentPlanTransition::class, 'Only draft or approved treatment plans may be rejected.');

    expect(fn () => $plan->pause(treatmentAt('2026-03-10 09:20:00'), treatmentActor(), ' '))
        ->toThrow(InvalidTreatmentPlanTransition::class, 'Pausing a treatment plan requires a reason.');
});

it('exposes the documented treatment plan status catalog and rejects invalid actors', function (): void {
    expect(TreatmentPlanStatus::all())->toBe([
        'draft',
        'approved',
        'active',
        'paused',
        'finished',
        'rejected',
    ]);

    expect(fn (): TreatmentPlanActor => new TreatmentPlanActor(' ', null, null))
        ->toThrow(InvalidArgumentException::class, 'Treatment plan actor type is required.');
});

function draftTreatmentPlan(): TreatmentPlan
{
    return TreatmentPlan::draft(
        planId: (string) Str::uuid(),
        tenantId: (string) Str::uuid(),
        patientId: (string) Str::uuid(),
        providerId: (string) Str::uuid(),
        title: 'Initial hypertension care plan',
        summary: 'Monitor blood pressure weekly.',
        goals: 'Reach a stable blood-pressure baseline.',
        plannedStartDate: '2026-03-15',
        plannedEndDate: '2026-04-15',
    );
}

function treatmentActor(): TreatmentPlanActor
{
    return new TreatmentPlanActor('user', 'user-1', 'Clinical Lead');
}

function treatmentAt(string $timestamp): DateTimeImmutable
{
    return new DateTimeImmutable($timestamp, new DateTimeZone('Asia/Tashkent'));
}
