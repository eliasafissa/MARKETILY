import { useEffect, useState } from 'react';
import { CalendarDays, Mail, Phone, Search, Globe2, RefreshCcw } from 'lucide-react';
import api from '@/api/client';
import toast from 'react-hot-toast';
import PageTransition from '@/components/PageTransition';

type Lead={id:number;name:string;email:string;phone:string;details:string;status:string;created_at:string};
export default function WebsiteLeads(){
  const [items,setItems]=useState<Lead[]>([]),[search,setSearch]=useState(''),[start,setStart]=useState(''),[end,setEnd]=useState(''),[page,setPage]=useState(1),[pages,setPages]=useState(1),[busy,setBusy]=useState(false),[issue,setIssue]=useState(''),[reload,setReload]=useState(0);
  useEffect(()=>{let live=true;setBusy(true);api.get('/admin/website-leads',{params:{search,date_from:start,date_to:end,page}}).then(({data})=>{if(!live)return;setItems(data.data??[]);setPages(Math.max(1,Number(data.last_page??1)));setIssue('');}).catch(()=>{if(live)setIssue('تعذّر تحميل طلبات إنشاء المواقع.');}).finally(()=>{if(live)setBusy(false);});return()=>{live=false;};},[search,start,end,page,reload]);
  return <PageTransition className="mx-auto max-w-7xl space-y-6 p-4 sm:p-7" >
    <main dir="rtl" className="space-y-6 text-gray-900 dark:text-white">
      <header className="flex flex-wrap items-center justify-between gap-4"><div><div className="flex items-center gap-2 text-sm text-green-500"><Globe2 size={18}/> طلبات الخدمات</div><h1 className="mt-2 text-2xl sm:text-3xl font-bold">طلبات إنشاء المواقع</h1><p className="mt-2 text-sm text-gray-500">الطلبات الواردة من نموذج إنشاء الموقع محفوظة هنا للمتابعة.</p></div><button className="inline-flex items-center gap-2 rounded-xl border border-white/10 px-4 py-3" onClick={()=>setReload(v=>v+1)}><RefreshCcw size={16}/> تحديث</button></header>
      <div className="grid gap-3 rounded-2xl border border-gray-200 p-4 dark:border-white/10 dark:bg-[#171b20] md:grid-cols-3">
        <label className="flex min-w-0 items-center gap-2"><Search size={18}/><input className="w-full rounded-xl border border-gray-300 bg-transparent p-3 dark:border-white/10" placeholder="بحث بالاسم أو البريد أو التفاصيل" value={search} onChange={e=>{setSearch(e.target.value);setPage(1);}}/></label>
        <label className="flex items-center gap-2"><CalendarDays size={18}/>من <input type="date" className="min-w-0 flex-1 rounded-xl border border-gray-300 bg-[#13171c] p-3 text-white dark:border-white/10" value={start} onChange={e=>{setStart(e.target.value);setPage(1);}}/></label>
        <label className="flex items-center gap-2"><CalendarDays size={18}/>إلى <input type="date" className="min-w-0 flex-1 rounded-xl border border-gray-300 bg-[#13171c] p-3 text-white dark:border-white/10" value={end} onChange={e=>{setEnd(e.target.value);setPage(1);}}/></label>
      </div>
      {issue && <p role="alert" className="rounded-xl bg-red-500/10 p-4 text-red-500">{issue}</p>}
      <section className="grid gap-4 md:grid-cols-2">{items.map(item=><article key={item.id} className="min-w-0 space-y-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-[#171b20]">
        <header className="flex items-start justify-between gap-2"><div><h2 className="font-bold">{item.name||'طلب إنشاء موقع'}</h2><p className="text-sm text-gray-500">طلب رقم {item.id}</p></div><time className="text-xs text-gray-500">{new Date(item.created_at).toLocaleString('ar-SY')}</time></header>
        {item.email&&<p className="flex gap-2 break-all text-sm"><Mail size={16}/>{item.email}</p>}{item.phone&&<p className="flex gap-2 text-sm"><Phone size={16}/>{item.phone}</p>}
        <div className="flex items-center gap-2"><label className="text-xs text-gray-500">الحالة</label><select aria-label="حالة طلب إنشاء الموقع" className="min-w-0 flex-1 rounded-lg border border-white/15 bg-[#13171c] px-3 py-2 text-white" value={item.status} onChange={async e=>{const value=e.target.value;try{await api.patch(`/admin/website-leads/${item.id}`,{status:value});setItems(xs=>xs.map(x=>x.id===item.id?{...x,status:value}:x));toast.success('تم تحديث الحالة');}catch{toast.error('تعذّر تحديث الحالة');}}}><option value="new">جديد</option><option value="contacted">تم التواصل</option><option value="in_progress">قيد التنفيذ</option><option value="completed">مكتمل</option><option value="rejected">مرفوض</option></select></div>
        <dl className="space-y-2 border-t border-gray-200 pt-3 text-sm dark:border-white/10">{Object.entries((()=>{try {return JSON.parse(item.details) as Record<string,unknown>}catch{return {details:item.details}}})()).map(([key,value])=><div key={key} className="grid grid-cols-[minmax(90px,1fr)_minmax(0,2fr)] gap-3"><dt className="text-gray-500 break-all">{key}</dt><dd className="break-words">{String(value)}</dd></div>)}</dl>
      </article>)}{!busy&&!items.length&&<div className="rounded-xl border border-white/10 p-8 text-center text-gray-500">لا توجد طلبات مطابقة.</div>}</section>
      <footer className="flex items-center justify-between"><span>صفحة {page} من {pages}</span><div className="flex gap-2"><button disabled={page<=1} onClick={()=>setPage(p=>p-1)} className="rounded-xl border border-white/10 px-4 py-2 disabled:opacity-40">السابق</button><button disabled={page>=pages} onClick={()=>setPage(p=>p+1)} className="rounded-xl border border-white/10 px-4 py-2 disabled:opacity-40">التالي</button></div></footer>
    </main>
  </PageTransition>;
}
