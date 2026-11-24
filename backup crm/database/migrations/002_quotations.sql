CREATE TABLE IF NOT EXISTS quotations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  quotation_number VARCHAR(60) NOT NULL,
  quotation_date DATE NOT NULL,
  valid_until DATE NULL,
  status ENUM('Draft','Sent','Accepted','Rejected','Expired','Converted') NOT NULL DEFAULT 'Draft',
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  customer_snapshot JSON NULL,
  business_snapshot JSON NULL,
  notes TEXT NULL,
  invoice_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_quotation_number(business_id,quotation_number),
  INDEX(business_id,status),
  CONSTRAINT fk_quotations_business FOREIGN KEY(business_id) REFERENCES businesses(id),
  CONSTRAINT fk_quotations_customer FOREIGN KEY(customer_id) REFERENCES customers(id),
  CONSTRAINT fk_quotations_invoice FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quotation_id BIGINT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  unit VARCHAR(40) NULL,
  unit_price DECIMAL(14,2) NOT NULL,
  tax_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
  line_subtotal DECIMAL(14,2) NOT NULL,
  line_tax DECIMAL(14,2) NOT NULL,
  line_total DECIMAL(14,2) NOT NULL,
  CONSTRAINT fk_quotation_items_quotation FOREIGN KEY(quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_permissions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,permission_key VARCHAR(100) NOT NULL,granted TINYINT(1) NOT NULL DEFAULT 0,UNIQUE KEY uq_user_permission(user_id,permission_key),CONSTRAINT fk_user_permissions_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
