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

### Allowed Transitions

- `draft -> scheduled`
- `scheduled -> confirmed`
- `confirmed -> checked_in`
- `scheduled -> checked_in` when an admin override is recorded
- `checked_in -> in_progress`
- `in_progress -> completed`
- `scheduled|confirmed -> canceled`
- `scheduled|confirmed -> no_show` after the scheduled start time
- `scheduled|confirmed -> rescheduled`
- `canceled|no_show|rescheduled -> scheduled` through `restore` while the original slot has not fully elapsed

### Operational Notes

- rescheduling must preserve audit history and previous slot reference
- no-show and cancel transitions must capture actor, reason, and timestamp
- reminder dispatch must be idempotent and based on scheduled state windows

## Treatment Plan State Machine

### States

- `draft`
- `approved`
- `active`
- `paused`
- `finished`
- `rejected`

### Allowed Transitions

- `draft -> approved`
- `approved -> active`
- `active -> paused`
- `paused -> active`
- `active|paused -> finished`
- `draft|approved -> rejected`

### Required Guards

- only draft treatment plans may be approved
- only approved treatment plans may be started
- pausing requires a non-empty reason
- only paused treatment plans may be resumed
- only active or paused treatment plans may be finished
- rejecting requires a non-empty reason and is limited to draft or approved plans

### Operational Notes

- treatment plan CRUD uses draft creation with explicit action routes for lifecycle transitions
- generic patch remains limited to `draft|approved`
- delete is soft-delete and limited to `draft|rejected`
- treatment-plan search and item subresources are implemented in `T043` and must continue to reuse this lifecycle; item writes remain limited to parent plans in `draft|approved`

## Lab Order State Machine

### States

- `draft`
- `sent`
- `specimen_collected`
- `specimen_received`
- `completed`
- `canceled`

### Allowed Transitions

- `draft -> sent`
- `draft|sent|specimen_collected|specimen_received -> canceled`
- `sent -> specimen_collected`
- `specimen_collected -> specimen_received`
- `specimen_received -> completed`

### Required Guards

- only draft lab orders may be sent
- canceling requires a non-empty reason
- specimen collection requires `sent`
- specimen receipt requires `specimen_collected`
- completion requires `specimen_received`
- completed and canceled lab orders are terminal

### Operational Notes

- remote provider sync may fast-forward a local order through missing intermediate specimen states in order
- results are read-only records received through webhook or reconciliation intake
- webhook updates must remain idempotent and signature-verified

## Prescription State Machine

### States

- `draft`
- `issued`
- `dispensed`
- `canceled`

### Allowed Transitions

- `draft -> issued`
- `issued -> dispensed`
- `draft|issued -> canceled`

### Required Guards

- only draft prescriptions may be issued
- only issued prescriptions may be dispensed
- canceling requires a non-empty reason
- dispensed and canceled prescriptions are terminal

### Operational Notes

- prescription CRUD uses draft creation with explicit action routes for lifecycle changes
- generic patch remains draft-only
- delete is soft-delete and limited to `draft|canceled`
- medication catalog linkage remains deferred; prescriptions keep medication snapshot fields directly on the aggregate

## Insurance Claim State Machine

### States

- `draft`
- `submitted`
- `under_review`
- `approved`
- `denied`
- `paid`

### Allowed Transitions

- `draft -> submitted`
- `submitted -> under_review`
- `under_review -> approved|denied`
- `approved -> paid`
- `approved|denied|paid -> submitted` through reopen

### Rules

- only draft claims may use generic CRUD write routes
- only submitted claims can enter review
- only under-review claims can be approved or denied
- only approved claims can become paid
- approve requires a positive amount not greater than billed
- paid requires a positive amount not greater than approved
- reopening must preserve the prior adjudication record
- every decision transition requires actor, reason, and source evidence

## Invoice State Machine

### States

- `draft`
- `issued`
- `finalized`
- `void`

### Allowed Transitions

- `draft -> issued`
- `issued -> finalized`
- `draft|issued|finalized -> void`

### Required Guards

- only draft invoices may be edited through generic CRUD routes
- only draft invoices may mutate invoice items
- issue requires at least one item and a positive total
- only issued invoices may be finalized
- voiding requires a non-empty reason
- void is terminal

### Operational Notes

- invoice create starts in `draft`
- delete is soft-delete and limited to `draft|void`
- issue emits `InvoiceIssued`
- invoice item and total recalculation remain transactionally consistent
- taxes, discounts, and payment allocations are deferred beyond `T049`

## Payment State Machine

### States

- `initiated`
- `pending`
- `captured`
- `failed`
- `canceled`
- `refunded`

### Allowed Transitions

- `initiated -> pending`
- `pending -> captured`
- `pending -> failed`
- `pending -> canceled`
- `captured -> refunded`

### Required Guards

- capture is allowed only from `pending`
- fail is allowed only from `pending`
- cancel is allowed only from `pending`
- refunds are conditional on provider support and prior capture

### Operational Notes

- payment creation starts in `initiated`
- webhook updates must be idempotent
- reconciliation may confirm or repair local payment status through valid forward transitions only
- payment creation validates that the linked invoice is in `issued|finalized`
- payment allocation and invoice-balance mutation remain deferred beyond `T050`

## Notification State Machine

### States

- `queued`
- `sent`
- `failed`
- `canceled`

### Allowed Transitions

- `create -> queued`
- `failed -> queued` through `retry`
- `queued|failed -> canceled`
- later provider tasks may advance `queued -> sent|failed`

### Required Guards

- notification create requires an active template in the current tenant
- retry is allowed only from `failed`
- retry is rejected when `attempts >= max_attempts`
- cancel is allowed only from `queued|failed`
- sent notifications are terminal

### Operational Notes

- `T057` establishes queue-first notification dispatch and does not call external providers directly
- notification records snapshot rendered content and recipient payload at queue time
- external provider tasks must reuse this lifecycle instead of introducing a second delivery model

## Domain Event Catalog

At minimum the platform must support events for:

- `AppointmentScheduled`
- `AppointmentConfirmed`
- `AppointmentCheckedIn`
- `AppointmentStarted`
- `AppointmentCompleted`
- `AppointmentCanceled`
- `AppointmentNoShow`
- `AppointmentRescheduled`
- `AppointmentRestored`
- `TreatmentPlanApproved`
- `LabOrderCreated`
- `LabResultReceived`
- `PrescriptionIssued`
- `InvoiceIssued`
- `PaymentCaptured`
- `ClaimSubmitted`
- `ClaimApproved`
- `NotificationQueued`
- `NotificationRetried`
- `NotificationCanceled`

Each module may define more events, but they must follow the standard envelope and versioning rules.

## Kafka Topics

- `medflow.appointments.v1`
- `medflow.labs.v1`
- `medflow.pharmacy.v1`
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
