import axios from 'axios';

const API_URL = import.meta.env.VITE_API_URL || 'https://marketly-backend-production.up.railway.app/api';

console.log('[API] Using backend URL:', API_URL);

const api = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

// Auto-send Bearer token from localStorage on every request
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use(
  (response) => {
    return response;
  },
  async (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token');
      localStorage.removeItem('auth_user');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;

// Auth
export const authApi = {
  register: (data: { name: string; email: string; password: string; password_confirmation: string }) =>
    api.post('/auth/register', data),
  login: (data: { email: string; password: string }) => api.post('/auth/login', data),
  logout: () => api.post('/auth/logout'),
  me: () => api.get('/auth/me'),
};

// Categories
export const categoryApi = {
  list: () => api.get('/categories'),
  show: (slug: string) => api.get(`/categories/${slug}`),
  formSchema: (slug: string) => api.get(`/categories/${slug}/form-schema`),
};

// Products
export const productApi = {
  list: (params?: Record<string, string>) => api.get('/products', { params }),
  show: (slug: string) => api.get(`/products/${slug}`),
};

// Orders
export const orderApi = {
  list: () => api.get('/orders'),
  create: (data: {
    items: Array<{
      product_id: number;
      quantity: number;
      payload?: string[] | Record<string, unknown>;
    }>;
    payment_method: string;
    meta?: Record<string, string>;
    store_id?: number;
  }) => api.post('/orders', data),
  show: (id: number) => api.get(`/orders/${id}`),
};

// Deposits
export const depositApi = {
  list: () => api.get('/deposits'),
  create: (data: { amount: number; method: string }) => api.post('/deposits', data),
};

// Withdrawals

// Favorites
export const favoriteApi = {
  list: () => api.get('/favorites'),
  add: (productId: number) => api.post('/favorites', { product_id: productId }),
  remove: (productId: number) => api.delete(`/favorites/${productId}`),
  check: (productId: number) => api.get(`/favorites/${productId}`),
};

// Withdrawals
export const withdrawalApi = {
  list: () => api.get('/withdrawals'),
  create: (data: { amount: number; wallet_address: string; method: string }) =>
    api.post('/withdrawals', data),
};

// Stores
export const storeApi = {
  create: (name: string, description: string) => api.post('/stores', { name, description }),
  my: () => api.get('/stores/my'),
  getBySlug: (slug: string) => api.get(`/stores/${slug}`),
  updateProductPrice: (storeId: number, productId: number, custom_price: number) =>
    api.post(`/stores/${storeId}/products/${productId}`, { custom_price }),
};

// VIP
export const vipApi = {
  status: () => api.get('/vip/status'),
  upgrade: (target: string) => api.post('/vip/upgrade', { target }),
};

// Transactions
export const transactionApi = {
  list: () => api.get('/transactions'),
};

// Admin — Users
export const adminUserApi = {
  list: (params?: Record<string, string>) => api.get('/admin/users', { params }),
  update: (id: number, data: Record<string, unknown>) => api.patch(`/admin/users/${id}`, data),
  delete: (id: number) => api.delete(`/admin/users/${id}`),
  adjustBalance: (id: number, data: { amount: number; note?: string }) =>
    api.post(`/admin/users/${id}/balance`, data),
};

// Admin — Products
export const adminSyncApi = {
  applyMarkup: () => api.post('/admin/sync/apply-markup'),
  syncProducts: () => api.post('/admin/sync/products'),
};

export const adminProductApi = {
  list: (params?: Record<string, string>) => api.get('/admin/products', { params }),
  stats: () => api.get('/admin/products/stats'),
  create: (data: Record<string, unknown>) => api.post('/admin/products', data),
  update: (id: number, data: Record<string, unknown>) => api.patch(`/admin/products/${id}`, data),
  delete: (id: number) => api.delete(`/admin/products/${id}`),
};

// Admin — Categories
export const adminCategoryApi = {
  list: () => api.get('/admin/categories'),
  create: (data: Record<string, unknown>) => api.post('/admin/categories', data),
  update: (id: number, data: Record<string, unknown>) => api.patch(`/admin/categories/${id}`, data),
  delete: (id: number) => api.delete(`/admin/categories/${id}`),
};

// Admin — Orders
export const adminOrderApi = {
  list: (params?: Record<string, string>) => api.get('/admin/orders', { params }),
  pendingManual: () => api.get('/admin/orders/pending-manual'),
  pendingManualCount: () => api.get('/admin/orders/pending-manual/count'),
  updateStatus: (id: number, data: { status: string; notes?: string }) =>
    api.patch(`/admin/orders/${id}/status`, data),
};

// Admin — Deposits
export const adminDepositApi = {
  list: (params?: Record<string, string>) => api.get('/admin/deposits', { params }),
  approve: (id: number) => api.post(`/admin/deposits/${id}/approve`),
  reject: (id: number, reason: string) => api.post(`/admin/deposits/${id}/reject`, { reason }),
};

// Admin — Withdrawals
export const adminWithdrawalApi = {
  list: (params?: Record<string, string>) => api.get('/admin/withdrawals', { params }),
  approve: (id: number) => api.post(`/admin/withdrawals/${id}/approve`),
  reject: (id: number, reason: string) => api.post(`/admin/withdrawals/${id}/reject`, { reason }),
};

// Admin — Settings
export const adminSettingsApi = {
  list: () => api.get('/admin/settings'),
  update: (data: { key: string; value: string; type?: string }) => api.post('/admin/settings', data),
  bulkUpdate: (items: Array<{ key: string; value: string; type?: string }>) =>
    api.post('/admin/settings/bulk', { items }),
  updateCompany: (data: Record<string, string>) => api.put('/admin/settings/company', data),
  updateLegal: (page: string, data: { content_en?: string; content_ar?: string }) =>
    api.put(`/admin/settings/legal/${page}`, data),
};

// Public settings
export const settingsApi = {
  company: () => api.get('/settings/company'),
  legal: (page: string, locale = 'en') => api.get(`/settings/legal/${page}?locale=${locale}`),
};

// Admin — Dashboard
export const adminDashboardApi = {
  stats: () => api.get('/admin/dashboard'),
  health: () => api.get('/admin/health'),
};

// Partner Request (user)
export const partnerRequestApi = {
  myRequest: () => api.get('/partner-request'),
  create: (data: { store_name: string; store_url: string; phone: string; notes?: string }) =>
    api.post('/partner-request', data),
};

// Admin Partner Requests
export const adminPartnerApi = {
  list: (params?: Record<string, string>) => api.get('/admin/partner-requests', { params }),
  approve: (id: number) => api.post(`/admin/partner-requests/${id}/approve`),
  reject: (id: number, reason: string) => api.post(`/admin/partner-requests/${id}/reject`, { reason }),
};