import type { UUID, ISO8601 } from '../common';

export type TreatmentPlanStatus = 'draft' | 'approved' | 'in_progress' | 'completed' | 'canceled';
export type EncounterStatus = 'open' | 'completed' | 'canceled';

export interface TreatmentPlan {
  id: UUID;
  tenant_id: UUID;
  patient_id: UUID;
  patient_name: string;
  provider_id: UUID;
  provider_name: string;
  title: string;
  description?: string;
  status: TreatmentPlanStatus;
  approved_at?: ISO8601;
  approved_by?: UUID;
  items_count: number;
  created_at: ISO8601;
  updated_at: ISO8601;
}

export interface TreatmentPlanItem {
  id: UUID;
  plan_id: UUID;
  title: string;
  description?: string;
  goal?: string;
  status: 'pending' | 'in_progress' | 'completed' | 'skipped';
  due_date?: ISO8601;
  completed_at?: ISO8601;
  sort_order: number;
}

export interface Encounter {
  id: UUID;
  tenant_id: UUID;
  patient_id: UUID;
  patient_name: string;
  provider_id: UUID;
  provider_name: string;
  appointment_id?: UUID;
  encounter_type: string;
  chief_complaint?: string;
  notes?: string;
  status: EncounterStatus;
  occurred_at: ISO8601;
  diagnoses_count: number;
  procedures_count: number;
  created_at: ISO8601;
}

export interface Diagnosis {
  id: UUID;
  encounter_id: UUID;
  icd_code: string;
  description: string;
  type: 'primary' | 'secondary' | 'differential';
  severity?: 'mild' | 'moderate' | 'severe';
  notes?: string;
}

export interface Procedure {
  id: UUID;
  encounter_id: UUID;
  cpt_code: string;
  description: string;
  quantity: number;
  status: 'planned' | 'performed' | 'canceled';
  performed_at?: ISO8601;
  notes?: string;
}
