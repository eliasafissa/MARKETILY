# Marketly — Deployment Guide

## Railway Service Layout

| Service | Type | Description |
|---------|------|-------------|
| `marketly-backend` | PHP (Docker) | Laravel API + Reverb worker + Scheduler |
| `marketly-frontend` | Node (Docker) | React SPA served via Nginx |
| `marketly-mysql` | MySQL 8.4 | Managed database with automatic backups |
| `marketly-reverb` | PHP (Docker) | Separate Reverb WebSocket service (optional, can co-host) |

**Production URLs:**
- Frontend: https://marketly-frontend-production.up.railway.app
- Backend API: https://marketly-backend-production.up.railway.app/api

## Required Environment Variables

### Backend Service (`marketly-backend`)
```bash
# Application
APP_KEY=base64:...
APP_URL=https://marketly-backend-production.up.railway.app
APP_DEBUG=false
LOG_CHANNEL=stderr

# Database (auto-provided by Railway MySQL service)
DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

# Oranos Integration
ORANOS_API_URL=https://api.oranosmarket.com
ORANOS_API_TOKEN=your_oranos_token
# ORANOS_MARKUP=1.25  # Optional: now persisted in settings table via Admin Settings; env var used as fallback

# Binance Pay
BINANCE_PAY_KEY=...
BINANCE_PAY_SECRET=...

# USDT (BEP-20)
USDT_WALLET_ADDRESS=...
USDT_WEBHOOK_SECRET=...

# Cloudinary
CLOUDINARY_URL=cloudinary://...

# Mail
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=...
MAIL_FROM_NAME=Marketly

# Reverb (WebSockets)
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=0.0.0.0
REVERB_PORT=8080

# Company Info (configured via Admin Settings → System)
# SUPPORT_EMAIL=, PHONE=, ADDRESS=

# Frontend URL (for CORS, webhooks, etc.)
FRONTEND_URL=https://marketly-frontend-production.up.railway.app
```

### Frontend Service (`marketly-frontend`)
```bash
VITE_API_URL=https://marketly-backend-production.up.railway.app/api
VITE_REVERB_APP_KEY=...
VITE_REVERB_HOST=...
VITE_REVERB_PORT=8080
VITE_GA_MEASUREMENT_ID=... (optional)
VITE_UMAMI_WEBSITE_ID=... (optional)
```

## Container Start Command

The backend service uses this start command (defined in `railway.toml` or Railway service settings):

```bash
php artisan migrate --force; php artisan schedule:work & php artisan serve --host=0.0.0.0 --port=$PORT
```

**Breakdown:**
1. `php artisan migrate --force` — Runs pending migrations on every deploy (safe, idempotent)
2. `php artisan schedule:work &` — Starts Laravel scheduler in background for cron jobs (`orders:poll-oranos` every 5 min, etc.)
3. `php artisan serve --host=0.0.0.0 --port=$PORT` — Starts the HTTP server on Railway's assigned port

> **Note:** The `&` runs scheduler in background. Railway will manage the process lifecycle. If you need a separate worker service for queues, create a separate Railway service with command `php artisan queue:work`.

## How to Manually Run the Oranos Poll

```bash
# SSH into the backend service
railway ssh --service marketly-backend

# Run the poll command
php artisan orders:poll-oranos
```

## How to Check an Order's Oranos Status via Tinker

```bash
# SSH into the backend service
railway ssh --service marketly-backend

# Start tinker
php artisan tinker

# In tinker:
$order = App\Models\Order::with('items')->find(123);
$order->items->first()->oranos_order_id;  // The Oranos order ID
$order->items->first()->oranos_status;    // Last polled status from Oranos
```

## IMPORTANT: Oranos Supplier Balance (بilingual EN + AR)

> **ENGLISH:** The Oranos supplier account balance is **SEPARATE** from user wallets. If Oranos balance is below $20, **all automation orders will be rejected with HTTP 417 "Insufficient Balance"**. Top up at oranosmarket.com.
>
> **العربية:** رصيد حساب المورد أورانوس **منفصل** عن محافظ المستخدمين. إذا انخفض رصيد أورانوس عن 20 دولارًا، **سيتم رفض جميع الطلبات الآلية بخطأ HTTP 417 "رصيدك غير كاف"**. يرجى شحن الرصيد على oranosmarket.com.

This is a critical operational dependency — monitor `/admin/oranos` dashboard regularly.

## Deployment Checklist

- [ ] All environment variables set in Railway project settings
- [ ] MySQL service provisioned and connected (Railway provides `DATABASE_URL` automatically)
- [ ] Persistent volume mounted at `/app/storage/app/public` for uploaded images
- [ ] `APP_KEY` generated (`php artisan key:generate --show`)
- [ ] CORS configured: `FRONTEND_URL` matches production frontend domain
- [ ] Reverb service running (separate service or co-hosted)
- [ ] Scheduler running (`schedule:work` in start command)
- [ ] Queue worker running (recommended separate service: `php artisan queue:work`)
- [ ] SSL/TLS automatic via Railway (custom domains require DNS verification)
- [ ] Oranos balance > $20 (check `/admin/oranos` or `php artisan tinker --execute='...'`)
- [ ] Health check endpoint responding: `GET /api/admin/health`

## Rolling Back

Railway supports instant rollback:
1. Go to Railway dashboard → Backend service → Deployments
2. Click "Rollback" on the previous successful deployment
3. Database migrations are **not** rolled back automatically — handle manually if needed

## Monitoring

- **Logs**: `railway logs --service marketly-backend`
- **Metrics**: Railway dashboard → Metrics tab (CPU, Memory, Network)
- **Health**: `GET /api/admin/health` returns `{ database, storage, reverb }` status
- **Oranos Balance**: `GET /api/admin/oranos/balance` (admin only)
- **Categories**: 561 total (18 root) — verify via `GET /api/categories`
- **Products**: 2,400+ synced — verify via `GET /api/products?per_page=1`

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Migrations fail on deploy | Check `railway logs` for SQL errors; run manually via `railway ssh` |
| Reverb connection fails | Verify `REVERB_*` vars on both backend and frontend; check firewall |
| Oranos orders stuck "Processing" | Run `php artisan orders:poll-oranos` manually; check Oranos balance |
| Home page shows no category images | Run category image propagation tinker script (see PROJECT_SPEC.md) |
| Admin modals show English text | Ensure `ar.ts` and `en.ts` have all `admin.*` keys; clear frontend cache |
| Frontend shows 404 on refresh | Ensure Nginx `try_files` config routes to `index.html` for SPA |
| CORS errors | Verify `FRONTEND_URL` in backend `.env` matches production domain exactly |