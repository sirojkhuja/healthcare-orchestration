# ADR 035: Billable Services, Price Lists, and Item Replacement

Date: `2026-03-10`

## Status

Accepted

## Context

The canonical source already defines:

- billable service catalog routes under `/services`
- price list routes under `/price-lists`
- replacement-based item management with `PUT /price-lists/{priceListId}/items`
- `T048` as the task that introduces billing catalog behavior before invoices and payments

Before `T048`, the docs do not define:

- the field set for billable services
- the field set for price lists
- list filters for services and price lists
- whether service and price list codes are unique per tenant
- how default price lists behave
- whether deleting a billable service is allowed after pricing references exist
- the item payload and replacement semantics for `PUT /price-lists/{priceListId}/items`

These decisions are required before implementation and before `T049` can build invoices on top of a stable pricing contract.

## Decision

Implement a tenant-scoped billing catalog with a normalized billable-service directory, tenant-owned price lists, and full-replacement price-list item management.

### Billable Service Aggregate

Each billable service owns:

- `service_id`
- `tenant_id`
- `code`
- `name`
- optional `category`
- optional `unit`
- optional `description`
- `is_active`
- `created_at`
- `updated_at`

Rules:

- `code` is required, normalized to uppercase, and unique per tenant
- `name` is required
- `category`, `unit`, and `description` are optional descriptive fields
- `DELETE /services/{serviceId}` is a hard delete
- deletion is rejected when the service is still referenced by any price-list item
- inactive services stay readable and may remain referenced by existing price lists

### Billable Service Read Contract

`GET /services` supports:

- `q`
- `category`
- `is_active`
- `limit`

Rules:

- default `limit` is `25`
- maximum `limit` is `100`
- `q` matches `code`, `name`, `category`, `unit`, and `description`
- results order by `name`, `code`, `created_at`, then `service_id`

### Price List Aggregate

Each price list owns:

- `price_list_id`
- `tenant_id`
- `code`
- `name`
- optional `description`
- `currency`
- `is_default`
- `is_active`
- optional `effective_from`
- optional `effective_to`
- `created_at`
- `updated_at`

The price-list read model also exposes:

- `item_count`
- full `items` on `GET /price-lists/{priceListId}`

Rules:

- `code` is required, normalized to uppercase, and unique per tenant
- `name` is required
- `currency` is required, normalized to uppercase, and must be a three-letter code
- `effective_to` must be on or after `effective_from` when both are present
- setting `is_default=true` on create or update clears the previous tenant default list or lists
- the default handoff is audited as updates on both the newly default list and the displaced list
- `DELETE /price-lists/{priceListId}` hard-deletes the list and cascades its items

### Price List Read Contract

`GET /price-lists` supports:

- `q`
- `is_active`
- `is_default`
- `active_on`
- `limit`

Rules:

- default `limit` is `25`
- maximum `limit` is `100`
- `q` matches `code`, `name`, `description`, and `currency`
- `active_on` filters to price lists where:
  - `is_active = true`
  - `effective_from` is null or on/before the date
  - `effective_to` is null or on/after the date
- results order by `is_default desc`, `is_active desc`, `name`, `code`, then `price_list_id`

### Price List Item Contract

Each price-list item owns:

- `price_list_item_id`
- `tenant_id`
- `price_list_id`
- `service_id`
- `unit_price_amount`
- `created_at`
- `updated_at`

The read model also embeds the referenced service snapshot:

- `service.id`
- `service.code`
- `service.name`
- `service.category`
- `service.unit`
- `service.is_active`

`PUT /price-lists/{priceListId}/items` accepts:

- `items[]`
  - `service_id`
  - `amount`

Rules:

- the request fully replaces the current item set
- omitted services are removed from the price list
- an empty array clears the price list
- `service_id` values must be distinct within the payload
- every referenced service must exist in the current tenant
- `amount` must be a positive decimal with up to two fraction digits
- the response exposes money as:
  - `unit_price.amount`
  - `unit_price.currency`
- item responses order by service name, service code, then item id

### Audit Contract

`T048` writes immutable audit actions:

- `billable_services.created`
- `billable_services.updated`
- `billable_services.deleted`
- `price_lists.created`
- `price_lists.updated`
- `price_lists.deleted`
- `price_lists.items_replaced`

All billable-service audit events use `object_type = billable_service`.

All price-list audit events use `object_type = price_list`.

## Consequences

- `T049` can build invoices and invoice items on top of stable tenant-owned service and pricing references.
- billing catalog retirement uses `is_active=false` instead of deleting referenced services out from under price lists.
- a price list remains a reusable pricing container while invoice tasks can later snapshot selected service and price data into invoice items.
