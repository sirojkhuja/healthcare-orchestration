# ADR 036: Invoice Lifecycle, Items, and Pricing Contract

Date: `2026-03-11`

## Status

Accepted

## Context

The canonical source already defines invoice routes under `/invoices`, item subresources under `/invoices/{invoiceId}/items`, and explicit lifecycle actions for `:issue`, `:void`, and `:finalize`.

Before `T049`, the docs do not define:

- the invoice field set
- invoice status values and transition rules
- how invoice numbers are assigned
- which invoice fields remain editable after creation
- how invoice items source pricing from price lists
- how invoice totals are calculated
- which search and export filters exist

These decisions are required before implementation and before payment work in `T050` can rely on stable invoice behavior.

## Decision

Implement invoices as tenant-scoped aggregates with draft-first creation, explicit lifecycle actions, snapshot-based line items, and monotonic tenant invoice numbering.

### Invoice Aggregate

Each invoice owns:

- `invoice_id`
- `tenant_id`
- `invoice_number`
- `patient_id`
- optional `price_list_id`
- `currency`
- `invoice_date`
- optional `due_on`
- optional `notes`
- `status`
- `subtotal_amount`
- `total_amount`
- `item_count`
- optional `issued_at`
- optional `finalized_at`
- optional `voided_at`
- optional `void_reason`
- optional `last_transition`
- optional `deleted_at`
- `created_at`
- `updated_at`

The invoice read model also exposes:

- `patient.id`
- `patient.display_name`
- optional `price_list.id`
- optional `price_list.code`
- optional `price_list.name`
- `totals.subtotal.amount`
- `totals.total.amount`
- `totals.currency`
- `items[]` on `GET /invoices/{invoiceId}`

Rules:

- invoices are tenant-scoped
- `patient_id` is required and must reference an active patient in the current tenant
- `price_list_id` is optional and must reference a tenant price list when present
- `currency` is required when `price_list_id` is omitted
- when `price_list_id` is present, `currency` is derived from the linked price list and must match it if supplied explicitly
- `invoice_date` defaults to the current tenant-local calendar day when omitted
- `due_on` is optional and must be on or after `invoice_date` when present
- `notes` is optional free text
- `subtotal_amount` and `total_amount` are calculated values, not writable fields
- `total_amount` equals `subtotal_amount` in `T049`; taxes, discounts, credits, and payment allocations are deferred to later tasks

### Invoice Numbering

Each tenant owns a monotonic invoice counter.

Rules:

- invoice numbers are assigned on create
- format is `INV-000001`, `INV-000002`, and so on within each tenant
- numbers are never reused
- gaps are allowed when draft invoices are deleted or transactions roll back after number allocation

### Invoice Status Catalog

Invoice status values are:

- `draft`
- `issued`
- `finalized`
- `void`

Allowed transitions:

- `draft -> issued`
- `issued -> finalized`
- `draft|issued|finalized -> void`

Required guards:

- only draft invoices may be updated through generic `PATCH /invoices/{invoiceId}`
- only draft or void invoices may be deleted through `DELETE /invoices/{invoiceId}`
- only draft invoices may add, update, or remove invoice items
- `issue` requires at least one invoice item and a positive `total_amount`
- `finalize` requires the invoice to already be `issued`
- `void` requires a non-empty reason
- `void` is terminal
- `finalized` is read-only except for `void`

Operational notes:

- `issue` records `issued_at` and emits `InvoiceIssued`
- `finalize` records `finalized_at`
- `void` records `voided_at`, `void_reason`, and transition metadata
- payments are not part of `T049`; later payment tasks may add additional guards around voiding invoices with captured funds

### Invoice CRUD Contract

`POST /invoices` accepts:

- `patient_id`
- optional `price_list_id`
- optional `currency`
- optional `invoice_date`
- optional `due_on`
- optional `notes`

Rules:

- create always returns a `draft` invoice
- generic patch remains draft-only
- draft patches may update:
  - `patient_id`
  - `price_list_id`
  - `currency`
  - `invoice_date`
  - `due_on`
  - `notes`
- `currency` may not be patched while `price_list_id` is present unless the value still matches the linked price-list currency
- `price_list_id` and `currency` may not be changed once the invoice already has items
- clearing `price_list_id` is only allowed while the invoice has zero items and requires an explicit `currency`
- delete is a soft delete limited to `draft|void`

### Invoice Item Contract

Each invoice item owns:

- `invoice_item_id`
- `tenant_id`
- `invoice_id`
- `service_id`
- `service_code`
- `service_name`
- optional `service_category`
- optional `service_unit`
- optional `description`
- `quantity`
- `unit_price_amount`
- `line_subtotal_amount`
- `currency`
- `created_at`
- `updated_at`

`POST /invoices/{invoiceId}/items` accepts:

- `service_id`
- optional `description`
- `quantity`
- optional `unit_price_amount`

`PATCH /invoices/{invoiceId}/items/{itemId}` accepts:

- optional `service_id`
- optional `description`
- optional `quantity`
- optional `unit_price_amount`

Rules:

- item writes are limited to invoices in `draft`
- `service_id` must reference an active billable service in the current tenant
- `quantity` must be a positive decimal with up to two fraction digits
- `unit_price_amount` must be a positive decimal with up to two fraction digits when provided
- when `unit_price_amount` is omitted, the invoice must have `price_list_id` and the referenced service must exist on that price list
- invoice items snapshot service code, name, category, and unit at write time
- invoice item pricing snapshots the chosen unit price at write time even if the price list changes later
- item responses expose money as:
  - `unit_price.amount`
  - `unit_price.currency`
  - `line_subtotal.amount`
  - `line_subtotal.currency`
- item responses order by `created_at` then `invoice_item_id`

### Totals Contract

Rules:

- each item calculates `line_subtotal_amount = quantity * unit_price_amount`
- the multiplication is rounded to two fraction digits using standard half-up rounding
- invoice `subtotal_amount` is the sum of all current item subtotals
- invoice `total_amount` equals `subtotal_amount`
- removing or editing items immediately recalculates invoice totals inside the same transaction

### Read, Search, and Export Contract

`GET /invoices` and `GET /invoices/search` support:

- `q`
- `status`
- `patient_id`
- `issued_from`
- `issued_to`
- `due_from`
- `due_to`
- `created_from`
- `created_to`
- `limit`

Rules:

- default `limit` is `25`
- maximum `limit` is `100`
- export maximum `limit` is `1000`
- `q` matches invoice number, patient display name, and notes
- list and search return the same tenant-scoped read model and differ only by route intent
- results order by `COALESCE(finalized_at, issued_at, created_at) DESC`, then `invoice_number DESC`
- export format is `csv` in `T049`

### Audit and Event Contract

`T049` writes immutable audit actions:

- `invoices.created`
- `invoices.updated`
- `invoices.deleted`
- `invoices.issued`
- `invoices.finalized`
- `invoices.voided`
- `invoice_items.created`
- `invoice_items.updated`
- `invoice_items.deleted`
- `invoices.exported`

All invoice audit events use `object_type = invoice`.

All invoice-item audit events use `object_type = invoice_item`.

`T049` publishes outbox-backed billing events:

- `invoice.created`
- `invoice.issued`
- `invoice.finalized`
- `invoice.voided`

The billing Kafka topic remains `medflow.billing.v1`.

## Consequences

- `T049` provides a stable invoice aggregate for payment and claim tasks without prematurely introducing tax, discount, or balance-allocation rules.
- invoice items keep price and service snapshots so later catalog changes do not mutate historical invoices.
- the split between `issue` and `finalize` preserves the route inventory already defined by the canonical source while keeping the aggregate explicitly state-machine driven.
