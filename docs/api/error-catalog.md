# Error Catalog

## Standard Error Shape

```json
{
  "code": "APPOINTMENT_INVALID_TRANSITION",
  "message": "The appointment cannot be completed from the current state.",
  "details": {
    "current_state": "confirmed",
    "required_state": "in_progress"
  },
  "trace_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "correlation_id": "3fdbf53f-5234-4b55-8a0e-d7d8c4f0cfb0"
}
```

Until full distributed tracing is implemented, `trace_id` uses the resolved request identifier.

## Global Error Codes

| Code | HTTP | Meaning |
| --- | --- | --- |
| `VALIDATION_FAILED` | `422` | Request payload is invalid. |
| `UNAUTHENTICATED` | `401` | Missing or invalid credentials. |
| `FORBIDDEN` | `403` | Authenticated actor lacks permission. |
| `RESOURCE_NOT_FOUND` | `404` | The requested resource does not exist in the current tenant scope. |
| `TENANT_CONTEXT_REQUIRED` | `400` | Tenant context was missing. |
| `TENANT_SCOPE_VIOLATION` | `403` | Request attempted cross-tenant access. |
| `CONFLICT` | `409` | Request conflicts with current system state. |
| `IDEMPOTENCY_REPLAY` | `409` | Duplicate idempotent request received or same key reused with a conflicting payload. |
| `RATE_LIMITED` | `429` | Rate limit exceeded. |
| `INTERNAL_ERROR` | `500` | Unexpected server-side failure. |

## Workflow Errors

| Code | HTTP | Meaning |
| --- | --- | --- |
| `APPOINTMENT_INVALID_TRANSITION` | `409` | Appointment transition is not valid from the current state. |
| `CLAIM_INVALID_TRANSITION` | `409` | Claim transition is not valid from the current state. |
| `PAYMENT_INVALID_TRANSITION` | `409` | Payment transition is not valid from the current state. |
| `PAST_APPOINTMENT_CONFIRMATION_FORBIDDEN` | `422` | Appointment cannot be confirmed in the past. |
| `CHECK_IN_REQUIRES_CONFIRMATION` | `422` | Check-in requires prior confirmation or documented override. |

## Integration Errors

| Code | HTTP | Meaning |
| --- | --- | --- |
| `INTEGRATION_NOT_ENABLED` | `409` | Integration is disabled for the tenant. |
| `INTEGRATION_AUTH_FAILED` | `502` | Provider authentication failed. |
| `INTEGRATION_TIMEOUT` | `504` | Provider request timed out. |
| `INTEGRATION_UNAVAILABLE` | `503` | Provider is unavailable or circuit is open. |
| `WEBHOOK_SIGNATURE_INVALID` | `401` | Inbound webhook signature failed verification. |
| `WEBHOOK_DUPLICATE` | `409` | Duplicate webhook delivery detected. |

## Billing and Claims Errors

| Code | HTTP | Meaning |
| --- | --- | --- |
| `PAYMENT_PROVIDER_REJECTED` | `422` | Provider rejected the payment request. |
| `REFUND_NOT_SUPPORTED` | `422` | Selected provider does not support refunds for this payment. |
| `RECONCILIATION_MISMATCH` | `409` | Local and provider payment states diverged. |
| `CLAIM_ATTACHMENT_REQUIRED` | `422` | Claim submission requires supporting attachments. |
| `CLAIM_RULE_VIOLATION` | `422` | Claim violates configured insurance rules. |

## Security and Compliance Errors

| Code | HTTP | Meaning |
| --- | --- | --- |
| `MFA_REQUIRED` | `401` | Actor must complete MFA. `details` must include `challenge_id` and `expires_at`. |
| `API_KEY_REVOKED` | `401` | API key is no longer valid. |
| `CONSENT_REQUIRED` | `403` | Action requires active consent. |
| `DATA_ACCESS_REQUEST_NOT_APPROVED` | `403` | Requested compliance operation lacks approval. |

## Error Documentation Rule

Any new business-specific error code must be added here and reflected in OpenAPI examples.
