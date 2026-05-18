ALTER TABLE lab_pcs
    ADD COLUMN IF NOT EXISTS assignment_status ENUM('unassigned', 'assigned') NOT NULL DEFAULT 'unassigned' AFTER machine_key,
    ADD COLUMN IF NOT EXISTS discovery_source VARCHAR(30) NULL AFTER assignment_status,
    ADD COLUMN IF NOT EXISTS discovered_at DATETIME NULL AFTER discovery_source,
    ADD COLUMN IF NOT EXISTS last_seen_at DATETIME NULL AFTER discovered_at;

UPDATE lab_pcs
SET assignment_status = CASE
    WHEN floor_id IS NOT NULL OR station_id IS NOT NULL THEN 'assigned'
    ELSE 'unassigned'
END,
discovery_source = COALESCE(discovery_source, 'agent'),
discovered_at = COALESCE(discovered_at, created_at),
last_seen_at = COALESCE(last_seen_at, last_heartbeat, updated_at);
