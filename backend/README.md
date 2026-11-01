# Marketly Backend

Laravel API for Marketly digital marketplace with Oranos integration.

## Oranos Sync Pipeline

Marketly syncs products and categories from Oranos Market API on a scheduled basis.

### Sync Flow

1. **Categories Sync** (`oranos:sync-categories`) - Daily at 03:00
   - Fetches categories from `GET /client/api/categories`
   - Creates/updates local categories with images
   - Links parent categories via `oranos_category_id`

2. **Products Sync** (`oranos:sync-products`) - Daily at 03:10
   - Fetches products from `GET /client/api/products`
   - For each product:
     - Reads `price` as `base_price` (our cost)
     - Applies markup: `price = base_price * (1 + markup_percent/100)`
     - Default markup: 20% (configurable via `oranos_markup_percent` setting)
     - Reads `available`/`is_available`/`status` for `oranos_available`
     - Downloads images (skips placeholder `empty.png`)
     - Links to categories, creates missing categories
   - Refreshes store prices with 10% markup (configurable via `store_markup_percent`)

3. **Price Verification** (`oranos:verify-price-sync`) - Weekly
   - Samples 10 random Oranos-linked products
   - Compares Oranos `price` vs local `base_price`
   - Reports MATCH/MISMATCH with diff

4. **Markup Recalculation** (`oranos:apply-markup`) - Weekly
   - Recomputes all Oranos product prices from `base_price` using current markup
   - Updates store custom prices

5. **Harvest** (`oranos:harvest --update`) - Weekly Sunday 03:30
   - Processes pending automation orders via Oranos
   - Updates order statuses based on Oranos responses

### Manual Commands

```bash
# Sync categories from Oranos
php artisan oranos:sync-categories

# Sync products from Oranos (with markup)
php artisan oranos:sync-products

# Recalculate all prices from base_price using current markup
php artisan oranos:apply-markup

# Verify prices match Oranos (spot check 10 products)
php artisan oranos:verify-price-sync

# Process automation orders
php artisan oranos:harvest --update
```

### Key Settings (Admin → Settings)

| Key | Default | Description |
|-----|---------|-------------|
| `oranos_markup_percent` | 20 | Markup % on Oranos base price |
| `store_markup_percent` | 10 | Store markup on product price |
| `usdt_wallet_address` | - | USDT BEP-20 deposit wallet |
| `binance_pay_key` | - | Binance Pay API key |
| `binance_pay_secret` | - | Binance Pay secret |
| `services.oranos.url` | `https://api.oranosmarket.com` | Oranos API base URL |
| `services.oranos.token` | - | Oranos API token |

### Environment Variables

```env
ORANOS_API_URL=https://api.oranosmarket.com
ORANOS_API_TOKEN=your_token
ORANOS_MARKUP=1.20
BINANCE_PAY_KEY=
BINANCE_PAY_SECRET=
USDT_WALLET_ADDRESS=
USDT_WEBHOOK_SECRET=placeholder
```

### Deployment (Railway)

1. Set environment variables in Railway dashboard
2. Custom Start Command:
   ```
   sh -c "php artisan storage:link || true; php artisan migrate --force; php artisan schedule:work > /dev/null 2>&1 & php artisan serve --host=0.0.0.0 --port=$PORT"
   ```
3. Ensure `schedule:work` runs for cron jobs
4. Configure webhook URLs for Binance/USDT in Oranos dashboard

### Database

Run migrations:
```bash
php artisan migrate --force
```

Key tables: `products`, `categories`, `orders`, `transactions`, `settings`, `stores`, `users`.

### Webhooks

- **Binance Pay**: `POST /webhooks/binance` (verify `X-Binance-Signature` HMAC-SHA512)
- **USDT**: `POST /webhooks/usdt` (verify `X-Usdt-Signature` HMAC-SHA256)
- **Oranos Orders**: `POST /webhooks/payments/{gateway}` (generic)

Both verify HMAC signatures and auto-approve deposits on success.

### Admin Features

- **Products**: List, create, edit, toggle active, stats (Oranos vs Manual count)
- **Categories**: Search, emoji picker, form fields
- **Orders**: Pending manual, status updates
- **Deposits/Withdrawals**: Approve/reject
- **Settings**: VIP, Payment, Oranos markup, Company info, Legal pages
