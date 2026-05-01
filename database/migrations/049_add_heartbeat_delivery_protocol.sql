-- Migration 049: Idempotent heartbeat receipts and delivery cursors

ALTER TABLE lab_pcs
    ADD COLUMN IF NOT EXISTS last_heartbeat_ack_id BIGINT NULL AFTER last_heartbeat,
    ADD COLUMN IF NOT EXISTS last_command_cursor BIGINT NULL AFTER last_heartbeat_ack_id;

CREATE TABLE IF NOT EXISTS pc_heartbeat_receipts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    pc_id INT NOT NULL,
    heartbeat_id VARCHAR(128) NOT NULL,
    command_cursor BIGINT NOT NULL DEFAULT 0,
    response_json LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pc_hb_receipts_pc FOREIGN KEY (pc_id) REFERENCES lab_pcs(id) ON DELETE CASCADE,
    UNIQUE KEY uq_pc_hb_receipt (pc_id, heartbeat_id),
    INDEX idx_pc_hb_receipts_pc_created (pc_id, created_at)
);

