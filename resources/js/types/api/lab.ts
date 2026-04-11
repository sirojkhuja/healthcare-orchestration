import type { UUID, ISO8601 } from '../common';

export type LabOrderStatus = 'draft' | 'submitted' | 'in_progress' | 'completed' | 'canceled';
export type ResultFlag = 'normal' | 'high' | 'low' | 'critical_high' | 'critical_low';

export interface LabOrder {
  id: UUID;
  tenant_id: UUID;
  patient_id: UUID;
  patient_name: string;
  provider_id: UUID;
  provider_name: string;
  encounter_id?: UUID;
  external_order_id?: string;
  status: LabOrderStatus;
  ordered_at: ISO8601;
  submitted_at?: ISO8601;
  completed_at?: ISO8601;
  tests: LabTest[];
  tests_count: number;
  results_received: boolean;
  notes?: string;
}

export interface LabTest {
  id: UUID;
  order_id: UUID;
  panel_name?: string;
  test_name: string;
  test_code?: string;
  status: 'pending' | 'in_progress' | 'completed' | 'canceled';
  result?: LabResult;
}

export interface LabResult {
  id: UUID;
  test_id: UUID;
  order_id: UUID;
  value: string;
  unit?: string;
  reference_range?: string;
  flag?: ResultFlag;
  received_at: ISO8601;
  notes?: string;
}

export interface LabOrderFilters {
  status?: LabOrderStatus;
  patient_id?: UUID;
  provider_id?: UUID;
  date_from?: ISO8601;
  date_to?: ISO8601;
  page?: number;
  per_page?: number;
}
