import { arabicAuthError } from '@/utils/arabicAuthError';
import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { authApi } from '@/api/client';
import { useAppDispatch, setUser } from '@/store';
import toast from 'react-hot-toast';
import Logo from '@/components/Logo';
import Button from '@/components/Button';
import PageTransition from '@/components/PageTransition';
import { Eye, EyeOff } from 'lucide-react';

export default function Login() {
  const dispatch = useAppDispatch();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [submitError, setSubmitError] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitError('');
    setLoading(true);
    try {
      const res = await authApi.login({ email, password });
      const userData = res.data.user;
      const token = res.data.token;
      const roles = userData.roles ?? [];
      localStorage.setItem('token', token);
      dispatch(setUser({ ...userData, roles }));
      toast.success('أهلاً بعودتك');
      const isAdmin = roles.some(
        (r: { name: string }) => r.name === 'admin' || r.name === 'moderator',
      );
      const next = new URLSearchParams(window.location.search).get('next');
      const safeNext = next?.startsWith('/') && !next.startsWith('//') ? next : null;
      navigate(isAdmin ? '/admin' : (!userData.email_verified_at ? '/dashboard/email-verification' : (safeNext ?? '/dashboard')));
    } catch (err: any) {
      setSubmitError(arabicAuthError(err, 'login'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <PageTransition className="min-h-[75vh] flex flex-col">
      <div className="flex-1 flex items-center justify-center px-6 py-12 lg:px-16">
        <div className="w-full max-w-md">
          <div className="lg:hidden mb-10 flex justify-center">
            <Logo size="lg" />
          </div>
          <motion.div
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.1, duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
          >
            <h2 className="text-h2 text-gray-900 dark:text-ink-900 mb-2">تسجيل الدخول</h2>
            <p className="text-body text-gray-600 dark:text-ink-600 mb-8">
              ليس لديك حساب؟{' '}
              <Link to="/register" className="text-green-400 hover:text-green-300 transition-colors">
                إنشاء حساب
              </Link>
            </p>
          </motion.div>
          {submitError && (
            <div role="alert" aria-live="assertive" className="mb-5 rounded-xl border border-red-300 bg-red-50 p-4 text-sm font-medium text-red-800 dark:border-red-800 dark:bg-red-950/30 dark:text-red-200">
              {submitError}
            </div>
          )}
          <motion.form
            onSubmit={handleSubmit}
            className="space-y-5"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.18, duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
          >
            <div>
              <label htmlFor="email" className="label">البريد الإلكتروني</label>
              <input
                id="email"
                type="email"
                required
                autoComplete="email"
                className="input"
                placeholder="you@example.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
            </div>
            <div>
              <label htmlFor="password" className="label">كلمة المرور</label>
              <div className="relative">
              <input
                id="password"
                type={showPassword ? "text" : "password"}
                required
                autoComplete="current-password"
                className="input"
                placeholder="••••••••"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
              />
              <button type="button" onClick={() => setShowPassword(v => !v)} aria-label={showPassword ? "إخفاء كلمة المرور" : "إظهار كلمة المرور"} aria-pressed={showPassword} className="absolute inset-y-0 end-3 flex items-center text-gray-400 hover:text-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-green-500">{showPassword ? <EyeOff size={19}/> : <Eye size={19}/>}</button>
              </div>
            </div>
            <Link to="/forgot-password" className="text-sm text-green-500 underline">نسيت كلمة المرور؟</Link>
            <Button
              type="submit"
              variant="accent"
              size="lg"
              loading={loading}
              className="w-full mt-2"
            >
              تسجيل الدخول
            </Button>
          </motion.form>

        </div>
      </div>
    </PageTransition>
  );
}