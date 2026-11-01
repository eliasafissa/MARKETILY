// HAIDAR-V5-CATEGORY: consistent nested cards, icon fallback and no motion.
import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Search, X, SlidersHorizontal, Package } from 'lucide-react';
import { categoryApi } from '@/api/client';
import PageTransition from '@/components/PageTransition';
import Breadcrumbs from '@/components/Breadcrumbs';
import { useI18n } from '@/i18n';
import type { Language } from '@/i18n';
import { localized } from '@/utils/localize';

type SortOrder = 'default' | 'az' | 'za';

interface CategoryNode {
  id: number;
  name?: string;
  name_ar?: string;
  slug: string;
  image_url?: string | null;
  children?: CategoryNode[];
  subtree_products_count?: number;
  subtree_categories_count?: number;
}

const englishCollator = new Intl.Collator('en', { numeric: true, sensitivity: 'base' });

function flatten(nodes: CategoryNode[]): CategoryNode[] {
  return nodes.flatMap((node) => [node, ...flatten(node.children ?? [])]);
}

function matches(category: CategoryNode, query: string): boolean {
  return [category.name, category.name_ar].some((name) => (name ?? '').toLocaleLowerCase().includes(query));
}

function sortCategories(categories: CategoryNode[], order: SortOrder): CategoryNode[] {
  if (order === 'default') return categories;
  return [...categories].sort((a, b) => {
    const comparison = englishCollator.compare(a.name ?? a.name_ar ?? '', b.name ?? b.name_ar ?? '');
    return (order === 'az' ? comparison : -comparison) || a.id - b.id;
  });
}

function safeImageUrl(url?: string | null): string | undefined {
  if (!url) return undefined;
  if (url.startsWith('https://') || /^http:\/\/(localhost|127\.0\.0\.1):8000\/storage\//.test(url)) return url;
  return undefined;
}

function CategoryCard({ category, locale, index }: { category: CategoryNode; locale: Language; index: number }) {
  const [imageError, setImageError] = useState(false);
  const name = localized(category, 'name', 'name_ar', locale);
  const image = safeImageUrl(category.image_url);

  return (
    <motion.div
      data-category-index={index} className="h-full"
    >
      <Link
        to={`/category/${category.slug}`}
        className="card-hover group block h-full overflow-hidden bg-white dark:bg-ink-50 rounded-2xl border border-gray-200 dark:border-ink-200 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-green-500"
      >
        <div className="relative isolate min-h-[195px] sm:min-h-[220px] flex flex-col justify-end overflow-hidden">
          {image && !imageError ? (
            <img src={image} alt="" loading="lazy" onError={() => setImageError(true)} className="pointer-events-none absolute inset-0 w-full h-full object-cover object-center bg-gray-900 transition-transform duration-500 group-hover:scale-105" />
          ) : <div className="absolute inset-0 bg-gray-100 dark:bg-ink-100" aria-hidden="true" />}
          {image && !imageError && <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/40 to-black/5" aria-hidden="true" />}
          <div className="relative z-10 p-4">
            {(!image || imageError) && <Package size={32} className="mb-3 text-green-600" aria-hidden="true" />}
            <h2 className={`font-heading text-base sm:text-lg font-bold line-clamp-2 ${image && !imageError ? 'text-white' : 'text-gray-900 dark:text-ink-900 group-hover:text-green-500'}`}>{name}</h2>
            <p className={`mt-2 text-xs ${image && !imageError ? 'text-white/90' : 'text-gray-500 dark:text-ink-500'}`}>
              {category.subtree_categories_count ?? 0} قسم فرعي · {category.subtree_products_count ?? 0} منتج
            </p>
          </div>
        </div>
      </Link>
    </motion.div>
  );
}

export default function Categories() {
  const { t, locale } = useI18n();
  const [categories, setCategories] = useState<CategoryNode[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [search, setSearch] = useState('');
  const [sort, setSort] = useState<SortOrder>('default');

  useEffect(() => {
    let cancelled = false;
    categoryApi.list()
      .then((response) => {
        if (!cancelled) setCategories(response.data.categories ?? []);
      })
      .catch(() => {
        if (!cancelled) setError(true);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => { cancelled = true; };
  }, []);

  const allCategories = useMemo(() => flatten(categories), [categories]);
  const visible = useMemo(() => {
    const query = search.trim().toLocaleLowerCase();
    const candidates = query ? allCategories.filter((category) => matches(category, query)) : categories;
    return sortCategories(candidates, sort);
  }, [allCategories, categories, search, sort]);

  if (loading) {
    return <div className="flex justify-center py-24"><span className="w-8 h-8 rounded-full border-2 border-green-500 border-t-transparent animate-spin" /></div>;
  }

  return (
    <PageTransition className="space-y-8">
      <Breadcrumbs items={[{ label: t('nav.home'), link: '/' }, { label: t('nav.categories') }]} />
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="eyebrow text-green-500 mb-2">MARKETLY</p>
          <h1 className="text-h1 text-gray-900 dark:text-ink-900">{t('home.categories')}</h1>
        </div>
        <p className="text-sm text-gray-500 dark:text-ink-500">
          {allCategories.length} قسم · {categories.reduce((total, category) => total + (category.subtree_products_count ?? 0), 0)} منتج
        </p>
      </div>

      {/* Standalone search field; sorting lives at the opposite end. */}
      <div className="flex flex-col md:flex-row md:items-center gap-3 md:gap-5">
        <label className="relative block flex-1 min-w-0">
          <span className="sr-only">البحث في جميع الأقسام الرئيسية والفرعية</span>
          <Search className="absolute start-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" aria-hidden="true" />
          <input
            type="search"
            value={search}
            maxLength={100}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="ابحث عن قسم أو لعبة..."
            className="w-full h-12 rounded-xl border border-gray-200 dark:border-ink-200 bg-white dark:bg-ink-50 text-gray-900 dark:text-ink-900 ps-12 pe-11 outline-none placeholder:text-gray-400 focus:border-green-500 focus:ring-2 focus:ring-green-500/20 transition-colors"
          />
          {search && <button type="button" onClick={() => setSearch('')} aria-label="مسح البحث" className="absolute end-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-green-500"><X className="w-4 h-4" /></button>}
        </label>
        <label className="flex items-center gap-2 md:shrink-0 text-sm text-gray-500 dark:text-ink-500">
          <SlidersHorizontal className="w-4 h-4" aria-hidden="true" />
          <span>ترتيب</span>
          <select value={sort} onChange={(event) => setSort(event.target.value as SortOrder)} aria-label="ترتيب الأقسام حسب الاسم الإنجليزي" className="h-12 w-full md:w-[158px] px-3 rounded-xl bg-white dark:bg-ink-50 border border-gray-200 dark:border-ink-200 text-gray-900 dark:text-ink-900 focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20">
            <option value="default">افتراضي</option>
            <option value="az">A–Z</option>
            <option value="za">Z–A</option>
          </select>
        </label>
      </div>

      {error ? (
        <div role="alert" className="card-pad text-center py-12 text-gray-500">تعذر تحميل الأقسام. حدّث الصفحة وحاول مجدداً.</div>
      ) : (
        <>
          {search.trim() && <p className="text-sm text-gray-500 dark:text-ink-500">{visible.length} نتيجة بحث</p>}
          {visible.length > 0 ? (
            <div className="grid grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6">
              {visible.map((category, index) => <CategoryCard key={category.id} category={category} locale={locale} index={index} />)}
            </div>
          ) : (
            <div className="card-pad text-center py-16 text-gray-500">ما لقينا أقسام مطابقة. جرّب اسم ثاني.</div>
          )}
        </>
      )}
    </PageTransition>
  );
}
