export type UUID = string;
export type ISO8601 = string;
export type DateString = string; // YYYY-MM-DD

export interface Money {
  amount: number;
  currency: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
}

export interface ApiErrorResponse {
  code: string;
  message: string;
  details?: Record<string, string[]>;
  traceId?: string;
  correlationId?: string;
}

export interface Address {
  street?: string;
  city?: string;
  state?: string;
  zip?: string;
  country?: string;
}

export interface PhoneNumber {
  number: string;
  country: string;
}
