# ADR 030: Treatment Plan Items, Search, and Ordered Reindexing

Date: `2026-03-10`

## Status

Accepted

## Context

The canonical source and ADR `029` already define:

- treatment-plan CRUD and lifecycle routes
- the treatment-plan search filter contract
- item subresource routes:
  - `GET /treatment-plans/{planId}/items`
  - `POST /treatment-plans/{planId}/items`
  - `PATCH /treatment-plans/{planId}/items/{itemId}`
  - `DELETE /treatment-plans/{planId}/items/{itemId}`
- `T043` as the task that operationalizes treatment items, search, and bulk treatment behaviors

Before `T043`, the docs do not define:

- the treatment-item field set
- whether treatment items are editable after a plan has started
- how item ordering works
- what search should match besides the fixed filter list from ADR `029`
- whether `T043` introduces a separate bulk mutation route

These decisions are required before implementation.

## Decision

Implement tenant-scoped treatment-plan search plus ordered treatment-item subresources. `T043` does not add a new bulk route because the canonical route inventory does not define one.

### Treatment Item Ownership

Each treatment item owns:

- `item_id`
- `tenant_id`
- `plan_id`
- `item_type`
- `title`
- optional `description`
- optional `instructions`
- `sort_order`
- `created_at`
- `updated_at`

### Treatment Item Type Catalog

`item_type` is one of:

- `assessment`
- `procedure`
- `medication`
- `therapy`
- `lab`
- `follow_up`
- `other`

### Item Lifecycle and Edit Guards

Treatment items are ordered plan subresources, not a separate state machine.

- Item reads are allowed for any active, non-deleted treatment plan in tenant scope.
- Item writes are allowed only while the parent treatment plan is:
  - `draft`
  - `approved`
- Active, paused, finished, and rejected plans are read-only for item mutations.
- Item delete is a hard delete.

### Ordered Reindexing

Treatment items always expose a contiguous one-based `sort_order`.

- Creating an item without `sort_order` appends it to the end of the plan.
- Creating an item with `sort_order` inserts at that position and shifts following siblings down.
- Updating `sort_order` repositions the item and reindexes the affected sibling range in the same transaction.
- Deleting an item closes the gap by shifting later siblings up in the same transaction.

This ordered reindexing is the only multi-row mutation behavior introduced in `T043`.

### Search Behavior

`GET /treatment-plans/search` implements the filter contract fixed by ADR `029`:

- `q`
- `status`
- `patient_id`
- `provider_id`
- `planned_from`
- `planned_to`
- `created_from`
- `created_to`
- `limit`

Search rules:

- default `limit` is `25`
- maximum `limit` is `100`
- `planned_to` must be on or after `planned_from`
- `created_to` must be on or after `created_from`
- active search results exclude soft-deleted treatment plans

Query matching uses:

- treatment-plan id
- treatment-plan title
- patient display name
- provider display name
- treatment-item titles within the plan

Search responses include:

- ordered treatment-plan result data
- `meta.filters` echoing the effective filters

### Read Model Enrichment

Treatment-plan directory, detail, and search responses expose `item_count` so later encounter work can reference whether a plan already has planned treatment steps without loading the full item collection first.

### Audit Contract

`T043` adds immutable treatment-item audit actions:

- `treatment_plan_items.created`
- `treatment_plan_items.updated`
- `treatment_plan_items.deleted`

All treatment-item audit events use `object_type = treatment_plan_item` and include `plan_id` metadata.

### No Separate Bulk Route

`T043` does not introduce `/treatment-plans/bulk` or `/treatment-plans/{planId}/items/bulk`.

Reason:

- the canonical route inventory does not define a bulk treatment-plan or bulk treatment-item route
- `T044` already reserves encounter bulk behavior through `POST /encounters/bulk`
- ordered sibling reindexing provides the only required multi-row mutation in this phase without changing the documented route surface

## Consequences

- `T043` can ship search and item management without inventing new route inventory.
- Treatment plans become richer read models through `item_count`.
- `T044` can link encounters and procedures to stable ordered treatment items.
- Any future explicit bulk treatment mutation route requires a new ADR and a canonical route-inventory update first.
