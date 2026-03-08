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
