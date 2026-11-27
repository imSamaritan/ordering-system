# cPanel Shared Hosting Deployment Guide

This package is pre-built and production-ready for shared cPanel hosting (no Node.js or Composer required on the server).

## 1. Server Requirements (cPanel)
- **PHP version:** `8.4` (8.4.1+) — set via **cPanel → MultiPHP Manager**
- **Required PHP extensions** (check **cPanel → Select PHP Version** / **PHP Extensions**):
  - `mysqli`
  - `mbstring`
  - `gd`
  - `dom`
  - `xml`
  - `openssl`
  - `fileinfo`
  - `json`
- **MySQL / MariaDB:** MySQL 8.0+ or MariaDB 10.4+
- **Apache `mod_rewrite`:** Enabled (standard on cPanel)

---

## 2. Create the MySQL Database in cPanel
1. Open **cPanel → MySQL® Databases** (or **Manage My Databases**).
2. Create a new database (e.g., `cpaneluser_ordering`).
3. Create a MySQL user with a strong password (e.g., `cpaneluser_orderuser`).
4. Add the user to the database with **ALL PRIVILEGES**.
5. Open **cPanel → phpMyAdmin**, select your new database, click **Import**, and upload:
   - `database/production_install.sql`
   - This creates all 11 tables, records Phinx migration state, seeds categories/units, seeds the 98 catalog products, and creates the initial supplier account (`supplier@freshproduce.co.za`).

---

## 3. Upload & Extract the Application
Upload `ordering-system-cpanel-prod.zip` using **cPanel → File Manager**:

### Option A: Root Domain (`https://yourdomain.co.za`)
1. Upload `ordering-system-cpanel-prod.zip` to `public_html/`.
2. Click **Extract** in File Manager.
3. The included root `.htaccess` automatically routes all traffic to `public/` and blocks direct web access to `.env`, `config/`, `src/`, `vendor/`, and `database/`.
4. Delete `ordering-system-cpanel-prod.zip` after extracting.

### Option B: Subdirectory (`https://yourdomain.co.za/ordering`)
1. Create `public_html/ordering/` and extract the zip inside it.
2. In `.env`, set:
   ```env
   APP_URL=https://yourdomain.co.za/ordering
   APP_BASE_PATH=/ordering
   ```

### Option C: Addon Domain / Subdomain Pointing Directly to `public/` (Most Secure)
1. Upload and extract the zip outside `public_html` (e.g. `/home/cpaneluser/ordering-system`).
2. In **cPanel → Domains**, set the domain's **Document Root** to `/home/cpaneluser/ordering-system/public`.
3. In `.env`, set `APP_BASE_PATH=` (empty).

---

## 4. Configure `.env`
In File Manager, enable **Show Hidden Files (dotfiles)** in **Settings**, then edit `.env`:

1. Live site URL & production flags are pre-configured:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://asamaritan-order-management-sys.co.za
   APP_BASE_PATH=
   ```
2. cPanel MySQL credentials are pre-configured in the bundled `.env`:
   ```env
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=asamarit_ordering_system_management_sys
   DB_USER=asamarit_root
   DB_PASS='<your-cpanel-db-password>'
   ```
3. **Email (SMTP) configuration** is pre-configured in the bundled `.env` for `mail.asamaritan-order-management-sys.co.za` (SSL, port 465):
   ```env
   SMTP_HOST=mail.asamaritan-order-management-sys.co.za
   SMTP_PORT=465
   SMTP_USER=no-reply@asamaritan-order-management-sys.co.za
   SMTP_PASS='<your-email-account-password>'
   SMTP_ENCRYPTION=ssl
   SMTP_FROM_EMAIL=no-reply@asamaritan-order-management-sys.co.za
   SMTP_FROM_NAME="Produce & Inventory Ordering System"
   ```
4. **Supplier notification email:** Update `SUPPLIER_EMAIL` if you want order notifications sent to a different live address.
5. **Security:** A fresh 64-character `JWT_SECRET` has already been generated for production. Ensure `.env` file permissions are `0640` or `0600`.

---

## 5. Enable HTTPS Redirect (Recommended)
Once SSL/AutoSSL is active on your domain, uncomment the 3 HTTPS redirect lines in `.htaccess` and `public/.htaccess`:
```apache
RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP:X-Forwarded-Proto} !https [NC]
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## 6. Post-Deployment Verification Checklist
1. Visit `https://yourdomain.co.za/api/v1/health` — should return:
   ```json
   {"status":"ok","database":"ok","currency":"ZAR","currency_symbol":"R"}
   ```
2. Visit `https://yourdomain.co.za/` — storefront loads with all 98 products and images.
3. Verify `https://yourdomain.co.za/.env` returns **403 Forbidden** or **404 Not Found**.
4. Sign in to `/login` as supplier (`supplier@freshproduce.co.za`) and **change the default password** via the password reset flow or database.
5. Test registering a customer account and confirm the verification email arrives from `no-reply@asamaritan-order-management-sys.co.za`.
6. Test placing an order and downloading a PDF statement from `/dashboard`.
