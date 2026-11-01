import { useEffect, useState } from 'react';
import { useAppSelector } from '@/store';
import { partnerRequestApi } from '@/api/client';
import toast from 'react-hot-toast';
import { CopyToClipboard } from 'react-copy-to-clipboard';
import Button from '@/components/Button';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';

type EndpointDoc = {
  title: string;
  method: string;
  path: string;
  description: string;
  queryParams?: { name: string; description: string }[];
  bodyParams?: { name: string; required: boolean; description: string }[];
  exampleResponse: any;
  errorResponse?: any;
};

const endpoints: EndpointDoc[] = [
  {
    title: 'Get Account Info',
    method: 'GET',
    path: '/partner/me',
    description: 'Returns your account information and current balance.',
    exampleResponse: {
      ok: true,
      data: { id: 123, name: 'John Doe', email: 'john@example.com', balance: 100.5, currency: 'USD' },
    },
  },
  {
    title: 'List Categories',
    method: 'GET',
    path: '/partner/categories',
    description: 'Returns categories that currently contain products available to partners, including local products and Oranos-synced products.',
    exampleResponse: {
      ok: true,
      data: [{ id: 1, name: 'Game Keys', name_ar: 'مفاتيح الألعاب', slug: 'game-keys', parent_id: null, image_url: null, oranos_category_id: 55 }],
    },
  },
  {
    title: 'List Products',
    method: 'GET',
    path: '/partner/products',
    description: 'Returns paginated products available for partner purchase from both local catalog items and Oranos-synced items. Supports filtering by category_slug, source, and search query (q).',
    queryParams: [
      { name: 'page', description: 'Page number (default: 1)' },
      { name: 'per_page', description: 'Items per page, max 100 (default: 50)' },
      { name: 'category_slug', description: 'Filter by category slug' },
      { name: 'source', description: 'Filter by source: local or oranos' },
      { name: 'q', description: 'Search by name/description' },
    ],
    exampleResponse: {
      ok: true,
      data: {
        current_page: 1,
        data: [{ id: 1, oranos_product_id: 1001, name: 'Product Name', name_ar: 'اسم المنتج', slug: 'product-name', price: '10.00', stock: 50, image_url: null, category_id: 1, category_slug: 'game-keys', source: 'oranos', product_type: 'automation', params: [] }, { id: 2, oranos_product_id: null, name: 'Local Product', name_ar: 'منتج محلي', slug: 'local-product', price: '5.00', stock: 7, image_url: null, category_id: 2, category_slug: 'services', source: 'local', product_type: 'manual', params: [] }],
        last_page: 5,
        total: 250,
      },
    },
  },
  {
    title: 'Get Product',
    method: 'GET',
    path: '/partner/products/{slug}',
    description: 'Returns a single product by slug from either the local catalog or Oranos-synced catalog.',
    exampleResponse: {
      ok: true,
      data: { id: 1, oranos_product_id: 1001, name: 'Product Name', name_ar: 'اسم المنتج', slug: 'product-name', price: '10.00', stock: 50, image_url: null, category_id: 1, category_slug: 'game-keys', source: 'oranos', product_type: 'automation', params: [] },
    },
  },
  {
    title: 'Create Order',
    method: 'POST',
    path: '/partner/orders',
    description: 'Creates an order for a partner-available product and deducts from the partner wallet balance. Supports an optional Idempotency-Key header to prevent accidental duplicates.',
    bodyParams: [
      { name: 'product_slug', required: true, description: 'Product slug' },
      { name: 'quantity', required: true, description: 'Quantity (1-9999)' },
      { name: 'params', required: false, description: 'Additional parameters required by the product' },
    ],
    exampleResponse: { ok: true, order_id: 456, status: 'processing', total_charged: 20.0, new_balance: 80.5 },
    errorResponse: { ok: false, error: 'insufficient_balance', required: 20.0, current: 10.0 },
  },
  {
    title: 'Get Order Status',
    method: 'GET',
    path: '/partner/orders/{id}',
    description: 'Returns the status of an order belonging to your account.',
    exampleResponse: { ok: true, data: { id: 456, status: 'completed', created_at: '2024-01-15T10:30:00Z', fulfilled_at: '2024-01-15T10:30:05Z' } },
  },
];

function ApprovedApiDocs() {
  const { user, isAuthenticated } = useAppSelector((s) => s.auth);
  const { t } = useI18n();
  const [apiKey, setApiKey] = useState<string | null>(null);
  const [showKey, setShowKey] = useState(false);
  const [activeEndpoint, setActiveEndpoint] = useState<EndpointDoc | null>(endpoints[0]);

  useEffect(() => {
    if (isAuthenticated && user) {
      partnerRequestApi.myRequest().then((res) => {
        if (res.data.api_key) setApiKey(res.data.api_key);
      }).catch(() => {});
    }
  }, [user, isAuthenticated]);

  const codeBoxClass = 'bg-gray-100 text-gray-800 dark:bg-[#123524] dark:text-white p-4 rounded-lg overflow-x-auto text-sm font-mono border border-gray-200 dark:border-[#1d5a3c]';
  const inlineCodeClass = 'font-mono text-sm bg-gray-100 text-gray-800 dark:bg-[#123524] dark:text-white px-3 py-1 rounded border border-gray-200 dark:border-[#1d5a3c]';

  if (!isAuthenticated) {
    return (
      <div className="flex items-center justify-center py-24">
        <span className="w-8 h-8 rounded-full border-2 border-green-500 border-t-transparent animate-spin" />
      </div>
    );
  }

  return (
    <PageTransition className="max-w-5xl mx-auto">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-h1 text-gray-900 dark:text-ink-900">{t('apiDocs.title')}</h1>
          <p className="text-body text-gray-600 dark:text-ink-600 mt-1">{t('apiDocs.rateLimit')}</p>
        </div>
        {apiKey && (
          <div className="flex items-center gap-3">
            <CopyToClipboard text={apiKey} onCopy={() => toast.success(t('apiDocs.keyCopied'))}>
              <Button variant="secondary">{t('apiDocs.copyKey')}</Button>
            </CopyToClipboard>
            <Button variant="ghost" size="sm" onClick={() => setShowKey(!showKey)}>
              {showKey ? '🙈' : '👁️'}
            </Button>
          </div>
        )}
      </div>

      {apiKey && (
        <div className="card-pad mb-8">
          <div className="flex items-center justify-between mb-3">
            <label className="label">{t('apiDocs.yourApiKey')}</label>
            <CopyToClipboard text={apiKey} onCopy={() => toast.success(t('apiDocs.keyCopied'))}>
              <Button variant="ghost" size="sm">{t('apiDocs.copyKey')}</Button>
            </CopyToClipboard>
          </div>
          <div className="font-mono text-small bg-gray-100 text-gray-800 dark:bg-[#123524] dark:text-white px-4 py-3 rounded-lg break-all flex items-center gap-3 border border-gray-200 dark:border-[#1d5a3c]">
            <span className="flex-1">{showKey ? apiKey : '•'.repeat(32)}</span>
            <CopyToClipboard text={apiKey} onCopy={() => toast.success(t('apiDocs.keyCopied'))}>
              <Button variant="ghost" size="sm" className="px-2">{t('apiDocs.copyKey')}</Button>
            </CopyToClipboard>
          </div>
          <p className="text-micro text-gray-500 dark:text-ink-500 mt-2">{t('apiDocs.keyWarning')}</p>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <aside className="lg:col-span-1 space-y-2">
          {endpoints.map((ep, i) => (
            <button
              key={ep.path}
              onClick={() => setActiveEndpoint(ep)}
              className={`w-full text-left px-4 py-3 rounded-lg transition-colors ${
                activeEndpoint === ep
                  ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 font-medium'
                  : 'text-gray-700 dark:text-ink-300 hover:bg-gray-100 dark:hover:bg-ink-200'
              }`}
            >
              <span className="text-xs text-gray-400 dark:text-ink-500 font-mono">{ep.method}</span>{' '}
              {ep.title}
            </button>
          ))}
        </aside>

        <div className="lg:col-span-3 space-y-6">
          {activeEndpoint && (
            <div className="card-pad space-y-4">
              <div className="flex items-center gap-3 mb-4">
                <span className={`badge ${activeEndpoint.method === 'GET' ? 'badge-completed' : 'badge-accent'} text-sm px-3 py-1`}>
                  {activeEndpoint.method}
                </span>
                <code className={inlineCodeClass}>{activeEndpoint.path}</code>
              </div>
              <p className="text-body text-gray-600 dark:text-ink-600">{activeEndpoint.description}</p>

              {activeEndpoint.queryParams && activeEndpoint.queryParams.length > 0 && (
                <div className="space-y-2">
                  <h4 className="text-small font-medium text-gray-900 dark:text-ink-900">Query Parameters</h4>
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="text-left text-gray-500 dark:text-ink-500">
                        <th className="pb-2 w-1/4">Parameter</th>
                        <th className="pb-2">Description</th>
                      </tr>
                    </thead>
                    <tbody>
                      {activeEndpoint.queryParams.map((p) => (
                        <tr key={p.name} className="border-t border-gray-100 dark:border-ink-200">
                          <td className="py-2 font-mono">{p.name}</td>
                          <td className="py-2 text-gray-700 dark:text-ink-300">{p.description}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              {activeEndpoint.bodyParams && activeEndpoint.bodyParams.length > 0 && (
                <div className="space-y-2">
                  <h4 className="text-small font-medium text-gray-900 dark:text-ink-900">Body Parameters</h4>
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="text-left text-gray-500 dark:text-ink-500">
                        <th className="pb-2 w-1/4">Parameter</th>
                        <th className="pb-2 w-1/6">Required</th>
                        <th className="pb-2">Description</th>
                      </tr>
                    </thead>
                    <tbody>
                      {activeEndpoint.bodyParams.map((p) => (
                        <tr key={p.name} className="border-t border-gray-100 dark:border-ink-200">
                          <td className="py-2 font-mono">{p.name}</td>
                          <td className="py-2">{p.required ? <span className="badge-accent text-xs">Required</span> : <span className="text-gray-400 dark:text-ink-500 text-xs">Optional</span>}</td>
                          <td className="py-2 text-gray-700 dark:text-ink-300">{p.description}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              <div className="space-y-2">
                <h4 className="text-small font-medium text-gray-900 dark:text-ink-900">Example Response</h4>
                <CopyToClipboard text={JSON.stringify(activeEndpoint.exampleResponse, null, 2)} onCopy={() => toast.success(t('common.copied'))}>
                  <Button variant="ghost" size="sm" className="mb-2">{t('common.copy')}</Button>
                </CopyToClipboard>
                <pre className={codeBoxClass}>
                  {JSON.stringify(activeEndpoint.exampleResponse, null, 2)}
                </pre>
              </div>

              {activeEndpoint.errorResponse && (
                <div className="space-y-2 border-t border-gray-100 dark:border-ink-200 pt-4">
                  <h4 className="text-small font-medium text-gray-900 dark:text-ink-900">Error Response (e.g., 402)</h4>
                  <CopyToClipboard text={JSON.stringify(activeEndpoint.errorResponse, null, 2)} onCopy={() => toast.success(t('common.copied'))}>
                    <Button variant="ghost" size="sm" className="mb-2">{t('common.copy')}</Button>
                  </CopyToClipboard>
                  <pre className={codeBoxClass}>
                    {JSON.stringify(activeEndpoint.errorResponse, null, 2)}
                  </pre>
                </div>
              )}

              <div className="pt-4 border-t border-gray-100 dark:border-ink-200">
                <h4 className="text-small font-medium text-gray-900 dark:text-ink-900 mb-2">cURL Example</h4>
                <pre className={codeBoxClass}>
                  {`curl -X ${activeEndpoint.method} "https://your-domain.com/api${activeEndpoint.path.replace('{slug}', 'product-slug').replace('{id}', '123')}" \
  -H "Accept: application/json" \
  -H "api-token: YOUR_API_KEY"${activeEndpoint.bodyParams ? ` \
  -H "Content-Type: application/json" \
  -d '${JSON.stringify({
    product_slug: 'product-slug',
    quantity: 1,
    params: {},
  })}'` : ''}`}
                </pre>
              </div>

              <div className="pt-4 border-t border-gray-100 dark:border-ink-200">
                <h4 className="text-small font-medium text-gray-900 dark:text-ink-900 mb-2">JavaScript Fetch Example</h4>
                <pre className={codeBoxClass}>
                  {`const response = await fetch("https://your-domain.com/api${activeEndpoint.path.replace('{slug}', 'product-slug').replace('{id}', '123')}", {
  method: "${activeEndpoint.method}",
  headers: {
    "Accept": "application/json",
    "api-token": "YOUR_API_KEY",
    ${activeEndpoint.bodyParams ? '"Content-Type": "application/json",' : ''}
  },
  ${activeEndpoint.bodyParams ? `body: JSON.stringify({
    product_slug: "product-slug",
    quantity: 1,
    params: {},
  }),` : ''}
});
const data = await response.json();`}
                </pre>
              </div>
            </div>
          )}
        </div>
      </div>
    </PageTransition>
  );
}
// MARKETLLY-PARTNER-APPROVAL-GATE-V1
// Do not render API documentation to unapproved users.
// Server-side partner.api middleware is the actual authorization boundary.
export default function ApiDocs() {
  const { user, isAuthenticated } = useAppSelector((s) => s.auth);
  const [approval, setApproval] = useState<'loading' | 'none' | 'pending' | 'rejected' | 'approved' | 'error'>('loading');

  useEffect(() => {
    let active = true;
    if (!isAuthenticated || !user) {
      setApproval('loading');
      return () => { active = false; };
    }
    setApproval('loading');
    partnerRequestApi.myRequest().then(({ data }) => {
      if (!active) return;
      const status = data?.request?.status;
      if (status === 'approved' && typeof data?.api_key === 'string' && data.api_key.length > 0) {
        setApproval('approved');
      } else if (status === 'pending' || status === 'rejected') {
        setApproval(status);
      } else {
        setApproval('none');
      }
    }).catch(() => { if (active) setApproval('error'); });
    return () => { active = false; };
  }, [isAuthenticated, user?.id]);

  if (approval === 'approved') return <ApprovedApiDocs />;

  const message = approval === 'pending'
    ? 'ط·ظ„ط¨ ط±ط¨ط· ظ…طھط¬ط±ظƒ ظ‚ظٹط¯ ظ…ط±ط§ط¬ط¹ط© ط§ظ„ط¥ط¯ط§ط±ط©. ظ„ظ† طھط¸ظ‡ط± ط¨ظٹط§ظ†ط§طھ ط§ظ„ط±ط¨ط· ظ‚ط¨ظ„ ط§ظ„ظ…ظˆط§ظپظ‚ط©.'
    : approval === 'rejected'
    ? 'طھظ… ط±ظپط¶ ط·ظ„ط¨ ط§ظ„ط±ط¨ط·. ط±ط§ط¬ط¹ ط­ط§ظ„ط© ط·ظ„ط¨ظƒ ظ…ظ† ظ‚ط³ظ… ط±ط¨ط· ظ…طھط¬ط±ظƒ.'
    : approval === 'none'
    ? 'ط±ظˆط§ط¨ط· API ظˆظ…ظپطھط§ط­ ط§ظ„ط±ط¨ط· ظ…طھط§ط­ط© ظپظ‚ط· ط¨ط¹ط¯ ط¥ط±ط³ط§ظ„ ط·ظ„ط¨ ط±ط¨ط· ط§ظ„ظ…طھط¬ط± ظˆظ…ظˆط§ظپظ‚ط© ط§ظ„ط¥ط¯ط§ط±ط© ط¹ظ„ظٹظ‡.'
    : approval === 'error'
    ? 'طھط¹ط°ظ‘ط± ط§ظ„طھط£ظƒط¯ ظ…ظ† ط§ظ„ظ…ظˆط§ظپظ‚ط© ط­ط§ظ„ظٹط§ظ‹. ط£ط¹ط¯ ط§ظ„ظ…ط­ط§ظˆظ„ط© ظ„ط§ط­ظ‚ط§ظ‹.'
    : 'ط¬ط§ط±ظچ ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ظ…ظˆط§ظپظ‚ط© ط§ظ„ط¥ط¯ط§ط±ط©...';

  return (
    <PageTransition className="mx-auto max-w-3xl px-4 py-12" >
      <div className="rounded-2xl border border-[var(--border)] bg-[var(--bg-card)] p-6 text-center" dir="rtl">
        <h1 className="mb-3 text-xl font-bold text-[var(--text-head)]">ط±ط¨ط· ظ…طھط¬ط±ظƒ â€” ط¨ط§ظ†طھط¸ط§ط± ظ…ظˆط§ظپظ‚ط© ط§ظ„ط¥ط¯ط§ط±ط©</h1>
        <p className="text-[var(--text-body)]">{message}</p>
      </div>
    </PageTransition>
  );
}