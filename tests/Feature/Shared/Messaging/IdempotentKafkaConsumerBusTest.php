<?php

use App\Shared\Application\Data\ConsumedKafkaMessage;
use App\Shared\Application\Services\IdempotentKafkaConsumerBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\Messaging\FakeKafkaConsumerHandler;

uses(RefreshDatabase::class);

it('skips replayed consumer messages after the first successful processing', function (): void {
    $consumerBus = app(IdempotentKafkaConsumerBus::class);
    $handler = new FakeKafkaConsumerHandler;
    $message = new ConsumedKafkaMessage(
        messageId: 'evt-123',
        topic: 'medflow.testing.v1',
        partition: 0,
        key: 'evt-123',
        headers: ['event_id' => 'evt-123'],
        payload: ['status' => 'ok'],
    );

    $firstResult = $consumerBus->dispatch($handler, $message);
    $secondResult = $consumerBus->dispatch($handler, $message);

    expect($firstResult->processed)->toBeTrue();
    expect($secondResult->processed)->toBeFalse();
    expect($handler->messages)->toHaveCount(1);
    expect(\App\Shared\Infrastructure\Messaging\Persistence\ConsumerReceiptRecord::query()->count())->toBe(1);
});
