# Project Task List

Last Updated: `2026-03-12`

Overall Progress: `82% (58/70 tasks completed)`

Active Task: `None`

## Rules

- Status values must be exactly `Todo`, `In Progress`, `Done`, or `Blocked`.
- At most one task may be `In Progress` at a time.
- A task moves to `Done` only after implementation, docs, tests, formatting, linting, analysis, build, verification, commit, and push are complete.
- Progress equals `Done / Total`.
- If scope changes, update this file in the same change.

## Phase 0: Documentation and Governance

| ID | Status | Task | Depends On | Deliverables | Verification |
| --- | --- | --- | --- | --- | --- |
| T001 | Done | Analyze the canonical SSoT and split it into a working documentation map. | None | `docs/README.md`, split document structure, authority rules. | Docs exist and cross-link cleanly. |
| T002 | Done | Create repository governance artifacts for Codex, including AGENTS guidance, local skill, hooks, and quality scripts. | T001 | `AGENTS.md`, local skill, hooks, scripts, quality files. | Governance files exist and are executable where required. |
| T003 | Done | Initialize Git and configure the remote repository. | None | Git repo, default branch, remote `origin`. | `git status` works and remote is configured. |

## Phase 1: Foundation and Tooling

| ID | Status | Task | Depends On | Deliverables | Verification |
| --- | --- | --- | --- | --- | --- |
| T004 | Done | Bootstrap Laravel 12 with PHP 8.5.3, Composer lock discipline, and pinned core tooling. | T002, T003 | `composer.json`, Laravel app skeleton, pinned dependencies. | `composer validate`, app boots, baseline tests pass. |
| T005 | Done | Create the module skeleton and shared kernel scaffolding under `app/Modules` and `app/Shared`. | T004 | Module directories, base namespaces, service provider wiring. | Autoloading works and architecture tests can discover modules. |
| T006 | Done | Implement the stable command contract for `make format`, `make lint`, `make analyse`, `make test`, `make build`, and `make verify`. | T004 | `Makefile`, Composer scripts, command documentation. | Each command executes successfully or fails with clear actionable output. |
| T007 | Done | Build Docker Compose for app, proxy, PostgreSQL, Redis, Kafka, and observability services with private internal networking. | T004 | `docker-compose.yml`, service configs, local runbook. | Services start and stateful ports stay private. |
| T008 | Done | Add GitHub Actions pipeline scaffolding for governance, quality, and future application CI. | T002, T006, T007 | Workflow YAML files and documented job contract. | Workflows validate on push and PR events. |
| T009 | Done | Add environment templates and config layering for local, test, and production-style settings. | T004, T007 | `.env.example`, config strategy, secrets contract. | Environments boot without committed secrets. |
| T010 | Done | Define shared storage abstractions for attachments, exports, and generated artifacts. | T005 | Storage interfaces, config contract, document handling approach. | Unit tests cover storage selection and path rules. |

## Phase 2: Shared Platform Capabilities

| ID | Status | Task | Depends On | Deliverables | Verification |
| --- | --- | --- | --- | --- | --- |
| T011 | Done | Implement tenant request context resolution and infrastructure-level tenant scoping. | T005, T009 | Middleware, tenant context service, scope adapters. | Feature tests prove no cross-tenant leakage. |
| T012 | Done | Implement correlation, request, and causation ID propagation across HTTP, jobs, and events. | T005 | Request metadata middleware, context propagation helpers. | Logs and traces contain stable IDs end to end. |
| T013 | Done | Implement the standard API error envelope and exception mapping layer. | T004, T005 | Exception handler strategy, shared error DTOs, error codes. | Feature tests validate status codes and payload shape. |
| T014 | Done | Implement the audit event foundation with immutable persistence and actor metadata. | T011, T012, T013 | Audit schema, repository, writer service, retention hooks. | Audit records capture before and after values where required. |
| T015 | Done | Implement authorization policy infrastructure and permission cache invalidation. | T011 | Policy layer, permission projection, invalidation events. | Authorization feature tests and cache invalidation tests pass. |
| T016 | Done | Implement idempotency key storage and middleware for protected commands. | T011, T013 | Idempotency storage, middleware, replay policy. | Duplicate payment and scheduling requests are safely deduplicated. |
| T017 | Done | Create base PostgreSQL migrations, UUID conventions, indexing rules, and common schema primitives. | T004 | Shared migration helpers, base tables, index strategy. | Migration suite runs cleanly on a fresh database. |
| T018 | Done | Implement Redis cache infrastructure with tenant-prefixed keys and explicit invalidation helpers. | T011, T017 | Cache contracts, key builder, invalidation listeners. | Cache tests prove tenant isolation and invalidation behavior. |
| T019 | Done | Implement Kafka outbox schema, relay worker, retry strategy, and consumer base framework. | T017, T012 | Outbox tables, relay process, consumer abstraction, lag metrics. | Integration tests cover publish, retry, and replay behavior. |

## Phase 3: IAM and Organization

| ID | Status | Task | Depends On | Deliverables | Verification |
| --- | --- | --- | --- | --- | --- |
| T020 | Done | Implement authentication endpoints for login, logout, refresh, and current-user retrieval. | T011, T013, T015 | Auth flows, token issuance, auth guards. | Feature tests cover happy path and failure modes. |
| T021 | Done | Implement password reset, session management, and revoke-all session flows. | T020 | Reset tokens, session queries, revoke operations. | Session revocation tests and password reset tests pass. |
| T022 | Done | Implement MFA setup, verification, disable flow, and security event tracking. | T020, T014 | MFA secrets, verification handlers, audit and security events. | MFA tests cover setup, challenge, recovery, and disable. |
| T023 | Done | Implement API keys, device registration, and IP allowlist administration. | T020, T015 | API key lifecycle, device endpoints, IP policy storage. | Feature tests cover creation, revocation, and enforcement. |
| T024 | Done | Implement roles, permissions, permission groups, and RBAC administration APIs. | T015 | RBAC commands, queries, policies, audit integration. | Role assignment and permission propagation tests pass. |
| T025 | Done | Implement user lifecycle management, status transitions, and bulk user operations. | T024 | User CRUD, activate, deactivate, lock, unlock, bulk import and update. | Feature tests cover all status transitions and bulk paths. |
| T026 | Done | Implement profile endpoints, avatar upload, and profile update policies. | T025, T010 | Profile views, update handlers, avatar storage integration. | Storage and authorization tests pass. |
| T027 | Done | Implement tenant lifecycle, limits, usage, and settings management. | T011, T014, T015 | Tenant CRUD, activation, suspension, limits, settings, usage endpoints. | Tenant admin feature tests pass with audit coverage. |
| T028 | Done | Implement clinics, departments, rooms, work hours, holidays, and location endpoints. | T027 | Clinic management APIs and location reference views. | Feature tests cover nested resources and tenant scoping. |

## Phase 4: Patient and Provider Management

| ID | Status | Task | Depends On | Deliverables | Verification |
| --- | --- | --- | --- | --- | --- |
| T029 | Done | Implement the patient aggregate and patient CRUD APIs. | T011, T013, T014 | Patient model, repository, commands, queries, routes. | CRUD and authorization tests pass. |
| T030 | Done | Implement patient search, summary, timeline, and export capabilities. | T029, T010 | Search queries, summary read model, timeline view, export pipeline. | Search relevance and export tests pass. |
| T031 | Done | Implement patient contacts, tags, and document management. | T029, T010 | Contact endpoints, tag assignment, document upload and delete flows. | Attachment, validation, and audit tests pass. |
| T032 | Done | Implement patient consents, insurance links, and external reference mapping. | T029, T010 | Consent endpoints, insurance association, external refs integration. | Integration and tenant-scope tests pass. |
| T033 | Done | Implement the provider aggregate and provider CRUD APIs. | T028 | Provider model, repository, commands, queries, routes. | CRUD and authorization tests pass. |
| T034 | Done | Implement provider profile, specialties, licenses, and provider groups. | T033 | Specialty catalog, license handling, grouping endpoints. | Validation and policy tests pass. |
| T035 | Done | Implement availability rules, slot generation, and cache rebuild operations. | T033, T018 | Availability domain, slot service, rebuild command. | Conflict and availability tests pass. |
| T036 | Done | Implement provider calendar, work hours, time-off, and calendar export. | T035, T010 | Calendar queries, time-off management, export endpoints. | Calendar correctness and export tests pass. |

## Phase 5: Scheduling and Clinical Care

| ID | Status | Task | Depends On | Deliverables | Verification |
| --- | --- | --- | --- | --- | --- |
| T037 | Done | Implement the appointment aggregate and appointment state machine. | T029, T033, T035 | Appointment domain, transition guards, domain events. | State machine unit tests reach full coverage. |
| T038 | Done | Implement appointment CRUD, search, export, and audit views. | T037, T014, T010 | Appointment endpoints, search, export, audit query integration. | Feature tests cover list, search, export, and audit. |
| T039 | Done | Implement appointment participants, notes, and bulk appointment operations. | T038 | Participant management, note endpoints, bulk update commands. | Bulk safety and authorization tests pass. |
| T040 | Done | Implement waitlist, recurrence, reschedule, no-show, cancel, and restore flows. | T037, T039 | Waitlist model, recurrence support, action endpoints. | Transition and calendar consistency tests pass. |
| T041 | Done | Implement reminder and confirmation orchestration for appointments. | T040, T056, T057 | Reminder scheduling, confirmation dispatch, notification linkage. | Idempotency and delivery tests pass. |
| T042 | Done | Implement the treatment plan aggregate and treatment state machine. | T029, T033 | Treatment plan domain, transition rules, search contract. | Unit and feature tests cover plan lifecycle. |
| T043 | Done | Implement treatment items, plan search, and bulk treatment behaviors. | T042 | Item management endpoints and read models. | Search and validation tests pass. |
| T044 | Done | Implement encounters, diagnoses, procedures, and encounter exports. | T042, T010 | Encounter APIs, diagnosis and procedure handling, export flow. | Clinical workflow tests and export tests pass. |
| T045 | Done | Implement lab orders, specimen transitions, lab results, lab test catalog, webhook intake, and reconciliation. | T044, T019, T010 | Lab domain, result ingestion, test catalog, reconciliation jobs. | Integration and webhook tests pass. |

## Phase 6: Revenue and Insurance

| ID | Status | Task | Depends On | Deliverables | Verification |
| --- | --- | --- | --- | --- | --- |
| T046 | Done | Implement the prescription aggregate and prescription lifecycle. | T044 | Prescription domain, issue, cancel, dispense flows. | State transition and authorization tests pass. |
| T047 | Done | Implement medication catalog, medication search, allergies, and patient medication views. | T046 | Medication endpoints, allergy management, patient medication queries. | Search, validation, and patient view tests pass. |
| T048 | Done | Implement billable services and price list management. | T027 | Billing catalog endpoints, price list item management. | CRUD and pricing rule tests pass. |
| T049 | Done | Implement the invoice aggregate, invoice items, and invoice lifecycle. | T048, T029 | Invoice domain, item management, issue, void, finalize actions. | Invoice state and calculation tests pass. |
| T050 | Done | Implement the payment aggregate and payment state machine. | T049, T016 | Payment domain, initiation contract, transition rules. | Unit and feature tests cover payment lifecycle. |
| T051 | Done | Implement payment API operations for initiate, status, capture, cancel, and refund. | T050 | Payment endpoints and idempotent command handling. | Feature tests cover all payment actions. |
| T052 | Done | Implement the Payme adapter, webhook handling, verification, and error mapping. | T051, T019 | Payme contract, adapter, webhook verifier, mapper. | Sandbox or contract tests pass. |
| T053 | Done | Implement the Click adapter, webhook handling, verification, and error mapping. | T051, T019 | Click contract, adapter, webhook verifier, mapper. | Sandbox or contract tests pass. |
| T054 | Done | Implement the Uzum adapter, reconciliation flow, webhook handling, and verification. | T051, T019 | Uzum contract, adapter, reconciliation logic, verifier. | Sandbox or contract tests pass. |
| T055 | Done | Implement insurance payers, rules, claim aggregate, claim attachments, claim search and export, and claim state actions. | T029, T049, T010 | Payer APIs, rule APIs, claim domain, attachment handling, state machine. | Claim lifecycle and rule enforcement tests pass. |

## Phase 7: Notifications and Integrations

| ID | Status | Task | Depends On | Deliverables | Verification |
| --- | --- | --- | --- | --- | --- |
| T056 | Done | Implement template management and test-render endpoints for notification content. | T010 | Template CRUD, render engine, versioned template storage. | Render tests and CRUD feature tests pass. |
| T057 | Done | Implement notification dispatch, listing, retry, and cancel flows. | T056, T019 | Dispatch service, retries, status tracking, notification queries. | Retry, cancellation, and audit tests pass. |
| T058 | Done | Implement SMS routing and failover using Eskiz, Play Mobile, and TextUp. | T057, T019 | SMS strategy, provider adapters, routing policy. | Contract tests prove routing and failover behavior. |
| T059 | Todo | Implement Telegram bot broadcast, inbound webhook handling, and bot sync flows. | T057, T019 | Telegram adapter, webhook processing, broadcast command. | Integration tests cover inbound and outbound Telegram flows. |
| T060 | Todo | Implement email send flows and email event tracking. | T057, T019 | Email adapter, send endpoint, email event query model. | Delivery and event-mapping tests pass. |
| T061 | Todo | Implement integrations hub management for credentials, health, logs, webhooks, and tokens. | T010, T014, T019 | Integration registry endpoints, secure credential store, token refresh flows. | Security, audit, and adapter tests pass. |
| T062 | Todo | Implement optional MyID and E-IMZO plug-ins behind feature flags. | T061 | Optional identity adapters, webhook flows, feature flags. | Feature-flag and contract tests pass. |

## Phase 8: Compliance, Search, and Operations

| ID | Status | Task | Depends On | Deliverables | Verification |
| --- | --- | --- | --- | --- | --- |
| T063 | Todo | Implement audit retention, export, object views, and PII field management. | T014 | Audit query APIs, retention config, PII field registry. | Compliance and audit tests pass. |
| T064 | Todo | Implement consent views and data access request workflows with approval actions. | T032, T063 | Consent endpoints, request workflows, approval and denial actions. | Compliance workflow tests pass. |
| T065 | Todo | Implement health, readiness, liveness, metrics, version, and admin operations endpoints. | T019 | Ops endpoints, cache controls, job retry, outbox and Kafka operations. | Admin authorization and operational tests pass. |
| T066 | Todo | Implement reference data, global search, and reporting endpoints. | T029, T033, T038, T049, T055 | Shared reference APIs, global search, report lifecycle endpoints. | Search relevance and report generation tests pass. |
| T067 | Todo | Implement structured logging, OpenTelemetry, Sentry, Prometheus metrics, Grafana dashboards, and Elastic log shipping. | T012, T019, T065 | Instrumentation, dashboards, alerts, log pipeline config. | Traces, metrics, and alerts work in local and CI validation paths. |

## Phase 9: Hardening and Release Readiness

| ID | Status | Task | Depends On | Deliverables | Verification |
| --- | --- | --- | --- | --- | --- |
| T068 | Todo | Complete production-grade OpenAPI documentation and contract test coverage for the full endpoint surface. | T028, T045, T055, T062, T066 | Complete OpenAPI spec, examples, schema validation, contract tests. | OpenAPI validation and contract suite pass. |
| T069 | Todo | Perform performance, security, and architecture hardening across the platform. | T067, T068 | Performance baselines, architecture checks, security review actions. | Load checks, security checks, and architecture rules pass. |
| T070 | Todo | Finalize release automation, changelog generation, production readiness review, and cutover checklist. | T069 | Release workflow, changelog process, go-live checklist, rollback plan. | Dry-run release succeeds and readiness review is signed off. |
