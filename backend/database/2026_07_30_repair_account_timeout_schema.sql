-- Safe forward-only repair for deployments where the FAQ/presence/timeout migration
-- was only partially applied. This migration is additive and can be run more than once.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS last_seen_at DATETIME NULL AFTER updated_at,
  ADD COLUMN IF NOT EXISTS timeout_until DATETIME NULL AFTER last_seen_at,
  ADD COLUMN IF NOT EXISTS timeout_effective_at DATETIME NULL AFTER timeout_until,
  ADD INDEX IF NOT EXISTS idx_users_timeout (timeout_until, timeout_effective_at),
  ADD INDEX IF NOT EXISTS idx_users_last_seen (last_seen_at);
