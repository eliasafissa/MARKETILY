import { useState, useEffect } from 'react';
import { Outlet, Link, useNavigate } from 'react-router-dom';
import { useAppSelector, useAppDispatch, logout } from '@/store';
import { authApi } from '@/api/client';
import Logo from './Logo';
import PageTransition from './PageTransition';
import ThemeToggle from './ThemeToggle';
import Footer from './Footer';
import CartDrawer from './CartDrawer';
import SearchPalette from './SearchPalette';
import { useI18n } from '@/i18n';
import { formatPrice } from '@/utils/format';

export default function Layout() {
  const { user, isAuthenticated } = useAppSelector((s) => s.auth);
  const cartItems = useAppSelector((s) => s.cart.items);
  const dispatch = useAppDispatch();
  const navigate = useNavigate();
  const { t } = useI18n();
  const roles = (user as unknown as { roles?: Array<{ name: string }> })?.roles?.map((r) => r.name) ?? [];

  const [mobileOpen, setMobileOpen] = useState(false);
  const [desktopDrawerOpen, setDesktopDrawerOpen] = useState(false);
  const [cartOpen, setCartOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);

  useEffect(() => {
    const handler = () => setSearchOpen(true);
    window.addEventListener('open-search-palette', handler);
    return () => window.removeEventListener('open-search-palette', handler);
  }, []);

  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setDesktopDrawerOpen(false);
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, []);

  const handleLogout = async () => {
    try { await authApi.logout(); } catch (_) { /* ignore */ }
    dispatch(logout());
    setMobileOpen(false);
    setDesktopDrawerOpen(false);
    navigate('/login');
  };

  const closeMenu = () => setMobileOpen(false);
  const toggleMenu = () => setMobileOpen((v) => !v);
  const closeDrawer = () => setDesktopDrawerOpen(false);
  const isAdmin = roles.includes('admin') || roles.includes('moderator');

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-ink flex flex-col overflow-x-hidden">
      <header className="sticky top-0 z-50 border-b border-gray-200 dark:border-ink-200 bg-white/90 dark:bg-ink/90 backdrop-blur-md">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
          <Link to="/" className="flex items-center gap-2 flex-shrink-0">
            <Logo size="sm" showText />
          </Link>

          <nav className="hidden lg:flex items-center gap-1.5 flex-1 min-w-0 overflow-hidden">
            <Link to="/categories" className="nav-link text-xs whitespace-nowrap">{t('nav.categories')}</Link>
            <Link to="/products" className="nav-link text-xs whitespace-nowrap">{t('nav.products')}</Link>
            <Link to="/connect-store" className="nav-link text-xs whitespace-nowrap">{t('nav.connectStore')}</Link>
            <Link to="/create-website" className="nav-link text-xs whitespace-nowrap">{t('nav.createWebsite')}</Link>
          </nav>

          <div className="flex items-center gap-1.5 flex-shrink-0">
            <button
              type="button"
              onClick={() => setSearchOpen(true)}
              className="p-2 rounded-lg text-gray-600 dark:text-ink-500 hover:text-gray-900 dark:hover:text-ink-900 hover:bg-gray-100 dark:hover:bg-ink-100 transition-colors"
              aria-label={t('common.search')}
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <circle cx="11" cy="11" r="8" />
                <line x1="21" y1="21" x2="16.65" y2="16.65" />
              </svg>
            </button>

            {isAuthenticated && (
              <button
                type="button"
                onClick={() => setCartOpen(true)}
                className="relative p-2 rounded-lg text-gray-600 dark:text-ink-500 hover:text-gray-900 dark:hover:text-ink-900 hover:bg-gray-100 dark:hover:bg-ink-100 transition-colors"
                aria-label={t('nav.cart')}
              >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                  <line x1="3" y1="6" x2="21" y2="6" />
                  <path d="M16 10a4 4 0 0 1-8 0" />
                </svg>
                {cartItems.length > 0 && (
                  <span className="absolute -top-0.5 -end-0.5 bg-green-500 text-ink text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">
                    {cartItems.length}
                  </span>
                )}
              </button>
            )}

            <ThemeToggle />

            <div className="hidden lg:flex items-center gap-1.5 flex-shrink-0">
              {isAuthenticated ? (
                <>
                  <Link to="/wallet" className="nav-link text-xs whitespace-nowrap text-green-400 hover:text-green-300 flex-shrink-0">
                    {t('account.walletBalance')}: {formatPrice(user?.balance)}
                  </Link>
                  <button onClick={handleLogout} className="nav-link text-xs whitespace-nowrap text-status-rejected/80 hover:text-status-rejected hover:bg-status-rejected/10 flex-shrink-0">
                    {t('nav.signOut')}
                  </button>
                </>
              ) : (
                <>
                  <Link to="/login" className="nav-link text-xs whitespace-nowrap">{t('nav.signIn')}</Link>
                  <Link to="/register" className="btn-accent btn-sm whitespace-nowrap">{t('nav.getStarted')}</Link>
                </>
              )}
            </div>

            {isAuthenticated && (
              <button
                type="button"
                onClick={() => setDesktopDrawerOpen((v) => !v)}
                className="hidden lg:inline-flex p-2 rounded-lg text-gray-700 dark:text-ink-700 hover:bg-gray-100 dark:hover:bg-ink-100 transition-colors"
                aria-label={desktopDrawerOpen ? t('common.closeMenu') : t('common.toggleMenu')}
                aria-expanded={desktopDrawerOpen}
              >
                {desktopDrawerOpen ? (
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                  </svg>
                ) : (
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                  </svg>
                )}
              </button>
            )}

            <button
              type="button"
              onClick={toggleMenu}
              className="lg:hidden p-2 rounded-lg text-gray-700 dark:text-ink-700 hover:bg-gray-100 dark:hover:bg-ink-100 transition-colors"
              aria-label={mobileOpen ? t('common.closeMenu') : t('common.toggleMenu')}
              aria-expanded={mobileOpen}
            >
              {mobileOpen ? (
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                  <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
              ) : (
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                  <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
              )}
            </button>
          </div>
        </div>
      </header>

      {desktopDrawerOpen && (
        <>
          <div
            className="fixed inset-0 bg-black/50 z-40 hidden lg:block"
            onClick={closeDrawer}
            aria-hidden="true"
          />
          <aside
            className="fixed inset-y-0 start-0 z-50 w-72 bg-white dark:bg-ink-50 border-e border-gray-200 dark:border-ink-200 shadow-xl hidden lg:flex flex-col transition-transform duration-300"
            style={{ transform: desktopDrawerOpen ? 'translateX(0)' : 'translateX(-100%)' }}
            dir="rtl"
          >
            <div className="px-4 py-3 border-b border-gray-200 dark:border-ink-200 flex items-center justify-between">
              <span className="text-small font-semibold text-gray-900 dark:text-ink-900">المزيد</span>
              <button
                type="button"
                onClick={closeDrawer}
                className="p-1.5 rounded-lg text-gray-500 dark:text-ink-500 hover:bg-gray-100 dark:hover:bg-ink-100 transition-colors"
                aria-label={t('common.closeMenu')}
              >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                  <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
              </button>
            </div>
            <nav className="px-3 py-3 space-y-0.5">
              <Link to="/dashboard/favorites" className="nav-link w-full" onClick={closeDrawer}>
                {t('nav.favorites')}
              </Link>
              <Link to="/my-stores" className="nav-link w-full" onClick={closeDrawer}>
                {t('nav.myStores')}
              </Link>
              <Link to="/dashboard" className="nav-link w-full" onClick={closeDrawer}>
                {t('nav.dashboard')}
              </Link>
              <Link to="/dashboard/api-docs" className="nav-link w-full" onClick={closeDrawer}>
                {t('nav.apiDocs')}
              </Link>
              {isAdmin && (
                <Link to="/admin" className="nav-link w-full text-green-400" onClick={closeDrawer}>
                  {t('nav.admin')}
                </Link>
              )}
            </nav>
          </aside>
        </>
      )}

      {mobileOpen && (
        <>
          <div className="fixed inset-0 bg-black/50 z-40 lg:hidden" onClick={closeMenu} />
          <div className="lg:hidden z-50 bg-white dark:bg-ink-50 border-b border-gray-200 dark:border-ink-200 shadow-lg">
            <nav className="px-4 py-3 space-y-1">
              <Link to="/categories" className="nav-link" onClick={closeMenu}>{t('nav.categories')}</Link>
              <Link to="/products" className="nav-link" onClick={closeMenu}>{t('nav.products')}</Link>

              {isAuthenticated ? (
                <>
                  <Link to="/dashboard/favorites" className="nav-link" onClick={closeMenu}>{t('nav.favorites')}</Link>
                  <Link to="/my-stores" className="nav-link" onClick={closeMenu}>{t('nav.myStores')}</Link>
                  <Link to="/connect-store" className="nav-link" onClick={closeMenu}>{t('nav.connectStore')}</Link>
                  <Link to="/create-website" className="nav-link" onClick={closeMenu}>{t('nav.createWebsite')}</Link>

                  <div className="my-2 border-t border-gray-200 dark:border-ink-200" />

                  <Link to="/dashboard" className="nav-link" onClick={closeMenu}>{t('nav.dashboard')}</Link>
                  <Link to="/wallet" className="nav-link" onClick={closeMenu}>{t('nav.wallet')}</Link>
                  <Link to="/dashboard/api-docs" className="nav-link" onClick={closeMenu}>{t('nav.apiDocs')}</Link>
                  {isAdmin && (
                    <Link to="/admin" className="nav-link text-green-400" onClick={closeMenu}>{t('nav.admin')}</Link>
                  )}

                  <div className="my-2 border-t border-gray-200 dark:border-ink-200" />

                  <div className="pt-2 pb-1 text-xs text-gray-500 dark:text-ink-500 font-semibold uppercase tracking-wider">{t('account.walletBalance')}: {formatPrice(user?.balance)}</div>
                  <button onClick={handleLogout} className="nav-link text-status-rejected/80 hover:text-status-rejected hover:bg-status-rejected/10 w-full text-left">
                    {t('nav.signOut')}
                  </button>
                </>
              ) : (
                <>
                  <div className="my-2 border-t border-gray-200 dark:border-ink-200" />
                  <Link to="/login" className="nav-link" onClick={closeMenu}>{t('nav.signIn')}</Link>
                  <Link to="/register" className="btn-accent btn-sm w-full text-center" onClick={closeMenu}>{t('nav.getStarted')}</Link>
                </>
              )}
            </nav>
          </div>
        </>
      )}

      <main className="flex-1">
        <PageTransition className="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-10">
          <Outlet />
        </PageTransition>
      </main>

      <Footer />

      <CartDrawer isOpen={cartOpen} onClose={() => setCartOpen(false)} />
      <SearchPalette isOpen={searchOpen} onClose={() => setSearchOpen(false)} />
    </div>
  );
}
