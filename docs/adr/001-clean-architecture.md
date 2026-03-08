# ADR 001: Clean Architecture

## Status

Accepted

## Date

2026-03-08

## Context

The platform spans many business domains, many integrations, and a large API surface. It also needs to remain safe for AI-assisted development, which increases the need for explicit boundaries and low-coupling design.

## Decision

Adopt modular clean architecture with four layers inside each module:

- Presentation
- Application
- Domain
- Infrastructure

Domain remains framework-agnostic. Application owns commands, queries, and contracts. Infrastructure implements the contracts. Presentation maps transport concerns only.

## Alternatives Considered

- Laravel-first monolith with service classes
- Eloquent-centric domain model
- Feature folders without explicit layers

## Consequences

- Stronger modularity and testability
- Higher initial implementation discipline
- More files and abstractions, offset by better long-term maintainability

## Migration Plan

- Bootstrap the shared module structure first
- Add architecture checks to CI
- Reject layer violations in review and automation
