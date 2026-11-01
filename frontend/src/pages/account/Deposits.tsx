import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { depositApi } from '@/api/client';
import toast from 'react-hot-toast';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';
import Breadcrumbs from '@/components/Breadcrumbs';
import { formatDateTime } from '@/utils/format';
import EmptyState from '@/components/EmptyState';
import Button from '@/components/Button';

interface DepositItem {
  id: number; user_id: number; type: string; amount: number; fee: number;
  status: 'pending' | 'approved' | 'rejected'; method: string; gateway_ref: string | null;
  meta: Record<string, unknown>; rejection_reason: string | null; created_at: string; updated_at: string;
}

const METHOD_LABELS: Record<string, string> = {
  binance_pay: 'Binance Pay', usdt: 'USDT (BEP-20)',
  sham_cash: 'شام كاش / SYP', sham_cash_usd: 'شام كاش دولار / USD',
  syriatel_cash: 'سيرياتيل كاش / Syriatel Cash', c_wallet: 'سي والت / C Wallet',
};

const STATUS_CONFIG: Record<string, { badge: string; label: string }> = {
  pending: { badge: 'hs-deposit-state hs-deposit-pending', label: 'بانتظار المراجعة' },
  approved: { badge: 'hs-deposit-state hs-deposit-approved', label: 'مقبول' },
  rejected: { badge: 'hs-deposit-state hs-deposit-rejected', label: 'مرفوض' },
};

export default function Deposits() {
  const { t, locale } = useI18n();
  const [deposits, setDeposits] = useState<DepositItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1); const [lastPage, setLastPage] = useState(1);
  const [showNewDeposit, setShowNewDeposit] = useState(false);
  const isRtl = locale === 'ar';

  const fetchDeposits = async (pageNum = 1) => {
    try {
      const res = await depositApi.list(pageNum);
      if (res.data.data) { setDeposits(res.data.data); setPage(res.data.current_page); setLastPage(res.data.last_page); }
      else if (Array.isArray(res.data)) { setDeposits(res.data); }
    } catch (err: any) { toast.error(err.response?.data?.message ?? t('toast.failed')); } finally { setLoading(false); }
  };

  useEffect(() => {
    void fetchDeposits(page);
    const timer = window.setInterval(() => { if (!document.hidden) void fetchDeposits(page); }, 25000);
    const onFocus = () => void fetchDeposits(page);
    window.addEventListener('focus', onFocus);
    return () => { window.clearInterval(timer); window.removeEventListener('focus', onFocus); };
  }, [page]);

  return (
    <PageTransition className="space-y-8">
      <Breadcrumbs items={[{ label: t('nav.home'), link: '/' }, { label: t('nav.dashboard'), link: '/dashboard' }, { label: t('depositHistory.title') }]} />
      <div className="flex flex-col gap-4">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <h1 className="text-h1 text-gray-900 dark:text-ink-900">{t('depositHistory.title')}</h1>
          <Button variant="accent" size="md" onClick={() => setShowNewDeposit(true)} className="w-full sm:w-auto">{t('depositHistory.newDeposit')}</Button>
        </div>

        {loading ? (
          <div className="space-y-3">{[...Array(5)].map((_, i) => <motion.div key={i} initial={{ opacity: 0, x: isRtl ? 20 : -20 }} animate={{ opacity: 1, x: 0 }} className="bg-gray-100 dark:bg-ink-100 rounded-xl h-16 animate-pulse" />)}</div>
        ) : deposits.length === 0 ? (
          <EmptyState icon={<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5"><path d="M12 5v14M5 12l7 7 7-7" strokeLinecap="round" strokeLinejoin="round" /></svg>} title={t('depositHistory.empty')} description={t('depositHistory.empty')} action={{ label: t('depositHistory.newDeposit'), onClick: () => setShowNewDeposit(true) }} className="py-16" />
        ) : (
          <>
            {/* Desktop Table */}
            <div className="hidden md:block overflow-x-auto bg-white dark:bg-ink-50 rounded-2xl border border-[var(--border)]">
              <table className="w-full text-sm text-right">
                <thead className="bg-gray-50 dark:bg-black/20 border-b border-gray-100 dark:border-gray-800">
                  <tr>
                    <th className="px-4 py-3 font-medium text-gray-500">{t('depositHistory.date')}</th>
                    <th className="px-4 py-3 font-medium text-gray-500">{t('depositHistory.amount')}</th>
                    <th className="px-4 py-3 font-medium text-gray-500">{t('depositHistory.method')}</th>
                    <th className="px-4 py-3 font-medium text-gray-500">{t('depositHistory.status')}</th>
                    <th className="px-4 py-3 font-medium text-gray-500 text-left">{t('admin.actions')}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {deposits.map((deposit) => {
                    const statusConfig = STATUS_CONFIG[deposit.status] || { badge: 'badge-neutral', label: deposit.status };
                    return (
                      <tr key={deposit.id} className="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                        <td className="px-4 py-4 text-gray-500 whitespace-nowrap">{formatDateTime(deposit.created_at)}</td>
                        <td className="px-4 py-4 whitespace-nowrap"><strong className="text-green-500">{Number(deposit.meta?.transfer_amount ?? deposit.amount).toLocaleString('en-US')} {deposit.meta?.transfer_currency === 'SYP' ? 'ل.س' : 'USD'}</strong></td>
                        <td className="px-4 py-4 whitespace-nowrap">{METHOD_LABELS[deposit.method] || deposit.method}</td>
                        <td className="px-4 py-4 whitespace-nowrap"><span className={`badge ${statusConfig.badge}`}>{statusConfig.label}</span></td>
                        <td className="px-4 py-4 text-left whitespace-nowrap"><Link to={`/dashboard/deposit/${deposit.id}`} className="text-blue-500 hover:underline text-sm font-medium">{t('common.view')}</Link></td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>

            {/* Mobile Cards */}
            <div className="grid grid-cols-1 gap-4 md:hidden">
              {deposits.map((deposit) => {
                const statusConfig = STATUS_CONFIG[deposit.status] || { badge: 'badge-neutral', label: deposit.status };
                return (
                  <div key={deposit.id} className="bg-white dark:bg-ink-50 rounded-2xl p-4 shadow-sm border border-gray-100 dark:border-ink-200">
                    <div className="flex justify-between items-start mb-3">
                      <div>
                        <span className="text-xs text-gray-500 block">{formatDateTime(deposit.created_at)}</span>
                        <strong className="text-sm font-bold text-gray-900 dark:text-white mt-1 block">{METHOD_LABELS[deposit.method] || deposit.method}</strong>
                      </div>
                      <span className={`badge ${statusConfig.badge}`}>{statusConfig.label}</span>
                    </div>
                    <div className="bg-gray-50 dark:bg-black/10 p-3 rounded-xl flex justify-between items-center mb-3">
                      <span className="text-xs text-gray-500">المبلغ:</span>
                      <strong className="text-green-500">{Number(deposit.meta?.transfer_amount ?? deposit.amount).toLocaleString('en-US')} {deposit.meta?.transfer_currency === 'SYP' ? 'ل.س' : 'USD'}</strong>
                    </div>
                    <Link to={`/dashboard/deposit/${deposit.id}`} className="block w-full py-2 bg-gray-100 dark:bg-white/10 text-center rounded-lg text-xs font-bold">{t('common.view')}</Link>
                  </div>
                );
              })}
            </div>

            {/* Pagination */}
            {lastPage > 1 && (
              <div className="flex items-center justify-center gap-2 mt-6">
                <button onClick={() => page > 1 && fetchDeposits(page - 1)} disabled={page <= 1} className="px-4 py-2 rounded-lg border border-gray-200 dark:border-ink-200 text-sm font-medium disabled:opacity-50">← السابق</button>
                <span className="text-sm text-gray-600 dark:text-ink-500">صفحة {page} من {lastPage}</span>
                <button onClick={() => page < lastPage && fetchDeposits(page + 1)} disabled={page >= lastPage} className="px-4 py-2 rounded-lg border border-gray-200 dark:border-ink-200 text-sm font-medium disabled:opacity-50">التالي →</button>
              </div>
            )}

            {/* New Deposit Modal */}
            {showNewDeposit && (
              <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" onClick={() => setShowNewDeposit(false)}>
                <motion.div initial={{ opacity: 0, scale: 0.95, y: 20 }} animate={{ opacity: 1, scale: 1, y: 0 }} exit={{ opacity: 0, scale: 0.95, y: 20 }} className="bg-white dark:bg-ink-800 rounded-2xl shadow-xl max-w-md w-full p-5 sm:p-6" onClick={(e) => e.stopPropagation()}>
                  <div className="flex items-center justify-between mb-4">
                    <h2 className="text-lg font-bold text-gray-900 dark:text-white">{t('depositHistory.newDeposit')}</h2>
                    <button onClick={() => setShowNewDeposit(false)} className="p-2 text-gray-500 hover:bg-gray-100 rounded-lg">✕</button>
                  </div>
                  <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">{t('deposit.subtitle')}</p>
                  <Link to="/dashboard/deposit" className="block"><Button variant="accent" size="lg" className="w-full" onClick={() => setShowNewDeposit(false)}>{t('deposit.submit')}</Button></Link>
                </motion.div>
              </motion.div>
            )}
          </>
        )}
      </div>
    </PageTransition>
  );
}