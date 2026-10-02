import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { categoryApi } from '@/api/client';
import PageTransition from '@/components/PageTransition';
import Breadcrumbs from '@/components/Breadcrumbs';
import { useI18n } from '@/i18n';
import { localized } from '@/utils/localize';
import { categoryEmoji } from '@/utils/categoryEmoji';
import { ORANOS_TOP_CATEGORIES, GENERIC_ICONS } from '@/utils/oranosCategories';

const CATEGORY_ICON: Record<string, string> = {
  gamepad: '🎮', message: '💬', 'credit-card': '💳', wallet: '💰',
  design: '🎨', monitor: '📺', server: '🛡️', 'check-circle': '✅',
  cpu: '🤖', handshake: '🤝', share: '🔗',
};

export default function Categories() {
  const { t, locale } = useI18n();
  const [categories, setCategories] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    categoryApi.list()
      .then((res) => {
        const all = res.data.categories ?? [];
        const rank = (c: any) => {
          const i1 = ORANOS_TOP_CATEGORIES.indexOf(c.name ?? '');
          const i2 = ORANOS_TOP_CATEGORIES.indexOf(c.name_ar ?? '');
          return Math.min(i1 === -1 ? 999 : i1, i2 === -1 ? 999 : i2);
        };
        const isTop = (c: any) => ORANOS_TOP_CATEGORIES.includes(c.name ?? '') || ORANOS_TOP_CATEGORIES.includes(c.name_ar ?? '');
        const filtered = all
          .filter((c: any) => rank(c) < 999 || (c.children?.length ?? 0) > 0 || (c.products_count ?? 0) > 0)
          .filter((c: any) => isTop(c) || (c.products_count ?? 0) > 0 || (c.children?.length ?? 0) > 0)
          .sort((a: any, b: any) => {
            const ra = rank(a);
            const rb = rank(b);
            if (ra !== rb) return ra - rb;
            return (b.products_count ?? 0) - (a.products_count ?? 0);
          });
        setCategories(filtered);
      })
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
      <Breadcrumbs items={[{ label: t('nav.home'), link: '/' }, { label: t('nav.categories') }]} />
      <div className="flex items-end justify-between">
        <h1 className="text-h1 text-gray-900 dark:text-ink-900">{t('home.categories')}</h1>
        <p className="text-small text-gray-500 dark:text-ink-500">{categories.length}</p>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-3 gap-6">
        {categories.map((cat, i) => {
          const name = localized(cat, 'name', 'name_ar', locale);
          const emoji = GENERIC_ICONS.includes(cat.icon ?? '')
            ? categoryEmoji(name)
            : (CATEGORY_ICON[cat.icon] ?? categoryEmoji(name));
          return (
            <motion.div
              key={cat.id}
              initial={{ opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: i * 0.04, duration: 0.35 }}
            >
              <Link
                to={`/category/${cat.slug}`}
                className="card-hover group block overflow-hidden h-full bg-white dark:bg-ink-50 rounded-2xl border border-gray-200 dark:border-ink-200 shadow-sm"
              >
                <div className="relative p-4 min-h-[220px] flex flex-col">
                  {cat.image_url ? (
                    <img
                      src={cat.image_url}
                      alt={name}
                      loading="lazy"
                      className="w-20 h-20 rounded-xl object-cover mb-auto transition-transform duration-700 group-hover:scale-110"
                    />
                  ) : (
                    <div className="w-20 h-20 text-4xl rounded-xl bg-gray-100 dark:bg-ink-100 flex items-center justify-center mb-auto">
                      <span aria-hidden="true">{emoji}</span>
                    </div>
                  )}
                  <div className="mt-4">
                    <h3 className="font-heading text-lg font-bold text-gray-900 dark:text-ink-900 group-hover:text-green-500 transition-colors line-clamp-2">
                      {name}
                    </h3>
                  </div>
                </div>
              </Link>
            </motion.div>
          );
        })}
      </div>
    </PageTransition>
  );
}
