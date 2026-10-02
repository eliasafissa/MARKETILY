import React, { createContext, useContext, useCallback } from 'react';
import en from './en';
import ar from './ar';
import { useAppSelector } from '@/store';

type Translations = typeof en;
type Language = 'en' | 'ar';

const translations: Record<Language, Translations> = { en, ar };

// Type-safe translation accessor
type NestedKeyOf<T> = T extends object
  ? { [K in keyof T]: K extends string
      ? T[K] extends object
        ? `${K}.${NestedKeyOf<T[K]>}`
        : K
      : never
    }[keyof T]
  : never;

export type TranslationKey = NestedKeyOf<Translations>;

interface I18nContextValue {
  locale: Language;
  t: (key: string, params?: Record<string, string | number>) => string;
  isRtl: boolean;
}

const I18nContext = createContext<I18nContextValue>({
  locale: 'en',
  t: (key) => key,
  isRtl: false,
});

export function I18nProvider({ children }: { children: React.ReactNode }) {
  const { locale } = useAppSelector((s) => s.language);

  const t = useCallback((key: string, params?: Record<string, string | number>): string => {
    const parts = key.split('.');
    let value: unknown = translations[locale];

    for (const part of parts) {
      if (value && typeof value === 'object' && part in value) {
        value = (value as Record<string, unknown>)[part];
      } else {
        // Fallback to English if key not found
        value = translations.en;
        for (const p of parts) {
          if (value && typeof value === 'object' && p in value) {
            value = (value as Record<string, unknown>)[p];
          } else {
            return key; // Return key if not found in either language
          }
        }
        break;
      }
    }

    if (typeof value !== 'string') return key;

    // Replace placeholders like {id} or {status}
    if (params) {
      return Object.entries(params).reduce(
        (str, [k, v]) => str.replace(new RegExp(`\\{${k}\\}`, 'g'), String(v)),
        value,
      );
    }

    return value;
  }, [locale]);

  return (
    <I18nContext.Provider value={{ locale, t, isRtl: locale === 'ar' }}>
      {children}
    </I18nContext.Provider>
  );
}

export function useI18n() {
  return useContext(I18nContext);
}

export { en, ar };
export type { Language, Translations };
