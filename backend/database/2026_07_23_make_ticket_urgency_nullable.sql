ALTER TABLE tickets
  DROP FOREIGN KEY fk_tickets_urgency_type;

ALTER TABLE tickets
  MODIFY urgency_type_id BIGINT UNSIGNED NULL;

ALTER TABLE tickets
  ADD CONSTRAINT fk_tickets_urgency_type
  FOREIGN KEY (urgency_type_id) REFERENCES urgency_types(id) ON DELETE RESTRICT;
