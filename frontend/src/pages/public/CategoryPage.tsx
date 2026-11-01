// HAIDAR-V5-CATEGORY: consistent nested cards, icon fallback and no motion.
import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { ArrowLeft, ChevronLeft, ChevronRight, Search, SlidersHorizontal, X, Package } from 'lucide-react';
import { categoryApi, productApi } from '@/api/client';
import ProductImage from '@/components/ProductImage';
import PageTransition from '@/components/PageTransition';
import Breadcrumbs from '@/components/Breadcrumbs';
import { useI18n } from '@/i18n';
import type { Language } from '@/i18n';
import { localized } from '@/utils/localize';
import { formatPrice } from '@/utils/format';

type SortOrder = 'default' | 'az' | 'za';

interface CategoryNode {
  id: number;
  parent_id?: number | null;
  name?: string;
  name_ar?: string;
  slug: string;
  description?: string | null;
  description_ar?: string | null;
  image_url?: string | null;
  children?: CategoryNode[];
  products_count?: number;
  subtree_products_count?: number;
  subtree_categories_count?: number;
}

const englishCollator = new Intl.Collator('en', { sensitivity: 'base', numeric: true });

function flatten(nodes: CategoryNode[]): CategoryNode[] {
  return nodes.flatMap((node) => [node, ...flatten(node.children ?? [])]);
}

function matches(node: CategoryNode, query: string): boolean {
  return [node.name, node.name_ar].some((value) => (value ?? '').toLocaleLowerCase().includes(query));
}

function sortCategories(nodes: CategoryNode[], sort: SortOrder): CategoryNode[] {
  if (sort === 'default') return nodes;
  return [...nodes].sort((a, b) => {
    const diff = englishCollator.compare(a.name ?? a.name_ar ?? '', b.name ?? b.name_ar ?? '');
    return (sort === 'az' ? diff : -diff) || a.id - b.id;
  });
}

function safeImageUrl(url?: string | null): string | undefined {
  if (!url) return undefined;
  if (url.startsWith('https://') || /^http:\/\/(localhost|127\.0\.0\.1):8000\/storage\//.test(url)) return url;
  return undefined;
}

function SubcategoryCard({ node, locale, index }: { node: CategoryNode; locale: Language; index: number }) {
  const name = localized(node, 'name', 'name_ar', locale);
  const [broken, setBroken] = useState(false);
  const src = safeImageUrl(node.image_url);

  return (
    <motion.div data-category-index={index} className="h-full">
      <Link to={`/category/${node.slug}`} className="card-hover group block h-full overflow-hidden rounded-2xl border border-gray-200 dark:border-ink-200 bg-white dark:bg-ink-50 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-green-500">
        <div className="relative isolate min-h-[110px] sm:min-h-[142px] flex flex-col justify-end overflow-hidden">
          {src && !broken ? (
            <img src={src} alt="" loading="lazy" onError={() => setBroken(true)} className="pointer-events-none absolute inset-0 w-full h-full object-cover object-center bg-gray-900 transition-transform duration-500 group-hover:scale-105" />
          ) : <div className="absolute inset-0 bg-gray-100 dark:bg-ink-100" aria-hidden="true" />}
          {src && !broken && <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-black/5" aria-hidden="true" />}
          <div className="relative z-10 p-2 sm:p-4">
            {(!src || broken) && <Package size={24} className="mb-2 text-green-600" aria-hidden="true" />}
            <h3 className={`font-heading text-xs sm:text-sm font-semibold line-clamp-2 break-words ${src && !broken ? 'text-white' : 'text-gray-900 dark:text-ink-900 group-hover:text-green-500'}`}>{name}</h3>
            <p className={`mt-1 text-[9px] sm:text-xs ${src && !broken ? 'text-white/90' : 'text-gray-500 dark:text-ink-500'}`}>{node.subtree_categories_count ?? 0} قسم فرعي · {node.subtree_products_count ?? node.products_count ?? 0} منتج</p>
          </div>
        </div>
      </Link>
    </motion.div>
  );
}

export default function CategoryPage() {
  const { slug } = useParams<{ slug: string }>();
  const navigate = useNavigate();
  const { t, locale } = useI18n();
  const [tree, setTree] = useState<CategoryNode[]>([]);
  const [category, setCategory] = useState<CategoryNode | null>(null);
  const [loadingCategory, setLoadingCategory] = useState(true);
  const [products, setProducts] = useState<any[]>([]);
  const [productsLoading, setProductsLoading] = useState(false);
  const [productError, setProductError] = useState(false);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [sort, setSort] = useState<SortOrder>('default');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    setPage(1);
  }, [slug, debouncedSearch]);

  useEffect(() => {
    if (!slug) return;
    let cancelled = false;
    setLoadingCategory(true);
    setCategory(null);
    setProducts([]);
    setSearch('');
    setDebouncedSearch('');
    setSort('default');
    setPage(1);

    Promise.all([categoryApi.list(), categoryApi.show(slug)])
      .then(([listResponse, showResponse]) => {
        if (cancelled) return;
        setTree(listResponse.data.categories ?? []);
        setCategory(showResponse.data.category ?? null);
      })
      .catch(() => { if (!cancelled) setCategory(null); })
      .finally(() => { if (!cancelled) setLoadingCategory(false); });
    return () => { cancelled = true; };
  }, [slug]);

  useEffect(() => {
    if (!slug || !category || loadingCategory) return;
    let cancelled = false;
    setProductsLoading(true);
    setProductError(false);
    productApi.list({
      category: slug,
      scope: 'descendants',
      per_page: '24',
      page: String(page),
      ...(debouncedSearch ? { q: debouncedSearch } : {}),
    })
      .then((response) => {
        if (cancelled) return;
        setProducts(response.data.data ?? []);
        setTotal(response.data.total ?? 0);
        setLastPage(Math.max(1, response.data.last_page ?? 1));
      })
      .catch(() => {
        if (cancelled) return;
        setProducts([]);
        setTotal(0);
        setProductError(true);
      })
      .finally(() => { if (!cancelled) setProductsLoading(false); });
    return () => { cancelled = true; };
  }, [slug, category?.id, loadingCategory, page, debouncedSearch]);

  const allCategories = useMemo(() => flatten(tree), [tree]);
  const categoryName = category ? localized(category, 'name', 'name_ar', locale) : '';
  const breadcrumbs = useMemo(() => {
    const chain: { label: string; link?: string }[] = [];
    const byId = new Map(allCategories.map((node) => [node.id, node]));
    const seen = new Set<number>();
    let current = category;
    while (current && !seen.has(current.id)) {
      seen.add(current.id);
      chain.unshift({ label: localized(current, 'name', 'name_ar', locale), link: `/category/${current.slug}` });
      current = current.parent_id ? byId.get(current.parent_id) ?? null : null;
    }
    if (chain.length) delete chain[chain.length - 1].link;
    return [{ label: t('nav.home'), link: '/' }, { label: t('nav.categories'), link: '/categories' }, ...chain];
  }, [allCategories, category, locale, t]);

  const visibleChildren = useMemo(() => {
    const children = category?.children ?? [];
    const query = debouncedSearch.toLocaleLowerCase();
    const candidates = query ? flatten(children).filter((node) => matches(node, query)) : children;
    return sortCategories(candidates, sort);
  }, [category, debouncedSearch, sort]);

  if (loadingCategory) return <div className="flex items-center justify-center py-24"><span className="w-9 h-9 rounded-full border-2 border-green-500 border-t-transparent animate-spin" /></div>;
  if (!category) return <PageTransition className="space-y-6"><h1 className="text-h2">القسم غير متاح</h1><Link to="/categories" className="text-green-500">العودة للأقسام</Link></PageTransition>;

  return (
    <PageTransition className="space-y-8">
      <button type="button" onClick={() => navigate(-1)} className="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-ink-500 hover:text-green-500"><ArrowLeft className="w-4 h-4 rtl:rotate-180" />{t('product.back')}</button>
      <Breadcrumbs items={breadcrumbs} />
      <header className="space-y-2">
        <p className="eyebrow text-green-500">{t('nav.categories')}</p>
        <h1 className="text-h1 text-gray-900 dark:text-ink-900">{categoryName}</h1>
        <p className="text-sm text-gray-500 dark:text-ink-500">{category.subtree_categories_count ?? 0} قسم فرعي · {category.subtree_products_count ?? 0} منتج ضمن القسم وأقسامه الفرعية</p>
        {(category.description_ar || category.description) && <p className="text-sm text-gray-500 dark:text-ink-500 whitespace-pre-line max-w-3xl">{localized(category, 'description', 'description_ar', locale)}</p>}
      </header>

      <div className="flex flex-col md:flex-row md:items-center gap-3 md:gap-5">
        <label className="relative block flex-1 min-w-0">
          <span className="sr-only">البحث في الأقسام الفرعية والمنتجات</span>
          <Search className="absolute start-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" aria-hidden="true" />
          <input
            type="search"
            value={search}
            maxLength={100}
            onChange={(event) => setSearch(event.target.value)}
            placeholder={`ابحث داخل ${categoryName}...`}
            className="w-full h-12 rounded-xl border border-gray-200 dark:border-ink-200 bg-white dark:bg-ink-50 text-gray-900 dark:text-ink-900 ps-12 pe-11 outline-none placeholder:text-gray-400 focus:border-green-500 focus:ring-2 focus:ring-green-500/20 transition-colors"
          />
          {search && <button type="button" onClick={() => setSearch('')} aria-label="مسح البحث" className="absolute end-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-green-500"><X className="w-4 h-4" /></button>}
        </label>
        <label className="flex items-center gap-2 md:shrink-0 text-sm text-gray-500 dark:text-ink-500">
          <SlidersHorizontal className="w-4 h-4" aria-hidden="true" />
          <span>ترتيب الأقسام</span>
          <select value={sort} onChange={(event) => setSort(event.target.value as SortOrder)} aria-label="ترتيب الأقسام الفرعية حسب الاسم الإنجليزي" className="h-12 w-full md:w-[155px] px-3 rounded-xl bg-white dark:bg-ink-50 border border-gray-200 dark:border-ink-200 text-gray-900 dark:text-ink-900 focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
            <option value="default">افتراضي</option>
            <option value="az">A–Z</option>
            <option value="za">Z–A</option>
          </select>
        </label>
      </div>

      {visibleChildren.length > 0 && (
        <section className="space-y-5">
          <div className="flex justify-between items-baseline gap-3"><h2 className="text-h3 text-gray-900 dark:text-ink-900">الأقسام الفرعية</h2><span className="text-xs text-gray-500">{visibleChildren.length} قسم</span></div>
          <div className="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2 sm:gap-4">
            {visibleChildren.map((node, index) => <SubcategoryCard key={node.id} node={node} locale={locale} index={index} />)}
          </div>
        </section>
      )}

      <section className="space-y-5" aria-live="polite">
        <div className="flex justify-between items-baseline gap-3"><h2 className="text-h3 text-gray-900 dark:text-ink-900">المنتجات</h2><span className="text-xs text-gray-500">{total} منتج</span></div>
        {productsLoading ? (
          <div className="flex items-center gap-3 py-12 justify-center text-gray-500"><span className="w-6 h-6 rounded-full border-2 border-green-500 border-t-transparent animate-spin" /> جارٍ تحميل المنتجات...</div>
        ) : productError ? (
          <div role="alert" className="card-pad text-center py-12 text-gray-600 dark:text-ink-500">تعذر تحميل المنتجات. تحقق من الاتصال وحاول مجدداً.</div>
        ) : products.length > 0 ? (
          <div className="grid grid-cols-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2 sm:gap-4">
            {products.map((product) => (
              <Link key={product.id} to={`/product/${product.slug}`} className="card-hover group block overflow-hidden rounded-2xl border border-gray-200 dark:border-ink-200 bg-white dark:bg-ink-50">
                <div className="relative aspect-square overflow-hidden">
                  <ProductImage name={localized(product, 'name', 'name_ar', locale)} icon={product.icon} category={localized(product.category, 'name', 'name_ar', locale)} categoryImageUrl={product.category?.image_url} imageBase64={product.image_base64} imageUrl={product.image_url} productId={product.id} showFavorite className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" />
                  {(product.stock === 0 || product.oranos_available === false) && <div className="absolute inset-0 bg-black/50 flex items-center justify-center"><span className="badge-rejected px-3 py-1">{t('common.unavailable')}</span></div>}
                </div>
                <div className="p-2 sm:p-4 space-y-1 sm:space-y-2"><h3 className="font-heading text-[11px] sm:text-sm font-semibold text-gray-900 dark:text-ink-900 line-clamp-2 group-hover:text-green-500">{localized(product, 'name', 'name_ar', locale)}</h3><p className="text-green-500 font-bold tabular-nums">{product.pricing_unavailable ? 'السعر قيد التحديث' : formatPrice(product.price)}</p></div>
              </Link>
            ))}
          </div>
        ) : <div className="card-pad text-center py-12 text-gray-600 dark:text-ink-500">{debouncedSearch ? 'ما لقينا منتجات بهذا الاسم داخل القسم.' : 'ما في منتجات متاحة ضمن هذا القسم حالياً.'}</div>}
        {lastPage > 1 && !productError && (
          <nav className="flex justify-center items-center gap-4" aria-label="صفحات المنتجات">
            <button type="button" className="btn-secondary inline-flex items-center gap-2 disabled:opacity-40" disabled={page <= 1 || productsLoading} onClick={() => setPage((current) => Math.max(1, current - 1))}><ChevronRight className="w-4 h-4" /> السابق</button>
            <span className="text-sm text-gray-600 dark:text-ink-500 tabular-nums">{page} / {lastPage}</span>
            <button type="button" className="btn-secondary inline-flex items-center gap-2 disabled:opacity-40" disabled={page >= lastPage || productsLoading} onClick={() => setPage((current) => Math.min(lastPage, current + 1))}>التالي <ChevronLeft className="w-4 h-4" /></button>
          </nav>
        )}
      </section>
    </PageTransition>
  );
}
