import { Link } from 'react-router-dom';
import PageTransition from '@/components/PageTransition';
import Button from '@/components/Button';

export default function NotFound() {
  return (
    <PageTransition className="flex flex-col items-center justify-center min-h-[60vh] text-center space-y-6">
      <div className="text-8xl font-extrabold text-green-500/20 select-none" aria-hidden>
        404
      </div>
      <div>
        <h1 className="text-h1 text-gray-900 dark:text-ink-900 mb-2">Page not found</h1>
        <p className="text-body text-gray-600 dark:text-ink-600">
          The page you're looking for doesn't exist or has been moved.
        </p>
      </div>
      <div className="flex gap-3">
        <Link to="/">
          <Button variant="accent" size="lg">
            Go Home
          </Button>
        </Link>
        <Link to="/products">
          <Button variant="secondary" size="lg">
            Browse Products
          </Button>
        </Link>
      </div>
    </PageTransition>
  );
}
