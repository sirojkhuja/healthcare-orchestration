import { Badge, type BadgeVariant } from '@/components/ui/Badge';
import type { AppointmentStatus } from '@/types/api/appointments';
import type { ClaimStatus } from '@/types/api/insurance';
import type { InvoiceStatus, PaymentStatus } from '@/types/api/billing';
import type { LabOrderStatus } from '@/types/api/lab';

// Generic badge driven by a color map
interface StateMachineBadgeProps {
  status: string;
  colorMap: Record<string, BadgeVariant>;
  labelMap?: Record<string, string>;
  className?: string;
}

export function StateMachineBadge({ status, colorMap, labelMap, className }: StateMachineBadgeProps) {
  const variant = colorMap[status] ?? 'gray';
  const label = labelMap?.[status] ?? status.replace(/_/g, ' ');
  return (
    <Badge variant={variant} dot className={className}>
      {label}
    </Badge>
  );
}

// ─── Appointment ─────────────────────────────────────────────────────────────
const APPOINTMENT_COLOR_MAP: Record<AppointmentStatus, BadgeVariant> = {
  draft: 'gray',
  scheduled: 'blue',
  confirmed: 'indigo',
  checked_in: 'yellow',
  in_progress: 'orange',
  completed: 'green',
  canceled: 'red',
  no_show: 'red',
  rescheduled: 'purple',
};

const APPOINTMENT_LABEL_MAP: Record<AppointmentStatus, string> = {
  draft: 'Draft',
  scheduled: 'Scheduled',
  confirmed: 'Confirmed',
  checked_in: 'Checked In',
  in_progress: 'In Progress',
  completed: 'Completed',
  canceled: 'Canceled',
  no_show: 'No Show',
  rescheduled: 'Rescheduled',
};

export function AppointmentStatusBadge({ status, className }: { status: AppointmentStatus; className?: string }) {
  return <StateMachineBadge status={status} colorMap={APPOINTMENT_COLOR_MAP} labelMap={APPOINTMENT_LABEL_MAP} className={className} />;
}

// ─── Claim ────────────────────────────────────────────────────────────────────
const CLAIM_COLOR_MAP: Record<ClaimStatus, BadgeVariant> = {
  draft: 'gray',
  submitted: 'blue',
  under_review: 'yellow',
  approved: 'green',
  denied: 'red',
  paid: 'indigo',
};

export function ClaimStatusBadge({ status, className }: { status: ClaimStatus; className?: string }) {
  return <StateMachineBadge status={status} colorMap={CLAIM_COLOR_MAP} className={className} />;
}

// ─── Invoice ─────────────────────────────────────────────────────────────────
const INVOICE_COLOR_MAP: Record<InvoiceStatus, BadgeVariant> = {
  draft: 'gray',
  issued: 'blue',
  partially_paid: 'yellow',
  paid: 'green',
  canceled: 'red',
};

export function InvoiceStatusBadge({ status, className }: { status: InvoiceStatus; className?: string }) {
  return <StateMachineBadge status={status} colorMap={INVOICE_COLOR_MAP} className={className} />;
}

// ─── Payment ─────────────────────────────────────────────────────────────────
const PAYMENT_COLOR_MAP: Record<PaymentStatus, BadgeVariant> = {
  initiated: 'gray',
  pending: 'yellow',
  captured: 'green',
  failed: 'red',
  canceled: 'red',
  refunded: 'purple',
};

export function PaymentStatusBadge({ status, className }: { status: PaymentStatus; className?: string }) {
  return <StateMachineBadge status={status} colorMap={PAYMENT_COLOR_MAP} className={className} />;
}

// ─── Lab Order ───────────────────────────────────────────────────────────────
const LAB_ORDER_COLOR_MAP: Record<LabOrderStatus, BadgeVariant> = {
  draft: 'gray',
  submitted: 'blue',
  in_progress: 'yellow',
  completed: 'green',
  canceled: 'red',
};

export function LabOrderStatusBadge({ status, className }: { status: LabOrderStatus; className?: string }) {
  return <StateMachineBadge status={status} colorMap={LAB_ORDER_COLOR_MAP} className={className} />;
}
