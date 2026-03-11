# Revenue and Insurance API

## Billing, Pricing, Invoices, and Payments

- `GET /services` -> `ListBillableServicesQuery` -> Billing
- `POST /services` -> `CreateBillableServiceCommand` -> Billing
- `PATCH /services/{serviceId}` -> `UpdateBillableServiceCommand` -> Billing
- `DELETE /services/{serviceId}` -> `DeleteBillableServiceCommand` -> Billing
- `GET /price-lists` -> `ListPriceListsQuery` -> Billing
- `POST /price-lists` -> `CreatePriceListCommand` -> Billing
- `GET /price-lists/{priceListId}` -> `GetPriceListQuery` -> Billing
- `PATCH /price-lists/{priceListId}` -> `UpdatePriceListCommand` -> Billing
- `DELETE /price-lists/{priceListId}` -> `DeletePriceListCommand` -> Billing
- `PUT /price-lists/{priceListId}/items` -> `SetPriceListItemsCommand` -> Billing
- `GET /invoices` -> `ListInvoicesQuery` -> Billing
- `POST /invoices` -> `CreateInvoiceCommand` -> Billing
- `GET /invoices/{invoiceId}` -> `GetInvoiceQuery` -> Billing
- `PATCH /invoices/{invoiceId}` -> `UpdateInvoiceCommand` -> Billing
- `DELETE /invoices/{invoiceId}` -> `DeleteInvoiceCommand` -> Billing
- `POST /invoices/{invoiceId}:issue` -> `IssueInvoiceCommand` -> Billing
- `POST /invoices/{invoiceId}:void` -> `VoidInvoiceCommand` -> Billing
- `POST /invoices/{invoiceId}:finalize` -> `FinalizeInvoiceCommand` -> Billing
- `GET /invoices/{invoiceId}/items` -> `ListInvoiceItemsQuery` -> Billing
- `POST /invoices/{invoiceId}/items` -> `AddInvoiceItemCommand` -> Billing
- `PATCH /invoices/{invoiceId}/items/{itemId}` -> `UpdateInvoiceItemCommand` -> Billing
- `DELETE /invoices/{invoiceId}/items/{itemId}` -> `RemoveInvoiceItemCommand` -> Billing
- `GET /invoices/search` -> `SearchInvoicesQuery` -> Billing
- `GET /invoices/export` -> `ExportInvoicesQuery` -> Billing
- `GET /payments` -> `ListPaymentsQuery` -> Billing
- `POST /payments:initiate` -> `InitiatePaymentCommand` -> Billing
- `GET /payments/{paymentId}` -> `GetPaymentQuery` -> Billing
- `GET /payments/{paymentId}/status` -> `GetPaymentStatusQuery` -> Billing
- `POST /payments/{paymentId}:cancel` -> `CancelPaymentCommand` -> Billing
- `POST /payments/{paymentId}:refund` -> `RefundPaymentCommand` -> Billing
- `POST /payments/{paymentId}:capture` -> `CapturePaymentCommand` -> Billing
- `POST /payments:reconcile` -> `ReconcilePaymentsCommand` -> Billing
- `GET /payments/reconciliation-runs` -> `ListReconciliationRunsQuery` -> Billing
- `GET /payments/reconciliation-runs/{runId}` -> `GetReconciliationRunQuery` -> Billing
- `POST /webhooks/payme` -> `HandlePaymeWebhookCommand` -> Integrations
- `POST /webhooks/click` -> `HandleClickWebhookCommand` -> Integrations
- `POST /webhooks/uzum` -> `HandleUzumWebhookCommand` -> Integrations
- `POST /webhooks/payme:verify` -> `VerifyPaymeWebhookCommand` -> Integrations
- `POST /webhooks/click:verify` -> `VerifyClickWebhookCommand` -> Integrations
- `POST /webhooks/uzum:verify` -> `VerifyUzumWebhookCommand` -> Integrations

## Billing Notes

- `T048` defines the billing catalog contract in ADR `035`.
- Billable services are tenant-scoped catalog records with `code`, `name`, optional `category`, optional `unit`, optional `description`, and `is_active`.
- Billable service `code` is required, normalized to uppercase, and unique per tenant.
- `GET /services` supports `q`, `category`, `is_active`, and `limit`.
- Referenced billable services cannot be deleted while any price-list item still points at them.
- Price lists are tenant-scoped pricing containers with `code`, `name`, optional `description`, `currency`, `is_default`, `is_active`, optional `effective_from`, and optional `effective_to`.
- Setting `is_default=true` on a price list clears any other tenant default price list.
- `GET /price-lists` supports `q`, `is_active`, `is_default`, `active_on`, and `limit`.
- `PUT /price-lists/{priceListId}/items` fully replaces the item set.
- Price-list item payloads use `service_id` plus a positive decimal `amount`.
- Empty item arrays are valid and clear the price list.
- `T049` defines the invoice aggregate contract in ADR `036`.
- Invoice status values are `draft`, `issued`, `finalized`, and `void`.
- Invoice creation requires `patient_id`, uses optional `price_list_id`, and requires `currency` only when no price list is linked.
- Invoice numbers are tenant-scoped monotonic values in the form `INV-000001`.
- Generic `PATCH /invoices/{invoiceId}` is draft-only.
- `DELETE /invoices/{invoiceId}` is a soft delete limited to `draft|void`.
- Invoice item writes are draft-only and use service snapshots plus snapped unit pricing.
- Invoice items accept `service_id`, optional `description`, `quantity`, and optional `unit_price_amount`.
- Omitting `unit_price_amount` requires the invoice to reference a price list containing the selected service.
- Invoice totals are calculated as the sum of line subtotals; taxes, discounts, and credits are deferred beyond `T049`.
- `POST /invoices/{invoiceId}:issue` requires at least one item and a positive total.
- `POST /invoices/{invoiceId}:finalize` requires the invoice to already be `issued`.
- `POST /invoices/{invoiceId}:void` requires a non-empty reason and is terminal.
- `GET /invoices` and `GET /invoices/search` support `q`, `status`, `patient_id`, `issued_from`, `issued_to`, `due_from`, `due_to`, `created_from`, `created_to`, and `limit`.
- `GET /invoices/export` supports CSV export for the same invoice filters with a maximum limit of `1000`.

## Payment Notes

- `T050` defines the payment aggregate and initiation contract in ADR `037`.
- `T051` defines payment HTTP operations and local gateway behavior in ADR `038`.
- Payment status values are `initiated`, `pending`, `captured`, `failed`, `canceled`, and `refunded`.
- Payments are tenant-scoped records linked to a single invoice and snapshot `invoice_number`.
- Payment initiation requires `invoice_id`, `provider_key`, and `amount`.
- Payment initiation is allowed only for invoices in `issued|finalized`.
- Payment amount must be positive, may not exceed the linked invoice `total_amount`, and must use the invoice currency.
- `provider_key` is a lowercase slug used to resolve the provider gateway implementation.
- Local payment creation starts in `initiated`.
- API initiation resolves the configured gateway and may immediately advance the local payment from `initiated` to `pending`.
- `GET /payments` supports `q`, `status`, `invoice_id`, `provider_key`, `created_from`, `created_to`, and `limit`.
- `GET /payments/{paymentId}/status` returns a status-focused projection from the local payment record.
- `POST /payments:initiate`, `POST /payments/{paymentId}:capture`, `POST /payments/{paymentId}:cancel`, and `POST /payments/{paymentId}:refund` all require `Idempotency-Key`.
- Allowed forward transitions are `initiated -> pending`, `pending -> captured|failed|canceled`, and `captured -> refunded`.
- Refunds are allowed only for captured payments and only when the selected gateway supports refunds.
- Payment creation and transitions write audit records and billing-topic outbox events.
- Payment records do not mutate invoice balances or settlement state in this phase.
- Local development and CI use the configured `manual` and `manual_no_refund` gateways until provider-specific adapters are introduced.
- `T052` defines the Payme Merchant API contract in ADR `039`.
- `provider_key = payme` returns a direct Payme checkout URL built from the documented merchant checkout link parameters.
- Payme public processing uses `POST /webhooks/payme` as a JSON-RPC 2.0 route and always responds with HTTP `200`.
- Payme verification uses the `Authorization` header with Payme Merchant API Basic auth and the configured merchant key.
- Payme links the provider request to MedFlow through `account.payment_id`, which must match the local payment UUID.
- Payme compares amount in tiyin against the local payment amount converted to minor units.
- `POST /webhooks/payme` supports `CheckPerformTransaction`, `CreateTransaction`, `PerformTransaction`, `CancelTransaction`, `CheckTransaction`, and `GetStatement`.
- Payme `CreateTransaction`, `PerformTransaction`, and `CancelTransaction` are replay-safe by provider transaction id and do not require `Idempotency-Key`.
- Payme provider state `1` maps to local `pending`, `2` maps to `captured`, `-1` maps to `canceled`, and `-2` maps to `refunded`.
- `POST /payments/{paymentId}:capture`, `POST /payments/{paymentId}:cancel`, and `POST /payments/{paymentId}:refund` are not supported for `provider_key = payme` in this phase and return `409`.

## Insurance Claims

- `GET /insurance/payers` -> `ListPayersQuery` -> Insurance
- `POST /insurance/payers` -> `CreatePayerCommand` -> Insurance
- `PATCH /insurance/payers/{payerId}` -> `UpdatePayerCommand` -> Insurance
- `DELETE /insurance/payers/{payerId}` -> `DeletePayerCommand` -> Insurance
- `GET /claims` -> `ListClaimsQuery` -> Insurance
- `POST /claims` -> `CreateClaimCommand` -> Insurance
- `GET /claims/{claimId}` -> `GetClaimQuery` -> Insurance
- `PATCH /claims/{claimId}` -> `UpdateClaimCommand` -> Insurance
- `DELETE /claims/{claimId}` -> `DeleteClaimCommand` -> Insurance
- `GET /claims/search` -> `SearchClaimsQuery` -> Insurance
- `GET /claims/export` -> `ExportClaimsQuery` -> Insurance
- `POST /claims/{claimId}:submit` -> `SubmitClaimCommand` -> Insurance
- `POST /claims/{claimId}:start-review` -> `StartClaimReviewCommand` -> Insurance
- `POST /claims/{claimId}:approve` -> `ApproveClaimCommand` -> Insurance
- `POST /claims/{claimId}:deny` -> `DenyClaimCommand` -> Insurance
- `POST /claims/{claimId}:mark-paid` -> `MarkClaimPaidCommand` -> Insurance
- `POST /claims/{claimId}:reopen` -> `ReopenClaimCommand` -> Insurance
- `GET /claims/{claimId}/attachments` -> `ListClaimAttachmentsQuery` -> Insurance
- `POST /claims/{claimId}/attachments` -> `UploadClaimAttachmentCommand` -> Insurance
- `DELETE /claims/{claimId}/attachments/{attachmentId}` -> `DeleteClaimAttachmentCommand` -> Insurance
- `GET /insurance/rules` -> `ListInsuranceRulesQuery` -> Insurance
- `POST /insurance/rules` -> `CreateInsuranceRuleCommand` -> Insurance
- `PATCH /insurance/rules/{ruleId}` -> `UpdateInsuranceRuleCommand` -> Insurance
- `DELETE /insurance/rules/{ruleId}` -> `DeleteInsuranceRuleCommand` -> Insurance

## API Notes

- Payment and claim workflows are state-machine driven and idempotent.
- All provider callback processing must pass through webhook verification and audit.
