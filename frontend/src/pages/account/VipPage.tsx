import { useEffect, useState, type ReactNode } from 'react';
import { motion } from 'framer-motion';
import { vipApi } from '@/api/client';
import { useAppSelector } from '@/store';
import toast from 'react-hot-toast';
import Button from '@/components/Button';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';
import Breadcrumbs from '@/components/Breadcrumbs';

const TIER_ICONS: Record<string, ReactNode> = {
  none: (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
      <circle cx="12" cy="12" r="9" />
    </svg>
  ),
  vip1: (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
    </svg>
  ),
  vip2: (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
      <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
      <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" transform="scale(0.55) translate(6,6)" />
    </svg>
  ),
  vip3: (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
      <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
      <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" transform="scale(0.55) translate(6,6)" />
    </svg>
  ),
};

export default function VipPage() {
  const user = useAppSelector((s) => s.auth.user);
  const { t } = useI18n();
  const [vip, setVip] = useState<any>(null);
  const [upgrading, setUpgrading] = useState<string | null>(null);

  useEffect(() => {
    vipApi.status().then((r) => setVip(r.data)).catch(console.error);
  }, []);

  const handleUpgrade = async (target: string) => {
    if (
      !confirm(
        `Upgrade to ${target.toUpperCase()}? This will deduct $${vip?.upgrade_prices?.[target]} from your balance.`,
      )
    )
      return;
    setUpgrading(target);
    try {
      await vipApi.upgrade(target);
      toast.success(`Upgraded to ${target.toUpperCase()}!`);
      const res = await vipApi.status();
      setVip(res.data);
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? 'Upgrade failed');
    } finally {
      setUpgrading(null);
    }
  };

  const tiers = [
    {
      key: 'none',
      label: 'Regular',
      limit: '$0',
      fee: '5%',
      price: null,
      color: 'ink-600',
      icon: TIER_ICONS.none,
    },
    {
      key: 'vip1',
      label: 'VIP 1',
      limit: `$${vip?.vip1_limit ?? 1000}`,
      fee: '3%',
      price: vip?.upgrade_prices?.vip1,
      color: 'status-vip',
      icon: TIER_ICONS.vip1,
    },
    {
      key: 'vip2',
      label: 'VIP 2',
      limit: `$${vip?.vip2_limit ?? 5000}`,
      fee: '1.5%',
      price: vip?.upgrade_prices?.vip2,
      color: 'green-400',
      icon: TIER_ICONS.vip2,
    },
    {
      key: 'vip3',
      label: t('vip.vip3'),
      limit: `$${vip?.vip3_limit ?? 10000}`,
      fee: '0.5%',
      price: vip?.upgrade_prices?.vip3,
      color: 'purple-400',
      icon: TIER_ICONS.vip3,
    },
  ];

  const currentIndex = tiers.findIndex((t) => t.key === (user as any)?.vip_level);

  return (
    <PageTransition className="max-w-4xl mx-auto w-full space-y-8">
      <Breadcrumbs
        items={[
          { label: t('nav.home'), link: '/' },
          { label: t('nav.dashboard'), link: '/dashboard' },
          { label: t('vip.title') },
        ]}
      />
      <div className="mt-4">
        <h1 className="text-h1 text-gray-900 dark:text-ink-900 text-center">{t('vip.title')}</h1>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {tiers.map((tier, idx) => {
          const isCurrent = tier.key === (user as any)?.vip_level;
          const canUpgrade = idx > currentIndex;
          return (
            <motion.div
              key={tier.key}
              className={`card-pad text-center relative ${
                isCurrent ? 'border-green-500/50 bg-green-500/5' : ''
              }`}
              initial={{ opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: idx * 0.1, duration: 0.35 }}
            >
              {isCurrent && (
                <span className="absolute top-4 end-4 badge-vip">{t('vip.current')}</span>
              )}

              <span className={`inline-block mb-4 ${isCurrent ? 'text-green-400' : 'text-gray-600 dark:text-ink-500'}`}>
                {tier.icon}
              </span>

              <h3 className={`text-h3 mb-1 ${
                tier.key === 'vip3' ? 'text-purple-400' :
                tier.key === 'vip2' ? 'text-green-400' :
                tier.key === 'vip1' ? 'text-status-vip' : 'text-gray-800 dark:text-ink-800'
              }`}>
                {tier.label}
              </h3>

              <div className="space-y-2 text-small text-gray-600 dark:text-ink-600 mt-4 mb-6">
                <div className="flex justify-between">
                  <span>{t('vip.limits')}</span>
                  <strong className="text-gray-800 dark:text-ink-800">{tier.limit}</strong>
                </div>
                <div className="flex justify-between">
                  <span>{t('vip.fee')}</span>
                  <strong className="text-gray-800 dark:text-ink-800">{tier.fee}</strong>
                </div>
                {tier.price && (
                  <div className="flex justify-between">
                    <span>{t('vip.price')}</span>
                    <strong className="text-green-400">${tier.price}</strong>
                  </div>
                )}
              </div>

              {canUpgrade && tier.price > 0 ? (
                <Button
                  variant="accent"
                  className="w-full"
                  loading={upgrading === tier.key}
                  onClick={() => handleUpgrade(tier.key)}
                >
                  {t('vip.upgrade')}
                </Button>
              ) : isCurrent ? (
                <p className="text-small text-green-400">✓ {t('vip.current')}</p>
              ) : null}
            </motion.div>
          );
        })}
      </div>
    </PageTransition>
  );
}
