# BUZNET CRM

BUZNET CRM is a PHP and MySQL business CRM and billing application for managing customers, sales activity, quotations, invoices, payments, expenses, and reports from one workspace. It is designed for local use with XAMPP and can also be deployed on a PHP/MySQL web server.

## Features

### Business overview

- Dashboard with live business metrics and revenue visualization.
- Responsive navigation and pages for desktop and mobile screens.
- Searchable in-app help and user guide.

### Customer and sales management

- Customer records with company/contact information, address, state, GSTIN, and notes.
- Lead tracking and conversion of leads into customers.
- Product and service catalog with prices, units, HSN/SAC, and configured tax rates.
- Task and reminder tracking with due dates and completion status.
- Quotations with customer, validity dates, line items, tax, and totals.
- Convert accepted quotations into invoices while retaining the quotation record.

### Billing, GST, and payments

- Create, issue, list, and view invoices with itemized lines and saved customer/business details.
- Printable invoice pages and server-generated invoice PDFs.
- Configurable business profile, currency, GST registration type, GSTIN, address, state, and state code.
- GST Tax Invoice, Bill of Supply, or Standard Invoice document type based on the configured business profile.
- For Regular GST profiles, calculate CGST and SGST for matching supplier/customer state codes, or IGST for different state codes.
- Invoice item prices and tax rates are looked up from the server-side catalog.
- Consecutive invoice numbers organized by Indian financial year.
- Record payments and reverse payments with an audit trail.

### Expenses and reports

- Record expenses with date, category, description, amount, payment method, and vendor.
- View date-range reports, sales and payment totals, expenses, and customer billing/outstanding summaries.

### Accounts, access, and data safety

- First-run setup creates the business workspace and Owner account.
- Shared Owner/Manager sign-in, hashed passwords, session controls, CSRF protection, and login throttling.
- Owner-controlled Manager accounts and module permissions enforced on server routes.
- Change account password and request single-use password reset links by email.
- Activity log for important account and business actions.
- Download business data as JSON and restore from a validated backup with Owner password confirmation, a recovery snapshot, and transactional restore handling.

## Requirements

- PHP 8.1 or newer.
- PHP PDO MySQL extension.
- MySQL or MariaDB.
- Apache or another PHP-capable web server. XAMPP provides Apache and MySQL/MariaDB for local development.

## Installation with XAMPP

1. Copy this project folder into XAMPP's `htdocs` directory.
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. In phpMyAdmin, create a database named `buznet_crm`.
4. Select that database and import [`database/schema.sql`](database/schema.sql).
5. Configure the database connection in the Apache/PHP environment. The local defaults are host `127.0.0.1`, database `buznet_crm`, user `root`, and a blank password. Override them with:
   - `BUZNET_DB_HOST`
   - `BUZNET_DB_NAME`
   - `BUZNET_DB_USER`
   - `BUZNET_DB_PASS`
6. Visit `http://localhost/<folder-name>/setup.php` and create the business and Owner account. Use a unique password of at least 12 characters.
7. Sign in at `http://localhost/<folder-name>/login.php`.

For this workspace folder named `backup crm`, the local setup URL is `http://localhost/backup%20crm/setup.php`.

The setup page is disabled after an Owner account exists. Keep the database credentials private.

## Existing database upgrades

New installations should import `database/schema.sql` only. To upgrade an older database, back it up first and apply only migrations it has not already received, in order as applicable:

1. `database/migrations/002_quotations.sql`
2. `database/migrations/003_lead_conversion.sql`
3. `database/migrations/004_payment_reversals.sql`
4. `database/migrations/005_password_recovery.sql`
5. `database/migrations/006_gst_workflow.sql`

Each migration is intended to run once. See [`docs/installation.md`](docs/installation.md) and [`database/README.md`](database/README.md) for upgrade details.

## Configuration after setup

- Complete the business legal name, address, GST registration profile, GSTIN, state, and state code in **Settings**.
- Add accurate HSN/SAC and tax rates to catalog items and customer GST/address/state information where applicable.
- Configure `BUZNET_APP_URL`, `BUZNET_MAIL_FROM`, and PHP mail transport (or a local SMTP relay) to deliver password recovery links.
- Use HTTPS and a restricted database account for production. Keep private storage outside the public document root.

## GST scope

GST support provides configured invoice calculations and document presentation. It is not a statutory compliance or filing system. It does not file returns, validate GSTINs with the GST portal, determine special place-of-supply cases, or implement reverse charge, credit/debit notes, e-invoicing/IRN, or e-way bills. Verify tax treatment and invoice content with a qualified tax professional.

## Project structure

```text
app.php                 Shared database, session, authentication, and page helpers
setup.php               First-run workspace and Owner setup
dashboard.php           Business overview
customers.php           Customer records
leads.php               Lead tracking
products.php             Products and services catalog
tasks.php                Tasks and reminders
quotations*.php          Quotations and quotation details
invoice*.php             Invoice creation, details, and PDF output
payments.php             Payment records and reversals
expenses.php             Expense tracking
reports.php              Business reports
managers.php             Manager accounts and permissions
settings.php              Business, account, and security settings
backup.php, restore.php   Business data export and restore
database/schema.sql      New-install database schema
database/migrations/     Incremental database upgrades
docs/                     Installation and implementation notes
```

## Current exclusions

This release does not include provider-backed AI, seeded demo data, full accounting/ledger, payroll, inventory management, bank synchronization, payment gateway processing, or automatic GST filing. See [`docs/implementation-status.md`](docs/implementation-status.md) for current implementation notes and known limits.
