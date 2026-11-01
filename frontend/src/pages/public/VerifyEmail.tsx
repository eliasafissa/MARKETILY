import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { authApi } from '@/api/client';
import { useAppDispatch, useAppSelector, setUser } from '@/store';
import toast from 'react-hot-toast';
import PageTransition from '@/components/PageTransition';

const CHECK_INTERVAL_MS = 60000;
const SEND_COOLDOWN_MS = 30000;

export default function VerifyEmail() {
  const dispatch = useAppDispatch();
  const navigate = useNavigate();
  const user = useAppSelector((state) => state.auth.user);

  const cooldownKey = `marketlly_verify_cooldown_${user?.id ?? 'guest'}`;

  const [busy, setBusy] = useState(false);
  const [checking, setChecking] = useState(false);
  const [seconds, setSeconds] = useState(0);

  const checkingRef = useRef(false);
  const verifiedRef = useRef(false);
  const lastCheckRef = useRef(0);

  const refreshAccount = useCallback(async (force = false) => {
    if (!localStorage.getItem('token')) return;
    if (checkingRef.current || verifiedRef.current) return;

    const now = Date.now();

    if (!force && now - lastCheckRef.current < 15000) return;

    checkingRef.current = true;
    lastCheckRef.current = now;
    setChecking(true);

    try {
      const response = await authApi.me();
      const freshUser = response.data?.user;

      if (!freshUser) return;

      dispatch(setUser({
        ...freshUser,
        roles: freshUser.roles ?? response.data?.roles ?? [],
      }));

      if (freshUser.email_verified_at) {
        verifiedRef.current = true;
        toast.success('تم تأكيد بريدك الإلكتروني بنجاح');
        navigate('/dashboard', { replace: true });
      }
    } catch {
      // A temporary network failure should not create repeated requests.
    } finally {
      checkingRef.current = false;
      setChecking(false);
    }
  }, [dispatch, navigate]);

  useEffect(() => {
    const updateCooldown = () => {
      const until = Number(localStorage.getItem(cooldownKey) || 0);
      const remaining = Math.max(0, Math.ceil((until - Date.now()) / 1000));

      setSeconds(remaining);

      if (remaining === 0 && until !== 0) {
        localStorage.removeItem(cooldownKey);
      }
    };

    updateCooldown();

    // Timer updates locally; no API requests.
    const timer = window.setInterval(updateCooldown, 1000);

    // Check once when the verification page opens.
    void refreshAccount(true);

    // Only check when the page is visible.
    const poller = window.setInterval(() => {
      if (document.visibilityState === 'visible') {
        void refreshAccount();
      }
    }, CHECK_INTERVAL_MS);

    const onFocus = () => {
      if (document.visibilityState === 'visible') {
        void refreshAccount();
      }
    };

    window.addEventListener('focus', onFocus);
    document.addEventListener('visibilitychange', onFocus);

    return () => {
      window.clearInterval(timer);
      window.clearInterval(poller);
      window.removeEventListener('focus', onFocus);
      document.removeEventListener('visibilitychange', onFocus);
    };
  }, [cooldownKey, refreshAccount]);

  const sendVerification = async () => {
    if (busy || seconds > 0) return;

    setBusy(true);

    try {
      const response = await authApi.sendVerification();

      localStorage.setItem(
        cooldownKey,
        String(Date.now() + SEND_COOLDOWN_MS),
      );

      setSeconds(30);

      toast.success(
        'تم إرسال رابط التأكيد إلى بريدك الإلكتروني.',
      );
    } catch (error: any) {
      const status = error.response?.status;

      if (status === 429) {
        // Laravel's existing rate limiter is the real server protection.
        const retryAfter = Number(error.response?.data?.retry_after ?? error.response?.headers?.['retry-after']);
        const cooldown = Number.isFinite(retryAfter) && retryAfter > 0
          ? retryAfter
          : 30;

        localStorage.setItem(
          cooldownKey,
          String(Date.now() + cooldown * 1000),
        );

        setSeconds(Math.ceil(cooldown));

        toast.error('طلبات الإرسال كثيرة. حاول بعد انتهاء المؤقت.');
      } else {
        toast.error(
          'تعذّر إرسال رابط التأكيد. حاول مجدداً.',
        );
      }
    } finally {
      setBusy(false);
    }
  };

  const formattedTime =
    `${String(Math.floor(seconds / 60)).padStart(2, '0')}:` +
    `${String(seconds % 60).padStart(2, '0')}`;

  return (
    <PageTransition className="mx-auto max-w-xl space-y-5 py-12 text-right">
      <h1 className="text-2xl font-bold">تأكيد البريد الإلكتروني</h1>

      <p className="text-sm leading-7">
        افتح رسالة التأكيد من الموبايل أو الكمبيوتر واضغط على الرابط.
        سيتم تحديث حالة حسابك هنا تلقائياً.
      </p>

      <p className="text-sm text-gray-500">
        {checking
          ? 'جارٍ التحقق من حالة البريد...'
          : 'نتحقق تلقائياً كل دقيقة أثناء فتح هذه الصفحة.'}
      </p>

      <div className="flex flex-wrap gap-3">
        <button
          type="button"
          className="btn-accent disabled:opacity-50"
          disabled={busy || seconds > 0}
          onClick={sendVerification}
        >
          {busy
            ? 'جارٍ الإرسال...'
            : seconds > 0
              ? `إعادة الإرسال بعد ${formattedTime}`
              : 'إرسال رابط التأكيد'}
        </button>

        <button
          type="button"
          className="rounded-lg border border-gray-400 px-4 py-2 disabled:opacity-50"
          disabled={checking}
          onClick={() => void refreshAccount(true)}
        >
          {checking ? 'جارٍ التحقق...' : 'تحققت من بريدي'}
        </button>
      </div>

      <p className="text-xs text-gray-500">
        المؤقت يقلّل الطلبات المتكررة، والحماية الأساسية
        من كثرة الإرسال موجودة أيضاً على السيرفر.
      </p>
    </PageTransition>
  );
}