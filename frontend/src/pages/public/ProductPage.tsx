import { useEffect, useState, useMemo } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { productApi } from '@/api/client';
import { useAppDispatch, addToCart } from '@/store';
import Button from '@/components/Button';
import ProductImage from '@/components/ProductImage';
import { formatPrice } from '@/utils/format';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';
import { localized } from '@/utils/localize';
import Breadcrumbs from '@/components/Breadcrumbs';

function isFormatA(qtyValues: any): boolean {
  return qtyValues && typeof qtyValues === 'object' && !Array.isArray(qtyValues) && 'min' in qtyValues && 'max' in qtyValues;
}

function isFormatB(qtyValues: any): boolean {
  return Array.isArray(qtyValues) && qtyValues.length > 0;
}

function parseQtyValue(v: string | number): number {
  const n: number = typeof v === 'number' ? v : parseFloat(String(v));
  return isNaN(n) ? 0 : n;
}

function formatTierValue(val: number): string {
  return val % 1 === 0 ? val.toLocaleString() : val.toFixed(2);
}

export default function ProductPage() {
  const { slug } = useParams();
  const navigate = useNavigate();
  const dispatch = useAppDispatch();
  const { t, locale } = useI18n();
  const [product, setProduct] = useState<any>(null);
  const [selectedQty, setSelectedQty] = useState<number | null>(null);
  const [payload, setPayload] = useState<Record<string, string>>({});
  const [paramValues, setParamValues] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const qtyValues = useMemo(() => product?.qty_values ?? null, [product?.qty_values]);
  const isFormatAQty = useMemo(() => isFormatA(qtyValues), [qtyValues]);
  const isFormatBQty = useMemo(() => isFormatB(qtyValues), [qtyValues]);
  const minQty = useMemo(() => isFormatAQty ? parseQtyValue(qtyValues.min) : null, [isFormatAQty, qtyValues]);
  const maxQty = useMemo(() => isFormatAQty ? parseQtyValue(qtyValues.max) : null, [isFormatAQty, qtyValues]);
  const tierValues = useMemo(() => isFormatBQty ? qtyValues.map(parseQtyValue).filter((n: number) => n > 0) : [], [isFormatBQty, qtyValues]);
  const defaultQty = useMemo(() => {
    if (isFormatAQty && minQty) return minQty;
    if (isFormatBQty && tierValues.length > 0) return tierValues[0];
    return 1;
  }, [isFormatAQty, isFormatBQty, minQty, tierValues]);

  // Initialize selectedQty on product load
  useEffect(() => {
    if (product && selectedQty === null) {
      setSelectedQty(defaultQty);
    }
  }, [product, defaultQty, selectedQty]);

  useEffect(() => {
    if (!slug) return;
    productApi.show(slug).then((res) => {
      const p = res.data.product;
      console.log('QTY DEBUG', {
        qty_values: p?.qty_values,
        typeofQty: typeof p?.qty_values,
        isArray: Array.isArray(p?.qty_values),
        keys: p?.qty_values && typeof p?.qty_values === 'object' ? Object.keys(p.qty_values).slice(0,5) : null,
      });
      setProduct(p);
      const params = Array.isArray(p?.params) ? p.params : [];
      setParamValues(new Array(params.length).fill(''));
      setLoading(false);
    }).catch(() => setLoading(false));
  }, [slug]);

  if (loading) {
    return (
      <div className="flex items-center justify-center py-24">
        <span className="w-8 h-8 rounded-full border-2 border-green-500 border-t-transparent animate-spin" />
      </div>
    );
  }

  if (!product) {
    return (
      <PageTransition className="text-center py-24">
        <p className="text-h3 text-gray-600 dark:text-ink-600 mb-4">{t('product.productNotFound')}</p>
        <Link to="/" className="btn-accent">{t('product.backToHome')}</Link>
      </PageTransition>
    );
  }

  const isManual = product.type === 'manual';
  const isCodeCraft = [1146, 1147, 1148, 1149, 1150].includes(product.category_id);
  const isAutomation = product.is_automation === true;
  const automationParams: string[] = Array.isArray(product.params) ? product.params : [];

  const getDisplayLabel = (label: string) =>
    ['الايدي', 'id', 'الايدي'].includes(label?.toLowerCase()) ? t('product.idLabel') : label;

  const manualFields: string[] = (Array.isArray(product.params) && product.params.length > 0)
    ? (product.params as string[])
    : [
        t('product.linkUsername'),
        t('product.quantity'),
        t('product.notesOptional'),
      ];

  const handleAddToCart = () => {
    const newErrors: Record<string, string> = {};

    if (isAutomation) {
      automationParams.forEach((label, i) => {
        if (!(paramValues[i] ?? '').trim()) {
          newErrors[`param-${i}`] = t('product.errorEnter', { label: getDisplayLabel(label) });
        }
      });
      if (!selectedQty || selectedQty < 1) {
        newErrors['quantity'] = t('product.errorQuantity');
      }
      if (isFormatAQty && (selectedQty! < (minQty ?? 1) || selectedQty! > (maxQty ?? Infinity))) {
        newErrors['quantity'] = t('product.errorQtyRange', { min: minQty ?? 1, max: maxQty ?? 999999 });
      }
      if (isFormatBQty && tierValues.length > 0 && !tierValues.includes(selectedQty!)) {
        newErrors['quantity'] = t('product.errorInvalidTier');
      }
      setErrors(newErrors);
      if (Object.keys(newErrors).length > 0) return;

      dispatch(
        addToCart({
          product_id: product.id,
          name: product.name,
          price: Number(product.price),
          quantity: selectedQty!,
          payload: paramValues as any,
          product_type: product.is_automation ? 'auto' : 'manual',
        }),
      );
      navigate('/cart');
      return;
    }

    if (isManual) {
      manualFields.forEach((label: string) => {
        const val = (payload[label] ?? '').trim();
        if (!val) {
          newErrors[label] = t('common.required') ?? 'هذا الحقل مطلوب';
        }
      });
      setErrors(newErrors);
      if (Object.keys(newErrors).length > 0) return;
    } else {
      if (!selectedQty || selectedQty < 1) {
        newErrors['quantity'] = t('product.errorQuantity');
      }
      if (isFormatAQty && (selectedQty! < (minQty ?? 1) || selectedQty! > (maxQty ?? Infinity))) {
        newErrors['quantity'] = t('product.errorQtyRange', { min: minQty ?? 1, max: maxQty ?? 999999 });
      }
      if (isFormatBQty && tierValues.length > 0 && !tierValues.includes(selectedQty!)) {
        newErrors['quantity'] = t('product.errorInvalidTier');
      }
      setErrors(newErrors);
      if (Object.keys(newErrors).length > 0) return;
    }

    dispatch(
      addToCart({
        product_id: product.id,
        name: product.name,
        price: Number(product.price),
        quantity: isManual
          ? Math.max(1, parseInt((payload[t('product.quantity')] ?? '1').trim(), 10) || 1)
          : selectedQty!,
        payload: isManual ? payload : undefined,
        product_type: isManual ? 'manual' : (product.is_automation ? 'auto' : 'manual'),
      }),
    );
    navigate('/cart');
  };

  const totalPrice = selectedQty ? Number(product.price) * selectedQty : Number(product.price);

  return (
    <PageTransition className="max-w-5xl mx-auto">
      {/* Back Button */}
      <button
        onClick={() => navigate(-1)}
        className="mb-4 text-small text-gray-600 dark:text-ink-500 hover:text-green-400 dark:hover:text-green-300 transition-colors flex items-center gap-1.5"
        aria-label={t('product.back')}
      >
        <svg className="w-4 h-4 rtl:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <path d="M19 12H5M12 19l-7-7 7-7" />
        </svg>
        <span>{t('product.back')}</span>
      </button>

      {/* Breadcrumb */}
      <Breadcrumbs
        items={[
          { label: t('nav.home'), link: '/' },
          { label: t('nav.categories'), link: '/categories' },
          { label: localized(product.category, 'name', 'name_ar', locale), link: `/category/${product.category?.slug}` },
          { label: localized(product, 'name', 'name_ar', locale) },
        ]}
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-10">
        <motion.div
          initial={{ opacity: 0, x: -16 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
        >
          <div className="aspect-video overflow-hidden rounded-2xl">
<ProductImage
             name={localized(product, 'name', 'name_ar', locale)}
             icon={product.icon}
             category={localized(product.category, 'name', 'name_ar', locale)}
             imageBase64={product.image_base64}
             imageUrl={product.image_url}
             className="w-full h-full"
             productId={product.id}
             showFavorite
           />
          </div>
        </motion.div>

        <motion.div
          className="space-y-6"
          initial={{ opacity: 0, x: 16 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ delay: 0.1, duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
        >
          <div>
            <div className="flex flex-wrap gap-2 mb-4">
              {product.external_store_id && (
                <span className="badge-neutral">{t('product.externalStore')}</span>
              )}
              <span className={`badge ${isManual ? 'badge-pending' : 'badge-completed'}`}>
                {isManual ? t('product.manualService') : t('product.autoDelivery')}
              </span>
            </div>
            <h1 className="text-h1 text-gray-900 dark:text-ink-900 mb-2">{localized(product, 'name', 'name_ar', locale)}</h1>
            <div className="text-small text-gray-600 dark:text-ink-500">
              <span>{t('product.category')}: <Link to={`/category/${product.category?.slug}`} className="text-green-400 hover:underline">{localized(product.category, 'name', 'name_ar', locale)}</Link></span>
            </div>
          </div>

          <p className="text-body text-gray-600 dark:text-ink-600 whitespace-pre-line leading-relaxed">
            {localized(product, 'description', 'description_ar', locale)}
          </p>

          <div className="card-pad space-y-5">
            <div className="flex items-baseline gap-3">
              <span className="text-display-1 text-green-400 font-bold">
                {formatPrice(product.price)}
              </span>
              <span className="text-body text-gray-600 dark:text-ink-500">{t('product.perUnit')}</span>
            </div>

            {isAutomation && (
              <div className="space-y-3">
                <p className="text-micro text-gray-600 dark:text-ink-500 uppercase tracking-wide">
                  {t('product.serviceDetails')}
                </p>
                {automationParams.map((label, i) => {
                  const displayLabel = ['الايدي', 'id', 'الايدي'].includes(label?.toLowerCase()) ? t('product.idLabel') : label;
                  return (
                    <div key={i}>
                      <label className="label">{displayLabel}</label>
                      <input
                        className={`input ${errors[`param-${i}`] ? 'border-status-rejected' : ''}`}
                        placeholder={displayLabel}
                        value={paramValues[i] ?? ''}
                        onChange={(e) => {
                          const next = [...paramValues];
                          next[i] = e.target.value;
                          setParamValues(next);
                          setErrors((prev) => ({ ...prev, [`param-${i}`]: '' }));
                        }}
                      />
                      {errors[`param-${i}`] && (
                        <p className="text-micro text-status-rejected mt-1">{errors[`param-${i}`]}</p>
                      )}
                    </div>
                  );
                })}
              </div>
            )}

            {isManual && !isCodeCraft && (
              <div className="space-y-3">
                <p className="text-micro text-gray-600 dark:text-ink-500 uppercase tracking-wide">
                  {t('product.serviceDetails')}
                </p>
                {manualFields.map((label) => {
                  const displayLabel = ['الايدي', 'id', 'الايدي'].includes(label?.toLowerCase()) ? t('product.idLabel') : label;
                  return (
                    <div key={label}>
                      <label className="label">{displayLabel}</label>
                      <input
                        className={`input ${errors[label] ? 'border-status-rejected' : ''}`}
                        placeholder={displayLabel}
                        value={payload[label] ?? ''}
                        onChange={(e) => {
                          setPayload((p) => ({ ...p, [label]: e.target.value }));
                          setErrors((prev) => ({ ...prev, [label]: '' }));
                        }}
                      />
                      {errors[label] && (
                        <p className="text-micro text-status-rejected mt-1">{errors[label]}</p>
                      )}
                    </div>
                  );
                })}
              </div>
            )}

            {(isFormatAQty || isFormatBQty) && !isManual && (
              <div className="space-y-3">
                <p className="text-micro text-gray-600 dark:text-ink-500 uppercase tracking-wide">
                  {t('product.quantity')}
                </p>
                {isFormatAQty && (
                  <div className="space-y-2">
                    <div className="flex items-center gap-4">
                      <label htmlFor="qty-input" className="label flex-1 min-w-0">
                        <input
                          id="qty-input"
                          type="number"
                          step="any"
                          className={`input ${errors.quantity ? 'border-status-rejected' : ''}`}
                          min={minQty ?? 1}
                          max={maxQty ?? 1000000}
                          value={selectedQty ?? ''}
                          onChange={(e) => {
                            const val = e.target.value === '' ? null : parseFloat(e.target.value);
                            setSelectedQty(val);
                            setErrors((prev) => ({ ...prev, quantity: '' }));
                          }}
                          onBlur={(e) => {
                            const val = e.target.value === '' ? null : parseFloat(e.target.value);
                            if (val !== null && (val < (minQty ?? 1) || val > (maxQty ?? Infinity))) {
                              setErrors((prev) => ({ ...prev, quantity: t('product.errorQtyRange', { min: formatTierValue(minQty ?? 1), max: formatTierValue(maxQty ?? 999999) }) }));
                            }
                          }}
                        />
                      </label>
                    </div>
                    <p className="text-micro text-gray-500 dark:text-ink-400">
                      {t('product.qtyRangeHint', { min: formatTierValue(minQty ?? 1), max: formatTierValue(maxQty ?? 999999) })}
                    </p>
                    {errors.quantity && (
                      <p className="text-micro text-status-rejected">{errors.quantity}</p>
                    )}
                  </div>
                )}
                {isFormatBQty && (
                  <div className="space-y-2">
                    {tierValues.length <= 8 ? (
                      <div className="flex flex-wrap gap-2" role="radiogroup" aria-label={t('product.quantity')}>
                        {tierValues.map((val: number) => (
                          <button
                            key={val}
                            type="button"
                            role="radio"
                            aria-checked={selectedQty === val}
                            className={`flex items-center justify-center min-w-[80px] px-4 py-2.5 rounded-lg border-2 text-body font-medium transition-colors ${
                              selectedQty === val
                                ? 'border-green-500 bg-green-50 dark:bg-green-500/20 text-green-900 dark:text-green-100'
                                : 'border-gray-300 dark:border-ink-500 text-gray-900 dark:text-white hover:border-green-400 dark:hover:border-green-500'
                            }`}
                            onClick={() => {
                              setSelectedQty(val);
                              setErrors((prev) => ({ ...prev, quantity: '' }));
                            }}
                          >
                            {formatTierValue(val)}
                          </button>
                        ))}
                      </div>
                    ) : (
                      <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-72 overflow-y-auto pr-2" role="radiogroup" aria-label={t('product.quantity')}>
                        {tierValues.map((val: number) => (
                          <button
                            key={val}
                            type="button"
                            role="radio"
                            aria-checked={selectedQty === val}
                            className={`flex items-center justify-center px-4 py-2.5 rounded-lg border-2 text-body font-medium transition-colors ${
                              selectedQty === val
                                ? 'border-green-500 bg-green-50 dark:bg-green-500/20 text-green-900 dark:text-green-100'
                                : 'border-gray-300 dark:border-ink-500 text-gray-900 dark:text-white hover:border-green-400 dark:hover:border-green-500'
                            }`}
                            onClick={() => {
                              setSelectedQty(val);
                              setErrors((prev) => ({ ...prev, quantity: '' }));
                            }}
                          >
                            {formatTierValue(val)}
                          </button>
                        ))}
                      </div>
                    )}
                  </div>
                )}
              </div>
            )}


            {isCodeCraft ? (
              <div className="border-t border-gray-200 dark:border-ink-200 pt-5 space-y-3">
                <p className="text-h3 text-gray-900 dark:text-ink-900">عندك فكرة مشروع؟</p>
                <p className="text-small text-gray-600 dark:text-ink-500">تواصل معنا مباشرة لمناقشة التفاصيل والتسعير:</p>
                <div className="space-y-2 text-body pt-1">
                  <div dir="ltr" className="text-start">
                    <a href="https://linkedin.com/in/elias-afisa-54299a233" target="_blank" rel="noopener noreferrer" className="text-green-500 hover:underline break-all">
                      linkedin.com/in/elias-afisa-54299a233
                    </a>
                  </div>
                  <div dir="ltr" className="text-start">
                    <a href="https://linkedin.com/in/serene-sheikhalard" target="_blank" rel="noopener noreferrer" className="text-green-500 hover:underline break-all">
                      linkedin.com/in/serene-sheikhalard
                    </a>
                  </div>
                  <div className="flex items-center gap-2 pt-1">
                    <span className="text-gray-500 dark:text-ink-500">هاتف:</span>
                    <a href="tel:+963933135837" dir="ltr" className="text-green-500 hover:underline tabular-nums">+963 933 135 837</a>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="text-gray-500 dark:text-ink-500">هاتف:</span>
                    <a href="tel:+963997112295" dir="ltr" className="text-green-500 hover:underline tabular-nums">+963 997 112 295</a>
                  </div>
                </div>
              </div>
            ) : (
              <div className="flex items-center justify-between">
                <span className="text-body text-gray-600 dark:text-ink-600">
                  {t('product.total')}:{' '}
                  <strong className="text-gray-900 dark:text-ink-900">
                    {formatPrice(totalPrice)}
                  </strong>
                </span>
                {product.stock === 0 || product.oranos_available === false ? (
                  <span className="badge-rejected text-base px-4 py-2">{t('common.unavailable')}</span>
                ) : (
                  <Button
                    variant="accent"
                    size="lg"
                    onClick={handleAddToCart}
                  >
                    {t('product.addToCart')}
                  </Button>
                )}
              </div>
            )}
          </div>
        </motion.div>
      </div>
    </PageTransition>
  );
}