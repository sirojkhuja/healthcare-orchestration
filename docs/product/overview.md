# Product Overview

## Product Name

- Codename: `MedFlow`
- Product class: enterprise-grade healthcare workflow and integration platform
- Architecture style: multi-tenant, event-driven, API-first Laravel platform

## Product Goal

MedFlow centralizes clinical, administrative, financial, and integration workflows for healthcare organizations. It is designed for clinic networks and enterprise healthcare operators that need strict tenant isolation, workflow traceability, external provider integrations, and a large documented API surface.

## Core Capabilities

- Manage tenants, clinics, departments, rooms, users, and permissions.
- Manage patients, providers, appointments, treatment plans, lab orders, prescriptions, billing, and claims.
- Orchestrate domain workflows through explicit state machines.
- Publish domain events through Kafka with an outbox relay.
- Integrate with Uzbekistan payment, messaging, and identity providers.
- Provide complete API documentation and development documentation.
- Support AI-agent-driven development with strict patterns and repository workflow controls.

## Primary Users

- `SuperAdmin`: manages the overall platform.
- `TenantAdmin`: manages a tenant account, settings, and high-level operations.
- `ClinicAdmin`: manages a clinic, staff, schedules, and local settings.
- `Doctor`: provides care, creates plans, and interacts with appointments and prescriptions.
- `Nurse`: supports clinical workflows, appointments, and encounters.
- `Receptionist`: manages patient intake and scheduling.
- `LabTech`: manages lab order progress and results.
- `BillingAgent`: manages invoices, payments, and claim workflows.
- `Patient`: consumes selected self-service and notification capabilities.

## Product Constraints

- Every regulated data object must be auditable.
- Every tenant-facing query must be tenant-scoped.
- All workflow transitions must be explicit and policy-checked.
- Integrations must sit behind adapters and contracts.
- High-risk changes must be covered by tests, documentation, and observability.

## Non-Functional Objectives

- Clean architecture with strict module boundaries
- Small, readable files and handlers
- High API coverage with explicit schemas
- Strong security defaults
- Deterministic development workflow for Codex and humans
- Production-grade observability and operational tooling

## Success Conditions

The platform is ready for production when:

- all core modules are implemented,
- API and event contracts are documented,
- quality gates run cleanly,
- integrations are isolated and testable,
- operational dashboards and alerts exist,
- the task list reaches 100 percent with no open blockers.
