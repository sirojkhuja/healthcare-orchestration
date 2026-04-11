import type { UUID, ISO8601, Money } from '../common';

export type AppointmentStatus =
  | 'draft'
  | 'scheduled'
  | 'confirmed'
  | 'checked_in'
  | 'in_progress'
  | 'completed'
  | 'canceled'
  | 'no_show'
  | 'rescheduled';

export type AppointmentType = 'in_person' | 'telehealth' | 'phone';

export interface Appointment {
  id: UUID;
  tenant_id: UUID;
  patient_id: UUID;
  patient_name: string;
  provider_id: UUID;
  provider_name: string;
  clinic_id: UUID;
  clinic_name: string;
  room_id?: UUID;
  room_name?: string;
  scheduled_start_at: ISO8601;
  scheduled_end_at: ISO8601;
  timezone: string;
  status: AppointmentStatus;
  appointment_type: AppointmentType;
  service_reason?: string;
  notes?: string;
  duration_minutes: number;
  created_at: ISO8601;
  updated_at: ISO8601;
}

export interface AppointmentParticipant {
  id: UUID;
  appointment_id: UUID;
  user_id: UUID;
  name: string;
  role: string;
}

export interface AppointmentNote {
  id: UUID;
  appointment_id: UUID;
  content: string;
  author_id: UUID;
  author_name: string;
  created_at: ISO8601;
}

export interface AvailabilitySlot {
  start_at: ISO8601;
  end_at: ISO8601;
  timezone: string;
  provider_id: UUID;
  is_available: boolean;
}

export interface AvailabilityRule {
  id: UUID;
  provider_id: UUID;
  day_of_week: number; // 0=Sunday, 6=Saturday
  start_time: string; // HH:mm
  end_time: string; // HH:mm
  is_active: boolean;
}

export interface WaitlistEntry {
  id: UUID;
  tenant_id: UUID;
  patient_id: UUID;
  patient_name: string;
  provider_id?: UUID;
  provider_name?: string;
  preferred_date_from?: ISO8601;
  preferred_date_to?: ISO8601;
  notes?: string;
  days_waiting: number;
  created_at: ISO8601;
}

export interface CreateAppointmentPayload {
  patient_id: UUID;
  provider_id: UUID;
  clinic_id: UUID;
  room_id?: UUID;
  scheduled_start_at: ISO8601;
  scheduled_end_at: ISO8601;
  timezone: string;
  appointment_type: AppointmentType;
  service_reason?: string;
  notes?: string;
  notify_patient?: boolean;
}

export interface AppointmentTransitionPayload {
  reason?: string;
  admin_override?: boolean;
  replacement_slot?: {
    start_at: ISO8601;
    end_at: ISO8601;
    timezone: string;
  };
}

export interface AppointmentFilters {
  status?: AppointmentStatus | AppointmentStatus[];
  provider_id?: UUID;
  clinic_id?: UUID;
  patient_id?: UUID;
  date_from?: ISO8601;
  date_to?: ISO8601;
  q?: string;
  page?: number;
  per_page?: number;
}

export const VALID_TRANSITIONS: Record<AppointmentStatus, AppointmentStatus[]> = {
  draft: ['scheduled'],
  scheduled: ['confirmed', 'canceled', 'no_show', 'rescheduled'],
  confirmed: ['checked_in', 'canceled', 'no_show', 'rescheduled'],
  checked_in: ['in_progress'],
  in_progress: ['completed'],
  completed: [],
  canceled: ['scheduled'], // restore
  no_show: ['scheduled'], // restore
  rescheduled: ['scheduled'], // restore
};
