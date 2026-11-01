import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { vipApi } from '@/api/client';
import PageTransition from '@/components/PageTransition';
import Breadcrumbs from '@/components/Breadcrumbs';
import { useI18n } from '@/i18n';
import { formatPrice } from '@/utils/format';

const TIERS = [
  { key: 'none', name: 'Regular', threshold: 0, desc: 'المستوى الافتراضي' },
  { key: 'vip1', name: 'VIP 1', threshold: 1000, desc: 'مشتريات مكتملة من $1,000' },
  { key: 'vip2', name: 'VIP 2', threshold: 2000, desc: 'مشتريات مكتملة من $2,000' },
  { key: 'vip3', name: 'VIP 3', threshold: 5000, desc: 'مشتريات مكتملة من $5,000' },
  { key: 'vip4', name: 'VIP 4', threshold: null, desc: 'للتجار — يمنحه الأدمن فقط' },
] as const;

export default function VipPage() {
  const { t } = useI18n();
  const [vip, setVip] = useState<any>(null);
  const [error, setError] = useState(false);

  useEffect(() => {
    vipApi.status().then(r => setVip(r.data)).catch(() => setError(true));
  }, []);

  const spend = Number(vip?.completed_spend ?? 0);
  const next = vip?.level === 'vip4' || vip?.next_threshold === null ? null : Number(vip?.next_threshold ?? 1000);
  const progress = next === null ? 100 : Math.min(100, spend / next * 100);

  return (
    <PageTransition className="max-w-5xl mx-auto w-full space-y-7">
      <Breadcrumbs items={[{ label: t('nav.home'), link: '/' }, { label: t('nav.dashboard'), link: '/dashboard' }, { label: 'VIP' }]} />
      <div className="card-pad space-y-3">
        <p className="eyebrow">MARKETLY REWARDS</p>
        <h1 className="text-h1 text-gray-900 dark:text-ink-900">نظام عضوية VIP</h1>
        {error ? <p className="text-status-rejected">تعذر تحميل بيانات العضوية</p> : !vip ? <p className="text-gray-500">جارٍ تحميل العضوية...</p> : (
          <>
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div><p className="text-small text-gray-500">مستواك الحالي</p><p className="text-h3 text-green-500">{vip.label}</p></div>
              <div className="text-end"><p className="text-small text-gray-500">إجمالي المشتريات المكتملة</p><p className="text-h3 tabular-nums">{formatPrice(spend)}</p></div>
              <div className="text-end"><p className="text-small text-gray-500">خصم عضويتك</p><p className="text-h3 tabular-nums">{vip.discount_percent}%</p></div>
            </div>
            {next !== null && <div className="space-y-2">
              <div className="flex justify-between text-small text-gray-600 dark:text-ink-600"><span>المتبقي للمستوى التالي</span><strong>{formatPrice(vip.remaining ?? 0)}</strong></div>
              <div className="h-2.5 rounded-full bg-gray-200 dark:bg-ink-200 overflow-hidden"><div className="bg-green-500 h-full rounded-full transition-all" style={{ width: `${progress}%` }} /></div>
            </div>}
            {vip.manual_override !== null && <p className="text-small text-green-500">العضوية محددة يدوياً من الإدارة.</p>}
          </>
        )}
      </div>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        {TIERS.map((tier, idx) => {
          const current = tier.key === vip?.level;
          return <motion.div key={tier.key} initial={{ opacity: 0, y: 14 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: idx * .05 }} className={`card-pad space-y-3 ${current ? 'border-green-500 bg-green-500/5' : ''}`}>
            <div className="flex justify-between items-center"><span className="text-green-500 text-xl">✦</span>{current && <span className="badge-vip">الحالية</span>}</div>
            <h2 className="text-h3 text-gray-900 dark:text-ink-900">{tier.name}</h2>
            <p className="text-small text-gray-500 min-h-[44px]">{tier.desc}</p>
            <div className="border-t border-gray-200 dark:border-ink-200 pt-3"><p className="text-micro text-gray-500">خصم المنتجات</p><strong className="text-green-500 text-lg">{tier.key === 'none' ? '0' : (vip?.discounts?.[tier.key] ?? 0)}%</strong></div>
          </motion.div>;
        })}
      </div>
      <p className="text-small text-gray-500 dark:text-ink-500 text-center">تُحتسب المشتريات المكتملة فقط؛ لا يمكن شراء VIP مباشرة. خصم Oranos لا يقلل السعر عن تكلفة المزوّد.</p>
    </PageTransition>
  );
}
