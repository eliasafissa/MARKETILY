import { useRef, useState } from 'react';

interface ImageUploaderProps {
  value?: string;
  onChange: (base64: string) => void;
  label?: string;
}

export default function ImageUploader({ value, onChange, label }: ImageUploaderProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [preview, setPreview] = useState<string | null>(value ?? null);

  const handleFile = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
      alert('Image must be under 5MB');
      return;
    }
    const reader = new FileReader();
    reader.onload = (ev) => {
      const result = ev.target?.result as string;
      setPreview(result);
      onChange(result);
    };
    reader.readAsDataURL(file);
  };

  return (
    <div>
      {label && <label className="label">{label}</label>}
      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={handleFile}
      />
      {preview ? (
        <div className="relative inline-block">
          <img
            src={preview}
            alt="Preview"
            className="w-32 h-32 object-cover rounded-lg border border-gray-200 dark:border-ink-200"
          />
          <button
            type="button"
            onClick={() => {
              setPreview(null);
              onChange('');
              if (inputRef.current) inputRef.current.value = '';
            }}
            className="absolute -top-2 -right-2 w-6 h-6 bg-status-rejected rounded-full flex items-center justify-center text-white text-xs"
          >
            ×
          </button>
        </div>
      ) : (
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          className="w-32 h-32 rounded-lg border-2 border-dashed border-gray-200 dark:border-ink-200 flex flex-col items-center justify-center gap-1 text-gray-600 dark:text-ink-500 hover:border-green-500 hover:text-green-400 transition-colors cursor-pointer"
        >
          <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
          >
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12" />
          </svg>
          <span className="text-micro">Upload</span>
        </button>
      )}
    </div>
  );
}
