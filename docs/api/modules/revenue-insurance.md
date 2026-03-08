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
