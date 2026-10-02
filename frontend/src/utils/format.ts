/**
 * Format a price number to a clean display string.
 * e.g. 10000 → "$10,000" | 49.99 → "$49.99" | 50 → "$50"
 */
export function formatPrice(value: number | string | null | undefined): string {
  if (value === null || value === undefined) return '$0';
  const num = typeof value === 'string' ? parseFloat(value) : value;
  if (isNaN(num)) return '$0';
  // Remove trailing .00
  const fixed = Number.isInteger(num) ? num.toString() : num.toFixed(2).replace(/\.?0+$/, '');
  return '$' + Number(fixed).toLocaleString('en-US');
}

/**
 * Format a date as date + hour:minute (no seconds).
 * e.g. "9/5/2026, 4:52 AM"
 */
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

/**
 * Format time as hour:minute only.
 * e.g. "4:52 AM"
 */
export function formatTime(value: string | number | Date | null | undefined): string {
  if (!value) return '';
  const d = new Date(value);
  if (isNaN(d.getTime())) return '';
  return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}
