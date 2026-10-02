import { useState, useEffect, useMemo } from 'react';
import { Outlet, Link, useNavigate, useLocation } from 'react-router-dom';
import { useAppDispatch, useAppSelector, logout } from '@/store';
import { authApi, adminOrderApi } from '@/api/client';
import Logo from './Logo';
import PageTransition from './PageTransition';
import { useI18n } from '@/i18n';
import ThemeToggle from './ThemeToggle';

const NAV_ITEMS = [
  { to: '/admin', key: 'admin.dashboard', exact: true, icon: (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
      <rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
    </svg>
  )},
  { to: '/admin/users', key: 'admin.users', icon: (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
      <circle cx="9" cy="7" r="4"/>
      <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
    </svg>
  )},
  { to: '/admin/products', key: 'admin.products', icon: (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
      <line x1="3" y1="6" x2="21" y2="6"/>
      <path d="M16 10a4 4 0 0 1-8 0"/>
    </svg>
  )},
  { to: '/admin/categories', key: 'admin.categories', icon: (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <line x1="8" y1="6" x2="21" y2="6"/>
      <line x1="8" y1="12" x2="21" y2="12"/>
      <line x1="8" y1="18" x2="21" y2="18"/>
      <line x1="3" y1="6" x2="3.01" y2="6"/>
      <line x1="3" y1="12" x2="3.01" y2="12"/>
      <line x1="3" y1="18" x2="3.01" y2="18"/>
    </svg>
  )},
  { to: '/admin/orders', key: 'admin.orders', icon: (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
      <rect x="9" y="3" width="6" height="4" rx="1"/>
    </svg>
  )},
  { to: '/admin/orders/manual', key: 'admin.manualOrders', icon: (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
      <polyline points="14 2 14 8 20 8"/>
    </svg>
  )},
  { to: '/admin/deposits', key: 'admin.deposits', icon: (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M12 5v14M5 12l7 7 7-7"/>
    </svg>
  )},
  { to: '/admin/withdrawals', key: 'admin.withdrawals', icon: (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M12 19V5M5 12l7-7 7 7"/>
    </svg>
  )},
  { to: '/admin/oranos', key: 'admin.oranosMonitor', icon: (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <circle cx="12" cy="12" r="10"/>
      <path d="M12 6v6l4 2"/>
    </svg>
  )},
  { to: '/admin/partner-requests', key: 'admin.partnerRequests', icon: (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
      <circle cx="9" cy="7" r="4"/>
      <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
    </svg>
  )},
  { to: '/admin/settings', key: 'admin.settings', icon: (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <circle cx="12" cy="12" r="3"/>
      <path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/>
    </svg>
  )},
];

export default function AdminLayout() {
  const dispatch = useAppDispatch();
  const navigate = useNavigate();
  const location = useLocation();
  const { t } = useI18n();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [pendingCount, setPendingCount] = useState(0);

  const user = useAppSelector((s: any) => s.auth.user);
  const roleNames: string[] = useMemo(
    () => (user?.roles ?? []).map((r: any) => r.name),
    [user]
  );
  const isAdmin = roleNames.includes('admin');
  const isModerator = roleNames.includes('moderator') && !isAdmin;

  const ADMIN_ONLY_ROUTES = new Set<string>([
    '/admin/users',
    '/admin/products',
    '/admin/categories',
    '/admin/oranos',
    '/admin/settings',
  ]);

  const visibleNavItems = useMemo(
    () => NAV_ITEMS.filter((item) => !isModerator || !ADMIN_ONLY_ROUTES.has(item.to)),
    [isModerator]
  );

  useEffect(() => {
    adminOrderApi.pendingManualCount()
      .then((r) => setPendingCount(r.data.count ?? 0))
      .catch(() => {});
  }, []);

  const handleLogout = async () => {
    try { await authApi.logout(); } catch (_) { /* ignore */ }
    dispatch(logout());
    navigate('/login');
  };

  const closeSidebar = () => setSidebarOpen(false);
  const toggleSidebar = () => setSidebarOpen((v: boolean) => !v);

  return (
    <div className="flex min-h-screen bg-gray-50 dark:bg-ink">
      {sidebarOpen && (
        <div className="fixed inset-0 bg-black/50 z-30 lg:hidden" onClick={closeSidebar} />
      )}

      <aside
        className={`
          fixed inset-y-0 start-0 z-40 w-64 flex flex-col
          bg-white dark:bg-ink-50
          border border-gray-200 dark:border-ink-200
          ltr:border-r rtl:border-l
          transition-transform duration-300
          lg:z-40 lg:flex-shrink-0 lg:h-screen
          ${sidebarOpen ? 'translate-x-0' : '-translate-x-full ltr:-translate-x-full rtl:translate-x-full'}
          lg:ltr:translate-x-0 lg:rtl:translate-x-0
        `}
      >
        <div className="flex items-center justify-between px-4 py-4 border-b border-gray-200 dark:border-ink-200">
          <Link to="/" className="flex items-center gap-2.5" onClick={closeSidebar}>
            <Logo size="sm" />
          </Link>
          <button type="button" onClick={closeSidebar} className="lg:hidden relative z-50 p-1.5 rounded-md text-gray-600 dark:text-ink-500 hover:text-gray-900 dark:hover:text-ink-900 hover:bg-gray-100 dark:hover:bg-ink-100 transition-colors" aria-label="Close menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>

        <p className="text-micro text-gray-500 dark:text-ink-500 px-4 py-2">{t('nav.adminPanel')}</p>

        <nav className="flex-1 px-3 py-2 space-y-0.5 overflow-y-auto no-scrollbar">
          {visibleNavItems.map((item) => {
            const isActive = item.exact ? location.pathname === item.to : location.pathname.startsWith(item.to);
            const showBadge = item.to === '/admin/orders' && pendingCount > 0;
            return (
              <Link key={item.to} to={item.to} onClick={closeSidebar} className={`nav-link text-sm flex items-center gap-2 ${isActive ? 'nav-link-active' : ''}`}>
                <span className={isActive ? 'text-green-500 dark:text-green-400' : 'text-gray-500 dark:text-ink-500'}>{item.icon}</span>
                <span className="flex-1">{t(item.key)}</span>
                {showBadge && <span className="ml-auto inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-xs font-bold text-white bg-green-500 rounded-full">{pendingCount > 99 ? '99+' : pendingCount}</span>}
              </Link>
            );
          })}
        </nav>

        <div className="px-3 py-4 border-t border-gray-200 dark:border-ink-200 space-y-0.5">
          <Link to="/dashboard" className="nav-link text-sm" onClick={closeSidebar}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-500 dark:text-ink-500">
              <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
              <rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
            </svg>
            {t('nav.dashboard')}
          </Link>
          <Link to="/" className="nav-link text-sm" onClick={closeSidebar}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-500 dark:text-ink-500">
              <path d="M15 18l-6-6 6-6"/>
            </svg>
            {t('nav.backToSite')}
          </Link>
          <button onClick={handleLogout} className="nav-link text-sm w-full text-status-rejected/70 hover:text-status-rejected hover:bg-status-rejected/10">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            {t('nav.signOut')}
          </button>
        </div>
      </aside>

      <div className="flex-1 flex flex-col min-w-0 lg:ltr:ml-64 lg:rtl:mr-64">
        <header className="sticky top-0 z-40 h-14 px-4 lg:px-8 flex items-center justify-between gap-2 bg-white/80 dark:bg-ink/80 backdrop-blur-md border-b border-gray-200 dark:border-ink-200">
          <button onClick={toggleSidebar} className="lg:hidden p-2 rounded-md bg-white dark:bg-ink-50 border border-gray-200 dark:border-ink-200 text-gray-700 dark:text-ink-700 hover:bg-gray-100 dark:hover:bg-ink-100" aria-label="Toggle sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <line x1="3" y1="6" x2="21" y2="6"/>
              <line x1="3" y1="12" x2="21" y2="12"/>
              <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
          </button>
          <div className="flex items-center gap-2">
            <ThemeToggle />
          </div>
        </header>
        <main className="flex-1 overflow-auto">
          <PageTransition className="p-4 lg:p-8 max-w-7xl mx-auto w-full">
            <Outlet />
          </PageTransition>
        </main>
      </div>
    </div>
  );
}