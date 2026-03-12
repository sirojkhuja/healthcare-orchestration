# Platform, Integrations, Notifications, and Operations API

## Notifications

- `GET /templates` -> `ListTemplatesQuery` -> Notifications
- `POST /templates` -> `CreateTemplateCommand` -> Notifications
- `GET /templates/{templateId}` -> `GetTemplateQuery` -> Notifications
- `PATCH /templates/{templateId}` -> `UpdateTemplateCommand` -> Notifications
- `DELETE /templates/{templateId}` -> `DeleteTemplateCommand` -> Notifications
- `POST /templates/{templateId}:test-render` -> `TestRenderTemplateCommand` -> Notifications
- `POST /notifications:test/sms` -> `SendTestSmsCommand` -> Notifications
- `POST /notifications:test/email` -> `SendTestEmailCommand` -> Notifications
- `POST /notifications:test/telegram` -> `SendTestTelegramCommand` -> Notifications
- `POST /notifications` -> `SendNotificationCommand` -> Notifications
- `GET /notifications` -> `ListNotificationsQuery` -> Notifications
- `GET /notifications/{notificationId}` -> `GetNotificationQuery` -> Notifications
- `POST /notifications/{notificationId}:retry` -> `RetryNotificationCommand` -> Notifications
- `POST /notifications/{notificationId}:cancel` -> `CancelNotificationCommand` -> Notifications
- `GET /notification-providers/sms` -> `ListSmsProvidersQuery` -> Notifications
- `PUT /notification-providers/sms` -> `SetSmsProvidersPriorityCommand` -> Notifications
- `GET /notification-providers/email` -> `GetEmailProviderQuery` -> Notifications
- `PUT /notification-providers/email` -> `SetEmailProviderCommand` -> Notifications
- `GET /notification-providers/telegram` -> `GetTelegramProviderQuery` -> Notifications
- `PUT /notification-providers/telegram` -> `SetTelegramProviderCommand` -> Notifications
- `POST /integrations/eskiz:send` -> `SendEskizSmsCommand` -> Integrations
- `POST /integrations/playmobile:send` -> `SendPlayMobileSmsCommand` -> Integrations
- `POST /integrations/textup:send` -> `SendTextUpSmsCommand` -> Integrations
- `POST /webhooks/telegram` -> `HandleTelegramWebhookCommand` -> Integrations
- `POST /telegram/bot:broadcast` -> `BroadcastTelegramCommand` -> Notifications
- `POST /telegram/bot:sync` -> `SyncTelegramBotCommand` -> Integrations
- `POST /email:send` -> `SendEmailCommand` -> Notifications
- `GET /email/events` -> `ListEmailEventsQuery` -> Notifications

## Notification Notes

- `T056` defines the template contract in ADR `043`.
- `T057` defines the notification queue lifecycle in ADR `044`.
- Templates are tenant-scoped, soft-deletable records with immutable version history.
- Supported channels are `email`, `sms`, and `telegram`.
- Template `code` is required, normalized to uppercase, and unique per tenant among non-deleted templates.
- `email` templates require both `subject_template` and `body_template`.
- `sms` and `telegram` templates require `body_template` and persist `subject_template = null`.
- `GET /templates` supports `q`, `channel`, `is_active`, and `limit`.
- `GET /templates/{templateId}` returns the current projection plus all versions in descending version order.
- Placeholder syntax is `{{path.to.value}}` with dot-path lookup into the `variables` object.
- Test-render accepts only scalar, boolean, or `null` final values; arrays and objects at the final placeholder path return `422`.
- Missing placeholder paths during test-render return `422`.
- `POST /notifications` requires `template_id`, `recipient`, and `variables`; `metadata` is optional.
- The referenced template must exist in the current tenant and be active.
- Notifications snapshot the rendered subject and body at queue time so later template edits do not alter history.
- Notification states are `queued`, `sent`, `failed`, and `canceled`.
- `POST /notifications` creates a `queued` notification record and publishes `notification.queued` to `medflow.notifications.v1`.
- `POST /notifications/{notificationId}:retry` is allowed only from `failed` and returns the record to `queued` when `attempts < max_attempts`.
- `POST /notifications/{notificationId}:cancel` is allowed from `queued|failed` and records an optional cancel reason.
- `GET /notifications` supports `q`, `status`, `channel`, `template_id`, `created_from`, `created_to`, and `limit`.
- `email` recipients require `recipient.email` and optional `recipient.name`.
- `sms` recipients require `recipient.phone_number`.
- `telegram` recipients require `recipient.chat_id`.
- SMS routing message types are `otp`, `reminder`, `transactional`, and `bulk`.
- `metadata.message_type` is the authoritative SMS routing hint when present; appointment reminder sends use `reminder` and appointment confirmation sends use `transactional`.
- `GET /notification-providers/sms` returns configured providers plus the effective ordered provider list for each SMS message type with `source = default|custom`.
- `PUT /notification-providers/sms` accepts a partial replacement set of `{ message_type, providers[] }` entries. Each route must contain unique configured provider keys and at least one provider.
- Default SMS failover order is `otp: eskiz -> playmobile -> textup`, `reminder: playmobile -> eskiz -> textup`, `transactional: eskiz -> playmobile -> textup`, and `bulk: textup -> playmobile -> eskiz`.
- `POST /notifications:test/sms` is a tenant-scoped diagnostic route. It uses the same routing and failover engine as queued SMS delivery, returns the ordered attempt list, and does not create a notification row.
- `POST /integrations/eskiz:send`, `POST /integrations/playmobile:send`, and `POST /integrations/textup:send` are provider-specific diagnostics that force one provider, return the single-provider result, and do not create a notification row.
- The SMS delivery consumer processes only `notification.queued|notification.retried` records whose channel is `sms`, advances them to `sent|failed`, and counts one `attempts` increment per provider attempt.
- SMS delivery writes audit actions `notifications.sent|notifications.failed` and publishes outbox events `notification.sent|notification.failed`, including the ordered delivery-attempt list.
- `GET /notification-providers/email` returns tenant-scoped sender settings with `enabled`, `provider_key`, `from_address`, `from_name`, and optional reply-to fields.
- `PUT /notification-providers/email` fully replaces the tenant email sender settings. Transport credential inventory is now managed through `PUT /integrations/email/credentials`.
- `POST /notifications:test/email` is a tenant-scoped diagnostic route. It uses the configured email adapter directly, returns `notification_test_email_sent|notification_test_email_failed`, and does not create notification or email-event rows.
- queued or retried email notifications are consumed from `medflow.notifications.v1`, use one delivery attempt per send, transition `queued -> sent|failed`, and append one email-event row per outcome.
- `POST /email:send` sends one transactional email directly without creating a notification row and always appends an email-event row with `source = direct`.
- `GET /email/events` returns actual email delivery outcomes only. Diagnostic sends are excluded. Supported filters are `q`, `source`, `event_type`, `notification_id`, `created_from`, `created_to`, and `limit`.
- `GET /notification-providers/telegram` returns tenant-scoped Telegram settings plus the last synced bot snapshot.
- `PUT /notification-providers/telegram` replaces tenant-scoped Telegram settings: `enabled`, `parse_mode`, `broadcast_chat_ids[]`, and `support_chat_ids[]`.
- Telegram chat ids may not be assigned to multiple tenants because webhook tenant resolution must remain deterministic.
- `POST /notifications:test/telegram` is a tenant-scoped diagnostic send and does not create a notification row.
- queued or retried Telegram notifications are consumed from `medflow.notifications.v1`, use one provider attempt per send, and publish `notification.sent|notification.failed`.
- `POST /telegram/bot:broadcast` requires `message` and either explicit `chat_ids[]` or `audience = configured_broadcast|configured_support|all_configured`.
- `POST /webhooks/telegram` verifies `X-Telegram-Bot-Api-Secret-Token`, stores delivery metadata keyed by `update_id`, and records support-chat audit activity for mapped inbound messages.
- `POST /telegram/bot:sync` reconciles `getMe`, `getWebhookInfo`, and the expected webhook URL `APP_URL + /api/v1/webhooks/telegram`.
- Telegram bot credential inventory is now managed through `PUT /integrations/telegram/credentials`.
- Appointment-linked scheduling actions in `T041` select active templates by exact code rather than by template id.
- The reserved appointment-linked template codes are `APPOINTMENT-REMINDER-SMS`, `APPOINTMENT-REMINDER-EMAIL`, `APPOINTMENT-CONFIRMATION-SMS`, and `APPOINTMENT-CONFIRMATION-EMAIL`.
- `T041` resolves appointment-linked recipients from patient `phone` and `email` first, then falls back to ordered patient contacts, and persists an appointment-to-notification linkage record for idempotent reminder windows and confirmation requests.

## Integrations Hub

- `GET /integrations` -> `ListIntegrationsQuery` -> Integrations
- `GET /integrations/{integrationKey}` -> `GetIntegrationQuery` -> Integrations
- `POST /integrations/{integrationKey}:enable` -> `EnableIntegrationCommand` -> Integrations
- `POST /integrations/{integrationKey}:disable` -> `DisableIntegrationCommand` -> Integrations
- `GET /integrations/{integrationKey}/credentials` -> `GetIntegrationCredentialsQuery` -> Integrations
- `PUT /integrations/{integrationKey}/credentials` -> `UpsertIntegrationCredentialsCommand` -> Integrations
- `DELETE /integrations/{integrationKey}/credentials` -> `DeleteIntegrationCredentialsCommand` -> Integrations
- `GET /integrations/{integrationKey}/health` -> `IntegrationHealthQuery` -> Integrations
- `POST /integrations/{integrationKey}:test-connection` -> `TestIntegrationConnectionCommand` -> Integrations
- `GET /integrations/{integrationKey}/logs` -> `ListIntegrationLogsQuery` -> Integrations
- `GET /integrations/{integrationKey}/webhooks` -> `ListIntegrationWebhooksQuery` -> Integrations
- `POST /integrations/{integrationKey}/webhooks` -> `CreateIntegrationWebhookCommand` -> Integrations
- `DELETE /integrations/{integrationKey}/webhooks/{webhookId}` -> `DeleteIntegrationWebhookCommand` -> Integrations
- `POST /integrations/{integrationKey}/webhooks/{webhookId}:rotate-secret` -> `RotateWebhookSecretCommand` -> Integrations
- `GET /integrations/{integrationKey}/tokens` -> `ListIntegrationTokensQuery` -> Integrations
- `POST /integrations/{integrationKey}/tokens:refresh` -> `RefreshIntegrationTokensCommand` -> Integrations
- `DELETE /integrations/{integrationKey}/tokens/{tokenId}` -> `RevokeIntegrationTokenCommand` -> Integrations
- `POST /integrations/myid:verify` -> `VerifyMyIdCommand` -> Integrations
- `POST /webhooks/myid` -> `HandleMyIdWebhookCommand` -> Integrations
- `POST /integrations/eimzo:sign` -> `CreateEImzoSignRequestCommand` -> Integrations
- `POST /webhooks/eimzo` -> `HandleEImzoWebhookCommand` -> Integrations

## Integrations Hub Notes

- Supported catalog keys in this phase are `email`, `telegram`, `eskiz`, `playmobile`, `textup`, `payme`, `click`, `uzum`, `mock-lab`, `myid`, and `eimzo`.
- `GET /integrations` supports optional `category` and `enabled` filters.
- Registry responses expose `available`, `enabled`, capability flags, credential summary, health summary, webhook count, and token count.
- Feature-flagged optional integrations remain visible with `available = false`; `POST /integrations/{integrationKey}:enable` returns `409` while the feature flag stays off.
- `GET /integrations/{integrationKey}/credentials` returns schema plus masked previews. Raw secrets are never returned after persistence.
- `PUT /integrations/{integrationKey}/credentials` fully replaces the tenant-managed payload and accepts only catalog-declared fields inside `values`.
- Credential deletion revokes all active hub-managed tokens for the same integration key.
- `GET /integrations/{integrationKey}/health` returns `status = healthy|degraded|failing|disabled` plus ordered checks for feature flag, credentials, webhooks, and tokens when applicable.
- `POST /integrations/{integrationKey}:test-connection` is a deterministic readiness probe for this phase. It records audit and integration-log entries and does not require live outbound traffic.
- `GET /integrations/{integrationKey}/logs` supports `level`, `event`, and `limit`, defaults to `50`, and caps at `100`.
- `GET /integrations/{integrationKey}/webhooks` returns tenant-managed webhook inventory only. It does not enumerate the underlying Laravel routes automatically.
- `POST /integrations/{integrationKey}/webhooks` requires `name` and derives the callback URL from the catalog. Secret-managed integrations return the generated secret exactly once at creation time.
- `POST /integrations/{integrationKey}/webhooks/{webhookId}:rotate-secret` returns the new secret exactly once and is allowed only for integrations marked as secret-rotatable.
- `DELETE /integrations/{integrationKey}/webhooks/{webhookId}` removes the tenant inventory record, not the underlying public route implementation.
- `GET /integrations/{integrationKey}/tokens` returns token metadata only, including token previews and expiry timestamps.
- `POST /integrations/{integrationKey}/tokens:refresh` supports optional `token_id` and refreshes the latest active token when omitted.
- `DELETE /integrations/{integrationKey}/tokens/{tokenId}` revokes the selected token without deleting history.
- `POST /integrations/myid:verify` requires the `myid` feature flag to be available, the tenant-managed integration to be enabled, managed credentials to exist, and at least one active secret-managed webhook registration.
- `POST /integrations/myid:verify` requires `external_reference` and `subject`, accepts optional `metadata`, returns a tenant-scoped `pending` verification session, and generates the provider reference locally in this phase.
- MyID verification session states are `pending`, `verified`, `rejected`, `expired`, and `failed`.
- `POST /integrations/eimzo:sign` requires the `eimzo` feature flag to be available, the tenant-managed integration to be enabled, managed credentials to exist, and at least one active secret-managed webhook registration.
- `POST /integrations/eimzo:sign` requires `external_reference`, `document_hash`, and `document_name`, accepts optional `signer` and `metadata`, returns a tenant-scoped `pending` sign request, and generates the provider reference locally in this phase.
- E-IMZO sign request states are `pending`, `signed`, `canceled`, `expired`, and `failed`.
- `POST /webhooks/myid` and `POST /webhooks/eimzo` are public routes. They require `X-Integration-Webhook-Secret` plus body fields `webhook_id`, `delivery_id`, `provider_reference`, and `status`.
- Optional plug-in webhook tenant resolution is derived from the managed webhook inventory by `integration_key + webhook_id`, and the provided secret is verified against the stored SHA-256 webhook secret hash.
- Replay protection for optional plug-in webhooks is keyed by `integration_key + webhook_id + delivery_id`; duplicate deliveries return `{ "ok": true }` without mutating state a second time.
- Optional plug-in initiation stays local-first in this phase. Webhook completion remains the authoritative state transition mechanism and every initiation plus processed webhook writes integration-log and audit entries.

## Audit and Compliance

- `GET /audit/events` -> `ListAuditEventsQuery` -> Audit
- `GET /audit/events/{eventId}` -> `GetAuditEventQuery` -> Audit
- `GET /audit/export` -> `ExportAuditEventsQuery` -> Audit
- `GET /audit/retention` -> `GetAuditRetentionQuery` -> Audit
- `PUT /audit/retention` -> `UpdateAuditRetentionCommand` -> Audit
- `GET /audit/object/{objectType}/{objectId}` -> `GetObjectAuditQuery` -> Audit
- `GET /compliance/pii-fields` -> `ListPiiFieldsQuery` -> Compliance
- `PUT /compliance/pii-fields` -> `SetPiiFieldsCommand` -> Compliance
- `POST /compliance/pii:rotate-keys` -> `RotatePiiKeysCommand` -> Compliance
- `POST /compliance/pii:re-encrypt` -> `ReEncryptPiiCommand` -> Compliance
- `GET /consents` -> `ListConsentsQuery` -> Compliance
- `GET /consents/{consentId}` -> `GetConsentQuery` -> Compliance
- `GET /data-access-requests` -> `ListDataAccessRequestsQuery` -> Compliance
- `POST /data-access-requests` -> `CreateDataAccessRequestCommand` -> Compliance
- `POST /data-access-requests/{requestId}:approve` -> `ApproveDataAccessRequestCommand` -> Compliance
- `POST /data-access-requests/{requestId}:deny` -> `DenyDataAccessRequestCommand` -> Compliance
- `GET /data-access-requests/{requestId}` -> `GetDataAccessRequestQuery` -> Compliance
- `GET /compliance/reports` -> `ListComplianceReportsQuery` -> Compliance

## Observability, Admin Ops, Reference Data, and Search

- `GET /health` -> `HealthQuery` -> Ops
- `GET /ready` -> `ReadinessQuery` -> Ops
- `GET /live` -> `LivenessQuery` -> Ops
- `GET /metrics` -> `MetricsQuery` -> Ops
- `GET /version` -> `VersionQuery` -> Ops
- `POST /admin/cache:flush` -> `FlushCacheCommand` -> Ops
- `POST /admin/cache:rebuild` -> `RebuildCachesCommand` -> Ops
- `GET /admin/jobs` -> `ListJobsQuery` -> Ops
- `POST /admin/jobs/{jobId}:retry` -> `RetryJobCommand` -> Ops
- `GET /admin/kafka/lag` -> `GetKafkaLagQuery` -> Ops
- `POST /admin/kafka:replay` -> `ReplayKafkaEventsCommand` -> Ops
- `GET /admin/outbox` -> `ListOutboxQuery` -> Ops
- `POST /admin/outbox:drain` -> `DrainOutboxCommand` -> Ops
- `POST /admin/outbox/{outboxId}:retry` -> `RetryOutboxItemCommand` -> Ops
- `GET /admin/logging/pipelines` -> `ListLoggingPipelinesQuery` -> Ops
- `POST /admin/logging:pipeline-reload` -> `ReloadLoggingPipelinesCommand` -> Ops
- `GET /admin/feature-flags` -> `ListFeatureFlagsQuery` -> Ops
- `PUT /admin/feature-flags` -> `SetFeatureFlagsCommand` -> Ops
- `GET /admin/rate-limits` -> `GetRateLimitsQuery` -> Ops
- `PUT /admin/rate-limits` -> `UpdateRateLimitsCommand` -> Ops
- `GET /admin/config` -> `GetRuntimeConfigQuery` -> Ops
- `POST /admin/config:reload` -> `ReloadRuntimeConfigCommand` -> Ops
- `GET /reference/currencies` -> `ListCurrenciesQuery` -> Shared
- `GET /reference/countries` -> `ListCountriesQuery` -> Shared
- `GET /reference/languages` -> `ListLanguagesQuery` -> Shared
- `GET /reference/diagnosis-codes` -> `ListDiagnosisCodesQuery` -> Shared
- `GET /reference/procedure-codes` -> `ListProcedureCodesQuery` -> Shared
- `GET /reference/insurance-codes` -> `ListInsuranceCodesQuery` -> Shared
- `GET /search/global` -> `GlobalSearchQuery` -> Shared
- `GET /search/patients` -> `SearchPatientsQuery` -> Patient
- `GET /search/providers` -> `SearchProvidersQuery` -> Provider
- `GET /search/appointments` -> `SearchAppointmentsQuery` -> Scheduling
- `GET /search/invoices` -> `SearchInvoicesQuery` -> Billing
- `GET /search/claims` -> `SearchClaimsQuery` -> Insurance
- `GET /reports` -> `ListReportsQuery` -> Reporting
- `POST /reports` -> `CreateReportCommand` -> Reporting
- `GET /reports/{reportId}` -> `GetReportQuery` -> Reporting
- `POST /reports/{reportId}:run` -> `RunReportCommand` -> Reporting
- `GET /reports/{reportId}/download` -> `DownloadReportQuery` -> Reporting
- `DELETE /reports/{reportId}` -> `DeleteReportCommand` -> Reporting

## API Notes

- Platform operations are administrative and must be strongly gated.
- Shared search and reference data endpoints must remain read-focused and tenant-safe.
- Optional integrations such as MyID and E-IMZO must remain feature-flagged until fully enabled.
