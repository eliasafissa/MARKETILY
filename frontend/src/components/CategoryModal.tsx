import { useState } from 'react';
import { adminCategoryApi } from '@/api/client';
import toast from 'react-hot-toast';
import Modal from './Modal';
import IconPicker from './IconPicker';
import ImageUploader from './ImageUploader';
import Button from './Button';
import { useI18n } from '@/i18n';

interface FormField {
  key: string;
  label: string;
  label_ar?: string;
  type: 'text' | 'select' | 'textarea';
  required: boolean;
  options?: string;
}

interface CategoryModalProps {
  category?: {
    id: number;
    name: string;
    name_ar?: string;
    type: string;
    description: string;
    description_ar?: string;
    icon: string;
    image_base64: string;
    sort_order: number;
    form_fields?: FormField[];
  };
  onClose: () => void;
  onSaved: () => void;
}

export default function CategoryModal({ category, onClose, onSaved }: CategoryModalProps) {
  const { t } = useI18n();
  const [form, setForm] = useState({
    name: category?.name ?? '',
    name_ar: category?.name_ar ?? '',
    type: category?.type ?? 'auto',
    description: category?.description ?? '',
    description_ar: category?.description_ar ?? '',
    icon: category?.icon ?? '',
    image_base64: category?.image_base64 ?? '',
    sort_order: category?.sort_order ?? 0,
  });

  // Form fields builder for manual categories
  const [formFields, setFormFields] = useState<FormField[]>(
    category?.form_fields?.length
      ? category.form_fields
      : []
  );

  const [saving, setSaving] = useState(false);
  const isEdit = !!category;

  const set = (k: string) => (v: unknown) => setForm((p) => ({ ...p, [k]: v }));

  const addField = () => {
    setFormFields((prev) => [
      ...prev,
      { key: `field_${Date.now()}`, label: '', label_ar: '', type: 'text', required: false, options: '' },
    ]);
  };

  const updateField = (index: number, patch: Partial<FormField>) => {
    setFormFields((prev) => prev.map((f, i) => (i === index ? { ...f, ...patch } : f)));
  };

  const removeField = (index: number) => {
    setFormFields((prev) => prev.filter((_, i) => i !== index));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.name_ar || !form.name_ar.trim()) {
      toast.error('الاسم بالعربية مطلوب');
      setSaving(false);
      return;
    }
    setSaving(true);
    try {
      const payload = { ...form, form_fields: formFields };
      if (!payload.name || !payload.name.trim()) {
        payload.name = payload.name_ar || '';
      }
      if (!payload.description || !payload.description.trim()) {
        payload.description = payload.description_ar || '';
      }
      if (isEdit) {
        await adminCategoryApi.update(category.id, payload);
      } else {
        await adminCategoryApi.create(payload);
      }
      onSaved();
      onClose();
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? t('common.failed'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      isOpen={true}
      onClose={onClose}
      title={isEdit ? t('admin.editCategory') : t('admin.newCategory')}
      size="md"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="label">{t('admin.nameAr')}</label>
          <input
            className="input"
            dir="rtl"
            value={form.name_ar}
            onChange={(e) => set('name_ar')(e.target.value)}
            placeholder="اسم الفئة"
          />
        </div>
        <div>
          <label className="label">{t('admin.type')}</label>
          <select
            className="input"
            value={form.type}
            onChange={(e) => set('type')(e.target.value)}
          >
            <option value="auto">{t('admin.automatic')}</option>
            <option value="manual">{t('admin.manual')}</option>
          </select>
        </div>
        <div>
          <label className="label">{t('admin.descriptionAr')}</label>
          <textarea
            className="input"
            rows={2}
            dir="rtl"
            value={form.description_ar}
            onChange={(e) => set('description_ar')(e.target.value)}
            placeholder="وصف اختياري"
          />
        </div>
        <div>
          <label className="label">{t('admin.icon')}</label>
          <IconPicker value={form.icon} onChange={(v) => set('icon')(v)} />
          {form.icon && (
            <p className="text-micro text-gray-600 dark:text-ink-500 mt-1">
              {t('admin.icon')}: <span className="font-mono">{form.icon}</span>
            </p>
          )}
        </div>
        <div>
          <label className="label">{t('admin.image')}</label>
          <ImageUploader
            value={form.image_base64}
            onChange={(v) => set('image_base64')(v)}
          />
        </div>
        <div>
          <label className="label">{t('admin.sortOrder')}</label>
          <input
            type="number"
            className="input w-32"
            value={form.sort_order}
            onChange={(e) => set('sort_order')(parseInt(e.target.value) || 0)}
          />
        </div>

        {/* Form Fields Builder — only for manual categories */}
        {form.type === 'manual' && (
          <div className="border-t border-gray-200 dark:border-ink-200 pt-4">
            <div className="flex items-center justify-between mb-3">
              <label className="label mb-0">{t('admin.formFields')}</label>
              <button
                type="button"
                onClick={addField}
                className="btn-secondary btn-sm"
              >
                + {t('admin.addField')}
              </button>
            </div>

            {formFields.length === 0 && (
              <p className="text-small text-gray-500 dark:text-ink-500 py-2">
                {'لم تُضف أي حقول بعد. اضغط "إضافة حقل" لإنشاء واحد.'}
              </p>
            )}

            <div className="space-y-3">
              {formFields.map((field, index) => (
                <div key={field.key} className="card-pad border border-gray-200 dark:border-ink-200">
                  {/* Row 1: Label (EN) + Label (AR) */}
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div>
                      <label className="text-micro text-gray-600 dark:text-ink-600 mb-1 block">
                        {'التسمية بالإنجليزية'}
                      </label>
                      <input
                        type="text"
                        className="input"
                        value={field.label}
                        onChange={(e) => updateField(index, { label: e.target.value })}
                        placeholder="مثال: رابط الملف"
                      />
                    </div>
                    <div>
                      <label className="text-micro text-gray-600 dark:text-ink-600 mb-1 block">
                        {'التسمية بالعربية'}
                      </label>
                      <input
                        type="text"
                        className="input"
                        dir="rtl"
                        value={field.label_ar ?? ''}
                        onChange={(e) => updateField(index, { label_ar: e.target.value })}
                        placeholder="مثال: رابط الملف"
                      />
                    </div>
                  </div>

                  {/* Row 2: Type + Required + Options */}
                  <div className="grid grid-cols-1 sm:grid-cols-12 gap-2 mt-2">
                    <div className="sm:col-span-3">
                      <label className="text-micro text-gray-600 dark:text-ink-600 mb-1 block">
                        {t('admin.fieldType')}
                      </label>
                      <select
                        className="input"
                        value={field.type}
                        onChange={(e) => updateField(index, { type: e.target.value as FormField['type'] })}
                      >
                        <option value="text">نص</option>
                        <option value="select">قائمة</option>
                        <option value="textarea">نص طويل</option>
                      </select>
                    </div>
                    <div className="sm:col-span-2">
                      <label className="text-micro text-gray-600 dark:text-ink-600 mb-1 block">
                        {t('admin.fieldRequired')}
                      </label>
                      <div className="flex items-center h-[42px]">
                        <input
                          type="checkbox"
                          className="w-4 h-4 accent-green-500"
                          checked={field.required}
                          onChange={(e) => updateField(index, { required: e.target.checked })}
                        />
                      </div>
                    </div>
                    <div className="sm:col-span-7">
                      <label className="text-micro text-gray-600 dark:text-ink-600 mb-1 block">
                        {t('admin.fieldOptions')}
                      </label>
                      <input
                        type="text"
                        className="input"
                        value={field.options ?? ''}
                        onChange={(e) => updateField(index, { options: e.target.value })}
                        placeholder="أ، ب، ج"
                        disabled={field.type !== 'select'}
                      />
                    </div>
                  </div>
                  {field.type === 'select' && (
                    <p className="text-micro text-gray-500 dark:text-ink-500 mt-1">
                      {t('admin.fieldOptionsHint')}
                    </p>
                  )}
                  <div className="flex justify-end mt-2">
                    <button
                      type="button"
                      onClick={() => removeField(index)}
                      className="btn-danger btn-sm"
                    >
                      {t('common.delete')}
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        <div className="flex gap-3 pt-2">
          <Button type="submit" variant="accent" className="flex-1" loading={saving}>
            {isEdit ? t('admin.saveChanges') : t('admin.createCategory')}
          </Button>
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
