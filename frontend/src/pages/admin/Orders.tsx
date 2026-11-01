import { Fragment, useEffect, useMemo, useState } from 'react';
import { CalendarDays, ChevronDown, ChevronLeft, ChevronRight, ChevronUp, Package, RefreshCw, Search, SlidersHorizontal } from 'lucide-react';
import { adminOrderApi } from '@/api/client';
import PageTransition from '@/components/PageTransition';
import toast from 'react-hot-toast';
import { formatPrice } from '@/utils/format';

type Product = { id?: number; name?: string; name_ar?: string; oranos_product_id?: string | number | null };
type Item = { id: number; product_id?: number; product?: Product | null; quantity?: number; unit_price?: number | string;
  payload?: unknown; oranos_order_id?: string | number | null; oranos_order_uuid?: string | null; status?: string; };
type OrderRow = { id: number; user?: { name?: string; email?: string }; user_id?: number; total?: number | string;
  status: string; created_at: string; payment_method?: string; payment_ref?: string | null; failure_reason?: string | null;
  items?: Item[]; notes?: string | null; };

const statuses: Record<string,string> = { 
  pending: 'بانتظار التنفيذ', processing: 'قيد التنفيذ', completed: 'مكتمل', rejected: 'مرفوض', cancelled: 'ملغى', failed: 'فشل' 
};
const badge = (status: string) => status === 'completed' ? 'hs-state hs-state-ok' : ['rejected','cancelled','failed'].includes(status) ? 'hs-state hs-state-no' : 'hs-state hs-state-pending';

function displayPayload(data: unknown): string {
  if (!data) return '—';
  if (typeof data === 'string') return data.slice(0, 1000);
  try { return JSON.stringify(data, null, 2).slice(0, 1000); } catch { return '—'; }
}

export default function AdminOrders() {
  const [orders, setOrders] = useState<OrderRow[]>([]);
  const [search, setSearch] = useState(''); const [status, setStatus] = useState('');
  const [fromDate,setFromDate] = useState(''); const [toDate,setToDate] = useState('');
  const [page,setPage] = useState(1); const [pages,setPages] = useState(1); const [total,setTotal] = useState(0);
  const [openIds,setOpenIds] = useState<number[]>([]); const [loading,setLoading] = useState(false); const [version,setVersion] = useState(0);
  
  const filters = useMemo(() => ({ page: String(page), per_page: '25',
    ...(search.trim() ? { search: search.trim() } : {}), ...(status ? { status } : {}),
    ...(fromDate ? { date_from: fromDate } : {}), ...(toDate ? { date_to: toDate } : {}),
  }), [page,search,status,fromDate,toDate]);

  useEffect(() => {
    let alive=true; setLoading(true);
    const timer=window.setTimeout(() => adminOrderApi.list(filters).then(({data})=>{
      if (!alive)return;
      setOrders(data.data ?? []); setPages(Math.max(1,Number(data.last_page ?? 1))); setTotal(Number(data.total ?? 0));
    }).catch(()=>{ if (alive)toast.error('تعذر تحميل سجل الطلبات'); })
      .finally(()=>{ if (alive)setLoading(false); }),250);
    return ()=>{ alive=false;window.clearTimeout(timer); };
  }, [filters,version]);

  function updateSearch(setter:(s:string)=>void,v:string){setter(v);setPage(1);}
  function toggle(id:number){setOpenIds(curr=>curr.includes(id)?curr.filter(x=>x!==id):[...curr,id]);}

  return <PageTransition className="hs-finance-page space-y-7" >
    <section className="hs-page-head flex flex-col sm:flex-row sm:items-center justify-between gap-4" dir="rtl">
      <div><span className="hs-kicker">إدارة المبيعات</span><h1 className="mt-1">جميع الطلبات</h1>
        <p className="mt-1">تفاصيل المنتج والعميل والتاريخ وحالة كل عملية شراء.</p></div>
      <button type="button" className="hs-btn hs-btn-neutral w-full sm:w-auto" onClick={()=>setVersion(v=>v+1)}><RefreshCw size={16}/> تحديث السجل</button>
    </section>

    <section className="hs-filter-panel space-y-4" dir="rtl">
      <div className="hs-filter-head"><SlidersHorizontal size={17}/><strong>البحث في الطلبات</strong><span>استخدم اسم العميل أو المنتج أو رقم الطلب</span></div>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <label className="hs-field md:col-span-2 lg:col-span-1"><span>بحث شامل</span><div className="hs-field-icon"><Search size={17}/>
          <input type="search" value={search} onChange={e=>updateSearch(setSearch,e.target.value)} placeholder="اسم المنتج، العميل، رقم الطلب…"/></div></label>
        <label className="hs-field"><span>حالة الطلب</span><select value={status} onChange={e=>updateSearch(setStatus,e.target.value)}>
          <option value="">جميع الحالات</option>{Object.entries(statuses).map(([v,l])=><option key={v} value={v}>{l}</option>)}</select></label>
        <label className="hs-field"><span>من تاريخ</span><div className="hs-field-icon"><CalendarDays size={16}/><input type="date" value={fromDate} onChange={e=>updateSearch(setFromDate,e.target.value)}/></div></label>
        <label className="hs-field"><span>إلى تاريخ</span><div className="hs-field-icon"><CalendarDays size={16}/><input type="date" min={fromDate||undefined} value={toDate} onChange={e=>updateSearch(setToDate,e.target.value)}/></div></label>
      </div>
      <div className="hs-filter-foot flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 pt-2"><span>{loading?'جاري التحميل…':`${total.toLocaleString('ar-SY')} طلب مطابق`}</span>
        <button className="hs-text-btn" onClick={()=>{setSearch('');setStatus('');setFromDate('');setToDate('');setPage(1);}}>مسح خيارات البحث</button></div>
    </section>

    {/* Desktop Table View */}
    <section className="hs-table-card hidden md:block" dir="rtl"><div className="hs-table-scroll overflow-x-auto"><table className="hs-admin-table min-w-full text-sm">
      <thead><tr><th className="whitespace-nowrap px-3 py-3">رقم الطلب</th><th className="whitespace-nowrap px-3 py-3">العميل</th><th className="whitespace-nowrap px-3 py-3">المنتج / الخدمة</th><th className="whitespace-nowrap px-3 py-3">قيمة الطلب</th><th className="whitespace-nowrap px-3 py-3">الحالة</th><th className="whitespace-nowrap px-3 py-3">التاريخ</th><th className="whitespace-nowrap px-3 py-3">التفاصيل</th></tr></thead>
      <tbody>{orders.map(order=><Fragment key={order.id}><tr className="hs-order-main-row">
        <td className="hs-id-cell px-3 py-3 whitespace-nowrap">#{order.id}</td>
        <td className="px-3 py-3 min-w-[150px]"><strong>{order.user?.name||`مستخدم ${order.user_id??'—'}`}</strong><br/><small dir="ltr">{order.user?.email??''}</small></td>
        <td className="px-3 py-3 min-w-[200px]"><div className="flex flex-col gap-1">{(order.items??[]).length ? (order.items??[]).slice(0,3).map(item=><div key={item.id} className="flex items-center gap-2 text-sm"><Package size={14} className="shrink-0 text-gray-400"/><span className="truncate">{item.product?.name_ar || item.product?.name || `منتج #${item.product_id??'—'}`}</span><small className="text-xs text-gray-500 shrink-0">×{item.quantity??1}</small></div>) : <span className="text-sm text-gray-500">لا تتوفر تفاصيل</span>}{(order.items??[]).length>3&&<small className="text-xs text-gray-500 mt-1">و {order.items!.length-3} منتجات إضافية</small>}</div></td>
        <td className="px-3 py-3 whitespace-nowrap"><strong className="hs-money">{formatPrice(order.total)}</strong></td>
        <td className="px-3 py-3 whitespace-nowrap"><span className={badge(order.status)}>{statuses[order.status]??'غير محدد'}</span></td>
        <td className="px-3 py-3 whitespace-nowrap"><time dateTime={order.created_at}>{new Date(order.created_at).toLocaleString('ar-SY',{dateStyle:'medium',timeStyle:'short'})}</time></td>
        <td className="px-3 py-3"><button type="button" className="hs-icon-btn" onClick={()=>toggle(order.id)}>{openIds.includes(order.id)?<ChevronUp size={16}/>:<ChevronDown size={16}/>} عرض</button></td>
      </tr>{openIds.includes(order.id) && <tr className="hs-detail-row bg-black/5 dark:bg-white/5"><td colSpan={7} className="p-4 sm:p-6"><div className="hs-order-detail space-y-4">
        <h3 className="text-lg font-bold text-gray-900 dark:text-white">تفاصيل الطلب #{order.id}</h3><p className="text-sm text-gray-600 dark:text-gray-400">العميل: {order.user?.name||'—'} — طريقة الدفع: {order.payment_method==='cash_wallet'?'رصيد المحفظة':order.payment_method==='partner_api'?'واجهة الشركاء':'—'}</p>
        <div className="space-y-4">{(order.items??[]).map(item=><div className="bg-white dark:bg-black/20 p-4 rounded-xl border border-gray-200 dark:border-gray-800 flex flex-col gap-2" key={item.id}>
          <strong className="text-md">{item.product?.name_ar||item.product?.name||`منتج #${item.product_id}`}</strong>
          <div className="flex flex-wrap gap-x-4 gap-y-2 text-sm text-gray-600 dark:text-gray-400"><span>الكمية: {item.quantity??'—'}</span><span>سعر الوحدة: {formatPrice(item.unit_price)}</span><span>معرّف Oranos: {item.oranos_order_id??item.oranos_order_uuid??'بانتظار التأكيد'}</span></div>
          <pre className="mt-2 p-3 bg-black/5 dark:bg-black/40 rounded-lg overflow-x-auto text-xs text-left text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700" dir="ltr">{displayPayload(item.payload)}</pre>
        </div>)}</div>{order.failure_reason && <p className="text-sm text-red-500 bg-red-500/10 p-3 rounded-lg">سبب التعثر: {order.failure_reason}</p>}
      </div></td></tr>}</Fragment>)}
      {!loading&&orders.length===0&&<tr><td className="hs-empty text-center py-8" colSpan={7}>ما في طلبات.</td></tr>}</tbody>
    </table></div></section>

    {/* Mobile Card View */}
    <div className="grid grid-cols-1 gap-4 md:hidden" dir="rtl">
      {orders.map(order => (
        <div key={order.id} className="bg-white dark:bg-ink-50 rounded-2xl p-4 shadow-sm border border-gray-100 dark:border-ink-200">
          <div className="flex justify-between items-start mb-3">
            <div>
              <span className="text-xs text-gray-500 block">طلب #{order.id}</span>
              <strong className="text-gray-900 dark:text-white text-sm">{order.user?.name || 'مستخدم'}</strong>
            </div>
            <span className={badge(order.status)}>{statuses[order.status] ?? 'غير محدد'}</span>
          </div>
          <div className="bg-gray-50 dark:bg-black/10 p-3 rounded-xl mb-3 space-y-2">
            <div className="flex justify-between items-center">
              <span className="text-xs text-gray-500">القيمة:</span>
              <strong className="text-green-500 text-sm">{formatPrice(order.total)}</strong>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-xs text-gray-500">التاريخ:</span>
              <span className="text-xs">{new Date(order.created_at).toLocaleDateString('ar-SY')}</span>
            </div>
            <div className="flex flex-col gap-1 mt-2 border-t border-gray-200 dark:border-gray-700 pt-2">
              <span className="text-xs text-gray-500">المنتجات:</span>
              {(order.items ?? []).slice(0, 2).map(item => (
                <div key={item.id} className="text-xs font-medium text-gray-700 dark:text-gray-300 truncate">
                  • {item.product?.name_ar || item.product?.name || `منتج #${item.product_id}`} (×{item.quantity})
                </div>
              ))}
              {(order.items ?? []).length > 2 && <span className="text-xs text-gray-500">+ منتجات أخرى...</span>}
            </div>
          </div>
          <button type="button" className="w-full py-2 bg-gray-100 dark:bg-white/10 rounded-lg text-xs font-bold justify-center flex items-center gap-1" onClick={() => toggle(order.id)}>
            {openIds.includes(order.id) ? <ChevronUp size={14}/> : <ChevronDown size={14}/>} {openIds.includes(order.id) ? 'إخفاء التفاصيل' : 'عرض التفاصيل'}
          </button>
          
          {openIds.includes(order.id) && (
            <div className="mt-4 p-3 bg-gray-50 dark:bg-black/20 rounded-xl space-y-3 border border-gray-100 dark:border-gray-800">
              <h4 className="text-sm font-bold border-b border-gray-200 dark:border-gray-700 pb-2">تفاصيل الطلب</h4>
              <p className="text-xs text-gray-600 dark:text-gray-400">الدفع: {order.payment_method === 'cash_wallet' ? 'رصيد' : 'شركاء'}</p>
              {(order.items ?? []).map(item => (
                <div key={item.id} className="bg-white dark:bg-black/40 p-3 rounded-lg border border-gray-100 dark:border-gray-700">
                  <strong className="text-xs block mb-1">{item.product?.name_ar || item.product?.name}</strong>
                  <div className="text-[10px] text-gray-500 mb-2">الكمية: {item.quantity} | السعر: {formatPrice(item.unit_price)}</div>
                  <pre className="p-2 bg-gray-100 dark:bg-black/50 rounded overflow-x-auto text-[10px] text-left" dir="ltr">{displayPayload(item.payload)}</pre>
                </div>
              ))}
            </div>
          )}
        </div>
      ))}
      {!loading && orders.length === 0 && <div className="text-center text-gray-500 py-8">لا توجد طلبات.</div>}
    </div>

    {/* Pagination (Shared) */}
    <div className="flex flex-col sm:flex-row justify-between items-center gap-3 p-4 bg-white dark:bg-ink-50 rounded-2xl border border-[var(--border)]" dir="rtl">
      <span className="text-sm text-gray-500">صفحة {page} من {pages}</span>
      <div className="flex gap-2 w-full sm:w-auto">
        <button className="flex-1 sm:flex-none px-4 py-2 rounded-lg border border-gray-200 dark:border-ink-200 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 disabled:opacity-50" disabled={page<=1||loading} onClick={()=>setPage(x=>Math.max(1,x-1))}>السابق</button>
        <button className="flex-1 sm:flex-none px-4 py-2 rounded-lg border border-gray-200 dark:border-ink-200 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 disabled:opacity-50" disabled={page>=pages||loading} onClick={()=>setPage(x=>x+1)}>التالي</button>
      </div>
    </div>
  </PageTransition>;
}