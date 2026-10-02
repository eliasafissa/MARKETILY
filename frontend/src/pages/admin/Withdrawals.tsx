import { useEffect, useState } from 'react';
import { adminWithdrawalApi } from '@/api/client';
import toast from 'react-hot-toast';
import Button from '@/components/Button';
import PageTransition from '@/components/PageTransition';
import { formatPrice } from '@/utils/format';
import { useI18n } from '@/i18n';

export default function AdminWithdrawals() {
  const [withdrawals, setWithdrawals] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const { t } = useI18n();

  const fetch = () => {
    setLoading(true);
    adminWithdrawalApi.list()
      .then((r) => setWithdrawals(r.data.data ?? []))
      .catch(console.error)
      .finally(() => setLoading(false));
  };

  useEffect(() => { fetch(); }, []);

  const approve = async (id: number) => {
    try {
      await adminWithdrawalApi.approve(id);
      toast.success(t('admin.withdrawalApproved'));
      fetch();
    } catch (err: any) { toast.error(err.response?.data?.message ?? t('common.failed')); }
  };

  const reject = async (id: number) => {
    const reason = prompt(t('admin.reasonForRejection'));
    if (!reason) return;
    try {
      await adminWithdrawalApi.reject(id, reason);
      toast.success(t('admin.withdrawalRejected'));
      fetch();
    } catch (err: any) { toast.error(err.response?.data?.message ?? t('common.failed')); }
  };

  const hasPending = withdrawals.some((w) => w.status === 'pending');

  return (
    <PageTransition className="space-y-6">
      <div>
        <p className="eyebrow mb-1">{t('admin.finance')}</p>
        <h1 className="text-h1 text-gray-900 dark:text-ink-900">{t('admin.withdrawals')}</h1>
      </div>

      <div className="card overflow-hidden p-0">
        <div className="table-wrap">
          <table className="table">
            <thead>
              <tr>
                <th>#</th>
                <th>{t('admin.user')}</th>
                <th>{t('admin.amount')}</th>
                <th>{t('admin.fee')}</th>
                <th>{t('admin.method')}</th>
                <th>{t('admin.status')}</th>
                <th>{t('admin.date')}</th>
                {hasPending && <th>{t('admin.actions')}</th>}
              </tr>
            </thead>
            <tbody>
              {withdrawals.map((w) => (
                <tr key={w.id}>
                  <td className="font-medium text-gray-900 dark:text-ink-900 whitespace-nowrap">#{w.id}</td>
                  <td className="whitespace-nowrap">{w.user?.name ?? '—'}</td>
                  <td className="font-medium tabular-nums text-status-rejected whitespace-nowrap">
                    {formatPrice(w.amount)}
                  </td>
                  <td className="text-gray-600 dark:text-ink-500 tabular-nums whitespace-nowrap">{formatPrice(w.fee)}</td>
                  <td className="whitespace-nowrap">{w.method}</td>
                  <td><span className={`badge-${w.status}`}>{w.status}</span></td>
                  <td className="text-gray-600 dark:text-ink-500 whitespace-nowrap">{new Date(w.created_at).toLocaleDateString()}</td>
                  {hasPending && (
                    <td>
                      {w.status === 'pending' ? (
                        <div className="flex gap-2">
                          <Button size="sm" variant="success" onClick={() => approve(w.id)}>{t('admin.approve')}</Button>
                          <Button size="sm" variant="danger" onClick={() => reject(w.id)}>{t('admin.reject')}</Button>
                        </div>
                      ) : (
                        <span className="text-micro text-gray-600 dark:text-ink-500">—</span>
                      )}
                    </td>
                  )}
                </tr>
              ))}
              {withdrawals.length === 0 && !loading && (
                <tr><td colSpan={hasPending ? 8 : 7} className="text-center text-gray-600 dark:text-ink-500 py-8">{t('admin.noWithdrawalsYet')}</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </PageTransition>
  );
}
