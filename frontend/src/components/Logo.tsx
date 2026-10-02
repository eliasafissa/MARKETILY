import { motion } from 'framer-motion';

interface LogoProps {
  size?: 'sm' | 'md' | 'lg';
  showText?: boolean;
}

export default function Logo({ size = 'md', showText = true }: LogoProps) {
  const px = size === 'sm' ? 24 : size === 'md' ? 32 : 44;
  return (
    <div className="flex items-center gap-2.5">
      <motion.svg
        whileHover={{ rotate: -8 }}
        transition={{ type: 'spring', stiffness: 300, damping: 18 }}
        width={px}
        height={px}
        viewBox="0 0 32 32"
        fill="none"
      >
        <rect width="32" height="32" rx="8" fill="#10B981" />
        <text
          x="16"
          y="23"
          textAnchor="middle"
          fontFamily="Inter, ui-sans-serif, system-ui, sans-serif"
          fontWeight="800"
          fontSize="20"
          fill="#0A0A0A"
        >M</text>
      </motion.svg>
      {showText && (
        <span className="text-gray-900 dark:text-ink-900 font-bold tracking-tighter text-lg">
          marketly
        </span>
      )}
    </div>
  );
}
