import type { UUID, ISO8601, DateString, PhoneNumber } from '../common';

export interface Provider {
  id: UUID;
  tenant_id: UUID;
  first_name: string;
  last_name: string;
  full_name: string;
  email?: string;
  primary_phone?: PhoneNumber;
  avatar_url?: string;
  specialties: ProviderSpecialty[];
  clinic_id?: UUID;
  clinic_name?: string;
  department_id?: UUID;
  department_name?: string;
  is_active: boolean;
  created_at: ISO8601;
}

export interface ProviderSpecialty {
  id: UUID;
  name: string;
  code?: string;
}

export interface ProviderLicense {
  id: UUID;
  provider_id: UUID;
  license_type: string;
  license_number: string;
  issuing_authority: string;
  issued_at: DateString;
  expires_at?: DateString;
}

export interface ProviderFilters {
  q?: string;
  specialty_id?: UUID;
  clinic_id?: UUID;
  is_active?: boolean;
  page?: number;
  per_page?: number;
}
