ALTER TABLE leads
  ADD COLUMN converted_customer_id BIGINT UNSIGNED NULL AFTER notes,
  ADD COLUMN converted_at DATETIME NULL AFTER converted_customer_id,
  ADD INDEX idx_leads_converted_customer(converted_customer_id),
  ADD CONSTRAINT fk_leads_converted_customer FOREIGN KEY(converted_customer_id) REFERENCES customers(id) ON DELETE SET NULL;
