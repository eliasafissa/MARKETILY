import { Link } from 'react-router-dom';
import type { ReactNode } from 'react';

interface EmptyStateProps {
  icon: ReactNode;
  title: string;
  description: string;
  action?: { label: string; to?: string; onClick?: () => void };
  secondaryAction?: { label: string; to?: string; onClick?: () => void };
  className?: string;
}

/**
 * Reusable empty state used when there's no data to show.
 * Designed to feel intentional rather than missing — gives the user
 * a clear next step.
 */
export default function EmptyState({
  icon,
  title,
  description,
  action,
  secondaryAction,
  className = '',
}: EmptyStateProps): ReactNode {
  return (
    <div className={`text-center py-16 px-6 max-w-lg mx-auto ${className}`} role="status">
      <div className="relative w-24 h-24 mx-auto mb-6">
        <div className="absolute inset-0 rounded-2xl bg-green-500/10 border border-green-500/20" aria-hidden="true" />
        <div className="relative w-full h-full flex items-center justify-center text-green-400">
          {icon}
        </div>
      </div>
      <h3 className="text-h3 text-gray-900 dark:text-ink-900 mb-2">{title}</h3>
      <p className="text-body text-gray-600 dark:text-ink-500 leading-relaxed">{description}</p>

      {(action || secondaryAction) && (
        <div className="flex flex-col sm:flex-row gap-3 justify-center mt-8">
          {action && (
            action.to
              ? <Link to={action.to} className="btn-accent">{action.label}</Link>
              : <button onClick={action.onClick} className="btn-accent">{action.label}</button>
          )}
          {secondaryAction && (
            secondaryAction.to
              ? <Link to={secondaryAction.to} className="btn-secondary">{secondaryAction.label}</Link>
              : <button onClick={secondaryAction.onClick} className="btn-secondary">{secondaryAction.label}</button>
          )}
        </div>
      )}
    </div>
  );
}
