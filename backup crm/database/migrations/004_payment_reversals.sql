ALTER TABLE payments
  ADD COLUMN reversed_by BIGINT UNSIGNED NULL AFTER reversed_at,
  ADD COLUMN reversal_note VARCHAR(500) NULL AFTER reversed_by,
  ADD CONSTRAINT fk_payments_reversed_by FOREIGN KEY(reversed_by) REFERENCES users(id) ON DELETE SET NULL;
