import type { ReactNode } from 'react';

interface AvatarProps {
  name: string;
  imageUrl?: string | null;
  imageBase64?: string | null;
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
  shape?: 'rounded' | 'square' | 'circle';
  className?: string;
}

const COLORS = [
  { bg: 'bg-red-500/15',     text: 'text-red-500' },
  { bg: 'bg-orange-500/15',  text: 'text-orange-500' },
  { bg: 'bg-amber-500/15',   text: 'text-amber-500' },
  { bg: 'bg-yellow-500/15',  text: 'text-yellow-500' },
  { bg: 'bg-lime-500/15',    text: 'text-lime-500' },
  { bg: 'bg-green-500/15',   text: 'text-green-500' },
  { bg: 'bg-emerald-500/15', text: 'text-emerald-500' },
  { bg: 'bg-teal-500/15',    text: 'text-teal-500' },
  { bg: 'bg-cyan-500/15',    text: 'text-cyan-500' },
  { bg: 'bg-sky-500/15',     text: 'text-sky-500' },
  { bg: 'bg-blue-500/15',    text: 'text-blue-500' },
  { bg: 'bg-indigo-500/15',  text: 'text-indigo-500' },
  { bg: 'bg-violet-500/15',  text: 'text-violet-500' },
  { bg: 'bg-purple-500/15',  text: 'text-purple-500' },
  { bg: 'bg-fuchsia-500/15', text: 'text-fuchsia-500' },
  { bg: 'bg-pink-500/15',    text: 'text-pink-500' },
];

function pickColor(name: string) {
  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return COLORS[Math.abs(hash) % COLORS.length];
}

const SIZE_CLASS: Record<string, string> = {
  xs: 'w-6 h-6 text-xs',
  sm: 'w-8 h-8 text-sm',
  md: 'w-10 h-10 text-base',
  lg: 'w-12 h-12 text-xl',
  xl: 'w-16 h-16 text-3xl',
};

const SHAPE_CLASS: Record<string, string> = {
  rounded: 'rounded-lg',
  square: 'rounded-none',
  circle: 'rounded-full',
};

export default function Avatar({
  name,
  imageUrl,
  imageBase64,
  size = 'md',
  shape = 'rounded',
  className = '',
}: AvatarProps): ReactNode {
  const src = imageUrl ?? imageBase64;

  if (src) {
    return (
      <div className={`relative overflow-hidden bg-gray-100 dark:bg-ink-100 ${SIZE_CLASS[size]} ${SHAPE_CLASS[shape]} ${className}`}>
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

  const trimmed = (name || '').trim();
  const letter = trimmed ? trimmed.charAt(0).toUpperCase() : '?';
  const color = pickColor(trimmed || 'default');

  return (
    <div
      className={`flex items-center justify-center font-bold ${color.bg} ${color.text} ${SIZE_CLASS[size]} ${SHAPE_CLASS[shape]} ${className}`}
      role="img"
      aria-label={name}
    >
      <span aria-hidden="true">{letter}</span>
    </div>
  );
}
