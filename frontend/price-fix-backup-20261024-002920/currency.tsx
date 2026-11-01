import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import api from '@/api/client';

export type DisplayCurrency = 'USD' | 'SYP';
type ContextValue = { currency: DisplayCurrency; rate: number | null; setCurrency: (x: DisplayCurrency) => void };
const CurrencyContext = createContext<ContextValue>({ currency: 'USD', rate: null, setCurrency: () => {} });

let activeCurrency: DisplayCurrency = 'USD';
let activeRate: number | null = null;
export function formatCurrencyAmount(usd: number): string {
  if (activeCurrency === 'SYP' && activeRate && activeRate > 0) {
    return `${Math.round(usd * activeRate).toLocaleString('en-US')} ل.س`;
  }
  return `$${usd.toLocaleString('en-US', { maximumFractionDigits: 2, minimumFractionDigits: 0 })}`;
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
  return <CurrencyContext.Provider value={{ currency: activeCurrency, rate, setCurrency }}>
    {/* Remount price views to refresh every formatted amount on currency switch. */}
    <div key={`${activeCurrency}:${rate}`}>{children}</div>
  </CurrencyContext.Provider>;
}
export const useCurrency = () => useContext(CurrencyContext);
