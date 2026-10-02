import { lazy, Suspense } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { useAppSelector } from '@/hooks/useAuth';
import Layout from '@/components/Layout';
import AdminLayout from '@/components/AdminLayout';
import ErrorBoundary from '@/components/ErrorBoundary';
import Analytics from '@/components/Analytics';

// Lazy load all page components
const Home           = lazy(() => import('@/pages/public/Home'));
const Products       = lazy(() => import('@/pages/public/Products'));
const CategoryPage   = lazy(() => import('@/pages/public/CategoryPage'));
const ProductPage    = lazy(() => import('@/pages/public/ProductPage'));
const Cart           = lazy(() => import('@/pages/public/Cart'));
const Login          = lazy(() => import('@/pages/public/Login'));
const Register       = lazy(() => import('@/pages/public/Register'));
const Dashboard      = lazy(() => import('@/pages/account/Dashboard'));
const Deposit        = lazy(() => import('@/pages/account/Deposit'));
const Withdraw       = lazy(() => import('@/pages/account/Withdraw'));
const Orders         = lazy(() => import('@/pages/account/Orders'));
const Deposits       = lazy(() => import('@/pages/account/Deposits'));
const Favorites      = lazy(() => import('@/pages/account/Favorites'));
const VipPage        = lazy(() => import('@/pages/account/VipPage'));
const ManualServices = lazy(() => import('@/pages/account/ManualServices'));
const AdminDashboard   = lazy(() => import('@/pages/admin/Dashboard'));
const AdminUsers       = lazy(() => import('@/pages/admin/Users'));
const AdminProducts    = lazy(() => import('@/pages/admin/Products'));
const AdminCategories  = lazy(() => import('@/pages/admin/Categories'));
const AdminOrders      = lazy(() => import('@/pages/admin/Orders'));
const AdminManualOrders= lazy(() => import('@/pages/admin/ManualOrders'));
const AdminDeposits    = lazy(() => import('@/pages/admin/Deposits'));
const AdminWithdrawals = lazy(() => import('@/pages/admin/Withdrawals'));
const AdminSettings    = lazy(() => import('@/pages/admin/Settings'));
const AdminOranos      = lazy(() => import('@/pages/admin/Oranos'));
const LegalPage        = lazy(() => import('@/pages/public/LegalPage'));
const NotFound         = lazy(() => import('@/pages/NotFound'));
const Categories       = lazy(() => import('@/pages/public/Categories'));
const CreateStore      = lazy(() => import('@/pages/public/CreateStore'));
const MyStores         = lazy(() => import('@/pages/public/MyStores'));
const StorePage        = lazy(() => import('@/pages/public/StorePage'));
const Wallet           = lazy(() => import('@/pages/public/Wallet'));

// Partner API pages
const ConnectStore     = lazy(() => import('@/pages/public/ConnectStore'));
const CreateWebsite    = lazy(() => import('@/pages/public/CreateWebsite'));
const ApiDocs          = lazy(() => import('@/pages/account/ApiDocs'));
const PartnerRequests  = lazy(() => import('@/pages/admin/PartnerRequests'));

function LoadingSpinner() {
  return (
    <div className="flex items-center justify-center py-24">
      <span className="w-8 h-8 rounded-full border-2 border-green-500 border-t-transparent animate-spin" />
    </div>
  );
}

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAppSelector((s) => s.auth);
  return isAuthenticated ? <>{children}</> : <Navigate to="/login" replace />;
}

function AdminRoute({ children }: { children: React.ReactNode }) {
  const user = useAppSelector((s) => s.auth.user);
  const roles = (user as unknown as { roles?: Array<{ name: string }> })?.roles?.map((r) => r.name) ?? [];
  const isAdmin = roles.includes('admin') || roles.includes('moderator');
  return isAdmin ? <>{children}</> : <Navigate to="/" replace />;
}

export default function App() {
  // TODO: Re-enable once Reverb is properly configured for production
  // useEcho();

  return (
    <ErrorBoundary>
      <Analytics />
      <Suspense fallback={<LoadingSpinner />}>
        <Routes>
          <Route element={<Layout />}>
            <Route path="/" element={<Home />} />
            <Route path="/products" element={<Products />} />
            <Route path="/category/:slug" element={<CategoryPage />} />
            <Route path="/product/:slug" element={<ProductPage />} />
            <Route path="/categories" element={<Categories />} />
            <Route path="/store/:slug" element={<StorePage />} />
            <Route path="/cart" element={<Cart />} />
            <Route path="/login" element={<Login />} />
            <Route path="/register" element={<Register />} />
            <Route path="/legal/:page" element={<LegalPage />} />
            <Route path="/connect-store" element={<ConnectStore />} />
            <Route path="/create-website" element={<CreateWebsite />} />
            <Route path="/dashboard" element={<ProtectedRoute><Dashboard /></ProtectedRoute>} />
            <Route path="/wallet" element={<ProtectedRoute><Wallet /></ProtectedRoute>} />
            <Route path="/create-store" element={<ProtectedRoute><CreateStore /></ProtectedRoute>} />
            <Route path="/my-stores" element={<ProtectedRoute><MyStores /></ProtectedRoute>} />
            <Route path="/dashboard/deposit" element={<ProtectedRoute><Deposit /></ProtectedRoute>} />
            <Route path="/dashboard/withdraw" element={<ProtectedRoute><Withdraw /></ProtectedRoute>} />
            <Route path="/dashboard/orders" element={<ProtectedRoute><Orders /></ProtectedRoute>} />
            <Route path="/dashboard/vip" element={<ProtectedRoute><VipPage /></ProtectedRoute>} />
            <Route path="/dashboard/manual-services" element={<ProtectedRoute><ManualServices /></ProtectedRoute>} />
            <Route path="/dashboard/favorites" element={<ProtectedRoute><Favorites /></ProtectedRoute>} />
            <Route path="/dashboard/deposits" element={<ProtectedRoute><Deposits /></ProtectedRoute>} />
            <Route path="/dashboard/api-docs" element={<ProtectedRoute><ApiDocs /></ProtectedRoute>} />
          </Route>

          <Route element={<AdminRoute><AdminLayout /></AdminRoute>}>
            <Route path="/admin" element={<AdminDashboard />} />
            <Route path="/admin/users" element={<AdminUsers />} />
            <Route path="/admin/products" element={<AdminProducts />} />
            <Route path="/admin/categories" element={<AdminCategories />} />
            <Route path="/admin/orders" element={<AdminOrders />} />
            <Route path="/admin/orders/manual" element={<AdminManualOrders />} />
            <Route path="/admin/deposits" element={<AdminDeposits />} />
            <Route path="/admin/withdrawals" element={<AdminWithdrawals />} />
            <Route path="/admin/oranos" element={<AdminOranos />} />
            <Route path="/admin/settings" element={<AdminSettings />} />
            <Route path="/admin/partner-requests" element={<PartnerRequests />} />
          </Route>

          <Route path="*" element={<NotFound />} />
        </Routes>
      </Suspense>
    </ErrorBoundary>
  );
}