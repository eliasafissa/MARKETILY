import { useEffect, useState, useRef, useCallback } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { AnimatePresence, motion } from 'framer-motion';
import { Search, X, ArrowRight } from 'lucide-react';
import { productApi } from '@/api/client';
import { useI18n } from '@/i18n';
import { localized } from '@/utils/localize';
import { formatPrice } from '@/utils/format';

interface SearchPaletteProps {
  isOpen: boolean;
  onClose: () => void;
}

export default function SearchPalette({ isOpen, onClose }: SearchPaletteProps) {
  const { t, locale } = useI18n();
  const location = useLocation();
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const prevPathRef = useRef(location.pathname);

  // Focus input on open
  useEffect(() => {
    if (isOpen) {
      setTimeout(() => inputRef.current?.focus(), 50);
      setQuery('');
      setResults([]);
    }
  }, [isOpen]);

  // Close on route change — prevents the palette lingering after clicking a result
  useEffect(() => {
    if (isOpen && location.pathname !== prevPathRef.current) {
      onClose();
    }
    prevPathRef.current = location.pathname;
  }, [location.pathname, isOpen, onClose]);

  // Close on global Escape
  useEffect(() => {
    if (!isOpen) return;
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [isOpen, onClose]);

  // Debounced search
  useEffect(() => {
    if (timeoutRef.current) clearTimeout(timeoutRef.current);
    if (!query.trim()) { setResults([]); return; }

    setLoading(true);
    timeoutRef.current = setTimeout(async () => {
      try {
        const r = await productApi.list({ q: query, per_page: '8' });
        setResults(r.data.data ?? []);
      } catch { setResults([]); }
      finally { setLoading(false); }
    }, 300);

    return () => { if (timeoutRef.current) clearTimeout(timeoutRef.current); };
  }, [query]);

  // Global keyboard shortcut Cmd/Ctrl+K
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        if (!isOpen) {
          window.dispatchEvent(new CustomEvent('open-search-palette'));
        }
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [isOpen]);

  const handleSelect = useCallback(() => {
    onClose();
    setQuery('');
    setResults([]);
  }, [onClose]);

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') { onClose(); }
  };

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.15 }}
            className="fixed inset-0 z-[998] bg-black/60 backdrop-blur-sm"
            onClick={onClose}
          />

          {/* Modal */}
          <motion.div
            initial={{ opacity: 0, scale: 0.95, y: -10 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.95, y: -10 }}
            transition={{ duration: 0.15, ease: [0.16, 1, 0.3, 1] }}
            onClick={(e) => e.stopPropagation()}
            className="fixed inset-0 m-auto w-[90%] max-w-lg h-fit z-[999]
                       bg-white dark:bg-ink-50 rounded-2xl shadow-2xl border border-gray-200 dark:border-ink-200
                       overflow-hidden"
          >
            {/* Search input */}
            <div className="flex items-center gap-3 px-4 py-4 border-b border-gray-100 dark:border-ink-200/50">
              <Search size={18} className="text-gray-400 dark:text-ink-500 flex-shrink-0" />
              <input
                ref={inputRef}
                type="text"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                onKeyDown={handleKeyDown}
                placeholder={t('nav.searchProducts') ?? 'Search products...'}
                className="flex-1 bg-transparent text-gray-900 dark:text-ink-900 placeholder:text-gray-400 dark:placeholder:text-ink-500 outline-none text-sm"
              />
              <button
                type="button"
                onClick={onClose}
                className="text-gray-400 dark:text-ink-500 hover:text-gray-600 dark:hover:text-ink-700 transition-colors"
                aria-label="Close search"
              >
                <X size={16} />
              </button>
            </div>

            {/* Results */}
            <div className="max-h-80 overflow-y-auto">
              {loading && query && (
                <div className="flex items-center justify-center py-8">
                  <span className="w-5 h-5 rounded-full border-2 border-green-500 border-t-transparent animate-spin" />
                </div>
              )}

              {!loading && query && results.length === 0 && (
                <div className="py-8 text-center text-sm text-gray-500 dark:text-ink-500">
                  No products found for "{query}"
                </div>
              )}

              {!loading && results.length > 0 && (
                <ul className="py-2">
                  {results.map((p) => (
                    <li key={p.id}>
                      <Link
                        to={`/product/${p.slug}`}
                        onClick={handleSelect}
                        className="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-ink-100/50 transition-colors group"
                      >
                        <div className="flex-1 min-w-0">
                          <p className="text-sm font-medium text-gray-900 dark:text-ink-900 truncate">
                            {localized(p, 'name', 'name_ar', locale)}
                          </p>
                          <p className="text-micro text-gray-500 dark:text-ink-500 truncate">
                            {localized(p, 'description', 'description_ar', locale)}
                          </p>
                        </div>
                        <div className="flex items-center gap-2">
                          <span className="text-sm font-semibold text-green-400 tabular-nums">
                            {formatPrice(p.price)}
                          </span>
                          <ArrowRight size={14} className="text-gray-300 dark:text-ink-300 group-hover:text-green-400 transition-colors" />
                        </div>
                      </Link>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}