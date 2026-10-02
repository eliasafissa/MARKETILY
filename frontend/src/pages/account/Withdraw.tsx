import { useState } from 'react';
import { motion } from 'framer-motion';
import { withdrawalApi } from '@/api/client';
import toast from 'react-hot-toast';
import Button from '@/components/Button';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';
import Breadcrumbs from '@/components/Breadcrumbs';

export default function Withdraw() {
  const { t } = useI18n();
  const [amount, setAmount] = useState('');
  const [wallet, setWallet] = useState('');
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await withdrawalApi.create({ amount: parseFloat(amount), wallet_address: wallet, method: 'usdt' });
      setSuccess(true);
      toast.success(t('withdraw.success'));
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? t('withdraw.failed'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-xl mx-auto w-full flex flex-col items-center">
      <PageTransition className="w-full">
        <Breadcrumbs
          items={[
            { label: t('nav.home'), link: '/' },
            { label: t('nav.dashboard'), link: '/dashboard' },
            { label: t('withdraw.title') },
          ]}
        />
        <h1 className="text-h1 text-gray-900 dark:text-ink-900 mb-8 text-center mt-4">{t('withdraw.title')}</h1>

        <motion.div
          className="card-pad space-y-6"
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <div className="bg-status-pending/10 border border-status-pending/20 rounded-xl p-4">
            <p className="text-micro text-gray-600 dark:text-ink-500 uppercase tracking-wide mb-1">{t('withdraw.notice')}</p>
            <p className="text-small text-gray-600 dark:text-ink-600">
              {t('withdraw.noticeText')}
            </p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <label className="label">{t('withdraw.amount')}</label>
              <div className="relative">
                <span className="absolute start-3 top-1/2 -translate-y-1/2 text-gray-600 dark:text-ink-500 text-sm">$</span>
                <input
                  type="number"
                  min="1"
                  step="0.01"
                  required
                  className="input ps-7"
                  placeholder="50.00"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                />
              </div>
            </div>

            <div>
              <label className="label">{t('withdraw.walletAddress')}</label>
              <input
                type="text"
                required
                className="input"
                placeholder="0x…"
                value={wallet}
                onChange={(e) => setWallet(e.target.value)}
              />
            </div>

            <Button type="submit" variant="accent" size="lg" loading={loading} className="w-full">
              {t('withdraw.submit')}
            </Button>
          </form>
        </motion.div>
      </PageTransition>
    </div>
  );
}
