import { useState, useEffect, useRef } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { useAppSelector, useAppDispatch, removeFromCart, clearCart } from '@/store';
import { orderApi } from '@/api/client';
import toast from 'react-hot-toast';
import Button from '@/components/Button';
import EmptyState from '@/components/EmptyState';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';
import { formatPrice } from '@/utils/format';

const PAYMENT_METHODS = [{ value: 'cash_wallet', label: 'رصيد الموقع / Site wallet' }];

export default function Cart() {
  const { items } = useAppSelector((s) => s.cart);
  const { isAuthenticated } = useAppSelector((s) => s.auth);
  const checkoutKey = useRef<string | null>(null);
  const dispatch = useAppDispatch();
  const navigate = useNavigate();
  const { t } = useI18n();
  const paymentMethod = 'cash_wallet';
  const [submitting, setSubmitting] = useState(false);
  const [confirming, setConfirming] = useState(false);
  const [quote, setQuote] = useState<any>(null);
  const [quoteError, setQuoteError] = useState('');
  const [quoting, setQuoting] = useState(false);

  useEffect(() => {
    let live = true;
    setConfirming(false);
    setQuote(null);
    checkoutKey.current = null;

    if (items.length === 0 || !isAuthenticated) return;

    setQuoting(true);
    setQuoteError('');

    orderApi.quote({ items: items.map(i => ({ product_id: i.product_id, quantity: i.quantity, payload: i.payload })) })
      .then(res => { if (live) setQuote(res.data); })
      .catch(err => { if (live) setQuoteError(err.response?.data?.message ?? 'تعذر حساب السعر الحالي. أعد المحاولة.'); })
      .finally(() => { if (live) setQuoting(false); });

    return () => { live = false; };
  }, [items, isAuthenticated]);

  const actualTotal = quote ? String(quote.total) : null;

  const handleConfirm = () => {
    if (!isAuthenticated) {
      toast.error('سجّل الدخول قبل الشراء / Log in first');
      navigate('/login?next=/cart');
      return;
    }
    if (items.length === 0 || !quote || quoting || quoteError) return;
    setConfirming(true);
  };

  const handleCheckout = async () => {
    if (!isAuthenticated) {
      navigate('/login?next=/cart');
      return;
    }
    if (items.length === 0 || !quote || quoting || quoteError) return;

    checkoutKey.current ||= `checkout_${crypto.randomUUID().replace(/-/g, '')}`;

    setSubmitting(true);
    try {
      const res = await orderApi.create({
        items: items.map((i) => ({
          product_id: i.product_id,
          quantity: i.quantity,
          payload: i.payload,
        })),
        payment_method: paymentMethod,
        expected_total: String(quote.total),
      }, checkoutKey.current);

      toast.success(t('cart.orderPlaced', { id: res.data.order.id }));
      checkoutKey.current = null;
      dispatch(clearCart());
      navigate('/dashboard/orders');
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? t('cart.checkoutFailed'));
      if (err.response && err.response.status !== 500) checkoutKey.current = null;

      setQuote(null);
      setConfirming(false);
      setQuoting(true);

      orderApi.quote({ items: items.map(i => ({ product_id: i.product_id, quantity: i.quantity, payload: i.payload })) })
        .then(res => { setQuote(res.data); setQuoteError(''); })
        .catch(e => setQuoteError(e.response?.data?.message ?? 'تعذر تحديث السعر'))
        .finally(() => setQuoting(false));
    } finally {
      setSubmitting(false);
    }
  };

  if (items.length === 0) {
    return (
      <PageTransition>
        <EmptyState
          icon={
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true">
              <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" strokeLinecap="round" strokeLinejoin="round" />
              <line x1="3" y1="6" x2="21" y2="6" />
              <path d="M16 10a4 4 0 0 1-8 0" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          }
          title={t('cart.emptyCart')}
          description={t('cart.emptyDescription')}
          action={{ label: t('cart.browseProducts'), to: '/products' }}
        />
      </PageTransition>
    );
  }

  return (
    <PageTransition>
      <h1 className="text-h1 text-gray-900 dark:text-ink-900 mb-8">{t('cart.yourCart')}</h1>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2 space-y-3">
          {items.map((item, i) => {
            const quotedLine = quote?.items?.find((line: any) => line.product_id === item.product_id);

            return (
              <motion.div
                key={item.product_id}
                className="card-pad flex items-center gap-4"
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: i * 0.05, duration: 0.3 }}
              >
                <div className="flex-1 min-w-0">
                  <h3 className="text-sm font-semibold text-gray-900 dark:text-ink-900 truncate">{item.name}</h3>
                  {item.payload && (
                    <p className="text-micro text-gray-600 dark:text-ink-500 mt-0.5 truncate">
                      {Array.isArray(item.payload)
                        ? (item.payload as string[]).filter(Boolean).join(' · ')
                        : Object.values(item.payload).filter(Boolean).join(' · ')}
                    </p>
                  )}
                </div>

                <span className="text-sm font-medium text-gray-600 dark:text-ink-600">الكمية: {item.quantity}</span>

                <span className="text-h3 text-green-400 w-28 text-right tabular-nums">
                  {quote ? formatPrice(quotedLine?.line_total ?? 0) : 'جارٍ حساب السعر...'}
                </span>

                <button
                  onClick={() => dispatch(removeFromCart(item.product_id))}
                  className="btn-ghost btn-sm text-status-rejected/70 hover:text-status-rejected p-1.5"
                  aria-label={t('cart.remove')}
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                    <path d="M18 6 6 18M6 6l12 12" />
                  </svg>
                </button>
              </motion.div>
            );
          })}
        </div>

        <div className="lg:col-span-1">
          <div className="card-pad sticky top-24 space-y-5">
            <h2 className="text-h3 text-gray-900 dark:text-ink-900">{t('cart.checkout')}</h2>

            <div className="space-y-2 rounded-lg border border-ink-200 p-4">
              <p className="font-medium">الدفع من رصيد الموقع فقط / Wallet checkout</p>
              <p className="text-sm text-gray-600 dark:text-ink-500">اشحن رصيدك باستخدام شام كاش، سيرياتيل كاش، Binance أو USDT، ثم اشترِ بعد تأكيد الإيداع.</p>
              <Link to="/dashboard/deposit" className="text-green-500 underline">شحن الرصيد / Top up wallet</Link>
              {!isAuthenticated && <Link to="/login?next=/cart" className="block text-green-500 underline">تسجيل الدخول لإتمام الشراء / Log in</Link>}
            </div>

            <div className="border-t border-ink-200 pt-4 space-y-1">
              <div className="flex justify-between text-small text-gray-600 dark:text-ink-500">
                <span>{t('cart.subtotal')}</span>
                <span>{quote ? formatPrice(actualTotal) : 'جارٍ حساب السعر...'}</span>
              </div>
              <div className="flex justify-between text-body text-gray-600 dark:text-ink-600">
                <span>{t('cart.total')}</span>
                <span className="text-h3 text-green-400">{quote ? formatPrice(actualTotal) : 'جارٍ حساب السعر...'}</span>
              </div>
            </div>

            {!isAuthenticated && <p className="text-sm">الشراء يتطلب تسجيل الدخول / Login required.</p>}
            {quoting && <p className="text-small text-gray-500">جارٍ حساب السعر الحالي...</p>}
            {quoteError && <p className="text-small text-status-rejected">{quoteError}</p>}
            {quote && Number(quote.vip_discount_percent) > 0 && <p className="text-small text-green-500">خصم {String(quote.vip_level).toUpperCase()} مطبق؛ الأسعار لا تقل عن تكلفة Oranos.</p>}

            {confirming ? (
              <div className="space-y-3">
                <div className="card-pad bg-gray-100 dark:bg-ink-100/40 p-4 space-y-1 text-small">
                  <p className="text-gray-700 dark:text-ink-700">
                    <strong className="text-gray-900 dark:text-ink-900">{items.length}</strong>{' '}
                    {items.length === 1 ? t('cart.item') : t('cart.items')}{' '}
                    {t('cart.for')}{' '}
                    <strong className="text-green-400">{quote ? formatPrice(actualTotal) : 'جارٍ حساب السعر...'}</strong>
                  </p>
                  <p className="text-gray-600 dark:text-ink-500">
                    {t('cart.payWith')}{' '}
                    <strong className="text-gray-700 dark:text-ink-700">
                      {PAYMENT_METHODS.find((m) => m.value === paymentMethod)?.label}
                    </strong>
                  </p>
                </div>

                <div className="flex gap-2">
                  <Button
                    variant="accent"
                    size="lg"
                    className="flex-1"
                    loading={submitting}
                    disabled={!isAuthenticated || !quote || quoting || Boolean(quoteError)}
                    onClick={handleCheckout}
                  >
                    {t('cart.confirmOrder')}
                  </Button>

                  <Button
                    variant="secondary"
                    onClick={() => setConfirming(false)}
                    disabled={submitting}
                  >
                    {t('cart.editOrder')}
                  </Button>
                </div>
              </div>
            ) : (
              <Button
                variant="accent"
                size="lg"
                className="w-full"
                onClick={handleConfirm}
                disabled={!isAuthenticated || !quote || quoting || Boolean(quoteError)}
              >
                {t('cart.reviewAndPlace')}
              </Button>
            )}

            <p className="text-micro text-gray-600 dark:text-ink-500 text-center">
              {t('cart.ordersProcessed')}
            </p>
          </div>
        </div>
      </div>
    </PageTransition>
  );
}