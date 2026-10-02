import type { ReactNode } from 'react';
import { useState } from 'react';
import { categoryEmoji } from '@/utils/categoryEmoji';
import { iconToEmoji } from '@/utils/iconToEmoji';
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

const BG_TINTS = [
  'bg-red-500/10',
  'bg-orange-500/10',
  'bg-amber-500/10',
  'bg-emerald-500/10',
  'bg-teal-500/10',
  'bg-cyan-500/10',
  'bg-sky-500/10',
  'bg-blue-500/10',
  'bg-indigo-500/10',
  'bg-violet-500/10',
  'bg-purple-500/10',
  'bg-pink-500/10',
];

function tint(seed: string): string {
  let h = 0;
  for (let i = 0; i < seed.length; i++) h = seed.charCodeAt(i) + ((h << 5) - h);
  return BG_TINTS[Math.abs(h) % BG_TINTS.length];
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
  const src = imageUrl ?? imageBase64;
  const [favorited, setFavorited] = useState(initialFavorited);
  const [loading, setLoading] = useState(false);

  const handleFavorite = async (e: React.MouseEvent) => {
    e.stopPropagation();
    e.preventDefault();
    if (!productId) return;
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
          className={`absolute top-2 end-2 z-10 p-1.5 rounded-full bg-white/90 dark:bg-ink-800/90 backdrop-blur-sm shadow-md transition-all hover:scale-110 disabled:opacity-50 ${
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
          className="w-full h-full object-cover"
          loading="lazy"
          decoding="async"
        />
      </div>
    );
  }

  // 2. Admin-set icon (product-level) wins over everything else
  const adminEmoji = iconToEmoji(icon);
  if (adminEmoji) {
    return renderWithFavorite(
      <div
        className={`relative w-full overflow-hidden rounded-lg h-full flex items-center justify-center ${tint(name || 'default')} ${className}`}
        role="img"
        aria-label={name}
      >
        <span className="text-5xl select-none" aria-hidden="true">{adminEmoji}</span>
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

  // 4. Product name → category name → default 🛍️
  const fromName = categoryEmoji(name);
  const fromCategory = categoryEmoji(category);
  const emoji = fromName !== '🛍️' ? fromName : fromCategory;
  const bg = tint(category || name || 'default');

  return renderWithFavorite(
    <div
      className={`relative w-full overflow-hidden rounded-lg h-full flex items-center justify-center ${bg} ${className}`}
      role="img"
      aria-label={name}
    >
      <span className="text-5xl select-none" aria-hidden="true">{emoji}</span>
    </div>
  );
}
