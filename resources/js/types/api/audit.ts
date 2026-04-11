import type { UUID, ISO8601 } from '../common';

export interface AuditEvent {
  id: UUID;
  tenant_id: UUID;
  object_type: string;
  object_id: UUID;
  action: 'created' | 'updated' | 'deleted' | 'viewed' | 'exported' | 'transition';
  actor_id?: UUID;
  actor_name?: string;
  actor_type: 'user' | 'system' | 'api_key';
  request_id?: UUID;
  correlation_id?: UUID;
  before_data?: Record<string, unknown>;
  after_data?: Record<string, unknown>;
  metadata?: Record<string, unknown>;
  created_at: ISO8601;
}

export interface AuditFilters {
  object_type?: string;
  object_id?: UUID;
  action?: string;
  actor_id?: UUID;
  date_from?: ISO8601;
  date_to?: ISO8601;
  page?: number;
  per_page?: number;
}
