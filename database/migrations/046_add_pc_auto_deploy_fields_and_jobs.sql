-- Migration 046: Auto-deployment policy and job tracking for lab PCs

ALTER TABLE lab_pcs
    ADD COLUMN IF NOT EXISTS auto_deploy_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER assignment_status,
    ADD COLUMN IF NOT EXISTS deployment_status ENUM('pending','in_progress','installed','failed','excluded') NULL AFTER auto_deploy_enabled,
    ADD COLUMN IF NOT EXISTS deployment_reason VARCHAR(255) NULL AFTER deployment_status,
    ADD COLUMN IF NOT EXISTS deployment_tag VARCHAR(100) NULL AFTER deployment_reason,
    ADD COLUMN IF NOT EXISTS last_deploy_attempt_at DATETIME NULL AFTER deployment_tag,
    ADD COLUMN IF NOT EXISTS last_deploy_error TEXT NULL AFTER last_deploy_attempt_at;

UPDATE lab_pcs
SET deployment_status = CASE
    WHEN auto_deploy_enabled = 0 THEN 'excluded'
    WHEN assignment_status = 'assigned' THEN 'pending'
    WHEN assignment_status = 'unassigned' THEN 'pending'
    ELSE 'pending'
END
WHERE deployment_status IS NULL;

CREATE TABLE IF NOT EXISTS pc_deployment_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pc_id INT NOT NULL,
    status ENUM('queued','in_progress','success','failed','cancelled') NOT NULL DEFAULT 'queued',
    trigger_type ENUM('auto','manual','retry') NOT NULL DEFAULT 'auto',
    created_by INT NULL,
    request_payload JSON NULL,
    result_payload JSON NULL,
    runner_log TEXT NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pc_deploy_jobs_pc FOREIGN KEY (pc_id) REFERENCES lab_pcs(id) ON DELETE CASCADE,
    CONSTRAINT fk_pc_deploy_jobs_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_pc_deployment_jobs_status (status),
    INDEX idx_pc_deployment_jobs_pc_id (pc_id),
    INDEX idx_pc_deployment_jobs_created_at (created_at)
);
