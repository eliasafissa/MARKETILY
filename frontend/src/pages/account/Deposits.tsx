import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { depositApi } from '@/api/client';
import toast from 'react-hot-toast';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';
import Breadcrumbs from '@/components/Breadcrumbs';
import { formatPrice, formatDateTime } from '@/utils/format';
import EmptyState from '@/components/EmptyState';
import Button from '@/components/Button';

interface DepositItem {
  id: number;
  user_id: number;
  type: string;
  amount: number;
  fee: number;
  status: 'pending' | 'approved' | 'rejected';
  method: string;
  gateway_ref: string | null;
  meta: Record<string, unknown>;
  rejection_reason: string | null;
  created_at: string;
  updated_at: string;
}

interface DepositsResponse {
  data: DepositItem[];
  current_page: number;
  last_page: number;
  total: number;
}

const METHOD_LABELS: Record<string, string> = {
  binance_pay: 'Binance Pay',
  usdt: 'USDT (BEP-20)',
  cash_wallet: 'Cash Wallet',
};

const STATUS_CONFIG: Record<string, { badge: string; label: string }> = {
  pending: { badge: 'badge-warning', label: 'depositHistory.pending' },
  approved: { badge: 'badge-success', label: 'depositHistory.approved' },
  rejected: { badge: 'badge-error', label: 'depositHistory.rejected' },
};

export default function Deposits() {
  const { t, locale } = useI18n();
  const [deposits, setDeposits] = useState<DepositItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [showNewDeposit, setShowNewDeposit] = useState(false);

  const isRtl = locale === 'ar';

  const fetchDeposits = async (pageNum = 1) => {
    try {
      const res = await depositApi.list();
      if (res.data.data) {
        setDeposits(res.data.data);
        setPage(res.data.current_page);
        setLastPage(res.data.last_page);
        setTotal(res.data.total);
      } else if (Array.isArray(res.data)) {
        setDeposits(res.data);
        setTotal(res.data.length);
      }
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? t('toast.failed'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDeposits();
  }, []);

  const getMethodLabel = (method: string) => {
    return METHOD_LABELS[method] || method;
  };

  const getStatusConfig = (status: string) => {
    return STATUS_CONFIG[status] || { badge: 'badge-neutral', label: status };
  };

  return (
    <PageTransition className="space-y-8">
      <Breadcrumbs
        items={[
          { label: t('nav.home'), link: '/' },
          { label: t('nav.dashboard'), link: '/dashboard' },
          { label: t('depositHistory.title') },
        ]}
      />
      <div className="flex flex-col gap-4">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <h1 className="text-h1 text-gray-900 dark:text-ink-900">{t('depositHistory.title')}</h1>
          <Button
            variant="accent"
            size="md"
            onClick={() => setShowNewDeposit(true)}
            className="w-full sm:w-auto"
          >
            {t('depositHistory.newDeposit')}
          </Button>
        </div>

        {loading ? (
          <div className="space-y-3">
            {[...Array(5)].map((_, i) => (
              <motion.div key={i} initial={{ opacity: 0, x: isRtl ? 20 : -20 }} animate={{ opacity: 1, x: 0 }} className="bg-gray-100 dark:bg-ink-100 rounded-xl h-16 animate-pulse" />
            ))}
          </div>
        ) : deposits.length === 0 ? (
          <EmptyState
            icon={
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true">
                <path d="M12 5v14M5 12l7 7 7-7" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
            }
            title={t('depositHistory.empty')}
            description={t('depositHistory.empty')}
            action={{ label: t('depositHistory.newDeposit'), onClick: () => setShowNewDeposit(true) }}
            className="py-16"
          />
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="table">
                <thead>
                  <tr>
                    <th>{t('depositHistory.date')}</th>
                    <th>{t('depositHistory.amount')}</th>
                    <th>{t('depositHistory.method')}</th>
                    <th>{t('depositHistory.reference')}</th>
                    <th>{t('depositHistory.status')}</th>
                    <th className="text-end">{t('admin.actions')}</th>
                  </tr>
                </thead>
                <tbody>
                  {deposits.map((deposit) => {
                    const statusConfig = getStatusConfig(deposit.status);
                    return (
                      <tr key={deposit.id}>
                        <td className="text-gray-500 dark:text-ink-500 whitespace-nowrap">
                          {formatDateTime(deposit.created_at)}
                        </td>
                        <td className="font-semibold tabular-nums text-green-400">
                          +{formatPrice(deposit.amount)}
                        </td>
                        <td className="text-gray-900 dark:text-ink-800">
                          {getMethodLabel(deposit.method)}
                        </td>
                        <td className="text-gray-500 dark:text-ink-500 font-mono text-xs">
                          {deposit.gateway_ref ?? '—'}
                        </td>
                        <td>
                          <span className={`badge ${statusConfig.badge}`}>
                            {t(statusConfig.label)}
                          </span>
                        </td>
                        <td className="text-end">
                          <Link
                            to={`/dashboard/deposit/${deposit.id}`}
                            className="btn-ghost btn-sm"
                          >
                            {t('common.view')}
                          </Link>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>

            {lastPage > 1 && (
              <div className="flex items-center justify-center gap-2 mt-6">
                <button
                  onClick={() => page > 1 && fetchDeposits(page - 1)}
                  disabled={page <= 1}
                  className="btn-ghost btn-sm"
                >
                  ← {t('common.prev')}
                </button>
                <span className="text-sm text-gray-600 dark:text-ink-500">
                  {t('common.page')} {page} {t('common.of')} {lastPage}
                </span>
                <button
                  onClick={() => page < lastPage && fetchDeposits(page + 1)}
                  disabled={page >= lastPage}
                  className="btn-ghost btn-sm"
                >
                  {t('common.next')} →
                </button>
              </div>
            )}

            {/* New Deposit Modal */}
            {showNewDeposit && (
              <motion.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
                onClick={() => setShowNewDeposit(false)}
              >
                <motion.div
                  initial={{ opacity: 0, scale: 0.95, y: 20 }}
                  animate={{ opacity: 1, scale: 1, y: 0 }}
                  exit={{ opacity: 0, scale: 0.95, y: 20 }}
                  className="bg-white dark:bg-ink-800 rounded-xl shadow-xl max-w-md w-full p-6"
                  onClick={(e) => e.stopPropagation()}
                >
                  <div className="flex items-center justify-between mb-6">
                    <h2 className="text-h3 text-gray-900 dark:text-ink-900">{t('depositHistory.newDeposit')}</h2>
                    <button
                      onClick={() => setShowNewDeposit(false)}
                      className="btn-ghost btn-sm"
                    >
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                      </svg>
                    </button>
                  </div>
                  <p className="text-body text-gray-600 dark:text-ink-500 mb-6">
                    {t('deposit.subtitle')}
                  </p>
                  <Link
                    to="/dashboard/deposit"
                    className="block"
                  >
                    <Button
                      variant="accent"
                      size="lg"
                      className="w-full"
                      onClick={() => setShowNewDeposit(false)}
                    >
                      {t('deposit.submit')}
                    </Button>
                  </Link>
                </motion.div>
              </motion.div>
            )}
          </>
        )}
      </div>
    </PageTransition>
  );
}
