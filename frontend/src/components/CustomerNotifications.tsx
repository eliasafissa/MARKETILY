import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Link, useLocation } from 'react-router-dom';
import { accountNotificationsApi } from '@/api/client';
import { useAppSelector } from '@/hooks/useAuth';
import { X } from 'lucide-react';

type Notice = { id: string; title: string; detail?: string; created_at: string; link: string; status: string };
export default function CustomerNotifications() {
  const authenticated = useAppSelector(s => s.auth.isAuthenticated);
  const userId = useAppSelector(s => s.auth.user?.id);
  const dismissedKey = `hs-notifications-hidden-${userId ?? 'guest'}`;
  const location = useLocation();
  const [items, setItems] = useState<Notice[]>([]);
  const [open, setOpen] = useState(false);
  const [seen, setSeen] = useState<string[]>([]);
  const [dismissed, setDismissed] = useState<string[]>([]);
  useEffect(() => {
    try { setDismissed(JSON.parse(localStorage.getItem(dismissedKey) || '[]')); }
    catch { setDismissed([]); }
    setSeen([]);
  }, [dismissedKey]);
  function hideNotice(id: string) {
    const next = [...new Set([...dismissed, id])].slice(-250);
    setDismissed(next);
    try { localStorage.setItem(dismissedKey, JSON.stringify(next)); } catch { /* private mode */ }
  }
  useEffect(() => {
    if (!authenticated || location.pathname.startsWith('/admin')) return;
    let live = true;
    const load = () => accountNotificationsApi.list().then(r => { if (live) setItems(r.data.notifications ?? []); }).catch(() => {});
    load(); const timer = setInterval(load, 30000);
    return () => { live = false; clearInterval(timer); };
  }, [authenticated, location.pathname]);
  useEffect(() => {
    if (!open) return;
    const onKey = (event: KeyboardEvent) => { if (event.key === 'Escape') setOpen(false); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open]);
  useEffect(() => { setOpen(false); }, [location.pathname]);
  if (!authenticated || location.pathname.startsWith('/admin')) return null;
  const visible = items.filter(i => !dismissed.includes(i.id));
  const unread = visible.filter(i => !seen.includes(i.id)).length;
  return <div dir="rtl" className="relative z-50">
    <button type="button" aria-label="إشعارات حسابي" aria-expanded={open} onClick={() => { setOpen(v => !v); setSeen(visible.map(i => i.id)); }} className="relative inline-flex items-center justify-center rounded-lg border border-emerald-500/20 bg-[#111a19] p-2 text-emerald-300 hover:bg-emerald-500/10">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
      {unread > 0 && <span className="absolute -top-1 -left-1 min-w-5 rounded-full bg-green-600 px-1 text-center text-xs text-white">{Math.min(unread, 99)}</span>}
    </button>
    {open && createPortal(<>
      <button type="button" aria-label="إغلاق لوحة الإشعارات" onClick={() => setOpen(false)} className="fixed inset-0 z-[9998] cursor-default bg-black/30" />
      <section role="dialog" aria-modal="true" aria-label="إشعارات الحساب" className="fixed left-3 right-3 top-[4.5rem] z-[9999] max-h-[min(75dvh,620px)] overflow-y-auto overscroll-contain rounded-xl border border-emerald-500/20 bg-[#141b1e] text-white shadow-2xl sm:left-auto sm:right-5 sm:w-[min(90vw,380px)]">
      <header className="flex items-center justify-between border-b border-white/10 p-4">
        <strong className="text-sm text-emerald-300">إشعارات الحساب</strong>
        <button type="button" aria-label="إغلاق الإشعارات" title="إغلاق" onClick={() => setOpen(false)} className="rounded-lg p-1.5 text-white/65 hover:bg-white/10 hover:text-white"><X size={17}/></button>
      </header>
      {visible.length === 0 ? <p className="p-4 text-sm text-white/55">لا توجد إشعارات جديدة</p> : visible.map(i => <div key={i.id} className={`flex items-start gap-2 border-b border-white/10 px-3 py-3 ${i.status === 'rejected' || i.status === 'failed' ? 'border-r-2 border-r-rose-500/70' : i.status === 'approved' || i.status === 'completed' ? 'border-r-2 border-r-emerald-500/70' : 'border-r-2 border-r-sky-500/70'}`}>
        <Link to={i.link} onClick={() => setOpen(false)} className="min-w-0 flex-1 rounded-lg p-1 hover:bg-white/5">
          <span className="block text-sm font-medium text-white">{i.title}</span>
          {i.detail && <span className="block break-words text-xs text-white/55">{i.detail}</span>}
          {i.created_at && <time className="mt-1 block text-xs text-white/40">{new Date(i.created_at).toLocaleString('ar')}</time>}
        </Link>
        <button type="button" aria-label={`إخفاء إشعار: ${i.title}`} title="إخفاء هذا الإشعار" onClick={() => hideNotice(i.id)} className="shrink-0 rounded-lg p-1.5 text-white/50 hover:bg-emerald-500/10 hover:text-emerald-300"><X size={16}/></button>
      </div>)}
      </section>
    </>, document.body)}
  </div>;
}
