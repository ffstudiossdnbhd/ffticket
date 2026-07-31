ALTER TABLE faqs
  ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER description,
  ADD KEY idx_faqs_category_id (category_id),
  ADD CONSTRAINT fk_faqs_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;
  