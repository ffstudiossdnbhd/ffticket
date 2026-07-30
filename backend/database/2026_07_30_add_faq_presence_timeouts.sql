-- Run this migration once against an existing FFTicket database after taking a backup.

ALTER TABLE users
  ADD COLUMN last_seen_at DATETIME NULL AFTER updated_at,
  ADD COLUMN timeout_until DATETIME NULL AFTER last_seen_at,
  ADD COLUMN timeout_effective_at DATETIME NULL AFTER timeout_until,
  ADD KEY idx_users_timeout (timeout_until, timeout_effective_at),
  ADD KEY idx_users_last_seen (last_seen_at);

CREATE TABLE faqs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_faqs_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_comment_reads (
  ticket_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  last_read_comment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  read_at DATETIME NOT NULL,
  PRIMARY KEY (ticket_id, user_id),
  KEY idx_ticket_comment_reads_user (user_id, ticket_id),
  CONSTRAINT fk_ticket_comment_reads_ticket
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_comment_reads_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_collaboration_presence (
  ticket_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  client_id VARCHAR(64) NOT NULL,
  mode ENUM('viewing', 'editing') NOT NULL DEFAULT 'viewing',
  last_seen_at DATETIME NOT NULL,
  PRIMARY KEY (ticket_id, user_id, client_id),
  KEY idx_ticket_presence_active (ticket_id, last_seen_at),
  CONSTRAINT fk_ticket_presence_ticket
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_presence_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
