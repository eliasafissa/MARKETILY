# Marketly — Project Specification

*Version 1.0 — Generated from codebase inventory*

---

## D1. What Marketly Is

Marketly is a digital marketplace platform where users can browse, purchase, and instantly receive digital products such as game keys, software subscriptions, gift cards, mobile top-ups, and social media services. The platform combines automated delivery (via integration with the Oranos Market API) with manual fulfillment for custom services.

Marketly also enables entrepreneurs to launch their own branded storefronts that pull products from the main catalog with customizable markups, and provides a Partner API for external systems to programmatically purchase products. Built on Laravel (PHP) and React, it runs on Railway with MySQL, using a wallet-based payment system supported by Binance Pay, USDT (BEP-20), and admin-managed cash wallet deposits.

---

## D2. User Roles

### Guest (Unauthenticated)
- Browse products and categories
- View product details
- Register for an account
- Login
- Submit a "Create Website" inquiry form
- View legal pages (Terms, Privacy, Refund)
- Request Partner API access (redirects to login)

### Customer (Authenticated User)
- All guest capabilities
- View dashboard with balance, VIP status, spending trend
- Add products to favorites
- Add to cart and checkout
- Deposit funds via Binance Pay, USDT, or Cash Wallet (admin-approved)
- Withdraw funds (VIP only, with limits and fees)
- View order history and transaction history
- Create and manage personal storefronts
- Request Partner API access → pending → approved → receive API key
- View API documentation with curl/fetch examples
- Upgrade VIP level using wallet balance

### Admin / Moderator
- All customer capabilities
- Access admin panel at `/admin`
- **Dashboard**: system stats, health checks, recent orders
- **Users**: list, view, edit, ban/unban, delete, assign roles
- **Products**: list, create, edit, delete, toggle active, sync from Oranos, apply markup
- **Categories**: list, create, edit, delete, manage manual order fields
- **Orders**: list, filter, view details, update status (pending/processing/completed/rejected), pending manual orders queue
- **Deposits**: list, approve/reject (credits user balance)
- **Withdrawals**: list, approve/reject (refunds balance on reject)
- **Partner Requests**: list, approve (generates API key), reject (with reason)
- **Oranos Monitor**: view Oranos account balance, health level, refresh
- **Settings**: manage all settings (VIP prices, fees, payment credentials, company info, legal pages) individually or in bulk

### Partner (External API Consumer)
- Authenticate via `api-token` header
- **GET /partner/me** — account info and balance
- **GET /partner/categories** — list categories with Oranos products
- **GET /partner/products** — paginated, filterable product catalog
- **GET /partner/products/{slug}** — single product details
- **POST /partner/orders** — create order (deducts from wallet, requires sufficient balance)
- **GET /partner/orders/{id}** — check order status
- Requires approved Partner API Request and generated API key

---

## D3. Feature List

### Storefront
- **Browse products** — paginated, searchable, filterable by category (`/products`)
- **Categories** — hierarchical with Oranos-sourced images (`/categories`, `/category/:slug`)
- **Product details** — image, description, price, automation vs manual, required parameters (`/product/:slug`)
- **Favorites** — heart icon on products, persistent list (`/dashboard/favorites`)
- **Shopping cart** — slide-out drawer, quantity adjustment, payload for manual/automation products
- **Responsive design** — 375px / 768px / 1280px breakpoints, RTL (Arabic) and LTR (English) support

### Checkout & Payments
- **Wallet balance** — central stored value, debited on purchase, credited on deposit
- **Deposit methods**:
  - Binance Pay (QR code, webhook confirmation)
  - USDT BEP-20 (wallet address + memo, webhook confirmation)
  - Cash Wallet (admin manual approval)
- **Withdrawal** — VIP only, configurable limits/fees per level, USDT/Binance, admin approval
- **VIP upgrades** — pay from wallet balance, instant level change, transaction recorded
- **Webhook handling** — signature verification for Binance (HMAC-SHA512) and USDT (HMAC-SHA256)

### Order Fulfillment
- **Automated (Oranos)** — products with `is_automation=true` and `oranos_product_id`:
  - Order created → balance deducted → Oranos API called with player ID + params
  - Oranos order ID stored → status polled → auto-completed or left as Processing for manual admin fulfillment
  - Stock decremented on our side
- **Manual** — products with `type=manual`:
  - Order created as Pending → admin reviews → marks Processing/Completed/Rejected
  - Payload collected at checkout (dynamic form schema from category)
  - No balance debit until admin completes
- **Store orders** — when purchased through a user's storefront, store owner earns markup difference

### VIP System
- **Levels**: None → VIP1 → VIP2 → VIP3 (sequential)
- **Withdrawal limits**: configurable per level (default VIP1: $1,000, VIP2: $2,000, VIP3: $5,000)
- **Withdrawal fees**: configurable per level (default VIP1: 3%, VIP2: 1.5%, VIP3: 0.5%, Regular: 5%)
- **Upgrade prices**: configurable (default VIP1: $100, VIP2: $300, VIP3: $800)
- All settings manageable in Admin Settings, persisted in `settings` table

### Category System (Oranos Sync)
- **561 total categories** in database (18 root categories from Oranos + children)
- **2,400+ products** synced from Oranos
- **Home page** displays exactly 18 Oranos root categories in fixed sort order
- **Category images**: Oranos API returns only id+name; images copied from child categories or product images to parent roots where missing

### Partner API (External Stores)
- **Onboarding**: user submits request (`/connect-store`) → admin approves → API key generated (64-char random)
- **Authentication**: `api-token` header (or `X-Api-Token`), validated against user's `api_key`
- **Authorization**: user must have approved PartnerApiRequest
- **Endpoints**: me, categories, products (list/show), orders (create/status)
- **Rate limiting**: 60 req/min (default Laravel Sanctum throttle)

### Admin Panel
- **Dashboard** — users, revenue, pending counts, VIP breakdown, recent orders
- **Health check** — database, storage, Reverb connectivity; Oranos balance monitor
- **Bulk operations** — settings bulk update, product/category bulk actions
- **Oranos sync** — 18 root categories + 2,400+ products synced; markup application; price verification; category image propagation
- **Role-based access** — admin/moderator middleware on all `/api/admin/*` routes

---

## D4. Integrations

| Integration | Purpose | Configuration (names only) |
|-------------|---------|---------------------------|
| **Oranos Market API** | Source of automated products, categories, images; fulfillment of automation orders | `ORANOS_API_URL`, `ORANOS_API_TOKEN`, `ORANOS_MARKUP` |
| **Binance Pay** | Deposit payments | `BINANCE_PAY_KEY`, `BINANCE_PAY_SECRET` |
| **USDT (BEP-20)** | Deposit/withdrawal payments | `USDT_WALLET_ADDRESS`, `USDT_WEBHOOK_SECRET` |
| **Cloudinary** | Image upload/storage for products/categories | `CLOUDINARY_URL` |
| **Mail (SMTP/Log)** | Order confirmations, contact form, notifications | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` |
| **Reverb (WebSockets)** | Real-time events (order updates, balance changes) | `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT` |
| **Code Craft** | Developer credit in footer | Static asset `/codecraft.svg` |
| **Railway (Hosting)** | App + MySQL + Volumes + Cron | Railway project, services, environment variables |

---

## D5. Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 13.x (PHP 8.3) |
| Frontend | React 19 + Vite 5 + TypeScript |
| Styling | Tailwind CSS 3.4 |
| State | Redux Toolkit (auth, cart) |
| Routing | React Router 6 |
| Animations | Framer Motion |
| Database | MySQL 8 (Railway managed) |
| Queue | Database driver |
| Cache | Database driver |
| Auth | Laravel Sanctum (SPA + API tokens) |
| Permissions | Spatie Laravel Permission |
| Broadcasting | Laravel Reverb |
| Testing | PHPUnit (backend), Vitest + React Testing Library (frontend) |
| Hosting | Railway (app, MySQL, volumes, cron) |

---

## D6. Deployment

### Hosting
- **Platform**: Railway
- **Services**: Backend (PHP), Frontend (Node/Express SSR), MySQL Database
- **Volumes**: Persistent volume mounted at `/app/storage/app/public` for uploaded images
- **Cron**: Railway runs `php artisan schedule:work` for scheduled tasks

### Migrations
- Run on deploy: `php artisan migrate --force`
- **Status**: Verify current migration state before deploy; run `php artisan migrate:status`

### Environment Variables Required
```
APP_KEY, APP_URL, DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
ORANOS_API_URL, ORANOS_API_TOKEN, ORANOS_MARKUP
BINANCE_PAY_KEY, BINANCE_PAY_SECRET
USDT_WALLET_ADDRESS, USDT_WEBHOOK_SECRET
CLOUDINARY_URL
MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, MAIL_FROM_NAME
REVERB_APP_ID, REVERB_APP_KEY, REVERB_APP_SECRET, REVERB_HOST, REVERB_PORT
VITE_API_URL, VITE_REVERB_APP_KEY, VITE_REVERB_HOST, VITE_REVERB_PORT
VITE_GA_MEASUREMENT_ID (optional), VITE_UMAMI_WEBSITE_ID (optional)
```

---

## D7. Onboarding a Partner

1. **User visits `/connect-store`** → clicks "Request Access"
2. **Fills form**: Store name, Store URL, Phone, Notes
3. **Admin reviews** at `/admin/partner-requests`
4. **Admin clicks Approve** → system generates 64-char API key, sets on user, marks request approved
5. **User sees API key** on `/connect-store` (with copy button, show/hide toggle)
6. **User reads docs** at `/dashboard/api-docs` → sees endpoints, curl/fetch examples

### cURL Example (Create Order)
```bash
curl -X POST "https://your-domain.com/api/partner/orders" \
  -H "Accept: application/json" \
  -H "api-token: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "product_slug": "steam-gift-card-50",
    "quantity": 1,
    "params": {}
  }'
```

### Response (Success)
```json
{
  "ok": true,
  "order_id": 456,
  "status": "processing",
  "total_charged": 50.00,
  "new_balance": 950.00
}
```

### Response (Insufficient Balance)
```json
{
  "ok": false,
  "error": "insufficient_balance",
  "required": 50.00,
  "current": 10.00
}
```

---

## D7.5. Recent Platform Changes (Post-Spec)

### Home Page & Categories
- **18 Oranos root categories** displayed on home page in fixed `sort_order`
- **Category images**: Parent categories without `image_url` inherit from first child category with image, or from first product in that category

### Admin Panel — Arabic-First
- **ProductModal** & **CategoryModal**: All visible strings use `t('admin.<key>')` with Arabic translations; English labels removed
- **Form Field Builder** (manual categories): Labels, placeholders, options — Arabic only

### VIP Tier 3
- Backend enum `VipLevel::VIP3` added; frontend pricing/display; admin settings for limits/fees/upgrade price
- Withdrawal limits/fees and upgrade prices now configurable for three tiers

### Company Info & Legal
- **Company Info fields**: `support_email`, `phone`, `address` added to settings table and footer
- **Legal pages**: `content_en` column is now optional (`sometimes` validation); Arabic content required

### Navbar & Footer
- **Navbar**: "الرئيسية" (Home) link removed; site logo links to `/`
- **Footer**: "Code Craft" developer credit with `/codecraft.svg` link

---

## D8. New Features (Phases 7, 9, 3)
 
### Back Button on Product & Category Pages (Phase 7)
- **Files**: `frontend/src/pages/public/ProductPage.tsx`, `frontend/src/pages/public/CategoryPage.tsx`, `frontend/src/i18n/en.ts`, `frontend/src/i18n/ar.ts`
- Added a "Back" button above the Breadcrumbs on product and category detail pages
- Calls `navigate(-1)` for browser history navigation
- Styled to match Breadcrumbs: `text-small`, muted color (`text-gray-600 dark:text-ink-500`), hover state (`hover:text-green-400 dark:hover:text-green-300`)
- Uses inline SVG arrow glyph with RTL support (`rtl:rotate-180`)
- i18n key: `product.back` (EN: "Back", AR: "رجوع")
 
### Hide Zero-Product Categories (Phase 9)
- **File**: `backend/app/Http/Controllers/Api/Category/CategoryController.php` (`index` method)
- Filters out categories where active product count is 0 at the database query level
- Uses `withCount` + `having('products_count', '>', 0)` on both root categories and their children
- Admin endpoint (`/api/admin/categories`) is separate and unaffected
- No PHP post-filtering — pure SQL-level filtering
 
### Admin Balance Adjustment (Phase 3)
- **Backend**:
  - New endpoint: `POST /api/admin/users/{user}/balance`
  - Body: `{ amount: number (positive or negative), note?: string }`
  - Admin auth required (role: admin|moderator middleware)
  - Wrapped in `DB::transaction`
  - Locks user row with `lockForUpdate()` before reading balance
  - Updates `users.balance` directly
  - Inserts transaction record: `type='admin_adjustment'`, `amount` (signed), `note`, `reference='admin-{adminId}-{timestamp}'`, `status='approved'`, `method='admin'`
  - Returns new balance in JSON
  - Does NOT touch `refundFailedOrder`, wallet-deduct/credit paths, or transactions table schema
  - **Files**: `backend/app/Http/Controllers/Api/Admin/UserController.php`, `backend/app/Enums/TransactionType.php` (added `AdminAdjustment` case), `backend/routes/api.php`
 
- **Frontend**:
  - Added "إضافة رصيد" (Add Balance) and "خصم رصيد" (Deduct Balance) buttons to admin Users table
  - Modal with amount (number input) and note (optional textarea)
  - On confirm: POST to endpoint, on success: close modal + refresh user list
  - Error handling: shows server message in toast
  - **Files**: `frontend/src/pages/admin/Users.tsx`, `frontend/src/api/client.ts` (`adminUserApi.adjustBalance`), `frontend/src/i18n/en.ts`, `frontend/src/i18n/ar.ts`
 
### Quantity Tier Selector
- **Format A (Object)**: `{ min: number, max: number }` — free-form quantity input with min/max validation
- **Format B (Array of Values)**: `[number, number, ...]` — predefined tier buttons (radio group)
- **Files**: `frontend/src/pages/public/ProductPage.tsx` (lines 14-53, 310-398)
 
### Wallet Auto-Deduct for Auto Products
- Automation products (`is_automation=true`) auto-deduct from wallet at checkout
- No payment method picker shown for auto products
- Manual products still show payment picker (Binance Pay, USDT, Cash Wallet)
- **File**: `frontend/src/pages/public/ProductPage.tsx` (lines 109-141)
 
### Oranos Integration: playerId Positional Fallback
- Oranos order payload expects `playerId` at array index 0
- `oranos_order_id` persisted on order for status polling
- **File**: `backend/app/Services/OrderService.php::fulfillAutomationItems` (line ~240, PROTECTED — do not modify)
 
### Orders Poll Oranos Command
- Scheduled command: `orders:poll-oranos` runs every 5 minutes
- Registered in `routes/console.php`
- Polls Oranos for order status updates on Processing orders
 
### Admin Reject on Wallet-Paid Orders Triggers Refund
- When admin rejects an order that was paid via wallet balance
- Triggers `OrderService::refundFailedOrder` to refund user balance
- **File**: `backend/app/Services/OrderService.php::refundFailedOrder` (PROTECTED — do not modify)
 
### Admin Balance Adjustment Endpoint
- `POST /api/admin/users/{user}/balance` (documented above)

### Category Image Propagation (Phase 10)
- **Root cause**: Oranos Categories API returns only `id` and `name` — no images
- **Fix**: One-time tinker script copies `image_url` from first child category with image, or from first product in category, to root categories missing images
- **Result**: 18 root categories now have images for home page display

### Admin Modals Arabic-Only (Phase 10)
- **Files**: `frontend/src/components/ProductModal.tsx`, `frontend/src/components/CategoryModal.tsx`
- **Pattern**: All labels → `t('admin.nameAr')`, `t('admin.type')`, `t('admin.descriptionAr')`, `t('admin.icon')`, `t('admin.image')`, `t('admin.sortOrder')`, `t('admin.formFields')`, `t('admin.addField')`, `t('admin.fieldLabelEn')`, `t('admin.fieldLabelAr')`, `t('admin.fieldType')`, `t('admin.fieldRequired')`, `t('admin.fieldOptions')`, `t('admin.fieldOptionsHint')`, `t('admin.automatic')`, `t('admin.manual')`, `t('admin.saveChanges')`, `t('admin.createCategory')`, `t('common.delete')`, `t('common.cancel')`
- **Translation keys**: Added to `admin:` block in both `en.ts` and `ar.ts` (reference `ProductModal.tsx` pattern)

---

## D9. Known Limitations

### From Inventory (Orphaned / Missing / Uncategorized)

1. **Database not fully migrated** — Check `php artisan migrate:status` for current pending count. Core tables (`products`, `orders`, `order_items`, `transactions`, `sessions`, `personal_access_tokens`, `favorites`, `manual_order_fields`, `form_schema` on categories, `parent_id` on categories, `icon` on products, `rejection_reason` on transactions) must exist before production deploy.

2. **Oranos stock not exposed** — Products show a ceiling of 999 (from seeder) but real Oranos stock is not synced to our `stock` column. Admin cannot see actual availability.

3. **Oranos balance must be topped up** — Auto-fulfillment fails silently if Oranos account balance is insufficient. Admin must monitor `/admin/oranos` and manually fund Oranos account.

4. **Admin Settings UI does not persist markup values** — The `ORANOS_MARKUP` env var is used for price computation, but the Admin Settings UI has no field to update it at runtime. The `oranos:apply-markup` command reads from config, not from settings table.

5. **Forgot password route exists but no frontend page** — `POST /api/auth/forgot-password` is registered but no "Forgot Password" UI exists.

6. **7 Oranos artisan commands are orphaned** — `apply-category-images`, `apply-tree`, `generate-placeholders`, `harvest` (scheduled), `import-category-images`, `import-images`, `import-product-images`, `parent-fallback`, `sync-product-images` — not triggered from UI or scheduled.

8. **No Job/Listener/Observer layers** — Events broadcast directly via Reverb; no async job processing for heavy operations (e.g., Oranos sync).

9. **CategoryModal now used** — Integrated in `frontend/src/pages/admin/Categories.tsx` for create/edit; no longer orphaned.

10. **Frontend LanguageSwitcher test fails** — Pre-existing bug in test, not in component.

11. **VITE_GA_MEASUREMENT_ID and VITE_UMAMI_WEBSITE_ID undocumented** — Used in Analytics.tsx but not in frontend `.env.example`.

12. **Duplicate rejection_reason migrations** — Two migrations add rejection_reason to transactions (2026_09_07_210539 and 2026_09_13_144541).

13. **Demo credentials in seeder** — DemoSeeder creates users with known passwords (`password`) and mock API keys.

### Unfixed from Task B
- None — ProductPage hero image centering fixed by adding `aspect-[4/5]` wrapper.

### Top 10 Risks (Ranked by Severity)

| # | Risk | File/Location | Suggested Fix |
|---|------|---------------|---------------|
| 1 | **Data loss: pending migrations** | `database/migrations/*.php` (13 pending) | Run `php artisan migrate` on staging; verify all tables exist before production deploy |
| 2 | **Money loss: Oranos balance depletion** | `OrderService::fulfillAutomationItems()` | Add pre-flight balance check; alert admin when Oranos balance < $50; fail order early with clear message |
| 3 | **Money loss: Auto-fulfillment silent failure** | `OrderService::fulfillAutomationItems()` catch block | On Oranos failure, mark order `Rejected` with reason, refund user balance, notify admin |
| 4 | **User-facing bug: Products invisible** | `Product::where('is_active', true)` but `products` table missing | Run migrations; add deployment gate that fails if migrations pending |
| 5 | **User-facing bug: Sessions don't persist** | `sessions` table migration pending | Run migration 2026_09_01_220626_create_sessions_table |
| 6 | **User-facing bug: Sanctum tokens broken** | `personal_access_tokens` table migration pending | Run migration 2026_09_04_233025_create_personal_access_tokens_table |
| 7 | **User-facing bug: Favorites broken** | `favorites` table migration pending | Run migration 2026_09_25_203000_create_favorites_table |
| 8 | **Cosmetic: Admin markup setting not persisted** | `AdminSettingsController`, `AdminSyncController` | Add `oranos_markup` to settings table; update `OranosMarketService` to read from Setting::get() |
| 9 | **Cosmetic: LanguageSwitcher test flaky** | `src/components/LanguageSwitcher.test.tsx` | Fix test to properly await locale change |
| 10 | **Cosmetic: Unused Oranos commands clutter CLI** | `app/Console/Commands/Oranos*.php` | Document purpose or remove if truly abandoned |

---

## Appendix: Inventory Summary

| Category | Count | Flags |
|----------|-------|-------|
| Backend Routes | 91 | 1 ORPHANED (forgot-password) |
| Artisan Commands | 12 | 7 ORPHANED, 5 USED/SCHEDULED |
| Models | 11 | 0 UNUSED |
| Services | 6 + 3 gateways | 0 UNUSED |
| Middleware | 2 | 0 DEAD |
| Migrations | 27 | Check `migrate:status` for current pending |
| Events | 5 | All ACTIVE |
| Frontend Pages | 36 | 0 ORPHANED/BROKEN |
| Shared Components | 26 | 10 HIGH-IMPACT (CategoryModal now used) |
| API Endpoints | 63 | 0 MISSING/MISTYPED |
| Env Variables | ~87 | 2 UNDOCUMENTED |
| Webhooks | 3 | All verified |
| Scheduled Tasks | 5 | All ACTIVE |
| **Oranos Categories** | **561 total (18 root)** | Synced |
| **Oranos Products** | **2,400+** | Synced |

---

*Generated by automated codebase inventory. For questions, contact the development team.*