-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: xplabs
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `_migrations`
--

DROP TABLE IF EXISTS `_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `_migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `_migrations`
--

LOCK TABLES `_migrations` WRITE;
/*!40000 ALTER TABLE `_migrations` DISABLE KEYS */;
INSERT INTO `_migrations` VALUES (1,'001_create_users.sql','2026-04-03 04:52:38'),(2,'002_create_courses.sql','2026-04-03 04:52:39'),(3,'003_create_course_enrollments.sql','2026-04-03 04:52:39'),(4,'004_create_lab_floors.sql','2026-04-03 04:54:20'),(5,'005_create_lab_stations.sql','2026-04-03 04:54:20'),(6,'006_create_attendance_sessions.sql','2026-04-03 04:54:20'),(7,'007_create_station_assignments.sql','2026-04-03 04:54:20'),(8,'008_create_assignments.sql','2026-04-03 04:54:20'),(9,'009_create_submissions.sql','2026-04-03 04:54:20'),(10,'010_create_quizzes.sql','2026-04-03 04:54:20'),(11,'011_create_quiz_questions.sql','2026-04-03 04:54:20'),(12,'012_create_quiz_attempts.sql','2026-04-03 04:54:20'),(13,'013_create_quiz_answers.sql','2026-04-03 04:54:20'),(14,'014_create_question_bank.sql','2026-04-03 04:54:20'),(15,'015_create_user_points.sql','2026-04-03 04:54:20'),(16,'016_create_achievements.sql','2026-04-03 04:54:20'),(17,'017_create_powerups.sql','2026-04-03 04:54:20'),(18,'018_create_rewards.sql','2026-04-03 04:54:20'),(19,'019_create_notifications.sql','2026-04-03 04:54:20'),(20,'020_create_admin_logs.sql','2026-04-03 04:54:20'),(21,'021_create_sync_out_queue.sql','2026-04-03 04:54:20'),(22,'022_create_announcements.sql','2026-04-03 04:54:20'),(23,'023_create_import_batches.sql','2026-04-03 04:54:20'),(24,'024_create_leaderboard_cache.sql','2026-04-03 04:54:20'),(25,'025_create_user_point_balances.sql','2026-04-03 04:54:20'),(26,'026_create_question_tags.sql','2026-04-03 04:54:20'),(27,'027_create_powerup_usage.sql','2026-04-03 04:54:20'),(28,'028_create_quiz_powerup_activations.sql','2026-04-03 04:54:20'),(29,'029_create_redeemed_rewards.sql','2026-04-03 04:54:20'),(30,'030_create_user_achievements.sql','2026-04-03 04:54:20'),(31,'031_seed_data.sql','2026-04-03 04:56:38'),(32,'032_create_triggers.sql','2026-04-03 04:57:32'),(33,'033_create_labs.sql','2026-04-03 07:09:54'),(34,'034_create_incidents.sql','2026-04-03 08:48:14'),(35,'035_create_inventory.sql','2026-04-03 08:48:15'),(36,'036_add_announcements_fields.sql','2026-04-03 08:58:23'),(37,'037_create_activity_feedback.sql','2026-04-03 10:33:09'),(38,'038_create_point_awards.sql','2026-04-03 10:33:09'),(39,'001_pc_lab_management.sql','2026-04-11 09:49:42'),(40,'039_create_ad_tables.sql','2026-04-11 09:50:55');
/*!40000 ALTER TABLE `_migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `achievements`
--

DROP TABLE IF EXISTS `achievements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `achievements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL COMMENT 'emoji or icon class',
  `points_reward` int(11) DEFAULT 0,
  `criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '{"type":"attendance_streak","value":10}' CHECK (json_valid(`criteria`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `achievements`
--

LOCK TABLES `achievements` WRITE;
/*!40000 ALTER TABLE `achievements` DISABLE KEYS */;
INSERT INTO `achievements` VALUES (1,'first_login','First Steps','Logged in for the first time','≡ƒÄë',10,'{\"type\":\"first_login\"}',1,'2026-04-03 04:54:20'),(2,'attendance_5','On a Roll','5 consecutive days of attendance','≡ƒöÑ',25,'{\"type\":\"attendance_streak\",\"value\":5}',1,'2026-04-03 04:54:20'),(3,'attendance_10','Week Warrior','10 consecutive days of attendance','ΓÜö∩╕Å',50,'{\"type\":\"attendance_streak\",\"value\":10}',1,'2026-04-03 04:54:20'),(4,'attendance_30','Monthly Champion','30 consecutive days of attendance','≡ƒÅå',100,'{\"type\":\"attendance_streak\",\"value\":30}',1,'2026-04-03 04:54:20'),(5,'quiz_perfect','Perfect Score','Got 100% on a quiz','≡ƒÆ»',30,'{\"type\":\"quiz_perfect\"}',1,'2026-04-03 04:54:20'),(6,'quiz_speed','Speed Demon','Completed a quiz in under 2 minutes','ΓÜí',20,'{\"type\":\"quiz_speed\",\"max_seconds\":120}',1,'2026-04-03 04:54:20'),(7,'assignment_early','Eager Beaver','Submitted an assignment 2+ days early','≡ƒÉ¥',15,'{\"type\":\"assignment_early\",\"days_before\":2}',1,'2026-04-03 04:54:20'),(8,'top_3','Rising Star','Ranked in top 3 on the leaderboard','Γ¡É',40,'{\"type\":\"leaderboard_top\",\"rank\":3}',1,'2026-04-03 04:54:20'),(9,'points_500','Point Collector','Accumulated 500 total points','≡ƒÆÄ',50,'{\"type\":\"total_points\",\"value\":500}',1,'2026-04-03 04:54:20'),(10,'points_1000','Point Master','Accumulated 1000 total points','≡ƒææ',100,'{\"type\":\"total_points\",\"value\":1000}',1,'2026-04-03 04:54:20');
/*!40000 ALTER TABLE `achievements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_feedback`
--

DROP TABLE IF EXISTS `activity_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activity_type` enum('assignment','quiz','lab_session') NOT NULL,
  `activity_id` int(11) NOT NULL,
  `fun_rating` tinyint(4) NOT NULL COMMENT '1-5 stars rating for how fun the activity was',
  `difficulty` enum('easy','medium','hard') NOT NULL,
  `feedback` text DEFAULT NULL COMMENT 'Optional text feedback from student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_activity` (`activity_type`,`activity_id`),
  CONSTRAINT `activity_feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_feedback`
--

LOCK TABLES `activity_feedback` WRITE;
/*!40000 ALTER TABLE `activity_feedback` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_feedback` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ad_computer_metrics`
--

DROP TABLE IF EXISTS `ad_computer_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ad_computer_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `computer_id` int(11) NOT NULL,
  `cpu_usage` int(11) DEFAULT NULL,
  `memory_free` bigint(20) DEFAULT NULL,
  `disk_free` bigint(20) DEFAULT NULL,
  `polled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `computer_id` (`computer_id`),
  CONSTRAINT `ad_computer_metrics_ibfk_1` FOREIGN KEY (`computer_id`) REFERENCES `ad_computers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ad_computer_metrics`
--

LOCK TABLES `ad_computer_metrics` WRITE;
/*!40000 ALTER TABLE `ad_computer_metrics` DISABLE KEYS */;
/*!40000 ALTER TABLE `ad_computer_metrics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ad_computers`
--

DROP TABLE IF EXISTS `ad_computers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ad_computers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ad_dn` varchar(255) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `os` varchar(255) DEFAULT NULL,
  `last_logon` bigint(20) DEFAULT NULL,
  `last_sync` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ad_dn` (`ad_dn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ad_computers`
--

LOCK TABLES `ad_computers` WRITE;
/*!40000 ALTER TABLE `ad_computers` DISABLE KEYS */;
/*!40000 ALTER TABLE `ad_computers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ad_groups`
--

DROP TABLE IF EXISTS `ad_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ad_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ad_dn` varchar(255) NOT NULL,
  `cn` varchar(255) NOT NULL,
  `last_sync` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ad_dn` (`ad_dn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ad_groups`
--

LOCK TABLES `ad_groups` WRITE;
/*!40000 ALTER TABLE `ad_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `ad_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ad_users`
--

DROP TABLE IF EXISTS `ad_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ad_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ad_dn` varchar(255) NOT NULL,
  `samaccountname` varchar(255) NOT NULL,
  `displayname` varchar(255) DEFAULT NULL,
  `last_sync` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ad_dn` (`ad_dn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ad_users`
--

LOCK TABLES `ad_users` WRITE;
/*!40000 ALTER TABLE `ad_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `ad_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_logs`
--

DROP TABLE IF EXISTS `admin_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL COMMENT 'import_csv, update_station, lock_pc, create_quiz',
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Before/after, IP, etc.' CHECK (json_valid(`details`)),
  `ip_address` varchar(15) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`,`created_at`),
  KEY `idx_user` (`user_id`,`created_at`),
  CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_logs`
--

LOCK TABLES `admin_logs` WRITE;
/*!40000 ALTER TABLE `admin_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `content` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `publish_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_pinned` tinyint(1) DEFAULT 0,
  `target_audience` enum('all','students','teachers') DEFAULT 'all',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_course` (`course_id`,`is_active`),
  KEY `idx_publish` (`publish_at`,`is_active`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `announcements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
INSERT INTO `announcements` VALUES (1,NULL,'jORDAN','','WAG KUMAIN SA CLASSROOM\r\n',100,'normal',NULL,NULL,1,0,'all','2026-04-14 15:50:22');
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignments`
--

DROP TABLE IF EXISTS `assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `due_date` datetime DEFAULT NULL,
  `max_points` int(11) DEFAULT 100,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `attachment_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_course` (`course_id`,`status`),
  KEY `idx_due` (`due_date`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignments`
--

LOCK TABLES `assignments` WRITE;
/*!40000 ALTER TABLE `assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_sessions`
--

DROP TABLE IF EXISTS `attendance_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `station_id` int(11) DEFAULT NULL,
  `floor_id` int(11) DEFAULT NULL,
  `clock_in` timestamp NOT NULL DEFAULT current_timestamp(),
  `clock_out` timestamp NULL DEFAULT NULL,
  `status` enum('active','completed','abandoned') DEFAULT 'active',
  `qr_scan_method` enum('lrn_card','dynamic_qr','manual') DEFAULT 'lrn_card',
  `kiosk_ip` varchar(15) DEFAULT NULL COMMENT 'IP of the tablet/kiosk that scanned',
  `duration_minutes` int(11) GENERATED ALWAYS AS (case when `clock_out` is not null then timestampdiff(MINUTE,`clock_in`,`clock_out`) else timestampdiff(MINUTE,`clock_in`,current_timestamp()) end) VIRTUAL,
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`,`clock_in`),
  KEY `idx_floor_active` (`floor_id`,`status`),
  KEY `idx_active` (`status`,`clock_in`),
  KEY `station_id` (`station_id`),
  CONSTRAINT `attendance_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_sessions_ibfk_2` FOREIGN KEY (`station_id`) REFERENCES `lab_stations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_sessions_ibfk_3` FOREIGN KEY (`floor_id`) REFERENCES `lab_floors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_sessions`
--

LOCK TABLES `attendance_sessions` WRITE;
/*!40000 ALTER TABLE `attendance_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_enrollments`
--

DROP TABLE IF EXISTS `course_enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `enrolled_by` int(11) DEFAULT NULL COMMENT 'Teacher who enrolled (NULL = admin/import)',
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('enrolled','dropped','completed') DEFAULT 'enrolled',
  `final_score` decimal(5,2) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_course_user` (`course_id`,`user_id`),
  KEY `enrolled_by` (`enrolled_by`),
  KEY `idx_user` (`user_id`,`status`),
  KEY `idx_course` (`course_id`,`status`),
  CONSTRAINT `course_enrollments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_enrollments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_enrollments_ibfk_3` FOREIGN KEY (`enrolled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_enrollments`
--

LOCK TABLES `course_enrollments` WRITE;
/*!40000 ALTER TABLE `course_enrollments` DISABLE KEYS */;
INSERT INTO `course_enrollments` VALUES (1,200,102,100,'2026-04-14 11:59:42','enrolled',NULL,NULL);
/*!40000 ALTER TABLE `course_enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_lessons`
--

DROP TABLE IF EXISTS `course_lessons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_lessons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `attachment_url` varchar(500) DEFAULT NULL,
  `posted_by` int(11) NOT NULL,
  `published_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `posted_by` (`posted_by`),
  CONSTRAINT `course_lessons_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_lessons_ibfk_2` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_lessons`
--

LOCK TABLES `course_lessons` WRITE;
/*!40000 ALTER TABLE `course_lessons` DISABLE KEYS */;
INSERT INTO `course_lessons` VALUES (1,200,'awesome','content','uploads/lessons/lesson_69de36242edb98.34329752.pdf',100,'2026-04-14 12:42:12','2026-04-14 12:42:12','2026-04-14 12:42:12');
/*!40000 ALTER TABLE `course_lessons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL COMMENT 'Course code e.g., WEBDEV-7N',
  `name` varchar(150) NOT NULL COMMENT 'Full name e.g., Web Development - Grade 7 Newton',
  `subject` enum('computer_programming','web_development','visual_graphics','it_fundamentals','cs_concepts','other') NOT NULL,
  `description` text DEFAULT NULL,
  `teacher_id` int(11) NOT NULL,
  `target_grade` varchar(10) DEFAULT NULL,
  `target_section` varchar(20) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL COMMENT 'e.g., 2024-2025',
  `semester` varchar(20) DEFAULT NULL,
  `status` enum('active','archived','draft') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  UNIQUE KEY `unique_teacher_section` (`teacher_id`,`target_section`,`academic_year`),
  KEY `idx_subject` (`subject`),
  KEY `idx_status` (`status`),
  KEY `idx_teacher` (`teacher_id`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=201 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (200,'CS101','Computer Science 101','computer_programming',NULL,101,NULL,NULL,NULL,NULL,'active','2026-04-03 04:58:32','2026-04-03 04:58:32');
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `drive_mappings`
--

DROP TABLE IF EXISTS `drive_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `drive_mappings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` varchar(50) NOT NULL COMMENT 'User role: student, teacher, admin',
  `drive_letter` varchar(1) NOT NULL,
  `network_path` varchar(255) NOT NULL COMMENT 'UNC path with variables like %USERNAME%',
  `label` varchar(50) DEFAULT NULL COMMENT 'Display label for the drive',
  `is_persistent` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_drive` (`role`,`drive_letter`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drive_mappings`
--

LOCK TABLES `drive_mappings` WRITE;
/*!40000 ALTER TABLE `drive_mappings` DISABLE KEYS */;
INSERT INTO `drive_mappings` VALUES (1,'student','H','\\\\SERVER\\home\\%USERNAME%','Home Drive',0,1,1,'2026-04-11 09:49:42'),(2,'student','S','\\\\SERVER\\shared','Shared Files',0,2,1,'2026-04-11 09:49:42'),(3,'student','L','\\\\SERVER\\lab\\%LABNAME%','Lab Resources',0,3,1,'2026-04-11 09:49:42'),(4,'teacher','H','\\\\SERVER\\home\\%USERNAME%','Home Drive',0,1,1,'2026-04-11 09:49:42'),(5,'teacher','T','\\\\SERVER\\teaching','Teaching Materials',0,2,1,'2026-04-11 09:49:42'),(6,'teacher','L','\\\\SERVER\\lab\\%LABNAME%','Lab Resources',0,3,1,'2026-04-11 09:49:42');
/*!40000 ALTER TABLE `drive_mappings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `folder_access_rules`
--

DROP TABLE IF EXISTS `folder_access_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `folder_access_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `floor_id` int(11) DEFAULT NULL COMMENT 'Applies to specific lab floor, NULL = global',
  `role` varchar(50) NOT NULL COMMENT 'User role: student, teacher, admin',
  `folder_path` varchar(255) NOT NULL COMMENT 'Full folder path or UNC path',
  `permission` enum('read','write','modify','full') DEFAULT 'read',
  `apply_to_group` varchar(100) DEFAULT NULL COMMENT 'Windows group name to apply permissions to',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_floor_role` (`floor_id`,`role`),
  KEY `idx_role` (`role`),
  CONSTRAINT `folder_access_rules_ibfk_1` FOREIGN KEY (`floor_id`) REFERENCES `lab_floors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `folder_access_rules`
--

LOCK TABLES `folder_access_rules` WRITE;
/*!40000 ALTER TABLE `folder_access_rules` DISABLE KEYS */;
INSERT INTO `folder_access_rules` VALUES (1,NULL,'student','C:\\Temp','modify','Students',1,'2026-04-11 09:49:42'),(2,NULL,'student','C:\\Program Files','read','Students',1,'2026-04-11 09:49:42'),(3,NULL,'student','C:\\Windows','read','Students',1,'2026-04-11 09:49:42'),(4,NULL,'teacher','C:\\Temp','full','Teachers',1,'2026-04-11 09:49:42'),(5,NULL,'teacher','C:\\Program Files','read','Teachers',1,'2026-04-11 09:49:42'),(6,NULL,'admin','C:\\','full','Administrators',1,'2026-04-11 09:49:42');
/*!40000 ALTER TABLE `folder_access_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `import_batches`
--

DROP TABLE IF EXISTS `import_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `import_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `imported_by` int(11) NOT NULL,
  `total_rows` int(11) DEFAULT NULL,
  `success_count` int(11) DEFAULT 0,
  `duplicate_count` int(11) DEFAULT 0,
  `error_count` int(11) DEFAULT 0,
  `column_mapping` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '{"LRN": "lrn", "First Name": "first_name"}' CHECK (json_valid(`column_mapping`)),
  `status` enum('processing','completed','partial','failed') DEFAULT 'processing',
  `error_log` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `imported_by` (`imported_by`),
  CONSTRAINT `import_batches_ibfk_1` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `import_batches`
--

LOCK TABLES `import_batches` WRITE;
/*!40000 ALTER TABLE `import_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `import_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incident_logs`
--

DROP TABLE IF EXISTS `incident_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incident_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `incident_id` int(10) unsigned NOT NULL,
  `action` varchar(50) NOT NULL COMMENT 'Action taken',
  `old_value` varchar(255) DEFAULT NULL,
  `new_value` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_incident` (`incident_id`),
  KEY `idx_performed_by` (`performed_by`),
  CONSTRAINT `incident_logs_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `incident_logs_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incident_logs`
--

LOCK TABLES `incident_logs` WRITE;
/*!40000 ALTER TABLE `incident_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `incident_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidents`
--

DROP TABLE IF EXISTS `incidents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL COMMENT 'Type of incident',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('reported','investigating','resolved','dismissed') DEFAULT 'reported',
  `location` varchar(100) DEFAULT NULL COMMENT 'Lab/floor where incident occurred',
  `lab_id` int(11) DEFAULT NULL,
  `floor_id` int(11) DEFAULT NULL,
  `station_id` int(11) DEFAULT NULL,
  `reported_by` int(11) NOT NULL COMMENT 'User who reported the incident',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'User assigned to resolve',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_severity` (`severity`),
  KEY `idx_location` (`lab_id`,`floor_id`),
  KEY `idx_reported_by` (`reported_by`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `incidents_ibfk_1` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidents_ibfk_2` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidents`
--

LOCK TABLES `incidents` WRITE;
/*!40000 ALTER TABLE `incidents` DISABLE KEYS */;
/*!40000 ALTER TABLE `incidents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_items`
--

DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_code` varchar(50) NOT NULL COMMENT 'Unique identifier for the item',
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL COMMENT 'Equipment category',
  `description` text DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `status` enum('available','in_use','reserved','maintenance','damaged','lost') DEFAULT 'available',
  `quantity` int(10) unsigned DEFAULT 1,
  `lab_id` int(11) DEFAULT NULL COMMENT 'Lab where item is located',
  `floor_id` int(11) DEFAULT NULL COMMENT 'Floor where item is located',
  `station_id` int(11) DEFAULT NULL COMMENT 'Station/item assignment',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'User currently using the item',
  `condition_rating` enum('excellent','good','fair','poor') DEFAULT 'good',
  `purchase_date` date DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_code` (`item_code`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_location` (`lab_id`,`floor_id`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_item_code` (`item_code`),
  CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_items`
--

LOCK TABLES `inventory_items` WRITE;
/*!40000 ALTER TABLE `inventory_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_logs`
--

DROP TABLE IF EXISTS `inventory_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned NOT NULL,
  `action` varchar(50) NOT NULL COMMENT 'Action taken (checkout, return, maintenance, etc)',
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_item` (`item_id`),
  KEY `idx_action` (`action`),
  KEY `idx_performed_by` (`performed_by`),
  CONSTRAINT `inventory_logs_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_logs_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_logs`
--

LOCK TABLES `inventory_logs` WRITE;
/*!40000 ALTER TABLE `inventory_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lab_floors`
--

DROP TABLE IF EXISTS `lab_floors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_floors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lab_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL COMMENT 'e.g., Computer Lab 1, CS Lab 2',
  `building` varchar(50) DEFAULT NULL,
  `floor_number` int(11) DEFAULT 1,
  `teacher_id` int(11) DEFAULT NULL,
  `grid_rows` tinyint(4) NOT NULL DEFAULT 4,
  `grid_cols` tinyint(4) NOT NULL DEFAULT 10,
  `total_stations` int(11) GENERATED ALWAYS AS (`grid_rows` * `grid_cols`) STORED,
  `layout_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Grid config: aisle positions, zones, canvas settings' CHECK (json_valid(`layout_config`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_teacher` (`teacher_id`),
  KEY `fk_floor_lab` (`lab_id`),
  CONSTRAINT `fk_floor_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_floors_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=302 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_floors`
--

LOCK TABLES `lab_floors` WRITE;
/*!40000 ALTER TABLE `lab_floors` DISABLE KEYS */;
INSERT INTO `lab_floors` VALUES (300,NULL,'Main Lab',NULL,1,NULL,7,5,35,'[]',1,'2026-04-03 04:58:32'),(301,1,'2nd floor','',2,NULL,5,6,30,'[]',1,'2026-04-16 04:26:12');
/*!40000 ALTER TABLE `lab_floors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lab_pcs`
--

DROP TABLE IF EXISTS `lab_pcs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_pcs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hostname` varchar(100) NOT NULL COMMENT 'Computer hostname',
  `floor_id` int(11) DEFAULT NULL COMMENT 'Links to lab_floors table',
  `station_id` int(11) DEFAULT NULL COMMENT 'Links to lab_stations table',
  `ip_address` varchar(45) DEFAULT NULL,
  `mac_address` varchar(17) DEFAULT NULL,
  `machine_key` varchar(64) NOT NULL COMMENT 'API key for machine authentication',
  `status` enum('online','offline','locked','maintenance','idle') DEFAULT 'offline',
  `last_heartbeat` datetime DEFAULT NULL COMMENT 'Last heartbeat timestamp',
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'PC-specific configuration' CHECK (json_valid(`config`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `hostname` (`hostname`),
  UNIQUE KEY `machine_key` (`machine_key`),
  KEY `floor_id` (`floor_id`),
  KEY `station_id` (`station_id`),
  KEY `idx_hostname` (`hostname`),
  KEY `idx_status` (`status`),
  KEY `idx_heartbeat` (`last_heartbeat`),
  CONSTRAINT `lab_pcs_ibfk_1` FOREIGN KEY (`floor_id`) REFERENCES `lab_floors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_pcs_ibfk_2` FOREIGN KEY (`station_id`) REFERENCES `lab_stations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_pcs`
--

LOCK TABLES `lab_pcs` WRITE;
/*!40000 ALTER TABLE `lab_pcs` DISABLE KEYS */;
/*!40000 ALTER TABLE `lab_pcs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lab_stations`
--

DROP TABLE IF EXISTS `lab_stations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_stations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `floor_id` int(11) NOT NULL,
  `station_code` varchar(10) NOT NULL COMMENT 'e.g., PC-01, A-01',
  `row_label` varchar(5) DEFAULT NULL COMMENT 'A, B, C, D',
  `col_number` tinyint(4) DEFAULT NULL,
  `hostname` varchar(50) DEFAULT NULL COMMENT 'Windows hostname of PC',
  `mac_address` varchar(17) DEFAULT NULL COMMENT 'For network-based detection',
  `ip_address` varchar(15) DEFAULT NULL,
  `status` enum('offline','idle','active','maintenance') DEFAULT 'offline',
  `grid_x` smallint(6) DEFAULT NULL COMMENT 'Pixel X position on floor canvas',
  `grid_y` smallint(6) DEFAULT NULL COMMENT 'Pixel Y position on floor canvas',
  `zone_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT NULL COMMENT 'Display order override',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_station_code` (`floor_id`,`station_code`),
  UNIQUE KEY `unique_position` (`floor_id`,`row_label`,`col_number`),
  KEY `idx_status` (`status`),
  KEY `idx_floor` (`floor_id`),
  CONSTRAINT `lab_stations_ibfk_1` FOREIGN KEY (`floor_id`) REFERENCES `lab_floors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_stations`
--

LOCK TABLES `lab_stations` WRITE;
/*!40000 ALTER TABLE `lab_stations` DISABLE KEYS */;
INSERT INTO `lab_stations` VALUES (1,300,'PC-01',NULL,NULL,NULL,NULL,NULL,'offline',NULL,NULL,NULL,NULL,NULL),(2,300,'PC-02','C',3,NULL,NULL,NULL,'offline',NULL,NULL,NULL,NULL,NULL),(3,300,'PC-03',NULL,NULL,NULL,NULL,NULL,'offline',NULL,NULL,NULL,NULL,NULL),(4,300,'PC-04','A',3,NULL,NULL,NULL,'offline',NULL,NULL,NULL,NULL,NULL),(5,300,'PC-05','C',5,NULL,NULL,NULL,'offline',NULL,NULL,NULL,NULL,NULL),(6,300,'PC-06','B',5,NULL,NULL,NULL,'offline',NULL,NULL,NULL,NULL,NULL),(7,300,'PC-07',NULL,NULL,NULL,NULL,NULL,'offline',NULL,NULL,NULL,NULL,NULL),(8,300,'PC-08','B',1,NULL,NULL,NULL,'offline',NULL,NULL,NULL,NULL,NULL),(9,300,'PC-09','C',1,NULL,NULL,NULL,'offline',NULL,NULL,NULL,NULL,NULL),(10,300,'PC-10','A',1,NULL,NULL,NULL,'offline',NULL,NULL,NULL,NULL,NULL),(11,300,'PC-11','B',3,NULL,NULL,NULL,'offline',NULL,NULL,NULL,NULL,NULL),(12,300,'PC-12','A',5,NULL,NULL,NULL,'offline',NULL,NULL,NULL,NULL,NULL),(13,301,'pc01','A',1,NULL,NULL,NULL,'offline',NULL,NULL,NULL,0,NULL);
/*!40000 ALTER TABLE `lab_stations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `labs`
--

DROP TABLE IF EXISTS `labs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `labs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `building` varchar(100) DEFAULT NULL,
  `floor_number` int(11) DEFAULT 1,
  `grid_cols` int(11) DEFAULT 6,
  `grid_rows` int(11) DEFAULT 5,
  `layout_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`layout_config`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `labs`
--

LOCK TABLES `labs` WRITE;
/*!40000 ALTER TABLE `labs` DISABLE KEYS */;
INSERT INTO `labs` VALUES (1,'Main Computer Lab','Primary computer laboratory','Main Building',1,6,5,NULL,1,'2026-04-03 07:09:54','2026-04-03 07:09:54');
/*!40000 ALTER TABLE `labs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leaderboard_cache`
--

DROP TABLE IF EXISTS `leaderboard_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leaderboard_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `period` enum('daily','weekly','monthly','all_time') NOT NULL,
  `period_value` varchar(20) DEFAULT NULL COMMENT 'e.g., "2024-W15", "2024-04"',
  `total_points` int(11) NOT NULL DEFAULT 0,
  `rank_position` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL COMMENT 'Course-specific leaderboard',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_period_course` (`user_id`,`period`,`period_value`,`course_id`),
  KEY `idx_rank` (`period`,`period_value`,`course_id`,`rank_position`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `leaderboard_cache_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leaderboard_cache_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leaderboard_cache`
--

LOCK TABLES `leaderboard_cache` WRITE;
/*!40000 ALTER TABLE `leaderboard_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `leaderboard_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL COMMENT 'achievement, assignment_due, announcement, quiz_result',
  `title` varchar(200) NOT NULL,
  `body` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `action_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_unread` (`user_id`,`is_read`,`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,102,'achievement','≡ƒÅå Achievement Unlocked!','You earned: First Steps - Logged in for the first time',0,NULL,'2026-04-14 11:18:59'),(2,102,'achievement','≡ƒÅå Achievement Unlocked!','You earned: Perfect Score - Got 100% on a quiz',0,NULL,'2026-04-14 15:05:41');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pc_sessions`
--

DROP TABLE IF EXISTS `pc_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pc_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `pc_id` int(11) NOT NULL,
  `station_id` int(11) DEFAULT NULL,
  `checkin_time` datetime NOT NULL DEFAULT current_timestamp(),
  `checkout_time` datetime DEFAULT NULL,
  `status` enum('active','completed','forced_logout','timeout') DEFAULT 'active',
  `checkout_reason` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `station_id` (`station_id`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_pc_status` (`pc_id`,`status`),
  KEY `idx_checkin` (`checkin_time`),
  CONSTRAINT `pc_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pc_sessions_ibfk_2` FOREIGN KEY (`pc_id`) REFERENCES `lab_pcs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pc_sessions_ibfk_3` FOREIGN KEY (`station_id`) REFERENCES `lab_stations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pc_sessions`
--

LOCK TABLES `pc_sessions` WRITE;
/*!40000 ALTER TABLE `pc_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `pc_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `point_awards`
--

DROP TABLE IF EXISTS `point_awards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `point_awards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `awarded_by` int(11) NOT NULL COMMENT 'Teacher/admin who gave the award',
  `user_id` int(11) NOT NULL COMMENT 'Student who received the award',
  `points` int(11) NOT NULL COMMENT 'Number of points awarded',
  `reason` varchar(255) NOT NULL COMMENT 'Reason for the award',
  `award_type` enum('behavior','participation','achievement','helping_others','improvement','other') DEFAULT 'other',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_awarded_by` (`awarded_by`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `point_awards_ibfk_1` FOREIGN KEY (`awarded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `point_awards_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `point_awards`
--

LOCK TABLES `point_awards` WRITE;
/*!40000 ALTER TABLE `point_awards` DISABLE KEYS */;
INSERT INTO `point_awards` VALUES (5,101,102,5,'asd','behavior','2026-04-14 11:18:59'),(6,101,102,100,'sda','achievement','2026-04-14 11:19:15');
/*!40000 ALTER TABLE `point_awards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `powerup_usage`
--

DROP TABLE IF EXISTS `powerup_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `powerup_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `powerup_id` int(11) NOT NULL,
  `points_spent` int(11) NOT NULL,
  `context_type` varchar(50) DEFAULT NULL COMMENT 'quiz_attempt, reward_redemption',
  `context_id` int(11) DEFAULT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('used','cancelled','refund') DEFAULT 'used',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `powerup_id` (`powerup_id`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_user` (`user_id`,`used_at`),
  CONSTRAINT `powerup_usage_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `powerup_usage_ibfk_2` FOREIGN KEY (`powerup_id`) REFERENCES `powerups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `powerup_usage_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `powerup_usage`
--

LOCK TABLES `powerup_usage` WRITE;
/*!40000 ALTER TABLE `powerup_usage` DISABLE KEYS */;
/*!40000 ALTER TABLE `powerup_usage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `powerups`
--

DROP TABLE IF EXISTS `powerups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `powerups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL COMMENT 'emoji',
  `point_cost` int(11) NOT NULL,
  `type` enum('quiz','reward') NOT NULL DEFAULT 'quiz',
  `category` varchar(50) DEFAULT NULL COMMENT 'timer, hints, scoring, skip, exemption, privilege',
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '{"effect":"freeze_timer","duration":10}' CHECK (json_valid(`config`)),
  `is_active` tinyint(1) DEFAULT 1,
  `usage_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `powerups`
--

LOCK TABLES `powerups` WRITE;
/*!40000 ALTER TABLE `powerups` DISABLE KEYS */;
INSERT INTO `powerups` VALUES (1,'freeze_timer','Freeze Timer','Stop the timer for 10 seconds','≡ƒºè',50,'quiz','timer','{\"effect\":\"freeze\",\"duration\":10}',1,0,'2026-04-03 04:54:20'),(2,'double_time','Double Time','Add 15 extra seconds to the current question','ΓÅ░',80,'quiz','timer','{\"effect\":\"add_time\",\"seconds\":15}',1,0,'2026-04-03 04:54:20'),(3,'double_points','Double Points','Earn 2x points on this question','≡ƒÄ»',120,'quiz','scoring','{\"effect\":\"multiply_points\",\"factor\":2}',1,0,'2026-04-03 04:54:20'),(4,'fifty_fifty','50:50','Remove 2 wrong answer options','Γ£é∩╕Å',60,'quiz','hints','{\"effect\":\"remove_wrong\",\"count\":2}',1,0,'2026-04-03 04:54:20'),(5,'skip_question','Skip','Skip this question and earn base points','≡ƒô¥',100,'quiz','skip','{\"effect\":\"skip\",\"base_points\":5}',1,0,'2026-04-03 04:54:20'),(6,'reveal_hint','Hint','Show a hint for this question','≡ƒÆí',40,'quiz','hints','{\"effect\":\"show_hint\"}',1,0,'2026-04-03 04:54:20'),(7,'bonus_rush','Bonus Rush','Next question is worth 3x points (wrong = 0)','≡ƒÄ░',300,'quiz','scoring','{\"effect\":\"multiply_points\",\"factor\":3,\"risk\":true}',1,0,'2026-04-03 04:54:20');
/*!40000 ALTER TABLE `powerups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `question_bank`
--

DROP TABLE IF EXISTS `question_bank`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_bank` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('multiple_choice','true_false','code_completion','code_ordering','output_prediction','short_answer','file_upload') NOT NULL,
  `question_text` text NOT NULL,
  `code_snippet` text DEFAULT NULL,
  `code_language` varchar(20) DEFAULT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `correct_answer` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`correct_answer`)),
  `explanation` text DEFAULT NULL,
  `hint` text DEFAULT NULL,
  `difficulty` tinyint(4) DEFAULT 1 COMMENT '1=easy, 2=medium, 3=hard',
  `points` int(11) DEFAULT 10,
  `subject` varchar(100) DEFAULT NULL,
  `topic` varchar(100) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `bloom_level` varchar(50) DEFAULT NULL,
  `source_type` enum('manual','csv_import','file_generated') DEFAULT 'manual',
  `source_id` int(11) DEFAULT NULL,
  `usage_count` int(11) DEFAULT 0,
  `success_rate` decimal(5,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_subject_topic` (`subject`,`topic`),
  KEY `idx_difficulty` (`difficulty`),
  FULLTEXT KEY `ft_question` (`question_text`,`code_snippet`),
  CONSTRAINT `question_bank_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `question_bank`
--

LOCK TABLES `question_bank` WRITE;
/*!40000 ALTER TABLE `question_bank` DISABLE KEYS */;
/*!40000 ALTER TABLE `question_bank` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `question_tag_map`
--

DROP TABLE IF EXISTS `question_tag_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_tag_map` (
  `question_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`question_id`,`tag_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `question_tag_map_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `question_bank` (`id`) ON DELETE CASCADE,
  CONSTRAINT `question_tag_map_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `question_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `question_tag_map`
--

LOCK TABLES `question_tag_map` WRITE;
/*!40000 ALTER TABLE `question_tag_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `question_tag_map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `question_tags`
--

DROP TABLE IF EXISTS `question_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `color` varchar(7) DEFAULT NULL COMMENT 'hex color for UI tag display',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `question_tags`
--

LOCK TABLES `question_tags` WRITE;
/*!40000 ALTER TABLE `question_tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `question_tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_answers`
--

DROP TABLE IF EXISTS `quiz_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quiz_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `answer` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Student answer structure' CHECK (json_valid(`answer`)),
  `is_correct` tinyint(1) DEFAULT NULL COMMENT 'NULL = needs manual grading',
  `points_earned` decimal(5,2) DEFAULT 0.00,
  `time_taken` int(11) DEFAULT NULL COMMENT 'seconds spent on this question',
  `powerup_used` varchar(50) DEFAULT NULL COMMENT 'Which power-up was used',
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attempt_question` (`attempt_id`,`question_id`),
  KEY `question_id` (`question_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `quiz_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_answers_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_answers`
--

LOCK TABLES `quiz_answers` WRITE;
/*!40000 ALTER TABLE `quiz_answers` DISABLE KEYS */;
INSERT INTO `quiz_answers` VALUES (9,5,8,102,'\"Mango\"',1,10.00,NULL,NULL,'2026-04-14 09:05:41');
/*!40000 ALTER TABLE `quiz_answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_attempts`
--

DROP TABLE IF EXISTS `quiz_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quiz_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `station_id` int(11) DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `finished_at` timestamp NULL DEFAULT NULL,
  `total_score` decimal(8,2) DEFAULT 0.00,
  `max_score` decimal(8,2) DEFAULT 0.00,
  `correct_answers` int(11) DEFAULT 0,
  `total_questions` int(11) DEFAULT 0,
  `status` enum('in_progress','completed','abandoned','submitted_late') DEFAULT 'in_progress',
  `is_reviewed` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `station_id` (`station_id`),
  KEY `idx_user_quiz` (`user_id`,`quiz_id`),
  KEY `idx_quiz_status` (`quiz_id`,`status`),
  CONSTRAINT `quiz_attempts_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_attempts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_attempts_ibfk_3` FOREIGN KEY (`station_id`) REFERENCES `lab_stations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_attempts`
--

LOCK TABLES `quiz_attempts` WRITE;
/*!40000 ALTER TABLE `quiz_attempts` DISABLE KEYS */;
INSERT INTO `quiz_attempts` VALUES (5,2,102,NULL,'2026-04-14 15:05:36','2026-04-14 09:05:41',10.00,10.00,1,1,'completed',0);
/*!40000 ALTER TABLE `quiz_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_powerup_activations`
--

DROP TABLE IF EXISTS `quiz_powerup_activations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quiz_powerup_activations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `powerup_id` int(11) NOT NULL,
  `activated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `effect_applied` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `attempt_id` (`attempt_id`),
  KEY `question_id` (`question_id`),
  KEY `powerup_id` (`powerup_id`),
  CONSTRAINT `quiz_powerup_activations_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_powerup_activations_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_powerup_activations_ibfk_3` FOREIGN KEY (`powerup_id`) REFERENCES `powerups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_powerup_activations`
--

LOCK TABLES `quiz_powerup_activations` WRITE;
/*!40000 ALTER TABLE `quiz_powerup_activations` DISABLE KEYS */;
/*!40000 ALTER TABLE `quiz_powerup_activations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_questions`
--

DROP TABLE IF EXISTS `quiz_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `question_number` int(11) NOT NULL,
  `type` enum('multiple_choice','true_false','code_completion','code_ordering','output_prediction','short_answer','file_upload') NOT NULL,
  `question_text` text NOT NULL,
  `code_snippet` text DEFAULT NULL COMMENT 'Code block for programming questions',
  `code_language` varchar(20) DEFAULT NULL COMMENT 'html, css, js, python',
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'MCQ options array' CHECK (json_valid(`options`)),
  `correct_answer` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Correct answer structure' CHECK (json_valid(`correct_answer`)),
  `points` int(11) DEFAULT 10,
  `time_limit` int(11) DEFAULT NULL COMMENT 'Override quiz default seconds',
  `hint` text DEFAULT NULL,
  `explanation` text DEFAULT NULL COMMENT 'Shown after quiz ends',
  PRIMARY KEY (`id`),
  KEY `idx_quiz` (`quiz_id`,`question_number`),
  CONSTRAINT `quiz_questions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_questions`
--

LOCK TABLES `quiz_questions` WRITE;
/*!40000 ALTER TABLE `quiz_questions` DISABLE KEYS */;
INSERT INTO `quiz_questions` VALUES (8,2,1,'multiple_choice','what kind of fruit is this?',NULL,NULL,'[\"Mango\",\"i dunno\",\"Orange\",\"Fruit\"]','\"Mango\"',10,NULL,NULL,NULL),(9,3,1,'multiple_choice','safasgas',NULL,NULL,'[\"asdasf\",\"safas\",\"asfasf\",\"fasfasf\"]','\"fasfasf\"',10,NULL,NULL,NULL);
/*!40000 ALTER TABLE `quiz_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quizzes`
--

DROP TABLE IF EXISTS `quizzes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `time_limit_per_q` int(11) DEFAULT 30 COMMENT 'seconds per question',
  `max_attempts` int(11) DEFAULT 1,
  `shuffle_questions` tinyint(1) DEFAULT 0,
  `shuffle_answers` tinyint(1) DEFAULT 1,
  `show_live_leaderboard` tinyint(1) DEFAULT 1,
  `allow_powerups` tinyint(1) DEFAULT 1,
  `show_results_immediately` tinyint(1) DEFAULT 1,
  `status` enum('draft','scheduled','active','completed','archived') DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `closes_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_course` (`course_id`,`status`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quizzes_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quizzes`
--

LOCK TABLES `quizzes` WRITE;
/*!40000 ALTER TABLE `quizzes` DISABLE KEYS */;
INSERT INTO `quizzes` VALUES (2,200,'what kind of fruit is this','Kind',100,30,1,0,1,1,1,1,'active',NULL,NULL,'2026-04-14 14:46:39','2026-04-14 14:58:24'),(3,200,'lmsadsaf','nasdkgjbfasKf',100,30,1,0,1,1,1,1,'draft',NULL,NULL,'2026-04-16 04:25:18','2026-04-16 04:25:18');
/*!40000 ALTER TABLE `quizzes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `redeemed_rewards`
--

DROP TABLE IF EXISTS `redeemed_rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `redeemed_rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `reward_id` int(11) NOT NULL,
  `points_deducted` int(11) NOT NULL,
  `status` enum('pending','approved','denied','fulfilled','expired') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `fulfilled_at` timestamp NULL DEFAULT NULL,
  `denial_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reward_id` (`reward_id`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_user_status` (`user_id`,`status`),
  CONSTRAINT `redeemed_rewards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redeemed_rewards_ibfk_2` FOREIGN KEY (`reward_id`) REFERENCES `rewards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redeemed_rewards_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `redeemed_rewards`
--

LOCK TABLES `redeemed_rewards` WRITE;
/*!40000 ALTER TABLE `redeemed_rewards` DISABLE KEYS */;
/*!40000 ALTER TABLE `redeemed_rewards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `remote_commands`
--

DROP TABLE IF EXISTS `remote_commands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `remote_commands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pc_id` int(11) NOT NULL,
  `issued_by` int(11) NOT NULL COMMENT 'Teacher/admin user_id who issued the command',
  `command_type` enum('lock','unlock','shutdown','restart','message','screenshot') NOT NULL,
  `params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional parameters (e.g., message text)' CHECK (json_valid(`params`)),
  `status` enum('pending','executed','failed','expired') DEFAULT 'pending',
  `result` text DEFAULT NULL COMMENT 'Execution result or error message',
  `executed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL COMMENT 'Command expires after this time',
  PRIMARY KEY (`id`),
  KEY `issued_by` (`issued_by`),
  KEY `idx_pc_status` (`pc_id`,`status`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `remote_commands_ibfk_1` FOREIGN KEY (`pc_id`) REFERENCES `lab_pcs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `remote_commands_ibfk_2` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `remote_commands`
--

LOCK TABLES `remote_commands` WRITE;
/*!40000 ALTER TABLE `remote_commands` DISABLE KEYS */;
/*!40000 ALTER TABLE `remote_commands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rewards`
--

DROP TABLE IF EXISTS `rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL COMMENT 'emoji',
  `point_cost` int(11) NOT NULL,
  `category` varchar(50) DEFAULT NULL COMMENT 'exemption, privilege, item, fun',
  `requires_approval` tinyint(1) DEFAULT 1,
  `max_redemptions` int(11) DEFAULT NULL COMMENT 'NULL = unlimited',
  `times_redeemed` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `valid_until` datetime DEFAULT NULL COMMENT 'NULL = no expiry',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `rewards_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rewards`
--

LOCK TABLES `rewards` WRITE;
/*!40000 ALTER TABLE `rewards` DISABLE KEYS */;
INSERT INTO `rewards` VALUES (9,'Quiz Exemption Card','Skip 1 question on any future quiz','≡ƒôï',1000,'exemption',1,NULL,0,1,1,NULL,'2026-04-03 04:56:38'),(10,'Free Lab Pass','Skip lab check-in for one session','≡ƒÆ╗',500,'privilege',1,NULL,0,1,1,NULL,'2026-04-03 04:56:38'),(11,'10-Min Game Time','Play games for the last 10 minutes of class','≡ƒÄ«',300,'fun',1,NULL,0,1,1,NULL,'2026-04-03 04:56:38'),(12,'No Homework Pass','Skip 1 homework assignment','≡ƒÅå',2000,'exemption',1,NULL,0,1,1,NULL,'2026-04-03 04:56:38'),(13,'Extra Attempt','Re-submit a failed assignment','≡ƒô¥',250,'privilege',1,NULL,0,1,1,NULL,'2026-04-03 04:56:38'),(14,'Choose Your Seat','Pick your own seat for 1 week','≡ƒîƒ',150,'privilege',0,NULL,0,1,1,NULL,'2026-04-03 04:56:38'),(15,'Custom Desktop','Set your lab PC wallpaper for 1 week','≡ƒÄ¿',400,'fun',1,NULL,0,1,1,NULL,'2026-04-03 04:56:38'),(16,'Play Music','Play your music during lab work time','≡ƒÄñ',200,'fun',1,NULL,0,1,1,NULL,'2026-04-03 04:56:38');
/*!40000 ALTER TABLE `rewards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `station_assignments`
--

DROP TABLE IF EXISTS `station_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `station_assignments` (
  `station_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `task` varchar(255) DEFAULT NULL COMMENT 'Current assigned activity',
  PRIMARY KEY (`station_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `station_assignments_ibfk_1` FOREIGN KEY (`station_id`) REFERENCES `lab_stations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `station_assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `station_assignments`
--

LOCK TABLES `station_assignments` WRITE;
/*!40000 ALTER TABLE `station_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `station_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `submissions`
--

DROP TABLE IF EXISTS `submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text DEFAULT NULL,
  `file_url` varchar(500) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `score` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `status` enum('pending','submitted','graded','late') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_submission` (`assignment_id`,`user_id`),
  KEY `idx_assignment` (`assignment_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `submissions_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `submissions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submissions`
--

LOCK TABLES `submissions` WRITE;
/*!40000 ALTER TABLE `submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sync_out_queue`
--

DROP TABLE IF EXISTS `sync_out_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sync_out_queue` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `operation` enum('insert','update','delete') NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `status` enum('pending','synced','failed') DEFAULT 'pending',
  `retry_count` int(11) DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `synced_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pending` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sync_out_queue`
--

LOCK TABLES `sync_out_queue` WRITE;
/*!40000 ALTER TABLE `sync_out_queue` DISABLE KEYS */;
/*!40000 ALTER TABLE `sync_out_queue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_achievements`
--

DROP TABLE IF EXISTS `user_achievements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_achievements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `achievement_id` int(11) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_achievement` (`user_id`,`achievement_id`),
  KEY `idx_earned` (`achievement_id`,`earned_at`),
  CONSTRAINT `user_achievements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_achievements_ibfk_2` FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_achievements`
--

LOCK TABLES `user_achievements` WRITE;
/*!40000 ALTER TABLE `user_achievements` DISABLE KEYS */;
INSERT INTO `user_achievements` VALUES (4,102,1,'2026-04-14 11:18:59'),(5,102,5,'2026-04-14 15:05:41');
/*!40000 ALTER TABLE `user_achievements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_point_balances`
--

DROP TABLE IF EXISTS `user_point_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_point_balances` (
  `user_id` int(11) NOT NULL,
  `total_earned` int(11) DEFAULT 0 COMMENT 'Lifetime points earned',
  `total_spent` int(11) DEFAULT 0 COMMENT 'Lifetime points spent',
  `balance` int(11) DEFAULT 0 COMMENT 'total_earned - total_spent',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `user_point_balances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_point_balances`
--

LOCK TABLES `user_point_balances` WRITE;
/*!40000 ALTER TABLE `user_point_balances` DISABLE KEYS */;
INSERT INTO `user_point_balances` VALUES (100,0,0,0,'2026-04-16 04:32:12'),(102,225,0,225,'2026-04-14 15:05:41');
/*!40000 ALTER TABLE `user_point_balances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_points`
--

DROP TABLE IF EXISTS `user_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `points` int(11) NOT NULL COMMENT 'Positive for earned, negative for spent',
  `reason` varchar(100) DEFAULT NULL COMMENT 'attendance, assignment, quiz, bonus, penalty, reward',
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'attendance_session, submission, quiz_attempt, achievement, reward',
  `reference_id` int(11) DEFAULT NULL,
  `awarded_by` int(11) DEFAULT NULL COMMENT 'Teacher/admin ID or NULL for system',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `awarded_by` (`awarded_by`),
  KEY `idx_user` (`user_id`,`created_at`),
  KEY `idx_reason` (`reason`),
  CONSTRAINT `user_points_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_points_ibfk_2` FOREIGN KEY (`awarded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_points`
--

LOCK TABLES `user_points` WRITE;
/*!40000 ALTER TABLE `user_points` DISABLE KEYS */;
INSERT INTO `user_points` VALUES (1,102,100,'initial_bonus',NULL,NULL,NULL,'2026-04-03 04:59:06'),(8,102,5,'award','point_award',NULL,101,'2026-04-14 11:18:59'),(9,102,10,'achievement_bonus','achievement',1,NULL,'2026-04-14 11:18:59'),(10,102,100,'award','point_award',NULL,101,'2026-04-14 11:19:15'),(11,102,10,'quiz','quiz_attempt',3,NULL,'2026-04-14 13:42:41'),(12,102,10,'quiz','quiz_attempt',4,NULL,'2026-04-14 13:45:46'),(13,102,10,'quiz','quiz_attempt',5,NULL,'2026-04-14 15:05:41'),(14,102,30,'achievement_bonus','achievement',5,NULL,'2026-04-14 15:05:41'),(15,102,50,'quiz_perfect_bonus','quiz_attempt',5,NULL,'2026-04-14 15:05:41');
/*!40000 ALTER TABLE `user_points` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_user_points_after_insert

AFTER INSERT ON user_points

FOR EACH ROW

BEGIN

    INSERT INTO user_point_balances (user_id, total_earned, total_spent, balance)

    VALUES (NEW.user_id, 0, 0, 0)

    ON DUPLICATE KEY UPDATE

        total_earned = total_earned + IF(NEW.points > 0, NEW.points, 0),

        total_spent = total_spent + IF(NEW.points < 0, ABS(NEW.points), 0),

        balance = balance + NEW.points,

        updated_at = NOW();END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lrn` varchar(20) DEFAULT NULL COMMENT 'Learner Reference Number (used as QR code)',
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `role` enum('student','teacher','admin') NOT NULL DEFAULT 'student',
  `grade_level` varchar(10) DEFAULT NULL,
  `section` varchar(20) DEFAULT NULL,
  `homeroom_teacher` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lrn` (`lrn`),
  KEY `idx_lrn` (`lrn`),
  KEY `idx_role` (`role`),
  KEY `idx_section` (`section`),
  KEY `idx_grade_section` (`grade_level`,`section`),
  KEY `idx_name` (`last_name`,`first_name`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'ADMIN-001','System',NULL,'Administrator',NULL,'admin',NULL,NULL,NULL,'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',NULL,1,'2026-04-03 04:54:20','2026-04-03 04:54:20',NULL),(100,'ADMIN001','System',NULL,'Administrator','admin@xplabs.local','admin',NULL,NULL,NULL,'$2y$10$I.Ls58wIh8tTg/gUJ584G.WQIlRMhUcO5sUwzcxU.6JUHxzQPs11i',NULL,1,'2026-04-03 04:57:50','2026-04-16 04:25:00','2026-04-16 04:25:00'),(101,'TEACHER01','Juan',NULL,'Dela Cruz','teacher@xplabs.local','teacher',NULL,NULL,NULL,'$2y$10$I.Ls58wIh8tTg/gUJ584G.WQIlRMhUcO5sUwzcxU.6JUHxzQPs11i',NULL,1,'2026-04-03 04:57:50','2026-04-14 11:55:30','2026-04-14 11:55:30'),(102,'20240001','Maria',NULL,'Santos','student@xplabs.local','student',NULL,NULL,NULL,'$2y$10$I.Ls58wIh8tTg/gUJ584G.WQIlRMhUcO5sUwzcxU.6JUHxzQPs11i',NULL,1,'2026-04-03 04:57:50','2026-04-14 15:29:56','2026-04-14 15:29:56');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'xplabs'
--

--
-- Dumping routines for database 'xplabs'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-23 15:08:21
