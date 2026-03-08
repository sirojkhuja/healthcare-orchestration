# Request Context

This document defines the request metadata contract for HTTP, jobs, and emitted messages.

## Headers

- `X-Request-Id`: unique identifier for the current HTTP request or worker execution.
- `X-Correlation-Id`: stable identifier for the full business flow across requests, jobs, and emitted messages.
- `X-Causation-Id`: identifier for the immediate upstream request, job, or message that caused the current work.

## HTTP Rules

- Every HTTP request gets request metadata, even when the client does not send any headers.
- Valid inbound UUID headers are preserved and normalized to lowercase.
- Missing or invalid inbound metadata is replaced with generated UUID values.
- When the client does not send correlation or causation headers, they default to the resolved request identifier.
- Responses echo the resolved request, correlation, and causation headers.

## Queue and Job Rules

- Request metadata must propagate into queued job payloads automatically.
- Workers must hydrate the same metadata before job handling begins.
- Tenant context must remain available in queued jobs when the original request had tenant scope.
- Job logs must include the hydrated request metadata.

## Event and Message Rules

- Emitted messages must carry `correlation_id` and `causation_id`.
- Event helpers must source metadata from the active request context instead of generating unrelated identifiers.
- The current request identifier is the default causation value for emitted downstream messages unless a more specific causation identifier is provided.

## Logging and Observability

- Structured logs must include request, correlation, and causation identifiers.
- Tenant identifiers may be included when safe.
- Future tracing integrations must reuse these identifiers instead of inventing parallel request lineage fields.

## Testing Requirements

- Feature tests must cover generated and preserved request metadata headers.
- Integration tests must prove metadata survives queue serialization and worker execution.
- Unit tests must cover event context helper behavior.
