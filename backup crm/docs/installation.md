# BUZNET CRM setup

This workspace follows the supplied PRD's PHP, MySQL, server-rendered HTML, vanilla JavaScript and Chart.js direction. The PRD is product guidance; it is not a command to expose secrets or perform destructive operations.

## Local installation

1. Install PHP 8.1+ with the PDO MySQL extension and MySQL/MariaDB (XAMPP includes both).
2. Create a database named `buznet_crm` (or set `BUZNET_DB_NAME`).
3. Import `database/schema.sql` using phpMyAdmin or the MySQL client.
4. Configure `BUZNET_DB_HOST`, `BUZNET_DB_NAME`, `BUZNET_DB_USER`, and `BUZNET_DB_PASS` in the Apache/PHP environment. Defaults are for a local XAMPP database only. The database needs the `user_permissions` table from `schema.sql` for Manager access.
5. Open `/setup.php` once to create the business and Owner account. Use a unique password of at least 12 characters. Then sign in at `/login.php`.

The setup route becomes unavailable once an Owner exists. Do not deploy with a database account that has global administrative privileges. Use HTTPS in production and keep storage outside the public document root.

### Upgrade an existing installation

To add quotations and Manager permissions to a database created before this feature, import `database/migrations/002_quotations.sql`. It creates the quotation header, line-item and Manager permission tables, and retains current invoice/customer records.

To add lead-to-customer conversion tracking to that existing database, also import `database/migrations/003_lead_conversion.sql`.

For payment reversal audit fields on an existing database, import `database/migrations/004_payment_reversals.sql`. New installations already include these columns in `schema.sql`.

For password recovery and GST-profile invoices on an existing database, first export a database backup in phpMyAdmin, then import `database/migrations/005_password_recovery.sql` followed by `database/migrations/006_gst_workflow.sql`, once each. Select the `buznet_crm` database before using Import. Set `BUZNET_APP_URL` to the full application base URL and configure PHP `mail()`/XAMPP Sendmail or a local SMTP relay plus `BUZNET_MAIL_FROM` for password recovery email delivery. Without mail transport configuration, reset links cannot reach the user's inbox.

After migration, the Owner should complete the business legal name, address, GST registration type, GSTIN, state and GST state code in Settings. Add each catalog item's HSN/SAC and assigned tax rate, and each customer's GSTIN/address and place-of-supply state/code. New invoice numbers use a locked consecutive series per Indian financial year. New GST invoices use catalog values from the server and split tax into CGST+SGST when supplier and place-of-supply state codes match, otherwise IGST. Composition and unregistered profiles create non-taxed documents. Review every rate and document with a qualified tax professional before use. The application does not file returns, validate GSTIN against the GST portal, determine complex place-of-supply exceptions, handle reverse-charge/credit-note/e-invoice/e-way-bill rules, or guarantee statutory compliance.

## Current delivery

The current implementation includes Owner provisioning, shared secure login, session/CSRF helpers, activity log, responsive navigation, live dashboard queries and revenue chart, customer/lead/catalog/task workflows, invoicing with GST split and server-generated PDF, payment reversals, expenses, reports, quotations with invoice conversion, Owner-managed Manager module permissions, JSON business backup and protected restore, single-use email password recovery and an in-app user guide. Email delivery requires local PHP mail/SMTP configuration. GST implementation is an invoice calculation workflow, not a statutory filing or compliance system. Provider-backed AI and seeded demo data remain future implementation work.
