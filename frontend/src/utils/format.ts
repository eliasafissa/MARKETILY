import { formatCurrencyAmount } from '@/currency';

/**
 * Format a price number/string to a clean customer-facing display string.
 */
export function formatPrice(value: number | string | null | undefined): string {
  if (value === null || value === undefined) return '$0';
  return formatCurrencyAmount(value);
}

export function formatDateTime(value: string | number | Date | null | undefined): string {
  if (!value) return '';
  const d = new Date(value);
  if (isNaN(d.getTime())) return '';
  return d.toLocaleString('en-US', {
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function formatTime(value: string | number | Date | null | undefined): string {
  if (!value) return '';
  const d = new Date(value);
  if (isNaN(d.getTime())) return '';
  return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}