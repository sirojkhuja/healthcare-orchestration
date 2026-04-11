import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { Badge } from '@/components/ui/Badge';
import { Spinner } from '@/components/ui/Spinner';
import { StateMachineBadge } from '@/components/shared/StateMachineBadge';
import { MoneyDisplay } from '@/components/shared/MoneyDisplay';
import { STALE } from '@/lib/query/queryClient';
import type { Appointment } from '@/types/api/appointments';
import type { PaginatedResponse } from '@/types/common';
import type { Invoice } from '@/types/api/billing';
import type { LabOrder } from '@/types/api/lab';

const APPOINTMENT_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-700',
  scheduled: 'bg-blue-100 text-blue-700',
  confirmed: 'bg-indigo-100 text-indigo-700',
  checked_in: 'bg-amber-100 text-amber-700',
  in_progress: 'bg-orange-100 text-orange-700',
  completed: 'bg-green-100 text-green-700',
  canceled: 'bg-red-100 text-red-700',
  no_show: 'bg-red-100 text-red-700',
  rescheduled: 'bg-purple-100 text-purple-700',
};

function StatCard({ title, value, sub, href }: { title: string; value: React.ReactNode; sub?: string; href?: string }) {
  const inner = (
    <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:shadow-md transition-shadow">
      <p className="text-sm font-medium text-gray-500">{title}</p>
      <p className="mt-2 text-3xl font-bold text-gray-900">{value}</p>
      {sub && <p className="mt-1 text-xs text-gray-400">{sub}</p>}
    </div>
  );
  return href ? <Link to={href}>{inner}</Link> : inner;
}

export default function DashboardPage() {
  const today = format(new Date(), 'yyyy-MM-dd');

  const { data: todayAppts, isLoading: apptLoading } = useQuery({
    queryKey: ['appointments', 'dashboard', today],
    queryFn: () =>
      api.get<PaginatedResponse<Appointment>>(endpoints.appointments, {
        params: { date_from: today, date_to: today, per_page: 10 },
      }).then((r) => r.data),
    staleTime: STALE.REALTIME,
  });

  const { data: pendingLab } = useQuery({
    queryKey: ['lab-orders', 'dashboard'],
    queryFn: () =>
      api.get<PaginatedResponse<LabOrder>>(endpoints.labOrders, {
        params: { status: 'in_progress', per_page: 5 },
      }).then((r) => r.data),
    staleTime: STALE.REALTIME,
  });

  const { data: unpaidInvoices } = useQuery({
    queryKey: ['invoices', 'dashboard'],
    queryFn: () =>
      api.get<PaginatedResponse<Invoice>>(endpoints.invoices, {
        params: { status: 'issued', per_page: 5 },
      }).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const statusCounts = (todayAppts?.data ?? []).reduce<Record<string, number>>((acc, a) => {
    acc[a.status] = (acc[a.status] ?? 0) + 1;
    return acc;
  }, {});

  return (
    <div className="flex flex-col gap-8">
      <div>
        <h1 className="text-2xl font-semibold text-gray-900">Dashboard</h1>
        <p className="mt-1 text-sm text-gray-500">
          {format(new Date(), 'EEEE, MMMM d, yyyy')}
        </p>
      </div>

      {/* Stat cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          title="Today's appointments"
          value={apptLoading ? <Spinner size="sm" /> : (todayAppts?.meta.total ?? 0)}
          sub={`${statusCounts['completed'] ?? 0} completed`}
          href="/appointments"
        />
        <StatCard
          title="Pending lab results"
          value={pendingLab?.meta.total ?? 0}
          sub="Awaiting results"
          href="/lab-orders?status=in_progress"
        />
        <StatCard
          title="Unpaid invoices"
          value={unpaidInvoices?.meta.total ?? 0}
          sub="Requires follow-up"
          href="/billing/invoices?status=issued"
        />
        <StatCard
          title="Waitlisted patients"
          value="—"
          href="/waitlist"
        />
      </div>

      {/* Status breakdown */}
      {!apptLoading && Object.keys(statusCounts).length > 0 && (
        <div>
          <h2 className="mb-3 text-sm font-semibold text-gray-700 uppercase tracking-wide">
            Today by status
          </h2>
          <div className="flex flex-wrap gap-2">
            {Object.entries(statusCounts).map(([status, count]) => (
              <span
                key={status}
                className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium ${APPOINTMENT_COLORS[status] ?? 'bg-gray-100 text-gray-700'}`}
              >
                <span className="capitalize">{status.replace('_', ' ')}</span>
                <span className="font-bold">{count}</span>
              </span>
            ))}
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Today's appointments */}
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm">
          <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <h2 className="font-semibold text-gray-900">Today's appointments</h2>
            <Link to="/appointments" className="text-sm text-blue-600 hover:underline">
              View all
            </Link>
          </div>
          <div className="divide-y divide-gray-50">
            {apptLoading && (
              <div className="flex justify-center py-8">
                <Spinner />
              </div>
            )}
            {!apptLoading && (todayAppts?.data ?? []).length === 0 && (
              <p className="px-5 py-8 text-center text-sm text-gray-400">No appointments today</p>
            )}
            {(todayAppts?.data ?? []).map((appt) => (
              <Link
                key={appt.id}
                to={`/appointments/${appt.id}`}
                className="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors"
              >
                <div>
                  <p className="text-sm font-medium text-gray-900">{appt.patient_name}</p>
                  <p className="text-xs text-gray-500">
                    {appt.provider_name} · {format(new Date(appt.scheduled_start_at), 'h:mm a')}
                  </p>
                </div>
                <StateMachineBadge status={appt.status} colorMap={APPOINTMENT_COLORS} />
              </Link>
            ))}
          </div>
        </div>

        {/* Recent lab results */}
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm">
          <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <h2 className="font-semibold text-gray-900">Pending lab results</h2>
            <Link to="/lab-orders" className="text-sm text-blue-600 hover:underline">
              View all
            </Link>
          </div>
          <div className="divide-y divide-gray-50">
            {(pendingLab?.data ?? []).length === 0 && (
              <p className="px-5 py-8 text-center text-sm text-gray-400">No pending lab orders</p>
            )}
            {(pendingLab?.data ?? []).map((order) => (
              <Link
                key={order.id}
                to={`/lab-orders/${order.id}`}
                className="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors"
              >
                <div>
                  <p className="text-sm font-medium text-gray-900">{order.patient_name}</p>
                  <p className="text-xs text-gray-500">
                    {order.tests_count} test{order.tests_count !== 1 ? 's' : ''} · Ordered {format(new Date(order.ordered_at), 'MMM d')}
                  </p>
                </div>
                <Badge variant="amber">In progress</Badge>
              </Link>
            ))}
          </div>
        </div>
      </div>

      {/* Unpaid invoices */}
      {(unpaidInvoices?.data ?? []).length > 0 && (
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm">
          <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <h2 className="font-semibold text-gray-900">Unpaid invoices</h2>
            <Link to="/billing/invoices?status=issued" className="text-sm text-blue-600 hover:underline">
              View all
            </Link>
          </div>
          <div className="divide-y divide-gray-50">
            {(unpaidInvoices?.data ?? []).map((inv) => (
              <Link
                key={inv.id}
                to={`/billing/invoices/${inv.id}`}
                className="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors"
              >
                <div>
                  <p className="text-sm font-medium text-gray-900">{inv.patient_name}</p>
                  <p className="text-xs text-gray-500">#{inv.invoice_number}</p>
                </div>
                <span className="text-sm font-semibold text-gray-900">
                  <MoneyDisplay money={inv.balance_due} />
                </span>
              </Link>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
