import { useState } from 'react';
import { useAppSelector } from '@/store';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import Button from '@/components/Button';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';

export default function CreateWebsite() {
  const { user, isAuthenticated } = useAppSelector((s) => s.auth);
  const navigate = useNavigate();
  const { t } = useI18n();

  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    needs: '',
  });
  const [submitting, setSubmitting] = useState(false);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    setFormData((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      // Submit to a simple contact endpoint
      await fetch('/api/contact-website', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData),
      });
      toast.success(t('createWebsite.success'));
      setFormData({ name: '', email: '', phone: '', needs: '' });
    } catch {
      toast.error(t('createWebsite.error'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <PageTransition className="max-w-2xl mx-auto py-16">
      <div className="text-center mb-12">
        <h1 className="text-h1 text-gray-900 dark:text-ink-900 mb-4">{t('createWebsite.title')}</h1>
        <p className="text-body text-gray-600 dark:text-ink-600 max-w-xl mx-auto">{t('createWebsite.description')}</p>
      </div>

      <form onSubmit={handleSubmit} className="card-pad space-y-5">
        {user ? (
          <>
            <div className="text-sm text-gray-500 dark:text-ink-500 mb-4">
              {t('createWebsite.form.loggedInAs', { email: user.email })}
            </div>
          </>
        ) : (
          <>
            <div>
              <label className="label">{t('createWebsite.form.name')}</label>
              <input
                type="text"
                name="name"
                className="input"
                value={formData.name}
                onChange={handleChange}
                required
                maxLength={100}
                placeholder="John Doe"
              />
            </div>
            <div>
              <label className="label">{t('createWebsite.form.email')}</label>
              <input
                type="email"
                name="email"
                className="input"
                value={formData.email}
                onChange={handleChange}
                required
                maxLength={255}
                placeholder="john@example.com"
              />
            </div>
          </>
        )}
        <div>
          <label className="label">{t('createWebsite.form.phone')}</label>
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
          <label className="label">{t('createWebsite.form.needs')}</label>
          <textarea
            name="needs"
            className="input min-h-[150px]"
            value={formData.needs}
            onChange={handleChange}
            required
            maxLength={2000}
            placeholder="Tell us about your project: what kind of store, expected volume, integrations needed, timeline..."
          />
        </div>
        <Button variant="accent" type="submit" loading={submitting} className="w-full">
          {t('createWebsite.form.submit')}
        </Button>
      </form>
    </PageTransition>
  );
}