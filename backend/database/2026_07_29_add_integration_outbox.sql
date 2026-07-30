USE u971957807_ffticket;

CREATE TABLE integration_outbox (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id CHAR(36) NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  ticket_id BIGINT UNSIGNED NOT NULL,
  payload JSON NOT NULL,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  delivered_at TIMESTAMP NULL DEFAULT NULL,
  last_error VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_integration_outbox_event_id (event_id),
  KEY idx_integration_outbox_delivery (delivered_at, next_attempt_at),
  KEY idx_integration_outbox_ticket (ticket_id, created_at),
  CONSTRAINT fk_integration_outbox_ticket
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
