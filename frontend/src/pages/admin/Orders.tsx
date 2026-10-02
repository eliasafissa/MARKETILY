import { useEffect, useState } from 'react';
import { adminOrderApi } from '@/api/client';
import PageTransition from '@/components/PageTransition';
import { formatPrice } from '@/utils/format';
import { useI18n } from '@/i18n';

export default function AdminOrders() {
  const [orders, setOrders] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const { t } = useI18n();

  useEffect(() => {
    adminOrderApi.list()
      .then((r) => setOrders(r.data.data ?? []))
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  return (
    <PageTransition className="space-y-6">
      <div>
        <p className="eyebrow mb-1">{t('admin.operations')}</p>
        <h1 className="text-h1 text-gray-900 dark:text-ink-900">{t('admin.allOrders')}</h1>
      </div>

      <div className="card overflow-visible p-0">
        <div className="table-wrap">
          <table className="table">
            <thead>
              <tr>
                <th>#</th>
                <th>{t('admin.user')}</th>
                <th>{t('admin.total')}</th>
                <th>{t('admin.status')}</th>
                <th>{t('admin.method')}</th>
                <th>{t('admin.date')}</th>
              </tr>
            </thead>
            <tbody>
              {orders.map((o) => (
                <tr key={o.id}>
                  <td className="font-medium text-gray-900 dark:text-ink-900 whitespace-nowrap">#{o.id}</td>
                  <td className="whitespace-nowrap">{o.user?.name ?? '—'}</td>
                  <td className="tabular-nums whitespace-nowrap">{formatPrice(o.total)}</td>
                  <td><span className={`badge-${o.status}`}>{o.status}</span></td>
                  <td className="text-gray-500 dark:text-ink-500 whitespace-nowrap">{o.payment_method}</td>
                  <td className="text-gray-500 dark:text-ink-500 whitespace-nowrap">{new Date(o.created_at).toLocaleDateString()}</td>
                </tr>
              ))}
              {orders.length === 0 && !loading && (
                <tr><td colSpan={6} className="text-center text-gray-500 dark:text-ink-500 py-8">{t('admin.noOrdersYet')}</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </PageTransition>
  );
}
