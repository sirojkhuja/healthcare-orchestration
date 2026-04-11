import type { UUID, ISO8601, DateString, Money } from '../common';

export type ClaimStatus = 'draft' | 'submitted' | 'under_review' | 'approved' | 'denied' | 'paid';

export interface Claim {
  id: UUID;
  tenant_id: UUID;
  claim_number: string;
  patient_id: UUID;
  patient_name: string;
  payer_id: UUID;
  payer_name: string;
  encounter_id?: UUID;
  invoice_id?: UUID;
  service_date: DateString;
  submitted_at?: ISO8601;
  status: ClaimStatus;
  billed_amount: Money;
  allowed_amount?: Money;
  paid_amount?: Money;
  denial_reason?: string;
  denial_code?: string;
  attachments: ClaimAttachment[];
  lines: ClaimLine[];
  created_at: ISO8601;
  updated_at: ISO8601;
}

export interface ClaimLine {
  id: UUID;
  claim_id: UUID;
  service_code: string;
  service_description: string;
  quantity: number;
  billed_amount: Money;
  allowed_amount?: Money;
}

export interface ClaimAttachment {
  id: UUID;
  claim_id: UUID;
  name: string;
  mime_type: string;
  size_bytes: number;
  url: string;
  uploaded_at: ISO8601;
}

export interface Payer {
  id: UUID;
  tenant_id: UUID;
  name: string;
  payer_id_code?: string;
  is_active: boolean;
}

export interface ClaimFilters {
  status?: ClaimStatus;
  payer_id?: UUID;
  date_from?: ISO8601;
  date_to?: ISO8601;
  q?: string;
  page?: number;
  per_page?: number;
}

export interface ClaimTransitionPayload {
  reason?: string;
  denial_code?: string;
  allowed_amount?: Money;
  paid_amount?: Money;
}
