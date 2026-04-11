import { useParams, Link } from 'react-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Spinner } from '@/components/ui/Spinner';
import { InvoiceStatusBadge } from '@/components/shared/StateMachineBadge';
import { MoneyDisplay } from '@/components/shared/MoneyDisplay';
import { generateIdempotencyKey } from '@/lib/api/idempotency';
import { STALE } from '@/lib/query/queryClient';
import type { Invoice, Payment } from '@/types/api/billing';
import type { PaginatedResponse } from '@/types/common';

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-700',
  issued: 'bg-blue-100 text-blue-700',
  partially_paid: 'bg-amber-100 text-amber-700',
  paid: 'bg-green-100 text-green-700',
  canceled: 'bg-red-100 text-red-700',
};

export default function InvoiceDetailPage() {
  const { invoiceId } = useParams<{ invoiceId: string }>();
  const qc = useQueryClient();

  const { data: invoice, isLoading } = useQuery({
    queryKey: ['invoices', 'detail', invoiceId],
    queryFn: () => api.get<Invoice>(endpoints.invoice(invoiceId!)).then((r) => r.data),
    staleTime: STALE.DETAIL,
    enabled: !!invoiceId,
  });

  const { data: payments } = useQuery({
    queryKey: ['payments', 'for-invoice', invoiceId],
    queryFn: () =>
      api.get<PaginatedResponse<Payment>>(endpoints.payments, { params: { invoice_id: invoiceId } })
        .then((r) => r.data),
    staleTime: STALE.LIST,
    enabled: !!invoiceId,
  });

  const { mutate: doAction, isPending } = useMutation({
    mutationFn: (action: 'issue' | 'cancel') =>
      api.post(endpoints.invoiceAction(invoiceId!, action), {}, {
        headers: { 'Idempotency-Key': generateIdempotencyKey() },
      }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['invoices', 'detail', invoiceId] }),
  });

  if (isLoading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>;
  if (!invoice) return <p className="text-center text-gray-500 py-16">Invoice not found.</p>;

  return (
    <div className="flex flex-col gap-6 max-w-4xl">
      <nav className="text-sm text-gray-500">
        <Link to="/billing/invoices" className="hover:underline">Invoices</Link>
        <span className="mx-2">/</span>
        <span className="text-gray-900">#{invoice.invoice_number}</span>
      </nav>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Invoice document */}
        <div className="lg:col-span-2 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
          <div className="flex items-start justify-between">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Invoice</h1>
              <p className="text-gray-500 font-mono">#{invoice.invoice_number}</p>
            </div>
            <InvoiceStatusBadge status={invoice.status} />
          </div>

          <div className="mt-4 grid grid-cols-2 gap-4 text-sm">
            <div>
              <p className="text-gray-500">Patient</p>
              <Link to={`/patients/${invoice.patient_id}`} className="font-medium text-blue-600 hover:underline">
                {invoice.patient_name}
              </Link>
            </div>
            {invoice.issued_at && (
              <div>
                <p className="text-gray-500">Issued</p>
                <p className="font-medium text-gray-900">{format(new Date(invoice.issued_at), 'MMM d, yyyy')}</p>
              </div>
            )}
            {invoice.due_date && (
              <div>
                <p className="text-gray-500">Due</p>
                <p className="font-medium text-gray-900">{format(new Date(invoice.due_date), 'MMM d, yyyy')}</p>
              </div>
            )}
          </div>

          {/* Line items */}
          <div className="mt-6 overflow-hidden rounded-lg border border-gray-100">
            <table className="w-full text-sm">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-2.5 text-left font-medium text-gray-600">Service</th>
                  <th className="px-4 py-2.5 text-right font-medium text-gray-600">Qty</th>
                  <th className="px-4 py-2.5 text-right font-medium text-gray-600">Unit price</th>
                  <th className="px-4 py-2.5 text-right font-medium text-gray-600">Total</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {invoice.items.map((item) => (
                  <tr key={item.id}>
                    <td className="px-4 py-3 text-gray-900">
                      {item.service_name}
                      {item.service_code && <span className="ml-2 text-xs text-gray-400 font-mono">{item.service_code}</span>}
                    </td>
                    <td className="px-4 py-3 text-right text-gray-600">{item.quantity}</td>
                    <td className="px-4 py-3 text-right text-gray-600"><MoneyDisplay money={item.unit_price} /></td>
                    <td className="px-4 py-3 text-right font-medium text-gray-900"><MoneyDisplay money={item.total} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Totals */}
          <div className="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm">
            <div className="flex justify-between text-gray-600">
              <span>Subtotal</span>
              <MoneyDisplay money={invoice.subtotal} />
            </div>
            {invoice.discount_amount.amount > 0 && (
              <div className="flex justify-between text-green-600">
                <span>Discount</span>
                <span>-<MoneyDisplay money={invoice.discount_amount} /></span>
              </div>
            )}
            {invoice.tax_amount.amount > 0 && (
              <div className="flex justify-between text-gray-600">
                <span>Tax</span>
                <MoneyDisplay money={invoice.tax_amount} />
              </div>
            )}
            <div className="flex justify-between text-base font-bold text-gray-900 border-t border-gray-200 pt-2">
              <span>Total</span>
              <MoneyDisplay money={invoice.total} />
            </div>
            {invoice.paid_amount.amount > 0 && (
              <div className="flex justify-between text-green-600">
                <span>Paid</span>
                <MoneyDisplay money={invoice.paid_amount} />
              </div>
            )}
            <div className="flex justify-between text-base font-bold text-red-600">
              <span>Balance due</span>
              <MoneyDisplay money={invoice.balance_due} />
            </div>
          </div>
        </div>

        {/* Actions sidebar */}
        <div className="flex flex-col gap-4">
          <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 className="font-semibold text-gray-900 mb-3">Actions</h2>
            <div className="flex flex-col gap-2">
              {invoice.status === 'draft' && (
                <Button className="w-full justify-center" isLoading={isPending} onClick={() => doAction('issue')}>
                  Issue invoice
                </Button>
              )}
              {(invoice.status === 'draft' || invoice.status === 'issued') && (
                <Button variant="danger" className="w-full justify-center" onClick={() => doAction('cancel')}>
                  Cancel invoice
                </Button>
              )}
              {invoice.status === 'issued' && (
                <Button variant="secondary" className="w-full justify-center">
                  Initiate payment
                </Button>
              )}
              {invoice.status === 'paid' && (
                <span className="text-sm text-green-600 font-medium text-center">Invoice fully paid</span>
              )}
            </div>
          </div>

          {/* Payments on this invoice */}
          {(payments?.data ?? []).length > 0 && (
            <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
              <h2 className="font-semibold text-gray-900 mb-3">Payments</h2>
              <div className="divide-y divide-gray-100">
                {payments!.data.map((p) => (
                  <div key={p.id} className="flex items-center justify-between py-2 text-sm">
                    <div>
                      <p className="font-medium text-gray-900 capitalize">{p.provider}</p>
                      <p className="text-xs text-gray-400">{format(new Date(p.initiated_at), 'MMM d, h:mm a')}</p>
                    </div>
                    <div className="text-right">
                      <MoneyDisplay money={p.amount} />
                      <Badge
                        variant={p.status === 'captured' ? 'green' : p.status === 'failed' ? 'red' : 'gray'}
                        className="ml-2 capitalize"
                      >
                        {p.status}
                      </Badge>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
