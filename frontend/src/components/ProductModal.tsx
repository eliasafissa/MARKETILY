import { useState, useEffect } from 'react';
import { adminProductApi, categoryApi } from '@/api/client';
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
  const [categories, setCategories] = useState<any[]>([]);
  const [catError, setCatError] = useState(false);
  const [form, setForm] = useState({
    name: product?.name ?? '',
    name_ar: product?.name_ar ?? '',
    description: product?.description ?? '',
    description_ar: product?.description_ar ?? '',
    price: product?.price ?? '',
    stock: product?.stock ?? 0,
    category_id: product?.category_id ?? '',
    type: product?.type ?? 'auto',
    is_active: product?.is_active ?? true,
    image_base64: product?.image_base64 ?? '',
    icon: product?.icon ?? '',
  });
  const [saving, setSaving] = useState(false);
  const isEdit = !!product;

  useEffect(() => {
    categoryApi.list()
      .then((r) => setCategories(r.data.categories ?? []))
      .catch(() => { setCatError(true); setCategories([]); });
  }, []);

  const set = (k: string) => (v: unknown) => setForm((p) => ({ ...p, [k]: v }));

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.name_ar || !form.name_ar.trim()) {
      toast.error('الاسم بالعربية مطلوب');
      setSaving(false);
      return;
    }
    if (!form.category_id) {
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
    if (isNaN(stockVal) || stockVal < 0) {
      toast.error('المخزون مطلوب');
      setSaving(false);
      return;
    }
    setSaving(true);
    try {
      const payload = {
        ...form,
        price: priceVal,
        stock: stockVal,
      };
      if (!payload.name || !payload.name.trim()) {
        payload.name = payload.name_ar || '';
      }
      if (!payload.description || !payload.description.trim()) {
        payload.description = payload.description_ar || '';
      }
      if (isEdit) {
        await adminProductApi.update(product.id, payload);
      } else {
        await adminProductApi.create(payload);
      }
      onSaved();
      onClose();
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? 'فشل حفظ المنتج');
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
                placeholder="اسم المنتج"
              />
            </div>
            <div>
              <label className="label">الفئة *</label>
              <select
                className="input"
                value={form.category_id}
                onChange={(e) => set('category_id')(e.target.value)}
                required
              >
                <option value="">اختر الفئة…</option>
                {categories.map((c) => (
                  <option key={c.id} value={c.id}>{c.name}</option>
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
              placeholder="وصف المنتج"
            />
          </div>
          <div className="grid grid-cols-3 gap-4">
            <div>
              <label className="label">السعر (USD) *</label>
              <input
                type="number"
                step="0.01"
                min="0.01"
                className="input"
                value={form.price}
                onChange={(e) => set('price')(e.target.value)}
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
                required
              />
            </div>
            <div>
              <label className="label">النوع</label>
              <select
                className="input"
                value={form.type}
                onChange={(e) => set('type')(e.target.value)}
              >
                <option value="auto">توصيل تلقائي</option>
                <option value="manual">خدمة يدوية</option>
              </select>
            </div>
          </div>
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
              value={form.image_base64}
              onChange={(v) => set('image_base64')(v)}
            />
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
