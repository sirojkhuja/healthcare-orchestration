# ADR 032: Lab Orders, Results, Webhooks, and Reconciliation

## Status

Accepted

## Date

2026-03-10

## Context

The canonical route inventory already defines:

- `GET /lab-orders`
- `POST /lab-orders`
- `GET /lab-orders/{orderId}`
- `PATCH /lab-orders/{orderId}`
- `DELETE /lab-orders/{orderId}`
- `GET /lab-orders/search`
- `POST /lab-orders/{orderId}:send`
- `POST /lab-orders/{orderId}:cancel`
- `POST /lab-orders/{orderId}:mark-collected`
- `POST /lab-orders/{orderId}:mark-received`
- `POST /lab-orders/{orderId}:mark-complete`
- `GET /lab-orders/{orderId}/results`
- `GET /lab-orders/{orderId}/results/{resultId}`
- `GET /lab-tests`
- `POST /lab-tests`
- `PATCH /lab-tests/{testId}`
- `DELETE /lab-tests/{testId}`
- `POST /webhooks/lab/{provider}`
- `POST /webhooks/lab/{provider}:verify`
- `GET /lab-orders/export`
- `POST /lab-orders/bulk`
- `POST /lab-orders:reconcile`

Before `T045`, the docs do not define:

- the lab-order aggregate field set
- the lab-order status catalog and transition guards
- how lab tests relate to orders
- how webhook result payloads are normalized
- how duplicate webhook deliveries are handled
- what reconciliation updates locally
- what the bulk route updates
- how exports and search behave

`T045` requires these decisions before implementation.

## Decision

Implement lab orders as tenant-scoped single-test orders with an explicit specimen-progress state machine, a tenant-scoped lab-test catalog, read-only result records created through webhook or reconciliation intake, a provider-gateway abstraction for outbound and inbound traffic, and an all-or-nothing bulk route limited to draft orders.

## Lab Test Catalog Contract

Lab tests are tenant-scoped reference records with CRUD plus active-directory reads.

Each lab test owns:

- `test_id`
- `tenant_id`
- `code`
- `name`
- optional `description`
- `specimen_type`
- `result_type`
- optional `unit`
- optional `reference_range`
- `lab_provider_key`
- optional `external_test_code`
- `is_active`
- `created_at`
- `updated_at`

`result_type` is one of:

- `numeric`
- `text`
- `boolean`
- `json`

Rules:

- `code`, `name`, `specimen_type`, `result_type`, and `lab_provider_key` are required
- `lab_provider_key` uses lowercase slug format `[a-z0-9._-]+`
- catalog uniqueness is `(tenant_id, lab_provider_key, code)`
- `DELETE /lab-tests/{testId}` hard-deletes the catalog row
- active orders keep their requested-test snapshot even if the catalog row is later deleted

`GET /lab-tests` is the filterable catalog route and supports:

- `q`
- `lab_provider_key`
- `is_active`
- `limit`

Rules:

- default `limit` is `25`
- maximum `limit` is `100`
- `q` matches `code`, `name`, `external_test_code`, and `description`
- list ordering is `name asc`, `code asc`, `created_at asc`

## Lab Order Aggregate Boundary

Each lab order owns:

- `order_id`
- `tenant_id`
- `patient_id`
- `provider_id`
- optional `encounter_id`
- optional `treatment_item_id`
- nullable `lab_test_id`
- `lab_provider_key`
- requested-test snapshot:
  - `requested_test_code`
  - `requested_test_name`
  - `requested_specimen_type`
  - `requested_result_type`
- `status`
- `ordered_at`
- `timezone`
- optional `notes`
- optional `external_order_id`
- optional `sent_at`
- optional `specimen_collected_at`
- optional `specimen_received_at`
- optional `completed_at`
- optional `canceled_at`
- optional `cancel_reason`
- optional `last_transition`
- soft-delete timestamp `deleted_at`
- `created_at`
- `updated_at`

The lab-order read model also exposes:

- patient display reference
- provider display reference
- optional encounter summary reference
- optional linked treatment item reference
- `result_count`

## Lab Order Status Catalog

`T045` defines this state machine:

- `draft`
- `sent`
- `specimen_collected`
- `specimen_received`
- `completed`
- `canceled`

Allowed transitions:

- `draft -> sent`
- `draft|sent|specimen_collected|specimen_received -> canceled`
- `sent -> specimen_collected`
- `specimen_collected -> specimen_received`
- `specimen_received -> completed`

Remote synchronization through webhook or reconciliation may fast-forward a local order through missing intermediate specimen states in this exact order:

1. `sent`
2. `specimen_collected`
3. `specimen_received`
4. `completed`

Required guards:

- sending is allowed only from `draft`
- canceling requires a non-empty reason
- specimen collection is allowed only from `sent`
- specimen receipt is allowed only from `specimen_collected`
- completion is allowed only from `specimen_received`
- terminal `completed` and `canceled` orders reject further workflow mutations

## Create, Update, Delete, and Bulk Rules

- `POST /lab-orders` creates orders in `draft`
- generic `PATCH /lab-orders/{orderId}` is allowed only while the order is `draft`
- generic patch may change only:
  - `patient_id`
  - `provider_id`
  - `encounter_id`
  - `treatment_item_id`
  - `lab_test_id`
  - `lab_provider_key`
  - `ordered_at`
  - `timezone`
  - `notes`
- changing `lab_test_id` rewrites the requested-test snapshot from the selected catalog row
- `DELETE /lab-orders/{orderId}` is a soft delete and is allowed only while status is:
  - `draft`
  - `canceled`
- active list, search, export, result, and detail reads exclude soft-deleted lab orders

`POST /lab-orders/bulk` is the shared-change bulk route for active `draft` orders only.

Bulk rules:

- requires `Idempotency-Key`
- accepts `order_ids` plus a shared `changes` object
- supports `1..100` distinct ids
- is all-or-nothing
- may update only:
  - `encounter_id`
  - `treatment_item_id`
  - `lab_test_id`
  - `lab_provider_key`
  - `ordered_at`
  - `timezone`
  - `notes`

## Validation and Reference Rules

Create and update validation uses:

- required `patient_id`
- required `provider_id`
- required `lab_test_id` on create
- required `lab_provider_key`
- required `ordered_at`
- required `timezone`
- optional `encounter_id`
- optional `treatment_item_id`
- optional `notes`

Reference rules:

- `patient_id` must reference an active patient in the current tenant
- `provider_id` must reference an active provider in the current tenant
- `lab_test_id` must reference an active lab test in the current tenant
- when `encounter_id` is present:
  - it must reference an active encounter in the current tenant
  - `patient_id` and `provider_id` must match the encounter
- when `treatment_item_id` is present:
  - `encounter_id` is required
  - the encounter must have `treatment_plan_id`
  - the treatment item must belong to that treatment plan
  - the treatment item must use `item_type = lab`
- `timezone` must be a valid timezone identifier

## List, Search, and Export Contract

`GET /lab-orders` and `GET /lab-orders/search` use the same filter contract in `T045`.

Filters:

- `q`
- `status`
- `patient_id`
- `provider_id`
- `encounter_id`
- `lab_test_id`
- `lab_provider_key`
- `ordered_from`
- `ordered_to`
- `created_from`
- `created_to`
- `limit`

Rules:

- default `limit` is `25`
- maximum `limit` is `100`
- `ordered_to` must be on or after `ordered_from`
- `created_to` must be on or after `created_from`
- `q` matches:
  - order id
  - external order id
  - patient display name
  - provider display name
  - requested-test code
  - requested-test name
  - notes
- ordering is:
  1. `ordered_at desc`
  2. `created_at desc`
  3. `id desc`
- responses include `meta.filters`

`GET /lab-orders/export` exports the active search result set to CSV only.

Export rules:

- export accepts the same filters as list/search plus `format`
- `format` currently supports only `csv`
- export `limit` defaults to `1000`
- export `limit` maximum is `1000`
- CSV generation uses `FileStorageManager::storeExport()`
- export writes audit action `lab_orders.exported`

CSV columns are:

- `order_id`
- `status`
- `lab_provider_key`
- `external_order_id`
- `patient_id`
- `patient_display_name`
- `provider_id`
- `provider_display_name`
- `encounter_id`
- `lab_test_id`
- `requested_test_code`
- `requested_test_name`
- `requested_specimen_type`
- `ordered_at`
- `timezone`
- `sent_at`
- `specimen_collected_at`
- `specimen_received_at`
- `completed_at`
- `canceled_at`
- `cancel_reason`
- `result_count`
- `notes`

## Result Contract

Lab results are order-owned read records created only through webhook or reconciliation intake.

Each result owns:

- `result_id`
- `tenant_id`
- `lab_order_id`
- nullable `lab_test_id`
- optional `external_result_id`
- `status`
- `observed_at`
- `received_at`
- `value_type`
- optional `value_numeric`
- optional `value_text`
- optional `value_boolean`
- optional `value_json`
- optional `unit`
- optional `reference_range`
- optional `abnormal_flag`
- optional `notes`
- optional `raw_payload`
- `created_at`
- `updated_at`

`status` is one of:

- `preliminary`
- `final`
- `corrected`

`value_type` is one of:

- `numeric`
- `text`
- `boolean`
- `json`

`abnormal_flag` is one of:

- `normal`
- `low`
- `high`
- `critical`
- `abnormal`

Rules:

- results belong to active, non-deleted lab orders in the current tenant
- webhook and reconciliation intake upsert results by `(lab_order_id, external_result_id)` when `external_result_id` is present
- when `external_result_id` is absent, the intake path creates a new result row
- result reads order by:
  1. `observed_at desc`
  2. `received_at desc`
  3. `id desc`

## Send Contract

`POST /lab-orders/{orderId}:send` is the outbound provider dispatch action.

Rules:

- requires `labs.manage`
- requires `integrations.manage`
- requires `Idempotency-Key`
- uses a provider gateway selected by `lab_provider_key`
- may send only `draft` orders
- stores the returned `external_order_id` and `sent_at`
- transitions the order to `sent`
- writes audit action `lab_orders.sent`
- emits outbox event `lab_order.sent` on topic `medflow.labs.v1`

## Webhook Intake Contract

`POST /webhooks/lab/{provider}` is the public inbound result-intake route.

Rules:

- route `provider` must match the configured provider gateway key
- the request must include:
  - `Idempotency-Key`
  - `X-Lab-Signature`
- the JSON payload must include:
  - `delivery_id`
  - `external_order_id`
  - `status`
  - `occurred_at`
  - optional `results`
- signature verification happens before any state changes
- successful processing persists a webhook-delivery record with provider, delivery id, payload hash, signature hash, order linkage, tenant linkage, and processing outcome
- duplicate webhook deliveries are safe through the combination of route idempotency and persisted delivery metadata
- incoming provider statuses map to local statuses:
  - `sent`
  - `specimen_collected`
  - `specimen_received`
  - `completed`
  - `canceled`
- results are normalized into internal result records before the final response is returned
- webhook processing writes audit action `lab_webhooks.processed`
- webhook verification failures return `401` with `WEBHOOK_SIGNATURE_INVALID`

`POST /webhooks/lab/{provider}:verify` is the authenticated diagnostics helper.

Rules:

- requires authenticated API access
- requires tenant scope
- requires `integrations.manage`
- accepts `signature` plus `payload`
- returns whether the provider signature is valid without mutating business state

## Reconciliation Contract

`POST /lab-orders:reconcile` synchronizes locally tracked orders with the remote provider.

Rules:

- requires `labs.manage`
- requires `integrations.manage`
- requires `Idempotency-Key`
- accepts:
  - required `lab_provider_key`
  - optional `order_ids`
  - optional `limit`
- `limit` defaults to `50`
- `limit` maximum is `100`
- when `order_ids` is omitted, reconciliation scans active tenant orders in statuses:
  - `sent`
  - `specimen_collected`
  - `specimen_received`
- reconciliation uses the same provider gateway abstraction as send and webhook verification
- each remote snapshot is applied through the same result-sync and status-sync logic used by webhook intake
- reconciliation returns:
  - `operation_id`
  - `lab_provider_key`
  - `affected_count`
  - `result_count`
  - synchronized order payloads
- reconciliation writes audit action `lab_orders.reconciled`
- reconciliation emits outbox event `lab_order.reconciled` on topic `medflow.labs.v1`

## Audit and Event Contract

`T045` adds immutable audit actions:

- `lab_tests.created`
- `lab_tests.updated`
- `lab_tests.deleted`
- `lab_orders.created`
- `lab_orders.updated`
- `lab_orders.deleted`
- `lab_orders.sent`
- `lab_orders.canceled`
- `lab_orders.specimen_collected`
- `lab_orders.specimen_received`
- `lab_orders.completed`
- `lab_orders.bulk_updated`
- `lab_orders.exported`
- `lab_orders.reconciled`
- `lab_results.received`
- `lab_results.updated`
- `lab_webhooks.processed`

Outbox event types for this phase are:

- `lab_order.created`
- `lab_order.sent`
- `lab_order.canceled`
- `lab_order.specimen_collected`
- `lab_order.specimen_received`
- `lab_order.completed`
- `lab_order.reconciled`
- `lab_result.received`

## Consequences

- `T045` gains a complete, documented lab-order workflow instead of only a route list.
- Lab traffic stays behind a provider gateway abstraction and can be replaced later by a richer integrations hub without rewriting the Lab module contract.
- Encounter and treatment-item work from `T044` becomes reusable through optional encounter and treatment-item linkage.
- Future provider-specific lab adapters may extend transport details without changing the tenant-facing lab-order API.
