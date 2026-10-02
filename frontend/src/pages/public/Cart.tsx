import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { useAppSelector, useAppDispatch, updateQuantity, removeFromCart, clearCart } from '@/store';
import { orderApi } from '@/api/client';
import toast from 'react-hot-toast';
import Button from '@/components/Button';
import EmptyState from '@/components/EmptyState';
import PageTransition from '@/components/PageTransition';
import { formatPrice } from '@/utils/format';
import { useI18n } from '@/i18n';

const PAYMENT_METHODS = [
  { value: 'cash_wallet', label: 'Cash Wallet' },
  { value: 'binance_pay', label: 'Binance Pay' },
  { value: 'usdt', label: 'USDT (BEP-20)' },
];

export default function Cart() {
  const { items } = useAppSelector((s) => s.cart);
  const dispatch = useAppDispatch();
  const navigate = useNavigate();
  const { t } = useI18n();
  const [paymentMethod, setPaymentMethod] = useState('cash_wallet');
  const [submitting, setSubmitting] = useState(false);
  const [confirming, setConfirming] = useState(false);

  // Payment-specific fields
  const [binanceId, setBinanceId] = useState('');
  const [usdtAddress, setUsdtAddress] = useState('');
  const [usdtTxHash, setUsdtTxHash] = useState('');
  const [usdtNetwork, setUsdtNetwork] = useState('BEP-20');

  // Reset crypto fields when payment method changes
  const handleMethodChange = (method: string) => {
    setPaymentMethod(method);
    if (method !== 'binance_pay') setBinanceId('');
    if (method !== 'usdt') { setUsdtAddress(''); setUsdtTxHash(''); }
  };

  const total = items.reduce((sum, i) => {
      const price = typeof i.price === 'number' ? i.price : parseFloat(String(i.price ?? '0'));
      const qty = typeof i.quantity === 'number' ? i.quantity : parseInt(String(i.quantity ?? '0'), 10);
      return sum + (isNaN(price) ? 0 : price) * (isNaN(qty) ? 0 : qty);
    }, 0);

  const handleConfirm = () => {
    if (items.length === 0) return;
    setConfirming(true);
  };

  const handleCheckout = async () => {
    if (items.length === 0) return;

    // Validate crypto payment fields
    if (paymentMethod === 'binance_pay' && !binanceId.trim()) {
      toast.error('Please enter your Binance ID or email.');
      return;
    }
    if (paymentMethod === 'usdt' && (!usdtAddress.trim() || !usdtTxHash.trim())) {
      toast.error('Please enter your USDT wallet address and transaction hash.');
      return;
    }

    const meta: Record<string, string> | undefined = paymentMethod === 'binance_pay'
      ? { binance_id: binanceId.trim(), binance_email: binanceId.trim() }
      : paymentMethod === 'usdt'
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
        payment_method: paymentMethod,
        ...(meta ? { meta } : {}),
      });
      toast.success(t('cart.orderPlaced', { id: res.data.order.id }));
      dispatch(clearCart());
      navigate('/dashboard/orders');
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? t('cart.checkoutFailed'));
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
        {/* Items */}
        <div className="lg:col-span-2 space-y-3">
          {items.map((item, i) => (
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

              <div className="flex items-center gap-2">
                <button
                  onClick={() =>
                    dispatch(updateQuantity({ product_id: item.product_id, quantity: Math.max(1, item.quantity - 1) }))
                  }
                  className="btn-secondary btn-sm w-7 h-7 p-0 flex items-center justify-center"
                >
                  −
                </button>
                <span className="text-sm font-medium text-gray-900 dark:text-ink-900 w-6 text-center tabular-nums">
                  {item.quantity}
                </span>
                <button
                  onClick={() =>
                    dispatch(updateQuantity({ product_id: item.product_id, quantity: item.quantity + 1 }))
                  }
                  className="btn-secondary btn-sm w-7 h-7 p-0 flex items-center justify-center"
                >
                  +
                </button>
              </div>

              <span className="text-h3 text-green-400 w-24 text-right tabular-nums">
                {formatPrice(item.price * item.quantity)}
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
          ))}
        </div>

        {/* Summary */}
        <div className="lg:col-span-1">
          <div className="card-pad sticky top-24 space-y-5">
            <h2 className="text-h3 text-gray-900 dark:text-ink-900">{t('cart.checkout')}</h2>

            <div className="space-y-2">
              {PAYMENT_METHODS.map((m) => (
                <label
                  key={m.value}
                  className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all duration-150 ${
                    paymentMethod === m.value
                      ? 'border-green-500 bg-green-500/5'
                      : 'border-ink-200 bg-gray-100 dark:bg-ink-100 hover:border-ink-300'
                  }`}
                >
                  <input
                    type="radio"
                    name="payment_method"
                    value={m.value}
                    checked={paymentMethod === m.value}
                    onChange={(e) => handleMethodChange(e.target.value)}
                    className="green-green-500"
                  />
                  <span className="text-sm text-gray-800 dark:text-ink-800">{m.label}</span>
                </label>
              ))}
            </div>

            {/* Binance Pay fields */}
            {paymentMethod === 'binance_pay' && (
              <div className="space-y-2 border-t border-ink-200 pt-4">
                <label className="label">Your Binance ID or Account Email</label>
                <input
                  type="text"
                  className="input"
                  placeholder="Binance ID or email@example.com"
                  value={binanceId}
                  onChange={(e) => setBinanceId(e.target.value)}
                />
                <p className="text-micro text-gray-500 dark:text-ink-500">
                  Enter the Binance Pay ID or email associated with your Binance account.
                </p>
              </div>
            )}

            {/* USDT fields */}
            {paymentMethod === 'usdt' && (
              <div className="space-y-3 border-t border-ink-200 pt-4">
                <div>
                  <label className="label">Your USDT Wallet Address (BEP-20)</label>
                  <input
                    type="text"
                    className="input"
                    placeholder="0x..."
                    value={usdtAddress}
                    onChange={(e) => setUsdtAddress(e.target.value)}
                  />
                </div>
                <div>
                  <label className="label">Transaction Hash (TX Hash)</label>
                  <input
                    type="text"
                    className="input"
                    placeholder="0x..."
                    value={usdtTxHash}
                    onChange={(e) => setUsdtTxHash(e.target.value)}
                  />
                </div>
                <div>
                  <label className="label">Network</label>
                  <select
                    className="input"
                    value={usdtNetwork}
                    onChange={(e) => setUsdtNetwork(e.target.value)}
                  >
                    <option value="BEP-20">BEP-20 (BNB Smart Chain)</option>
                    <option value="TRC-20">TRC-20 (Tron)</option>
                    <option value="ERC-20">ERC-20 (Ethereum)</option>
                  </select>
                </div>
                <p className="text-micro text-gray-500 dark:text-ink-500">
                  Enter the wallet address you sent USDT from and the transaction hash from your blockchain explorer.
                </p>
              </div>
            )}

            <div className="border-t border-ink-200 pt-4 space-y-1">
              <div className="flex justify-between text-small text-gray-600 dark:text-ink-500">
                <span>{t('cart.subtotal')}</span>
                <span>{formatPrice(total)}</span>
              </div>
              <div className="flex justify-between text-body text-gray-600 dark:text-ink-600">
                <span>{t('cart.total')}</span>
                <span className="text-h3 text-green-400">{formatPrice(total)}</span>
              </div>
            </div>

            {confirming ? (
              <div className="space-y-3">
                <div className="card-pad bg-gray-100 dark:bg-ink-100/40 p-4 space-y-1 text-small">
                  <p className="text-gray-700 dark:text-ink-700">
                    <strong className="text-gray-900 dark:text-ink-900">{items.length}</strong>{' '}
                    {items.length === 1 ? t('cart.item') : t('cart.items')}{' '}
                    {t('cart.for')}{' '}
                    <strong className="text-green-400">{formatPrice(total)}</strong>
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