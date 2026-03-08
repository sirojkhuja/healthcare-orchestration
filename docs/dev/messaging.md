# Messaging

This document defines the shared Kafka outbox and consumer foundation.

## Core Rules

- Every event-producing business transaction writes an outbox record in the same database transaction as business data.
- Kafka publication never happens inline from controllers.
- Relay delivery is at-least-once.
- Consumer processing must be idempotent.
- Retry behavior must use bounded backoff.

## Outbox Contract

- Outbox records live in `outbox_messages`.
- Required fields include `event_id`, `event_type`, `topic`, `payload`, tenant metadata, request metadata (`request_id`, `correlation_id`, `causation_id`), attempts, retry schedule, and delivery timestamps.
- Ready outbox records are claimed in batches by `OutboxRelay`.
- Successful relay marks the outbox row `delivered`.
- Failed relay attempts increment `attempts`, store the last error, and set `next_attempt_at` unless retry attempts are exhausted.

## Transport Contract

- The current Kafka transport implementation uses `longlang/phpkafka` because the runtime provides sockets but not `ext-rdkafka`.
- Broker bootstrap servers come from `KAFKA_BROKERS`.
- Producer retries are handled both by the Kafka client and the outbox retry policy.

## Consumer Contract

- Consumers implement `KafkaConsumerHandler`.
- `IdempotentKafkaConsumerBus` records processed message IDs in `kafka_consumer_receipts`.
- Duplicate message replays for the same consumer name are skipped after the first successful handling.
- Consumer messages must provide `event_id` in headers or as the Kafka message key.

## Operational Hooks

- `outbox:drain` publishes ready outbox records.
- Outbox lag metrics come from ready-count and oldest-ready-age calculations.
- Future ops work must expose admin views for outbox backlog, lag, retry, and replay.

## Testing Requirements

- Tests must prove outbox publishing marks records delivered.
- Tests must prove relay failures schedule retries with bounded backoff.
- Tests must prove consumer replay is skipped after a receipt is recorded.
