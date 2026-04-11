import type { UUID, ISO8601, DateString, Address, PhoneNumber, Money } from '../common';

export interface Patient {
  id: UUID;
  tenant_id: UUID;
  first_name: string;
  last_name: string;
  full_name: string;
  date_of_birth: DateString;
  gender?: string;
  national_id?: string;
  locale: string;
  timezone: string;
  avatar_url?: string;
  is_active: boolean;
  tags: string[];
  primary_phone?: PhoneNumber;
  email?: string;
  address?: Address;
  created_at: ISO8601;
  updated_at: ISO8601;
}

export interface PatientSummary extends Patient {
  age: number;
  last_appointment_at?: ISO8601;
  upcoming_appointment_count: number;
  active_prescription_count: number;
  allergy_count: number;
  open_claim_count: number;
}

export interface PatientContact {
  id: UUID;
  patient_id: UUID;
  name: string;
  relationship: string;
  phone?: PhoneNumber;
  email?: string;
  is_emergency_contact: boolean;
}

export interface PatientDocument {
  id: UUID;
  patient_id: UUID;
  name: string;
  type: string;
  mime_type: string;
  size_bytes: number;
  url: string;
  uploaded_by: UUID;
  created_at: ISO8601;
}

export interface PatientConsent {
  id: UUID;
  patient_id: UUID;
  type: string;
  signed_at: ISO8601;
  expires_at?: ISO8601;
  notes?: string;
}

export interface PatientInsuranceMembership {
  id: UUID;
  patient_id: UUID;
  payer_name: string;
  plan_name: string;
  member_id: string;
  group_id?: string;
  effective_from: DateString;
  effective_to?: DateString;
  is_active: boolean;
}

export interface CreatePatientPayload {
  first_name: string;
  last_name: string;
  date_of_birth: DateString;
  gender?: string;
  national_id?: string;
  locale?: string;
  timezone?: string;
  primary_phone?: PhoneNumber;
  email?: string;
  address?: Address;
  tags?: string[];
}

export interface PatientFilters {
  q?: string;
  birth_date_from?: DateString;
  birth_date_to?: DateString;
  tags?: string[];
  is_active?: boolean;
  page?: number;
  per_page?: number;
}

export interface PatientTimelineEvent {
  id: UUID;
  type: 'appointment' | 'encounter' | 'lab_order' | 'prescription' | 'document' | 'consent';
  title: string;
  description?: string;
  occurred_at: ISO8601;
  actor?: string;
  metadata?: Record<string, unknown>;
}

export interface PatientAllergy {
  id: UUID;
  patient_id: UUID;
  allergen: string;
  severity: 'mild' | 'moderate' | 'severe';
  reaction?: string;
  notes?: string;
}

export interface PatientInvoiceSummary {
  total_invoices: number;
  paid_amount: Money;
  outstanding_amount: Money;
}
