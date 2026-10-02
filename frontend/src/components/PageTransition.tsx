import { motion, type HTMLMotionProps } from 'framer-motion';
import { type ReactNode } from 'react';

interface PageTransitionProps extends HTMLMotionProps<'div'> {
  children: ReactNode;
}

/** Subtle page-level fade + rise — used on every page root. */
export default function PageTransition({ children, className, ...rest }: PageTransitionProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: -4 }}
      transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
      className={className}
      {...rest}
    >
      {children}
    </motion.div>
  );
}
