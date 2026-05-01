-- Migration 050: Protocol debug events for heartbeat and command flows

CREATE TABLE IF NOT EXISTS pc_protocol_debug_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    pc_id INT NULL,
    event_type VARCHAR(64) NOT NULL,
    severity ENUM('debug','info','warn','error') NOT NULL DEFAULT 'info',
    event_payload LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pc_proto_dbg_pc_created (pc_id, created_at),
    INDEX idx_pc_proto_dbg_type_created (event_type, created_at),
    CONSTRAINT fk_pc_proto_dbg_pc FOREIGN KEY (pc_id) REFERENCES lab_pcs(id) ON DELETE SET NULL
);

