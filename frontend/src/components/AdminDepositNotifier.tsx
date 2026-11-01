import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { adminDepositApi, paymentMethodsApi } from '@/api/client';

type AlertItem = { id: number; amount: string; method: string; submitted_at: string };
type Receiver = { id: string; label: string; receiver: string | null; other_receivers?: string[]; network?: string | null };
const METHOD: Record<string, string> = {
  sham_cash: 'شام كاش (ل.س)', sham_cash_usd: 'شام كاش (USD)', syriatel_cash: 'سيرياتيل كاش', c_wallet: 'C Wallet',
  binance_pay: 'Binance Pay', usdt: 'USDT BEP-20',
};

/** Admin-only widget, mounted across ALL admin routes. Does not grant credit or auto-approve. */
export default function AdminDepositNotifier() {
  const [items, setItems] = useState<AlertItem[]>([]);
  const [pending, setPending] = useState(0);
  const [open, setOpen] = useState(false);
  const [showReceivers, setShowReceivers] = useState(false);
  const [receivers, setReceivers] = useState<Receiver[]>([]);
  const [soundOn, setSoundOn] = useState(false);
  const [error, setError] = useState('');
  const seen = useRef<Set<number> | null>(null);
  const soundEnabled = useRef(false);
  const audio = useRef<AudioContext | null>(null);

  const beep = () => {
    if (!soundEnabled.current || !audio.current) return;
    const ctx = audio.current;
    void ctx.resume().then(() => {
      for (const [index, frequency] of [740, 990].entries()) {
        const oscillator = ctx.createOscillator();
        const volume = ctx.createGain();
        const start = ctx.currentTime + index * 0.19;
        oscillator.type = 'sine';
        oscillator.frequency.value = frequency;
        volume.gain.setValueAtTime(0.0001, start);
        volume.gain.exponentialRampToValueAtTime(0.12, start + 0.025);
        volume.gain.exponentialRampToValueAtTime(0.0001, start + 0.16);
        oscillator.connect(volume); volume.connect(ctx.destination);
        oscillator.start(start); oscillator.stop(start + 0.17);
      }
    }).catch(() => { /* browser may block background audio */ });
  };

  useEffect(() => {
    let active = true;
    const poll = async () => {
      try {
        const { data } = await adminDepositApi.alerts();
        if (!active) return;
        const newItems: AlertItem[] = Array.isArray(data.items) ? data.items : [];
        const nowIds = new Set<number>(newItems.map(item => item.id));
        if (seen.current !== null) {
          const newArrival = newItems.find(item => !seen.current?.has(item.id));
          if (newArrival) {
            beep();
            if ('Notification' in window && Notification.permission === 'granted') {
              try {
                const notification = new Notification('Marketlly: طلب إيداع جديد', {
                  body: `#${newArrival.id} · ${METHOD[newArrival.method] ?? newArrival.method} · ${newArrival.amount} USD`,
                  tag: `haidar-deposit-${newArrival.id}`,
                });
                notification.onclick = () => { window.focus(); notification.close(); };
              } catch { /* unsupported mobile browser: Telegram is the background fallback */ }
            }
          }
        }
        seen.current = nowIds;
        setItems(newItems);
        setPending(Number(data.pending_count ?? 0));
        setError('');
      } catch {
        if (active) setError('تعذّر تحديث طلبات الإيداع؛ راجع اتصال السيرفر.');
      }
    };
    void poll();
    const interval = window.setInterval(() => void poll(), 15000);
    return () => { active = false; window.clearInterval(interval); };
  }, []);

  const enableAlerts = async () => {
    try {
      audio.current ??= new AudioContext();
      await audio.current.resume();
      soundEnabled.current = true;
      setSoundOn(true);
      beep(); // test tone, only after explicit user gesture
      if ('Notification' in window && Notification.permission === 'default') {
        try { await Notification.requestPermission(); } catch { /* notifications are optional */ }
      }
    } catch { setError('الصوت محجوب من المتصفح؛ افتح الصفحة واضغط تفعيل الصوت.'); }
  };

  const viewReceivers = async () => {
    setShowReceivers(v => !v);
    if (receivers.length) return;
    try {
      const { data } = await paymentMethodsApi.list();
      setReceivers(Array.isArray(data.methods) ? data.methods : []);
    } catch { setError('تعذّر جلب حسابات الاستقبال من إعدادات السيرفر.'); }
  };

  return (
    <div className="fixed top-3 right-16 z-[80]" dir="rtl">
      {open && <section className="absolute top-12 right-0 w-[min(90vw,370px)] rounded-2xl border border-gray-200 dark:border-ink-200 bg-white dark:bg-ink-50 p-4 shadow-2xl max-h-[70vh] overflow-auto" aria-label="تنبيهات الإيداع">
        <div className="flex items-center justify-between gap-2 mb-3">
          <h2 className="font-bold text-gray-900 dark:text-ink-900">🔔 إيداعات بانتظار التدقيق</h2>
          <button type="button" onClick={() => setOpen(false)} className="btn-ghost btn-sm" aria-label="إغلاق">✕</button>
        </div>
        <p className="text-sm mb-3 text-gray-600 dark:text-ink-600">{pending} طلب بإثبات تحويل ينتظر مراجعة الأدمن. لا يُضاف رصيد قبل المطابقة.</p>
        {items.slice(0, 8).map(item => (
          <div key={item.id} className="flex justify-between gap-2 border-b border-gray-200 dark:border-ink-200 py-2 text-sm">
            <span>#{item.id} · {METHOD[item.method] ?? item.method}</span>
            <strong dir="ltr">${item.amount}</strong>
          </div>
        ))}
        <Link to="/admin/deposits" onClick={() => setOpen(false)} className="btn-accent mt-3 block text-center">فتح الإيداعات والإيصالات</Link>
        <div className="mt-3 flex gap-2 flex-wrap">
          <button type="button" onClick={() => void enableAlerts()} className="btn-ghost btn-sm">{soundOn ? '🔊 الصوت مفعّل' : '🔔 تفعيل الصوت'}</button>
          <button type="button" onClick={() => void viewReceivers()} className="btn-ghost btn-sm">📋 حسابات الاستقبال</button>
        </div>
        {showReceivers && <div className="mt-3 rounded-lg bg-gray-100 dark:bg-ink-100 p-3 text-xs space-y-2">
          <p className="font-bold">طابقها مع بيانات صاحب المتجر قبل استقبال أي تحويل.</p>
          {receivers.map(m => <div key={m.id} className="border-b border-gray-300 dark:border-ink-200 pb-2">
            <strong>{METHOD[m.id] ?? m.label}</strong> {m.network ? `(${m.network})` : ''}
            <p className="font-mono break-all" dir="ltr">{m.receiver ?? 'غير مضبوط'}</p>
            {m.other_receivers?.map(x => <p className="font-mono break-all" dir="ltr" key={x}>{x}</p>)}
          </div>)}
        </div>}
        {error && <p role="alert" className="text-red-500 text-xs mt-2">{error}</p>}
        <p className="text-xs text-gray-500 mt-2">صوت المتصفح أثناء فتح الموقع. للموبايل والموقع مغلق استخدم تنبيهات Telegram الاختيارية.</p>
      </section>}
      <button type="button" onClick={() => setOpen(v => !v)} className="relative h-10 w-10 rounded-full bg-gray-100 dark:bg-ink-100 text-gray-900 dark:text-ink-900 border border-gray-200 dark:border-ink-200 shadow-md flex items-center justify-center" aria-label="عرض طلبات الإيداع الجديدة">
        <span aria-hidden="true">🔔</span>
        {pending > 0 && <span className="absolute -top-1 -right-1 rounded-full bg-red-600 text-white px-1.5 text-[10px]">{pending}</span>}
      </button>
    </div>
  );
}
