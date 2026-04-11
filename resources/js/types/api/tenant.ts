import type { UUID, ISO8601, Address } from '../common';

export interface Tenant {
  id: UUID;
  name: string;
  logo_url?: string;
  address?: Address;
  contact_email?: string;
  contact_phone?: string;
  locale: string;
  timezone: string;
  is_active: boolean;
  created_at: ISO8601;
}

export interface Clinic {
  id: UUID;
  tenant_id: UUID;
  name: string;
  address?: Address;
  contact_phone?: string;
  contact_email?: string;
  departments: Department[];
  rooms_count: number;
  is_active: boolean;
}

export interface Department {
  id: UUID;
  clinic_id: UUID;
  name: string;
  floor?: string;
  description?: string;
}

export interface Room {
  id: UUID;
  clinic_id: UUID;
  department_id?: UUID;
  name: string;
  type?: string;
  capacity?: number;
  equipment?: string[];
  is_active: boolean;
}

export interface WorkHours {
  day_of_week: number;
  open_time?: string;
  close_time?: string;
  is_closed: boolean;
}

export interface TenantSettings {
  max_appointments_per_day?: number;
  max_active_providers?: number;
  appointment_booking_window_days?: number;
  cancellation_policy_hours?: number;
}

export interface TenantUser {
  id: UUID;
  user_id: UUID;
  name: string;
  email: string;
  avatar_url?: string;
  roles: string[];
  status: 'active' | 'inactive' | 'locked';
  last_login_at?: ISO8601;
  joined_at: ISO8601;
}

export interface Role {
  id: UUID;
  tenant_id: UUID;
  name: string;
  description?: string;
  permissions: string[];
  users_count: number;
  is_system_role: boolean;
  created_at: ISO8601;
}
