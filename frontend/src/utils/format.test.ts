import { describe, expect, it } from 'vitest';
import { formatPrice } from './format';

describe('formatPrice', () => {
  it('formats regular prices', () => {
    expect(formatPrice(9.99)).toBe('$9.99');
    expect(formatPrice(1000.5)).toBe('$1,000.5');
    expect(formatPrice(10000)).toBe('$10,000');
  });

  it('formats tiny provider prices cleanly', () => {
    expect(formatPrice('0.000084425804188560000000')).toBe('$0.00008443');
    expect(formatPrice('0.010448000000000000000000')).toBe('$0.0104');
  });

  it('rounds large decimal strings for customer display', () => {
    expect(formatPrice('74.090862571999989552')).toBe('$74.09');
    expect(formatPrice('1.013109650262720000000000')).toBe('$1.01');
  });

  it('handles empty and invalid values', () => {
    expect(formatPrice(0)).toBe('$0');
    expect(formatPrice(null)).toBe('$0');
    expect(formatPrice(undefined)).toBe('$0');
    expect(formatPrice('not a number')).toBe('$0');
  });
});