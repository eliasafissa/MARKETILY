import { useEffect, useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAppSelector } from '@/store';
import { partnerRequestApi } from '@/api/client';
import toast from 'react-hot-toast';
import Button from '@/components/Button';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';
import { CopyToClipboard } from 'react-copy-to-clipboard';

export default function ConnectStore() {
  const { user, isAuthenticated } = useAppSelector((s) => s.auth);
  const navigate = useNavigate();
  const { t } = useI18n();
  const authLoading = !isAuthenticated && user === undefined;

  const [request, setRequest] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [formData, setFormData] = useState({
    store_name: '',
    store_url: '',
    phone: '',
    notes: '',
  });
  const [showKey, setShowKey] = useState(false);
  const [apiKey, setApiKey] = useState<string | null>(null);

  useEffect(() => {
    if (!authLoading) {
      if (!user) {
        setLoading(false);
        return;
      }
      loadRequest();
    }
  }, [user, authLoading]);

  const loadRequest = async () => {
    try {
      const res = await partnerRequestApi.myRequest();
      setRequest(res.data.request);
      if (res.data.api_key) {
        setApiKey(res.data.api_key);
      }
    } catch (err: any) {
      toast.error(err?.response?.data?.error ?? t('common.failed'));
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      const res = await partnerRequestApi.create(formData);
      toast.success(res.data.request ? t('connectStore.form.submitted') : t('common.failed'));
      setRequest(res.data.request);
      setFormData({ store_name: '', store_url: '', phone: '', notes: '' });
    } catch (err: any) {
      toast.error(err?.response?.data?.error ?? t('common.failed'));
    } finally {
      setSubmitting(false);
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    setFormData((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-24">
        <span className="w-8 h-8 rounded-full border-2 border-green-500 border-t-transparent animate-spin" />
      </div>
    );
  }

  if (!user) {
    return (
      <PageTransition className="max-w-2xl mx-auto text-center py-16">
        <h1 className="text-h1 text-gray-900 dark:text-ink-900 mb-4">{t('connectStore.title')}</h1>
        <p className="text-body text-gray-600 dark:text-ink-600 mb-8">{t('connectStore.description')}</p>
        <div className="space-y-4">
          <p className="text-body text-gray-600 dark:text-ink-600">{t('connectStore.notLoggedIn')}</p>
          <Link to="/login" className="btn-accent inline-block">
            {t('connectStore.login')}
          </Link>
        </div>
      </PageTransition>
    );
  }

  const status = request?.status;

  return (
    <PageTransition className="max-w-2xl mx-auto">
      <h1 className="text-h1 text-gray-900 dark:text-ink-900 mb-2">{t('connectStore.title')}</h1>
      <p className="text-body text-gray-600 dark:text-ink-600 mb-8">{t('connectStore.description')}</p>

      {status === 'pending' && (
        <div className="card-pad text-center">
          <div className="flex items-center justify-center gap-2 text-warning mb-4">
            <span className="w-6 h-6 rounded-full border-2 border-warning border-t-transparent animate-spin" />
            <span className="text-h3 font-medium">{t('connectStore.status.pending')}</span>
          </div>
          <p className="text-body text-gray-600 dark:text-ink-600">{t('connectStore.form.storeName')}: {request?.store_name}</p>
          <p className="text-body text-gray-600 dark:text-ink-600 mt-1">{t('connectStore.form.storeUrl')}: {request?.store_url}</p>
        </div>
      )}

      {status === 'approved' && (
        <div className="card-pad space-y-6">
          <div className="flex items-center justify-between">
            <span className="badge-completed text-base px-4 py-2">{t('connectStore.status.approved')}</span>
            <Link to="/dashboard/api-docs" className="btn-secondary">
              {t('connectStore.apiKey.viewDocs')}
            </Link>
          </div>
          <div className="space-y-3">
            <label className="label">{t('connectStore.apiKey.yourKey')}</label>
            <div className="flex items-center gap-3">
              <div className="flex-1 font-mono text-small bg-gray-100 dark:bg-ink-200 px-4 py-3 rounded-lg break-all">
                {showKey ? apiKey : '•'.repeat(32)}
              </div>
              <CopyToClipboard text={apiKey ?? ''} onCopy={() => toast.success(t('connectStore.apiKey.copied'))}>
                <Button variant="secondary" size="sm">{t('connectStore.apiKey.copy')}</Button>
              </CopyToClipboard>
              <Button variant="ghost" size="sm" onClick={() => setShowKey(!showKey)}>
                {showKey ? '🙈' : '👁️'}
              </Button>
            </div>
            <p className="text-micro text-gray-500 dark:text-ink-500">{t('connectStore.apiKey.warning')}</p>
          </div>
        </div>
      )}

      {status === 'rejected' && (
        <div className="card-pad space-y-4">
          <span className="badge-rejected text-base px-4 py-2">{t('connectStore.status.rejected')}</span>
          {request?.rejected_reason && (
            <p className="text-body text-status-rejected">{t('connectStore.status.rejectedReason', { reason: request.rejected_reason })}</p>
          )}
          <Button variant="accent" onClick={() => setRequest(null)}>{t('connectStore.status.reapply')}</Button>
        </div>
      )}

      {!status && (
        <form onSubmit={handleSubmit} className="card-pad space-y-5">
          <div>
            <label className="label">{t('connectStore.form.storeName')}</label>
            <input
              type="text"
              name="store_name"
              className="input"
              value={formData.store_name}
              onChange={handleChange}
              required
              maxLength={255}
              placeholder="My Awesome Store"
            />
          </div>
          <div>
            <label className="label">{t('connectStore.form.storeUrl')}</label>
            <input
              type="url"
              name="store_url"
              className="input"
              value={formData.store_url}
              onChange={handleChange}
              required
              maxLength={500}
              placeholder="https://mystore.com"
            />
          </div>
          <div>
            <label className="label">{t('connectStore.form.phone')}</label>
            <input
              type="tel"
              name="phone"
              className="input"
              value={formData.phone}
              onChange={handleChange}
              required
              maxLength={50}
              placeholder="+1 555 123 4567"
            />
          </div>
          <div>
            <label className="label">{t('connectStore.form.notes')}</label>
            <textarea
              name="notes"
              className="input min-h-[100px]"
              value={formData.notes}
              onChange={handleChange}
              maxLength={1000}
              placeholder="Any additional information about your store..."
            />
          </div>
          <Button variant="accent" type="submit" loading={submitting} className="w-full">
            {t('connectStore.form.submit')}
          </Button>
        </form>
      )}
    </PageTransition>
  );
}