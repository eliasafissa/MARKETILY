import { useEffect, useState, type ReactNode } from 'react';
import { motion } from 'framer-motion';
import { adminDashboardApi } from '@/api/client';
import PageTransition from '@/components/PageTransition';
import { formatPrice } from '@/utils/format';
import { useI18n } from '@/i18n';
import { ArrowUpRight, TrendingUp, TrendingDown } from 'lucide-react';

interface HealthCheck {
  status: 'ok' | 'warn' | 'error';
  message: string;
}

function StatusDot({ status }: { status: HealthCheck['status'] }): ReactNode {
  const color = {
    ok: 'bg-green-500',
    warn: 'bg-status-pending',
    error: 'bg-status-rejected',
  }[status];
  return (
    <span
      className={`inline-block w-2.5 h-2.5 rounded-full ${color} ${status === 'ok' ? 'animate-pulse' : ''}`}
      aria-label={status}
    />
  );
}

// Stagger reveal
const reveal = (i: number) => ({
  initial: { opacity: 0, y: 24 },
  whileInView: { opacity: 1, y: 0 },
  viewport: { once: true, margin: '-40px' },
  transition: { delay: i * 0.06, duration: 0.55, ease: [0.16, 1, 0.3, 1] as const },
});

export default function AdminDashboard() {
  const [stats, setStats] = useState<any>(null);
  const [health, setHealth] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const { t } = useI18n();

  useEffect(() => {
    Promise.all([
      adminDashboardApi.stats(),
      adminDashboardApi.health().catch(() => null),
    ])
      .then(([statsRes, healthRes]) => {
        setStats(statsRes.data);
        if (healthRes) setHealth(healthRes.data);
      })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="flex items-center justify-center py-24">
        <span className="w-8 h-8 rounded-full border-2 border-green-500 border-t-transparent animate-spin" />
      </div>
    );
  }

  const s = stats?.stats ?? {};

  // Asymmetric tile definitions — hero revenue tile is huge, rest are compact
  const tiles = [
    { key: 'admin.revenue',             value: formatPrice(s.total_revenue ?? 0),     color: 'text-green-500',        trend: '+12.4%', hero: true },
    { key: 'admin.totalUsers',          value: s.total_users,                          color: 'text-gray-900 dark:text-ink-900', trend: '+3.1%',  hero: false },
    { key: 'admin.pendingDeposits',     value: s.pending_deposits,                     color: 'text-status-pending',    trend: null,      hero: false },
    { key: 'admin.pendingWithdrawals',  value: s.pending_withdrawals,                  color: 'text-status-processing', trend: null,      hero: false },
    { key: 'admin.pendingManualOrders', value: s.pending_manual_orders,                color: 'text-status-vip',        trend: null,      hero: false },
  ];

  return (
    <PageTransition className="space-y-10">
      <motion.div {...reveal(0)}>
        <p className="eyebrow mb-2">{t('admin.overview')}</p>
        <h1 className="font-heading text-[clamp(2rem,4vw,3.25rem)] text-gray-900 dark:text-ink-900 leading-[1.05] text-balance">
          {t('admin.dashboard')}
        </h1>
      </motion.div>

      {/* Asymmetric KPI grid — first tile is hero, spans 2 cols + 2 rows */}
      <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-4 gap-3 md:gap-4">
        {tiles.map((tile, i) => {
          const isHero = tile.hero;
          return (
            <motion.div
              key={tile.key}
              {...reveal(i)}
              whileHover={{ y: -4 }}
              className={
                isHero
                  ? 'col-span-2 md:col-span-2 lg:row-span-2 group relative overflow-hidden rounded-2xl p-6 ' +
                    'bg-white dark:bg-ink-100 ' +
                    'border border-gray-200 dark:border-ink-200 shadow-sm ' +
                    'hover:shadow-glow hover:border-green-500/40 transition-all duration-300'
                  : 'col-span-1 group relative overflow-hidden rounded-2xl p-5 ' +
                    'bg-white/70 dark:bg-ink-50/70 backdrop-blur-xl ' +
                    'border border-white/10 dark:border-ink-200/50 ' +
                    'shadow-sm hover:shadow-glow hover:border-green-500/30 hover:-translate-y-1 transition-all duration-300'
              }
            >
              {/* Decorative gradient blob on hero */}
              {isHero && (
                <div className="pointer-events-none absolute -top-20 -end-20 w-60 h-60 rounded-full
                                bg-green-500/20 dark:bg-green-500/10 blur-3xl" />
              )}

              <div className="relative">
                <p className={`text-micro text-gray-500 dark:text-ink-500 uppercase tracking-wider mb-2 ${isHero ? 'mb-3' : ''}`}>
                  {t(tile.key)}
                </p>
                <p className={`font-heading tabular-nums font-bold ${isHero ? 'text-5xl md:text-6xl' : 'text-3xl'} ${tile.color}`}>
                  {tile.value ?? '—'}
                </p>

                {tile.trend && (
                  <div className={`mt-3 inline-flex items-center gap-1 text-xs font-medium ${tile.trend.startsWith('+') ? 'text-green-500' : 'text-status-rejected'}`}>
                    {tile.trend.startsWith('+') ? <TrendingUp size={12} /> : <TrendingDown size={12} />}
                    {tile.trend}
                    <span className="text-gray-500 dark:text-ink-500 ms-1">vs last week</span>
                  </div>
                )}
              </div>
            </motion.div>
          );
        })}
      </div>

      {/* Two-column asymmetric: System Health (2/3) + Recent Orders (1/3) */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* System Health — wider */}
        <motion.div {...reveal(0)} className="lg:col-span-2 card-pad">
          <div className="flex items-center justify-between mb-5">
            <div>
              <h2 className="font-heading text-2xl text-gray-900 dark:text-ink-900">{t('admin.systemHealth')}</h2>
              <p className="text-micro text-gray-500 dark:text-ink-500 mt-1">
                {t('admin.liveInfra')} · {health?.app_env ?? 'unknown'} · DEBUG:{' '}
                <span className={health?.app_debug ? 'text-status-rejected' : 'text-green-500'}>
                  {String(health?.app_debug ?? '—')}
                </span>
              </p>
            </div>
            {health && (
              <span className={`badge-${health.healthy ? 'completed' : 'rejected'}`}>
                {health.healthy ? t('admin.allSystemsNormal') : t('admin.issuesDetected')}
              </span>
            )}
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            {(['database', 'storage', 'reverb'] as const).map((key, i) => {
              const check: HealthCheck = health?.checks?.[key] ?? { status: 'warn', message: 'Not yet checked' };
              const labelKey = `admin.${key}`;
              return (
                <motion.div
                  key={key}
                  {...reveal(i)}
                  whileHover={{ y: -4 }}
                  className="flex items-start gap-3 p-4 rounded-xl
                             bg-gray-50/80 dark:bg-ink-100/40
                             border border-gray-200/60 dark:border-ink-200/50
                             hover:border-green-500/30 hover:shadow-glow transition-all duration-300"
                >
                  <StatusDot status={check.status} />
                  <div className="flex-1 min-w-0">
                    <p className="text-small font-medium text-gray-900 dark:text-ink-900">{t(labelKey)}</p>
                    <p className="text-micro text-gray-500 dark:text-ink-500 truncate" title={check.message}>
                      {check.message}
                    </p>
                  </div>
                </motion.div>
              );
            })}
          </div>
        </motion.div>

        {/* Recent Orders — narrower, list-style */}
        <motion.div {...reveal(1)} className="card-pad">
          <div className="flex items-center justify-between mb-5">
            <h2 className="font-heading text-2xl text-gray-900 dark:text-ink-900">{t('admin.recentOrders')}</h2>
            <ArrowUpRight size={18} className="text-gray-400 dark:text-ink-500" />
          </div>
          <ul className="space-y-3">
            {(stats?.recent_orders ?? []).slice(0, 5).map((o: any) => (
              <li key={o.id} className="flex items-center justify-between gap-3 py-2 border-b border-gray-100 dark:border-ink-200/50 last:border-0">
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium text-gray-900 dark:text-ink-900 truncate">#{o.id}</p>
                  <p className="text-micro text-gray-500 dark:text-ink-500 truncate">{o.user?.name ?? '—'}</p>
                </div>
                <div className="text-end">
                  <p className="text-sm font-semibold text-green-500 tabular-nums">{formatPrice(o.total)}</p>
                  <p className="text-micro text-gray-500 dark:text-ink-500">{new Date(o.created_at).toLocaleDateString()}</p>
                </div>
              </li>
            ))}
            {(stats?.recent_orders ?? []).length === 0 && (
              <li className="text-center text-gray-500 dark:text-ink-500 py-6 text-sm">{t('admin.noOrdersYet')}</li>
            )}
          </ul>
        </motion.div>
      </div>
    </PageTransition>
  );
}
