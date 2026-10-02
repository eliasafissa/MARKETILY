/**
 * Analytics — drop-in placeholder for Google Analytics 4 and Umami.
 *
 * To enable, set either of these in the frontend .env file:
 *   VITE_GA_MEASUREMENT_ID=G-XXXXXXXXXX   (Google Analytics 4)
 *   VITE_UMAMI_WEBSITE_ID=xxxxxxxx-xxxx  (Umami self-hosted / cloud)
 *
 * The component injects the appropriate script and Page View event
 * automatically. If neither is configured it renders nothing.
 */
import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

declare global {
  interface Window {
    dataLayer?: unknown[];
    gtag?: (...args: unknown[]) => void;
  }
}

const GA_ID = (import.meta.env.VITE_GA_MEASUREMENT_ID as string | undefined)?.trim();
const UMAMI_ID = (import.meta.env.VITE_UMAMI_WEBSITE_ID as string | undefined)?.trim();

function injectScript(src: string, attrs: Record<string, string> = {}): void {
  if (document.querySelector(`script[src="${src}"]`)) return;
  const s = document.createElement('script');
  s.src = src;
  s.async = true;
  for (const [k, v] of Object.entries(attrs)) s.setAttribute(k, v);
  document.head.appendChild(s);
}

export default function Analytics(): null {
  const location = useLocation();

  // Inject Google Analytics 4
  useEffect(() => {
    if (!GA_ID) return;

    // gtag bootstrap
    window.dataLayer = window.dataLayer ?? [];
    window.gtag = function gtag(...args: unknown[]) {
      window.dataLayer?.push(args);
    };
    window.gtag('js', new Date());
    window.gtag('config', GA_ID, { send_page_view: false });

    injectScript(`https://www.googletagmanager.com/gtag/js?id=${GA_ID}`);
  }, []);

  // Inject Umami
  useEffect(() => {
    if (!UMAMI_ID) return;
    injectScript('https://cloud.umami.is/script.js', {
      'data-website-id': UMAMI_ID,
    });
  }, []);

  // Track SPA page views
  useEffect(() => {
    if (GA_ID && window.gtag) {
      window.gtag('event', 'page_view', {
        page_path: location.pathname + location.search,
        page_title: document.title,
      });
    }
    // Umami automatically tracks SPA page changes via history.pushState
    // when its script picks up the change, so no extra call needed.
  }, [location.pathname, location.search]);

  return null;
}
