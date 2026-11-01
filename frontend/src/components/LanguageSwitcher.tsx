import { useAppSelector, useAppDispatch, setLanguage } from '@/store';

export default function LanguageSwitcher() {
  const dispatch = useAppDispatch();
  const { locale } = useAppSelector((s) => s.language);

  return (
    <div className="flex items-center gap-1 bg-gray-100 dark:bg-ink-100 rounded-lg p-0.5">
      <button
        onClick={() => dispatch(setLanguage('en'))}
        className={`px-2 py-1 text-xs font-medium rounded transition-all ${
          locale === 'en'
            ? 'bg-gray-900 text-white dark:bg-ink-900 dark:text-ink'
            : 'text-gray-600 dark:text-ink-600 hover:text-gray-900 dark:hover:text-ink-900'
        }`}
        aria-label="Switch to English"
      >
        EN
      </button>
      <button
        onClick={() => dispatch(setLanguage('ar'))}
        className={`px-2 py-1 text-xs font-medium rounded transition-all ${
          locale === 'ar'
            ? 'bg-gray-900 text-white dark:bg-ink-900 dark:text-ink'
            : 'text-gray-600 dark:text-ink-600 hover:text-gray-900 dark:hover:text-ink-900'
        }`}
        aria-label="التبديل إلى العربية"
      >
        AR
      </button>
    </div>
  );
}
