import { useEffect, useState, useRef } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { productApi } from '@/api/client';
import ProductImage from '@/components/ProductImage';
import { ProductGridSkeleton } from '@/components/Skeleton';
import { formatPrice } from '@/utils/format';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';
import { localized } from '@/utils/localize';
import Breadcrumbs from '@/components/Breadcrumbs';

export default function Products() {
  const { t, locale } = useI18n();
  const [products, setProducts] = useState<any[]>([]);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const handleSearch = (v: string) => {
    setSearch(v);
    if (timeoutRef.current) clearTimeout(timeoutRef.current);
    timeoutRef.current = setTimeout(() => setDebouncedSearch(v), 350);
  };

  useEffect(() => {
    setLoading(true);
    productApi.list({ per_page: '100', ...(debouncedSearch ? { q: debouncedSearch } : {}) })
      .then((r) => setProducts(r.data.data ?? []))
      .catch(console.error)
      .finally(() => setLoading(false));
  }, [debouncedSearch]);

  if (loading) {
    return (
      <PageTransition className="space-y-8">
        <div>
          <Breadcrumbs items={[{ label: t('nav.home'), link: '/' }, { label: t('nav.products') }]} />
          <h1 className="text-h1 text-gray-900 dark:text-ink-900 mt-4">{t('nav.products')}</h1>
        </div>
        <ProductGridSkeleton count={8} />
      </PageTransition>
    );
  }

  return (
    <PageTransition className="space-y-8">
      <div>
        <Breadcrumbs items={[{ label: t('nav.home'), link: '/' }, { label: t('nav.products') }]} />
        <h1 className="text-h1 text-gray-900 dark:text-ink-900 mt-4">{t('nav.products')}</h1>
      </div>

      <div className="relative max-w-sm">
        <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-600 dark:text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input
          type="search"
          placeholder={t('products.searchPlaceholder')}
          className="input pl-10"
          value={search}
          onChange={(e) => handleSearch(e.target.value)}
        />
      </div>

      {products.length > 0 ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {products.map((p, i) => (
            <motion.div
              key={p.id}
              initial={{ opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: i * 0.03, duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
            >
              <Link to={`/product/${p.slug}`} className="card-hover group block overflow-hidden">
                <div className="aspect-square overflow-hidden relative">
                  <ProductImage name={localized(p, 'name', 'name_ar', locale)} icon={p.icon} category={localized(p.category, 'name', 'name_ar', locale)} categoryImageUrl={p.category?.image_url} imageBase64={p.image_base64} imageUrl={p.image_url} className="w-full h-full object-cover" productId={p.id} showFavorite />
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
                  <p className="text-micro text-gray-600 dark:text-ink-500 line-clamp-2 mb-3">
                    {localized(p, 'description', 'description_ar', locale)}
                  </p>
                  <div className="flex items-center justify-between">
                    <span className="text-h3 text-green-400">
                      {formatPrice(p.price)}
                    </span>
                    {p.external_store_id && (
                      <span className="badge-neutral text-micro">External</span>
                    )}
                  </div>
                </div>
              </Link>
            </motion.div>
          ))}
        </div>
      ) : (
        <div className="text-center py-20 card-pad">
          <p className="text-body text-gray-600 dark:text-ink-600">
            {search ? 'No products match your search.' : t('products.noProducts')}
          </p>
          {search && (
            <button
              onClick={() => handleSearch('')}
              className="text-sm text-green-400 hover:text-green-300 mt-2 transition-colors"
            >
              Clear search
            </button>
          )}
        </div>
      )}
    </PageTransition>
  );
}
