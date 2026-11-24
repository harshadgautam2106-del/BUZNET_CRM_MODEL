# Database

Import `schema.sql` into a new MySQL/MariaDB database, then open the application setup page. The schema creates the initial workspace, account, CRM, billing, payment, expense, quotation, activity-log and Manager-permission tables. There is no demo seed by default, so empty databases never display fabricated business metrics.

Existing installations created before quotations and Manager permissions should also import `migrations/002_quotations.sql`. Import `migrations/003_lead_conversion.sql` to add lead-to-customer conversion tracking and `migrations/004_payment_reversals.sql` to add reversal attribution fields. For recovery and GST profile/invoice columns, import `migrations/005_password_recovery.sql` and then `migrations/006_gst_workflow.sql` into `buznet_crm` in phpMyAdmin. Back up the database first. Do not re-import a migration already applied: these upgrade scripts add columns and are intended to run once.
