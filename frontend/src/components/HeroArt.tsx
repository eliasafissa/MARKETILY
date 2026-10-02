interface HeroArtProps {
  variant?: 'aurora' | 'grid' | 'orbs';
  className?: string;
}

/** A custom inline-SVG hero illustration — no stock images. */
export default function HeroArt({ variant = 'aurora', className = '' }: HeroArtProps) {
  if (variant === 'grid') {
    return (
      <svg viewBox="0 0 600 600" className={className} aria-hidden>
        <defs>
          <pattern id="g" width="32" height="32" patternUnits="userSpaceOnUse">
            <path d="M32 0H0V32" fill="none" stroke="currentColor" strokeWidth="1" className="text-ink-200 dark:text-ink-200" />
          </pattern>
          <radialGradient id="glow" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stopColor="#10B981" stopOpacity="0.35" />
            <stop offset="60%" stopColor="#10B981" stopOpacity="0" />
          </radialGradient>
        </defs>
        <rect width="600" height="600" fill="url(#g)" />
        <circle cx="300" cy="300" r="240" fill="url(#glow)" />
        <g stroke="#10B981" strokeWidth="1" fill="none" opacity="0.5">
          <circle cx="300" cy="300" r="120" />
          <circle cx="300" cy="300" r="180" />
        </g>
        <g fill="#10B981">
          <circle cx="300" cy="120" r="3" />
          <circle cx="480" cy="300" r="3" />
          <circle cx="300" cy="480" r="3" />
          <circle cx="120" cy="300" r="3" />
        </g>
      </svg>
    );
  }

  if (variant === 'orbs') {
    return (
      <svg viewBox="0 0 600 600" className={className} aria-hidden>
        <defs>
          <radialGradient id="o1" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stopColor="#10B981" stopOpacity="0.5" />
            <stop offset="100%" stopColor="#10B981" stopOpacity="0" />
          </radialGradient>
          <radialGradient id="o2" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stopColor="#34D399" stopOpacity="0.35" />
            <stop offset="100%" stopColor="#34D399" stopOpacity="0" />
          </radialGradient>
        </defs>
        <circle cx="200" cy="200" r="240" fill="url(#o1)" />
        <circle cx="450" cy="400" r="200" fill="url(#o2)" />
      </svg>
    );
  }

  // aurora (default) — Stretched to 1440 width so it NEVER cuts off on large screens
  return (
    <svg viewBox="0 0 1440 600" preserveAspectRatio="none" className={className} aria-hidden>
      <defs>
        <linearGradient id="aurora" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#F59E0B" stopOpacity="0.25" />
          <stop offset="50%" stopColor="#FCD34D" stopOpacity="0.12" />
          <stop offset="100%" stopColor="#F59E0B" stopOpacity="0" />
        </linearGradient>
        <pattern id="dots" width="24" height="24" patternUnits="userSpaceOnUse">
          <circle cx="2" cy="2" r="1" fill="currentColor" className="text-ink-300 dark:text-ink-200" />
        </pattern>
      </defs>
      <rect width="1440" height="600" fill="url(#dots)" opacity="0.6" />
      <path
        d="M0 380 C300 300, 600 460, 1440 320 L1440 600 L0 600 Z"
        fill="url(#aurora)"
      />
      <path
        d="M0 420 C400 380, 800 500, 1440 420 L1440 600 L0 600 Z"
        fill="#F59E0B"
        opacity="0.05"
      />
    </svg>
  );
}