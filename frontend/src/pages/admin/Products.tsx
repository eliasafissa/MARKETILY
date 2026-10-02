import { useEffect, useState, useCallback } from 'react';
import { adminProductApi, categoryApi } from '@/api/client';
import toast from 'react-hot-toast';
import ProductModal from '@/components/ProductModal';
import ProductImage from '@/components/ProductImage';
import { TableSkeleton } from '@/components/Skeleton';
import { formatPrice } from '@/utils/format';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';

export default function AdminProducts() {
  const { locale, t } = useI18n();
  const [products, setProducts] = useState<any[]>([]);
  const [categories, setCategories] = useState<any[]>([]);
  const [stats, setStats] = useState<{ oranos: number; manual: number; total: number } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [editProduct, setEditProduct] = useState<any | undefined>(undefined);

  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [q, setQ] = useState('');
  const [catFilter, setCatFilter] = useState('');

  const fetch = useCallback(() => {
    setLoading(true);
    setError(null);
    const params: Record<string, string> = { page: String(page) };
    if (q) params.q = q;
    if (catFilter) params.category = catFilter;

    Promise.all([
      adminProductApi.list(params),
      adminProductApi.stats()
    ])
      .then(([listRes, statsRes]) => {
        const d = listRes.data;
        setProducts(d.data ?? []);
        setLastPage(d.last_page ?? 1);
        setTotal(d.total ?? 0);
        setStats(statsRes.data);
      })
      .catch((err: any) => {
        console.error(err);
        setError(err.response?.data?.message ?? t('common.failed'));
      })
      .finally(() => setLoading(false));
  }, [page, q, catFilter, t]);

  useEffect(() => { fetch(); }, [fetch]);
  useEffect(() => {
    categoryApi.list().then((r) => setCategories(r.data.categories ?? [])).catch(() => {});
  }, []);

  const handleDelete = async (p: any) => {
    if (!confirm(t('admin.deleteConfirm', { name: p.name }))) return;
    try {
      await adminProductApi.delete(p.id);
      toast.success(t('admin.productDeleted'));
      fetch();
    } catch (err: any) { toast.error(err.response?.data?.message ?? t('common.failed')); }
  };

  const toggleActive = async (p: any) => {
    try {
      await adminProductApi.update(p.id, { is_active: !p.is_active });
      toast.success(p.is_active ? t('admin.productDeactivated') : t('admin.productActivated'));
      fetch();
    } catch (err: any) { toast.error(err.response?.data?.message ?? t('common.failed')); }
  };

  const openEdit = (p: any) => { setEditProduct(p); setShowModal(true); };
  const openNew = () => { setEditProduct(undefined); setShowModal(true); };

  return (
    <PageTransition className="space-y-6">
      <div className="flex items-end justify-between">
        <div>
          <p className="eyebrow mb-1">{t('admin.system')}</p>
          <h1 className="text-h1 text-gray-900 dark:text-ink-900">{t('admin.products')}</h1>
          {stats && (
            <p className="text-small text-gray-500 dark:text-ink-500 mt-1">
              {t('admin.statsOranos')}: {stats.oranos} · {t('admin.statsManual')}: {stats.manual} · {t('admin.statsTotal')}: {stats.total}
            </p>
          )}
        </div>
        <button onClick={openNew} className="btn-accent">
          + {t('admin.newProduct')}
        </button>
      </div>

      <div className="flex flex-wrap gap-3">
        <input
          type="search"
          className="input max-w-xs"
          placeholder={t('admin.searchProducts') ?? 'Search products…'}
          value={q}
          onChange={(e) => { setQ(e.target.value); setPage(1); }}
        />
        <select
          className="input max-w-xs"
          value={catFilter}
          onChange={(e) => { setCatFilter(e.target.value); setPage(1); }}
        >
          <option value="">{t('admin.allCategories') ?? 'All categories'}</option>
          {categories.map((c) => (
            <option key={c.id} value={c.id}>
              {locale === 'ar' && c.name_ar ? c.name_ar : c.name}
            </option>
          ))}
        </select>
      </div>

      {error && (
        <div className="card-pad text-center">
          <p className="text-body text-status-rejected mb-3">{error}</p>
          <button onClick={fetch} className="btn-accent">{t('admin.retry')}</button>
        </div>
      )}

      {loading && !error && <TableSkeleton rows={6} columns={7} />}

      <div className={`card overflow-hidden p-0 ${loading ? 'hidden' : ''}`}>
        <div className="overflow-x-auto">
          <table className="table">
            <thead>
              <tr>
                <th>{t('admin.name')}</th>
                <th>{t('admin.category')}</th>
                <th>{t('admin.price')}</th>
                <th>{t('admin.type')}</th>
                <th>{t('admin.stock')}</th>
                <th>{t('admin.active')}</th>
                <th>{t('admin.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {products.map((p) => (
                <tr key={p.id}>
                  <td>
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 shrink-0 rounded-lg overflow-hidden">
                        <ProductImage
                          name={locale === 'ar' && p.name_ar ? p.name_ar : p.name}
                          category={p.category ? (locale === 'ar' && p.category.name_ar ? p.category.name_ar : p.category.name) : undefined}
                          icon={p.icon}
                          categoryImageUrl={p.category?.image_url}
                          imageBase64={p.image_base64}
                          imageUrl={p.image_url}
                          className="w-10 h-10 rounded-lg"
                        />
                      </div>
                      <span className="font-medium text-gray-900 dark:text-ink-900 line-clamp-2">
                        {locale === 'ar' && p.name_ar ? p.name_ar : p.name}
                      </span>
                    </div>
                  </td>
                  <td className="text-gray-500 dark:text-ink-500">
                    {p.category
                      ? locale === 'ar' && p.category.name_ar
                        ? p.category.name_ar
                        : p.category.name
                      : '—'}
                  </td>
                  <td className="tabular-nums">{formatPrice(p.price)}</td>
                  <td>
                    <span className={p.type === 'manual' ? 'badge-pending' : 'badge-completed'}>
                      {p.type}
                    </span>
                  </td>
                  <td className="tabular-nums">{p.stock}</td>
                  <td>
                    <button
                      onClick={() => toggleActive(p)}
                      className={`text-small font-medium ${
                        p.is_active
                          ? 'text-green-400 hover:text-green-300'
                          : 'text-status-rejected hover:text-status-rejected/80'
                      }`}
                    >
                      {p.is_active ? t('admin.active') : t('admin.inactive')}
                    </button>
                  </td>
                  <td>
                    <div className="flex gap-2">
                      <button onClick={() => openEdit(p)} className="btn-secondary btn-sm">
                        {t('common.edit')}
                      </button>
                      <button
                        onClick={() => handleDelete(p)}
                        className="btn-danger btn-sm"
                      >
                        {t('common.delete')}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {products.length === 0 && !loading && (
                <tr>
                  <td colSpan={7} className="text-center text-gray-500 dark:text-ink-500 py-8">
                    {t('admin.noProductsYet')}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {lastPage > 1 && (
          <div className="flex items-center justify-between gap-3 p-4 border-t border-gray-200 dark:border-ink-200">
            <button
              className="btn-secondary btn-sm"
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={page <= 1}
            >
              ← {t('common.prev')}
            </button>
            <span className="text-small text-gray-600 dark:text-ink-500">
              {t('common.page')} {page} {t('common.of')} {lastPage}
            </span>
            <button
              className="btn-secondary btn-sm"
              onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
              disabled={page >= lastPage}
            >
              {t('common.next')} →
            </button>
          </div>
        )}
      </div>

      {showModal && (
        <ProductModal
          product={editProduct}
          onClose={() => setShowModal(false)}
          onSaved={() => { toast.success(t('admin.productSaved')); fetch(); }}
        />
      )}
    </PageTransition>
  );
}
