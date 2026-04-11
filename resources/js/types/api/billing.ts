import type { UUID, ISO8601, DateString, Money } from '../common';

export type InvoiceStatus = 'draft' | 'issued' | 'partially_paid' | 'paid' | 'canceled';
export type PaymentStatus = 'initiated' | 'pending' | 'captured' | 'failed' | 'canceled' | 'refunded';
export type PaymentProvider = 'payme' | 'click' | 'uzum' | 'cash' | 'bank_transfer';

export interface Invoice {
  id: UUID;
  tenant_id: UUID;
  invoice_number: string;
  patient_id: UUID;
  patient_name: string;
  provider_id?: UUID;
  encounter_id?: UUID;
  status: InvoiceStatus;
  issued_at?: ISO8601;
  due_date?: DateString;
  subtotal: Money;
  discount_amount: Money;
  tax_amount: Money;
  total: Money;
  paid_amount: Money;
  balance_due: Money;
  items: InvoiceItem[];
  created_at: ISO8601;
  updated_at: ISO8601;
}

export interface InvoiceItem {
  id: UUID;
  invoice_id: UUID;
  service_name: string;
  service_code?: string;
  quantity: number;
  unit_price: Money;
  discount_amount: Money;
  total: Money;
}

export interface Payment {
  id: UUID;
  tenant_id: UUID;
  invoice_id: UUID;
  invoice_number: string;
  patient_id: UUID;
  patient_name: string;
  provider: PaymentProvider;
  external_reference?: string;
  amount: Money;
  status: PaymentStatus;
  initiated_at: ISO8601;
  captured_at?: ISO8601;
  failed_at?: ISO8601;
  refunded_at?: ISO8601;
  failure_reason?: string;
}

export interface BillableService {
  id: UUID;
  tenant_id: UUID;
  name: string;
  code?: string;
  category?: string;
  base_price: Money;
  effective_from: DateString;
  effective_to?: DateString;
  is_active: boolean;
}

export interface CreateInvoicePayload {
  patient_id: UUID;
  provider_id?: UUID;
  encounter_id?: UUID;
  due_date?: DateString;
  items: {
    service_id?: UUID;
    service_name: string;
    quantity: number;
    unit_price: Money;
    discount_amount?: Money;
  }[];
}

export interface InitiatePaymentPayload {
  invoice_id: UUID;
  amount: Money;
  provider: PaymentProvider;
}

export interface InvoiceFilters {
  status?: InvoiceStatus;
  patient_id?: UUID;
  date_from?: ISO8601;
  date_to?: ISO8601;
  q?: string;
  page?: number;
  per_page?: number;
}

export interface PaymentFilters {
  status?: PaymentStatus;
  provider?: PaymentProvider;
  date_from?: ISO8601;
  date_to?: ISO8601;
  page?: number;
  per_page?: number;
}
