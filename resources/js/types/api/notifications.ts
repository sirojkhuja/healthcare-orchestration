import type { UUID, ISO8601 } from '../common';

export type NotificationChannel = 'email' | 'sms' | 'telegram';
export type NotificationStatus = 'queued' | 'sent' | 'failed';

export interface Notification {
  id: UUID;
  tenant_id: UUID;
  recipient_id?: UUID;
  recipient_name?: string;
  recipient_contact: string;
  channel: NotificationChannel;
  template_id?: UUID;
  template_name?: string;
  subject?: string;
  body: string;
  status: NotificationStatus;
  scheduled_at?: ISO8601;
  sent_at?: ISO8601;
  error_message?: string;
  created_at: ISO8601;
}

export interface NotificationTemplate {
  id: UUID;
  tenant_id: UUID;
  name: string;
  channel: NotificationChannel;
  event_trigger: string;
  subject?: string;
  body: string;
  variables: string[];
  is_active: boolean;
  created_at: ISO8601;
  updated_at: ISO8601;
}
