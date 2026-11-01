import { useEffect, useRef, useState } from 'react';
import { ImagePlus, RotateCcw, X } from 'lucide-react';

interface ImageUploaderProps {
  value?: string;
  onChange: (base64: string) => void;
  label?: string;
}

export default function ImageUploader({ value, onChange, label }: ImageUploaderProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [preview, setPreview] = useState<string | null>(value || null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => setPreview(value || null), [value]);

  const handleFile = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
      setError('اختر صورة PNG أو JPEG أو WebP فقط.');
      e.target.value = '';
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      setError('الحد الأقصى للصورة 5 ميغابايت.');
      e.target.value = '';
      return;
    }
    setError(null);
    const reader = new FileReader();
    reader.onload = (ev) => {
      const result = ev.target?.result;
      if (typeof result === 'string') { setPreview(result); onChange(result); }
    };
    reader.readAsDataURL(file);
  };

  return <div className="space-y-2">
    {label && <label className="label">{label}</label>}
    <input ref={inputRef} type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" onChange={handleFile} />
    <div className="flex flex-wrap items-center gap-3">
      <div className="w-24 h-24 rounded-xl bg-green-500/10 border border-gray-200 dark:border-ink-200 overflow-hidden flex items-center justify-center">
        {preview ? <img src={preview} alt="معاينة الصورة المختارة" className="w-full h-full object-cover" /> : <ImagePlus className="w-7 h-7 text-green-600" />}
      </div>
      <div className="flex flex-col items-start gap-2">
        <button type="button" onClick={() => inputRef.current?.click()} className="btn-secondary btn-sm inline-flex items-center gap-2"><ImagePlus className="w-4 h-4" />{preview ? 'تغيير الصورة' : 'رفع صورة'}</button>
        {preview && <button type="button" onClick={() => { onChange(''); setPreview(null); if (inputRef.current) inputRef.current.value = ''; }} className="inline-flex items-center gap-1 text-xs text-gray-600 dark:text-ink-500 hover:text-green-500"><RotateCcw className="w-3.5 h-3.5" />إزالة صورة الأدمن</button>}
      </div>
    </div>
    <p className="text-xs text-gray-500 dark:text-ink-500">PNG / JPG / WebP · حتى 5 MB</p>
    {error && <p className="flex gap-1 items-center text-xs text-red-500"><X className="w-4 h-4" />{error}</p>}
  </div>;
}
