import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { settingsApi } from '@/api/client';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';

export default function LegalPage() {
  const { page } = useParams<{ page: string }>();
  const { t } = useI18n();
  const [content, setContent] = useState('');
  const [loading, setLoading] = useState(true);

  const key = (page ?? 'terms') as 'terms' | 'privacy' | 'refund';
  const titleKey = `legal.${key}` as const;

  useEffect(() => {
    setLoading(true);
    settingsApi.legal(key, 'ar')
      .then((r) => setContent(r.data.content ?? ''))
      .catch(() => setContent(''))
      .finally(() => setLoading(false));
  }, [key]);

  return (
    <PageTransition className="max-w-3xl mx-auto">
      <h1 className="text-h1 text-gray-900 dark:text-ink-900 mb-2">{t(titleKey)}</h1>
      {loading ? (
        <div className="space-y-3 animate-pulse">
          {[100, 90, 100, 75, 100, 85].map((w, i) => (
            <div key={i} className="h-4 bg-gray-200 dark:bg-ink-100 rounded" style={{ width: `${w}%` }} />
          ))}
        </div>
      ) : content ? (
        <div
          className="prose prose-sm dark:prose-invert max-w-none mt-6
                     prose-headings:text-gray-900 dark:prose-headings:text-ink-900
                     prose-p:text-gray-600 dark:prose-p:text-ink-600
                     prose-li:text-gray-600 dark:prose-li:text-ink-600"
          dangerouslySetInnerHTML={{ __html: content.replace(/\n/g, '<br>') }}
        />
      ) : (
        <p className="mt-6 text-body text-gray-600 dark:text-ink-600">{t('legal.beingUpdated')}</p>
      )}
    </PageTransition>
  );
}
