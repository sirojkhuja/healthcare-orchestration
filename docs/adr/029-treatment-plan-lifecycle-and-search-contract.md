# ADR 029: Treatment Plan Lifecycle and Search Contract

Date: `2026-03-10`

## Status

Accepted

## Context

The canonical source already defines:

- treatment-plan CRUD routes
- explicit treatment-plan action routes for `approve`, `start`, `pause`, `resume`, `finish`, and `reject`
- a dedicated Treatment module
- `T042` as the task that introduces the treatment-plan aggregate and state machine
- `T043` as the later task for treatment items, plan search behavior, and bulk treatment flows

Before `T042`, the docs do not define:

- the treatment-plan status catalog
- which state transitions are valid
- which generic CRUD operations remain valid after approval or start
- whether delete is hard-delete or soft-delete
- the minimum treatment-plan field set that later item and encounter work may depend on
- the search-filter contract that `T043` must implement

These decisions are required before implementation.

## Decision

Implement a tenant-scoped treatment-plan aggregate with explicit lifecycle actions, soft-delete retention, and a fixed search-criteria contract for later work.

### Aggregate Ownership

The treatment-plan aggregate owns:

- `plan_id`
- `tenant_id`
- `patient_id`
- `provider_id`
- `title`
- optional `summary`
- optional `goals`
- optional `planned_start_date`
- optional `planned_end_date`
- `status`
- latest transition metadata
- lifecycle timestamps:
  - `approved_at`
  - `started_at`
  - `paused_at`
  - `finished_at`
  - `rejected_at`

Treatment items, encounter linkage, diagnoses, and procedures are explicitly deferred to `T043` and `T044`.

### Status Catalog

`T042` defines these treatment-plan states:

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

### Transition Guards

- only draft treatment plans may be approved
- only approved treatment plans may be started
- only active treatment plans may be paused
- pausing requires a non-empty reason
- only paused treatment plans may be resumed
- only active or paused treatment plans may be finished
- only draft or approved treatment plans may be rejected
- rejecting requires a non-empty reason

### CRUD Scope

`POST /treatment-plans` creates plans only in `draft`.

Generic `PATCH /treatment-plans/{planId}` is allowed only while the plan is:

- `draft`
- `approved`

Generic update may change:

- patient linkage
- provider linkage
- title
- summary
- goals
- planned start date
- planned end date

Generic `DELETE /treatment-plans/{planId}` is a soft delete and is allowed only while the plan is:

- `draft`
- `rejected`

Soft-deleted plans leave active directory reads but remain available for audit history.

### Validation Rules

Treatment plans must resolve active tenant-scoped:

- `patient_id`
- `provider_id`

`planned_end_date` must be on or after `planned_start_date` when both are present.

### Audit Contract

`T042` writes immutable audit actions:

- `treatment_plans.created`
- `treatment_plans.updated`
- `treatment_plans.deleted`
- `treatment_plans.approved`
- `treatment_plans.started`
- `treatment_plans.paused`
- `treatment_plans.resumed`
- `treatment_plans.finished`
- `treatment_plans.rejected`

All treatment-plan audit events use `object_type = treatment_plan`.

### Search Contract for T043

`T042` fixes the treatment-plan search filter contract so later work can implement it without redefining semantics.

Supported filters:

- `q`
- `status`
- `patient_id`
- `provider_id`
- `planned_from`
- `planned_to`
- `created_from`
- `created_to`
- `limit`

Search remains a later route-level feature in `T043`, but the repository and DTO contract are established now.

## Consequences

- `T042` can ship treatment-plan CRUD plus lifecycle behavior without waiting for item and encounter work.
- `T043` must reuse the fixed search contract and may not redefine treatment-plan statuses.
- Future encounter and treatment-item work can reference a stable plan lifecycle and immutable plan audit history.
