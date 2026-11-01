import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { settingsApi } from '@/api/client';
import Logo from './Logo';
import { useI18n } from '@/i18n';

interface CompanySettings {
  company_name?: string;
  phone?: string;
  address?: string;
  facebook_url?: string;
  instagram_url?: string;
  twitter_url?: string;
  telegram_url?: string;
}

interface SocialLink {
  name: string;
  href: string;
  svg: string;
}

const SOCIAL_ICONS: Record<string, SocialLink> = {
  facebook: {
    name: 'Facebook',
    href: '',
    svg: '<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>',
  },
  instagram: {
    name: 'Instagram',
    href: '',
    svg: '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/>',
  },
  twitter: {
    name: 'Twitter',
    href: '',
    svg: '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>',
  },
  telegram: {
    name: 'Telegram',
    href: '',
    svg: '<path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>',
  },
};

const KEY_TO_SOCIAL: Record<string, keyof typeof SOCIAL_ICONS> = {
  facebook_url: 'facebook',
  instagram_url: 'instagram',
  twitter_url: 'twitter',
  telegram_url: 'telegram',
};

export default function Footer() {
  const { t } = useI18n();
  const [company, setCompany] = useState<CompanySettings>({});

  useEffect(() => {
    settingsApi.company()
      .then((r) => setCompany(r.data.settings ?? {}))
      .catch(console.error);
  }, []);

  const activeSocials = Object.entries(KEY_TO_SOCIAL)
    .filter(([key]) => company[key as keyof CompanySettings])
    .map(([key, socialKey]) => ({
      ...SOCIAL_ICONS[socialKey],
      href: company[key as keyof CompanySettings] as string,
    }));

  const companyName = company.company_name || 'Marketly';

  return (
    <footer className="border-t border-gray-200 dark:border-ink-200 py-8 mt-auto bg-white/50 dark:bg-ink/50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6">
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-8">
          {/* Brand */}
          <div className="space-y-3">
            <Link to="/" className="flex items-center gap-2.5">
              <Logo size="sm" showText={false} />
              <span className="text-sm font-semibold text-gray-900 dark:text-ink-900">{companyName}</span>
            </Link>
            <p className="text-micro text-gray-600 dark:text-ink-500">
              {t('footer.tagline')}
            </p>
          </div>

          {/* Legal */}
          <div>
            <h3 className="text-small font-semibold text-gray-900 dark:text-ink-900 mb-3">{t('footer.legal')}</h3>
            <ul className="space-y-1.5 text-micro text-gray-600 dark:text-ink-500">
              <li><Link to="/legal/terms" className="hover:text-green-400 transition-colors">{t('footer.termsOfService')}</Link></li>
              <li><Link to="/legal/privacy" className="hover:text-green-400 transition-colors">{t('footer.privacyPolicy')}</Link></li>
              <li><Link to="/legal/refund" className="hover:text-green-400 transition-colors">{t('footer.refundPolicy')}</Link></li>
            </ul>
          </div>

          {/* Social */}
          <div>
            <h3 className="text-small font-semibold text-gray-900 dark:text-ink-900 mb-3">{t('footer.followUs')}</h3>
            {activeSocials.length > 0 ? (
              <div className="flex items-center gap-4">
                {activeSocials.map((link) => (
                  <a
                    key={link.name}
                    href={link.href}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={link.name}
                    className="text-gray-600 dark:text-ink-500 hover:text-green-400 transition-colors"
                  >
                    <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                      <g dangerouslySetInnerHTML={{ __html: link.svg }} />
                    </svg>
                  </a>
                ))}
              </div>
            ) : (
              <p className="text-micro text-gray-500 dark:text-ink-500">{t('footer.noSocialLinks')}</p>
            )}
          </div>
        </div>

        <div className="mt-8 pt-6 border-t border-gray-200 dark:border-ink-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-micro text-gray-600 dark:text-ink-500">
          <span dir="ltr">{t('footer.copyright', { year: new Date().getFullYear() })}</span>
          <div className="flex items-center gap-2">
            <span>تم التطوير بواسطة</span>
            <img src="/codecraft.svg" alt="Code Craft" className="h-5 w-auto" />
            <span className="font-semibold text-gray-900 dark:text-ink-900" dir="ltr">Code Craft</span>
          </div>
          <span>{t('footer.poweredBy')}</span>
        </div>
      </div>
    </footer>
  );
}