import { useEffect, useMemo, useState } from 'react';
import { Filter, Search, RefreshCw, CheckCircle2, XCircle, Image as ImageIcon, ChevronLeft, ChevronRight, CalendarDays } from 'lucide-react';
import { adminDepositApi } from '@/api/client';
import toast from 'react-hot-toast';
import PageTransition from '@/components/PageTransition';

type DepositRow = {
  id: number; user_id?: number; user?: { name?: string; email?: string };
  amount: number | string; method?: string; payment_reference?: string | null;
  status: string; created_at: string; rejection_reason?: string | null;
  meta?: { sender_account?: string; sender_name?: string; receipt_path?: string;
    transfer_amount?: number; transfer_currency?: string; network?: string; stage?: string;
    submitted_at?: string; verified_at?: string; verification_note?: string };
};
const methods: Record<string, string> = {
  sham_cash: 'شام كاش (ل.س)', sham_cash_usd: 'شام كاش (USD)', syriatel_cash: 'سيرياتيل كاش', c_wallet: 'سي والت',
  binance_pay: 'باينانس باي', usdt: 'يو إس دي تي — شبكة BEP20',
};
const statuses: Record<string, string> = {
  pending: 'بانتظار المراجعة', approved: 'تمت الموافقة', rejected: 'مرفوض',
  completed: 'مكتمل', processing: 'قيد المعالجة', cancelled: 'ملغى',
};
const statusClass = (status: string) => status === 'approved' || status === 'completed'
  ? 'hs-state hs-state-ok' : status === 'rejected' || status === 'cancelled'
    ? 'hs-state hs-state-no' : 'hs-state hs-state-pending';
const usdValue = (value: number | string) => '$' + Number(value).toLocaleString('en-US', { maximumFractionDigits: 2, minimumFractionDigits: 2 });
const dateValue = (date?: string) => date ? new Date(date).toLocaleString('ar-SY', { dateStyle: 'medium', timeStyle: 'short' }) : '—';

export default function AdminDeposits() {
  const [rows, setRows] = useState<DepositRow[]>([]);
  const [query, setQuery] = useState('');
  const [method, setMethod] = useState('');
  const [status, setStatus] = useState('');
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [page, setPage] = useState(1);
  const [pages, setPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [busy, setBusy] = useState(false);
  const [refreshKey, setRefreshKey] = useState(0);
  const [selected, setSelected] = useState<DepositRow | null>(null);
  const [processing, setProcessing] = useState(false);
  const [rejectReason, setRejectReason] = useState('');
  const [action, setAction] = useState<'approve' | 'reject'>('approve');
  const [receiptModalUrl, setReceiptModalUrl] = useState<string | null>(null);

  const filters = useMemo(() => ({
    ...(query.trim() ? { search: query.trim() } : {}),
    ...(method ? { method } : {}), ...(status ? { status } : {}),
    ...(fromDate ? { date_from: fromDate } : {}), ...(toDate ? { date_to: toDate } : {}),
    page: String(page), per_page: '25',
  }), [query, method, status, fromDate, toDate, page]);

  useEffect(() => {
    let alive = true; setBusy(true);
    const timer = window.setTimeout(() => {
      adminDepositApi.list(filters).then(({ data }) => {
        if (!alive) return;
        setRows(data.data ?? []); setPages(Math.max(1, Number(data.last_page ?? 1))); setTotal(Number(data.total ?? 0));
      }).catch(() => { if (alive) toast.error('تعذر تحميل سجل الإيداعات'); }).finally(() => { if (alive) setBusy(false); });
    }, 250);
    return () => { alive = false; window.clearTimeout(timer); };
  }, [filters, refreshKey]);

  useEffect(() => {
    const timer = window.setInterval(() => { if (!document.hidden) setRefreshKey(value => value + 1); }, 25000);
    return () => window.clearInterval(timer);
  }, []);

  function changeFilter(setter: (value: string) => void, value: string) { setter(value); setPage(1); }
  function reset() { setQuery(''); setMethod(''); setStatus(''); setFromDate(''); setToDate(''); setPage(1); }
  function openAction(deposit: DepositRow, kind: 'approve' | 'reject') { setSelected(deposit); setAction(kind); setRejectReason(''); }

  async function viewReceipt(id: number) {
    try {
      const { data } = await adminDepositApi.receipt(id);
      setReceiptModalUrl(URL.createObjectURL(data));
    } catch { toast.error('تعذّر فتح صورة الإيصال'); }
  }

  async function saveAction() {
    if (!selected || processing) return;
    setProcessing(true);
    try {
      if (action === 'approve') {
        await adminDepositApi.approve(selected.id);
        toast.success('تمت الموافقة على الإيداع بعد التحقق');
      } else {
        if (rejectReason.trim().length < 3) { toast.error('اكتب سبب الرفض'); return; }
        await adminDepositApi.reject(selected.id, rejectReason.trim());
        toast.success('تم رفض الإيداع وتسجيل السبب');
      }
      setSelected(null); setRefreshKey(x => x + 1);
    } catch (error: any) { toast.error(error?.response?.data?.message ?? 'تعذّر حفظ القرار'); }
    finally { setProcessing(false); }
  }

  return <PageTransition className="hs-finance-page space-y-7" >
    <section className="hs-page-head flex flex-col sm:flex-row sm:items-center justify-between gap-4" dir="rtl">
      <div><span className="hs-kicker">الإدارة المالية</span><h1 className="mt-1">سجل الإيداعات</h1>
        <p className="mt-1">جميع الإيداعات محفوظة في السجل، مع بيانات المرسل والإيصالات وحالات المراجعة.</p></div>
      <button type="button" className="hs-btn hs-btn-neutral w-full sm:w-auto" onClick={() => setRefreshKey(x => x + 1)}>
        <RefreshCw size={16}/> تحديث السجل
      </button>
    </section>

    <section className="hs-filter-panel space-y-4" dir="rtl">
      <div className="hs-filter-head"><Filter size={17}/><strong>البحث والفرز</strong><span>تُطبق الفلاتر على كامل السجل، وليس الصفحة الحالية فقط</span></div>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <label className="hs-field md:col-span-2 lg:col-span-1"><span>بحث شامل</span><div className="hs-field-icon"><Search size={17}/>
          <input type="search" value={query} onChange={e => changeFilter(setQuery, e.target.value)} placeholder="رقم العملية، مرجع التحويل، المرسل، العميل" /></div></label>
        <label className="hs-field"><span>طريقة الدفع</span><select className="dark:bg-gray-800 dark:text-white" value={method} onChange={e => changeFilter(setMethod, e.target.value)}>
          <option value="">جميع الطرق</option>{Object.entries(methods).map(([value,label]) => <option key={value} value={value}>{label}</option>)}</select></label>
        <label className="hs-field"><span>حالة الإيداع</span><select className="dark:bg-gray-800 dark:text-white" value={status} onChange={e => changeFilter(setStatus, e.target.value)}>
          <option value="">جميع الحالات</option>{Object.entries(statuses).filter(([v]) => ['pending','approved','rejected'].includes(v)).map(([v,l]) => <option key={v} value={v}>{l}</option>)}</select></label>
        <label className="hs-field"><span>من تاريخ</span><div className="hs-field-icon"><CalendarDays size={16}/><input type="date" value={fromDate} onChange={e => changeFilter(setFromDate, e.target.value)}/></div></label>
        <label className="hs-field"><span>إلى تاريخ</span><div className="hs-field-icon"><CalendarDays size={16}/><input type="date" value={toDate} min={fromDate || undefined} onChange={e => changeFilter(setToDate, e.target.value)}/></div></label>
      </div>
      <div className="hs-filter-foot flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 pt-2"><span>{busy ? 'جاري التحميل…' : `${total.toLocaleString('ar-SY')} عملية مطابقة`}</span>
        <button type="button" className="hs-text-btn" onClick={reset}>مسح خيارات البحث</button></div>
    </section>

    {/* Desktop Table View */}
    <section className="hs-table-card hidden md:block" dir="rtl">
      <div className="hs-table-scroll overflow-x-auto"><table className="hs-admin-table min-w-full text-sm"><thead><tr>
        <th className="whitespace-nowrap px-3 py-3">الرقم</th><th className="whitespace-nowrap px-3 py-3">العميل</th><th className="whitespace-nowrap px-3 py-3">طريقة الدفع</th><th className="whitespace-nowrap px-3 py-3">مبلغ التحويل الفعلي</th>
        <th className="whitespace-nowrap px-3 py-3">المرسل ومرجع التحويل</th><th className="whitespace-nowrap px-3 py-3">الحالة</th><th className="whitespace-nowrap px-3 py-3">تاريخ الطلب</th><th className="whitespace-nowrap px-3 py-3">الإيصال والإجراء</th>
      </tr></thead><tbody>{rows.map(d => <tr key={d.id}>
        <td className="hs-id-cell px-3 py-3 whitespace-nowrap">#{d.id}</td><td className="px-3 py-3 min-w-[150px]"><strong>{d.user?.name || `مستخدم ${d.user_id ?? '—'}`}</strong><br/><small dir="ltr">{d.user?.email ?? ''}</small></td>
        <td className="px-3 py-3 whitespace-nowrap">{methods[d.method ?? ''] ?? d.method ?? '—'}</td>
        <td className="px-3 py-3 whitespace-nowrap"><strong dir="ltr">{Number(d.meta?.transfer_amount ?? d.amount).toLocaleString('en-US')} {d.meta?.transfer_currency === 'SYP' ? 'ل.س' : (d.meta?.transfer_currency ?? 'USD')}</strong><br/><small>{usdValue(d.amount)} رصيد</small></td>
        <td className="px-3 py-3 min-w-[150px]"><span className="hs-cell-content">{d.meta?.sender_name || d.meta?.sender_account || 'لم يُقدّم بعد'}</span><br/><small dir="ltr">{d.payment_reference || 'مرجع غير مقدّم'}</small></td>
        <td className="px-3 py-3 whitespace-nowrap"><span className={statusClass(d.status)}>{statuses[d.status] ?? 'غير محدد'}</span></td>
        <td className="px-3 py-3 whitespace-nowrap"><time dateTime={d.created_at}>{dateValue(d.created_at)}</time></td>
        <td className="px-3 py-3">
          <div className="hs-actions flex gap-2">
            {d.meta?.receipt_path && <button type="button" className="hs-icon-btn" onClick={() => viewReceipt(d.id)}><ImageIcon size={16}/><span>الإيصال</span></button>}
            {d.status === 'pending' && d.meta?.stage === 'awaiting_admin' && <>
              <button type="button" className="hs-icon-btn hs-approve" onClick={() => openAction(d,'approve')}><CheckCircle2 size={16}/> موافقة</button>
              <button type="button" className="hs-icon-btn hs-reject" onClick={() => openAction(d,'reject')}><XCircle size={16}/> رفض</button>
            </>}
          </div>
        </td>
      </tr>)}{!busy && rows.length === 0 && <tr><td colSpan={8} className="hs-empty text-center py-8">لا توجد إيداعات تطابق خيارات البحث.</td></tr>}</tbody></table></div>
    </section>

    {/* Mobile Card View */}
    <div className="grid grid-cols-1 gap-4 md:hidden" dir="rtl">
      {rows.map(d => (
        <div key={d.id} className="bg-white dark:bg-ink-50 rounded-2xl p-4 shadow-sm border border-gray-100 dark:border-ink-200">
          <div className="flex justify-between items-start mb-3">
            <div>
              <span className="text-xs text-gray-500 block">إيداع #{d.id}</span>
              <strong className="text-gray-900 dark:text-white text-sm">{d.user?.name || 'مستخدم'}</strong>
            </div>
            <span className={statusClass(d.status)}>{statuses[d.status] ?? 'غير محدد'}</span>
          </div>
          <div className="bg-gray-50 dark:bg-black/10 p-3 rounded-xl mb-3 space-y-2">
            <div className="flex justify-between">
              <span className="text-xs text-gray-500">المبلغ:</span>
              <strong className="text-green-500 text-sm" dir="ltr">{Number(d.meta?.transfer_amount ?? d.amount).toLocaleString('en-US')} {d.meta?.transfer_currency === 'SYP' ? 'ل.س' : 'USD'}</strong>
            </div>
            <div className="flex justify-between">
              <span className="text-xs text-gray-500">الطريقة:</span>
              <span className="text-xs font-medium">{methods[d.method ?? ''] ?? d.method}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-xs text-gray-500">المرجع:</span>
              <span className="text-xs font-mono truncate max-w-[150px]" dir="ltr">{d.payment_reference || 'غير مقدم'}</span>
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            {d.meta?.receipt_path && <button type="button" className="flex-1 py-2 bg-gray-100 dark:bg-white/10 rounded-lg text-xs font-bold justify-center flex items-center gap-1" onClick={() => viewReceipt(d.id)}><ImageIcon size={14}/> الإيصال</button>}
            {d.status === 'pending' && d.meta?.stage === 'awaiting_admin' && <>
              <button type="button" className="flex-1 py-2 bg-green-500/10 text-green-500 rounded-lg text-xs font-bold justify-center flex items-center gap-1" onClick={() => openAction(d,'approve')}><CheckCircle2 size={14}/> موافقة</button>
              <button type="button" className="flex-1 py-2 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-lg text-xs font-bold justify-center flex items-center gap-1" onClick={() => openAction(d,'reject')}><XCircle size={14}/> رفض</button>
            </>}
          </div>
        </div>
      ))}
      {!busy && rows.length === 0 && <div className="text-center text-gray-500 py-8">لا توجد إيداعات.</div>}
    </div>

    {/* Pagination (Shared) */}
    <div className="flex flex-col sm:flex-row justify-between items-center gap-3 p-4 bg-white dark:bg-ink-50 rounded-2xl border border-[var(--border)]" dir="rtl">
      <span className="text-sm text-gray-500">صفحة {page} من {pages}</span>
      <div className="flex gap-2 w-full sm:w-auto">
        <button className="flex-1 sm:flex-none px-4 py-2 rounded-lg border border-gray-200 dark:border-ink-200 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 disabled:opacity-50" disabled={page <= 1 || busy} onClick={() => setPage(p => Math.max(1,p-1))}>السابق</button>
        <button className="flex-1 sm:flex-none px-4 py-2 rounded-lg border border-gray-200 dark:border-ink-200 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 disabled:opacity-50" disabled={page >= pages || busy} onClick={() => setPage(p => p+1)}>التالي</button>
      </div>
    </div>

    {/* Action Modal */}
    {selected && <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" role="presentation" onMouseDown={e => { if (e.target === e.currentTarget && !processing) setSelected(null); }}>
      <section className="bg-white dark:bg-ink-50 rounded-2xl w-full max-w-lg p-5 sm:p-6 shadow-xl overflow-y-auto max-h-[90vh]" dir="rtl">
        <h2 className="text-xl font-bold mb-4">{action === 'approve' ? 'التحقق من الإيداع' : 'رفض الإيداع'} <span>#{selected.id}</span></h2>
        <p className="text-sm mb-4">العميل: {selected.user?.name ?? selected.user_id} — مبلغ التحويل: {Number(selected.meta?.transfer_amount ?? selected.amount).toLocaleString('en-US')} {selected.meta?.transfer_currency === 'SYP' ? 'ل.س' : 'USD'}</p>
        {selected.meta?.receipt_path && <button className="w-full mb-4 py-2 bg-gray-100 dark:bg-white/10 rounded-lg text-sm font-bold flex justify-center items-center gap-2" type="button" onClick={() => viewReceipt(selected.id)}><ImageIcon size={17}/> عرض إثبات التحويل</button>}
        {action === 'approve' ? <p className="text-sm text-gray-500 leading-relaxed mb-6">تأكد من وصول الحوالة والمبلغ في حساب الاستلام قبل الموافقة. سيُضاف الرصيد للعميل فور التأكيد.</p> : <label className="block mb-6"><span className="block mb-2 text-sm">سبب الرفض</span><textarea className="w-full p-3 rounded-xl border border-gray-200 dark:border-ink-200 bg-transparent" value={rejectReason} onChange={e => setRejectReason(e.target.value)} rows={3} placeholder="أوضح سبب الرفض للعميل"/></label>}
        <div className="flex flex-col-reverse sm:flex-row justify-end gap-3">
          <button className="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-gray-200 dark:border-ink-200 font-medium" type="button" onClick={() => setSelected(null)} disabled={processing}>إلغاء</button>
          <button className={`w-full sm:w-auto px-5 py-2.5 rounded-xl text-white font-medium ${action === 'approve' ? 'bg-green-500 hover:bg-green-600' : 'bg-red-500 hover:bg-red-600'}`} type="button" disabled={processing} onClick={saveAction}>{processing ? 'جاري الحفظ…' : action === 'approve' ? 'تأكيد الموافقة' : 'تأكيد الرفض'}</button>
        </div>
      </section>
    </div>}

    {/* Receipt Modal */}
    {receiptModalUrl && (
      <div className="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/80" onClick={() => setReceiptModalUrl(null)}>
        <div className="relative max-w-4xl w-full max-h-[90vh] bg-white dark:bg-gray-900 rounded-xl overflow-hidden shadow-2xl flex flex-col" onClick={e => e.stopPropagation()}>
          <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-600" dir="rtl">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white">إيصال التحويل</h3>
            <button onClick={() => setReceiptModalUrl(null)} className="p-2 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-500/20 font-bold">إغلاق ✕</button>
          </div>
          <div className="flex-1 overflow-auto p-4 flex items-center justify-center bg-gray-100 dark:bg-gray-950" dir="ltr">
            <img src={receiptModalUrl} alt="Receipt" className="max-w-full max-h-[70vh] object-contain" />
          </div>
        </div>
      </div>
    )}
  </PageTransition>;
}