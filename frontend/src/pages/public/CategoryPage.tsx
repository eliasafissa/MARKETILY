import { useEffect, useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { categoryApi, productApi } from '@/api/client';
import ProductImage from '@/components/ProductImage';
import PageTransition from '@/components/PageTransition';
import Breadcrumbs from '@/components/Breadcrumbs';
import { useI18n } from '@/i18n';
import { localized } from '@/utils/localize';
import { categoryEmoji } from '@/utils/categoryEmoji';
import { GENERIC_ICONS } from '@/utils/oranosCategories';

const CATEGORY_ICON: Record<string, string> = {
  gamepad: '🎮', message: '💬', 'credit-card': '💳', wallet: '💰',
  design: '🎨', monitor: '📺', server: '🛡️', 'check-circle': '✅',
  cpu: '🤖', handshake: '🤝', share: '🔗',
};

const reveal = (i: number) => ({
  initial: { opacity: 0, y: 20 },
  animate: { opacity: 1, y: 0 },
  transition: { delay: i * 0.04, duration: 0.4, ease: [0.16, 1, 0.3, 1] as const },
});

function buildBreadcrumbs(category: any, allCategories: any[]): Array<{label: string, link?: string}> {
  const chain: Array<{label: string, link?: string}> = [];
  let current = category;

  // Walk up the parent chain
  while (current) {
    chain.unshift({
      label: current.name_ar ?? current.name ?? 'Unknown',
      link: `/category/${current.slug}`
    });
    if (current.parent_id) {
      current = allCategories.find((c: any) => c.id === current.parent_id);
    } else {
      current = null;
    }
  }

  // Last item is the current page — not clickable
  if (chain.length > 0) {
    delete chain[chain.length - 1].link;
  }

  // Add home and categories as root
  return [
    { label: 'الرئيسية', link: '/' },
    { label: 'الفئات', link: '/categories' },
    ...chain
  ];
}

export default function CategoryPage() {
  const { slug } = useParams<{ slug: string }>();
  const navigate = useNavigate();
  const { t, locale } = useI18n();
  const [all, setAll] = useState<any[]>([]);
  const [category, setCategory] = useState<any | null>(null);
  const [children, setChildren] = useState<any[]>([]);
  const [products, setProducts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!slug) return;
    setLoading(true);

    Promise.all([
      categoryApi.list(),
      categoryApi.show(slug)
    ])
      .then(([listRes, showRes]) => {
        const list = listRes.data.categories ?? [];
        setAll(list);
        
        const cat = showRes.data.category;
        if (!cat) {
          setCategory(null);
          return;
        }
        setCategory(cat);
        setChildren(cat.children ?? []);
        setProducts(cat.products ?? []);
      })
      .catch(console.error)
      .finally(() => setLoading(false));
  }, [slug]);

  if (loading) {
    return (
      <div className="flex items-center justify-center py-24">
        <span className="w-8 h-8 rounded-full border-2 border-green-500 border-t-transparent animate-spin" />
      </div>
    );
  }

  if (!category) {
    return (
      <PageTransition className="space-y-6">
        <h1 className="text-h1">Category not found</h1>
        <Link to="/categories" className="text-green-500">← Back to categories</Link>
      </PageTransition>
    );
  }

  const catName = localized(category, 'name', 'name_ar', locale);
  const filteredChildren = children.filter((child: any) => (child.products_count ?? 0) > 0 || (child.children?.length ?? 0) > 0);
  const showChildren = filteredChildren.length > 0;
  const breadcrumbItems = buildBreadcrumbs(category, all);

  return (
    <PageTransition className="space-y-10">
      {/* Back Button */}
      <button
        onClick={() => navigate(-1)}
        className="mb-4 text-small text-gray-600 dark:text-ink-500 hover:text-green-400 dark:hover:text-green-300 transition-colors flex items-center gap-1.5"
        aria-label={t('product.back')}
      >
        <svg className="w-4 h-4 rtl:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <path d="M19 12H5M12 19l-7-7 7-7" />
        </svg>
        <span>{t('product.back')}</span>
      </button>

      <Breadcrumbs items={breadcrumbItems} />

      {category?.description_ar && (
        <div className="max-w-3xl -mt-6 space-y-1 text-body text-gray-600 dark:text-ink-500 leading-relaxed">
          {localized(category, 'description', 'description_ar', locale).split('\n').map((line: string, i: number) => {
            const trimmed = line.trim();
            const isUrl = /^(https?:\/\/\S+|www\.\S+|linkedin\.com\/\S+)$/i.test(trimmed);
            if (isUrl) {
              const href = /^https?:/i.test(trimmed) ? trimmed : `https://${trimmed}`;
              return (
                <div key={i} dir="ltr" className="text-start">
                  <a href={href} target="_blank" rel="noopener noreferrer" className="text-green-500 hover:underline break-all">
                    {trimmed}
                  </a>
                </div>
              );
            }
            const phoneMatch = trimmed.match(/^(هاتف|هاتف:|الهاتف|Tel|Phone)\s*:?\s*(.+)$/i);
            if (phoneMatch) {
              return (
                <div key={i} className="flex items-center gap-2">
                  <span className="text-gray-500 dark:text-ink-500">هاتف:</span>
                  <a href={`tel:${phoneMatch[2].replace(/\s+/g, '')}`} dir="ltr" className="text-green-500 hover:underline tabular-nums">
                    {phoneMatch[2].trim()}
                  </a>
                </div>
              );
            }
            return trimmed ? <div key={i}>{line}</div> : <div key={i} className="h-2" />;
          })}
        </div>
      )}

      {/* Subcategories section */}
      {showChildren && (
        <section>
          <div className="mb-6">
            <p className="eyebrow mb-2">{t('home.browse') ?? 'Browse'}</p>
            <h2 className="font-heading text-[clamp(1.5rem,3vw,2.25rem)] text-gray-900 dark:text-ink-900">
              {catName}
            </h2>
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            {filteredChildren.map((child, i) => {
              const childName = localized(child, 'name', 'name_ar', locale);
              const emoji = GENERIC_ICONS.includes(child.icon ?? '')
                ? categoryEmoji(childName)
                : (CATEGORY_ICON[child.icon] ?? categoryEmoji(childName));
              return (
                <motion.div key={child.id} {...reveal(i)}>
                  <Link
                    to={`/category/${child.slug}`}
                    className="card-hover group block overflow-hidden h-full bg-white dark:bg-ink-50 rounded-2xl border border-gray-200 dark:border-ink-200 shadow-sm"
                  >
                    <div className="relative p-4 min-h-[130px] flex flex-col">
                      {child.image_url ? (
                        <img
                          src={child.image_url}
                          alt={childName}
                          loading="lazy"
                          className="w-11 h-11 rounded-xl object-cover mb-auto transition-transform duration-700 group-hover:scale-110"
                        />
                      ) : (
                        <div className="w-11 h-11 text-xl rounded-xl bg-gray-100 dark:bg-ink-100 flex items-center justify-center mb-auto">
                          <span aria-hidden="true">{emoji}</span>
                        </div>
                      )}
                      <div className="mt-3">
                        <h3 className="font-heading text-sm font-semibold text-gray-900 dark:text-ink-900 group-hover:text-green-500 transition-colors line-clamp-2">
                          {childName}
                        </h3>
                      </div>
                    </div>
                  </Link>
                </motion.div>
              );
            })}
          </div>
        </section>
      )}

      {/* Products section */}
      {products.length > 0 && (
        <section>
          <div className="mb-6">
            <p className="eyebrow mb-2">{t('home.hotRightNow') ?? 'Products'}</p>
            <h2 className="font-heading text-[clamp(1.5rem,3vw,2.25rem)] text-gray-900 dark:text-ink-900">
              {showChildren ? `${t('home.browse') ?? 'Browse'} ${catName}` : catName}
            </h2>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {products.map((p, i) => (
              <motion.div key={p.id} {...reveal(i)} className="h-full">
<Link
                  to={`/product/${p.slug}`}
                  className="card-hover group block overflow-hidden h-full bg-white dark:bg-ink-50 rounded-2xl border border-gray-200 dark:border-ink-200 shadow-sm"
                >
                  <div className="aspect-square overflow-hidden relative">
                    <ProductImage
                       name={localized(p, 'name', 'name_ar', locale)}
                       icon={p.icon}
                       category={localized(p.category, 'name', 'name_ar', locale)}
                       categoryImageUrl={p.category?.image_url}
                       imageBase64={p.image_base64}
                       imageUrl={p.image_url}
                       className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                       productId={p.id}
                       showFavorite
                     />
                    {(p.stock === 0 || p.oranos_available === false) && (
                      <div className="absolute inset-0 bg-black/50 flex items-center justify-center z-10">
                        <span className="badge-rejected text-base px-4 py-2">{t('common.unavailable')}</span>
                      </div>
                    )}
                  </div>
                  <div className="p-5">
                    <h3 className="font-heading text-sm font-semibold text-gray-900 dark:text-ink-900 group-hover:text-green-500 transition-colors line-clamp-2 mb-2">
                      {localized(p, 'name', 'name_ar', locale)}
                    </h3>
                    <p className="text-micro text-gray-500 dark:text-ink-500 line-clamp-2 mb-3">
                      {localized(p, 'description', 'description_ar', locale)}
                    </p>
                  </div>
                </Link>
              </motion.div>
            ))}
          </div>
        </section>
      )}

      {!showChildren && products.length === 0 && (
        <div className="card-pad text-center py-16">
          <p className="text-body text-gray-500 dark:text-ink-500">
            {t('common.noResults') ?? 'No products here yet.'}
          </p>
        </div>
      )}
    </PageTransition>
  );
}
