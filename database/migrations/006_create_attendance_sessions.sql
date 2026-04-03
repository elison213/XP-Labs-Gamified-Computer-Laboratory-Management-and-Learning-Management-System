-- XPLabs Migration 006: Attendance sessions table
-- Clock-in/out records via QR kiosk

CREATE TABLE IF NOT EXISTS attendance_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    station_id INT DEFAULT NULL,
    floor_id INT DEFAULT NULL,
    clock_in TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    clock_out TIMESTAMP NULL DEFAULT NULL,
    status ENUM('active', 'completed', 'abandoned') DEFAULT 'active',
    qr_scan_method ENUM('lrn_card', 'dynamic_qr', 'manual') DEFAULT 'lrn_card',
    kiosk_ip VARCHAR(15) DEFAULT NULL COMMENT 'IP of the tablet/kiosk that scanned',
    duration_minutes INT GENERATED ALWAYS AS (
        CASE
            WHEN clock_out IS NOT NULL
            THEN TIMESTAMPDIFF(MINUTE, clock_in, clock_out)
            ELSE TIMESTAMPDIFF(MINUTE, clock_in, NOW())
        END
    ) VIRTUAL,

    INDEX idx_user_date (user_id, clock_in),
    INDEX idx_floor_active (floor_id, status),
    INDEX idx_active (status, clock_in),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES lab_stations(id) ON DELETE SET NULL,
    FOREIGN KEY (floor_id) REFERENCES lab_floors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;