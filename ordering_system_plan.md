# Produce & Inventory Ordering System

**Verified Architecture, Database Schema & Phased Implementation Plan**  
**Status:** Verified & Finalized Blueprint

> **Core Stack:** PHP 8.4 (Slim 4, `slim/php-view`, PHP-DI, `dibi`, `firebase/php-jwt` via `HttpOnly` cookies, Phinx migrations, PHPMailer, Dompdf, PHPUnit) • MySQL 8.4 (WAMP64) • Tailwind CSS • Strict TypeScript + Modular Alpine.js + Native Fetch API • Apache `.htaccess`.

---

## 1. Filtered Inventory Catalog Analysis (Option A — 98 Active Items)

Derived from the 4-page *Produce & Inventory Catalog* (105 original rows). Non-matching `Other` items, `Package` items, and the duplicate `Radish` entry under `Vegetable` are removed (7 rows removed), while **Mushrooms** and **Eggs** are included in the active inventory under their own dedicated categories.

| Category | Item Count | Units Used | Notes |
| :--- | :---: | :--- | :--- |
| **Fruit** | 31 | `kg`, `Punnet`, `Unit` | All 31 Fruit items from PDF (Pages 1–2) |
| **Vegetable** | 42 | `kg`, `Punnet`, `Unit` | All Vegetable items minus duplicate `Radish` (`Mushroom` moved to the dedicated **Mushrooms** category) |
| **Herb** | 22 | `kg`, `Punnet` | All 22 Herb items from PDF (Page 2), including `Radish` (`kg`) |
| **Mushrooms** | 1 | `kg` | `Mushroom` in `kg` (`0.1 kg` steps, Page 3) — extendable for more mushroom varieties in `kg` |
| **Eggs** | 2 | `Unit` | `Eggs Large (30) - Free Range`, `Eggs Large (30) - Plain` |
| **Total Active Seed** | **98** | **3 units** | `kg`, `Unit`, `Punnet` |
| **Removed from PDF** | 7 | — | `Check Out Bag`, `Brown Bread`, `White Bread`, `Hamburger Buns (6's)`, `Feta Cheese`, `Stirfry Processed`, duplicate `Radish` |

- **Currency:** ZAR (`R`), configured in `.env` (`APP_CURRENCY=ZAR`, `APP_CURRENCY_SYMBOL=R`).
- **Product Images:** Generated images matching each product description stored in `public/assets/images/products/<slug>` and referenced via `products.image_path`.
- **Cart Increments:** `kg` uses `0.1` decimal steps (`0`, `0.1`, `0.2`, `0.3`, ... using integer-tenth math to avoid float drift); `Unit` and `Punnet` use integer steps of `1`.

---

## 2. MySQL Database Schema Design (Managed via Phinx + `dibi`)

Target engine: **MySQL 8.4** (`InnoDB`, `utf8mb4_unicode_ci`). All migrations and seeders live under `database/migrations/` and `database/seeds/` and read WAMP64 credentials strictly from `.env` via `phinx.php`.

| Table | Key Columns & Types | Purpose & Constraints |
| :--- | :--- | :--- |
| `users` | `id` (PK), `role` (`customer\|company\|supplier`), `company_name`, `contact_name`, `email` (UK), `phone`, `location`, `password_hash`, `email_verified_at`, `is_active`, timestamps | Unified auth & profile table; powers Supplier company cards; login requires verified email. |
| `email_verifications` | `id` (PK), `user_id` (FK), `token_hash` (`CHAR 64` UK), `expires_at`, `used_at`, `created_at` | SHA-256 hashed 24-hour verification tokens sent on registration. |
| `password_resets` | `id` (PK), `user_id` (FK), `hashed_token` (`VARCHAR 100` UK), `expiry_date`, `created_at` | SHA-256 hashed password reset tokens (minimum 7-minute MySQL `NOW()` / `INTERVAL` TTL); self-cleaning on reset, replacement, or expiry. |
| `categories` | `id` (PK), `name` (UK), `slug` (UK), `sort_order` | Seeded with Fruit, Vegetable, Herb, Mushrooms, Eggs. |
| `units` | `id` (PK), `name` (UK), `allow_decimal`, `step_size` (`DECIMAL 4,2`) | `kg` (`allow_decimal=1`, `step_size=0.10`); `Unit` & `Punnet` (`0`, `1.00`). |
| `products` | `id` (PK), `category_id` (FK), `unit_id` (FK), `sku` (UK), `slug` (UK), `description`, `image_path`, `unit_price` (`DECIMAL 10,2`), `stock_quantity`, `is_active` | 98 seeded items with ZAR pricing, active stock flag, and generated image paths. |
| `orders` | `id` (PK), `order_number` (UK), `user_id` (FK), `status` (`pending\|received\|in_progress\|delivered\|declined\|cancelled`), `total_amount`, `notes`, `ordered_at`, `received_at`, `delivered_at`, `cancelled_at` | Order lifecycle tracking; indexed on `(user_id, ordered_at)` and `(status, ordered_at)`. |
| `order_items` | `id` (PK), `order_id` (FK), `product_id` (FK), `product_name_snapshot`, `unit_name_snapshot`, `unit_price_snapshot`, `quantity` (`DECIMAL 10,2`), `line_total` | Immutable price/name snapshots preserving historical invoices and PDF statements. |
| `order_status_history` | `id` (PK), `order_id` (FK), `old_status`, `new_status`, `changed_by_user_id` (FK), `note`, `changed_at` | Full audit trail of supplier status updates. |
| `email_logs` | `id` (PK), `order_id` (FK nullable), `user_id` (FK), `recipient_email`, `email_type`, `subject`, `status` (`sent\|failed`), `error_message`, `sent_at` | Tracks verification emails, order confirmations, supplier alerts, and status updates. |

---

## 3. Backend Architecture & Project Structure

```text
ordering-system/
├── public/
│   ├── .htaccess                  # Apache rewrite rules routing requests to index.php
│   ├── index.php                  # Slim 4 front controller
│   └── assets/
│       ├── css/                   # Compiled Tailwind CSS
│       ├── js/                    # Compiled TypeScript -> JS bundles (shared/ + pages/)
│       └── images/products/       # Generated product images (<slug>.png/.webp)
├── resources/
│   ├── css/                       # Tailwind CSS source (app.css)
│   └── ts/                        # Strict TypeScript for modular Alpine.js
│       ├── types/                 # Shared TS interfaces (Product, CartItem, Order, User, ApiResponse)
│       ├── shared/                # Global layout modules (api.ts, cartStore.ts, authStore.ts, toast.ts, themeStore.ts)
│       └── pages/                 # Page modules (storefront.ts, customerDashboard.ts, supplierDashboard.ts)
├── config/                        # settings.php, container.php (PHP-DI), middleware.php, routes.php
├── src/
│   ├── Controllers/               # Auth, Catalog, Order, CustomerDashboard, SupplierDashboard, Report
│   ├── Services/                  # Auth, Verification, Order, Email, PdfReport, Inventory
│   ├── Repositories/              # dibi SQL repositories (User, EmailVerification, Product, Order, EmailLog)
│   ├── Middleware/                # JwtCookieAuthMiddleware, RoleMiddleware
│   └── Support/                   # OrderNumberGenerator, ResponseHelper, Validator
├── templates/                     # Official slim/php-view templates + layouts/base.php
├── tests/                         # PHPUnit unit & integration test suites
├── database/                      # Phinx migrations/ and seeds/
├── .htaccess                      # Root Apache rewrite to public/ (WAMP64 compatibility)
├── phinx.php                      # Phinx config loading DB credentials from .env
├── phpunit.xml                    # PHPUnit configuration
├── tsconfig.json                  # Strict TypeScript configuration
├── package.json & composer.json   # Frontend & PHP dependencies
└── .env / .env.example            # Local secrets (gitignored) & template
```

### Planned API Endpoints (`/api/v1`)

| Area | Method & Route | Role | Purpose |
| :--- | :--- | :--- | :--- |
| **Auth** | `POST /auth/register` | Public | Register customer/company & send verification email |
| | `GET /auth/verify-email` | Public | Verify email token & activate account |
| | `POST /auth/resend-verification` | Public | Resend verification link |
| | `POST /auth/login` | Public | Authenticate verified account & set JWT `HttpOnly` cookie |
| | `POST /auth/forgot-password` | Public | Issue 7-minute hashed reset token & email (fakes success on invalid/unknown/inactive input) |
| | `POST /auth/reset-password` | Public | Validate MySQL-checked token, update password, and clean up `password_resets` |
| | `POST /auth/logout` / `GET /auth/me` | Auth | Clear JWT cookie / return active user profile |
| **Storefront** | `GET /products` & `GET /categories` | Public | Active stock with category, unit step size, ZAR price & image |
| **Orders** | `POST /orders` | Cust/Comp | Create order, issue order number, email customer & supplier |
| **Customer** | `GET /customer/orders` & `/{id}` | Cust/Comp | Filter by date range/order number/status; compute total spend |
| | `GET /customer/orders/{id}/reorder` | Cust/Comp | Live stock & price validation preview for one-click reorder into cart |
| | `POST /customer/orders/{id}/cancel` | Cust/Comp | Cancel a `pending` order, atomically restore reserved stock, log history & email supplier/customer |
| | `PATCH /customer/orders/{id}/notes` | Cust/Comp | Edit delivery notes while an order is still `pending` |
| | `GET /customer/reports/pdf` | Cust/Comp | Download Dompdf statement (Complete/Delivered & Declined) |
| **Supplier** | `GET /supplier/companies` & `/{id}/orders` | Supplier | Company cards & status-filtered order history |
| | `PATCH /supplier/orders/{id}/status` | Supplier | Transition status (`received`, `in_progress`, `delivered`, `declined`) & email |
| | `GET / POST / PATCH / DELETE /supplier/products` | Supplier | Full product CRUD: add, edit & update products, ZAR pricing, stock, images & active status |

---

## 4. Frontend Architecture (`slim/php-view` + Tailwind + TypeScript/Alpine.js)

- **Templating & Routing:** Official `slim/php-view` with a shared `templates/layouts/base.php` layout and Apache `.htaccess` rewrite rules.
- **Modular TypeScript + Alpine.js:** Shared modules (`api.ts` using native Fetch API only with `credentials: 'same-origin'`, `authStore.ts`, `cartStore.ts`, `toast.ts`, `themeStore.ts`) compiled and linked globally in `layouts/base.php`; page modules linked in individual templates.
- **Welcome Storefront (`/`):** Category tabs, live search, product cards with generated photos, ZAR pricing, `0.1 kg` / `1 Unit` controls, and reactive cart drawer.
- **Customer Dashboard (`/dashboard`):** Date-range & order-number filters, Total Spend across selected order invoices, order status tracking, and PDF statement export (*Complete / Delivered & Declined*).
- **Supplier Dashboard (`/supplier` & `/supplier/inventory`):** Company cards (total orders, email, phone, location, View More Details), order status transitions, and full admin product CRUD (add, edit & update products, ZAR pricing, stock & active visibility).

---

## 5. Phased Execution Roadmap & Release Gate

1. **Phase 1 — Git Setup, Project Foundation, Phinx Migrations & Catalog Seed:** Init Git repo on `main`, add `git@github.com:imSamaritan/ordering-system.git` as `origin`, configure `.gitignore`, Composer, npm/TypeScript/Tailwind, `.htaccess`, `.env`, Phinx migrations, and 98-item seed + default supplier.
2. **Phase 2 — Product Image Generation:** Generate matching product images for all 98 items and wire `products.image_path`.
3. **Phase 3 — Auth, Email Verification & JWT Cookies:** Registration, email verification flow, login/logout with `HttpOnly` JWT cookies, and role middleware.
4. **Phase 4 — Storefront & Decimal Cart Checkout:** Active stock UI, `0.1 kg` / `1 Unit` TypeScript/Alpine.js cart, order placement & order numbering.
5. **Phase 5 — Customer Dashboard & PDF Statements:** Date-range/order-number filtering, total spend across selected invoices, and Dompdf statement generation.
6. **Phase 6 — Supplier Dashboard, Product Management, Order Lifecycle & Emails:** Company cards, status transitions, full supplier product CRUD (add, edit, update products & ZAR pricing), SMTP alerts/updates, and end-to-end verification.
7. **Phase 7 — Mobile-First Responsive UI Across All Devices:** Full mobile & tablet responsiveness across every template and interactive flow — collapsible sticky mobile navigation drawer with always-accessible cart trigger, mobile-safe toast positioning, horizontally scrollable category filter pills, dynamic-viewport (`100dvh`) slide-over cart drawer with safe-area padding, mobile card views alongside desktop tables for Customer Invoices, Supplier Company Orders, and Supplier Inventory, touch-friendly controls, responsive filter grids, and overflow-safe modals across Storefront, Auth, Customer Dashboard, and Supplier Portal.
8. **Phase 8 — Extensible Dark Mode & Safe Light Mode:** Implement full dark mode support across every template, modal, drawer, table, badge, form control, and toast while preserving light mode as a safe, regression-free default. Built on an extensible theme registry (`themeStore.ts`), `data-theme` / `.dark` attribute switching with Tailwind v4 `@custom-variant dark` and semantic CSS tokens, zero-FOUC inline initialization, `localStorage` persistence with cross-tab sync, live `prefers-color-scheme` (`system`) tracking, and accessible desktop & mobile theme switchers designed so additional color modes can be registered cleanly without refactoring UI plumbing.
9. **Phase 9 — Password Reset Flow (MySQL Expiry, Self-Cleaning Tokens & Anti-Enumeration):** Implement end-to-end forgot-password and reset-password flows backed by the `password_resets` Phinx migration (`user_id`, `hashed_token` unique, `expiry_date`, `created_at`). All token lifespans (`DATE_ADD(NOW(), INTERVAL 7 MINUTE)`, minimum 7 minutes) and validity checks (`expiry_date > NOW()` returning strict `bool`) run directly in MySQL; expired, superseded, and redeemed rows are automatically cleaned up from `password_resets`; misleading/invalid/unknown/inactive email requests fake sending with an identical `200 OK` response to prevent user enumeration; includes `/forgot-password` and `/reset-password` views, Alpine.js/TypeScript forms, `email_logs` integration, and full PHPUnit coverage. See [`password_reset_plan.md`](password_reset_plan.md) for the complete specification.
10. **Phase 10 — Role-Aware Navigation & Global Cart Drawer Overlay:** Refine top navigation visibility by user role and make the cart overlay accessible outside the storefront for buying accounts: (1) remove/hide the "Cart" and "Storefront" links in the navigation when the logged-in account's role is `supplier`; (2) enable both `customer` and `company` accounts to click "Cart" in the top navigation while on their dashboard and render the cart drawer as a screen overlay identically to the storefront experience.
11. **Phase 11 — Cart Item Product Thumbnails (Left-Aligned Inventory Parity):** Mirror the supplier inventory list's left-aligned product thumbnail layout inside the global slide-over cart drawer (`templates/layouts/base.php`, `resources/ts/shared/cartDrawer.ts`, and `resources/ts/shared/cartStore.ts`) so `customer` and `company` buyers can visually identify items at a glance while building and reviewing their cart: render each cart line item with its product image (`CartItem.imagePath`, resolved via `getBasePath()` with external URL support) neatly aligned on the left of the item description and unit price using a fixed-size rounded square thumbnail (`object-cover`, `shrink-0`) with a graceful `IMG` placeholder fallback when missing or broken, refresh `imagePath` on repeated `cartStore.increment()` calls for existing items, and preserve responsive alignment and light/dark theme styling across both Storefront and Customer/Company Dashboard views.
12. **Phase 12 — Compact Welcome/Home Banner Card:** Streamline the storefront hero banner card in `templates/home.php` by removing the `<h1>` heading (`Fresh Produce Storefront`) and `<p>` description paragraph, leaving only the badge tag (`Fresh Wholesale & Retail Produce`) and the catalog search box cleanly aligned, followed by rebuilding production assets and updating the production bundle.
13. **Phase 13 — One-Click Reorder ("Order Again") with Live Stock & Price Validation:** Add repeat ordering across the Customer Dashboard (`/dashboard`) desktop table, mobile order cards, and Order Detail modal backed by `GET /api/v1/customer/orders/{id}/reorder` and read-only `ProductRepository::findByIds()`. Live-validates each historical line item into `available`, `adjusted` (stock-capped in valid `0.1 kg` / `1 Unit` increments), and `unavailable` with `price_changed` delta reporting; adds atomic `cartStore.addBulkItems(items, 'merge' | 'replace')`; prompts `Merge with cart` vs `Replace cart` when the cart is non-empty; displays a toast summarizing adjustments, unavailable items, or price changes; opens `$store.cart.open()`; and ships with full PHPUnit coverage (`tests/Integration/CustomerReorderTest.php`). See [`customer_dashboard_oms_roadmap.md`](customer_dashboard_oms_roadmap.md) for the full OMS roadmap (Phases 13–18).
14. **Phase 14 — Customer Self-Service: Cancel Pending Orders & Edit Pending Notes:** Extend `orders.status` ENUM with `cancelled` (distinct from supplier-initiated `declined`) and add `cancelled_at` (`DATETIME NULL`) via reversible Phinx migration (`database/migrations/20261128000300_add_cancelled_order_status.php`) mirrored in `database/production_install.sql`. Add `OrderService::cancelOrderByCustomer()` (locks order `FOR UPDATE`, verifies ownership & `status === 'pending'`, transitions to `cancelled` with `cancelled_at`, logs `order_status_history`, atomically restores reserved stock for all `order_items`, and emails both supplier and customer via `EmailService`) and `OrderService::updatePendingOrderNotes()` (edits delivery notes only while `pending`). Expose `POST /api/v1/customer/orders/{id}/cancel` and `PATCH /api/v1/customer/orders/{id}/notes` under `RoleMiddleware('customer', 'company')` + `JwtCookieAuthMiddleware`; add `cancelled` filter, summary counts, and badge styling across Customer Dashboard, Supplier Dashboard, and Dompdf statements; add inline Edit Delivery Notes and Cancel Order confirmation modal (with optional reason) in the Customer Order Detail modal; and verify with `tests/Integration/CustomerOrderCancelAndNotesTest.php`. See [`customer_dashboard_oms_roadmap.md`](customer_dashboard_oms_roadmap.md) for the complete specification.
15. **Phase 15 — Saved Order Templates / Standing Lists:** Add `order_templates` and `order_template_items` tables plus full CRUD and one-click cart loading so retail and wholesale buyers can save recurring baskets from the Cart Drawer or past orders and manage them in a dedicated **Saved Lists** tab.
16. **Phase 16 — Visual Order Progress Tracker, PO Reference & Delivery Scheduling:** Add optional `po_number` and `requested_delivery_date` columns to `orders` (captured at checkout, searchable in filters, and printed on PDF invoices/emails) alongside a 4-step visual fulfillment progress stepper in the Order Detail modal.
17. **Phase 17 — Spend Analytics, Quick Date Presets, Search/Pagination & CSV Export:** Add `GET /api/v1/customer/analytics` (monthly spend trend, category breakdown, Top 5 most-ordered items with quick `+ Add to Cart`), quick date range presets, product-name search, column sorting, pagination, and RFC 4180 CSV export (`GET /api/v1/customer/reports/csv`).
18. **Phase 18 — Account & Delivery Profile Self-Service + Notification History:** Add a **Profile & Settings** tab on `/dashboard` allowing buyers to update contact/company/delivery details (`PATCH /api/v1/customer/profile`), change their password (`POST /api/v1/customer/change-password`), and inspect recent order email logs (`email_logs`).
19. **Phase 19 — Shared Currency Formatting Utility & Active Total Spend Exclusion (`declined` / `cancelled`) *(Completed)*:**
    - **Shared Currency Formatter (`resources/ts/shared/format.ts` & `resources/ts/shared/api.ts`):** Added `formatCurrencyAmount(amount, symbol = 'R')` and wired `formatCurrency()` to delegate to it across both the Customer Dashboard and Supplier Dashboard (cards, tables, modals, cart, and storefront). Formats all amounts to 2 decimal places with comma + non-breaking space (`,\u00A0`) thousands grouping so narrow KPI cards never wrap mid-number (e.g., `50` → `R50.00`, `100` → `R100.00`, `1000` → `R1, 000.00`, `10000` → `R10, 000.00`, `120000` → `R120, 000.00`, `135599.99` → `R135, 599.99`).
    - **Excluded Orders Total Spend Rule (`resources/ts/pages/customerDashboard.ts` & `src/Controllers/CustomerDashboardController.php`):** Preserved historical `orders.total_amount` on `declined` and `cancelled` orders while excluding both statuses from default auto-selection (`selectedOrderIds`) and default `summary.total_spend` on **All Statuses** views. When a buyer explicitly filters by `declined`, `cancelled`, or `Delivered & Declined`, or manually checks those invoices, their totals are included in **Total Spend (Selected)**. Verified by PHPUnit (`CustomerDashboardAndPdfTest`, `CustomerOrderCancelAndNotesTest`), TypeScript strict check, and production bundle rebuild.

### Mandatory Release Gate After Every Phase

1. Run **PHPUnit** (`vendor/bin/phpunit`) + TypeScript check (`npx tsc --noEmit`) + asset build until 100% green.
2. Create a new phase branch (e.g., `phase-1-setup`) and commit all verified changes.
3. Push the phase branch to GitHub `origin`, checkout `main`, merge the phase branch into `main`, and push `main` to `origin`.
