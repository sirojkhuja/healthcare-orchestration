import { create } from 'zustand';

export interface Toast {
  id: string;
  type: 'success' | 'error' | 'warning' | 'info';
  title: string;
  message?: string;
  correlationId?: string;
  duration?: number;
}

interface UiState {
  sidebarOpen: boolean;
  toasts: Toast[];
  lastCorrelationId: string | null;
}

interface UiActions {
  toggleSidebar: () => void;
  setSidebarOpen: (open: boolean) => void;
  addToast: (toast: Omit<Toast, 'id'>) => void;
  removeToast: (id: string) => void;
  setLastCorrelationId: (id: string) => void;
}

export const useUiStore = create<UiState & UiActions>((set) => ({
  sidebarOpen: true,
  toasts: [],
  lastCorrelationId: null,

  toggleSidebar: () => set((s) => ({ sidebarOpen: !s.sidebarOpen })),

  setSidebarOpen: (open) => set({ sidebarOpen: open }),

  addToast: (toast) => {
    const id = crypto.randomUUID();
    set((s) => ({ toasts: [...s.toasts, { ...toast, id }] }));
    const duration = toast.duration ?? (toast.type === 'error' ? 8000 : 4000);
    if (duration > 0) {
      setTimeout(() => {
        set((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) }));
      }, duration);
    }
  },

  removeToast: (id) => set((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) })),

  setLastCorrelationId: (id) => set({ lastCorrelationId: id }),
}));
