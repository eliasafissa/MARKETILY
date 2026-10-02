import type { ReactNode, CSSProperties } from 'react';

interface SkeletonProps {
  className?: string;
  style?: CSSProperties;
  rounded?: 'sm' | 'md' | 'lg' | 'full';
}

const ROUNDED = {
  sm: 'rounded',
  md: 'rounded-md',
  lg: 'rounded-lg',
  full: 'rounded-full',
};

/**
 * Animated skeleton block used to indicate content is loading.
 * Uses a subtle shimmer animation and respects prefers-reduced-motion.
 */
export default function Skeleton({ className = '', style, rounded = 'md' }: SkeletonProps): ReactNode {
  return (
    <div
      role="status"
      aria-live="polite"
      aria-busy="true"
      className={`relative overflow-hidden bg-gray-100 dark:bg-ink-100 ${ROUNDED[rounded]} ${className}`}
      style={style}
    >
      <span className="sr-only">Loading…</span>
      <style>{`
        @keyframes skeleton-shimmer {
          0%   { transform: translateX(-100%); }
          100% { transform: translateX(100%); }
        }
        .skeleton-shimmer::after {
          content: '';
          position: absolute;
          inset: 0;
          background: linear-gradient(
            90deg,
            transparent 0%,
            rgba(255, 255, 255, 0.04) 50%,
            transparent 100%
          );
          animation: skeleton-shimmer 1.5s ease-in-out infinite;
        }
        @media (prefers-reduced-motion: reduce) {
          .skeleton-shimmer::after { animation: none; }
        }
      `}</style>
      <span
        aria-hidden="true"
        className="skeleton-shimmer absolute inset-0 block"
      />
    </div>
  );
}

/** Product-card shaped skeleton — used by the public product grid. */
export function ProductCardSkeleton(): ReactNode {
  return (
    <div className="card-hover overflow-hidden p-0">
      <Skeleton className="h-40 w-full mb-4" rounded="lg" />
      <div className="px-4 pb-4 space-y-2">
        <Skeleton className="h-4 w-3/4" />
        <Skeleton className="h-3 w-full" />
        <Skeleton className="h-3 w-2/3" />
        <div className="flex justify-between items-center pt-2">
          <Skeleton className="h-5 w-16" />
          <Skeleton className="h-4 w-12" />
        </div>
      </div>
    </div>
  );
}

/** Grid of product card skeletons. */
export function ProductGridSkeleton({ count = 8 }: { count?: number }): ReactNode {
  return (
    <div
      className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"
      role="status"
      aria-live="polite"
      aria-busy="true"
    >
      {Array.from({ length: count }).map((_, i) => (
        <ProductCardSkeleton key={i} />
      ))}
    </div>
  );
}

/** Table-row skeleton — used by admin tables. */
export function TableRowSkeleton({ columns = 5 }: { columns?: number }): ReactNode {
  return (
    <tr aria-hidden="true">
      {Array.from({ length: columns }).map((_, i) => (
        <td key={i} className="py-3 px-4">
          <Skeleton className="h-3" />
        </td>
      ))}
    </tr>
  );
}

/** Table skeleton — used while admin tables load. */
export function TableSkeleton({ rows = 6, columns = 5 }: { rows?: number; columns?: number }): ReactNode {
  return (
    <div className="card overflow-hidden p-0">
      <div className="overflow-x-auto">
        <table className="table">
          <thead>
            <tr>
              {Array.from({ length: columns }).map((_, i) => (
                <th key={i}>
                  <Skeleton className="h-3 w-20" />
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {Array.from({ length: rows }).map((_, i) => (
              <TableRowSkeleton key={i} columns={columns} />
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
