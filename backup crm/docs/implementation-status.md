# Implementation status

BUZNET CRM is a local PHP/MySQL CRM and billing application. The supplied PRD is descriptive product guidance; it does not authorize external services, transmit customer data to an AI provider, or imply that every proposed feature has shipped.

## Implemented

- Owner workspace setup, sign-in, password hashing, CSRF protection, login throttling, session rotation and password changes.
- Single-use password recovery tokens stored as hashes with expiry, generic account response and email link delivery through PHP `mail()`.
- Business-scoped customers, leads and lead conversion, products/services, tasks, quotations, invoices, payments, reversals, expenses and reports.
- Owner and Manager module permissions enforced on server routes.
- Owner JSON backup download and restore preview with workspace match, Owner password confirmation, transaction rollback and a private pre-restore snapshot. Restore preserves current users and user permissions while remapping operational record IDs.
- GST business profile and customer place-of-supply fields; catalog HSN/SAC and configured rate; server-side invoice price/rate lookup; GST Tax Invoice/Bill of Supply/Standard Invoice selection; CGST+SGST or IGST calculation from state codes; locked consecutive financial-year invoice series; saved issue-time snapshots; printable and PDF invoice details.
- Responsive dashboard, searchable in-app guide, server-generated invoice PDFs, audit log and setup guide.

## Configure before use

- Existing databases need migration `005_password_recovery.sql`, then `006_gst_workflow.sql`, each imported once after a database backup.
- Configure PHP mail transport and `BUZNET_APP_URL` / `BUZNET_MAIL_FROM` for account recovery email.
- The Owner must complete business GST registration/state fields; catalog tax rates and customer state data must be accurate.

## Known limits

GST support is a configured invoice-calculation workflow, not a full statutory GST suite. It does not file returns, query GST portal registrations, determine special place-of-supply cases, implement reverse charge, credit/debit notes, e-invoice IRN/QR, e-way bills, tax-rate schedules or a statutory invoice-number policy. Users must verify tax treatment and invoice content with a qualified professional. Email delivery depends on local mail transport. Automated security and financial-integrity test coverage remains limited; complete a controlled backup/restore and invoice review before business use.
