import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { favoriteApi } from '@/api/client';
import toast from 'react-hot-toast';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';
import Breadcrumbs from '@/components/Breadcrumbs';
import ProductImage from '@/components/ProductImage';
import { formatPrice } from '@/utils/format';
import EmptyState from '@/components/EmptyState';

interface FavoriteItem {
  id: number;
  product_id: number;
  product: {
    id: number;
    name: string;
    name_ar: string | null;
    slug: string;
    price: number;
    image_url: string | null;
    category: { name: string; name_ar: string | null } | null;
  };
}

interface FavoritesResponse {
  data: FavoriteItem[];
  current_page: number;
  last_page: number;
  total: number;
}

export default function Favorites() {
  const { t, locale } = useI18n();
  const [favorites, setFavorites] = useState<FavoriteItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  const isRtl = locale === 'ar';

  const getProductName = (product: { name: string; name_ar: string | null }) =>
    isRtl && product.name_ar ? product.name_ar : product.name;

  const getCategoryName = (category: { name: string; name_ar: string | null } | null) =>
    category ? (isRtl && category.name_ar ? category.name_ar : category.name) : '';

  const fetchFavorites = async (pageNum = 1) => {
    try {
      const res = await favoriteApi.list();
      if (res.data.data) {
        setFavorites(res.data.data);
        setPage(res.data.current_page);
        setLastPage(res.data.last_page);
        setTotal(res.data.total);
      } else if (Array.isArray(res.data)) {
        setFavorites(res.data);
        setTotal(res.data.length);
      }
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? t('toast.failed'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchFavorites();
  }, []);

  const handleRemove = async (productId: number) => {
    try {
      await favoriteApi.remove(productId);
      toast.success(t('favorites.productRemoved'));
      setFavorites((prev) => prev.filter((f) => f.product_id !== productId));
    } catch {
      toast.error(t('toast.failed'));
    }
  };

  return (
    <PageTransition className="space-y-8">
      <Breadcrumbs
        items={[
          { label: t('nav.home'), link: '/' },
          { label: t('nav.dashboard'), link: '/dashboard' },
          { label: t('favorites.title') },
        ]}
      />
      <div className="flex flex-col gap-4">
        <div className="flex items-center justify-between">
          <h1 className="text-h1 text-gray-900 dark:text-ink-900">{t('favorites.title')}</h1>
          <span className="text-micro text-gray-500 dark:text-ink-500">
            {total} {total === 1 ? t('products.item') : t('products.items')}
          </span>
        </div>

        {loading ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {[...Array(8)].map((_, i) => (
              <motion.div
                key={i}
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: i * 0.05 }}
                className="bg-gray-100 dark:bg-ink-100 rounded-xl h-64 animate-pulse"
              />
            ))}
          </div>
        ) : favorites.length === 0 ? (
          <EmptyState
            icon={
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
            }
            title={t('favorites.empty')}
            description={t('favorites.empty')}
            action={{ label: t('home.browseProducts'), to: '/products' }}
            className="py-16"
          />
        ) : (
          <>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
              {favorites.map((fav, i) => (
                <motion.div
                  key={fav.id}
                  initial={{ opacity: 0, y: 12 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: i * 0.05, duration: 0.35 }}
                >
                  <Link to={`/product/${fav.product.slug}`} className="block group">
                    <div className="relative aspect-square mb-3 overflow-hidden rounded-xl bg-gray-100 dark:bg-ink-100 group-hover:shadow-lg transition-shadow">
                      <ProductImage
                        name={getProductName(fav.product)}
                        category={getCategoryName(fav.product.category)}
                        imageUrl={fav.product.image_url ?? undefined}
                        productId={fav.product_id}
                        showFavorite
                        initialFavorited={true}
                        className="w-full h-full object-cover"
                      />
                    </div>
                    <h3 className="font-semibold text-gray-900 dark:text-ink-900 line-clamp-1 group-hover:text-green-500 transition-colors mb-1">
                      {getProductName(fav.product)}
                    </h3>
                    <p className="text-h3 text-green-400 tabular-nums">{formatPrice(fav.product.price)}</p>
                    {fav.product.category && (
                      <p className="text-micro text-gray-500 dark:text-ink-500 mt-1 line-clamp-1">
                        {getCategoryName(fav.product.category)}
                      </p>
                    )}
                  </Link>
                </motion.div>
              ))}
            </div>

            {lastPage > 1 && (
              <div className="flex items-center justify-center gap-2 mt-6">
                <button
                  onClick={() => page > 1 && fetchFavorites(page - 1)}
                  disabled={page <= 1}
                  className="btn-ghost btn-sm"
                >
                  ← {t('common.prev')}
                </button>
                <span className="text-sm text-gray-600 dark:text-ink-500">
                  {t('common.page')} {page} {t('common.of')} {lastPage}
                </span>
                <button
                  onClick={() => page < lastPage && fetchFavorites(page + 1)}
                  disabled={page >= lastPage}
                  className="btn-ghost btn-sm"
                >
                  {t('common.next')} →
                </button>
              </div>
            )}
          </>
        )}
      </div>
    </PageTransition>
  );
}
