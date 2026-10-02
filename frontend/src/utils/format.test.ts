import { describe, it, expect } from 'vitest';
import { formatPrice } from './format';

describe('formatPrice', () => {
  it('formats a decimal price with cents', () => {
    expect(formatPrice(9.99)).toBe('$9.99');
  });

  it('formats zero as "$0" (strips trailing .00)', () => {
    // The actual implementation strips trailing .00 and returns "$0" for 0
    expect(formatPrice(0)).toBe('$0');
  });

  it('formats large numbers with thousands separator', () => {
    expect(formatPrice(1000.5)).toBe('$1,000.5');
  });

  it('formats 10000 with thousands separator and no decimals', () => {
    expect(formatPrice(10000)).toBe('$10,000');
  });

  it('handles null and undefined gracefully', () => {
    expect(formatPrice(null)).toBe('$0');
    expect(formatPrice(undefined)).toBe('$0');
  });

  it('handles numeric string input', () => {
    expect(formatPrice('49.99')).toBe('$49.99');
  });

  it('handles non-numeric string by returning $0', () => {
    expect(formatPrice('not a number')).toBe('$0');
  });
});
