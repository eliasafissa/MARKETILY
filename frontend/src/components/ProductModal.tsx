import { useState, useEffect } from 'react';
import { adminProductApi, adminCategoryApi } from '@/api/client';
import toast from 'react-hot-toast';
import Modal from './Modal';
import ImageUploader from './ImageUploader';
import IconPicker from './IconPicker';
import Button from './Button';

interface ProductModalProps {
  product?: any;
  onClose: () => void;
  onSaved: () => void;
}

import { useI18n } from '@/i18n';

export default function ProductModal({ product, onClose, onSaved }: ProductModalProps) {
  const { t } = useI18n();
  const isProvider = Boolean(product?.oranos_product_id);
  const providerCost = Number(product?.base_price ?? 0);
  const [categories, setCategories] = useState<any[]>([]);
  const flatten = (roots: any[], depth = 0): any[] => roots.flatMap((cat) => [
    { ...cat, indent: '— '.repeat(depth) }, ...flatten(cat.children ?? [], depth + 1),
  ]);
  const [catError, setCatError] = useState(false);
  const [catQuery, setCatQuery] = useState('');
  const [form, setForm] = useState({
    name: product?.name ?? '',
    name_ar: product?.name_ar ?? '',
    description: product?.description ?? '',
    description_ar: product?.description_ar ?? '',
    price: product?.price ?? '',
    pricing_mode: product?.pricing_mode ?? 'auto',
    stock: product?.stock ?? 0,
    category_id: product?.category_id ?? '',
    type: product?.type ?? 'manual',
    is_active: product?.is_active ?? true,
    image_base64: '',
    remove_admin_image: false,
    icon: product?.icon ?? '',
    params: (product?.type === 'manual' && Array.isArray(product?.params) ? product.params.join('\n') : ''),
  });
  const [saving, setSaving] = useState(false);
  const isEdit = !!product;

  useEffect(() => {
    adminCategoryApi.selection()
      .then((r) => setCategories(flatten(r.data.categories ?? [])))
      .catch(() => { setCatError(true); setCategories([]); });
  }, []);

  const set = (k: string) => (v: unknown) => setForm((p) => ({ ...p, [k]: v }));

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!isProvider && (!form.name_ar || !form.name_ar.trim())) {
      toast.error('الاسم بالعربية مطلوب');
      setSaving(false);
      return;
    }
    if (!isProvider && !form.category_id) {
      toast.error('الفئة مطلوبة');
      setSaving(false);
      return;
    }
    const priceVal = parseFloat(form.price as string);
    if (isNaN(priceVal) || priceVal <= 0) {
      toast.error('السعر مطلوب');
      setSaving(false);
      return;
    }
    const stockVal = parseInt(form.stock as string);
    if (!isProvider && (isNaN(stockVal) || stockVal < 0)) {
      toast.error('المخزون مطلوب');
      setSaving(false);
      return;
    }
    if (isProvider && form.is_active && (!Number.isFinite(providerCost) || providerCost <= 0)) {
      toast.error('تكلفة Oranos غير معروفة؛ لا يمكن تعديل السعر حالياً');
      return;
    }
    setSaving(true);
    try {
      const payload = {
        ...form,
        params: form.type === 'manual' ? form.params.split('\n').map((x: string) => x.trim()).filter(Boolean) : undefined,
        price: priceVal,
        stock: stockVal,
        image_base64: form.image_base64 || undefined,
      };
      if (!payload.name || !payload.name.trim()) {
        payload.name = payload.name_ar || '';
      }
      if (!payload.description || !payload.description.trim()) {
        payload.description = payload.description_ar || '';
      }
      if (isEdit) {
        await adminProductApi.update(product.id, isProvider ? {
          is_active: payload.is_active,
          icon: payload.icon,
          image_base64: form.image_base64 || undefined,
          remove_admin_image: form.remove_admin_image,
        } : payload);
      } else {
        await adminProductApi.create(payload);
      }
      onSaved();
      onClose();
    } catch (err: any) {
      const validation = err.response?.data?.errors?.price?.[0];
      toast.error(validation ?? err.response?.data?.message ?? 'فشل حفظ المنتج');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      isOpen={true}
      onClose={onClose}
      title={isEdit ? 'تعديل المنتج' : 'منتج جديد'}
      size="xl"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="label">اسم المنتج بالعربية</label>
              <input
                className="input"
                dir="rtl"
                value={form.name_ar}
                onChange={(e) => set('name_ar')(e.target.value)}
                disabled={isProvider}
                placeholder="اسم المنتج"
              />
            </div>
            <div>
              <label className="label">الفئة *</label>
              <input type="search" className="input mb-2" value={catQuery}
                onChange={(e) => setCatQuery(e.target.value)} placeholder="ابحث عن فئة رئيسية أو فرعية" />
              <select
                className="input"
                value={form.category_id}
                disabled={isProvider}
                onChange={(e) => set('category_id')(e.target.value)}
                required
              >
                <option value="">اختر الفئة…</option>
                {categories.filter((c) => !catQuery.trim() || `${c.name_ar ?? ''} ${c.name ?? ''}`.toLocaleLowerCase().includes(catQuery.trim().toLocaleLowerCase()) || String(c.id) === String(form.category_id)).map((c) => (
                  <option key={c.id} value={c.id}>{c.indent}{c.name_ar || c.name}</option>
                ))}
              </select>
              {catError && <p className="text-micro text-status-rejected mt-1">فشل تحميل الفئات.</p>}
            </div>
          </div>
          <div>
            <label className="label">{t('admin.descriptionAr')}</label>
            <textarea
              className="input"
              rows={3}
              dir="rtl"
              value={form.description_ar}
              onChange={(e) => set('description_ar')(e.target.value)}
              disabled={isProvider}
              placeholder="وصف المنتج"
            />
          </div>
          {isProvider && (
            <div className="rounded-lg border border-gray-200 dark:border-ink-200 p-3 space-y-3">
              <div className="flex flex-wrap items-center gap-4">
                <span className="text-sm text-gray-600 dark:text-ink-500">تكلفة Oranos الحالية: <strong className="tabular-nums">${providerCost.toFixed(4)}</strong></span>
                <span className="text-sm text-green-500">التسعير تلقائي بالنِسبة العامة من إعدادات الأدمن</span>
              </div>
              <p className="text-xs text-gray-500 dark:text-ink-500">تعديل السعر بشكل فردي متاح للمنتجات اليدوية فقط. تحديث الأسعار مرتبط بنسبة الربح العامة.</p>
            </div>
          )}
          <div className="grid grid-cols-3 gap-4">
            <div>
              <label className="label">السعر (USD) *</label>
              <input
                type="number"
                step="0.0001"
                min={isProvider ? Math.max(0.0001, providerCost) : 0.0001}
                className="input"
                value={form.price}
                onChange={(e) => set('price')(e.target.value)}
                disabled={isProvider}
                required
                placeholder="0.00"
              />
            </div>
            <div>
              <label className="label">المخزون *</label>
              <input
                type="number"
                min="0"
                className="input"
                value={form.stock}
                onChange={(e) => set('stock')(e.target.value)}
                disabled={isProvider}
                required
              />
            </div>
            <div>
              <label className="label">النوع</label>
              <select
                className="input"
                value={form.type}
                onChange={(e) => set('type')(e.target.value)}
                disabled={isProvider}
              >
                <option value="auto" disabled={!product?.oranos_product_id}>توصيل تلقائي (Oranos فقط)</option>
                <option value="manual">طلب يدوي</option>
              </select>
            </div>
          </div>
          {form.type === 'manual' && (
            <div>
              <label className="label">حقول الطلب اليدوي (كل حقل بسطر)</label>
              <textarea className="input" rows={4} value={form.params}
                onChange={(e) => set('params')(e.target.value)}
                placeholder={'اسم الحساب\nرابط الخدمة\nملاحظات'} />
            </div>
          )}
          <div className="flex items-center gap-2">
            <input
              type="checkbox"
              id="is_active"
              className="accent-green-500"
              checked={form.is_active}
              onChange={(e) => set('is_active')(e.target.checked)}
            />
            <label htmlFor="is_active" className="text-sm text-gray-800 dark:text-ink-800">
              مفعّل (ظاهر للعملاء)
            </label>
          </div>
          <div>
            <label className="label">صورة المنتج</label>
            <ImageUploader
              value={form.image_base64 || (!form.remove_admin_image ? product?.admin_image_url ?? '' : '')}
              onChange={(v) => setForm((prev) => ({ ...prev, image_base64: v, remove_admin_image: !v && !!product?.admin_image_url }))}
            />
            <p className="text-xs text-gray-500 dark:text-ink-500 mt-2">صورة الأدمن تظهر أولاً؛ إذا ما رفعت صورة أو أزلتها رح تظهر صورة Oranos.</p>
            {product?.oranos_image_url && <div className="flex gap-2 items-center mt-2"><img className="w-10 h-10 rounded-lg object-cover" src={product.oranos_image_url} alt="صورة Oranos" /><span className="text-xs text-gray-500 dark:text-ink-500">صورة Oranos الاحتياطية</span></div>}
          </div>
          <div>
            <label className="label">أيقونة بديلة</label>
            <p className="text-micro text-gray-600 dark:text-ink-500 mb-2">
              تُعرض عندما لا توجد صورة للمنتج. اتركها فارغة للاختيار التلقائي من الاسم.
            </p>
            <IconPicker value={form.icon} onChange={(v) => set('icon')(v)} />
            {form.icon && (
              <p className="text-micro text-gray-600 dark:text-ink-500 mt-2">
                المختار: <span className="font-mono">{form.icon}</span>
              </p>
            )}
          </div>
          <div className="flex gap-3 pt-2">
            <Button type="submit" variant="accent" className="flex-1" loading={saving}>
              {isEdit ? 'حفظ التعديلات' : 'إنشاء المنتج'}
            </Button>
            <Button type="button" variant="secondary" onClick={onClose}>
              إلغاء
            </Button>
          </div>
        </form>
    </Modal>
  );
}
