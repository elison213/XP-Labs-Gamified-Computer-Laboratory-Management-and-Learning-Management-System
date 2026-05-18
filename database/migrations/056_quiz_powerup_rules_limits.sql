-- XPLabs Migration 056: Add per-quiz max usage override for powerups
ALTER TABLE quiz_powerup_rules
    ADD COLUMN IF NOT EXISTS max_uses_per_attempt INT NULL AFTER is_enabled;

