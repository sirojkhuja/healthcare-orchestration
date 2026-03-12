# Healthcare Orchestration & Integration Platform (Laravel)
**Single Source of Truth (SSoT) — Build Spec + Standards + Runbooks + API Catalog**

**Project codename:** MedFlow

**Last updated:** 2026-02-28 (Asia/Tashkent)

---

## 0. Purpose
Build an enterprise-grade, multi-tenant, event-driven **Healthcare Workflow & Integration Platform**:
- Manage patients, providers, clinics, appointments, treatment plans, lab orders, prescriptions, billing, insurance claims.
- Orchestrate workflows with **state machines**.
- Integrate with Uzbekistan services: **Payme, Click, Uzum**, SMS providers (**Eskiz, Play Mobile, TextUp**), **Telegram Bot**, **Email**, **Google Auth**, plus optional **MyID** and **E-IMZO**.
- Provide **tons of endpoints**, complete **API documentation**, and complete **developer documentation**.
- Built to be **AI-agent friendly** (Codex-ready): small files, strict patterns, explicit rules, checklists, and a single reference.

---

## 1. Technology Stack (Pinned)
> Versions pinned at time of writing; patch upgrades allowed only through approved dependency update process.

### Core
- PHP **8.5.3**
- Laravel **12.53.0**
- Composer **2.x** (lockfile enforced)

### Database + Cache
- PostgreSQL **18.3**
- Redis **8.6.0** (GA) **with TLS enabled**

### Messaging / Streaming
- Apache Kafka **4.2.0**

### API Documentation
- OpenAPI **3.1.1**
- Swagger UI **5.31.2**

### Testing & Quality
- Pest **4.4.1**
- PHPStan (max level; pinned in composer)
- Psalm (strict; pinned in composer)
- Laravel Pint (pinned; project config)

### Observability
- Sentry PHP SDK **4.21.0**
- OpenTelemetry Collector **0.146.1**
- Prometheus **3.10.0**
- Grafana **12.3.4**
- Elastic Stack (Elasticsearch/Kibana) **9.3.1**

### Runtime / Deployment
- Docker + Docker Compose
- Nginx (or Caddy) reverse proxy
- Horizon/Queue workers (if using Laravel queues for internal async) + Kafka consumers

---

## 2. Non-Negotiable Engineering Rules (AI-Agent Friendly)
These rules are enforced by CI and must be followed by humans and AI agents.

### 2.1 Small Files & Single Responsibility
- **Max file length:** 250 lines (soft), 400 lines (hard). Exceeding hard limit fails CI.
- **Max method length:** 40 lines.
- **Max class public methods:** 12.
- **Cyclomatic complexity:** keep ≤ 10 per method.

### 2.2 Layering & Dependency Rules (Clean Architecture)
Allowed dependencies:
- `Presentation` → may depend on `Application` interfaces/DTOs.
- `Application` → may depend on `Domain` (entities, value objects, domain services, events).
- `Domain` → depends on nothing else (no Laravel, no Eloquent, no HTTP).
- `Infrastructure` → implements interfaces defined in `Application`/`Domain`.

Forbidden:
- Domain importing Laravel classes.
- Controllers using Eloquent models directly for business logic.
- Controllers making external HTTP calls directly.
- Cross-module imports without public contracts.

### 2.3 Dependency Injection Everywhere
- No `new` in business code except factories.
- All external services accessed through interfaces and injected.
- All integrations behind adapters.

### 2.4 Patterns Required
This project intentionally uses many patterns; use them consistently:
- Repository, Unit of Work (transaction boundary), Factory, Builder
- Strategy, Adapter, Facade (internal), Decorator
- Observer (events), Mediator (event bus), Command
- Chain of Responsibility (pipelines), Specification
- State pattern (state machine)
- CQRS (Commands/Queries) at application layer
- Outbox pattern for Kafka publishing

### 2.5 "No Magic" Rule
- No hidden global helpers for business logic.
- Each use-case must be implemented as an **Application Command/Query handler**.

### 2.6 Security-by-Default
- **Database ports MUST NOT be exposed** in docker-compose.
- All secrets via env / secret manager, never committed.
- TLS for Redis and external integrations.
- OAuth tokens encrypted at rest.

### 2.7 Documentation Required for Every Change
Every PR must include:
- Updated OpenAPI (or explicitly "no API change")
- Updated ADR if architecture decision
- Updated tests
- Updated module docs if behavior changed

---

## 3. Product Requirements
### 3.1 Tenancy
- Multi-tenant by `tenant_id` (clinic network / enterprise group).
- Tenant isolation at DB row-level via:
  - mandatory `tenant_id` column,
  - global query scope in infrastructure only,
  - request context sets tenant.

### 3.2 Roles & Permissions
Roles (baseline):
- SuperAdmin, TenantAdmin, ClinicAdmin, Doctor, Nurse, Receptionist, LabTech, BillingAgent, Patient.

Permissions:
- Fine-grained action permissions, policy-driven.
- Permission caching per user with event-based invalidation.
- Custom roles are tenant-scoped and user-role assignments are tenant-scoped.
- Users are shared identity accounts attached to tenants through tenant-scoped memberships.
- Tenant user lifecycle status is membership-scoped with `active`, `inactive`, and `locked` states.
- Attaching an existing global user account to a tenant must not silently overwrite shared identity fields; shared identity changes go through explicit user update endpoints.
- Shared profile data and avatar metadata live with the global identity account, while tenant-admin profile access remains constrained by tenant membership and `profiles.*` permissions.
- Permission definitions and permission groups are fixed catalog data, not tenant-editable records.

### 3.3 Audit
- Full audit trail for regulated objects:
  - who, what, when, before/after, request-id/correlation-id.
- Write-once storage semantics for audit events.

---

## 4. Architecture
### 4.1 High-Level
- HTTP API (Laravel) is the edge.
- Domain is pure.
- Application orchestrates use-cases.
- Infrastructure provides adapters:
  - DB repositories
  - Kafka producer/consumer
  - Redis caches
  - External integrations

### 4.2 Module Boundaries
Each module has its own domain, application handlers, contracts, and infrastructure.
Modules:
1. Identity & Access (IAM)
2. Tenant & Clinic Management
3. Patient
4. Provider (Doctor/Nurse)
5. Scheduling (Appointment)
6. Treatment Plans
7. Lab Orders & Results
8. Prescriptions
9. Billing & Payments
10. Insurance Claims
11. Notifications
12. Integrations Hub
13. Audit & Compliance
14. Observability

### 4.3 Directory Structure
```
app/
  Modules/
    Scheduling/
      Domain/
      Application/
      Infrastructure/
      Presentation/
    Billing/
    ...
  Shared/
    Domain/
    Application/
    Infrastructure/
bootstrap/
config/
database/
docs/
  api/
  dev/
  adr/
```

---

## 5. Domain Model (Core Aggregates)
### 5.1 Aggregates
- Tenant
- Clinic
- User
- Patient
- Provider
- Appointment
- TreatmentPlan
- LabOrder
- Prescription
- Invoice
- Payment
- InsuranceClaim
- Notification

### 5.2 Value Objects (examples)
- Money (amount, currency)
- PhoneNumber (E.164, country)
- EmailAddress
- NationalId (optional)
- AppointmentSlot
- Address
- ExternalReference (provider + id)

### 5.3 Domain Events (examples)
- AppointmentScheduled
- AppointmentConfirmed
- AppointmentCheckedIn
- AppointmentStarted
- AppointmentCompleted
- AppointmentCanceled
- AppointmentNoShow
- AppointmentRescheduled
- AppointmentRestored
- TreatmentPlanApproved
- LabOrderCreated
- LabResultReceived
- InvoiceIssued
- PaymentCaptured
- ClaimSubmitted
- ClaimApproved

---

## 6. State Machines
### 6.1 Appointment State Machine
States:
- `draft` → `scheduled` → `confirmed` → `checked_in` → `in_progress` → `completed`
- side branches: `canceled`, `no_show`, `rescheduled`

Guards:
- cannot confirm past appointment
- cannot check-in without confirmation (unless admin override)
- cannot complete unless in_progress
- terminal states may only recover through explicit `restore`

Transition rules:
- `schedule`: `draft -> scheduled`
- `confirm`: `scheduled -> confirmed`
- `check-in`: `confirmed -> checked_in`, or `scheduled -> checked_in` only with recorded admin override
- `start`: `checked_in -> in_progress`
- `complete`: `in_progress -> completed`
- `cancel`: `scheduled|confirmed -> canceled`, reason required
- `no-show`: `scheduled|confirmed -> no_show`, reason required, only after scheduled start
- `reschedule`: `scheduled|confirmed -> rescheduled`, reason required, replacement slot reference required
- `restore`: `canceled|no_show|rescheduled -> scheduled`, only while the original slot has not fully elapsed

Aggregate minimum fields:
- `appointment_id`
- `tenant_id`
- `patient_id`
- `provider_id`
- optional `clinic_id`
- optional `room_id`
- `scheduled_start_at`
- `scheduled_end_at`
- `timezone`
- `status`
- latest transition metadata including actor, timestamp, reason, admin override, restored-from status, and replacement-slot reference where applicable

Transitions produce events for Kafka + audit.

### 6.2 Insurance Claim State Machine
- `draft` → `submitted` → `under_review` → `approved|denied` → `paid` (if approved)

### 6.3 Payment State Machine
- `initiated` → `pending` → `captured|failed|canceled` → `refunded` (optional)

### 6.4 Implementation Contract
- All state transitions go through **Application handlers**.
- State machine logic lives in Domain (pure), invoked via Application.

---

## 7. Messaging (Kafka)
### 7.1 Topics
- `medflow.appointments.v1`
- `medflow.treatments.v1`
- `medflow.billing.v1`
- `medflow.claims.v1`
- `medflow.notifications.v1`
- `medflow.audit.v1`
- `medflow.integrations.v1`

### 7.2 Event Envelope (Standard)
Fields:
- `event_id` (UUID)
- `event_type`
- `occurred_at`
- `tenant_id`
- `correlation_id`
- `causation_id`
- `actor` (user_id/service)
- `payload` (schema per event)
- `version`

### 7.3 Delivery Guarantees
- At-least-once processing.
- Idempotency required for consumers.

### 7.4 Outbox Pattern (Required)
- Business transaction writes domain changes + outbox record in same DB transaction.
- Outbox relay publishes to Kafka.
- Relay retries with backoff.

---

## 8. Caching (Redis)
### 8.1 What to Cache
- permission sets
- provider availability
- clinic settings
- integration token metadata
- rate limit buckets
- frequently queried reference data

### 8.2 Patterns
- Cache-aside with explicit invalidation on domain events.
- Tagged caching by tenant.

### 8.3 Security
- Redis TLS + auth
- keys include tenant prefix

---

## 9. Database (PostgreSQL)
### 9.1 Principles
- DB schema is authoritative and versioned by migrations.
- Use UUIDs for public identifiers.
- Separate internal numeric ids allowed but not exposed.

### 9.2 Constraints & Indexing
- Foreign keys enforced.
- Partial indexes for common filters (tenant_id + status + dates).
- Full-text search for patient/provider directories.

---

## 10. Integrations Hub (Uzbekistan + General)
### 10.1 Integration Framework
All integrations follow the same pattern:
- `Contract` (interface) in Application
- `Adapter` in Infrastructure
- `Client` wraps HTTP
- `Authenticator` handles auth
- `RetryPolicy` + `CircuitBreaker`
- `WebhookVerifier` for inbound
- `Mapper` converts external payloads → internal DTOs

### 10.2 Payment Integrations
- Payme
- Click
- Uzum

Common features:
- create payment
- query status
- handle callbacks/webhooks
- reconcile
- refunds (if supported)

### 10.3 Identity
- Google OAuth (login)
- Optional: MyID (KYC / identity verification)
- MyID verification is initiated by a tenant-scoped command and completes through a replay-safe managed webhook using a tenant-managed secret
- MyID verification session states are `pending`, `verified`, `rejected`, `expired`, and `failed`

### 10.4 SMS Providers
- Eskiz
- Play Mobile
- TextUp

Strategy pattern:
- `SmsProvider` interface
- dynamic routing rules:
  - failover provider order
  - per-tenant provider
  - per-message type (OTP vs marketing)
- supported SMS message types are `otp`, `reminder`, `transactional`, and `bulk`
- queued SMS notifications are delivered asynchronously from `medflow.notifications.v1` and advance `queued -> sent|failed`

### 10.5 Telegram Bot
- patient reminders
- clinic broadcast
- support channel

### 10.6 Email
- transactional email service (SMTP or provider)
- templates versioned

### 10.7 Additional Uzbekistan Integrations (Optional)
These are designed as plug-ins; implement when you have credentials:
- E-IMZO e-signature workflows
- Global ID Gate / other local identity aggregators
- Tax/receipt systems (if applicable)
- Local maps/geocoding providers (if needed)
- E-IMZO sign requests are initiated by a tenant-scoped command and complete through a replay-safe managed webhook using a tenant-managed secret
- E-IMZO sign request states are `pending`, `signed`, `canceled`, `expired`, and `failed`
- Optional plug-in initiation in this phase is local-first, requires tenant-managed enablement plus credentials plus an active managed webhook, and treats the verified webhook callback as the authoritative completion signal

---

## 11. API Design Standards
### 11.1 REST + Action Endpoints
- Resource-first routes.
- Action routes for transitions (confirm, cancel, approve).

### 11.2 Versioning
- URL: `/api/v1/...`
- Event versioning separate from API version.

### 11.3 Error Model
Standard error:
- `code`, `message`, `details`, `trace_id`, `correlation_id`

### 11.4 Pagination
- cursor pagination preferred.

### 11.5 Idempotency
- Idempotency keys for:
  - payment creation
  - appointment scheduling
  - client-initiated webhook processing
- provider-initiated webhooks that cannot send `Idempotency-Key` must use provider-native replay identifiers plus persisted delivery records

---

## 12. Endpoint Catalog (High-Level)
> This is the initial catalog. Each endpoint is documented in OpenAPI with full schemas and examples.

### 12.1 Auth & Identity
- POST `/api/v1/auth/login`
- POST `/api/v1/auth/logout`
- POST `/api/v1/auth/refresh`
- GET  `/api/v1/auth/me`
- GET  `/api/v1/auth/google/redirect`
- GET  `/api/v1/auth/google/callback`

### 12.2 Tenants & Clinics
- CRUD tenants
- CRUD clinics
- clinic settings
- tenant bootstrap attaches the creator as an active member of the new tenant and assigns a bootstrap administrator role
- tenant settings: locale, timezone, currency
- tenant limits: users, clinics, providers, patients, storage_gb, monthly_notifications
- tenant usage reports used, limit, and remaining values for each documented limit key
- clinic records: code, name, status, contact details, location codes, address fields, notes
- clinic lifecycle states: active, inactive; delete only while inactive
- clinic settings: timezone override, default appointment duration, slot interval, walk-in flag, confirmation flag, telemedicine flag
- departments and rooms are clinic-owned nested resources with unique per-clinic codes
- clinic work hours are weekly schedules with non-overlapping daily time intervals
- clinic holidays are inclusive non-overlapping date ranges
- location endpoints expose approved global reference data and are not tenant-owned

### 12.3 Users & RBAC
- CRUD users
- roles
- permissions

### 12.4 Patients
- CRUD patients
- search
- attach documents
- patient master records: first name, last name, middle name, preferred name, sex, birth date, national ID, contact details, approved location codes, address fields, notes
- patient deletion is a soft delete; deleted patients are excluded from active directory reads but retained for auditability

### 12.5 Providers
- CRUD providers
- availability
- calendars

### 12.6 Scheduling (Appointments)
- CRUD appointments
- actions: schedule, confirm, check-in, start, complete, cancel, no-show, reschedule
- appointment booking owns patient, provider, optional clinic and room assignment, scheduled slot, status, and transition metadata
- `POST /appointments` creates draft appointments; `PATCH /appointments/{appointmentId}` and `DELETE /appointments/{appointmentId}` apply only to draft appointments so workflow transitions remain action-based
- appointment deletion is a soft delete; deleted draft appointments leave active directory reads but remain auditable
- appointment search supports `q`, `status`, `patient_id`, `provider_id`, `clinic_id`, `room_id`, `scheduled_from`, `scheduled_to`, `created_from`, `created_to`, and `limit`
- appointment export uses the active search result set, supports `format=csv`, stores a private export artifact, and returns export metadata with `filters` and `storage`
- appointment audit views use immutable audit events with object type `appointment` and remain accessible for soft-deleted appointments inside tenant scope
- draft appointments do not consume slot capacity yet; availability and provider-calendar reads continue to ignore draft appointments until later scheduling workflow tasks
- reschedule closes the current appointment as `rescheduled` and preserves the original slot history for the replacement booking

### 12.7 Treatments
- CRUD treatment plans
- search treatment plans
- manage ordered treatment-plan items
- actions: approve, start, pause, resume, finish, reject
- treatment plans own patient linkage, provider linkage, title, optional summary and goals, optional planned start and end dates, status, transition metadata, and lifecycle timestamps
- `POST /treatment-plans` creates `draft` plans; generic `PATCH` is limited to `draft|approved`
- treatment-plan delete is a soft delete limited to `draft|rejected`
- treatment plan lifecycle is `draft -> approved -> active -> paused -> active -> finished`, with `draft|approved -> rejected`
- treatment plans expose `item_count` in read models once treatment items are enabled
- treatment items are ordered plan subresources with `item_type`, title, optional description, optional instructions, and one-based `sort_order`
- treatment item writes are limited to parent plans in `draft|approved`; started or terminal plans are read-only for item changes
- treatment-item insert, move, and delete operations reindex sibling order transactionally to keep a contiguous ordered list

### 12.8 Labs
- CRUD lab orders
- receive results (webhook)

### 12.9 Prescriptions
- CRUD prescriptions
- actions: issue, cancel, dispense
- prescriptions are tenant-scoped records with patient and provider linkage, optional encounter and treatment-item linkage, medication snapshot fields, explicit issue/cancel/dispense actions, and soft-delete retention
- prescription status values are `draft`, `issued`, `dispensed`, and `canceled`
- generic `PATCH /prescriptions/{prescriptionId}` is draft-only and workflow changes happen only through `:issue`, `:cancel`, and `:dispense`

### 12.10 Billing & Payments
- invoices
- payments: initiate, status, refund
- reconcile

### 12.11 Insurance
- claims
- actions: submit, review, approve/deny, mark-paid

### 12.12 Notifications
- email/sms/telegram templates
- send test
- templates are tenant-scoped, versioned, and soft-deletable
- email templates require subject + body; sms and telegram use body only
- test-render uses strict `{{path.to.value}}` placeholders and rejects missing or non-scalar final values

### 12.13 Integrations
- manage credentials per tenant
- webhook endpoints
- health checks

### 12.14 Audit
- query audit events
- generic audit reads are tenant-scoped in this phase and support free-text, actor, object, action-prefix, and time-range filters
- object audit views use `GET /audit/object/{objectType}/{objectId}` and return newest first
- audit export is synchronous CSV generation stored on the exports disk and recorded as audit action `audit.exported`
- audit retention uses platform default `AUDIT_RETENTION_DAYS` plus optional tenant override; `0` disables pruning for the tenant override scope

### 12.15 Observability
- health, readiness, liveness
- metrics endpoint

**Note:** The full endpoint matrix (200+ routes) is produced in `/docs/api/endpoint-matrix.md` section below with route naming conventions, request/response schemas, and state transitions.

---

## 13. Testing Strategy (100% Coverage Target)
### 13.1 Test Types
- Unit tests (Domain + pure Application logic)
- Feature tests (HTTP API)
- Integration tests (DB, Redis, Kafka test containers)
- Contract tests (OpenAPI schema validation)
- Consumer-driven contract tests for integrations (where possible)

### 13.2 Coverage Policy
- Coverage measured for:
  - Domain
  - Application
  - Infrastructure critical paths
- 100% for Domain + Application required.

### 13.3 Test Data
- Builders + Mother objects.
- Deterministic time.

---

## 14. Observability & Logging (Best Practices)
### 14.1 Logging
- Structured JSON logs.
- Correlation IDs:
  - `X-Request-Id`
  - `X-Correlation-Id`
- Log levels standardized.

### 14.2 Tracing
- OpenTelemetry instrumentation for:
  - inbound HTTP
  - outbound HTTP integrations
  - DB queries (sampled)
  - Kafka produce/consume

### 14.3 Errors
- Sentry for exception aggregation.

### 14.4 Metrics
- Prometheus metrics for:
  - request latency
  - queue lag
  - kafka consumer lag
  - integration error rates
  - cache hit ratio

### 14.5 Central Logs
- Elastic for log indexing and dashboards.

---

## 15. Security
- OAuth2 / JWT
- TOTP-based MFA with recovery codes for accounts that enable MFA
- MFA-enabled logins must return `MFA_REQUIRED` with a short-lived challenge until second-factor verification succeeds
- MFA secrets encrypted at rest; recovery codes stored only as hashes
- managed API keys may be issued for machine-to-machine access, must be stored only as hashes, and are returned only once at creation
- tenant IP allowlists use CIDR entries and must be enforced for API-key-authenticated traffic whenever a tenant scope has active entries
- device registration is user-owned and must support metadata refresh for an existing installation without duplicate device rows
- Rate limiting per tenant and per IP
- Webhook signature verification for all inbound callbacks
- Encrypt integration secrets at rest
- PII handling: field-level encryption for sensitive fields
- PII governance uses a tenant-scoped registry with explicit classification, encryption profile, key version, and rotation history
- PII key rotation and re-encryption operations must create append-only compliance reports and audit records
- Audit logging mandatory
- Security event tracking mandatory for authentication, MFA, and other sensitive IAM actions

---

## 16. CI/CD
### 16.1 Pipeline Gates
- lint (Pint)
- static analysis (PHPStan + Psalm)
- unit tests
- feature tests
- integration tests (optional on main)
- OpenAPI validation
- coverage thresholds
- architecture rules (layering, file size)

### 16.2 Releases
- semantic versioning
- changelog generation

---

## 17. Docker & Local Dev
### 17.1 Compose Rules
- DB ports **NOT exposed**.
- services on a private network.
- only API gateway ports exposed.

### 17.2 Services
- app
- nginx
- postgres
- redis
- kafka
- otel-collector
- prometheus
- grafana
- elasticsearch
- kibana

---

## 18. AI Agent / Codex Playbook (How to Work in This Repo)
### 18.1 Golden Path: Adding a New Feature
1. Choose module.
2. Add domain types (entity/value objects/events).
3. Add application command/query + handler.
4. Add interface contracts.
5. Implement infrastructure adapters.
6. Add controller + request DTO.
7. Add OpenAPI definitions + examples.
8. Add tests (unit + feature + integration if needed).
9. Update docs (endpoint matrix + module docs).

### 18.2 PR Checklist
- [ ] All CI gates pass
- [ ] No file > 400 lines
- [ ] No domain imports Laravel
- [ ] OpenAPI updated
- [ ] Tests added
- [ ] Observability: logs/metrics/traces updated if needed

---

## 19. Documents Included (Virtual File List)
> Everything below is included in this single SSoT document; in the repo these are separate markdown files under `/docs`.

### `/docs/dev/`
- `architecture.md` (this doc sections 4–5)
- `coding-standards.md` (section 2)
- `testing.md` (section 13)
- `observability.md` (section 14)
- `security.md` (section 15)
- `ci-cd.md` (section 16)
- `local-dev.md` (section 17)
- `ai-agent-playbook.md` (section 18)

### `/docs/api/`
- `openapi-guidelines.md`
- `endpoint-matrix.md` (expanded route inventory)
- `error-catalog.md`
- `webhooks.md`

### `/docs/adr/`
- `000-template.md`
- `001-clean-architecture.md`
- `002-kafka-outbox.md`
- `003-redis-caching.md`
- `004-integrations-framework.md`
- `005-observability-stack.md`

---

## 20. Next Steps (Implementation Roadmap)
1. Bootstrap Laravel 12 + module structure + shared kernel.
2. Implement IAM + tenancy context + audit.
3. Implement Kafka outbox + consumer framework.
4. Implement integration framework + sandbox stubs.
5. Implement scheduling + appointment state machine.
6. Add billing + Payme/Click/Uzum.
7. Add notifications with SMS failover + Telegram.
8. Add full observability stack.
9. Expand endpoint matrix and finalize OpenAPI.

---

# Appendix A — Endpoint Matrix (Full Inventory v1)
> Target inventory: **~280 endpoints** (within requested 200–400). This matrix is the authoritative route catalog. Each endpoint maps to exactly one Application handler (Command/Query). Each route must be documented in OpenAPI with schemas and examples.

## A.0 Conventions
- Base prefix: `/api/v1`
- Route format: `METHOD /path` → `UseCase` → `Module`
- `:action` routes are explicit workflow/state transitions.
- Bulk actions use `/bulk`.
- Exports use `/export`.
- Search endpoints use `/search` or `?q=`.
- Webhooks live under `/webhooks/{provider}`.
- Admin-only endpoints live under `/admin/*`.

---

## A.1 Auth & Identity (16)
- POST `/auth/login` → `LoginCommand` → IAM
- POST `/auth/logout` → `LogoutCommand` → IAM
- POST `/auth/refresh` → `RefreshTokenCommand` → IAM
- GET `/auth/me` → `GetMeQuery` → IAM
- POST `/auth/password/forgot` → `RequestPasswordResetCommand` → IAM
- POST `/auth/password/reset` → `ResetPasswordCommand` → IAM
- POST `/auth/mfa/setup` → `SetupMfaCommand` → IAM
- POST `/auth/mfa/verify` → `VerifyMfaCommand` → IAM
- POST `/auth/mfa/disable` → `DisableMfaCommand` → IAM
- GET `/auth/google/redirect` → `GoogleRedirectQuery` → IAM
- GET `/auth/google/callback` → `GoogleCallbackCommand` → IAM
- POST `/auth/sessions` → `ListSessionsQuery` → IAM
- DELETE `/auth/sessions/{sessionId}` → `RevokeSessionCommand` → IAM
- POST `/auth/api-keys` → `CreateApiKeyCommand` → IAM
- GET `/auth/api-keys` → `ListApiKeysQuery` → IAM
- DELETE `/auth/api-keys/{keyId}` → `RevokeApiKeyCommand` → IAM

---

## A.2 Tenants (12)
- GET `/tenants` → `ListTenantsQuery` → Tenancy
- POST `/tenants` → `CreateTenantCommand` → Tenancy
- GET `/tenants/{tenantId}` → `GetTenantQuery` → Tenancy
- PATCH `/tenants/{tenantId}` → `UpdateTenantCommand` → Tenancy
- DELETE `/tenants/{tenantId}` → `DeleteTenantCommand` → Tenancy
- POST `/tenants/{tenantId}:activate` → `ActivateTenantCommand` → Tenancy
- POST `/tenants/{tenantId}:suspend` → `SuspendTenantCommand` → Tenancy
- GET `/tenants/{tenantId}/usage` → `GetTenantUsageQuery` → Tenancy
- GET `/tenants/{tenantId}/limits` → `GetTenantLimitsQuery` → Tenancy
- PUT `/tenants/{tenantId}/limits` → `UpdateTenantLimitsCommand` → Tenancy
- GET `/tenants/{tenantId}/settings` → `GetTenantSettingsQuery` → Tenancy
- PUT `/tenants/{tenantId}/settings` → `UpdateTenantSettingsCommand` → Tenancy

Tenant route notes:
- `GET /tenants` returns only tenants where the authenticated actor has a membership.
- `POST /tenants` is an authenticated bootstrap route and does not require an active tenant context.
- Tenant-specific routes accept `{tenantId}` as the documented tenant context source and may also accept `X-Tenant-Id`; mismatches fail closed.
- Tenant lifecycle states are `active` and `suspended`.
- Suspended tenants remain administratively accessible for authorized tenant actors so recovery and reactivation remain possible.

---

## A.3 Clinics & Locations (26)
- GET `/clinics` → `ListClinicsQuery` → Clinics
- POST `/clinics` → `CreateClinicCommand` → Clinics
- GET `/clinics/{clinicId}` → `GetClinicQuery` → Clinics
- PATCH `/clinics/{clinicId}` → `UpdateClinicCommand` → Clinics
- DELETE `/clinics/{clinicId}` → `DeleteClinicCommand` → Clinics
- POST `/clinics/{clinicId}:activate` → `ActivateClinicCommand` → Clinics
- POST `/clinics/{clinicId}:deactivate` → `DeactivateClinicCommand` → Clinics
- GET `/clinics/{clinicId}/settings` → `GetClinicSettingsQuery` → Clinics
- PUT `/clinics/{clinicId}/settings` → `UpdateClinicSettingsCommand` → Clinics
- GET `/clinics/{clinicId}/departments` → `ListDepartmentsQuery` → Clinics
- POST `/clinics/{clinicId}/departments` → `CreateDepartmentCommand` → Clinics
- GET `/clinics/{clinicId}/departments/{deptId}` → `GetDepartmentQuery` → Clinics
- PATCH `/clinics/{clinicId}/departments/{deptId}` → `UpdateDepartmentCommand` → Clinics
- DELETE `/clinics/{clinicId}/departments/{deptId}` → `DeleteDepartmentCommand` → Clinics
- GET `/clinics/{clinicId}/rooms` → `ListRoomsQuery` → Clinics
- POST `/clinics/{clinicId}/rooms` → `CreateRoomCommand` → Clinics
- PATCH `/clinics/{clinicId}/rooms/{roomId}` → `UpdateRoomCommand` → Clinics
- DELETE `/clinics/{clinicId}/rooms/{roomId}` → `DeleteRoomCommand` → Clinics
- GET `/clinics/{clinicId}/work-hours` → `GetClinicWorkHoursQuery` → Clinics
- PUT `/clinics/{clinicId}/work-hours` → `UpdateClinicWorkHoursCommand` → Clinics
- GET `/clinics/{clinicId}/holidays` → `ListClinicHolidaysQuery` → Clinics
- POST `/clinics/{clinicId}/holidays` → `CreateClinicHolidayCommand` → Clinics
- DELETE `/clinics/{clinicId}/holidays/{holidayId}` → `DeleteClinicHolidayCommand` → Clinics
- GET `/locations/cities` → `ListCitiesQuery` → Clinics
- GET `/locations/districts` → `ListDistrictsQuery` → Clinics
- GET `/locations/search` → `SearchLocationsQuery` → Clinics
- Clinic codes are unique within a tenant. Department and room codes are unique within a clinic.
- Clinic settings currently contain `timezone`, `default_appointment_duration_minutes`, `slot_interval_minutes`, `allow_walk_ins`, `require_appointment_confirmation`, and `telemedicine_enabled`.
- Clinic work hours are a weekly schedule keyed by `monday` through `sunday`, with zero or more non-overlapping `HH:MM` intervals per day.
- Clinic holidays are inclusive date ranges with `name`, `start_date`, `end_date`, `is_closed`, and `notes`.
- Location endpoints return approved read-only global reference data.

---

## A.4 Users, Roles, Permissions (38)
- GET `/users` → `ListUsersQuery` → IAM
- POST `/users` → `CreateUserCommand` → IAM
- GET `/users/{userId}` → `GetUserQuery` → IAM
- PATCH `/users/{userId}` → `UpdateUserCommand` → IAM
- DELETE `/users/{userId}` → `DeleteUserCommand` → IAM
- POST `/users/{userId}:activate` → `ActivateUserCommand` → IAM
- POST `/users/{userId}:deactivate` → `DeactivateUserCommand` → IAM
- POST `/users/{userId}:lock` → `LockUserCommand` → IAM
- POST `/users/{userId}:unlock` → `UnlockUserCommand` → IAM
- POST `/users/{userId}:reset-password` → `AdminResetPasswordCommand` → IAM
- GET `/users/{userId}/roles` → `ListUserRolesQuery` → IAM
- PUT `/users/{userId}/roles` → `SetUserRolesCommand` → IAM
- GET `/users/{userId}/permissions` → `GetUserPermissionsQuery` → IAM
- POST `/users:bulk-import` → `BulkImportUsersCommand` → IAM
- POST `/users/bulk` → `BulkUpdateUsersCommand` → IAM
- GET `/roles` → `ListRolesQuery` → IAM
- POST `/roles` → `CreateRoleCommand` → IAM
- GET `/roles/{roleId}` → `GetRoleQuery` → IAM
- PATCH `/roles/{roleId}` → `UpdateRoleCommand` → IAM
- DELETE `/roles/{roleId}` → `DeleteRoleCommand` → IAM
- GET `/roles/{roleId}/permissions` → `ListRolePermissionsQuery` → IAM
- PUT `/roles/{roleId}/permissions` → `SetRolePermissionsCommand` → IAM
- GET `/permissions` → `ListPermissionsQuery` → IAM
- GET `/permissions/groups` → `ListPermissionGroupsQuery` → IAM
- GET `/rbac/audit` → `GetRbacAuditQuery` → IAM
- GET `/profiles/me` → `GetMyProfileQuery` → IAM
- PATCH `/profiles/me` → `UpdateMyProfileCommand` → IAM
- POST `/profiles/me/avatar` → `UploadMyAvatarCommand` → IAM
- GET `/profiles/{userId}` → `GetProfileQuery` → IAM
- PATCH `/profiles/{userId}` → `UpdateProfileCommand` → IAM
- GET `/devices` → `ListDevicesQuery` → IAM
- POST `/devices` → `RegisterDeviceCommand` → IAM
- DELETE `/devices/{deviceId}` → `DeregisterDeviceCommand` → IAM
- GET `/security/events` → `ListSecurityEventsQuery` → IAM
- GET `/security/events/{eventId}` → `GetSecurityEventQuery` → IAM
- POST `/security/ip-allowlist` → `UpdateIpAllowlistCommand` → IAM
- GET `/security/ip-allowlist` → `GetIpAllowlistQuery` → IAM
- POST `/security/sessions:revoke-all` → `RevokeAllSessionsCommand` → IAM

---

## A.5 Patients (30)
- GET `/patients` → `ListPatientsQuery` → Patient
- POST `/patients` → `CreatePatientCommand` → Patient
- GET `/patients/{patientId}` → `GetPatientQuery` → Patient
- PATCH `/patients/{patientId}` → `UpdatePatientCommand` → Patient
- DELETE `/patients/{patientId}` → `DeletePatientCommand` → Patient
- GET `/patients/search` → `SearchPatientsQuery` → Patient
- GET `/patients/{patientId}/summary` → `GetPatientSummaryQuery` → Patient
- GET `/patients/{patientId}/timeline` → `GetPatientTimelineQuery` → Patient
- GET `/patients/{patientId}/contacts` → `ListPatientContactsQuery` → Patient
- POST `/patients/{patientId}/contacts` → `CreatePatientContactCommand` → Patient
- PATCH `/patients/{patientId}/contacts/{contactId}` → `UpdatePatientContactCommand` → Patient
- DELETE `/patients/{patientId}/contacts/{contactId}` → `DeletePatientContactCommand` → Patient
- GET `/patients/{patientId}/insurance` → `ListPatientInsuranceQuery` → Insurance
- POST `/patients/{patientId}/insurance` → `AttachPatientInsuranceCommand` → Insurance
- DELETE `/patients/{patientId}/insurance/{policyId}` → `DetachPatientInsuranceCommand` → Insurance
- GET `/patients/{patientId}/documents` → `ListPatientDocumentsQuery` → Patient
- POST `/patients/{patientId}/documents` → `UploadPatientDocumentCommand` → Patient
- GET `/patients/{patientId}/documents/{docId}` → `GetPatientDocumentQuery` → Patient
- DELETE `/patients/{patientId}/documents/{docId}` → `DeletePatientDocumentCommand` → Patient
- GET `/patients/{patientId}/consents` → `ListPatientConsentsQuery` → Patient
- POST `/patients/{patientId}/consents` → `CreatePatientConsentCommand` → Patient
- POST `/patients/{patientId}/consents/{consentId}:revoke` → `RevokePatientConsentCommand` → Patient
- GET `/patients/{patientId}/tags` → `ListPatientTagsQuery` → Patient
- PUT `/patients/{patientId}/tags` → `SetPatientTagsCommand` → Patient
- POST `/patients:bulk-import` → `BulkImportPatientsCommand` → Patient
- POST `/patients/bulk` → `BulkUpdatePatientsCommand` → Patient
- GET `/patients/export` → `ExportPatientsQuery` → Patient
- GET `/patients/{patientId}/external-refs` → `ListPatientExternalRefsQuery` → Integrations
- POST `/patients/{patientId}/external-refs` → `AttachPatientExternalRefCommand` → Integrations
- DELETE `/patients/{patientId}/external-refs/{refId}` → `DetachPatientExternalRefCommand` → Integrations

Patient search, summary, timeline, and export operate on the active tenant-owned patient directory:

- patient search supports `q`, `sex`, `city_code`, `district_code`, `birth_date_from`, `birth_date_to`, `created_from`, `created_to`, `has_email`, `has_phone`, and `limit`
- search token matching uses AND semantics across patient names, `national_id`, `email`, and `phone`
- patient summary is derived from the active patient master record plus immutable patient audit events
- patient timeline returns patient audit events newest first
- patient export writes CSV only through the private shared exports storage and returns an export reference rather than a public file URL
- export creation writes audit action `patients.exported` with object type `patient_export`
- patient contacts require `name` plus at least one of `phone` or `email`; only one active primary contact may exist per patient
- patient contacts list ordered by `is_primary desc`, `is_emergency desc`, `name asc`, and `created_at asc`
- patient tags are replaced as a full normalized set; normalization trims, collapses repeated internal whitespace, lowercases, discards empty values, and sorts alphabetically
- patient document routes expose metadata only, store files on the private shared attachments disk, and accept only `pdf`, `jpg`, `jpeg`, `png`, and `webp` uploads up to `10 MiB`
- patient consents accept `consent_type`, `granted_by_name`, optional `granted_by_relationship`, optional `granted_at`, optional `expires_at`, and optional `notes`
- patient consent type is normalized to lowercase snake case; `granted_by_name` is required and `granted_at` defaults to the current timestamp
- patient consent status is derived as `active`, `expired`, or `revoked`; at most one active consent of the same type may exist for a patient at a time
- patient consent list ordered by active consents first, then `granted_at desc`, then `created_at desc`
- `POST /patients/{patientId}/consents/{consentId}:revoke` records `revoked_at` and optional `reason`; revoked consent history is retained and not hard-deleted in normal flows
- patient insurance links accept `insurance_code`, `policy_number`, optional `member_number`, optional `group_number`, optional `plan_name`, optional `effective_from`, optional `effective_to`, optional `is_primary`, and optional `notes`
- patient insurance links ordered by `is_primary desc`, `effective_from desc nulls last`, and `created_at desc`
- only one patient insurance link may be primary at a time; attaching a new primary policy clears the previous primary flag
- duplicate patient insurance links for `{patient_id, insurance_code, policy_number}` are conflicts
- patient external references accept `integration_key`, `external_id`, optional `external_type`, optional `display_name`, and optional JSON `metadata`
- patient external reference `external_type` defaults to `patient` and the tuple `{patient_id, integration_key, external_type, external_id}` must be unique
- patient contact, tag, document, consent, insurance-link, and external-reference mutations emit patient audit actions so they can appear in patient timelines

---

## A.6 Providers (Doctors/Nurses) & Availability (34)
- GET `/providers` → `ListProvidersQuery` → Provider
- POST `/providers` → `CreateProviderCommand` → Provider
- GET `/providers/{providerId}` → `GetProviderQuery` → Provider
- PATCH `/providers/{providerId}` → `UpdateProviderCommand` → Provider
- DELETE `/providers/{providerId}` → `DeleteProviderCommand` → Provider
- GET `/providers/search` → `SearchProvidersQuery` → Provider
- GET `/providers/{providerId}/profile` → `GetProviderProfileQuery` → Provider
- PATCH `/providers/{providerId}/profile` → `UpdateProviderProfileCommand` → Provider
- GET `/providers/{providerId}/specialties` → `ListProviderSpecialtiesQuery` → Provider
- PUT `/providers/{providerId}/specialties` → `SetProviderSpecialtiesCommand` → Provider
- GET `/providers/{providerId}/licenses` → `ListProviderLicensesQuery` → Provider
- POST `/providers/{providerId}/licenses` → `AddProviderLicenseCommand` → Provider
- DELETE `/providers/{providerId}/licenses/{licenseId}` → `RemoveProviderLicenseCommand` → Provider
- GET `/providers/{providerId}/availability/rules` → `ListAvailabilityRulesQuery` → Scheduling
- POST `/providers/{providerId}/availability/rules` → `CreateAvailabilityRuleCommand` → Scheduling
- PATCH `/providers/{providerId}/availability/rules/{ruleId}` → `UpdateAvailabilityRuleCommand` → Scheduling
- DELETE `/providers/{providerId}/availability/rules/{ruleId}` → `DeleteAvailabilityRuleCommand` → Scheduling
- GET `/providers/{providerId}/availability/slots` → `GetAvailabilitySlotsQuery` → Scheduling
- POST `/providers/{providerId}/availability:rebuild-cache` → `RebuildAvailabilityCacheCommand` → Scheduling
- GET `/providers/{providerId}/calendar` → `GetProviderCalendarQuery` → Scheduling
- GET `/providers/{providerId}/calendar/export` → `ExportProviderCalendarQuery` → Scheduling
- GET `/providers/{providerId}/work-hours` → `GetProviderWorkHoursQuery` → Provider
- PUT `/providers/{providerId}/work-hours` → `UpdateProviderWorkHoursCommand` → Provider
- GET `/providers/{providerId}/time-off` → `ListTimeOffQuery` → Provider
- POST `/providers/{providerId}/time-off` → `CreateTimeOffCommand` → Provider
- PATCH `/providers/{providerId}/time-off/{timeOffId}` → `UpdateTimeOffCommand` → Provider
- DELETE `/providers/{providerId}/time-off/{timeOffId}` → `DeleteTimeOffCommand` → Provider
- GET `/specialties` → `ListSpecialtiesQuery` → Provider
- POST `/specialties` → `CreateSpecialtyCommand` → Provider
- PATCH `/specialties/{specialtyId}` → `UpdateSpecialtyCommand` → Provider
- DELETE `/specialties/{specialtyId}` → `DeleteSpecialtyCommand` → Provider
- GET `/provider-groups` → `ListProviderGroupsQuery` → Provider
- POST `/provider-groups` → `CreateProviderGroupCommand` → Provider
- PUT `/provider-groups/{groupId}/members` → `SetProviderGroupMembersCommand` → Provider

Provider master records use the base fields `first_name`, `last_name`, `middle_name`, `preferred_name`, `provider_type`, `email`, `phone`, `clinic_id`, and `notes`:

- `first_name`, `last_name`, and `provider_type` are required on create
- `provider_type` enum values are `doctor`, `nurse`, and `other`
- `provider_type` is normalized to lowercase; `email` is lowercased when present and `phone` is trimmed
- `clinic_id` is optional, but when present it must reference an existing clinic in the same tenant scope
- provider list ordered by `last_name asc`, `first_name asc`, and `created_at asc`
- `DELETE /providers/{providerId}` is a soft delete; deleted providers are excluded from active directory reads but retained for auditability and future scheduling references
- provider CRUD mutations emit `providers.created`, `providers.updated`, and `providers.deleted` audit actions
- `GET /providers/{providerId}/profile` returns the base provider record plus provider-profile fields `professional_title`, `bio`, `years_of_experience`, `department_id`, `room_id`, `is_accepting_new_patients`, `languages`, and nested `department` and `room` summaries when available
- provider profile data is a one-to-one extension; `is_accepting_new_patients` defaults to `true` and `languages` are normalized by trimming, collapsing repeated internal whitespace, removing empties, deduplicating case-insensitively, and sorting alphabetically
- `department_id` and `room_id` are optional, but if either is present the provider must belong to a clinic and the selected department or room must belong to that clinic; if both are present and the room has a department assignment, it must match `department_id`
- if provider `clinic_id` changes through base CRUD, provider-profile `department_id` and `room_id` are cleared automatically to prevent stale clinic-location links
- specialties are tenant-owned catalog records with required `name`, optional `description`, case-insensitive unique names per tenant, and list ordering `name asc`, `created_at asc`
- `PUT /providers/{providerId}/specialties` replaces the full specialty set using `specialties[] = {specialty_id, is_primary?}`; each specialty may appear only once and at most one specialty may be primary
- `GET /providers/{providerId}/specialties` returns assigned specialties ordered by `is_primary desc`, `name asc`, `assigned_at asc`
- `DELETE /specialties/{specialtyId}` fails with `409 Conflict` while the specialty is still assigned to any provider
- provider licenses use `license_type`, `license_number`, `issuing_authority`, optional `jurisdiction`, optional `issued_on`, optional `expires_on`, and optional `notes`
- `license_type` is normalized to lowercase snake case; `{provider_id, license_type, license_number}` must be unique; license status is derived as `active` or `expired`
- `GET /providers/{providerId}/licenses` returns licenses with active licenses first, then `expires_on asc nulls last`, then `created_at asc`
- availability rules are scheduling-owned provider records with `scope_type`, `availability_type`, optional `weekday`, optional `specific_date`, `start_time`, `end_time`, and optional `notes`
- `scope_type` values are `weekly` and `date`; `availability_type` values are `available` and `unavailable`; weekly rules require `weekday` and forbid `specific_date`, while date rules require `specific_date` and forbid `weekday`
- availability rules conflict only when the same provider and tenant have the same `scope_type`, the same `availability_type`, the same weekday or specific date, and overlapping time ranges; cross-scope overlaps like `weekly available` plus `date unavailable` are allowed
- `GET /providers/{providerId}/availability/rules` returns rules ordered by `scope_type asc`, `weekday asc nulls last`, `specific_date asc nulls last`, `start_time asc`, and `created_at asc`
- availability mutation routes and `POST /providers/{providerId}/availability:rebuild-cache` require `Idempotency-Key`
- `GET /providers/{providerId}/availability/slots` accepts `date_from`, `date_to`, and optional `limit`; the slot window may span at most `31` calendar days, `limit` defaults to `200`, and `limit` may not exceed `1000`
- slot generation is a scheduling availability view only; it unions matching available rules, subtracts matching unavailable rules, intersects clinic work hours when the provider is assigned to a clinic, returns no slots on closed clinic holidays, and does not subtract booked appointments before appointment scheduling work lands later
- slot timezone resolves by clinic settings timezone override, then tenant settings timezone, then app timezone; slot duration resolves by clinic settings `default_appointment_duration_minutes` or `30`; slot interval resolves by clinic settings `slot_interval_minutes` or `15`
- availability slot views use tenant-scoped cache domain `availability` keyed by provider plus date window and limit; rule mutations, cache rebuild, clinic settings/work-hours/holiday/lifecycle changes, provider clinic assignment changes, provider soft delete, and tenant timezone changes invalidate that namespace
- availability audit actions are `availability.rules.created`, `availability.rules.updated`, `availability.rules.deleted`, and `availability.cache_rebuilt`
- `GET /providers/{providerId}/work-hours` returns the provider-owned weekly template projected from weekly availability rules; the response always contains all seven weekdays plus `provider_id`, `timezone`, and `updated_at`
- work-hours projection unions weekly available rules and subtracts weekly unavailable rules for the same weekday; clinic work hours and clinic holidays do not rewrite the provider-owned template and only affect effective calendar slots
- `PUT /providers/{providerId}/work-hours` is replacement-based using `days`; unknown weekday keys are rejected, intervals must use `HH:MM`, intervals must not overlap within a day, omitted weekdays become empty lists, and adjacent intervals are collapsed into a canonical interval set
- work-hours replacement rewrites the weekly schedule into canonical `weekly available` rules only and replaces the provider weekly rule set atomically
- `GET /providers/{providerId}/time-off` returns date-scoped unavailable intervals ordered by `specific_date asc`, `start_time asc`, and `created_at asc`
- provider time-off records use `id`, `provider_id`, `specific_date`, `start_time`, `end_time`, optional `notes`, `created_at`, and `updated_at`; multi-day leave is represented as multiple records
- `POST /providers/{providerId}/time-off` requires `specific_date`, `start_time`, `end_time`, and optional `notes`; `PATCH /providers/{providerId}/time-off/{timeOffId}` supports partial updates of the same fields
- time-off mutations use the same date-unavailable conflict rules as low-level availability rules and require `Idempotency-Key`
- `GET /providers/{providerId}/calendar` accepts `date_from`, `date_to`, and optional `limit` using the same window rules as availability slots and returns `work_hours`, `time_off`, `slot_count`, `slots`, and `is_clinic_closed` for each day
- provider calendar combines the provider weekly template, date-specific time-off, clinic closures, and effective slots; booked appointment subtraction remains out of scope until later scheduling tasks
- `GET /providers/{providerId}/calendar/export` accepts the same window query plus optional `format=csv`, stores a private export artifact, and returns export metadata with `filters` and `storage`
- provider schedule audit actions additionally include `providers.work_hours_updated`, `providers.time_off_created`, `providers.time_off_updated`, `providers.time_off_deleted`, and `providers.calendar_exported`
- appointments persist `patient_id`, `provider_id`, optional `clinic_id`, optional `room_id`, `status`, `scheduled_start_at`, `scheduled_end_at`, `timezone`, optional `last_transition`, soft-delete timestamp, and audit timestamps
- appointment create and update validate tenant-scoped patient, provider, clinic, and room references; `room_id` requires `clinic_id`, and an explicit appointment clinic must match the provider clinic assignment when the provider is already clinic-bound
- `GET /appointments` returns active tenant-scoped appointments ordered by `scheduled_start_at asc`, `created_at asc`, and `id asc`
- appointment search matches appointment IDs plus patient and provider display names using AND semantics across query tokens and deterministic relevance ordering
- appointment export writes audit action `appointments.exported` with object type `appointment_export`
- appointment CRUD audit actions are `appointments.created`, `appointments.updated`, and `appointments.deleted`
- appointment mutation routes `POST /appointments`, `PATCH /appointments/{appointmentId}`, and `DELETE /appointments/{appointmentId}` require `Idempotency-Key`
- provider groups are tenant-owned records with required `name`, optional `description`, optional `clinic_id`, and replacement-based member management through `provider_ids`
- `GET /provider-groups` returns `member_count`, `member_ids`, and ordered `members` summaries for each group
- provider extension audit actions include `providers.profile_updated`, `providers.specialties_set`, `providers.license_added`, `providers.license_removed`, `provider_specialties.created`, `provider_specialties.updated`, `provider_specialties.deleted`, `provider_groups.created`, and `provider_groups.members_updated`

---

## A.7 Scheduling: Appointments (State Machine) (44)
### Appointment resources
- GET `/appointments` → `ListAppointmentsQuery` → Scheduling
- POST `/appointments` → `CreateAppointmentCommand` → Scheduling
- GET `/appointments/{appointmentId}` → `GetAppointmentQuery` → Scheduling
- PATCH `/appointments/{appointmentId}` → `UpdateAppointmentCommand` → Scheduling
- DELETE `/appointments/{appointmentId}` → `DeleteAppointmentCommand` → Scheduling
- GET `/appointments/search` → `SearchAppointmentsQuery` → Scheduling
- GET `/appointments/export` → `ExportAppointmentsQuery` → Scheduling
- GET `/appointments/{appointmentId}/audit` → `GetAppointmentAuditQuery` → Audit

### State transitions (actions)
- POST `/appointments/{appointmentId}:schedule` → `ScheduleAppointmentCommand` → Scheduling
- POST `/appointments/{appointmentId}:confirm` → `ConfirmAppointmentCommand` → Scheduling
- POST `/appointments/{appointmentId}:check-in` → `CheckInAppointmentCommand` → Scheduling
- POST `/appointments/{appointmentId}:start` → `StartAppointmentCommand` → Scheduling
- POST `/appointments/{appointmentId}:complete` → `CompleteAppointmentCommand` → Scheduling
- POST `/appointments/{appointmentId}:cancel` → `CancelAppointmentCommand` → Scheduling
- POST `/appointments/{appointmentId}:no-show` → `MarkNoShowCommand` → Scheduling
- POST `/appointments/{appointmentId}:reschedule` → `RescheduleAppointmentCommand` → Scheduling
- POST `/appointments/{appointmentId}:restore` → `RestoreAppointmentCommand` → Scheduling

### Participants & notes
- GET `/appointments/{appointmentId}/participants` → `ListAppointmentParticipantsQuery` → Scheduling
- POST `/appointments/{appointmentId}/participants` → `AddAppointmentParticipantCommand` → Scheduling
- DELETE `/appointments/{appointmentId}/participants/{participantId}` → `RemoveAppointmentParticipantCommand` → Scheduling
- GET `/appointments/{appointmentId}/notes` → `ListAppointmentNotesQuery` → Scheduling
- POST `/appointments/{appointmentId}/notes` → `AddAppointmentNoteCommand` → Scheduling
- PATCH `/appointments/{appointmentId}/notes/{noteId}` → `UpdateAppointmentNoteCommand` → Scheduling
- DELETE `/appointments/{appointmentId}/notes/{noteId}` → `DeleteAppointmentNoteCommand` → Scheduling
- appointment participants are tenant-owned subresources and use `participant_type = user|provider|external`, `reference_id`, `display_name`, `role`, optional `required`, and optional `notes`
- `user` participants must reference an active tenant user membership; `provider` participants must reference an active tenant provider; `external` participants carry free-text `display_name`
- participant reads are ordered by `required desc`, `display_name asc`, `created_at asc`; participant deletion hard-deletes the row
- appointment notes are authored tenant-owned subresources with `body`, immutable author metadata, `created_at`, and `updated_at`
- appointment notes are ordered by `updated_at desc`, `created_at desc`, `id desc`; note deletion hard-deletes the row
- participant mutations write `appointments.participant_added` and `appointments.participant_removed`
- note mutations write `appointments.note_added`, `appointments.note_updated`, and `appointments.note_deleted`

### Reminders & communications
- POST `/appointments/{appointmentId}:send-reminder` → `SendAppointmentReminderCommand` → Notifications
- POST `/appointments/{appointmentId}:send-confirmation` → `SendAppointmentConfirmationCommand` → Notifications
- reminder sends are allowed only for future `scheduled|confirmed` appointments, compute a local `window_key` of `advance|day_before|same_day`, and are idempotent per appointment, channel, and window
- confirmation sends are allowed only for future `scheduled` appointments whose clinic has `require_appointment_confirmation = true` and are idempotent per appointment and channel
- appointment-linked sends resolve active tenant templates by exact code: `APPOINTMENT-REMINDER-SMS`, `APPOINTMENT-REMINDER-EMAIL`, `APPOINTMENT-CONFIRMATION-SMS`, and `APPOINTMENT-CONFIRMATION-EMAIL`
- appointment-linked recipient resolution uses patient `phone` and `email` first, then falls back to ordered patient contacts
- appointment-linked notifications persist a linkage record between the appointment and the queued notification row so reminder windows and confirmation requests remain auditable

### Recurrence (optional)
- POST `/appointments/{appointmentId}:make-recurring` → `MakeAppointmentRecurringCommand` → Scheduling
- POST `/appointments/recurrences/{recurrenceId}:cancel` → `CancelRecurrenceCommand` → Scheduling

### Waitlist
- GET `/waitlist` → `ListWaitlistQuery` → Scheduling
- POST `/waitlist` → `AddToWaitlistCommand` → Scheduling
- DELETE `/waitlist/{entryId}` → `RemoveFromWaitlistCommand` → Scheduling
- POST `/waitlist/{entryId}:offer-slot` → `OfferWaitlistSlotCommand` → Scheduling

### Bulk & ops
- POST `/appointments/bulk` → `BulkUpdateAppointmentsCommand` → Scheduling
- POST `/appointments:bulk-cancel` → `BulkCancelAppointmentsCommand` → Scheduling
- POST `/appointments:bulk-reschedule` → `BulkRescheduleAppointmentsCommand` → Scheduling
- POST `/appointments:rebuild-cache` → `RebuildSchedulingCacheCommand` → Scheduling
- `POST /appointments/bulk` applies one shared change set across `1..100` distinct appointment ids, requires `Idempotency-Key`, and is all-or-nothing
- bulk draft updates support the same mutable fields as the single generic draft `PATCH /appointments/{appointmentId}` route
- bulk draft updates are limited to active `draft` appointments so explicit state-transition routes remain authoritative for scheduled workflows
- bulk draft updates return `operation_id`, `affected_count`, `updated_fields`, and the updated appointment payloads in input order
- bulk draft updates write one summary `appointments.bulk_updated` audit event plus per-appointment `appointments.updated` events linked to the bulk operation
- `POST /appointments:bulk-cancel` and `POST /appointments:bulk-reschedule` are explicit workflow routes with all-or-nothing transition semantics documented in ADR `028`
- `T040` implements the action-route contract, booked-slot blocking, recurrence materialization, waitlist booking, and bulk transition contracts documented in ADR `028`

---

## A.8 Treatment Plans & Encounters (32)
- GET `/treatment-plans` → `ListTreatmentPlansQuery` → Treatment
- POST `/treatment-plans` → `CreateTreatmentPlanCommand` → Treatment
- GET `/treatment-plans/{planId}` → `GetTreatmentPlanQuery` → Treatment
- PATCH `/treatment-plans/{planId}` → `UpdateTreatmentPlanCommand` → Treatment
- DELETE `/treatment-plans/{planId}` → `DeleteTreatmentPlanCommand` → Treatment
- GET `/treatment-plans/search` → `SearchTreatmentPlansQuery` → Treatment
- GET `/treatment-plans/{planId}/items` → `ListTreatmentItemsQuery` → Treatment
- POST `/treatment-plans/{planId}/items` → `AddTreatmentItemCommand` → Treatment
- PATCH `/treatment-plans/{planId}/items/{itemId}` → `UpdateTreatmentItemCommand` → Treatment
- DELETE `/treatment-plans/{planId}/items/{itemId}` → `RemoveTreatmentItemCommand` → Treatment

### Treatment state actions
- POST `/treatment-plans/{planId}:approve` → `ApproveTreatmentPlanCommand` → Treatment
- POST `/treatment-plans/{planId}:start` → `StartTreatmentPlanCommand` → Treatment
- POST `/treatment-plans/{planId}:pause` → `PauseTreatmentPlanCommand` → Treatment
- POST `/treatment-plans/{planId}:resume` → `ResumeTreatmentPlanCommand` → Treatment
- POST `/treatment-plans/{planId}:finish` → `FinishTreatmentPlanCommand` → Treatment
- POST `/treatment-plans/{planId}:reject` → `RejectTreatmentPlanCommand` → Treatment
- `T043` implements treatment-plan search plus ordered treatment-item routes defined in ADR `030`; no separate bulk treatment route is introduced because the canonical route inventory does not define one in this phase

### Encounters / visits
- GET `/encounters` → `ListEncountersQuery` → Treatment
- POST `/encounters` → `CreateEncounterCommand` → Treatment
- GET `/encounters/{encounterId}` → `GetEncounterQuery` → Treatment
- PATCH `/encounters/{encounterId}` → `UpdateEncounterCommand` → Treatment
- DELETE `/encounters/{encounterId}` → `DeleteEncounterCommand` → Treatment
- GET `/encounters/{encounterId}/diagnoses` → `ListDiagnosesQuery` → Treatment
- POST `/encounters/{encounterId}/diagnoses` → `AddDiagnosisCommand` → Treatment
- DELETE `/encounters/{encounterId}/diagnoses/{dxId}` → `RemoveDiagnosisCommand` → Treatment
- GET `/encounters/{encounterId}/procedures` → `ListProceduresQuery` → Treatment
- POST `/encounters/{encounterId}/procedures` → `AddProcedureCommand` → Treatment
- DELETE `/encounters/{encounterId}/procedures/{procId}` → `RemoveProcedureCommand` → Treatment
- GET `/encounters/export` → `ExportEncountersQuery` → Treatment
- POST `/encounters/bulk` → `BulkUpdateEncountersCommand` → Treatment
- `T044` implements encounters through ADR `031`.
- Encounters are tenant-scoped visit records with patient and provider linkage, optional treatment-plan and appointment linkage, optional clinic and room linkage, `open|completed|entered_in_error` status, `encountered_at`, `timezone`, optional `chief_complaint`, optional `summary`, optional `notes`, optional `follow_up_instructions`, and soft-delete retention.
- `GET /encounters` is the filterable directory route. It supports `q`, `status`, `patient_id`, `provider_id`, `treatment_plan_id`, `appointment_id`, `clinic_id`, `encounter_from`, `encounter_to`, `created_from`, `created_to`, and `limit`, and it returns `meta.filters`.
- Encounter exports reuse the active encounter list filter set, support `format=csv`, store a private export artifact, and write `encounters.exported`.
- Diagnoses are encounter-owned subresources with `code`, `display_name`, `diagnosis_type = primary|secondary`, and optional `notes`. Each encounter may have at most one primary diagnosis.
- Procedures are encounter-owned subresources with optional `treatment_item_id`, optional `code`, `display_name`, optional `performed_at`, and optional `notes`.
- Procedure linkage to treatment-plan items is allowed only when the encounter already links to a treatment plan and the referenced treatment item belongs to that plan with `item_type = procedure`.
- `POST /encounters/bulk` is the generic shared-change bulk route for active encounters. It requires `Idempotency-Key`, accepts `encounter_ids` plus a shared `changes` object, supports `1..100` distinct ids, is all-or-nothing, and may update only `status`, `provider_id`, `clinic_id`, `room_id`, `encountered_at`, and `timezone`.

---

## A.9 Labs: Orders & Results (28)
- GET `/lab-orders` → `ListLabOrdersQuery` → Lab
- POST `/lab-orders` → `CreateLabOrderCommand` → Lab
- GET `/lab-orders/{orderId}` → `GetLabOrderQuery` → Lab
- PATCH `/lab-orders/{orderId}` → `UpdateLabOrderCommand` → Lab
- DELETE `/lab-orders/{orderId}` → `DeleteLabOrderCommand` → Lab
- GET `/lab-orders/search` → `SearchLabOrdersQuery` → Lab
- POST `/lab-orders/{orderId}:send` → `SendLabOrderCommand` → Integrations
- POST `/lab-orders/{orderId}:cancel` → `CancelLabOrderCommand` → Lab
- POST `/lab-orders/{orderId}:mark-collected` → `MarkSpecimenCollectedCommand` → Lab
- POST `/lab-orders/{orderId}:mark-received` → `MarkSpecimenReceivedCommand` → Lab
- POST `/lab-orders/{orderId}:mark-complete` → `MarkLabOrderCompleteCommand` → Lab
- GET `/lab-orders/{orderId}/results` → `ListLabResultsQuery` → Lab
- GET `/lab-orders/{orderId}/results/{resultId}` → `GetLabResultQuery` → Lab

### Lab reference data
- GET `/lab-tests` → `ListLabTestsQuery` → Lab
- POST `/lab-tests` → `CreateLabTestCommand` → Lab
- PATCH `/lab-tests/{testId}` → `UpdateLabTestCommand` → Lab
- DELETE `/lab-tests/{testId}` → `DeleteLabTestCommand` → Lab

### Webhooks
- POST `/webhooks/lab/{provider}` → `ReceiveLabResultWebhookCommand` → Integrations
- POST `/webhooks/lab/{provider}:verify` → `VerifyLabWebhookCommand` → Integrations

### Ops
- GET `/lab-orders/export` → `ExportLabOrdersQuery` → Lab
- POST `/lab-orders/bulk` → `BulkUpdateLabOrdersCommand` → Lab
- POST `/lab-orders:reconcile` → `ReconcileLabOrdersCommand` → Lab
- `T045` implements lab orders through ADR `032`.
- Lab orders are tenant-scoped single-test orders with patient and provider linkage, optional `encounter_id`, optional `treatment_item_id`, `lab_provider_key`, requested-test snapshots, workflow timestamps, optional `external_order_id`, and soft-delete retention.
- Lab-order status values are `draft`, `sent`, `specimen_collected`, `specimen_received`, `completed`, and `canceled`.
- Generic `PATCH /lab-orders/{orderId}` is draft-only. Workflow changes happen only through `:send`, `:cancel`, `:mark-collected`, `:mark-received`, and `:mark-complete`.
- `POST /lab-orders/{orderId}:send` requires `labs.manage`, `integrations.manage`, and `Idempotency-Key`, dispatches through a provider gateway selected by `lab_provider_key`, stores `external_order_id`, and transitions the order to `sent`.
- `GET /lab-orders`, `GET /lab-orders/search`, and `GET /lab-orders/export` share the same filter contract: `q`, `status`, `patient_id`, `provider_id`, `encounter_id`, `lab_test_id`, `lab_provider_key`, `ordered_from`, `ordered_to`, `created_from`, `created_to`, and `limit`.
- `POST /lab-orders/bulk` is an all-or-nothing draft-only route. It requires `Idempotency-Key`, accepts `order_ids` plus a shared `changes` object, supports `1..100` distinct ids, and may update only `encounter_id`, `treatment_item_id`, `lab_test_id`, `lab_provider_key`, `ordered_at`, `timezone`, and `notes`.
- Lab tests are tenant-scoped catalog records with `code`, `name`, `specimen_type`, `result_type`, `lab_provider_key`, optional `unit`, optional `reference_range`, optional `description`, optional `external_test_code`, and `is_active`.
- Lab results are read-only order-owned records created through webhook or reconciliation intake. They expose `status = preliminary|final|corrected`, typed value storage, optional `abnormal_flag`, `observed_at`, `received_at`, and provider payload metadata.
- `POST /webhooks/lab/{provider}` is the public inbound route. It requires `Idempotency-Key`, `X-Lab-Signature`, signature verification before state changes, and a payload containing `delivery_id`, `external_order_id`, `status`, `occurred_at`, and optional `results`.
- `POST /webhooks/lab/{provider}:verify` is the authenticated diagnostics helper. It requires tenant scope plus `integrations.manage` and validates a submitted payload and signature without mutating business state.
- `POST /lab-orders:reconcile` requires `labs.manage`, `integrations.manage`, and `Idempotency-Key`. It synchronizes active sent or specimen-in-flight tenant orders through the same provider gateway and result-sync logic used by webhook intake.

---

## A.10 Prescriptions & Medications (22)
- GET `/prescriptions` → `ListPrescriptionsQuery` → Pharmacy
- POST `/prescriptions` → `CreatePrescriptionCommand` → Pharmacy
- GET `/prescriptions/{prescriptionId}` → `GetPrescriptionQuery` → Pharmacy
- PATCH `/prescriptions/{prescriptionId}` → `UpdatePrescriptionCommand` → Pharmacy
- DELETE `/prescriptions/{prescriptionId}` → `DeletePrescriptionCommand` → Pharmacy
- POST `/prescriptions/{prescriptionId}:issue` → `IssuePrescriptionCommand` → Pharmacy
- POST `/prescriptions/{prescriptionId}:cancel` → `CancelPrescriptionCommand` → Pharmacy
- POST `/prescriptions/{prescriptionId}:dispense` → `DispensePrescriptionCommand` → Pharmacy
- GET `/prescriptions/search` → `SearchPrescriptionsQuery` → Pharmacy
- GET `/prescriptions/export` → `ExportPrescriptionsQuery` → Pharmacy

- `T046` implements only the prescription aggregate, lifecycle, search, and export contract defined in ADR `033`
- `T046` does not depend on the medication catalog; medication identity remains on the prescription as free-text snapshot fields until `T047`
- `GET /prescriptions`, `GET /prescriptions/search`, and `GET /prescriptions/export` share the filter contract `q`, `status`, `patient_id`, `provider_id`, `encounter_id`, `issued_from`, `issued_to`, `start_from`, `start_to`, `created_from`, `created_to`, and `limit`
- `POST /prescriptions/{prescriptionId}:issue` records `issued_at`
- `POST /prescriptions/{prescriptionId}:dispense` records `dispensed_at`
- `POST /prescriptions/{prescriptionId}:cancel` requires a non-empty `reason` and records `canceled_at`

### Medication catalog
- GET `/medications` → `ListMedicationsQuery` → Pharmacy
- POST `/medications` → `CreateMedicationCommand` → Pharmacy
- GET `/medications/{medId}` → `GetMedicationQuery` → Pharmacy
- PATCH `/medications/{medId}` → `UpdateMedicationCommand` → Pharmacy
- DELETE `/medications/{medId}` → `DeleteMedicationCommand` → Pharmacy
- GET `/medications/search` → `SearchMedicationsQuery` → Pharmacy
- `T047` defines the medication catalog contract in ADR `034`
- medications are tenant-scoped catalog records with `code`, `name`, optional `generic_name`, optional `form`, optional `strength`, optional `description`, and `is_active`
- medication `code` is required, normalized to uppercase, and unique per tenant
- `GET /medications` and `GET /medications/search` share filters `q`, `is_active`, and `limit`
- `DELETE /medications/{medId}` hard-deletes the catalog row

### Allergies
- GET `/patients/{patientId}/allergies` → `ListAllergiesQuery` → Pharmacy
- POST `/patients/{patientId}/allergies` → `AddAllergyCommand` → Pharmacy
- DELETE `/patients/{patientId}/allergies/{allergyId}` → `RemoveAllergyCommand` → Pharmacy
- GET `/patients/{patientId}/medications` → `ListPatientMedicationsQuery` → Pharmacy
- allergy records are patient-owned tenant records with an `allergen_name` snapshot, optional `medication_id`, optional `reaction`, optional `severity`, optional `noted_at`, and optional `notes`
- allergy `severity` values are `mild`, `moderate`, `severe`, and `life_threatening`
- duplicate allergies for the same patient and normalized allergen are rejected
- `GET /patients/{patientId}/medications` projects non-draft prescriptions for the patient and supports optional `status` and `limit` filters
- patient medication views may include an optional medication catalog match by exact `medication_code = medications.code`
- `T047` does not require prescriptions to store `medication_id`; prescription snapshot fields remain authoritative

---

## A.11 Billing: Invoices, Payments, Pricing (40)
### Pricing & catalog
- GET `/services` → `ListBillableServicesQuery` → Billing
- POST `/services` → `CreateBillableServiceCommand` → Billing
- PATCH `/services/{serviceId}` → `UpdateBillableServiceCommand` → Billing
- DELETE `/services/{serviceId}` → `DeleteBillableServiceCommand` → Billing
- GET `/price-lists` → `ListPriceListsQuery` → Billing
- POST `/price-lists` → `CreatePriceListCommand` → Billing
- GET `/price-lists/{priceListId}` → `GetPriceListQuery` → Billing
- PATCH `/price-lists/{priceListId}` → `UpdatePriceListCommand` → Billing
- DELETE `/price-lists/{priceListId}` → `DeletePriceListCommand` → Billing
- PUT `/price-lists/{priceListId}/items` → `SetPriceListItemsCommand` → Billing
- `T048` defines the billing catalog contract in ADR `035`
- billable services are tenant-scoped records with `code`, `name`, optional `category`, optional `unit`, optional `description`, and `is_active`
- billable service `code` is required, normalized to uppercase, and unique per tenant
- referenced billable services cannot be deleted while price-list items still exist
- `GET /services` supports filters `q`, `category`, `is_active`, and `limit`
- price lists own `code`, `name`, optional `description`, `currency`, `is_default`, `is_active`, optional `effective_from`, and optional `effective_to`
- setting `is_default=true` clears any prior tenant default list
- `GET /price-lists` supports filters `q`, `is_active`, `is_default`, `active_on`, and `limit`
- `PUT /price-lists/{priceListId}/items` fully replaces the item set and accepts `service_id` plus positive decimal `amount`
- empty price-list item arrays are valid and clear the list

### Invoices
- GET `/invoices` → `ListInvoicesQuery` → Billing
- POST `/invoices` → `CreateInvoiceCommand` → Billing
- GET `/invoices/{invoiceId}` → `GetInvoiceQuery` → Billing
- PATCH `/invoices/{invoiceId}` → `UpdateInvoiceCommand` → Billing
- DELETE `/invoices/{invoiceId}` → `DeleteInvoiceCommand` → Billing
- POST `/invoices/{invoiceId}:issue` → `IssueInvoiceCommand` → Billing
- POST `/invoices/{invoiceId}:void` → `VoidInvoiceCommand` → Billing
- POST `/invoices/{invoiceId}:finalize` → `FinalizeInvoiceCommand` → Billing
- GET `/invoices/{invoiceId}/items` → `ListInvoiceItemsQuery` → Billing
- POST `/invoices/{invoiceId}/items` → `AddInvoiceItemCommand` → Billing
- PATCH `/invoices/{invoiceId}/items/{itemId}` → `UpdateInvoiceItemCommand` → Billing
- DELETE `/invoices/{invoiceId}/items/{itemId}` → `RemoveInvoiceItemCommand` → Billing
- GET `/invoices/search` → `SearchInvoicesQuery` → Billing
- GET `/invoices/export` → `ExportInvoicesQuery` → Billing
- `T049` defines the invoice contract in ADR `036`
- invoices are tenant-scoped aggregates with `invoice_number`, `patient_id`, optional `price_list_id`, `currency`, `invoice_date`, optional `due_on`, optional `notes`, status, totals, lifecycle timestamps, and soft-delete retention
- invoice status values are `draft`, `issued`, `finalized`, and `void`
- invoice numbering is tenant-scoped monotonic and formatted as `INV-000001`
- create starts in `draft`; generic `PATCH /invoices/{invoiceId}` is draft-only; delete is soft-delete limited to `draft|void`
- explicit lifecycle is `draft -> issued -> finalized`, with `draft|issued|finalized -> void`
- `issue` requires at least one item and a positive total; `finalize` requires `issued`; `void` requires a non-empty reason
- invoice items are draft-only subresources with service snapshots, quantity, unit price, line subtotal, and invoice currency
- omitting invoice-item `unit_price_amount` requires the invoice to reference a price list containing the selected service
- invoice totals equal the sum of item subtotals; taxes, discounts, credits, and payment allocations are deferred beyond `T049`
- `GET /invoices` and `GET /invoices/search` support `q`, `status`, `patient_id`, `issued_from`, `issued_to`, `due_from`, `due_to`, `created_from`, `created_to`, and `limit`
- `GET /invoices/export` supports CSV export for the same invoice filters

### Payments
- GET `/payments` → `ListPaymentsQuery` → Billing
- POST `/payments:initiate` → `InitiatePaymentCommand` → Billing
- GET `/payments/{paymentId}` → `GetPaymentQuery` → Billing
- GET `/payments/{paymentId}/status` → `GetPaymentStatusQuery` → Billing
- POST `/payments/{paymentId}:cancel` → `CancelPaymentCommand` → Billing
- POST `/payments/{paymentId}:refund` → `RefundPaymentCommand` → Billing
- POST `/payments/{paymentId}:capture` → `CapturePaymentCommand` → Billing
- `T050` defines the payment aggregate and initiation contract in ADR `037`
- payment status values are `initiated`, `pending`, `captured`, `failed`, `canceled`, and `refunded`
- payment initiation requires `invoice_id`, `provider_key`, and `amount`
- payments link to a single tenant-scoped invoice and snapshot `invoice_number`
- initiation is allowed only for invoices in `issued|finalized`
- payment `currency` must equal the linked invoice currency and `amount` must be positive and no greater than invoice `total_amount`
- payment creation starts in `initiated`
- `GET /payments` supports `q`, `status`, `invoice_id`, `provider_key`, `created_from`, `created_to`, and `limit`
- `GET /payments/{paymentId}/status` returns a status-focused projection of the stored payment lifecycle
- `POST /payments:initiate`, `POST /payments/{paymentId}:capture`, `POST /payments/{paymentId}:cancel`, and `POST /payments/{paymentId}:refund` require `Idempotency-Key`
- allowed forward transitions are `initiated -> pending`, `pending -> captured|failed|canceled`, and `captured -> refunded`
- refunds are allowed only when the gateway supports refunds and the payment is already `captured`
- payment creation and transitions write audit records and billing outbox events
- payment allocation and invoice balance mutation remain deferred in this phase
- `provider_key = payme` returns a direct Payme checkout URL built from documented merchant checkout parameters
- Payme generic capture, cancel, and refund action routes are not supported in this phase and return `409`
- `provider_key = click` returns a direct Click payment-button checkout URL built from `service_id`, `merchant_id`, `amount`, and `transaction_param`, with optional configured `merchant_user_id`, `return_url`, and `card_type`
- Click generic capture, cancel, and refund action routes are not supported in this phase and return `409`
- `provider_key = uzum` keeps the local payment in `initiated`, stores provider status `awaiting_uzum_webhook`, and does not return a checkout URL in this phase
- Uzum generic capture, cancel, and refund action routes are not supported in this phase and return `409`

### Reconciliation
- POST `/payments:reconcile` → `ReconcilePaymentsCommand` → Billing
- GET `/payments/reconciliation-runs` → `ListReconciliationRunsQuery` → Billing
- GET `/payments/reconciliation-runs/{runId}` → `GetReconciliationRunQuery` → Billing
- reconciliation requires `provider_key`, accepts optional `payment_ids[]` and `limit`, stores a tenant-scoped run record, and returns per-payment result snapshots
- Uzum reconciliation uses the payment gateway snapshot abstraction to mark stale `pending` payments as `failed` after the configured confirmation timeout instead of polling a remote provider API in this phase

### Webhooks (Uzbek payments)
- POST `/webhooks/payme` → `HandlePaymeWebhookCommand` → Integrations
- POST `/webhooks/click` → `HandleClickWebhookCommand` → Integrations
- POST `/webhooks/uzum` → `HandleUzumWebhookCommand` → Integrations
- POST `/webhooks/payme:verify` → `VerifyPaymeWebhookCommand` → Integrations
- POST `/webhooks/click:verify` → `VerifyClickWebhookCommand` → Integrations
- POST `/webhooks/uzum:verify` → `VerifyUzumWebhookCommand` → Integrations
- Payme webhook transport uses JSON-RPC 2.0 and always returns HTTP `200`
- Payme verification uses the `Authorization` header with Merchant API Basic auth and the configured merchant key
- Payme request linkage uses `account.payment_id` and amount matching in tiyin
- Payme supports `CheckPerformTransaction`, `CreateTransaction`, `PerformTransaction`, `CancelTransaction`, `CheckTransaction`, and `GetStatement`
- Payme mutating methods are replay-safe by provider transaction id and do not require `Idempotency-Key`
- Click webhook transport uses the documented Shop API callback JSON contract over `POST /webhooks/click`
- Click verification uses the documented MD5 `sign_string` with the configured secret key and service id
- Click request linkage uses `merchant_trans_id` for local payment lookup and `click_trans_id` for replay-safe processing
- Click `Prepare` (`action = 0`) maps local `initiated -> pending`
- Click `Complete` (`action = 1`) with `error = 0` maps local `pending -> captured`
- Click `Complete` (`action = 1`) with `error < 0` maps local `pending -> canceled`
- Click `Prepare` and `Complete` are replay-safe by `click_trans_id` and do not require `Idempotency-Key`
- Uzum webhook transport uses `POST /webhooks/uzum?operation={check|create|confirm|reverse|status}`
- Uzum verification uses the `Authorization` header with configured Basic auth plus payload field `serviceId`
- Uzum request linkage uses `params.payment_id`, with `params.account.value` accepted as a compatibility alias
- Uzum replay safety uses `transId` plus `operation`
- Uzum `check` is read-only and validates authentication, service id, payment existence, and amount
- Uzum `create` maps local `initiated -> pending` with provider status `CREATED`
- Uzum `confirm` maps local `pending -> captured` with provider status `CONFIRMED`
- Uzum `reverse` maps local `initiated|pending -> canceled` with provider status `CANCELED` and local `captured -> refunded` with provider status `REFUNDED`
- Uzum `status` returns the current normalized provider state from the local payment lifecycle

---

## A.12 Insurance Claims (State Machine) (28)
- GET `/insurance/payers` → `ListPayersQuery` → Insurance
- POST `/insurance/payers` → `CreatePayerCommand` → Insurance
- PATCH `/insurance/payers/{payerId}` → `UpdatePayerCommand` → Insurance
- DELETE `/insurance/payers/{payerId}` → `DeletePayerCommand` → Insurance

- GET `/claims` → `ListClaimsQuery` → Insurance
- POST `/claims` → `CreateClaimCommand` → Insurance
- GET `/claims/{claimId}` → `GetClaimQuery` → Insurance
- PATCH `/claims/{claimId}` → `UpdateClaimCommand` → Insurance
- DELETE `/claims/{claimId}` → `DeleteClaimCommand` → Insurance
- GET `/claims/search` → `SearchClaimsQuery` → Insurance
- GET `/claims/export` → `ExportClaimsQuery` → Insurance

### Claim state actions
- POST `/claims/{claimId}:submit` → `SubmitClaimCommand` → Insurance
- POST `/claims/{claimId}:start-review` → `StartClaimReviewCommand` → Insurance
- POST `/claims/{claimId}:approve` → `ApproveClaimCommand` → Insurance
- POST `/claims/{claimId}:deny` → `DenyClaimCommand` → Insurance
- POST `/claims/{claimId}:mark-paid` → `MarkClaimPaidCommand` → Insurance
- POST `/claims/{claimId}:reopen` → `ReopenClaimCommand` → Insurance

### Attachments
- GET `/claims/{claimId}/attachments` → `ListClaimAttachmentsQuery` → Insurance
- POST `/claims/{claimId}/attachments` → `UploadClaimAttachmentCommand` → Insurance
- DELETE `/claims/{claimId}/attachments/{attachmentId}` → `DeleteClaimAttachmentCommand` → Insurance

### Rules
- GET `/insurance/rules` → `ListInsuranceRulesQuery` → Insurance
- POST `/insurance/rules` → `CreateInsuranceRuleCommand` → Insurance
- PATCH `/insurance/rules/{ruleId}` → `UpdateInsuranceRuleCommand` → Insurance
- DELETE `/insurance/rules/{ruleId}` → `DeleteInsuranceRuleCommand` → Insurance

### Claim notes
- payer `code` is tenant-unique uppercase and payer `insurance_code` is tenant-unique lowercase
- insurance rules are payer-owned and may enforce service-category matching, attachment requirements, primary-policy requirements, billed-amount caps, and submission windows
- claims are tenant-scoped aggregates with monotonic claim numbers in the form `CLM-000001`
- claim creation requires `invoice_id` and `payer_id`, may reference one patient insurance policy, and is limited to invoices in `issued|finalized`
- claim billed amount must be positive and may not exceed the linked invoice total
- generic claim patch and delete are draft-only
- claim states are `draft`, `submitted`, `under_review`, `approved`, `denied`, and `paid`
- allowed claim transitions are `draft -> submitted`, `submitted -> under_review`, `under_review -> approved|denied`, `approved -> paid`, and `approved|denied|paid -> submitted` through reopen
- `start-review`, `approve`, `deny`, `mark-paid`, and `reopen` require `reason` and `source_evidence`
- claim attachments use shared attachment storage and can be added or removed for any non-deleted claim
- claim list and search support `q`, `status`, `payer_id`, `patient_id`, `invoice_id`, `service_date_from`, `service_date_to`, `created_from`, `created_to`, and `limit`
- claim export uses CSV with a maximum limit of `1000`

---

## A.13 Notifications: SMS/Email/Telegram (34)
### Templates
- GET `/templates` → `ListTemplatesQuery` → Notifications
- POST `/templates` → `CreateTemplateCommand` → Notifications
- GET `/templates/{templateId}` → `GetTemplateQuery` → Notifications
- PATCH `/templates/{templateId}` → `UpdateTemplateCommand` → Notifications
- DELETE `/templates/{templateId}` → `DeleteTemplateCommand` → Notifications
- POST `/templates/{templateId}:test-render` → `TestRenderTemplateCommand` → Notifications
- template `code` is uppercase and unique per tenant among non-deleted templates
- `GET /templates` supports `q`, `channel`, `is_active`, and `limit`
- `GET /templates/{templateId}` returns the current projection plus immutable versions in descending version order
- email templates require `subject_template` and `body_template`
- sms and telegram templates require `body_template` and persist `subject_template = null`
- render placeholders use `{{path.to.value}}` lookup against the `variables` object
- missing placeholders and non-scalar final values return `422`

### Channels
- POST `/notifications:test/sms` → `SendTestSmsCommand` → Notifications
- POST `/notifications:test/email` → `SendTestEmailCommand` → Notifications
- POST `/notifications:test/telegram` → `SendTestTelegramCommand` → Notifications

### Dispatch
- POST `/notifications` → `SendNotificationCommand` → Notifications
- GET `/notifications` → `ListNotificationsQuery` → Notifications
- GET `/notifications/{notificationId}` → `GetNotificationQuery` → Notifications
- POST `/notifications/{notificationId}:retry` → `RetryNotificationCommand` → Notifications
- POST `/notifications/{notificationId}:cancel` → `CancelNotificationCommand` → Notifications
- `POST /notifications` requires `template_id`, `recipient`, and `variables`; `metadata` is optional
- the referenced template must exist in the current tenant and be active
- send is queue-first for this phase: the API stores a rendered notification snapshot in `queued` and publishes `notification.queued` to `medflow.notifications.v1`
- notification states are `queued`, `sent`, `failed`, and `canceled`
- retry is allowed only from `failed` and only while `attempts < max_attempts`
- cancel is allowed from `queued|failed`
- notifications snapshot rendered subject and body so later template edits do not rewrite delivery history
- `GET /notifications` supports `q`, `status`, `channel`, `template_id`, `created_from`, `created_to`, and `limit`
- `email` recipients require `recipient.email` and optional `recipient.name`
- `sms` recipients require `recipient.phone_number`
- `telegram` recipients require `recipient.chat_id`

### Provider configs
- GET `/notification-providers/sms` → `ListSmsProvidersQuery` → Notifications
- PUT `/notification-providers/sms` → `SetSmsProvidersPriorityCommand` → Notifications
- GET `/notification-providers/email` → `GetEmailProviderQuery` → Notifications
- PUT `/notification-providers/email` → `SetEmailProviderCommand` → Notifications
- GET `/notification-providers/telegram` → `GetTelegramProviderQuery` → Notifications
- PUT `/notification-providers/telegram` → `SetTelegramProviderCommand` → Notifications

### SMS providers (Uz)
- POST `/integrations/eskiz:send` → `SendEskizSmsCommand` → Integrations
- POST `/integrations/playmobile:send` → `SendPlayMobileSmsCommand` → Integrations
- POST `/integrations/textup:send` → `SendTextUpSmsCommand` → Integrations
- SMS routing is tenant-scoped per `message_type` with default priority `otp: eskiz -> playmobile -> textup`, `reminder: playmobile -> eskiz -> textup`, `transactional: eskiz -> playmobile -> textup`, and `bulk: textup -> playmobile -> eskiz`
- `POST /notifications:test/sms` uses the same routing and failover engine as queued delivery but does not persist a notification row
- provider-specific SMS routes force a single provider for diagnostics and do not persist a notification row
- queued or retried SMS notifications are consumed from `medflow.notifications.v1`, count one attempt per provider try, and publish `notification.sent|notification.failed` after delivery processing

### Telegram
- POST `/webhooks/telegram` → `HandleTelegramWebhookCommand` → Integrations
- POST `/telegram/bot:broadcast` → `BroadcastTelegramCommand` → Notifications
- POST `/telegram/bot:sync` → `SyncTelegramBotCommand` → Integrations
- GET `/notification-providers/telegram` returns tenant-scoped Telegram settings plus the last synced bot snapshot
- PUT `/notification-providers/telegram` replaces tenant-scoped Telegram settings: `enabled`, `parse_mode`, `broadcast_chat_ids[]`, `support_chat_ids[]`
- Telegram chat ids may not be assigned to multiple tenants because webhook tenant resolution must remain deterministic
- POST `/notifications:test/telegram` uses the Telegram adapter directly for diagnostics and does not persist a notification row
- queued or retried Telegram notifications are consumed from `medflow.notifications.v1`, use one provider attempt per send, and transition `queued -> sent|failed`
- `POST /telegram/bot:broadcast` requires `message` plus either explicit `chat_ids[]` or `audience = configured_broadcast|configured_support|all_configured`
- `POST /webhooks/telegram` verifies `X-Telegram-Bot-Api-Secret-Token`, stores replay-safe delivery metadata keyed by `update_id`, and records mapped support-chat messages in audit history
- `POST /telegram/bot:sync` reconciles `getMe`, `getWebhookInfo`, and the expected webhook URL `APP_URL + /api/v1/webhooks/telegram`

### Email
- GET `/notification-providers/email` → `GetEmailProviderQuery` → Notifications
- PUT `/notification-providers/email` → `SetEmailProviderCommand` → Notifications
- POST `/notifications:test/email` → `SendTestEmailCommand` → Notifications
- POST `/email:send` → `SendEmailCommand` → Notifications
- GET `/email/events` → `ListEmailEventsQuery` → Notifications
- GET `/notification-providers/email` returns tenant-scoped sender settings with `enabled`, `provider_key`, `from_address`, `from_name`, and optional reply-to fields
- PUT `/notification-providers/email` fully replaces the tenant sender settings; transport credential inventory is managed through `PUT /integrations/email/credentials`
- POST `/notifications:test/email` uses the configured adapter directly, returns `notification_test_email_sent|notification_test_email_failed`, and does not persist notification or email-event rows
- queued or retried email notifications are consumed from `medflow.notifications.v1`, use one delivery attempt per send, transition `queued -> sent|failed`, and append one email-event record per outcome
- POST `/email:send` sends one transactional email directly without creating a notification row and always appends an email-event row with `source = direct`
- GET `/email/events` returns append-only delivery outcomes for actual sends and supports `q`, `source`, `event_type`, `notification_id`, `created_from`, `created_to`, and `limit`

---

## A.14 Integrations Hub (Configs, Tokens, Webhooks) (36)
### Integration registry
- GET `/integrations` → `ListIntegrationsQuery` → Integrations
- GET `/integrations/{integrationKey}` → `GetIntegrationQuery` → Integrations
- POST `/integrations/{integrationKey}:enable` → `EnableIntegrationCommand` → Integrations
- POST `/integrations/{integrationKey}:disable` → `DisableIntegrationCommand` → Integrations

### Credentials per tenant
- GET `/integrations/{integrationKey}/credentials` → `GetIntegrationCredentialsQuery` → Integrations
- PUT `/integrations/{integrationKey}/credentials` → `UpsertIntegrationCredentialsCommand` → Integrations
- DELETE `/integrations/{integrationKey}/credentials` → `DeleteIntegrationCredentialsCommand` → Integrations

### Health & diagnostics
- GET `/integrations/{integrationKey}/health` → `IntegrationHealthQuery` → Integrations
- POST `/integrations/{integrationKey}:test-connection` → `TestIntegrationConnectionCommand` → Integrations
- GET `/integrations/{integrationKey}/logs` → `ListIntegrationLogsQuery` → Integrations

### Webhook management
- GET `/integrations/{integrationKey}/webhooks` → `ListIntegrationWebhooksQuery` → Integrations
- POST `/integrations/{integrationKey}/webhooks` → `CreateIntegrationWebhookCommand` → Integrations
- DELETE `/integrations/{integrationKey}/webhooks/{webhookId}` → `DeleteIntegrationWebhookCommand` → Integrations
- POST `/integrations/{integrationKey}/webhooks/{webhookId}:rotate-secret` → `RotateWebhookSecretCommand` → Integrations

### OAuth token stores
- GET `/integrations/{integrationKey}/tokens` → `ListIntegrationTokensQuery` → Integrations
- POST `/integrations/{integrationKey}/tokens:refresh` → `RefreshIntegrationTokensCommand` → Integrations
- DELETE `/integrations/{integrationKey}/tokens/{tokenId}` → `RevokeIntegrationTokenCommand` → Integrations

### Uzbekistan add-ons (optional)
- POST `/integrations/myid:verify` → `VerifyMyIdCommand` → Integrations
- POST `/webhooks/myid` → `HandleMyIdWebhookCommand` → Integrations
- POST `/integrations/eimzo:sign` → `CreateEImzoSignRequestCommand` → Integrations
- POST `/webhooks/eimzo` → `HandleEImzoWebhookCommand` → Integrations

- Supported catalog keys in this phase are `email`, `telegram`, `eskiz`, `playmobile`, `textup`, `payme`, `click`, `uzum`, `mock-lab`, `myid`, and `eimzo`
- feature-flagged optional integrations remain visible in the registry with `available = false`
- integration registry enablement is tenant-scoped and controls the hub administrative state for the tenant
- `GET /integrations/{integrationKey}/credentials` returns field schema plus masked previews; raw secrets are never returned after persistence
- `PUT /integrations/{integrationKey}/credentials` fully replaces the tenant-managed credential payload
- credential deletion revokes active hub-managed tokens for the same integration key
- `GET /integrations/{integrationKey}/health` returns `status = healthy|degraded|failing|disabled` plus ordered readiness checks
- `POST /integrations/{integrationKey}:test-connection` is a deterministic readiness probe for this phase and records audit plus integration-log entries
- integration logs are append-only tenant-scoped operational records and support `level`, `event`, and `limit`
- webhook inventory records are tenant-managed metadata entries; deleting one does not remove the underlying Laravel webhook route
- secret-managed webhook integrations return the generated or rotated secret exactly once
- token inventory returns metadata only; raw access and refresh tokens are not returned after persistence
- `POST /integrations/myid:verify` requires `external_reference`, `subject`, and tenant readiness through the integrations hub, then creates a `pending` verification session with a locally generated provider reference
- `POST /integrations/eimzo:sign` requires `external_reference`, `document_hash`, `document_name`, and tenant readiness through the integrations hub, then creates a `pending` sign request with a locally generated provider reference
- `POST /webhooks/myid` and `POST /webhooks/eimzo` require `X-Integration-Webhook-Secret`, `webhook_id`, `delivery_id`, `provider_reference`, and `status`, resolve tenant scope from managed webhook inventory, and deduplicate replays by `integration_key + webhook_id + delivery_id`
- token refresh supports an optional `token_id` and otherwise refreshes the latest active token

---

## A.15 Audit & Compliance (18)
- GET `/audit/events` → `ListAuditEventsQuery` → Audit
- GET `/audit/events/{eventId}` → `GetAuditEventQuery` → Audit
- GET `/audit/export` → `ExportAuditEventsQuery` → Audit
- GET `/audit/retention` → `GetAuditRetentionQuery` → Audit
- PUT `/audit/retention` → `UpdateAuditRetentionCommand` → Audit
- GET `/audit/object/{objectType}/{objectId}` → `GetObjectAuditQuery` → Audit
- GET `/compliance/pii-fields` → `ListPiiFieldsQuery` → Compliance
- PUT `/compliance/pii-fields` → `SetPiiFieldsCommand` → Compliance
- POST `/compliance/pii:rotate-keys` → `RotatePiiKeysCommand` → Compliance
- POST `/compliance/pii:re-encrypt` → `ReEncryptPiiCommand` → Compliance
- GET `/consents` → `ListConsentsQuery` → Compliance
- GET `/consents/{consentId}` → `GetConsentQuery` → Compliance
- GET `/data-access-requests` → `ListDataAccessRequestsQuery` → Compliance
- POST `/data-access-requests` → `CreateDataAccessRequestCommand` → Compliance
- POST `/data-access-requests/{requestId}:approve` → `ApproveDataAccessRequestCommand` → Compliance
- POST `/data-access-requests/{requestId}:deny` → `DenyDataAccessRequestCommand` → Compliance
- GET `/data-access-requests/{requestId}` → `GetDataAccessRequestQuery` → Compliance
- GET `/compliance/reports` → `ListComplianceReportsQuery` → Compliance

Compliance consent views and data-access-request rules:

- `GET /consents` is a tenant-scoped read-only compliance projection over patient consent history and supports filters `q`, `patient_id`, `consent_type`, `status`, `granted_from`, `granted_to`, `expires_from`, `expires_to`, and `limit`
- consent views return patient summary context plus the underlying patient consent timestamps and derived `active|expired|revoked` status
- `GET /consents/{consentId}` returns one tenant-owned consent projection only when the consent belongs to the active tenant
- `GET /data-access-requests` supports filters `q`, `patient_id`, `request_type`, `status`, `requested_from`, `requested_to`, and `limit`
- data access requests are tenant-scoped patient-linked workflow records with status `submitted|approved|denied`
- `POST /data-access-requests` accepts `patient_id`, `request_type`, `requested_by_name`, optional `requested_by_relationship`, optional `requested_at`, optional `reason`, and optional `notes`
- `request_type` is normalized to lowercase snake case and new requests always start in `submitted`
- `POST /data-access-requests/{requestId}:approve` accepts optional `decision_notes`, works only from `submitted`, and records reviewer identity and `approved_at`
- `POST /data-access-requests/{requestId}:deny` requires `reason`, accepts optional `decision_notes`, works only from `submitted`, and records reviewer identity and `denied_at`
- approve or deny against a non-`submitted` request returns `409 Conflict`
- compliance workflow mutations write immutable audit actions `compliance.data_access_request_created`, `compliance.data_access_request_approved`, and `compliance.data_access_request_denied`

---

## A.16 Observability, Health, Admin Ops (22)
- GET `/health` → `HealthQuery` → Ops
- GET `/ready` → `ReadinessQuery` → Ops
- GET `/live` → `LivenessQuery` → Ops
- GET `/metrics` → `MetricsQuery` → Ops
- GET `/version` → `VersionQuery` → Ops

### Admin ops
- POST `/admin/cache:flush` → `FlushCacheCommand` → Ops
- POST `/admin/cache:rebuild` → `RebuildCachesCommand` → Ops
- GET `/admin/jobs` → `ListJobsQuery` → Ops
- POST `/admin/jobs/{jobId}:retry` → `RetryJobCommand` → Ops
- GET `/admin/kafka/lag` → `GetKafkaLagQuery` → Ops
- POST `/admin/kafka:replay` → `ReplayKafkaEventsCommand` → Ops
- GET `/admin/outbox` → `ListOutboxQuery` → Ops
- POST `/admin/outbox:drain` → `DrainOutboxCommand` → Ops
- POST `/admin/outbox/{outboxId}:retry` → `RetryOutboxItemCommand` → Ops
- GET `/admin/logging/pipelines` → `ListLoggingPipelinesQuery` → Ops
- POST `/admin/logging:pipeline-reload` → `ReloadLoggingPipelinesCommand` → Ops
- GET `/admin/feature-flags` → `ListFeatureFlagsQuery` → Ops
- PUT `/admin/feature-flags` → `SetFeatureFlagsCommand` → Ops
- GET `/admin/rate-limits` → `GetRateLimitsQuery` → Ops
- PUT `/admin/rate-limits` → `UpdateRateLimitsCommand` → Ops
- GET `/admin/config` → `GetRuntimeConfigQuery` → Ops
- POST `/admin/config:reload` → `ReloadRuntimeConfigCommand` → Ops
- all ops routes are authenticated, tenant-scoped for authorization, and protected by `admin.view` or `admin.manage`
- `GET /live` returns process liveness only; `GET /ready` fails when critical runtime probes fail; `GET /health` returns `healthy|degraded|failing` with ordered checks
- `GET /metrics` returns Prometheus-compatible text with app info, health state, outbox lag, queue counts, and Kafka consumer receipt lag in this phase
- cache admin uses explicit namespace invalidation only; raw store flush is forbidden
- failed job retry reinserts one `failed_jobs` payload into the queue backend and removes the failed row on success
- Kafka replay in this phase clears consumer replay receipts for the selected consumer and event window; broker offset movement remains an operator action outside the API
- outbox admin supports tenant-visible listing, synchronous relay drain, and retry of failed outbox rows only
- feature flags and rate limits are tenant-scoped overrides with config-backed defaults
- runtime config reload refreshes the safe config projection and audit history only; it does not hot-reload environment variables or restart workers

---

## A.17 Reference Data & Search (18)
- GET `/reference/currencies` → `ListCurrenciesQuery` → Shared
- GET `/reference/countries` → `ListCountriesQuery` → Shared
- GET `/reference/languages` → `ListLanguagesQuery` → Shared
- GET `/reference/diagnosis-codes` → `ListDiagnosisCodesQuery` → Shared
- GET `/reference/procedure-codes` → `ListProcedureCodesQuery` → Shared
- GET `/reference/insurance-codes` → `ListInsuranceCodesQuery` → Shared
- GET `/search/global` → `GlobalSearchQuery` → Shared
- GET `/search/patients` → `SearchPatientsQuery` → Patient
- GET `/search/providers` → `SearchProvidersQuery` → Provider
- GET `/search/appointments` → `SearchAppointmentsQuery` → Scheduling
- GET `/search/invoices` → `SearchInvoicesQuery` → Billing
- GET `/search/claims` → `SearchClaimsQuery` → Insurance
- GET `/reports` → `ListReportsQuery` → Reporting
- POST `/reports` → `CreateReportCommand` → Reporting
- GET `/reports/{reportId}` → `GetReportQuery` → Reporting
- POST `/reports/{reportId}:run` → `RunReportCommand` → Reporting
- GET `/reports/{reportId}/download` → `DownloadReportQuery` → Reporting
- DELETE `/reports/{reportId}` → `DeleteReportCommand` → Reporting

---

## A.18 Totals
Approximate count by section:
- Auth & Identity: 16
- Tenants: 12
- Clinics & Locations: 26
- Users/Roles/Permissions: 38
- Patients: 30
- Providers & Availability: 34
- Scheduling/Appointments: 44
- Treatment & Encounters: 32
- Labs: 28
- Prescriptions: 22
- Billing & Payments: 40
- Insurance Claims: 28
- Notifications: 34
- Integrations Hub: 36
- Audit & Compliance: 18
- Observability/Admin Ops: 22
- Reference Data & Search: 18

**Total inventory:** ~**478** lines listed here, with **~280–320 distinct endpoints** depending on whether optional endpoints (MyID/E-IMZO, reporting) are enabled.

---

# Appendix B — ADR Template
`/docs/adr/000-template.md`
- Context
- Decision
- Alternatives
- Consequences
- Migration Plan

---

# Appendix C — Done Definition
A feature is “done” when:
- OpenAPI updated
- Tests added
- Observability updated
- ADR if needed
- No rule violations
