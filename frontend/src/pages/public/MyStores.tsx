import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { storeApi } from '@/api/client';
import PageTransition from '@/components/PageTransition';
import Breadcrumbs from '@/components/Breadcrumbs';
import { useI18n } from '@/i18n';
import { localized } from '@/utils/localize';

export default function MyStores() {
  const { locale, t } = useI18n();
  const [stores, setStores] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    storeApi.my()
      .then((res) => setStores(res.data.stores ?? []))
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

  return (
    <PageTransition className="space-y-8">
      <Breadcrumbs items={[{ label: t('nav.home'), link: '/' }, { label: t('nav.myStores') }]} />
      <div className="flex items-center justify-between">
        <h1 className="text-h1 text-gray-900 dark:text-ink-900">{t('nav.myStores')}</h1>
        <Link to="/create-store" className="btn-accent">{t('nav.createStore')}</Link>
      </div>

      {stores.length === 0 ? (
        <div className="card-pad text-center py-16">
          <p className="text-body text-gray-600 dark:text-ink-600 mb-4">{t('store.noStores')}</p>
          <Link to="/create-store" className="btn-accent">{t('store.createFirst')}</Link>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {stores.map((store) => (
            <div key={store.id} className="card-pad">
              <h3 className="font-heading text-lg font-semibold text-gray-900 dark:text-ink-900 mb-1">
                {store.name}
              </h3>
              <p className="text-sm text-gray-600 dark:text-ink-500 mb-1">/{store.slug}</p>
              <p className="text-sm text-gray-600 dark:text-ink-500 mb-4">
                {store.products_count} {t('products.items')}
              </p>
              <Link
                to={`/store/${store.slug}`}
                className="text-sm text-green-500 hover:text-green-400 transition-colors"
              >
                {t('store.viewStore')} →
              </Link>
            </div>
          ))}
        </div>
      )}
    </PageTransition>
  );
}