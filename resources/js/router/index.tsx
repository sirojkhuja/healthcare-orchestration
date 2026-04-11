import { createBrowserRouter, Navigate } from 'react-router';
import { lazy, Suspense } from 'react';
import { AuthGuard } from './guards/AuthGuard';
import { TenantGuard } from './guards/TenantGuard';
import { AppShell } from '@/components/layout/AppShell';
import { AuthShell } from '@/components/layout/AuthShell';
import { Spinner } from '@/components/ui/Spinner';

function PageLoader() {
  return (
    <div className="flex min-h-64 items-center justify-center">
      <Spinner size="lg" />
    </div>
  );
}

function lazy_page(factory: () => Promise<{ default: React.ComponentType<object> }>) {
  const Comp = lazy(factory);
  return (
    <Suspense fallback={<PageLoader />}>
      <Comp />
    </Suspense>
  );
}

// ─── Auth pages ───────────────────────────────────────────────────────────────
const LoginPage = () => lazy_page(() => import('@/features/auth/LoginPage'));
const MfaVerifyPage = () => lazy_page(() => import('@/features/auth/MfaVerifyPage'));
const ForgotPasswordPage = () => lazy_page(() => import('@/features/auth/ForgotPasswordPage'));
const ResetPasswordPage = () => lazy_page(() => import('@/features/auth/ResetPasswordPage'));
const SelectTenantPage = () => lazy_page(() => import('@/features/auth/SelectTenantPage'));

// ─── App pages ────────────────────────────────────────────────────────────────
const DashboardPage = () => lazy_page(() => import('@/features/dashboard/DashboardPage'));

// Patients
const PatientListPage = () => lazy_page(() => import('@/features/patients/PatientListPage'));
const PatientDetailPage = () => lazy_page(() => import('@/features/patients/PatientDetailPage'));
const PatientCreatePage = () => lazy_page(() => import('@/features/patients/PatientCreatePage'));

// Providers
const ProviderListPage = () => lazy_page(() => import('@/features/providers/ProviderListPage'));
const ProviderDetailPage = () => lazy_page(() => import('@/features/providers/ProviderDetailPage'));

// Scheduling
const AppointmentListPage = () => lazy_page(() => import('@/features/scheduling/AppointmentListPage'));
const AppointmentDetailPage = () => lazy_page(() => import('@/features/scheduling/AppointmentDetailPage'));
const AppointmentCreatePage = () => lazy_page(() => import('@/features/scheduling/AppointmentCreatePage'));
const WaitlistPage = () => lazy_page(() => import('@/features/scheduling/WaitlistPage'));

// Treatment / Clinical Records
const TreatmentPlanListPage = () => lazy_page(() => import('@/features/treatment/TreatmentPlanListPage'));
const EncounterListPage = () => lazy_page(() => import('@/features/treatment/EncounterListPage'));
const EncounterDetailPage = () => lazy_page(() => import('@/features/treatment/EncounterDetailPage'));
const LabOrderListPage = () => lazy_page(() => import('@/features/lab/LabOrderListPage'));
const LabOrderDetailPage = () => lazy_page(() => import('@/features/lab/LabOrderDetailPage'));
const PrescriptionListPage = () => lazy_page(() => import('@/features/pharmacy/PrescriptionListPage'));

// Billing
const InvoiceListPage = () => lazy_page(() => import('@/features/billing/InvoiceListPage'));
const InvoiceDetailPage = () => lazy_page(() => import('@/features/billing/InvoiceDetailPage'));
const PaymentListPage = () => lazy_page(() => import('@/features/billing/PaymentListPage'));
const PriceListPage = () => lazy_page(() => import('@/features/billing/PriceListPage'));

// Insurance
const ClaimListPage = () => lazy_page(() => import('@/features/insurance/ClaimListPage'));
const ClaimDetailPage = () => lazy_page(() => import('@/features/insurance/ClaimDetailPage'));

// Notifications
const NotificationListPage = () => lazy_page(() => import('@/features/notifications/NotificationListPage'));
const TemplateListPage = () => lazy_page(() => import('@/features/notifications/TemplateListPage'));

// Admin
const TenantSettingsPage = () => lazy_page(() => import('@/features/admin/TenantSettingsPage'));
const ClinicsPage = () => lazy_page(() => import('@/features/admin/ClinicsPage'));
const UsersPage = () => lazy_page(() => import('@/features/admin/UsersPage'));
const RolesPage = () => lazy_page(() => import('@/features/admin/RolesPage'));
const SystemHealthPage = () => lazy_page(() => import('@/features/admin/SystemHealthPage'));
const AuditLogPage = () => lazy_page(() => import('@/features/admin/AuditLogPage'));

// Profile
const ProfilePage = () => lazy_page(() => import('@/features/profile/ProfilePage'));
const SessionsPage = () => lazy_page(() => import('@/features/profile/SessionsPage'));
const ApiKeysPage = () => lazy_page(() => import('@/features/profile/ApiKeysPage'));

export const router = createBrowserRouter([
  // ─── Public / auth routes ─────────────────────────────────────────────────
  {
    element: <AuthShell />,
    children: [
      { path: '/login', element: <LoginPage /> },
      { path: '/mfa', element: <MfaVerifyPage /> },
      { path: '/forgot-password', element: <ForgotPasswordPage /> },
      { path: '/reset-password', element: <ResetPasswordPage /> },
    ],
  },

  // ─── Auth required, no tenant yet ─────────────────────────────────────────
  {
    element: <AuthGuard />,
    children: [
      { path: '/select-tenant', element: <SelectTenantPage /> },
    ],
  },

  // ─── Auth + tenant required ────────────────────────────────────────────────
  {
    element: <AuthGuard />,
    children: [
      {
        element: <TenantGuard />,
        children: [
          {
            element: <AppShell />,
            children: [
              { index: true, element: <Navigate to="/dashboard" replace /> },
              { path: '/dashboard', element: <DashboardPage /> },

              // Patients
              { path: '/patients', element: <PatientListPage /> },
              { path: '/patients/new', element: <PatientCreatePage /> },
              { path: '/patients/:patientId', element: <PatientDetailPage /> },

              // Providers
              { path: '/providers', element: <ProviderListPage /> },
              { path: '/providers/:providerId', element: <ProviderDetailPage /> },

              // Scheduling
              { path: '/appointments', element: <AppointmentListPage /> },
              { path: '/appointments/new', element: <AppointmentCreatePage /> },
              { path: '/appointments/:appointmentId', element: <AppointmentDetailPage /> },
              { path: '/waitlist', element: <WaitlistPage /> },

              // Clinical Records
              { path: '/treatment-plans', element: <TreatmentPlanListPage /> },
              { path: '/encounters', element: <EncounterListPage /> },
              { path: '/encounters/:encounterId', element: <EncounterDetailPage /> },
              { path: '/lab-orders', element: <LabOrderListPage /> },
              { path: '/lab-orders/:orderId', element: <LabOrderDetailPage /> },
              { path: '/prescriptions', element: <PrescriptionListPage /> },

              // Billing
              { path: '/billing/invoices', element: <InvoiceListPage /> },
              { path: '/billing/invoices/:invoiceId', element: <InvoiceDetailPage /> },
              { path: '/billing/payments', element: <PaymentListPage /> },
              { path: '/billing/price-lists', element: <PriceListPage /> },

              // Insurance
              { path: '/insurance/claims', element: <ClaimListPage /> },
              { path: '/insurance/claims/:claimId', element: <ClaimDetailPage /> },

              // Notifications
              { path: '/admin/notifications', element: <NotificationListPage /> },
              { path: '/admin/notifications/templates', element: <TemplateListPage /> },

              // Admin
              { path: '/admin/tenant', element: <TenantSettingsPage /> },
              { path: '/admin/clinics', element: <ClinicsPage /> },
              { path: '/admin/users', element: <UsersPage /> },
              { path: '/admin/roles', element: <RolesPage /> },
              { path: '/admin/system', element: <SystemHealthPage /> },
              { path: '/admin/audit', element: <AuditLogPage /> },

              // Profile
              { path: '/profile', element: <ProfilePage /> },
              { path: '/profile/sessions', element: <SessionsPage /> },
              { path: '/profile/api-keys', element: <ApiKeysPage /> },
            ],
          },
        ],
      },
    ],
  },

  // ─── Fallback ─────────────────────────────────────────────────────────────
  { path: '*', element: <Navigate to="/" replace /> },
]);
