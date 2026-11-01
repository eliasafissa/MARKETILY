import { FormEvent, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { authApi } from '@/api/client';

// MARKETLLY_FORGOT_PASSWORD_ARABIC_NO_RELOAD_V1
export default function ForgotPassword() {
  const [email, setEmail] = useState('');
  const [busy, setBusy] = useState(false);
  const [feedback, setFeedback] = useState<{ kind: 'success' | 'error'; text: string } | null>(null);
  const [waitUntil, setWaitUntil] = useState<number | null>(() => {
    const until = Number(sessionStorage.getItem('marketlly_reset_cooldown_until') ?? 0);
    return until > Date.now() ? until : null;
  });
  const [remaining, setRemaining] = useState(0);

  useEffect(() => {
    if (waitUntil === null) return;
    const update = () => {
      const left = Math.max(0, Math.ceil((waitUntil - Date.now()) / 1000));
      setRemaining(left);
      if (left === 0) sessionStorage.removeItem('marketlly_reset_cooldown_until');
    };
    update();
    const interval = window.setInterval(update, 1000);
    return () => window.clearInterval(interval);
  }, [waitUntil]);

  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    e.stopPropagation();
    if (busy || remaining > 0) return;
    const value = email.trim();
    if (!value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
      setFeedback({ kind: 'error', text: 'يرجى إدخال بريد إلكتروني صحيح.' });
      return;
    }
    setBusy(true);
    setFeedback(null);
    try {
      const response = await authApi.forgotPassword({ email: value });
      const seconds = Number(response?.data?.retry_after) || 30;
      const until = Date.now() + seconds * 1000;
      sessionStorage.setItem('marketlly_reset_cooldown_until', String(until));
      setWaitUntil(until);
      setRemaining(seconds);
      setFeedback({ kind: 'success', text: 'إذا كان البريد الإلكتروني مرتبطاً بحساب، فسيصلك رابط استعادة كلمة المرور. يرجى التحقق من البريد الوارد والرسائل غير المرغوب فيها.' });
    } catch (err: any) {
      const status = Number(err?.response?.status || 0);
      if (status === 429) {
        const raw = err?.response?.data?.retry_after ?? err?.response?.headers?.['retry-after'];
        const seconds = Number(raw);
        if (Number.isFinite(seconds) && seconds > 0 && seconds <= 3600) {
          const until = Date.now() + Math.ceil(seconds) * 1000;
          sessionStorage.setItem('marketlly_reset_cooldown_until', String(until));
          setWaitUntil(until);
          setRemaining(Math.ceil(seconds));
          setFeedback({ kind: 'error', text: `تم طلب الرابط مؤخراً. يرجى المحاولة بعد ${Math.ceil(seconds)} ثانية.` });
        } else {
          setFeedback({ kind: 'error', text: 'تم إرسال طلبات كثيرة. يرجى المحاولة بعد قليل.' });
        }
      } else if (status === 422) {
        setFeedback({ kind: 'error', text: 'يرجى إدخال بريد إلكتروني صحيح.' });
      } else if (status === 503 || status >= 500) {
        setFeedback({ kind: 'error', text: 'تعذّر إرسال رابط الاستعادة حالياً. يرجى المحاولة بعد قليل.' });
      } else if (!err?.response) {
        setFeedback({ kind: 'error', text: 'تعذّر الاتصال بالخادم. يرجى التحقق من الإنترنت والمحاولة مجدداً.' });
      } else {
        setFeedback({ kind: 'error', text: 'تعذّر إرسال رابط الاستعادة. يرجى المحاولة بعد قليل.' });
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="min-h-[65vh] flex items-center justify-center p-6" dir="rtl" lang="ar">
      <form onSubmit={submit} noValidate className="card-pad w-full max-w-md space-y-5">
        <h1 className="text-h2 text-gray-900 dark:text-ink-900">نسيت كلمة المرور؟</h1>
        <p className="text-sm text-gray-600 dark:text-ink-600">أدخل البريد الإلكتروني المرتبط بحسابك لإرسال رابط استعادة كلمة المرور.</p>
        <label htmlFor="marketlly-reset-email" className="block text-sm font-medium text-gray-800 dark:text-gray-200">البريد الإلكتروني</label>
        <input
          id="marketlly-reset-email"
          className="input w-full"
          type="email"
          autoComplete="email"
          inputMode="email"
          required
          dir="ltr"
          value={email}
          onChange={e => { setEmail(e.target.value); if (feedback?.kind === 'error') setFeedback(null); }}
          placeholder="name@example.com"
          aria-invalid={feedback?.kind === 'error'}
          aria-describedby={feedback ? 'marketlly-reset-feedback' : undefined}
        />
        <button className="btn-accent w-full" type="submit" disabled={busy || remaining > 0}>
          {busy ? 'جارٍ إرسال الرابط...' : remaining > 0 ? `إعادة المحاولة بعد ${remaining} ثانية` : 'إرسال رابط الاستعادة'}
        </button>
        {feedback && (
          <div id="marketlly-reset-feedback" role={feedback.kind === 'error' ? 'alert' : 'status'} aria-live="polite"
            className={`rounded-xl border px-4 py-3 text-sm leading-7 ${feedback.kind === 'error'
              ? 'border-red-400/40 bg-red-500/10 text-red-700 dark:text-red-300'
              : 'border-emerald-400/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'}`}>
            {feedback.text}
          </div>
        )}
        <Link to="/login" className="block text-green-600 dark:text-green-400 underline">العودة إلى تسجيل الدخول</Link>
      </form>
    </main>
  );
}
