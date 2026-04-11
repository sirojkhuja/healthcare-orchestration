/**
 * Generate a fresh idempotency key for a single user-initiated action.
 * Must NOT be reused — call this once per button click / form submission.
 */
export function generateIdempotencyKey(): string {
  return crypto.randomUUID();
}
