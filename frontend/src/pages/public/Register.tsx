import { arabicAuthError, arabicFieldError } from '@/utils/arabicAuthError';
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { authApi } from '@/api/client';
import { useAppDispatch, setUser } from '@/store';
import toast from 'react-hot-toast';
import Logo from '@/components/Logo';
import Button from '@/components/Button';
import PageTransition from '@/components/PageTransition';
import { Eye, EyeOff } from 'lucide-react';

export default function Register() {
  const dispatch = useAppDispatch();
  const navigate = useNavigate();
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' });
  const [loading, setLoading] = useState(false);
  const [submitError, setSubmitError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [shownFields, setShownFields] = useState<Record<string, boolean>>({});

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitError('');
    setFieldErrors({});
    const invalid: Record<string, string> = {};
    if (!form.name.trim()) invalid.name = 'الاسم مطلوب.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) invalid.email = 'صيغة البريد الإلكتروني غير صحيحة.';
    if (form.password.length < 10) invalid.password = 'كلمة المرور يجب أن تكون 10 أحرف على الأقل.';
    if (form.password.length >= 10 && (!/[a-z]/.test(form.password) || !/[A-Z]/.test(form.password) || !/[0-9]/.test(form.password) || !/[^A-Za-z0-9]/.test(form.password))) invalid.password = 'يجب أن تتضمن كلمة المرور أحرفاً كبيرة وصغيرة وأرقاماً ورمزاً خاصاً.';
    if (form.password !== form.password_confirmation) invalid.password_confirmation = 'تأكيد كلمة المرور غير مطابق.';
    if (Object.keys(invalid).length > 0) {
      setFieldErrors(invalid);
      setSubmitError('يرجى تصحيح الحقول الموضحة أدناه.');
      return;
    }
    setLoading(true);
    try {
      const res = await authApi.register(form);
      const token = res.data.token;
      localStorage.setItem('token', token);
      dispatch(setUser(res.data.user));
      if (res.data.verification_email_sent) {
        localStorage.setItem(`marketlly_verify_cooldown_${res.data.user.id}`, String(Date.now() + 90000));
      }
      toast.success('تم إنشاء الحساب. يرجى تأكيد بريدك الإلكتروني قبل المتابعة.');
      navigate('/dashboard/email-verification');
    } catch (err: any) {
      setSubmitError(arabicAuthError(err, 'register'));
      const errors = err?.response?.data?.errors;
      if (errors && typeof errors === 'object') {
        const translated: Record<string, string> = {};
        Object.keys(errors).forEach((field) => {
          translated[field] = arabicFieldError(field, String(errors[field]?.[0] ?? ''));
        });
        setFieldErrors(translated);
      }
    } finally {
      setLoading(false);
    }
  };

  const set = (key: string) => (e: React.ChangeEvent<HTMLInputElement>) => {
    setForm((p) => ({ ...p, [key]: e.target.value }));
    setFieldErrors((p) => { const copy = { ...p }; delete copy[key]; return copy; });
    setSubmitError('');
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
            <h2 className="text-h2 text-gray-900 dark:text-ink-900 mb-2">إنشاء حساب</h2>
            <p className="text-body text-gray-600 dark:text-ink-600 mb-8">
              لديك حساب؟{' '}
              <Link to="/login" className="text-green-400 hover:text-green-300 transition-colors">
                تسجيل الدخول
              </Link>
            </p>
          </motion.div>
          {submitError && (
            <div role="alert" aria-live="assertive" className="mb-5 rounded-xl border border-red-300 bg-red-50 p-4 text-sm font-medium text-red-800 dark:border-red-800 dark:bg-red-950/30 dark:text-red-200">
              {submitError}
            </div>
          )}
          <motion.form
            noValidate
            onSubmit={handleSubmit}
            className="space-y-5"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.18, duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
          >
            {[
              { key: 'name', label: 'الاسم', type: 'text', placeholder: 'الاسم الكامل' },
              { key: 'email', label: 'البريد الإلكتروني', type: 'email', placeholder: 'you@example.com' },
              { key: 'password', label: 'كلمة المرور', type: 'password', placeholder: '10 أحرف على الأقل، كبيرة وصغيرة وأرقام ورمز' },
              { key: 'password_confirmation', label: 'تأكيد كلمة المرور', type: 'password', placeholder: 'أعد كتابة كلمة المرور' },
            ].map(({ key, label, type, placeholder }) => (
              <div key={key}>
                <label htmlFor={key} className="label">{label}</label>
                <div className="relative">
                <input
                  id={key}
                  type={type === "password" && shownFields[key] ? "text" : type}
                  required
                  minLength={key.includes('password') ? 10 : undefined}
                  autoComplete={key === 'password_confirmation' ? 'new-password' : key}
                  className="input"
                  placeholder={placeholder}
                  value={(form as any)[key]}
                  onChange={set(key)}
                  aria-invalid={!!fieldErrors[key]}
                  aria-describedby={key === 'password' ? 'password-hint' : undefined}
                />
                {type === "password" && <button type="button" onClick={() => setShownFields(s => ({...s, [key]: !s[key]}))} aria-label={shownFields[key] ? "إخفاء كلمة المرور" : "إظهار كلمة المرور"} aria-pressed={!!shownFields[key]} className="absolute inset-y-0 end-3 flex items-center text-gray-400 hover:text-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-green-500">{shownFields[key] ? <EyeOff size={19}/> : <Eye size={19}/>}</button>}
              </div>
                {fieldErrors[key] && (
                  <p role="alert" className="mt-1.5 text-sm font-medium text-red-700 dark:text-red-300">{fieldErrors[key]}</p>
                )}
                {key === 'password' && (
                  <p id="password-hint" className="text-micro text-gray-600 dark:text-ink-500 mt-1.5">
                    10 أحرف على الأقل، تتضمن أحرفاً كبيرة وصغيرة وأرقاماً ورمزاً خاصاً.
                  </p>
                )}
              </div>
            ))}
            <Button type="submit" variant="accent" size="lg" loading={loading} className="w-full mt-2">
              إنشاء حساب
            </Button>
            <p className="text-micro text-gray-600 dark:text-ink-500 text-center">
              بإنشاء الحساب، فإنك توافق على{' '}
              <span className="text-gray-600 dark:text-ink-600">شروط الاستخدام</span>.
            </p>
          </motion.form>
        </div>
      </div>
    </PageTransition>
  );
}