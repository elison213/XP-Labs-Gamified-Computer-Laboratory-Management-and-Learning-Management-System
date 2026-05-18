-- =====================================================
-- XPLabs - Add delayed FK constraints for PC Lab tables
-- Migration: 048_add_pc_lab_foreign_keys.sql
-- Date: 2026-04-29
-- Description: Adds foreign keys that depend on tables
--              created by later core migrations.
-- =====================================================

-- lab_pcs.floor_id -> lab_floors.id
SET @fk_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'lab_pcs'
    AND CONSTRAINT_NAME = 'fk_lab_pcs_floor'
);
SET @sql = IF(
  @fk_exists = 0,
  'ALTER TABLE lab_pcs ADD CONSTRAINT fk_lab_pcs_floor FOREIGN KEY (floor_id) REFERENCES lab_floors(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- lab_pcs.station_id -> lab_stations.id
SET @fk_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'lab_pcs'
    AND CONSTRAINT_NAME = 'fk_lab_pcs_station'
);
SET @sql = IF(
  @fk_exists = 0,
  'ALTER TABLE lab_pcs ADD CONSTRAINT fk_lab_pcs_station FOREIGN KEY (station_id) REFERENCES lab_stations(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- pc_sessions.user_id -> users.id
SET @fk_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pc_sessions'
    AND CONSTRAINT_NAME = 'fk_pc_sessions_user'
);
SET @sql = IF(
  @fk_exists = 0,
  'ALTER TABLE pc_sessions ADD CONSTRAINT fk_pc_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- pc_sessions.pc_id -> lab_pcs.id
SET @fk_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pc_sessions'
    AND CONSTRAINT_NAME = 'fk_pc_sessions_pc'
);
SET @sql = IF(
  @fk_exists = 0,
  'ALTER TABLE pc_sessions ADD CONSTRAINT fk_pc_sessions_pc FOREIGN KEY (pc_id) REFERENCES lab_pcs(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- pc_sessions.station_id -> lab_stations.id
SET @fk_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pc_sessions'
    AND CONSTRAINT_NAME = 'fk_pc_sessions_station'
);
SET @sql = IF(
  @fk_exists = 0,
  'ALTER TABLE pc_sessions ADD CONSTRAINT fk_pc_sessions_station FOREIGN KEY (station_id) REFERENCES lab_stations(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- remote_commands.pc_id -> lab_pcs.id
SET @fk_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'remote_commands'
    AND CONSTRAINT_NAME = 'fk_remote_commands_pc'
);
SET @sql = IF(
  @fk_exists = 0,
  'ALTER TABLE remote_commands ADD CONSTRAINT fk_remote_commands_pc FOREIGN KEY (pc_id) REFERENCES lab_pcs(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- remote_commands.issued_by -> users.id
SET @fk_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'remote_commands'
    AND CONSTRAINT_NAME = 'fk_remote_commands_issued_by'
);
SET @sql = IF(
  @fk_exists = 0,
  'ALTER TABLE remote_commands ADD CONSTRAINT fk_remote_commands_issued_by FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- folder_access_rules.floor_id -> lab_floors.id
SET @fk_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'folder_access_rules'
    AND CONSTRAINT_NAME = 'fk_folder_access_rules_floor'
);
SET @sql = IF(
  @fk_exists = 0,
  'ALTER TABLE folder_access_rules ADD CONSTRAINT fk_folder_access_rules_floor FOREIGN KEY (floor_id) REFERENCES lab_floors(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

