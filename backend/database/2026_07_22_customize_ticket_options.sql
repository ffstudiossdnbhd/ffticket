USE u971957807_ffticket;

ALTER TABLE categories
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER description,
  ADD KEY idx_categories_is_active (is_active);

UPDATE categories SET is_active = 1;

CREATE TABLE urgency_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_urgency_types_name (name),
  KEY idx_urgency_types_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE locations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_locations_name (name),
  KEY idx_locations_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO urgency_types (name, description, is_active) VALUES
  ('Low', 'Low business impact', 1),
  ('Medium', 'Moderate business impact', 1),
  ('High', 'High business impact', 1),
  ('Critical', 'Critical business impact', 1)
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO locations (name, description, is_active) VALUES
  ('Head Office', 'Main office location', 1),
  ('Branch Office', 'Branch office location', 1),
  ('Remote/Home', 'Remote or home-based work location', 1),
  ('Other', 'Other active location', 1),
  ('Unspecified', 'Backfill value for tickets created before locations were required', 0)
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

ALTER TABLE tickets
  ADD COLUMN urgency_type_id BIGINT UNSIGNED NULL AFTER category_id,
  ADD COLUMN location_id BIGINT UNSIGNED NULL AFTER urgency_type_id;

UPDATE tickets t
INNER JOIN urgency_types u ON u.name = t.urgency
SET t.urgency_type_id = u.id;

UPDATE tickets
SET urgency_type_id = (SELECT id FROM urgency_types WHERE name = 'Medium' LIMIT 1)
WHERE urgency_type_id IS NULL;

UPDATE tickets
SET location_id = (SELECT id FROM locations WHERE name = 'Unspecified' LIMIT 1)
WHERE location_id IS NULL;

ALTER TABLE tickets
  MODIFY urgency_type_id BIGINT UNSIGNED NOT NULL,
  MODIFY location_id BIGINT UNSIGNED NOT NULL;

ALTER TABLE tickets
  DROP INDEX idx_tickets_urgency,
  DROP COLUMN urgency,
  ADD KEY idx_tickets_urgency_type_id (urgency_type_id),
  ADD KEY idx_tickets_location_id (location_id),
  ADD CONSTRAINT fk_tickets_urgency_type FOREIGN KEY (urgency_type_id) REFERENCES urgency_types(id) ON DELETE RESTRICT,
  ADD CONSTRAINT fk_tickets_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT;

ALTER TABLE ticket_comments
  MODIFY visibility ENUM('internal', 'public') NOT NULL DEFAULT 'public';

UPDATE ticket_comments SET visibility = 'public' WHERE visibility = 'internal';

ALTER TABLE ticket_comments
  MODIFY visibility ENUM('public') NOT NULL DEFAULT 'public';
