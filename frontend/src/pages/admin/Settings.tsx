import { useEffect, useState } from 'react';
import { adminSettingsApi, settingsApi, adminSyncApi, currencyApi } from '@/api/client';
import toast from 'react-hot-toast';
import Button from '@/components/Button';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';

const GROUPS: Record<string, { labelKey: string; keys: string[] }> = {
  vip: { labelKey: 'admin.vipAndMembership', keys: ['vip1_discount_percent', 'vip2_discount_percent', 'vip3_discount_percent', 'vip4_discount_percent'] },
  payment: { labelKey: 'admin.paymentProviders', keys: ['oranos_markup_percent'] },
};

const FRIENDLY_LABELS: Record<string, string> = {
  oranos_markup_percent: 'نسبة ربح Oranos العامة (%)',
  vip1_discount_percent: 'خصم VIP 1 (%)',
  vip2_discount_percent: 'خصم VIP 2 (%)',
  vip3_discount_percent: 'خصم VIP 3 (%)',
  vip4_discount_percent: 'خصم VIP 4 - التجار (%)',
};

const COMPANY_FIELDS = [
  { key: 'company_name', labelKey: 'admin.companyName', type: 'text' },
  { key: 'facebook_url', labelKey: 'admin.facebookUrl', type: 'url' },
  { key: 'instagram_url', labelKey: 'admin.instagramUrl', type: 'url' },
  { key: 'twitter_url', labelKey: 'admin.twitterUrl', type: 'url' },
  { key: 'telegram_url', labelKey: 'admin.telegramUrl', type: 'url' },
  { key: 'whatsapp_url', labelKey: 'admin.whatsappUrl', type: 'text' },
] as const;

const DEPOSIT_RECEIVERS = [
  { key: 'sham_cash_receiver', label: 'رقم شام كاش (ل.س)' },
  { key: 'sham_cash_usd_receiver', label: 'رقم شام كاش (USD)' },
  { key: 'syriatel_cash_receiver', label: 'رقم سيرياتيل كاش' },
  { key: 'c_wallet_receiver', label: 'رقم سيرياني (cWallet)' },
  { key: 'binance_pay_receiver', label: 'رقم حساب Binance Pay' },
  { key: 'usdt_receiver', label: 'عنوان محفظة USDT (BEP20)' },
];

const LEGAL_PAGES = [
  { slug: 'terms', labelKey: 'admin.legalTerms' },
  { slug: 'privacy', labelKey: 'admin.legalPrivacy' },
  { slug: 'refund', labelKey: 'admin.legalRefund' },
] as const;

export default function AdminSettings() {
  const [settings, setSettings] = useState<Record<string, any>>({});
  const [saving, setSaving] = useState(false);
  
  const [exchangeRate, setExchangeRate] = useState('');
  const [syriatelRate, setSyriatelRate] = useState('');
  const [rateSaving, setRateSaving] = useState(false);

  const [walletValues, setWalletValues] = useState<Record<string, string>>({});
  const [walletsSaving, setWalletsSaving] = useState(false);

  const [company, setCompany] = useState<Record<string, string>>({});
  const [companySaving, setCompanySaving] = useState(false);
  const [legalContent, setLegalContent] = useState<Record<string, string>>({});
  const [legalSaving, setLegalSaving] = useState<string | null>(null);

  const { t } = useI18n();

  useEffect(() => {
    currencyApi.rate().then(({ data }) => setExchangeRate(data.syp_per_usd ? String(data.syp_per_usd) : '')).catch(() => {});
    adminSettingsApi.list().then((r) => {
        const all = r.data.settings ?? {};
        setSettings(all);
        setSyriatelRate(String(all.payment?.payment_syriatel_syp_per_usd?.value ?? ''));
        
        const initialWallets: Record<string, string> = {};
        DEPOSIT_RECEIVERS.forEach(f => {
            initialWallets[f.key] = all.general?.[f.key]?.value ?? '';
        });
        setWalletValues(initialWallets);
      }).catch(console.error);
    settingsApi.company().then((r) => setCompany(r.data.settings ?? {})).catch(console.error);
    LEGAL_PAGES.forEach((p) => {
      settingsApi.legal(p.slug, 'ar').then((r) => setLegalContent((prev) => ({ ...prev, [`${p.slug}_ar`]: r.data.content ?? '' }))).catch(console.error);
    });
  }, []);

  const saveWallets = async () => {
    setWalletsSaving(true);
    try {
      const items = DEPOSIT_RECEIVERS.map(f => ({ key: f.key, value: walletValues[f.key] || '', type: 'string' }));
      // تم تصحيح الخطأ هنا (إزالة الأقواس المعقوفة حول items)
      await adminSettingsApi.bulkUpdate(items);
      toast.success('تم حفظ أرقام المحافظ بنجاح');
      const r = await adminSettingsApi.list();
      setSettings(r.data.settings ?? {});
    } catch (err: any) { 
      toast.error(err.response?.data?.message ?? 'فشل حفظ المحافظ'); 
    } finally { 
      setWalletsSaving(false); 
    }
  };

  const updateSetting = async (key: string, value: string) => {
    setSaving(true);
    try {
      await adminSettingsApi.update({ key, value, type: key.endsWith('_discount_percent') || key === 'oranos_markup_percent' ? 'float' : 'string' });
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
      const fresh = await settingsApi.company();
      setCompany(fresh.data.settings ?? {});
      window.dispatchEvent(new Event('marketlly-company-updated'));
      toast.success(t('admin.companyInfoUpdated'));
    } catch (err: any) { toast.error(err.response?.data?.message ?? t('common.failed')); }
    finally { setCompanySaving(false); }
  };

  const saveLegal = async (slug: string) => {
    setLegalSaving(slug);
    try {
      await adminSettingsApi.updateLegal(slug, { content_ar: legalContent[`${slug}_ar`] ?? '' });
      toast.success(t('admin.legalPageUpdated'));
    } catch (err: any) { toast.error(err.response?.data?.message ?? t('common.failed')); }
    finally { setLegalSaving(null); }
  };

  const saveRate = async () => {
    const values: { syp_per_usd?: number; syriatel_syp_per_usd?: number; } = {};
    for (const [text, key] of [[exchangeRate, 'syp_per_usd'], [syriatelRate, 'syriatel_syp_per_usd']] as const) {
      if (text.trim()) {
        const value = Number(text);
        if (!Number.isFinite(value) || value < 1 || value > 100000000) { toast.error('سعر صرف غير صالح'); return; }
        values[key] = value;
      }
    }
    setRateSaving(true);
    try {
      await currencyApi.updateDepositSettings(values);
      toast.success('تم حفظ إعدادات الإيداع.');
    } catch (err: any) { toast.error(err.response?.data?.message ?? 'فشل حفظ الإعدادات'); }
    finally { setRateSaving(false); }
  };

  const handleApplyMarkup = async () => {
    if (!confirm(t('admin.confirmApplyMarkup'))) return;
    try {
      const r = await adminSyncApi.applyMarkup();
      toast.success((r.data?.output ?? 'done').slice(0, 200));
    } catch (err: any) { toast.error(err?.response?.data?.error ?? t('common.failed')); }
  };

  const handleSyncProducts = async () => {
    if (!confirm(t('admin.confirmSyncProducts'))) return;
    try {
      const r = await adminSyncApi.syncProducts();
      toast.success((r.data?.output ?? 'done').slice(0, 200));
    } catch (err: any) { toast.error(err?.response?.data?.error ?? t('common.failed')); }
  };

  return (
    <PageTransition className="space-y-8">
      <div>
        <p className="eyebrow mb-1">{t('admin.system')}</p>
        <h1 className="text-h1 text-gray-900 dark:text-ink-900">{t('admin.settings')}</h1>
      </div>

      <section className="card-pad space-y-4" dir="rtl">
        <div>
          <h2 className="text-h3">إدارة محافظ الدفع (أرقام الاستلام)</h2>
          <p className="text-sm text-gray-500 mt-1">أدخل أرقام المحافظ التي سيحول إليها العملاء، ثم اضغط زر الحفظ في الأسفل.</p>
        </div>
        <div className="space-y-4 pt-2">
          {DEPOSIT_RECEIVERS.map((f) => (
            <div key={f.key} className="flex flex-col sm:flex-row items-start sm:items-center gap-3">
              <label className="w-64 flex-shrink-0 text-small font-medium text-gray-800 dark:text-ink-800 pt-2 sm:pt-0">{f.label}</label>
              <input
                type="text" dir="ltr" className="input flex-1 text-left" placeholder="أدخل الرقم هنا..."
                value={walletValues[f.key] || ''}
                onChange={(e) => setWalletValues(prev => ({ ...prev, [f.key]: e.target.value }))}
                disabled={walletsSaving}
              />
            </div>
          ))}
        </div>
        <div className="pt-2 flex justify-end">
          <Button variant="accent" onClick={saveWallets} loading={walletsSaving}>
            حفظ أرقام المحافظ
          </Button>
        </div>
      </section>

      <section className="card-pad space-y-3" dir="rtl">
        <h2 className="text-h3">سعر صرف الدولار مقابل الليرة السورية</h2>
        <p className="text-sm text-gray-500">سعر الصرف العام يستخدمه شام كاش بالليرة وعرض الرصيد. سعر سيرياتيل منفصل.</p>
        <div className="flex flex-wrap gap-3 items-center">
          <label htmlFor="exchange-rate">1 USD =</label>
          <input id="exchange-rate" className="input max-w-xs" type="number" min="1" max="100000000" step="0.01" value={exchangeRate} onChange={e => setExchangeRate(e.target.value)} placeholder="مثلاً 14000" />
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <label htmlFor="syriatel-rate">سيرياتيل كاش: 1 USD =</label>
          <input id="syriatel-rate" className="input max-w-xs" type="number" min="1" max="100000000" step="0.01" value={syriatelRate} onChange={e => setSyriatelRate(e.target.value)} placeholder="سعر سيرياتيل" />
          <span className="text-xs text-gray-500">ل.س (مستقل عن السعر العام)</span>
        </div>
        <button type="button" disabled={rateSaving} className="btn-accent" onClick={saveRate}>حفظ أسعار الصرف</button>
      </section>

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
                      <p className="text-small font-medium text-gray-800 dark:text-ink-800 break-words">{FRIENDLY_LABELS[key] ?? key}</p>
                    </div>
                    <input
                      type={key.endsWith('_discount_percent') || key === 'oranos_markup_percent' ? 'number' : 'text'}
                      min={key.endsWith('_discount_percent') || key === 'oranos_markup_percent' ? 0 : undefined}
                      max={key === 'oranos_markup_percent' ? 1000 : (key.endsWith('_discount_percent') ? 100 : undefined)}
                      step={key.endsWith('_discount_percent') || key === 'oranos_markup_percent' ? '0.01' : undefined}
                      className="input flex-1"
                      key={`${key}-${value}`}
                      defaultValue={value === '' && key.endsWith('_discount_percent') ? '0' : value}
                      disabled={saving}
                      onBlur={(e) => { if (e.target.value !== String(value)) updateSetting(key, e.target.value); }}
                    />
                  </div>
                );
              })}
            </div>
          </div>
        ))}
      </div>

      <div className="card-pad">
        <h2 className="text-h3 text-gray-900 dark:text-ink-900 mb-5">{t('admin.oranosSync')}</h2>
        <div className="flex flex-wrap gap-3">
          <Button variant="accent" onClick={handleApplyMarkup}>{t('admin.applyMarkup')}</Button>
          <Button variant="secondary" onClick={handleSyncProducts}>{t('admin.syncProductsNow')}</Button>
        </div>
      </div>

      <div className="card-pad">
        <h2 className="text-h3 text-gray-900 dark:text-ink-900 mb-5">{t('admin.companyInfo')}</h2>
        <div className="space-y-4">
          {COMPANY_FIELDS.map((f) => (
            <div key={f.key} className="grid grid-cols-1 sm:grid-cols-3 gap-2 items-start">
              <label className="text-small font-medium text-gray-800 dark:text-ink-800 pt-2 sm:pt-2.5">
                {f.key === 'whatsapp_url' ? 'رابط واتساب' : t(f.labelKey)}
              </label>
              <input type={f.type} className="input sm:col-span-2" value={company[f.key] ?? ''} onChange={(e) => setCompany((p) => ({ ...p, [f.key]: e.target.value }))} />
            </div>
          ))}
          <div className="pt-2 flex justify-end">
            <Button variant="accent" onClick={saveCompany} loading={companySaving}>{t('admin.saveCompany')}</Button>
          </div>
        </div>
      </div>

      <div className="card-pad">
        <h2 className="text-h3 text-gray-900 dark:text-ink-900 mb-5">{t('admin.legalPages')}</h2>
        <div className="space-y-6">
          {LEGAL_PAGES.map((p) => (
            <div key={p.slug} className="space-y-4">
              <label className="text-small font-medium text-gray-800 dark:text-ink-800">{t(p.labelKey)}</label>
              <div className="space-y-1">
                <p className="text-micro text-gray-500 dark:text-ink-500 font-medium" dir="rtl">المحتوى بالعربية</p>
                <textarea className="input min-h-[120px] font-mono text-small text-right" dir="rtl" value={legalContent[`${p.slug}_ar`] ?? ''} onChange={(e) => setLegalContent((prev) => ({ ...prev, [`${p.slug}_ar`]: e.target.value }))} placeholder="أدخل المحتوى..." />
              </div>
              <div className="flex justify-end">
                <Button variant="secondary" size="sm" onClick={() => saveLegal(p.slug)} loading={legalSaving === p.slug}>{t('admin.saveLegal')}</Button>
              </div>
            </div>
          ))}
        </div>
      </div>
    </PageTransition>
  );
}