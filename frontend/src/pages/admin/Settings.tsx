import { useEffect, useState } from 'react';
import { adminSettingsApi, settingsApi, adminSyncApi } from '@/api/client';
import toast from 'react-hot-toast';
import Button from '@/components/Button';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';

const GROUPS: Record<string, { labelKey: string; keys: string[] }> = {
  vip: {
    labelKey: 'admin.vipAndMembership',
    keys: [
      'vip1_withdrawal_limit',
      'vip2_withdrawal_limit',
      'vip3_withdrawal_limit',
      'vip1_fee_percent',
      'vip2_fee_percent',
      'vip3_fee_percent',
      'regular_fee_percent',
      'vip1_upgrade_price',
      'vip2_upgrade_price',
      'vip3_upgrade_price',
    ],
  },
  payment: {
    labelKey: 'admin.paymentProviders',
    keys: ['binance_pay_key', 'binance_pay_secret', 'usdt_wallet_address', 'oranos_markup_percent', 'store_markup_percent'],
  },
};

const COMPANY_FIELDS = [
  { key: 'company_name',  labelKey: 'admin.companyName',   type: 'text' },
  { key: 'facebook_url',  labelKey: 'admin.facebookUrl',   type: 'url' },
  { key: 'instagram_url', labelKey: 'admin.instagramUrl',  type: 'url' },
  { key: 'twitter_url',   labelKey: 'admin.twitterUrl',    type: 'url' },
  { key: 'telegram_url',  labelKey: 'admin.telegramUrl',   type: 'url' },
] as const;

const LEGAL_PAGES = [
  { slug: 'terms',   labelKey: 'admin.legalTerms' },
  { slug: 'privacy', labelKey: 'admin.legalPrivacy' },
  { slug: 'refund',  labelKey: 'admin.legalRefund' },
] as const;

export default function AdminSettings() {
  const [settings, setSettings] = useState<Record<string, any>>({});
  const [saving, setSaving] = useState(false);

  const [company, setCompany] = useState<Record<string, string>>({});
  const [companySaving, setCompanySaving] = useState(false);

  const [legalContent, setLegalContent] = useState<Record<string, string>>({});
  const [legalSaving, setLegalSaving] = useState<string | null>(null);

  // legalContent shape: { terms_en: '', terms_ar: '', privacy_en: '', privacy_ar: '', refund_en: '', refund_ar: '' }

  const { t } = useI18n();

  useEffect(() => {
    adminSettingsApi.list()
      .then((r) => setSettings(r.data.settings ?? {}))
      .catch(console.error);
    settingsApi.company()
      .then((r) => setCompany(r.data.settings ?? {}))
      .catch(console.error);
    LEGAL_PAGES.forEach((p) => {
      settingsApi.legal(p.slug, 'ar')
        .then((r) => setLegalContent((prev) => ({ ...prev, [`${p.slug}_ar`]: r.data.content ?? '' })))
        .catch(console.error);
    });
  }, []);

  const updateSetting = async (key: string, value: string) => {
    setSaving(true);
    try {
      await adminSettingsApi.update({ key, value, type: 'string' });
      toast.success(t('admin.updated', { key }));
      const r = await adminSettingsApi.list();
      setSettings(r.data.settings ?? {});
    } catch (err: any) { toast.error(err.response?.data?.message ?? t('common.failed')); }
    finally { setSaving(false); }
  };

  const saveCompany = async () => {
    setCompanySaving(true);
    try {
      await adminSettingsApi.updateCompany(company);
      toast.success(t('admin.companyInfoUpdated'));
    } catch (err: any) { toast.error(err.response?.data?.message ?? t('common.failed')); }
    finally { setCompanySaving(false); }
  };

  const saveLegal = async (slug: string) => {
    setLegalSaving(slug);
    try {
      await adminSettingsApi.updateLegal(slug, {
        content_ar: legalContent[`${slug}_ar`] ?? '',
      });
      toast.success(t('admin.legalPageUpdated'));
    } catch (err: any) { toast.error(err.response?.data?.message ?? t('common.failed')); }
    finally { setLegalSaving(null); }
  };

  const handleApplyMarkup = async () => {
    if (!confirm(t('admin.confirmApplyMarkup'))) return;
    try {
      const r = await adminSyncApi.applyMarkup();
      toast.success((r.data?.output ?? 'done').slice(0, 200));
    } catch (err: any) {
      toast.error(err?.response?.data?.error ?? t('common.failed'));
    }
  };

  const handleSyncProducts = async () => {
    if (!confirm(t('admin.confirmSyncProducts'))) return;
    try {
      const r = await adminSyncApi.syncProducts();
      toast.success((r.data?.output ?? 'done').slice(0, 200));
    } catch (err: any) {
      toast.error(err?.response?.data?.error ?? t('common.failed'));
    }
  };

  return (
    <PageTransition className="space-y-8">
      <div>
        <p className="eyebrow mb-1">{t('admin.system')}</p>
        <h1 className="text-h1 text-gray-900 dark:text-ink-900">{t('admin.settings')}</h1>
      </div>

      {/* ── Existing system settings (VIP / Payment) ── */}
      <div className="space-y-6">
        {Object.entries(GROUPS).map(([group, info]) => (
          <div key={group} className="card-pad">
            <h2 className="text-h3 text-gray-900 dark:text-ink-900 mb-5">{t(info.labelKey)}</h2>
            <div className="space-y-4">
              {info.keys.map((key) => {
                const value = (settings[group] ?? {})[key]?.value ?? '';
                return (
                  <div key={key} className="flex items-start gap-3">
                    <div className="w-48 flex-shrink-0 pt-1">
                      <p className="text-small font-medium text-gray-800 dark:text-ink-800 break-words">{key}</p>
                    </div>
                    <input
                      type="text"
                      className="input flex-1"
                      defaultValue={value}
                      disabled={saving}
                      onBlur={(e) => {
                        if (e.target.value !== value) updateSetting(key, e.target.value);
                      }}
                    />
                  </div>
                );
              })}
            </div>
          </div>
        ))}
      </div>

      {/* ── Oranos Sync ── */}
      <div className="card-pad">
        <h2 className="text-h3 text-gray-900 dark:text-ink-900 mb-5">{t('admin.oranosSync')}</h2>
        <div className="flex flex-wrap gap-3">
          <Button variant="accent" onClick={handleApplyMarkup}>
            {t('admin.applyMarkup')}
          </Button>
          <Button variant="secondary" onClick={handleSyncProducts}>
            {t('admin.syncProductsNow')}
          </Button>
        </div>
        <p className="text-micro text-gray-500 dark:text-ink-500 mt-3">{t('admin.oranosSyncInfo')}</p>
      </div>

      {/* ── Company Info (editable) ── */}
      <div className="card-pad">
        <h2 className="text-h3 text-gray-900 dark:text-ink-900 mb-5">{t('admin.companyInfo')}</h2>
        <div className="space-y-4">
          {COMPANY_FIELDS.map((f) => (
            <div key={f.key} className="grid grid-cols-1 sm:grid-cols-3 gap-2 items-start">
              <label className="text-small font-medium text-gray-800 dark:text-ink-800 pt-2 sm:pt-2.5">
                {t(f.labelKey)}
              </label>
              <input
                type={f.type}
                className="input sm:col-span-2"
                value={company[f.key] ?? ''}
                onChange={(e) => setCompany((p) => ({ ...p, [f.key]: e.target.value }))}
              />
            </div>
          ))}
          <div className="pt-2 flex justify-end">
            <Button variant="accent" onClick={saveCompany} loading={companySaving}>
              {t('admin.saveCompany')}
            </Button>
          </div>
        </div>
      </div>

      {/* ── Legal Pages (editable) ── */}
      <div className="card-pad">
        <h2 className="text-h3 text-gray-900 dark:text-ink-900 mb-5">{t('admin.legalPages')}</h2>
        <div className="space-y-6">
          {LEGAL_PAGES.map((p) => (
            <div key={p.slug} className="space-y-4">
              <label className="text-small font-medium text-gray-800 dark:text-ink-800">
                {t(p.labelKey)}
              </label>
              {/* Arabic */}
              <div className="space-y-1">
                <p className="text-micro text-gray-500 dark:text-ink-500 font-medium" dir="rtl">المحتوى بالعربية</p>
                <textarea
                  className="input min-h-[120px] font-mono text-small text-right"
                  dir="rtl"
                  value={legalContent[`${p.slug}_ar`] ?? ''}
                  onChange={(e) => setLegalContent((prev) => ({ ...prev, [`${p.slug}_ar`]: e.target.value }))}
                  placeholder="أدخل المحتوى بالعربية..."
                />
              </div>
              <div className="flex justify-end">
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => saveLegal(p.slug)}
                  loading={legalSaving === p.slug}
                >
                  {t('admin.saveLegal')}
                </Button>
              </div>
            </div>
          ))}
        </div>
      </div>
    </PageTransition>
  );
}
