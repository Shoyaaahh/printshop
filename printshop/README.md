# Online Printing Shop: ordering and management system

A walk-in printing shop website with online ordering and **store pickup only** (no delivery).
Customers upload files, see an estimated price, place the order and track it. Staff review orders,
verify payments, run production and confirm pickup from an admin dashboard.

**Stack:** PHP 8.1+ (website, API, admin) · SQLite (database, zero setup) · Python 3 (page counting, email sender, backups, report export, cleanup) · Chart.js and Lucide icons (loaded from CDN).

> The original brief suggested React/Supabase. Because you asked for Python and PHP, this is a classic server-rendered PHP app. The business rules, pages and database tables from the brief are all implemented.

## 1. Requirements

| Tool | Version | Check |
|---|---|---|
| PHP | 8.1 or newer, with extensions `pdo_sqlite`, `fileinfo`, `mbstring` | `php -v` and `php -m` |
| Python | 3.8 or newer (optional `pip install pypdf` for more exact PDF page counts) | `python --version` |

Windows: install PHP from https://windows.php.net/download (unzip, add to PATH, in `php.ini` enable `extension=pdo_sqlite`, `extension=sqlite3`, `extension=fileinfo`, `extension=mbstring`). Or install XAMPP and use its `php.exe`.

## 2. Run it in VS Code (5 minutes)

1. Unzip, then **File > Open Folder** > `printshop`.
2. Copy `.env.example` to `.env` (on Windows keep `PYTHON_BIN=python`).
3. Open the terminal (**Ctrl+`**) and run:
   ```
   php database/install.php
   php -S localhost:8000 -t public
   ```
   (Or **Terminal > Run Task** > *1. Install database*, then *2. Run website*.)
4. Open http://localhost:8000

**Default admin login** (change it right away in Admin > Settings > Account):
- URL: http://localhost:8000/admin/login.php
- Email: `admin@printshop.local` · Password: `Admin@12345`
- Choose your own: `php database/install.php --reset --admin-email=you@shop.com --admin-password="YourPass123"`

Customers register themselves at `/register.php`.

## 3. First things to do as the shop owner
1. **Settings**: business name, logo, address, phone, operating hours, GCash/Maya/bank details and instructions, payment policy (pay before processing or pay at store).
2. **Pricing**: set your real rates. The customer calculator uses them immediately.
3. **Services**: edit or hide the sample services, add your own.

## 4. How an order flows

`Order Received` > `Awaiting Shop Review` > (`Awaiting Customer Confirmation` if you change the price) > `Pending Payment` > `Payment Verified` > `Processing` > `Printing` > `Quality Checking` > `Ready for Pickup` > `Completed` (only through **Confirm Pickup**) · or `Cancelled`.

- The browser's price is only a preview. The **server recalculates** the price when the order is submitted.
- Price changes after review notify the customer, who must accept (or the order is cancelled).
- Payments are never "paid" until an admin verifies them (or records cash). With the default policy, processing is blocked until payment is verified.
- Every status change, payment action, login, download and settings change is recorded (order history + audit log).

## 5. Project layout
```
app/        PHP libraries: db, auth, pricing engine, order/payment/pickup workflow, layout
database/   schema.sql and install.php (creates DB + sample data)
public/     the ONLY web-served folder (document root)
  api/        price, upload, order_submit endpoints
  customer/   customer portal      admin/   administrator dashboard
storage/    database + private customer uploads (never served by the web server)
tools/      Python scripts (see below)
tests/      smoke_test.py (end-to-end test)
```

### Python tools
| Script | What it does | Used by |
|---|---|---|
| `tools/analyze_file.py` | Counts pages in PDF/DOCX/PPTX/images | PHP, on every upload |
| `tools/send_emails.py` | Sends queued notification emails by SMTP | schedule every minute |
| `tools/backup.py` | Zips database + uploads into `storage/backups` | schedule daily |
| `tools/daily_report.py` | Exports sales CSV for a date range | manual |
| `tools/cleanup_tmp.py` | Deletes abandoned temporary uploads | schedule daily |

Schedule them with cron (Mac/Linux) or Task Scheduler (Windows), e.g. `python tools/backup.py`.

## 6. Tests
```
python tests/smoke_test.py
```
Starts a temporary server and database and checks 68 things: the complete order > payment > pickup flow, price recalculation, access control between customers, upload validation, CSRF, workflow rules, CSV export.

## 7. Deploying for real customers
- **Use HTTPS.** Point the web server's document root at `public/` only. Keep `app/`, `database/`, `storage/`, `tools/` outside it (or blocked; `.htaccess` files are included for Apache).
- Set `APP_DEBUG=0`. Change the admin password. Make `storage/` and `public/uploads/` writable by the web user.
- In `php.ini` set `upload_max_filesize` and `post_max_size` (at least 25M).
- Apache/Nginx + PHP-FPM on a VPS or shared hosting with PHP 8.1+ works. (Vercel/Supabase from the original brief are not used.)
- Schedule `backup.py`. Test restoring a backup once.
- Email: fill `SMTP_*` in `.env`, tick the option in Settings, schedule `send_emails.py`.
- Moving to MySQL/PostgreSQL later: `app/db.php` uses PDO and prepared statements. You would change the DSN and a few SQLite-specific bits (`strftime`, `INSERT OR REPLACE`, `AUTOINCREMENT`) in `schema.sql`, `helpers.php` and `reports.php`.

## 8. Security checklist (what is implemented)
Password hashing (bcrypt via `password_hash`) · role checks on every page (separate admin login) · customers can only read their own orders/files · prepared statements everywhere · output escaped (`e()`) · CSRF tokens on all forms and APIs · login/upload/order/track/contact rate limits · uploads: extension + real MIME check, size limit, random names, stored outside the web root and delivered through an authorised download script with `nosniff` and a sandbox CSP · session cookie `HttpOnly`/`SameSite` (+`Secure` on HTTPS) and session id regenerated on login · CSV export guards against formula injection · audit log.

## 9. Honest limitations
- Payments are **manual verification** (customer sends reference + receipt; staff verifies). There is no GCash/Maya API integration, which would need merchant credentials from those providers.
- Orders contain one service per order (the item table supports more; the form does not yet).
- Email needs your own SMTP account. In-app notifications work without it.
- SQLite suits one shop with moderate traffic. Switch to MySQL/PostgreSQL if you expect heavy concurrent use.
