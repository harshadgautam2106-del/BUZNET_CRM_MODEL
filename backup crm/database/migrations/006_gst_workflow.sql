ALTER TABLE businesses
  ADD COLUMN legal_name VARCHAR(190) NULL AFTER currency,
  ADD COLUMN gst_registration_type ENUM('Regular','Composition','Unregistered') NOT NULL DEFAULT 'Unregistered' AFTER legal_name,
  ADD COLUMN gstin VARCHAR(15) NULL AFTER gst_registration_type,
  ADD COLUMN address VARCHAR(255) NULL AFTER gstin,
  ADD COLUMN city VARCHAR(100) NULL AFTER address,
  ADD COLUMN state VARCHAR(100) NULL AFTER city,
  ADD COLUMN state_code CHAR(2) NULL AFTER state,
  ADD COLUMN postal_code VARCHAR(20) NULL AFTER state_code;

ALTER TABLE customers
  ADD COLUMN address VARCHAR(255) NULL AFTER gstin,
  ADD COLUMN state VARCHAR(100) NULL AFTER address,
  ADD COLUMN state_code CHAR(2) NULL AFTER state,
  ADD COLUMN postal_code VARCHAR(20) NULL AFTER state_code;

ALTER TABLE products ADD COLUMN hsn_sac VARCHAR(20) NULL AFTER sku;

ALTER TABLE invoices
  ADD COLUMN document_type ENUM('Standard Invoice','GST Tax Invoice','Bill of Supply') NOT NULL DEFAULT 'Standard Invoice' AFTER status,
  ADD COLUMN place_of_supply_state VARCHAR(100) NULL AFTER document_type,
  ADD COLUMN place_of_supply_code CHAR(2) NULL AFTER place_of_supply_state,
  ADD COLUMN reverse_charge TINYINT(1) NOT NULL DEFAULT 0 AFTER place_of_supply_code;

ALTER TABLE invoice_items
  ADD COLUMN product_id BIGINT UNSIGNED NULL AFTER invoice_id,
  ADD COLUMN hsn_sac VARCHAR(20) NULL AFTER description,
  ADD COLUMN cgst_rate DECIMAL(6,3) NOT NULL DEFAULT 0 AFTER tax_rate,
  ADD COLUMN sgst_rate DECIMAL(6,3) NOT NULL DEFAULT 0 AFTER cgst_rate,
  ADD COLUMN igst_rate DECIMAL(6,3) NOT NULL DEFAULT 0 AFTER sgst_rate,
  ADD COLUMN cgst_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER line_tax,
  ADD COLUMN sgst_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER cgst_amount,
  ADD COLUMN igst_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER sgst_amount,
  ADD CONSTRAINT fk_invoice_items_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL;
