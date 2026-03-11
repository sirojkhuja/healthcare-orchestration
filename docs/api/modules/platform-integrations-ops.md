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
