import { QueryClient } from '@tanstack/react-query';
import { ApiError } from '@/types/api/errors';

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000, // 30 seconds default
      retry: (failureCount, error) => {
        // Don't retry on 4xx client errors
        if (error instanceof ApiError && error.status !== undefined && error.status < 500) {
          return false;
        }
        return failureCount < 2;
      },
      refetchOnWindowFocus: true,
    },
    mutations: {
      retry: false,
    },
  },
});

// Per-entity stale time constants
export const STALE = {
  REFERENCE: 10 * 60_000,       // 10 min — medications, specialties, locations
  REFERENCE_DATA: 10 * 60_000,  // alias kept for compatibility
  USER_PROFILE: 5 * 60_000,     // 5 min
  DETAIL: 30_000,               // 30 sec — single entity detail views
  LIST: 30_000,                 // 30 sec — patient/provider lists
  APPOINTMENT: 10_000,          // 10 sec — frequent status changes
  REALTIME: 0,                  // always fresh — health probes, dashboard
} as const;
