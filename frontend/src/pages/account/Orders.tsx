import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { orderApi } from '@/api/client';
import EmptyState from '@/components/EmptyState';
import PageTransition from '@/components/PageTransition';
import { formatPrice, formatDateTime } from '@/utils/format';
import { useI18n } from '@/i18n';
import Breadcrumbs from '@/components/Breadcrumbs';

export default function Orders() {
  const { t } = useI18n();
  const [orders, setOrders] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    orderApi.list()
      .then((r) => setOrders(r.data.data ?? []))
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="flex items-center justify-center py-24">
        <span className="w-8 h-8 rounded-full border-2 border-green-500 border-t-transparent animate-spin" />
      </div>
    );
  }

  return (
    <PageTransition className="max-w-4xl mx-auto w-full space-y-6">
      <Breadcrumbs
        items={[
          { label: t('nav.home'), link: '/' },
          { label: t('nav.dashboard'), link: '/dashboard' },
          { label: t('orders.title') },
        ]}
      />
      <h1 className="text-h1 text-gray-900 dark:text-ink-900 text-center mt-4">{t('orders.title')}</h1>

      {orders.length === 0 ? (
        <EmptyState
          icon={
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true">
              <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" strokeLinecap="round" />
              <rect x="9" y="3" width="6" height="4" rx="1" />
            </svg>
          }
          title={t('orders.empty')}
          description="When you place your first order, it will appear here with real-time status updates. Browse the catalog to get started."
          action={{ label: t('home.browseProducts'), to: '/products' }}
        />
      ) : (
        <div className="space-y-4">
          {orders.map((order, i) => (
            <motion.div
              key={order.id}
              className="card-pad"
              initial={{ opacity: 0, y: 12 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: i * 0.05, duration: 0.3 }}
            >
              <div className="flex items-start justify-between gap-4 mb-4">
                <div>
                  <h3 className="text-body font-semibold text-gray-900 dark:text-ink-900">
                    {t('orders.orderNumber')}{order.id}
                  </h3>
                  <p className="text-small text-gray-600 dark:text-ink-500">
                    {formatDateTime(order.created_at)}
                  </p>
                </div>
                <div className="text-right">
                  <span className={`badge-${order.status}`}>{t(`orders.statuses.${order.status.toLowerCase()}`)}</span>
                  <p className="text-h3 text-green-400 mt-1 tabular-nums">
                    {formatPrice(order.total)}
                  </p>
                </div>
              </div>

              {order.items?.length > 0 && (
                <div className="border-t border-ink-200 pt-3 space-y-2">
                  {order.items.map((item: any) => (
                    <div key={item.id} className="flex justify-between text-small">
                      <span className="text-gray-700 dark:text-ink-700">
                        {item.product?.name ?? 'Product'} × {item.quantity}
                      </span>
                      <span className="text-gray-600 dark:text-ink-600 tabular-nums">
                        {formatPrice(Number(item.unit_price) * item.quantity)}
                      </span>
                    </div>
                  ))}
                </div>
              )}

              {order.notes && (
                <p className="text-micro text-gray-600 dark:text-ink-500 mt-2 pt-2 border-t border-ink-200">
                  {order.notes}
                </p>
              )}
            </motion.div>
          ))}
        </div>
      )}
    </PageTransition>
  );
}
