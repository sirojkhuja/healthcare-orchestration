# State Machines and Events

## Workflow Principles

- State transitions are business decisions, not controller decisions.
- Only application handlers may invoke state transitions.
- State machine logic lives in the domain and remains framework-agnostic.
- Every successful transition emits audit data and domain events.
- Kafka publication happens through the outbox pattern, never inline from controllers.

## Appointment State Machine

### States

- `draft`
- `scheduled`
- `confirmed`
- `checked_in`
- `in_progress`
- `completed`
- `canceled`
- `no_show`
- `rescheduled`

### Required Guards

- cannot confirm an appointment scheduled in the past
- cannot check in without confirmation unless an admin override is recorded
- cannot complete without first being `in_progress`
- cannot transition from terminal states without an explicit recovery path

### Operational Notes

- rescheduling must preserve audit history and previous slot reference
- no-show and cancel transitions must capture actor, reason, and timestamp
- reminder dispatch must be idempotent and based on scheduled state windows

## Insurance Claim State Machine

### States

- `draft`
- `submitted`
- `under_review`
- `approved`
- `denied`
- `paid`

### Rules

- only submitted claims can enter review
- only approved claims can become paid
- reopening must preserve the prior adjudication record
- every decision transition requires actor, reason, and source evidence

## Payment State Machine

### States

- `initiated`
- `pending`
- `captured`
- `failed`
- `canceled`
- `refunded`

### Rules

- webhook updates must be idempotent
- reconciliation may confirm or repair local payment status
- refunds are conditional on provider support and prior capture

## Domain Event Catalog

At minimum the platform must support events for:

- `AppointmentScheduled`
- `AppointmentConfirmed`
- `AppointmentCanceled`
- `TreatmentPlanApproved`
- `LabOrderCreated`
- `LabResultReceived`
- `InvoiceIssued`
- `PaymentCaptured`
- `ClaimSubmitted`
- `ClaimApproved`

Each module may define more events, but they must follow the standard envelope and versioning rules.

## Kafka Topics

- `medflow.appointments.v1`
- `medflow.treatments.v1`
- `medflow.billing.v1`
- `medflow.claims.v1`
- `medflow.notifications.v1`
- `medflow.audit.v1`
- `medflow.integrations.v1`

## Event Envelope

Every published event must include:

- `event_id`
- `event_type`
- `occurred_at`
- `tenant_id`
- `correlation_id`
- `causation_id`
- `actor`
- `payload`
- `version`

## Delivery Guarantees

- Delivery is at least once.
- Consumers must be idempotent.
- Retries must use backoff.
- Poison message handling must be observable and recoverable.

## Outbox Pattern

The outbox pattern is required for every event-producing business transaction:

1. persist aggregate changes and outbox record in the same database transaction
2. relay pending outbox records to Kafka
3. mark successful publications as delivered
4. retry failed publications with bounded backoff
5. expose lag and retry metrics for operations
