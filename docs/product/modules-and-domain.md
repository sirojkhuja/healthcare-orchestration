# Modules and Domain Boundaries

## Bounded Contexts

The system is split into independently understandable modules. Each module owns its domain objects, application handlers, public contracts, and infrastructure adapters.

1. Identity and Access
2. Tenant and Clinic Management
3. Patient
4. Provider
5. Scheduling
6. Treatment
7. Lab
8. Pharmacy
9. Billing
10. Insurance
11. Notifications
12. Integrations Hub
13. Audit and Compliance
14. Observability and Operations
15. Shared Kernel

## Cross-Cutting Invariants

- Every business record is tenant-aware unless explicitly global reference data.
- Every module emits audit records for regulated changes.
- Every externally visible identifier uses UUIDs.
- Every command and query resolves through the application layer.
- No module may reach into another module's infrastructure internals.

## Core Aggregates

### Organizational

- `Tenant`
- `Clinic`
- `Department`
- `Room`
- `User`
- `Role`
- `Permission`

### Clinical

- `Patient`
- `PatientContact`
- `Provider`
- `ProviderAvailabilityRule`
- `Appointment`
- `TreatmentPlan`
- `Encounter`
- `LabOrder`
- `LabResult`
- `Prescription`
- `Medication`

### Financial and Compliance

- `Invoice`
- `InvoiceItem`
- `Payment`
- `PriceList`
- `InsuranceClaim`
- `Notification`
- `AuditEvent`
- `Consent`
- `DataAccessRequest`

## Value Objects

- `Money`
- `PhoneNumber`
- `EmailAddress`
- `NationalId`
- `AppointmentSlot`
- `Address`
- `ExternalReference`
- `CorrelationId`
- `TenantId`
- `Actor`
- `WebhookSignature`

## Module Responsibilities

### Identity and Access

- authentication, MFA, sessions, API keys, RBAC, profile security, and security events

### Tenant and Clinic Management

- tenant lifecycle, limits, settings, clinics, departments, rooms, holidays, and location references
- clinic settings define local schedule defaults, appointment cadence, confirmation behavior, and telemedicine enablement
- clinic weekly work hours and holiday ranges are the source of truth for future provider and scheduling constraints
- location references are global approved data, while clinics, departments, rooms, work hours, and holidays remain tenant-owned

### Patient

- patient master records, contacts, documents, consent references, and patient summaries

### Provider

- provider master records, credentials, specialties, work hours, time off, and grouping

### Scheduling

- calendars, availability rules, waitlists, appointments, reminders, and scheduling cache rebuilds

### Treatment

- treatment plans, treatment items, encounters, diagnoses, procedures, and visit exports

### Lab

- lab orders, specimen progress, lab tests, result storage, and reconciliation

### Pharmacy

- prescriptions, medications, allergies, and patient medication views

### Billing

- billable services, price lists, invoices, payments, reconciliation, and refunds

### Insurance

- payers, claims, claim attachments, adjudication workflow, and insurance rules

### Notifications

- templates, channel dispatch, retry logic, channel testing, and provider priority rules

### Integrations Hub

- credential storage, token refresh, webhook registration, diagnostics, and provider-specific adapters

### Audit and Compliance

- immutable audit events, retention, PII policy, key rotation, and data access requests

### Observability and Operations

- health endpoints, metrics, runtime controls, admin jobs, outbox operations, and lag monitoring

### Shared Kernel

- request context, tenant context, correlation IDs, common DTOs, domain primitives, and policy abstractions

## Inter-Module Rules

- Cross-module reads should prefer application contracts, read models, or shared query services.
- Cross-module writes must happen through explicit application commands.
- Shared Kernel may provide generic primitives but must not absorb domain logic from feature modules.
- Event-driven integration between modules is preferred over direct coupling when workflows are asynchronous.
