import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { useAppSelector, useAppDispatch, updateQuantity, removeFromCart, clearCart } from '@/store';
import { orderApi } from '@/api/client';
import toast from 'react-hot-toast';
import Button from '@/components/Button';
import { formatPrice } from '@/utils/format';
import { useI18n } from '@/i18n';

const PAYMENT_METHODS = [
  { value: 'cash_wallet', label: 'Cash Wallet' },
  { value: 'binance_pay', label: 'Binance Pay' },
  { value: 'usdt', label: 'USDT (BEP-20)' },
];

interface CartDrawerProps {
  isOpen: boolean;
  onClose: () => void;
}

export default function CartDrawer({ isOpen, onClose }: CartDrawerProps) {
  const { items } = useAppSelector((s) => s.cart);
  const { user } = useAppSelector((s) => s.auth);
  const dispatch = useAppDispatch();
  const navigate = useNavigate();
  const { t, isRtl } = useI18n();
  const [paymentMethod, setPaymentMethod] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [confirming, setConfirming] = useState(false);

  const [binanceId, setBinanceId] = useState('');
  const [usdtAddress, setUsdtAddress] = useState('');
  const [usdtTxHash, setUsdtTxHash] = useState('');
  const [usdtNetwork, setUsdtNetwork] = useState('BEP-20');

  const hasManualProduct = items.some((i) => i.product_type === 'manual');
  const userBalance = user ? parseFloat(user.balance) : 0;
  const total = items.reduce((sum, i) => {
      const price = typeof i.price === 'number' ? i.price : parseFloat(String(i.price ?? '0'));
      const qty = typeof i.quantity === 'number' ? i.quantity : parseInt(String(i.quantity ?? '0'), 10);
      return sum + (isNaN(price) ? 0 : price) * (isNaN(qty) ? 0 : qty);
    }, 0);

  const handleMethodChange = (method: string) => {
    setPaymentMethod(method);
    if (method !== 'binance_pay') setBinanceId('');
    if (method !== 'usdt') { setUsdtAddress(''); setUsdtTxHash(''); }
  };

  const handleCheckout = async (method: string) => {
    if (items.length === 0) return;

    if (method === 'binance_pay' && !binanceId.trim()) {
      toast.error('Please enter your Binance ID or email.');
      return;
    }
    if (method === 'usdt' && (!usdtAddress.trim() || !usdtTxHash.trim())) {
      toast.error('Please enter your USDT wallet address and transaction hash.');
      return;
    }

    const meta: Record<string, string> | undefined = method === 'binance_pay'
      ? { binance_id: binanceId.trim(), binance_email: binanceId.trim() }
      : method === 'usdt'
        ? { wallet_address: usdtAddress.trim(), tx_hash: usdtTxHash.trim(), network: usdtNetwork }
        : undefined;

    setSubmitting(true);
    try {
      const res = await orderApi.create({
        items: items.map((i) => ({
          product_id: i.product_id,
          quantity: i.quantity,
          payload: i.payload,
        })),
        payment_method: method,
        ...(meta ? { meta } : {}),
      });
      toast.success(t('cart.orderPlaced', { id: res.data.order.id }));
      dispatch(clearCart());
      onClose();
      navigate('/dashboard/orders');
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? t('cart.checkoutFailed'));
    } finally {
      setSubmitting(false);
    }
  };

  const handleConfirm = () => {
    if (items.length === 0) return;

    if (hasManualProduct) {
      setPaymentMethod('cash_wallet');
      setConfirming(true);
      return;
    }

    // Auto products only
    if (userBalance >= total) {
      setPaymentMethod('cash_wallet');
      handleCheckout('cash_wallet');
      return;
    }

    // Insufficient balance → redirect to deposit
    toast.error(t('cart.insufficientBalance'));
    navigate('/dashboard/deposit');
  };

  const showPaymentPicker = hasManualProduct || (paymentMethod && paymentMethod !== 'cash_wallet');

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.2 }}
            className="fixed inset-0 bg-black/60 backdrop-blur-sm z-[998]"
            onClick={onClose}
          />

          {/* Drawer — anchored to the end side (right in LTR, left in RTL).
              Framer-motion x uses physical direction, so we flip it for RTL. */}
          <motion.div
            initial={{ x: isRtl ? '-100%' : '100%' }}
            animate={{ x: 0 }}
            exit={{ x: isRtl ? '-100%' : '100%' }}
            transition={{ type: 'spring', damping: 30, stiffness: 300 }}
            className="fixed inset-y-0 end-0 w-full sm:w-[420px] z-[999] flex flex-col
                       bg-white dark:bg-ink-50 shadow-2xl"
          >
            {/* Header */}
            <div className="flex items-center justify-between px-6 py-5 border-b border-gray-200 dark:border-ink-200">
              <h2 className="text-h3 text-gray-900 dark:text-ink-900">{t('cart.yourCart')}</h2>
              <button
                type="button"
                onClick={onClose}
                className="absolute top-4 end-4 p-2 rounded-lg text-gray-500 dark:text-ink-500 hover:bg-gray-100 dark:hover:bg-ink-100 hover:text-gray-700 dark:hover:text-ink-700 transition-colors z-[1000]"
                aria-label="Close cart"
              >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
                  <path d="M18 6 6 18M6 6l12 12" />
                </svg>
              </button>
            </div>

            {/* Items */}
            <div className="flex-1 overflow-y-auto px-6 py-4 space-y-3">
              {items.length === 0 ? (
                <div className="flex flex-col items-center justify-center h-full text-center">
                  <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="text-gray-400 dark:text-ink-500 mb-4" aria-hidden="true">
                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" strokeLinecap="round" strokeLinejoin="round" />
                    <line x1="3" y1="6" x2="21" y2="6" />
                    <path d="M16 10a4 4 0 0 1-8 0" strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                  <p className="text-gray-600 dark:text-ink-500 mb-4">{t('cart.emptyCart')}</p>
                  <Link to="/products" onClick={onClose} className="btn-accent">
                    {t('cart.browseProducts')}
                  </Link>
                </div>
              ) : (
                items.map((item, i) => (
                  <motion.div
                    key={item.product_id}
                    className="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-ink-100/50 border border-gray-100 dark:border-ink-200/50"
                    initial={{ opacity: 0, y: 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: i * 0.04 }}
                  >
                    <div className="flex-1 min-w-0">
                      <h3 className="text-sm font-medium text-gray-900 dark:text-ink-900 truncate">{item.name}</h3>
                      {item.payload && (
                        <p className="text-micro text-gray-500 dark:text-ink-500 truncate mt-0.5">
                          {Object.entries(item.payload).map(([k, v]) => `${k}: ${v}`).join(' · ')}
                        </p>
                      )}
                    </div>

                    <div className="flex items-center gap-1">
                      <button
                        onClick={() => dispatch(updateQuantity({ product_id: item.product_id, quantity: Math.max(1, item.quantity - 1) }))}
                        className="w-7 h-7 rounded-lg bg-white dark:bg-ink-100 border border-gray-200 dark:border-ink-200 flex items-center justify-center text-gray-700 dark:text-ink-700 hover:bg-gray-100 dark:hover:bg-ink-200 transition-colors"
                      >
                        −
                      </button>
                      <span className="text-sm font-medium text-gray-900 dark:text-ink-900 w-6 text-center tabular-nums">
                        {item.quantity}
                      </span>
                      <button
                        onClick={() => dispatch(updateQuantity({ product_id: item.product_id, quantity: item.quantity + 1 }))}
                        className="w-7 h-7 rounded-lg bg-white dark:bg-ink-100 border border-gray-200 dark:border-ink-200 flex items-center justify-center text-gray-700 dark:text-ink-700 hover:bg-gray-100 dark:hover:bg-ink-200 transition-colors"
                      >
                        +
                      </button>
                    </div>

                    <span className="text-sm font-semibold text-green-400 tabular-nums min-w-[70px] text-end">
                      {formatPrice(item.price * item.quantity)}
                    </span>

                    <button
                      onClick={() => dispatch(removeFromCart(item.product_id))}
                      className="p-1.5 text-gray-400 dark:text-ink-500 hover:text-status-rejected transition-colors"
                      aria-label={t('cart.remove')}
                    >
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                      </svg>
                    </button>
                  </motion.div>
                ))
              )}
            </div>

            {/* Footer — checkout */}
            {items.length > 0 && (
              <div className="border-t border-gray-200 dark:border-ink-200 px-6 py-5 space-y-4 bg-white dark:bg-ink-50">
                {/* Payment method — only show for manual products or when user chose alternative */}
                {!confirming && showPaymentPicker && (
                  <div className="space-y-2">
                    {PAYMENT_METHODS.map((m) => (
                      <label
                        key={m.value}
                        className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all duration-150 ${
                          paymentMethod === m.value
                            ? 'border-green-500 bg-green-500/5'
                            : 'border-gray-200 dark:border-ink-200 bg-gray-50 dark:bg-ink-100/50 hover:border-ink-300'
                        }`}
                      >
                        <input
                          type="radio"
                          name="payment_method_drawer"
                          value={m.value}
                          checked={paymentMethod === m.value}
                          onChange={(e) => handleMethodChange(e.target.value)}
                          className="green-green-500"
                        />
                        <span className="text-sm text-gray-800 dark:text-ink-800">{m.label}</span>
                      </label>
                    ))}
                  </div>
                )}

                {/* Crypto fields */}
                {paymentMethod === 'binance_pay' && !confirming && (
                  <div className="space-y-2">
                    <label className="label">Your Binance ID or Account Email</label>
                    <input
                      type="text"
                      className="input"
                      placeholder="Binance ID or email@example.com"
                      value={binanceId}
                      onChange={(e) => setBinanceId(e.target.value)}
                    />
                  </div>
                )}

                {paymentMethod === 'usdt' && !confirming && (
                  <div className="space-y-3">
                    <div>
                      <label className="label">USDT Wallet Address (BEP-20)</label>
                      <input type="text" className="input" placeholder="0x..." value={usdtAddress} onChange={(e) => setUsdtAddress(e.target.value)} />
                    </div>
                    <div>
                      <label className="label">Transaction Hash</label>
                      <input type="text" className="input" placeholder="0x..." value={usdtTxHash} onChange={(e) => setUsdtTxHash(e.target.value)} />
                    </div>
                  </div>
                )}

                {/* Total */}
                <div className="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-ink-200">
                  <span className="text-gray-600 dark:text-ink-500">{t('cart.total')}</span>
                  <span className="text-h3 text-green-400 tabular-nums">{formatPrice(total)}</span>
                </div>

                {/* Confirm / Checkout */}
                {confirming ? (
                  <div className="space-y-3">
                    <div className="card-pad bg-gray-50 dark:bg-ink-100/50 p-4">
                      <p className="text-sm text-gray-700 dark:text-ink-700">
                        <strong className="text-gray-900 dark:text-ink-900">{items.length}</strong> {items.length === 1 ? t('cart.item') : t('cart.items')} {t('cart.for')} <strong className="text-green-400">{formatPrice(total)}</strong>
                      </p>
                    </div>
                    <div className="flex gap-2">
                      <Button variant="accent" size="lg" className="flex-1" loading={submitting} onClick={() => handleCheckout(paymentMethod!)}>
                        {t('cart.confirmOrder')}
                      </Button>
                      <Button variant="secondary" onClick={() => setConfirming(false)} disabled={submitting}>
                        {t('cart.editOrder')}
                      </Button>
                    </div>
                  </div>
                ) : (
                  <Button variant="accent" size="lg" className="w-full" onClick={handleConfirm}>
                    {hasManualProduct ? t('cart.reviewAndPlace') : t('cart.confirmOrder')}
                  </Button>
                )}
              </div>
            )}
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}
