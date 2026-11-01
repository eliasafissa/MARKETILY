import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import api from '@/api/client';

export type DisplayCurrency = 'USD' | 'SYP';
type ContextValue = { currency: DisplayCurrency; rate: number | null; setCurrency: (x: DisplayCurrency) => void };
const CurrencyContext = createContext<ContextValue>({ currency: 'USD', rate: null, setCurrency: () => {} });

let activeCurrency: DisplayCurrency = 'USD';
let activeRate: number | null = null;

function toFiniteNumber(value: number | string): number | null {
  const num = typeof value === 'number' ? value : Number(String(value).trim());
  return Number.isFinite(num) ? num : null;
}

function usdMaxFractionDigits(amount: number): number {
  const abs = Math.abs(amount);
  if (abs === 0) return 0;
  if (abs >= 1) return 2;
  if (abs >= 0.01) return 4;
  return 8;
}

/**
 * Customer-facing money formatter.
 * Keeps backend precision intact, but shows a clean rounded value in the UI:
 * - 1 USD and above: up to 2 decimals
 * - 0.01 to 0.99 USD: up to 4 decimals
 * - below 0.01 USD: up to 8 decimals
 */
export function formatCurrencyAmount(usd: number | string): string {
  const amount = toFiniteNumber(usd);
  if (amount === null) return '$0';

  if (activeCurrency === 'SYP' && activeRate && activeRate > 0) {
    const converted = amount * activeRate;
    return Number.isFinite(converted)
      ? `${Math.round(converted).toLocaleString('en-US')} ل.س`
      : '—';
  }

  return `$${amount.toLocaleString('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: usdMaxFractionDigits(amount),
  })}`;
}

export function CurrencyProvider({ children }: { children: ReactNode }) {
  const [currency, setCurrencyRaw] = useState<DisplayCurrency>(() =>
    localStorage.getItem('haidar-display-currency') === 'SYP' ? 'SYP' : 'USD');
  const [rate, setRate] = useState<number | null>(null);

  useEffect(() => {
    let alive = true;
    api.get('/currency/rate').then(({ data }) => {
      if (alive) setRate(Number(data.syp_per_usd) > 0 ? Number(data.syp_per_usd) : null);
    }).catch(() => { /* display USD until rate can be loaded */ });
    return () => { alive = false; };
  }, []);

  const setCurrency = (next: DisplayCurrency) => {
    if (next === 'SYP' && !rate) return;
    localStorage.setItem('haidar-display-currency', next);
    setCurrencyRaw(next);
  };

  activeRate = rate;
  activeCurrency = rate ? currency : 'USD';

  return (
    <CurrencyContext.Provider value={{ currency: activeCurrency, rate, setCurrency }}>
      <div key={`${activeCurrency}:${rate}`}>{children}</div>
    </CurrencyContext.Provider>
  );
}

export const useCurrency = () => useContext(CurrencyContext);