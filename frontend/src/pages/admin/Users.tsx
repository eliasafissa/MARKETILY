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
      await adminUserApi.update(user.id, { vip_override: level });
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
      <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
          <p className="eyebrow mb-1">{t('admin.system')}</p>
          <h1 className="text-h1 text-gray-900 dark:text-ink-900">{t('admin.allUsers')}</h1>
        </div>
        <div className="relative w-full sm:w-72">
          <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500 dark:text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
          <input
            type="search"
            placeholder={t('admin.searchUsers')}
            className="input pl-10 w-full"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
      </div>

      {/* Desktop View: Table (Hidden on Mobile) */}
      <div className="card overflow-hidden p-0 hidden md:block">
        <div className="overflow-x-auto">
          <table className="table min-w-full text-sm">
            <thead>
              <tr>
                <th className="whitespace-nowrap px-4 py-3">{t('admin.name')}</th>
                <th className="whitespace-nowrap px-4 py-3">{t('admin.email')}</th>
                <th className="whitespace-nowrap px-4 py-3">{t('admin.vip')}</th>
                <th className="whitespace-nowrap px-4 py-3">المشتريات المكتملة</th>
                <th className="whitespace-nowrap px-4 py-3">{t('account.balance')}</th>
                <th className="whitespace-nowrap px-4 py-3">{t('admin.status')}</th>
                <th className="whitespace-nowrap px-4 py-3">{t('admin.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {users.map((u) => (
                <tr key={u.id}>
                  <td className="font-medium text-gray-900 dark:text-ink-900 px-4 py-3">{u.name}</td>
                  <td className="text-gray-500 dark:text-ink-500 px-4 py-3">{u.email}</td>
                  <td className="px-4 py-3">
                    <select className="input py-1 text-xs w-28 dark:bg-gray-800 dark:text-white" value={u.vip_override ?? 'auto'} onChange={(e) => changeVip(u, e.target.value)}>
                      <option value="auto" className="dark:bg-gray-800 dark:text-white">تلقائي ({(u.vip_level ?? 'none').toUpperCase()})</option>
                      <option value="none" className="dark:bg-gray-800 dark:text-white">عادي</option>
                      <option value="vip1" className="dark:bg-gray-800 dark:text-white">VIP 1</option>
                      <option value="vip2" className="dark:bg-gray-800 dark:text-white">VIP 2</option>
                      <option value="vip3" className="dark:bg-gray-800 dark:text-white">VIP 3</option>
                      <option value="vip4" className="dark:bg-gray-800 dark:text-white">VIP 4</option>
                    </select>
                  </td>
                  <td className="tabular-nums whitespace-nowrap px-4 py-3">{formatPrice(u.completed_spend ?? 0)}</td>
                  <td className="font-medium tabular-nums whitespace-nowrap px-4 py-3">{formatPrice(u.balance)}</td>
                  <td className="px-4 py-3">
                    {u.banned_at ? <span className="badge-rejected whitespace-nowrap">{t('admin.banned')}</span> : <span className="badge-completed whitespace-nowrap">{t('admin.active')}</span>}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-3">
                      <button onClick={() => openBalanceModal(u, 'add')} className="text-xs font-medium text-green-400 hover:text-green-300">{t('admin.addBalance')}</button>
                      <button onClick={() => openBalanceModal(u, 'deduct')} className="text-xs font-medium text-amber-400 hover:text-amber-300">{t('admin.deductBalance')}</button>
                      <button onClick={() => toggleBan(u)} className={`text-xs font-medium ${u.banned_at ? 'text-green-400' : 'text-status-rejected'}`}>{u.banned_at ? t('admin.unban') : t('admin.ban')}</button>
                    </div>
                  </td>
                </tr>
              ))}
              {users.length === 0 && !loading && (
                <tr><td colSpan={7} className="text-center text-gray-500 dark:text-ink-500 py-8">{t('admin.noUsersFound')}</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Mobile View: Cards (Hidden on Desktop) */}
      <div className="grid grid-cols-1 gap-4 md:hidden">
        {users.map((u) => (
          <div key={u.id} className="bg-white dark:bg-ink-50 rounded-2xl p-4 shadow-sm border border-gray-100 dark:border-ink-200">
            <div className="flex justify-between items-start mb-3">
              <div>
                <h3 className="font-bold text-gray-900 dark:text-ink-900 text-lg">{u.name}</h3>
                <p className="text-xs text-gray-500 dark:text-ink-500 break-all" dir="ltr">{u.email}</p>
              </div>
              <div>
                {u.banned_at ? <span className="badge-rejected">{t('admin.banned')}</span> : <span className="badge-completed">{t('admin.active')}</span>}
              </div>
            </div>
            
            <div className="grid grid-cols-2 gap-3 mb-4 bg-gray-50 dark:bg-black/10 p-3 rounded-xl">
              <div>
                <span className="block text-xs text-gray-500 dark:text-ink-500 mb-1">{t('account.balance')}</span>
                <strong className="text-green-500 font-bold">{formatPrice(u.balance)}</strong>
              </div>
              <div>
                <span className="block text-xs text-gray-500 dark:text-ink-500 mb-1">المشتريات</span>
                <strong className="text-gray-700 dark:text-ink-800 font-bold">{formatPrice(u.completed_spend ?? 0)}</strong>
              </div>
            </div>

            <div className="mb-4">
              <label className="block text-xs text-gray-500 dark:text-ink-500 mb-1">{t('admin.vip')}</label>
              <select className="input py-2 text-sm w-full" value={u.vip_override ?? 'auto'} onChange={(e) => changeVip(u, e.target.value)}>
                <option value="auto" className="dark:bg-gray-800 dark:text-white">تلقائي ({(u.vip_level ?? 'none').toUpperCase()})</option>
                <option value="none" className="dark:bg-gray-800 dark:text-white">عادي — إلغاء VIP</option>
                <option value="vip1" className="dark:bg-gray-800 dark:text-white">VIP 1 — يدوي</option>
                <option value="vip2" className="dark:bg-gray-800 dark:text-white">VIP 2 — يدوي</option>
                <option value="vip3" className="dark:bg-gray-800 dark:text-white">VIP 3 — يدوي</option>
                <option value="vip4" className="dark:bg-gray-800 dark:text-white">VIP 4 — تاجر</option>
              </select>
            </div>

            <div className="flex flex-wrap gap-2">
              <button onClick={() => openBalanceModal(u, 'add')} className="flex-1 py-2 bg-green-500/10 text-green-500 rounded-lg text-xs font-bold text-center">+ إضافة</button>
              <button onClick={() => openBalanceModal(u, 'deduct')} className="flex-1 py-2 bg-amber-500/10 text-amber-500 rounded-lg text-xs font-bold text-center">- خصم</button>
              <button onClick={() => toggleBan(u)} className={`flex-1 py-2 rounded-lg text-xs font-bold text-center ${u.banned_at ? 'bg-green-500/10 text-green-500' : 'bg-red-500/10 text-red-500'}`}>{u.banned_at ? 'فك الحظر' : 'حظر'}</button>
            </div>
          </div>
        ))}
        {users.length === 0 && !loading && (
          <div className="text-center text-gray-500 py-8">{t('admin.noUsersFound')}</div>
        )}
      </div>

      {/* Balance Modal */}
      {balanceModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" onClick={closeBalanceModal}>
          <div className="bg-white dark:bg-ink-50 rounded-2xl w-full max-w-md p-5 sm:p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg sm:text-xl font-bold text-gray-900 dark:text-ink-900 mb-4">
              {balanceModal.type === 'add' ? t('admin.addBalance') : t('admin.deductBalance')} — {balanceModal.user.name}
            </h3>
            <div className="space-y-4">
              <div>
                <label className="label text-sm mb-1 block">{t('admin.balanceAmount')}</label>
                <input type="number" step="0.01" min="0.01" className="input w-full" value={amount} onChange={(e) => setAmount(e.target.value)} placeholder="0.00" autoFocus />
              </div>
              <div>
                <label className="label text-sm mb-1 block">{t('admin.balanceNote')}</label>
                <textarea className="input w-full min-h-[80px]" value={note} onChange={(e) => setNote(e.target.value)} placeholder={t('admin.balanceNote')} rows={3} />
              </div>
              <div className="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-2">
                <button type="button" onClick={closeBalanceModal} className="btn-secondary w-full sm:w-auto" disabled={submitting}>{t('admin.cancel')}</button>
                <button type="button" onClick={handleBalanceSubmit} className={`w-full sm:w-auto ${balanceModal.type === 'add' ? 'btn-accent' : 'bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg font-medium'}`} disabled={submitting}>
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