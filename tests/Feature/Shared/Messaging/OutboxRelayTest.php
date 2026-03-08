<?php

use App\Shared\Application\Contracts\KafkaProducer;
use App\Shared\Application\Contracts\OutboxRepository;
use App\Shared\Application\Data\OutboxMessage;
use App\Shared\Application\Services\OutboxRelay;
use App\Shared\Infrastructure\Messaging\Persistence\OutboxMessageRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Fixtures\Messaging\FakeKafkaProducer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-03-08 10:00:00');

    $producer = new FakeKafkaProducer;
    $this->app->instance(KafkaProducer::class, $producer);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('publishes ready outbox messages and marks them delivered', function (): void {
    $repository = app(OutboxRepository::class);
    $relay = app(OutboxRelay::class);
    $producer = app(KafkaProducer::class);

    $repository->enqueue(new OutboxMessage(
        outboxId: Str::uuid()->toString(),
        eventId: Str::uuid()->toString(),
        eventType: 'patient.created',
        topic: 'medflow.patient.v1',
        tenantId: Str::uuid()->toString(),
        requestId: Str::uuid()->toString(),
        correlationId: Str::uuid()->toString(),
        causationId: Str::uuid()->toString(),
        partitionKey: 'patient-1',
        headers: ['source' => 'test'],
        payload: ['patient_id' => 'patient-1'],
    ));

    $result = $relay->drain(10);

    expect($result->claimed)->toBe(1);
    expect($result->delivered)->toBe(1);
    expect($result->failed)->toBe(0);
    expect($producer)->toBeInstanceOf(FakeKafkaProducer::class);
    expect($producer->published)->toHaveCount(1);
    expect(OutboxMessageRecord::query()->value('status'))->toBe(OutboxMessageRecord::STATUS_DELIVERED);
});

it('retries failed outbox publications with bounded backoff', function (): void {
    config()->set('medflow.kafka.outbox.max_attempts', 3);
    config()->set('medflow.kafka.outbox.backoff_seconds', 1);
    config()->set('medflow.kafka.outbox.backoff_max_seconds', 5);

    $repository = app(OutboxRepository::class);
    $relay = app(OutboxRelay::class);
    /** @var FakeKafkaProducer $producer */
    $producer = app(KafkaProducer::class);
    $producer->failuresRemaining = 1;
    $outboxId = Str::uuid()->toString();

    $repository->enqueue(new OutboxMessage(
        outboxId: $outboxId,
        eventId: Str::uuid()->toString(),
        eventType: 'appointment.scheduled',
        topic: 'medflow.scheduling.v1',
        tenantId: Str::uuid()->toString(),
        requestId: Str::uuid()->toString(),
        correlationId: Str::uuid()->toString(),
        causationId: Str::uuid()->toString(),
        partitionKey: 'appointment-1',
        headers: [],
        payload: ['appointment_id' => 'appointment-1'],
    ));

    $firstAttempt = $relay->drain(10);
    $failedRecord = OutboxMessageRecord::query()->findOrFail($outboxId);

    expect($firstAttempt->failed)->toBe(1);
    expect($failedRecord->status)->toBe(OutboxMessageRecord::STATUS_FAILED);
    expect($failedRecord->attempts)->toBe(1);
    expect($failedRecord->next_attempt_at?->equalTo(CarbonImmutable::now()->addSecond()))->toBeTrue();

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addSecond());

    $secondAttempt = $relay->drain(10);
    $deliveredRecord = OutboxMessageRecord::query()->findOrFail($outboxId);

    expect($secondAttempt->delivered)->toBe(1);
    expect($producer->published)->toHaveCount(1);
    expect($deliveredRecord->status)->toBe(OutboxMessageRecord::STATUS_DELIVERED);
});
