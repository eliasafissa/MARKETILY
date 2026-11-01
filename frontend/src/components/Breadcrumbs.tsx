import { Link } from 'react-router-dom';

export interface BreadcrumbItem {
  label: string;
  link?: string;
}

interface BreadcrumbsProps {
  items: BreadcrumbItem[];
}

export default function Breadcrumbs({ items }: BreadcrumbsProps) {
  return (
    <nav
      aria-label="Breadcrumb"
      className="flex flex-wrap items-center gap-2 text-small"
    >
      {items.map((item, index) => {
        const isLast = index === items.length - 1;

        return (
          <span key={index} className="flex items-center gap-2">
            {item.link && !isLast ? (
              <Link
                to={item.link}
                className="text-green-400 hover:text-green-300 transition-colors"
              >
                {item.label}
              </Link>
            ) : (
              <span
                className={
                  isLast
                    ? 'text-gray-900 dark:text-ink-900 font-medium'
                    : 'text-gray-600 dark:text-ink-500'
                }
              >
                {item.label}
              </span>
            )}
            {!isLast && (
              <svg
                className="w-3 h-3 text-gray-400 dark:text-ink-400 rtl:rotate-180"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="m9 18 6-6-6-6" />
              </svg>
            )}
          </span>
        );
      })}
    </nav>
  );
}
