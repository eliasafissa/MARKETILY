import './styles/haidar-v7-final.css';
import './styles/haidar-console-v5.css';
import React from 'react';
import ReactDOM from 'react-dom/client';
import { Provider } from 'react-redux';
import { BrowserRouter } from 'react-router-dom';
import toast, { Toaster, ToastBar } from 'react-hot-toast';
import App from './App';
import { store } from './store';
import { I18nProvider } from './i18n';
import './index.css';
import { CurrencyProvider } from './currency';

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <Provider store={store}>
      <I18nProvider>
        <BrowserRouter>
          <CurrencyProvider>
            <App />
            <Toaster position="top-right" toastOptions={{
              duration: 4800,
              style: { background: '#151d21', color: '#e7f1ee', border: '1px solid #245047', borderRadius: '12px', boxShadow: '0 12px 35px rgba(0,0,0,.35)' },
              success: { iconTheme: { primary: '#35b987', secondary: '#101918' } },
              error: { iconTheme: { primary: '#ef7474', secondary: '#101918' } },
            }}>
              {(notification) => <ToastBar toast={notification}>
                {({ icon, message }) => <div dir="rtl" style={{ display: 'flex', alignItems: 'center', gap: 10, width: '100%' }}>
                  {icon}<div style={{ flex: 1, fontSize: 13, lineHeight: 1.65 }}>{message}</div>
                  <button type="button" title="إخفاء التنبيه" aria-label="إخفاء التنبيه" onClick={() => toast.dismiss(notification.id)} style={{ background: 'transparent', border: 0, color: '#9fc7bd', cursor: 'pointer', padding: 5, fontSize: 18 }}>×</button>
                </div>}
              </ToastBar>}
            </Toaster>
          </CurrencyProvider>
        </BrowserRouter>
      </I18nProvider>
    </Provider>
  </React.StrictMode>,
);
