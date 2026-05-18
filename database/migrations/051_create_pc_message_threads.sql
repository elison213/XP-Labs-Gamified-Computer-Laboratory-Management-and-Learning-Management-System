-- PC direct messaging: instructor-initiated threads to lab PCs, students reply via agent.
CREATE TABLE IF NOT EXISTS pc_message_threads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pc_id INT NOT NULL,
    station_id INT NULL,
    started_by_user_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    INDEX idx_pc_started (pc_id, started_at),
    INDEX idx_started_by (started_by_user_id),
    CONSTRAINT fk_pc_msg_thread_pc FOREIGN KEY (pc_id) REFERENCES lab_pcs(id) ON DELETE CASCADE,
    CONSTRAINT fk_pc_msg_thread_user FOREIGN KEY (started_by_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pc_message_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id INT UNSIGNED NOT NULL,
    sender_user_id INT NULL,
    sender_role ENUM('instructor', 'student', 'system') NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_thread_created (thread_id, created_at),
    CONSTRAINT fk_pc_msg_msg_thread FOREIGN KEY (thread_id) REFERENCES pc_message_threads(id) ON DELETE CASCADE,
    CONSTRAINT fk_pc_msg_msg_user FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
