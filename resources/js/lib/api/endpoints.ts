const BASE = '/api/v1';

export const endpoints = {
  // Auth
  auth: {
    login: `${BASE}/auth/login`,
    refresh: `${BASE}/auth/refresh`,
    logout: `${BASE}/auth/logout`,
    me: `${BASE}/auth/me`,
    mfa: {
      setup: `${BASE}/auth/mfa/setup`,
      verify: `${BASE}/auth/mfa/verify`,
      disable: `${BASE}/auth/mfa/disable`,
    },
    password: {
      forgot: `${BASE}/auth/password/forgot`,
      reset: `${BASE}/auth/password/reset`,
    },
    sessions: `${BASE}/auth/sessions`,
    session: (id: string) => `${BASE}/auth/sessions/${id}`,
    apiKeys: `${BASE}/auth/api-keys`,
    apiKey: (id: string) => `${BASE}/auth/api-keys/${id}`,
  },

  // Tenants
  tenants: `${BASE}/tenants`,
  tenant: (id: string) => `${BASE}/tenants/${id}`,
  tenantSettings: (id: string) => `${BASE}/tenants/${id}/settings`,

  // Clinics
  clinics: `${BASE}/clinics`,
  clinic: (id: string) => `${BASE}/clinics/${id}`,
  clinicDepartments: (id: string) => `${BASE}/clinics/${id}/departments`,
  clinicRooms: (id: string) => `${BASE}/clinics/${id}/rooms`,

  // Users
  users: `${BASE}/users`,
  user: (id: string) => `${BASE}/users/${id}`,

  // Roles
  roles: `${BASE}/roles`,
  role: (id: string) => `${BASE}/roles/${id}`,
  rolePermissions: (id: string) => `${BASE}/roles/${id}/permissions`,

  // Patients
  patients: `${BASE}/patients`,
  patient: (id: string) => `${BASE}/patients/${id}`,
  patientSearch: `${BASE}/patients/search`,
  patientExport: `${BASE}/patients/export`,
  patientSummary: (id: string) => `${BASE}/patients/${id}/summary`,
  patientContacts: (id: string) => `${BASE}/patients/${id}/contacts`,
  patientContact: (patientId: string, contactId: string) => `${BASE}/patients/${patientId}/contacts/${contactId}`,
  patientDocuments: (id: string) => `${BASE}/patients/${id}/documents`,
  patientConsents: (id: string) => `${BASE}/patients/${id}/consents`,
  patientAllergies: (id: string) => `${BASE}/patients/${id}/allergies`,

  // Providers
  providers: `${BASE}/providers`,
  provider: (id: string) => `${BASE}/providers/${id}`,
  providerSchedule: (id: string) => `${BASE}/providers/${id}/schedule`,
  providerAvailabilityRules: (id: string) => `${BASE}/providers/${id}/availability/rules`,
  providerAvailabilitySlots: (id: string) => `${BASE}/providers/${id}/availability/slots`,

  // Appointments
  appointments: `${BASE}/appointments`,
  appointment: (id: string) => `${BASE}/appointments/${id}`,
  appointmentSearch: `${BASE}/appointments/search`,
  appointmentExport: `${BASE}/appointments/export`,
  appointmentAction: (id: string, action: string) => `${BASE}/appointments/${id}:${action}`,
  appointmentParticipants: (id: string) => `${BASE}/appointments/${id}/participants`,
  appointmentNotes: (id: string) => `${BASE}/appointments/${id}/notes`,
  appointmentAudit: (id: string) => `${BASE}/appointments/${id}/audit`,

  // Waitlist
  waitlist: `${BASE}/waitlist`,
  waitlistEntry: (id: string) => `${BASE}/waitlist/${id}`,
  waitlistOfferSlot: (id: string) => `${BASE}/waitlist/${id}:offer-slot`,

  // Treatment
  treatmentPlans: `${BASE}/treatment-plans`,
  treatmentPlan: (id: string) => `${BASE}/treatment-plans/${id}`,
  treatmentPlanItems: (id: string) => `${BASE}/treatment-plans/${id}/items`,

  // Encounters
  encounters: `${BASE}/encounters`,
  encounter: (id: string) => `${BASE}/encounters/${id}`,
  encounterDiagnoses: (id: string) => `${BASE}/encounters/${id}/diagnoses`,
  encounterProcedures: (id: string) => `${BASE}/encounters/${id}/procedures`,

  // Lab
  labOrders: `${BASE}/lab-orders`,
  labOrder: (id: string) => `${BASE}/lab-orders/${id}`,
  labOrderResults: (id: string) => `${BASE}/lab-orders/${id}/results`,
  labOrderAction: (id: string, action: string) => `${BASE}/lab-orders/${id}:${action}`,

  // Pharmacy
  medications: `${BASE}/medications`,
  prescriptions: `${BASE}/prescriptions`,
  prescription: (id: string) => `${BASE}/prescriptions/${id}`,

  // Billing
  invoices: `${BASE}/invoices`,
  invoice: (id: string) => `${BASE}/invoices/${id}`,
  invoiceAction: (id: string, action: string) => `${BASE}/invoices/${id}:${action}`,
  payments: `${BASE}/payments`,
  payment: (id: string) => `${BASE}/payments/${id}`,
  paymentsReconcile: `${BASE}/payments:reconcile`,
  billableServices: `${BASE}/billable-services`,
  billableService: (id: string) => `${BASE}/billable-services/${id}`,

  // Insurance
  claims: `${BASE}/claims`,
  claim: (id: string) => `${BASE}/claims/${id}`,
  claimAction: (id: string, action: string) => `${BASE}/claims/${id}:${action}`,
  claimAttachments: (id: string) => `${BASE}/claims/${id}/attachments`,
  payers: `${BASE}/payers`,

  // Notifications
  notifications: `${BASE}/notifications`,
  notificationTemplates: `${BASE}/notification-templates`,
  notificationTemplate: (id: string) => `${BASE}/notification-templates/${id}`,

  // Audit
  auditEvents: `${BASE}/audit/events`,
  auditEvent: (id: string) => `${BASE}/audit/events/${id}`,
  auditExport: `${BASE}/audit/export`,
  auditObject: (type: string, id: string) => `${BASE}/audit/object/${type}/${id}`,

  // Admin / Ops
  adminJobs: `${BASE}/admin/jobs`,
  adminJobAction: (id: string, action: string) => `${BASE}/admin/jobs/${id}:${action}`,
  adminCache: (action: string) => `${BASE}/admin/cache:${action}`,
  adminOutbox: (action: string) => `${BASE}/admin/outbox:${action}`,
  adminOutboxMessage: (id: string, action: string) => `${BASE}/admin/outbox/${id}:${action}`,
  adminKafka: (action: string) => `${BASE}/admin/kafka:${action}`,
  adminFeatureFlags: `${BASE}/admin/feature-flags`,
  adminFeatureFlag: (key: string) => `${BASE}/admin/feature-flags/${key}`,
  adminRateLimits: `${BASE}/admin/rate-limits`,

  // Observability
  health: `${BASE}/health`,
  ready: `${BASE}/ready`,
  live: `${BASE}/live`,
  metrics: `${BASE}/metrics`,
} as const;
