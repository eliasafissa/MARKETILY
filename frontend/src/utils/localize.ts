import type { Language } from '@/i18n';

/**
 * Pick the localized field on a record.
 * Falls back to the English/base field if the AR translation is missing or empty.
 */
export function localized<T extends Record<string, any>>(
  record: T | null | undefined,
  baseKey: keyof T,
  arKey: keyof T,
  locale: Language,
): string {
  if (!record) return '';
  if (locale === 'ar') {
    const ar = record[arKey];
    if (typeof ar === 'string' && ar.trim().length > 0) return ar;
  }
  const base = record[baseKey];
  return typeof base === 'string' ? base : '';
}