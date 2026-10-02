import { useEffect, useState } from 'react';
import { adminUserApi } from '@/api/client';
import toast from 'react-hot-toast';
import PageTransition from '@/components/PageTransition';
import { formatPrice } from '@/utils/format';
import { useI18n } from '@/i18n';

export default function AdminUsers() {
  const { t } = useI18n();
  const [users, setUsers] = useState<any[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [balanceModal, setBalanceModal] = useState<{ user: any; type: 'add' | 'deduct' } | null>(null);
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const fetchUsers = () => {
    setLoading(true);
    adminUserApi.list(search ? { search } : {})
      .then((r) => setUsers(r.data.data ?? []))
      .catch(console.error)
      .finally(() => setLoading(false));
  };

  useEffect(() => { fetchUsers(); }, [search]);

  const toggleBan = async (user: any) => {
    try {
      await adminUserApi.update(user.id, { banned: !user.banned_at });
      toast.success(user.banned_at ? t('admin.userUnbanned') : t('admin.userBanned'));
      fetchUsers();
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? t('common.failed'));
    }
  };

  const changeVip = async (user: any, level: string) => {
    try {
      await adminUserApi.update(user.id, { vip_level: level });
      toast.success(t('admin.vipUpdated', { level }));
      fetchUsers();
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? t('common.failed'));
    }
  };

  const openBalanceModal = (user: any, type: 'add' | 'deduct') => {
    setBalanceModal({ user, type });
    setAmount('');
    setNote('');
  };

  const closeBalanceModal = () => {
    setBalanceModal(null);
    setAmount('');
    setNote('');
  };

  const handleBalanceSubmit = async () => {
    if (!balanceModal || !amount) return;
    const { user, type } = balanceModal;
    const numAmount = parseFloat(amount);
    if (isNaN(numAmount) || numAmount <= 0) {
      toast.error(t('common.invalidAmount') ?? 'Invalid amount');
      return;
    }
    setSubmitting(true);
    try {
      const finalAmount = type === 'add' ? numAmount : -numAmount;
      await adminUserApi.adjustBalance(user.id, { amount: finalAmount, note: note || undefined });
      toast.success(type === 'add' ? t('admin.balanceAdded') : t('admin.balanceDeducted'));
      fetchUsers();
      closeBalanceModal();
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? t('admin.balanceAdjustFailed'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <PageTransition className="space-y-6">
      <div className="flex items-end justify-between gap-4 flex-wrap">
        <div>
          <p className="eyebrow mb-1">{t('admin.system')}</p>
          <h1 className="text-h1 text-gray-900 dark:text-ink-900">{t('admin.allUsers')}</h1>
        </div>
        <div className="relative w-72 max-w-full">
          <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500 dark:text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
          <input
            type="search"
            placeholder={t('admin.searchUsers')}
            className="input pl-10"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
      </div>

      <div className="card overflow-hidden p-0">
        <div className="overflow-x-auto">
          <table className="table">
            <thead>
              <tr>
                <th>{t('admin.name')}</th>
                <th>{t('admin.email')}</th>
                <th>{t('admin.vip')}</th>
                <th>{t('account.balance')}</th>
                <th>{t('admin.status')}</th>
                <th>{t('admin.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {users.map((u) => (
                <tr key={u.id}>
                  <td className="font-medium text-gray-900 dark:text-ink-900">{u.name}</td>
                  <td className="text-gray-500 dark:text-ink-500">{u.email}</td>
                  <td>
                    <select
                      className="input py-1 text-xs w-28"
                      value={u.vip_level}
                      onChange={(e) => changeVip(u, e.target.value)}
                    >
                      <option value="none">{t('admin.regular')}</option>
                      <option value="vip1">VIP1</option>
                      <option value="vip2">VIP2</option>
                      <option value="vip3">VIP3</option>
                    </select>
                  </td>
                  <td className="font-medium tabular-nums">{formatPrice(u.balance)}</td>
                  <td>
                    {u.banned_at
                      ? <span className="badge-rejected">{t('admin.banned')}</span>
                      : <span className="badge-completed">{t('admin.active')}</span>}
                  </td>
                  <td>
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => openBalanceModal(u, 'add')}
                        className="text-small font-medium text-green-400 hover:text-green-300"
                      >
                        {t('admin.addBalance')}
                      </button>
                      <button
                        onClick={() => openBalanceModal(u, 'deduct')}
                        className="text-small font-medium text-amber-400 hover:text-amber-300"
                      >
                        {t('admin.deductBalance')}
                      </button>
                      <button
                        onClick={() => toggleBan(u)}
                        className={`text-small font-medium ${
                          u.banned_at
                            ? 'text-green-400 hover:text-green-300'
                            : 'text-status-rejected hover:text-status-rejected/80'
                        }`}
                      >
                        {u.banned_at ? t('admin.unban') : t('admin.ban')}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {users.length === 0 && !loading && (
                <tr><td colSpan={6} className="text-center text-gray-500 dark:text-ink-500 py-8">{t('admin.noUsersFound')}</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {balanceModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" onClick={closeBalanceModal}>
          <div className="bg-white dark:bg-ink-50 rounded-2xl w-full max-w-md p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-h3 text-gray-900 dark:text-ink-900 mb-4">
              {balanceModal.type === 'add' ? t('admin.addBalance') : t('admin.deductBalance')} — {balanceModal.user.name}
            </h3>
            <div className="space-y-4">
              <div>
                <label className="label">{t('admin.balanceAmount')}</label>
                <input
                  type="number"
                  step="0.01"
                  min="0.01"
                  className="input"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  placeholder="0.00"
                  autoFocus
                />
              </div>
              <div>
                <label className="label">{t('admin.balanceNote')}</label>
                <textarea
                  className="input min-h-[80px]"
                  value={note}
                  onChange={(e) => setNote(e.target.value)}
                  placeholder={t('admin.balanceNote')}
                  rows={3}
                />
              </div>
              <div className="flex justify-end gap-3 pt-4">
                <button
                  type="button"
                  onClick={closeBalanceModal}
                  className="btn-secondary"
                  disabled={submitting}
                >
                  {t('admin.cancel')}
                </button>
                <button
                  type="button"
                  onClick={handleBalanceSubmit}
                  className={balanceModal.type === 'add' ? 'btn-accent' : 'bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg font-medium'}
                  disabled={submitting}
                >
                  {submitting ? t('admin.confirm') : (balanceModal.type === 'add' ? t('admin.addBalance') : t('admin.deductBalance'))}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </PageTransition>
  );
}
