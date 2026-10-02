import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { storeApi } from '@/api/client';
import ProductImage from '@/components/ProductImage';
import { formatPrice } from '@/utils/format';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';
import { localized } from '@/utils/localize';
import Breadcrumbs from '@/components/Breadcrumbs';

export default function StorePage() {
  const { slug } = useParams();
  const { t, locale } = useI18n();
  const [store, setStore] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [notFound, setNotFound] = useState(false);

  useEffect(() => {
    if (!slug) return;
    storeApi.getBySlug(slug)
      .then((res) => setStore(res.data.store))
      .catch(() => setNotFound(true))
      .finally(() => setLoading(false));
  }, [slug]);

  if (loading) {
    return (
      <div className="flex items-center justify-center py-24">
        <span className="w-8 h-8 rounded-full border-2 border-green-500 border-t-transparent animate-spin" />
      </div>
    );
  }

  if (notFound || !store) {
    return (
      <PageTransition className="text-center py-24">
        <p className="text-h3 text-gray-600 dark:text-ink-600 mb-4">Store not found.</p>
        <Link to="/" className="btn-accent">Back to home</Link>
      </PageTransition>
    );
  }

  return (
    <PageTransition className="space-y-8">
      <Breadcrumbs items={[{ label: t('nav.home') ?? 'Home', link: '/' }, { label: store.name }]} />

      <div>
        <h1 className="text-h1 text-gray-900 dark:text-ink-900 mb-2">{store.name}</h1>
        {store.description && (
          <p className="text-body text-gray-600 dark:text-ink-600">{store.description}</p>
        )}
      </div>

      {store.products?.length > 0 ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {store.products.map((p: any, i: number) => {
            const price = p.store_price ?? p.price;
            return (
              <motion.div
                key={p.id}
                initial={{ opacity: 0, y: 16 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: i * 0.03, duration: 0.35 }}
              >
<Link to={`/product/${p.slug}`} className="card-hover group block overflow-hidden">
                  <div className="aspect-square overflow-hidden relative">
                    <ProductImage
                      name={localized(p, 'name', 'name_ar', locale)} icon={p.icon}
                      category={store.name}
                      imageBase64={p.image_base64}
                      imageUrl={p.image_url}
                      className="w-full h-full object-cover"
                      productId={p.id}
                      showFavorite
                    />
                    {(p.stock === 0 || p.oranos_available === false) && (
                      <div className="absolute inset-0 bg-black/50 flex items-center justify-center z-10">
                        <span className="badge-rejected text-base px-4 py-2">{t('common.unavailable')}</span>
                      </div>
                    )}
                  </div>
                  <div className="px-4 pb-4">
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-ink-900 group-hover:text-green-400 transition-colors line-clamp-2 mb-1">
                      {localized(p, 'name', 'name_ar', locale)}
                    </h3>
                    <span className="text-h3 text-green-400">{formatPrice(price)}</span>
                  </div>
                </Link>
              </motion.div>
            );
          })}
        </div>
      ) : (
        <div className="text-center py-20 card-pad">
          <p className="text-body text-gray-600 dark:text-ink-600">No products in this store.</p>
        </div>
      )}
    </PageTransition>
  );
}