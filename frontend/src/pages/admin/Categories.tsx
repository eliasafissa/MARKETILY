import { useEffect, useMemo, useState } from 'react';
import { ChevronDown, ChevronLeft, ChevronRight, Image as ImageIcon, Search, SlidersHorizontal, X } from 'lucide-react';
import toast from 'react-hot-toast';
import { adminCategoryApi } from '@/api/client';
import CategoryModal from '@/components/CategoryModal';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';

interface CategoryRow {
  id: number;
  is_visible?: boolean;
  name: string;
  name_ar?: string;
  slug: string;
  type: string;
  image_url?: string | null;
  admin_image_url?: string | null;
  oranos_image_url?: string | null;
  sort_order?: number;
  parent_id?: number | null;
  children?: CategoryRow[];
  products_count?: number;
  subtree_products_count?: number;
  subtree_categories_count?: number;
}

function flatten(nodes: CategoryRow[], depth = 0): Array<CategoryRow & { depth: number }> {
  return nodes.flatMap((node) => [{ ...node, depth }, ...flatten(node.children ?? [], depth + 1)]);
}

export default function AdminCategories() {
  const { locale, t } = useI18n();
  const [categories, setCategories] = useState<CategoryRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<CategoryRow | undefined>();
  const [search, setSearch] = useState('');
  const [sort, setSort] = useState<'tree' | 'az' | 'za'>('tree');
  const [expanded, setExpanded] = useState<Set<number>>(new Set());
  const [page, setPage] = useState(1);

  const reload = () => {
    setLoading(true);
    setError(null);
    adminCategoryApi.list()
      .then(({ data }) => setCategories(data.categories ?? []))
      .catch((err: any) => setError(err.response?.data?.message ?? t('common.failed')))
      .finally(() => setLoading(false));
  };

  useEffect(() => { reload(); }, []);
  useEffect(() => setPage(1), [search, sort, expanded]);

  const all = useMemo(() => flatten(categories), [categories]);
  const name = (c: CategoryRow) => locale === 'ar' ? (c.name_ar || c.name) : (c.name || c.name_ar || '');
  const visible = useMemo(() => {
    const term = search.trim().toLocaleLowerCase();
    const collator = new Intl.Collator(locale === 'ar' ? 'ar' : 'en', { numeric: true, sensitivity: 'base' });
    const result = all.filter((c) => {
      if (term) return [c.name, c.name_ar, c.slug].some((v) => (v || '').toLocaleLowerCase().includes(term));
      if (sort !== 'tree') return true;
      const parents = new Map(all.map((n) => [n.id, n]));
      let parent = c.parent_id;
      const seen = new Set<number>();
      while (parent && !seen.has(parent)) {
        seen.add(parent);
        if (!expanded.has(parent)) return false;
        parent = parents.get(parent)?.parent_id;
      }
      return true;
    });
    if (sort === 'tree') return result;
    return result.sort((a, b) => (sort === 'az' ? 1 : -1) * collator.compare(name(a), name(b)));
  }, [all, search, sort, expanded, locale]);

  const displayed = visible.slice((page - 1) * 40, page * 40);
  const totalPages = Math.max(1, Math.ceil(visible.length / 40));
  const totalProducts = categories.reduce((sum, c) => sum + (c.subtree_products_count ?? 0), 0);
  const toggle = (id: number) => setExpanded((old) => {
    const next = new Set(old);
    if (next.has(id)) next.delete(id); else next.add(id);
    return next;
  });

  const [changingVisibility, setChangingVisibility] = useState<number | null>(null);
  const changeVisibility = async (c: CategoryRow) => {
    if (changingVisibility !== null) return;
    setChangingVisibility(c.id);
    try {
      await adminCategoryApi.setVisibility(c.id, c.is_visible === false);
      toast.success(c.is_visible === false ? 'تم إظهار القسم في المتجر.' : 'تم إخفاء القسم من المتجر.');
      reload();
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? 'تعذّر تغيير ظهور القسم. يرجى المحاولة بعد قليل.');
    } finally {
      setChangingVisibility(null);
    }
  };

  const remove = async (category: CategoryRow) => {
    if (!confirm(t('admin.categoryDeleteConfirm', { name: name(category) }))) return;
    try {
      await adminCategoryApi.delete(category.id);
      toast.success(t('admin.categoryDeleted'));
      reload();
    } catch (err: any) { toast.error(err.response?.data?.message ?? t('common.failed')); }
  };

  return <PageTransition className="marketlly-admin-categories min-w-0 space-y-4 sm:space-y-6">
    <div className="flex flex-wrap gap-4 items-end justify-between">
      <div><p className="eyebrow mb-1">MARKETLLY · ADMIN</p><h1 className="text-h1 text-gray-900 dark:text-ink-900">إدارة الأقسام والصور</h1><p className="text-small text-gray-500 dark:text-ink-500 mt-1">تظهر صورة الأدمن أولاً، وإلا صورة Oranos إن توفرت.</p></div>
      <button className="btn-accent" onClick={() => { setEditing(undefined); setShowModal(true); }}>+ {t('admin.newCategory')}</button>
    </div>

    <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 sm:gap-3">
      <div className="card-pad"><p className="text-xs text-gray-500 dark:text-ink-500">الأقسام الرئيسية</p><p className="text-2xl font-bold text-gray-900 dark:text-ink-900 tabular-nums">{categories.length}</p></div>
      <div className="card-pad"><p className="text-xs text-gray-500 dark:text-ink-500">كل الأقسام والفرعية</p><p className="text-2xl font-bold text-gray-900 dark:text-ink-900 tabular-nums">{all.length}</p></div>
      <div className="card-pad"><p className="text-xs text-gray-500 dark:text-ink-500">المنتجات في جميع الأقسام</p><p className="text-2xl font-bold text-gray-900 dark:text-ink-900 tabular-nums">{totalProducts}</p></div>
    </div>

    <div className="flex min-w-0 flex-col gap-3 lg:flex-row rounded-2xl p-4 bg-white dark:bg-ink-50 border border-gray-200 dark:border-ink-200">
      <label className="relative min-w-0 flex-1"><span className="sr-only">بحث بكل الأقسام</span><Search className="absolute start-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" /><input type="search" className="input w-full ps-11 pe-10" placeholder="ابحث في كل الأقسام الرئيسية والفرعية..." value={search} maxLength={100} onChange={(e) => setSearch(e.target.value)} />{search && <button type="button" aria-label="مسح البحث" onClick={() => setSearch('')} className="absolute end-3 top-1/2 -translate-y-1/2 text-gray-500"><X className="w-4 h-4" /></button>}</label>
      <label className="flex min-w-0 flex-wrap items-center gap-2 text-gray-600 dark:text-ink-500 text-sm"><SlidersHorizontal className="w-4 h-4" />ترتيب<select className="input min-w-0 w-full sm:w-auto sm:min-w-[160px]" value={sort} onChange={(e) => setSort(e.target.value as typeof sort)}><option value="tree">حسب شجرة Oranos</option><option value="az">أبجدي أ ← ي</option><option value="za">أبجدي ي ← أ</option></select></label>
      <button type="button" className="btn-secondary btn-sm" onClick={() => setExpanded(expanded.size === all.length ? new Set() : new Set(all.map((cat) => cat.id)))}>{expanded.size === all.length ? 'طي الكل' : 'عرض كل الفروع'}</button>
    </div>

    {error && <div className="card-pad text-center text-red-500">{error}<button onClick={reload} className="btn-secondary btn-sm ms-3">إعادة المحاولة</button></div>}
    {loading ? <div className="card-pad text-center">جاري تحميل الأقسام...</div> : <div className="space-y-2">
      <p className="text-xs text-gray-500 dark:text-ink-500">{visible.length} قسم معروض من أصل {all.length}</p>
      {displayed.map((c) => <div className="marketlly-category-row card-pad flex min-w-0 flex-col items-stretch gap-3 sm:flex-row sm:items-center" key={c.id}>
        <div className="marketlly-category-info flex min-w-0 flex-1 items-center gap-2" style={{ paddingInlineStart: search || sort !== 'tree' ? 0 : Math.min(c.depth, 7) * 12 }}>
          {(c.children?.length ?? 0) > 0 && sort === 'tree' && !search ? <button type="button" className="p-1 shrink-0 text-gray-500 hover:text-green-500" aria-label={expanded.has(c.id) ? 'طي الأقسام الفرعية' : 'إظهار الأقسام الفرعية'} onClick={() => toggle(c.id)}>{expanded.has(c.id) ? <ChevronDown className="w-4 h-4" /> : <ChevronLeft className="w-4 h-4 rtl:rotate-0 ltr:rotate-180" />}</button> : <span className="w-6 shrink-0" />}
          <div className="w-12 h-12 shrink-0 rounded-xl border border-gray-200 dark:border-ink-200 bg-green-500/10 flex items-center justify-center overflow-hidden">
            {c.image_url ? <img src={c.image_url} alt="" className="w-full h-full object-cover" loading="lazy" /> : <ImageIcon size={24} aria-hidden="true" />}
          </div>
          <div className="min-w-0 flex-1"><p className="text-sm font-semibold text-gray-900 dark:text-ink-900 truncate">{name(c)}</p><p className="text-xs text-gray-500 dark:text-ink-500">{c.subtree_categories_count ?? 0} قسم فرعي · {c.subtree_products_count ?? 0} منتج</p><p className={`text-xs font-semibold ${c.is_visible === false ? 'text-red-600' : 'text-green-600'}`}>{c.is_visible === false ? 'مخفي من المتجر' : (c.subtree_products_count ?? 0) === 0 ? 'ظاهر إدارياً · فارغ ومخفي تلقائياً عن الزبائن' : 'ظاهر في المتجر'}</p><p className="text-xs text-gray-500 dark:text-ink-500">{c.admin_image_url ? 'صورة مخصّصة' : c.oranos_image_url ? 'صورة Oranos' : 'بدون صورة'} · #{c.id}</p></div>
        </div>
        <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end"><button type="button" disabled={changingVisibility !== null} onClick={() => changeVisibility(c)} className={`btn-sm rounded-lg px-3 py-2 font-semibold border ${c.is_visible === false ? 'border-green-500 text-green-700' : 'border-amber-500 text-amber-700'}`}>{changingVisibility === c.id ? 'جارٍ الحفظ...' : c.is_visible === false ? 'إظهار القسم' : 'إخفاء القسم'}</button><button onClick={() => { setEditing(c); setShowModal(true); }} className="btn-secondary btn-sm inline-flex items-center gap-1"><ImageIcon className="w-3.5 h-3.5" />تعديل / صورة</button><button onClick={() => remove(c)} className="btn-danger btn-sm">{t('common.delete')}</button></div>
      </div>)}
      {!visible.length && <div className="card-pad text-center text-gray-500 dark:text-ink-500">لا توجد أقسام مطابقة للبحث.</div>}
      {totalPages > 1 && <div className="flex justify-center items-center gap-3 pt-3"><button className="btn-secondary btn-sm" disabled={page <= 1} onClick={() => setPage(page - 1)}><ChevronRight className="w-4 h-4" /></button><span className="text-sm text-gray-500">{page} / {totalPages}</span><button className="btn-secondary btn-sm" disabled={page >= totalPages} onClick={() => setPage(page + 1)}><ChevronLeft className="w-4 h-4" /></button></div>}
    </div>}

    {showModal && <CategoryModal category={editing as any} onClose={() => setShowModal(false)} onSaved={() => { toast.success(editing ? t('admin.categoryUpdated') : t('admin.newCategoryCreated')); reload(); }} />}
  </PageTransition>;
}
