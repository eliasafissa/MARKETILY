import type { ReactNode } from 'react';
import { useState, useEffect } from 'react';
import { Package } from 'lucide-react';
import { useAppSelector } from '@/hooks/useAuth';
import { useI18n } from '@/i18n';
import { favoriteApi } from '@/api/client';
import toast from 'react-hot-toast';

interface ProductImageProps {
  name: string;
  category?: string;
  icon?: string;
  categoryImageUrl?: string;
  imageBase64?: string;
  imageUrl?: string;
  className?: string;
  productId?: number;
  showFavorite?: boolean;
  initialFavorited?: boolean;
}

function HeartIcon({ filled, className = '' }: { filled?: boolean; className?: string }): ReactNode {
  return (
    <svg
      width="20"
      height="20"
      viewBox="0 0 24 24"
      fill={filled ? 'currentColor' : 'none'}
      stroke="currentColor"
      strokeWidth={filled ? 0 : 2}
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
    >
      <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
    </svg>
  );
}

export default function ProductImage({
  name,
  category,
  icon,
  categoryImageUrl,
  imageBase64,
  imageUrl,
  className = '',
  productId,
  showFavorite = false,
  initialFavorited = false,
}: ProductImageProps): ReactNode {
  const { t } = useI18n();
  const authenticated = useAppSelector(st => st.auth.isAuthenticated);
  const emailVerified = useAppSelector(st => Boolean(st.auth.user?.email_verified_at));
  const src = imageUrl ?? imageBase64;
  const [favorited, setFavorited] = useState(initialFavorited);
  const [loading, setLoading] = useState(false);
  useEffect(() => {
    if (!showFavorite || !productId) return;
    if (!authenticated) { setFavorited(false); return; }
    let alive = true;
    favoriteApi.check(productId).then(({ data }) => { if (alive) setFavorited(Boolean(data.favorited)); }).catch(() => {});
    return () => { alive = false; };
  }, [productId, showFavorite, authenticated]);

  const handleFavorite = async (e: React.MouseEvent) => {
    e.stopPropagation();
    e.preventDefault();
    if (!productId) return;
    if (!authenticated) { window.location.assign('/login'); return; }
    if (!emailVerified) { window.location.assign('/dashboard/email-verification'); return; }
    setLoading(true);
    try {
      if (favorited) {
        await favoriteApi.remove(productId);
        toast.success(t('favorites.productRemoved'));
      } else {
        await favoriteApi.add(productId);
        toast.success(t('favorites.productAdded'));
      }
      setFavorited(!favorited);
    } catch {
      toast.error(t('toast.failed'));
    } finally {
      setLoading(false);
    }
  };

  const renderWithFavorite = (content: ReactNode): ReactNode => {
    if (!showFavorite || !productId) return content;
    return (
      <div className="relative h-full">
        {content}
        <button
          onClick={handleFavorite}
          disabled={loading}
          className={`absolute top-2 end-2 z-10 p-1.5 rounded-full bg-white/90 dark:bg-ink-800/90 backdrop-blur-sm shadow-md transition-colors disabled:opacity-50 ${
            favorited ? 'text-status-rejected' : 'text-gray-400 hover:text-status-rejected'
          }`}
          aria-label={favorited ? t('favorites.removeFromFavorites') : t('favorites.addToFavorites')}
          aria-pressed={favorited}
        >
          <HeartIcon filled={favorited} className="w-5 h-5" />
        </button>
      </div>
    );
  };

  // 1. Real product image
  if (src) {
    return renderWithFavorite(
      <div className={`relative w-full overflow-hidden rounded-lg bg-gray-100 dark:bg-ink-100 ${className}`}>
        <img
          src={src}
          alt={name}
          className="w-full h-full object-contain p-2"
          loading="lazy"
          decoding="async"
        />
      </div>
    );
  }

  // 3. No product image, but category has one
  if (categoryImageUrl) {
    return renderWithFavorite(
      <div className={`relative w-full overflow-hidden rounded-lg bg-gray-100 dark:bg-ink-100 ${className}`}>
        <img
          src={categoryImageUrl}
          alt=""
          aria-hidden="true"
          className="absolute inset-0 w-full h-full object-cover"
          loading="lazy"
          decoding="async"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-black/5" />
        <div className="absolute inset-x-0 bottom-0 p-3">
          <p className="text-white text-xs font-semibold leading-tight line-clamp-2 drop-shadow-md">
            {name}
          </p>
        </div>
      </div>
    );
  }

  // Neutral icon when no trustworthy product image is available. Never fabricate product photos.
  return renderWithFavorite(
    <div className={`relative w-full h-full overflow-hidden rounded-lg flex items-center justify-center bg-gray-100 dark:bg-ink-100 text-green-600 ${className}`} role="img" aria-label={name}>
      <Package size={42} strokeWidth={1.5} aria-hidden="true" />
    </div>
  );
}
