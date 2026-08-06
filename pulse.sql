CREATE DATABASE  IF NOT EXISTS `pulse_mysql` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `pulse_mysql`;
-- MySQL dump 10.13  Distrib 8.0.45, for Win64 (x86_64)
--
-- Host: mysql-database.cc98ouaycdke.us-east-1.rds.amazonaws.com    Database: pulse_mysql
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
SET @MYSQLDUMP_TEMP_LOG_BIN = @@SESSION.SQL_LOG_BIN;
SET @@SESSION.SQL_LOG_BIN= 0;

--
-- GTID state at the beginning of the backup 
--

SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ '';

--
-- Table structure for table `activities`
--

DROP TABLE IF EXISTS `activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activities`
--

LOCK TABLES `activities` WRITE;
/*!40000 ALTER TABLE `activities` DISABLE KEYS */;
INSERT INTO `activities` VALUES (1,'Sedentary','Little to no regular exercise',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(2,'Lightly Active','1–3 days of exercise per week',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(3,'Moderately Active','3–5 days of exercise per week',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(4,'Very Active','6–7 days of intense training',1,'2026-08-05 08:29:49','2026-08-05 08:29:49');
/*!40000 ALTER TABLE `activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `awareness_snapshots`
--

DROP TABLE IF EXISTS `awareness_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `awareness_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `cycle_id` bigint unsigned DEFAULT NULL,
  `phase` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `day_range` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_cycle_day` tinyint unsigned DEFAULT NULL,
  `dominant_hormone_note` text COLLATE utf8mb4_unicode_ci,
  `energy` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `skin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mood` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estrogen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `progesterone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modeled` tinyint(1) NOT NULL DEFAULT '0',
  `source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `bbt_note` text COLLATE utf8mb4_unicode_ci,
  `energy_note` text COLLATE utf8mb4_unicode_ci,
  `hormone_note` text COLLATE utf8mb4_unicode_ci,
  `focus_note` text COLLATE utf8mb4_unicode_ci,
  `current_phase` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phases` json DEFAULT NULL,
  `ai_generated` tinyint(1) NOT NULL DEFAULT '0',
  `ai_cached` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `awareness_snapshots_user_id_cycle_id_unique` (`user_id`,`cycle_id`),
  KEY `awareness_snapshots_cycle_id_foreign` (`cycle_id`),
  KEY `awareness_snapshots_phase_index` (`phase`),
  KEY `awareness_snapshots_current_phase_index` (`current_phase`),
  CONSTRAINT `awareness_snapshots_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `awareness_snapshots_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `awareness_snapshots`
--

LOCK TABLES `awareness_snapshots` WRITE;
/*!40000 ALTER TABLE `awareness_snapshots` DISABLE KEYS */;
/*!40000 ALTER TABLE `awareness_snapshots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bbt_logs`
--

DROP TABLE IF EXISTS `bbt_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bbt_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` bigint unsigned DEFAULT NULL,
  `cycleID` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `log_date` date NOT NULL,
  `temperature` decimal(5,2) NOT NULL,
  `unit` enum('C','F') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'F',
  `logged_at` time DEFAULT NULL,
  `illness` tinyint(1) NOT NULL DEFAULT '0',
  `poor_sleep` tinyint(1) NOT NULL DEFAULT '0',
  `alcohol` tinyint(1) NOT NULL DEFAULT '0',
  `late_wakeup` tinyint(1) NOT NULL DEFAULT '0',
  `travel` tinyint(1) NOT NULL DEFAULT '0',
  `is_excluded` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `coverline_value` decimal(5,2) DEFAULT NULL,
  `ovulation_confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `cycle_day` int DEFAULT NULL,
  `phase` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bbt_logs_cycle_id_log_date_unique` (`cycle_id`,`log_date`),
  KEY `bbt_logs_log_date_index` (`log_date`),
  KEY `bbt_logs_is_excluded_index` (`is_excluded`),
  CONSTRAINT `bbt_logs_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bbt_logs`
--

LOCK TABLES `bbt_logs` WRITE;
/*!40000 ALTER TABLE `bbt_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `bbt_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_categories`
--

DROP TABLE IF EXISTS `blog_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_categories_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_categories`
--

LOCK TABLES `blog_categories` WRITE;
/*!40000 ALTER TABLE `blog_categories` DISABLE KEYS */;
INSERT INTO `blog_categories` VALUES (1,'Beauty & Radiance','beauty-radiance','Revealing how skin, energy, and outward vitality reflect inner health, turning daily signals into visible results.',0,1,'2026-08-06 01:29:46','2026-08-06 01:29:46'),(2,'Cycle & Fertility','cycle-fertility','Establishing the monthly baseline by connecting wearable, hormone, and cycle signals.',0,1,'2026-08-06 01:30:13','2026-08-06 01:30:13'),(3,'Athlete','athlete','Optimizing training, recovery, and performance around the hormonal rhythms that female-specific data too often ignores.',0,1,'2026-08-06 01:30:30','2026-08-06 01:30:30'),(4,'Perimenopause/Menopause & Vitality','perimenopausemenopause-vitality','Navigating the hormonal turbulence of perimenopause and menopause — decoding disruptive symptoms and protecting long-term vitality as the body\'s rhythms shift.',0,1,'2026-08-06 01:32:46','2026-08-06 01:32:46'),(5,'Pregnancy & Postpartum','pregnancy-postpartum','Tracking the body\'s rapid changes and supporting recovery through one of life\'s most demanding chapters.',0,1,'2026-08-06 01:33:13','2026-08-06 01:33:13'),(6,'Lifelong Thriving','lifelong-thriving','Sustaining strength, clarity, and well-being across the years, with intelligence that keeps adapting as you do.',0,1,'2026-08-06 01:33:37','2026-08-06 01:33:37');
/*!40000 ALTER TABLE `blog_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blog_comments`
--

DROP TABLE IF EXISTS `blog_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `blog_id` bigint unsigned NOT NULL,
  `author_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `blog_comments_blog_id_foreign` (`blog_id`),
  CONSTRAINT `blog_comments_blog_id_foreign` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_comments`
--

LOCK TABLES `blog_comments` WRITE;
/*!40000 ALTER TABLE `blog_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `blog_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blogs`
--

DROP TABLE IF EXISTS `blogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blogs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `blog_category_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_desc` text COLLATE utf8mb4_unicode_ci,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `cover_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reading_time` int unsigned DEFAULT NULL,
  `word_count` int unsigned DEFAULT NULL,
  `tags` json DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `published_at` timestamp NULL DEFAULT NULL,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `views_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blogs_slug_unique` (`slug`),
  KEY `blogs_blog_category_id_foreign` (`blog_category_id`),
  CONSTRAINT `blogs_blog_category_id_foreign` FOREIGN KEY (`blog_category_id`) REFERENCES `blog_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blogs`
--

LOCK TABLES `blogs` WRITE;
/*!40000 ALTER TABLE `blogs` DISABLE KEYS */;
/*!40000 ALTER TABLE `blogs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `category_place_queries`
--

DROP TABLE IF EXISTS `category_place_queries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `category_place_queries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` smallint unsigned NOT NULL,
  `places_type` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keyword` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category_place_queries_category_id_foreign` (`category_id`),
  CONSTRAINT `category_place_queries_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `provider_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category_place_queries`
--

LOCK TABLES `category_place_queries` WRITE;
/*!40000 ALTER TABLE `category_place_queries` DISABLE KEYS */;
/*!40000 ALTER TABLE `category_place_queries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `category_taxonomy_codes`
--

DROP TABLE IF EXISTS `category_taxonomy_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `category_taxonomy_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` smallint unsigned NOT NULL,
  `nucc_code` char(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nucc_prefix` tinyint(1) NOT NULL DEFAULT '0',
  `label` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category_taxonomy_codes_category_id_foreign` (`category_id`),
  KEY `category_taxonomy_codes_nucc_code_index` (`nucc_code`),
  CONSTRAINT `category_taxonomy_codes_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `provider_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category_taxonomy_codes`
--

LOCK TABLES `category_taxonomy_codes` WRITE;
/*!40000 ALTER TABLE `category_taxonomy_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `category_taxonomy_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cervical_mucus_logs`
--

DROP TABLE IF EXISTS `cervical_mucus_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cervical_mucus_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` bigint unsigned NOT NULL,
  `log_date` date NOT NULL,
  `consistency` enum('dry','sticky','creamy','watery','egg_white') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` enum('low','medium','high') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` enum('clear','white','yellow','cloudy') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stretch_cm` decimal(4,1) DEFAULT NULL,
  `fertility_score` tinyint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cervical_mucus_logs_cycle_id_log_date_unique` (`cycle_id`,`log_date`),
  CONSTRAINT `cervical_mucus_logs_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cervical_mucus_logs`
--

LOCK TABLES `cervical_mucus_logs` WRITE;
/*!40000 ALTER TABLE `cervical_mucus_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `cervical_mucus_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_messages`
--

DROP TABLE IF EXISTS `chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_type` enum('user','ai') COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_summary` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_messages_user_id_foreign` (`user_id`),
  KEY `chat_messages_session_id_foreign` (`session_id`),
  CONSTRAINT `chat_messages_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `chat_sessions` (`session_id`) ON DELETE CASCADE,
  CONSTRAINT `chat_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_messages`
--

LOCK TABLES `chat_messages` WRITE;
/*!40000 ALTER TABLE `chat_messages` DISABLE KEYS */;
INSERT INTO `chat_messages` VALUES (1,4,'1b2abfdd-cd24-40c8-9d68-890a89af4fb9','user','How to improve my sleep quality?',NULL,'2026-08-05 22:50:02','2026-08-05 22:50:02'),(2,4,'1b2abfdd-cd24-40c8-9d68-890a89af4fb9','ai','Hi, happy to help with this. Sleep quality is one of the most powerful levers for your energy, cycle, mood, and recovery, so it\'s a great thing to focus on. What I notice: I wasn\'t able to pull your health logs, cycle snapshot, or temperature data right now (there were some access errors on the backend). That means I can\'t personalize this to your recent sleep, HRV, or cycle phase yet. Once your data is connected, I can give you much more tailored guidance, for example matching sleep tips to your luteal phase if that\'s when you tend to sleep less deeply. In the meantime, a few evidence-based basics that tend to help most people: - Keep a consistent wake time, even on weekends. This anchors your body clock more than bedtime does. - Get 5-10 minutes of natural light within an hour of waking, which supports melatonin timing later that night. - Cool, dark, and quiet room. Around 18-20 C is a common sweet spot. - Cut caffeine after about 2 pm, and avoid heavy meals or alcohol within 2-3 hours of bed. - Give yourself a 30-minute wind-down without bright screens. Even a short stretch, warm shower, or journaling helps signal sleep. A helpful next step: try picking one of the above to focus on this week, rather than changing everything at once. Could you tell me a bit about what\'s making sleep hard right now? For example, is it falling asleep, waking up during the night, waking too early, or feeling unrefreshed in the morning? That\'ll help me point you in the right direction.','{\"temperature_range\": \"No temperature logs found\", \"data_points_analyzed\": 0}','2026-08-05 22:50:13','2026-08-05 22:50:13'),(3,8,'9b8c11b9-e680-479c-90d4-01865ac3489d','user','Why am I more tired this week?',NULL,'2026-08-06 03:10:30','2026-08-06 03:10:30'),(4,8,'9b8c11b9-e680-479c-90d4-01865ac3489d','ai','That\'s such an important question to check in on, and I\'d love to help you figure it out. What I notice: I\'m not able to pull your health data right now. Your profile, today\'s health log, and cycle snapshot all came back with connection errors, so I don\'t have your recent sleep, energy, mood, or cycle information to look at. That means I can\'t yet see the patterns that would explain the extra tiredness. A few common reasons fatigue tends to shift week to week include changes in sleep quality or duration, where you are in your cycle (energy often dips in the late luteal and early menstrual phases), stress load, hydration, iron or B12 levels, and recovery from workouts. Any one of these can quietly add up. A helpful next step: Try logging out and back in so the app can refresh your data, then re-open this chat. Once your logs come through, I can compare this week\'s sleep, HRV, mood, and cycle phase to your usual baseline and give you a much clearer picture. In the meantime, can you share what you\'ve noticed? For example, is your sleep shorter or more restless, are you closer to your period, or has anything changed with stress, workouts, or eating? Even a quick description will help me point you in the right direction.','{\"temperature_range\": \"No temperature logs found\", \"data_points_analyzed\": 0}','2026-08-06 03:10:41','2026-08-06 03:10:41'),(5,8,'5780920b-f42e-415c-afe4-780668cd558f','user','Why am I more tired this week?',NULL,'2026-08-06 03:18:05','2026-08-06 03:18:05'),(6,8,'5780920b-f42e-415c-afe4-780668cd558f','ai','That\'s such a good question to explore, and I wish I could look at your data with you right now. What I notice: I\'m not able to pull your recent logs at the moment. Your profile, today\'s health log, and cycle snapshot all came back with access errors on my end, so I don\'t have your sleep, energy, mood, or cycle data to compare this week against your usual patterns. Once your data is available, the most useful things I\'d look at for fatigue are: - Sleep duration and quality over the last 7 to 10 days - Where you are in your cycle, since energy often dips in the late luteal phase and around your period - HRV and readiness trends, which can hint at recovery or stress load - Mood and stress logs, since emotional load quietly drains energy too - Any new symptoms like headaches, cravings, or brain fog A helpful next step: If you can, try signing out and back in so I can reconnect to your logs. In the meantime, it may help to reflect on whether your sleep, stress, or workouts have shifted this week, since those are the most common drivers of a sudden energy dip. Would you like to tell me a bit about your sleep and cycle timing this week so I can offer some thoughts while we wait on your data?','{\"temperature_range\": \"No temperature logs found\", \"data_points_analyzed\": 0}','2026-08-06 03:18:15','2026-08-06 03:18:15'),(7,8,'5780920b-f42e-415c-afe4-780668cd558f','user','What does my HRV trend mean?',NULL,'2026-08-06 03:18:36','2026-08-06 03:18:36'),(8,8,'5780920b-f42e-415c-afe4-780668cd558f','ai','That\'s a great follow-up, and it ties nicely into the fatigue question from earlier. What I notice: I still can\'t access your data right now. Your profile and health logs are returning access errors, and there\'s no cycle snapshot available, so I don\'t have your recent HRV readings to interpret for you specifically. Without those numbers, I don\'t want to guess at a trend that isn\'t grounded in your real data. Here\'s what HRV generally reflects, so you have some context once your data reconnects. HRV, or heart rate variability, is the small variation in time between heartbeats. Higher HRV usually suggests your nervous system is well-recovered and adaptable, while lower HRV can point to stress, poor sleep, illness, intense training, alcohol, or being in the late luteal or menstrual phase of your cycle. A gradual downward trend over several days often signals accumulated stress or under-recovery, which can absolutely connect to the tiredness you mentioned. A helpful next step: Try signing out and back in so I can pull your HRV history. Once I can see it, I\'d look at your 7-day trend, compare it to your usual baseline, and cross-reference it with your sleep and cycle timing to give you a clearer picture. In the meantime, would you like to share whether your HRV has been trending lower, higher, or bouncing around? That would help me give you more meaningful thoughts right now.','{\"temperature_range\": \"No temperature logs found\", \"data_points_analyzed\": 0}','2026-08-06 03:18:45','2026-08-06 03:18:45'),(9,8,'5780920b-f42e-415c-afe4-780668cd558f','user','hi neumera',NULL,'2026-08-06 03:19:34','2026-08-06 03:19:34'),(10,8,'5780920b-f42e-415c-afe4-780668cd558f','ai','Hi, so lovely to hear from you. Quick heads up: I still can\'t access your health data right now. Your profile and today\'s health log are returning access errors, and there\'s no cycle snapshot loaded on my end. So if you want me to dig into anything specific, like the fatigue or HRV questions from earlier, it would help to sign out and back in so I can reconnect. In the meantime, I\'m happy to chat, answer general wellness questions, or think through what you\'re noticing in your body this week even without the numbers. What would you like to focus on today?','{\"temperature_range\": \"No temperature logs found\", \"data_points_analyzed\": 0}','2026-08-06 03:19:42','2026-08-06 03:19:42'),(11,8,'844714c7-354c-4193-b39f-ed5e3355f8e7','user','who are you ?',NULL,'2026-08-06 03:32:14','2026-08-06 03:32:14'),(12,8,'844714c7-354c-4193-b39f-ed5e3355f8e7','ai','Hi there, lovely to meet you. I\'m Neumera, your wellness companion here in the app. Think of me as a thoughtful health coach in your pocket. I\'m here to help you understand your body better by looking at the health data you log, things like your cycle, mood, energy, sleep, symptoms, temperature, and even lab reports if you share them. What I can help with: - Spotting patterns across your cycle, sleep, energy, and mood - Explaining what your logs might mean in plain, calm language - Walking through lab or hormone reports so they feel less overwhelming - Suggesting small, practical next steps to support how you feel A quick note: I noticed I couldn\'t pull your profile or recent logs just now, it looks like there may be a sign-in hiccup on the backend. Once that\'s sorted, I\'ll be able to give you much more personalized insights. In the meantime, is there something specific on your mind today, maybe your cycle, energy, sleep, or a recent symptom you\'d like to talk through?','{\"temperature_range\": \"No temperature logs found\", \"data_points_analyzed\": 0}','2026-08-06 03:32:21','2026-08-06 03:32:21');
/*!40000 ALTER TABLE `chat_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_sessions`
--

DROP TABLE IF EXISTS `chat_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_sessions_session_id_unique` (`session_id`),
  KEY `chat_sessions_user_id_foreign` (`user_id`),
  CONSTRAINT `chat_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_sessions`
--

LOCK TABLES `chat_sessions` WRITE;
/*!40000 ALTER TABLE `chat_sessions` DISABLE KEYS */;
INSERT INTO `chat_sessions` VALUES (1,4,'1b2abfdd-cd24-40c8-9d68-890a89af4fb9','2026-08-05 22:50:02','2026-08-05 22:50:02'),(2,8,'9b8c11b9-e680-479c-90d4-01865ac3489d','2026-08-06 03:10:30','2026-08-06 03:10:30'),(3,8,'5780920b-f42e-415c-afe4-780668cd558f','2026-08-06 03:18:05','2026-08-06 03:19:34'),(4,8,'844714c7-354c-4193-b39f-ed5e3355f8e7','2026-08-06 03:32:14','2026-08-06 03:32:14');
/*!40000 ALTER TABLE `chat_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `community_comments`
--

DROP TABLE IF EXISTS `community_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `community_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `community_comments_post_id_foreign` (`post_id`),
  KEY `community_comments_user_id_foreign` (`user_id`),
  CONSTRAINT `community_comments_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `community_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `community_comments`
--

LOCK TABLES `community_comments` WRITE;
/*!40000 ALTER TABLE `community_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `community_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `community_likes`
--

DROP TABLE IF EXISTS `community_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `community_likes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `liked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `community_likes_post_id_foreign` (`post_id`),
  KEY `community_likes_user_id_foreign` (`user_id`),
  CONSTRAINT `community_likes_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `community_likes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `community_likes`
--

LOCK TABLES `community_likes` WRITE;
/*!40000 ALTER TABLE `community_likes` DISABLE KEYS */;
/*!40000 ALTER TABLE `community_likes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `community_post_life_journey`
--

DROP TABLE IF EXISTS `community_post_life_journey`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `community_post_life_journey` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `community_post_id` bigint unsigned NOT NULL,
  `life_journey_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `community_post_life_journey_community_post_id_foreign` (`community_post_id`),
  KEY `community_post_life_journey_life_journey_id_foreign` (`life_journey_id`),
  CONSTRAINT `community_post_life_journey_community_post_id_foreign` FOREIGN KEY (`community_post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `community_post_life_journey_life_journey_id_foreign` FOREIGN KEY (`life_journey_id`) REFERENCES `life_journeys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `community_post_life_journey`
--

LOCK TABLES `community_post_life_journey` WRITE;
/*!40000 ALTER TABLE `community_post_life_journey` DISABLE KEYS */;
/*!40000 ALTER TABLE `community_post_life_journey` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `community_post_reports`
--

DROP TABLE IF EXISTS `community_post_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `community_post_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `report_cause` enum('spam','sexual_content','harassment','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `community_post_reports_post_id_foreign` (`post_id`),
  KEY `community_post_reports_user_id_foreign` (`user_id`),
  CONSTRAINT `community_post_reports_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `community_post_reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `community_post_reports`
--

LOCK TABLES `community_post_reports` WRITE;
/*!40000 ALTER TABLE `community_post_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `community_post_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `community_posts`
--

DROP TABLE IF EXISTS `community_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `community_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT '1',
  `is_approved` tinyint(1) NOT NULL DEFAULT '1',
  `tags` json DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `community_posts_slug_unique` (`slug`),
  KEY `community_posts_user_id_foreign` (`user_id`),
  CONSTRAINT `community_posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `community_posts`
--

LOCK TABLES `community_posts` WRITE;
/*!40000 ALTER TABLE `community_posts` DISABLE KEYS */;
/*!40000 ALTER TABLE `community_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `connect_device_profile`
--

DROP TABLE IF EXISTS `connect_device_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `connect_device_profile` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `connect_device_id` bigint unsigned NOT NULL,
  `profile_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `connect_device_profile_connect_device_id_foreign` (`connect_device_id`),
  KEY `connect_device_profile_profile_id_foreign` (`profile_id`),
  CONSTRAINT `connect_device_profile_connect_device_id_foreign` FOREIGN KEY (`connect_device_id`) REFERENCES `connect_devices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `connect_device_profile_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `connect_device_profile`
--

LOCK TABLES `connect_device_profile` WRITE;
/*!40000 ALTER TABLE `connect_device_profile` DISABLE KEYS */;
/*!40000 ALTER TABLE `connect_device_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `connect_devices`
--

DROP TABLE IF EXISTS `connect_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `connect_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `icon` text COLLATE utf8mb4_unicode_ci,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `connect_devices`
--

LOCK TABLES `connect_devices` WRITE;
/*!40000 ALTER TABLE `connect_devices` DISABLE KEYS */;
INSERT INTO `connect_devices` VALUES (1,'https://api.fightthenumber.com/backend/devices/apple.png','Apple HealthKit','iOS health & activity data',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(2,'https://api.fightthenumber.com/backend/devices/android.png','Android Health Connect','Android health & activity data',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(3,'https://api.fightthenumber.com/backend/devices/fitbit.png','Fitbit','Wearable activity, sleep, HR',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(4,'https://api.fightthenumber.com/backend/devices/fitbit.png','MyFitnessPal','Wearable activity, sleep, HR',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(5,'https://api.fightthenumber.com/backend/devices/fitbit.png','Terra API','Unified wearable aggregation (Oura, Whoop, Garmin & more)',1,'2026-08-05 08:29:49','2026-08-05 08:29:49');
/*!40000 ALTER TABLE `connect_devices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cycle_calendar_inputs`
--

DROP TABLE IF EXISTS `cycle_calendar_inputs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cycle_calendar_inputs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_day_n` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cycle_calendar_inputs_user_id_foreign` (`user_id`),
  CONSTRAINT `cycle_calendar_inputs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cycle_calendar_inputs`
--

LOCK TABLES `cycle_calendar_inputs` WRITE;
/*!40000 ALTER TABLE `cycle_calendar_inputs` DISABLE KEYS */;
INSERT INTO `cycle_calendar_inputs` VALUES (1,2,'2026-08-06',NULL,0,'2026-08-05 09:02:33','2026-08-05 09:02:33'),(2,7,'2026-08-06',NULL,0,'2026-08-05 09:02:33','2026-08-05 09:02:34');
/*!40000 ALTER TABLE `cycle_calendar_inputs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cycle_consents`
--

DROP TABLE IF EXISTS `cycle_consents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cycle_consents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `consent_version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_consented` tinyint(1) NOT NULL DEFAULT '0',
  `consented_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cycle_consents_user_id_unique` (`user_id`),
  CONSTRAINT `cycle_consents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cycle_consents`
--

LOCK TABLES `cycle_consents` WRITE;
/*!40000 ALTER TABLE `cycle_consents` DISABLE KEYS */;
/*!40000 ALTER TABLE `cycle_consents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cycle_daily_logs`
--

DROP TABLE IF EXISTS `cycle_daily_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cycle_daily_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` bigint unsigned NOT NULL,
  `log_date` date NOT NULL,
  `cycle_day` tinyint unsigned NOT NULL,
  `phase` enum('menstrual','follicular','ovulatory','luteal') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tag` enum('none','period','fertile','ovulation') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `is_prediction` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cycle_daily_logs_cycle_id_log_date_unique` (`cycle_id`,`log_date`),
  KEY `cycle_daily_logs_phase_index` (`phase`),
  KEY `cycle_daily_logs_tag_index` (`tag`),
  CONSTRAINT `cycle_daily_logs_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cycle_daily_logs`
--

LOCK TABLES `cycle_daily_logs` WRITE;
/*!40000 ALTER TABLE `cycle_daily_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `cycle_daily_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cycle_modes`
--

DROP TABLE IF EXISTS `cycle_modes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cycle_modes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `mode` enum('cycle_awareness','trying_to_conceive','avoiding_pregnancy') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cycle_awareness',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `activated_at` timestamp NULL DEFAULT NULL,
  `has_consented` tinyint(1) NOT NULL DEFAULT '0',
  `consent_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consented_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cycle_modes_user_id_unique` (`user_id`),
  KEY `cycle_modes_mode_index` (`mode`),
  KEY `cycle_modes_has_consented_index` (`has_consented`),
  CONSTRAINT `cycle_modes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cycle_modes`
--

LOCK TABLES `cycle_modes` WRITE;
/*!40000 ALTER TABLE `cycle_modes` DISABLE KEYS */;
/*!40000 ALTER TABLE `cycle_modes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cycle_prediction_caches`
--

DROP TABLE IF EXISTS `cycle_prediction_caches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cycle_prediction_caches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` bigint unsigned NOT NULL,
  `cache_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_payload` json DEFAULT NULL,
  `prediction` json NOT NULL,
  `prediction_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ai_generated` tinyint(1) NOT NULL DEFAULT '1',
  `ai_cached` tinyint(1) NOT NULL DEFAULT '1',
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cycle_prediction_caches_cache_key_unique` (`cache_key`),
  KEY `cycle_prediction_caches_cycle_id_foreign` (`cycle_id`),
  KEY `cycle_prediction_caches_endpoint_index` (`endpoint`),
  KEY `cycle_prediction_caches_expires_at_index` (`expires_at`),
  CONSTRAINT `cycle_prediction_caches_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cycle_prediction_caches`
--

LOCK TABLES `cycle_prediction_caches` WRITE;
/*!40000 ALTER TABLE `cycle_prediction_caches` DISABLE KEYS */;
/*!40000 ALTER TABLE `cycle_prediction_caches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cycle_settings`
--

DROP TABLE IF EXISTS `cycle_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cycle_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `average_cycle_length` tinyint unsigned NOT NULL DEFAULT '28',
  `average_period_length` tinyint unsigned NOT NULL DEFAULT '5',
  `luteal_phase_length` tinyint unsigned NOT NULL DEFAULT '14',
  `prediction_method` enum('calendar','bbt','opk','combined') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'combined',
  `temperature_unit` enum('C','F') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'F',
  `period_reminder` tinyint(1) NOT NULL DEFAULT '1',
  `fertility_reminder` tinyint(1) NOT NULL DEFAULT '1',
  `bbt_reminder` tinyint(1) NOT NULL DEFAULT '1',
  `opk_reminder` tinyint(1) NOT NULL DEFAULT '1',
  `allow_ai_prediction` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cycle_settings_user_id_unique` (`user_id`),
  CONSTRAINT `cycle_settings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cycle_settings`
--

LOCK TABLES `cycle_settings` WRITE;
/*!40000 ALTER TABLE `cycle_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `cycle_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cycle_statistics`
--

DROP TABLE IF EXISTS `cycle_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cycle_statistics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `completed_cycles` tinyint unsigned NOT NULL DEFAULT '0',
  `average_cycle_length` tinyint unsigned NOT NULL DEFAULT '28',
  `average_period_length` tinyint unsigned NOT NULL DEFAULT '5',
  `average_ovulation_day` tinyint unsigned DEFAULT NULL,
  `cycle_variance_days` tinyint unsigned NOT NULL DEFAULT '0',
  `reliability_level` enum('low','medium','high') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'low',
  `reliability_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `last_period_date` date DEFAULT NULL,
  `predicted_next_period` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cycle_statistics_user_id_unique` (`user_id`),
  KEY `cycle_statistics_reliability_level_index` (`reliability_level`),
  CONSTRAINT `cycle_statistics_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cycle_statistics`
--

LOCK TABLES `cycle_statistics` WRITE;
/*!40000 ALTER TABLE `cycle_statistics` DISABLE KEYS */;
INSERT INTO `cycle_statistics` VALUES (1,2,0,28,5,0,0,'',0.00,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `cycle_statistics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_scriptures`
--

DROP TABLE IF EXISTS `daily_scriptures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_scriptures` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scripture_date` date DEFAULT NULL,
  `badge` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verse_text` text COLLATE utf8mb4_unicode_ci,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `daily_scriptures_user_id_foreign` (`user_id`),
  CONSTRAINT `daily_scriptures_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_scriptures`
--

LOCK TABLES `daily_scriptures` WRITE;
/*!40000 ALTER TABLE `daily_scriptures` DISABLE KEYS */;
INSERT INTO `daily_scriptures` VALUES (1,3,'Daily Scripture','2026-08-05','Strength','The Lord is my strength and my shield; my heart trusts in him, and he helps me.','Psalm 28:7','A reminder to draw on God\'s strength and trust for today.','completed','2026-08-05 22:00:04','2026-08-05 22:00:13'),(2,8,'Daily Scripture','2026-08-06','Strength','I can do all things through Christ who strengthens me.','Philippians 4:13','A steady reminder that strength for today comes from God, offering encouragement to face whatever the day holds.','completed','2026-08-06 03:03:33','2026-08-06 03:03:39'),(3,3,'Daily Scripture','2026-08-06','Strength','I can do all things through Christ who strengthens me.','Philippians 4:13','A reminder that strength for today comes from God, offering encouragement for whatever the day holds.','completed','2026-08-06 03:47:06','2026-08-06 03:47:21');
/*!40000 ALTER TABLE `daily_scriptures` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
INSERT INTO `failed_jobs` VALUES (1,'431f9204-5b6c-40cf-b897-f83ed403ad21','database','default','{\"uuid\":\"431f9204-5b6c-40cf-b897-f83ed403ad21\",\"displayName\":\"App\\\\Jobs\\\\FetchSmartAnalysisJob\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":600,\"retryUntil\":null,\"data\":{\"commandName\":\"App\\\\Jobs\\\\FetchSmartAnalysisJob\",\"command\":\"O:30:\\\"App\\\\Jobs\\\\FetchSmartAnalysisJob\\\":1:{s:18:\\\"\\u0000*\\u0000smartAnalysisId\\\";i:1;}\",\"batchId\":null},\"createdAt\":1785967204,\"delay\":null}','Exception: smart_analysis key missing. in /var/www/html/app/Jobs/FetchSmartAnalysisJob.php:137\nStack trace:\n#0 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): App\\Jobs\\FetchSmartAnalysisJob->handle()\n#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#4 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#5 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(129): Illuminate\\Container\\Container->call(Array)\n#6 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Bus\\Dispatcher->Illuminate\\Bus\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#7 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#8 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(133): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#9 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(136): Illuminate\\Bus\\Dispatcher->dispatchNow(Object(App\\Jobs\\FetchSmartAnalysisJob), false)\n#10 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Queue\\CallQueuedHandler->Illuminate\\Queue\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#11 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#12 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(129): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#13 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(70): Illuminate\\Queue\\CallQueuedHandler->dispatchThroughMiddleware(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(App\\Jobs\\FetchSmartAnalysisJob))\n#14 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(102): Illuminate\\Queue\\CallQueuedHandler->call(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Array)\n#15 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(504): Illuminate\\Queue\\Jobs\\Job->fire()\n#16 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(454): Illuminate\\Queue\\Worker->process(\'database\', Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Queue\\WorkerOptions))\n#17 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(212): Illuminate\\Queue\\Worker->runJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), \'database\', Object(Illuminate\\Queue\\WorkerOptions))\n#18 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(149): Illuminate\\Queue\\Worker->daemon(\'database\', \'default\', Object(Illuminate\\Queue\\WorkerOptions))\n#19 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(132): Illuminate\\Queue\\Console\\WorkCommand->runWorker(\'database\', \'default\')\n#20 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Queue\\Console\\WorkCommand->handle()\n#21 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#22 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#23 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#24 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#25 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(211): Illuminate\\Container\\Container->call(Array)\n#26 /var/www/html/vendor/symfony/console/Command/Command.php(341): Illuminate\\Console\\Command->execute(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#27 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(180): Symfony\\Component\\Console\\Command\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#28 /var/www/html/vendor/symfony/console/Application.php(1117): Illuminate\\Console\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#29 /var/www/html/vendor/symfony/console/Application.php(356): Symfony\\Component\\Console\\Application->doRunCommand(Object(Illuminate\\Queue\\Console\\WorkCommand), Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#30 /var/www/html/vendor/symfony/console/Application.php(195): Symfony\\Component\\Console\\Application->doRun(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#31 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(198): Symfony\\Component\\Console\\Application->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#32 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(1235): Illuminate\\Foundation\\Console\\Kernel->handle(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#33 /var/www/html/artisan(16): Illuminate\\Foundation\\Application->handleCommand(Object(Symfony\\Component\\Console\\Input\\ArgvInput))\n#34 {main}','2026-08-05 22:00:13'),(2,'d3c166b1-f99f-4608-955d-6d6f1f5eb8eb','database','default','{\"uuid\":\"d3c166b1-f99f-4608-955d-6d6f1f5eb8eb\",\"displayName\":\"App\\\\Jobs\\\\FetchSmartAnalysisJob\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":600,\"retryUntil\":null,\"data\":{\"commandName\":\"App\\\\Jobs\\\\FetchSmartAnalysisJob\",\"command\":\"O:30:\\\"App\\\\Jobs\\\\FetchSmartAnalysisJob\\\":1:{s:18:\\\"\\u0000*\\u0000smartAnalysisId\\\";i:1;}\",\"batchId\":null},\"createdAt\":1785967411,\"delay\":null}','Exception: smart_analysis key missing. in /var/www/html/app/Jobs/FetchSmartAnalysisJob.php:137\nStack trace:\n#0 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): App\\Jobs\\FetchSmartAnalysisJob->handle()\n#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#4 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#5 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(129): Illuminate\\Container\\Container->call(Array)\n#6 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Bus\\Dispatcher->Illuminate\\Bus\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#7 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#8 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(133): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#9 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(136): Illuminate\\Bus\\Dispatcher->dispatchNow(Object(App\\Jobs\\FetchSmartAnalysisJob), false)\n#10 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Queue\\CallQueuedHandler->Illuminate\\Queue\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#11 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#12 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(129): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#13 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(70): Illuminate\\Queue\\CallQueuedHandler->dispatchThroughMiddleware(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(App\\Jobs\\FetchSmartAnalysisJob))\n#14 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(102): Illuminate\\Queue\\CallQueuedHandler->call(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Array)\n#15 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(504): Illuminate\\Queue\\Jobs\\Job->fire()\n#16 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(454): Illuminate\\Queue\\Worker->process(\'database\', Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Queue\\WorkerOptions))\n#17 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(212): Illuminate\\Queue\\Worker->runJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), \'database\', Object(Illuminate\\Queue\\WorkerOptions))\n#18 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(149): Illuminate\\Queue\\Worker->daemon(\'database\', \'default\', Object(Illuminate\\Queue\\WorkerOptions))\n#19 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(132): Illuminate\\Queue\\Console\\WorkCommand->runWorker(\'database\', \'default\')\n#20 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Queue\\Console\\WorkCommand->handle()\n#21 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#22 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#23 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#24 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#25 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(211): Illuminate\\Container\\Container->call(Array)\n#26 /var/www/html/vendor/symfony/console/Command/Command.php(341): Illuminate\\Console\\Command->execute(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#27 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(180): Symfony\\Component\\Console\\Command\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#28 /var/www/html/vendor/symfony/console/Application.php(1117): Illuminate\\Console\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#29 /var/www/html/vendor/symfony/console/Application.php(356): Symfony\\Component\\Console\\Application->doRunCommand(Object(Illuminate\\Queue\\Console\\WorkCommand), Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#30 /var/www/html/vendor/symfony/console/Application.php(195): Symfony\\Component\\Console\\Application->doRun(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#31 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(198): Symfony\\Component\\Console\\Application->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#32 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(1235): Illuminate\\Foundation\\Console\\Kernel->handle(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#33 /var/www/html/artisan(16): Illuminate\\Foundation\\Application->handleCommand(Object(Symfony\\Component\\Console\\Input\\ArgvInput))\n#34 {main}','2026-08-05 22:03:32'),(3,'f551ebdb-6a6b-4a69-ac7b-59d9617bfbd6','database','default','{\"uuid\":\"f551ebdb-6a6b-4a69-ac7b-59d9617bfbd6\",\"displayName\":\"App\\\\Jobs\\\\FetchHealthTrendJob\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":600,\"retryUntil\":null,\"data\":{\"commandName\":\"App\\\\Jobs\\\\FetchHealthTrendJob\",\"command\":\"O:28:\\\"App\\\\Jobs\\\\FetchHealthTrendJob\\\":2:{s:16:\\\"\\u0000*\\u0000healthTrendId\\\";i:1;s:9:\\\"\\u0000*\\u0000period\\\";s:2:\\\"7d\\\";}\",\"batchId\":null},\"createdAt\":1785968613,\"delay\":null}','Exception: health_trends key not found in AI response. in /var/www/html/app/Jobs/FetchHealthTrendJob.php:142\nStack trace:\n#0 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): App\\Jobs\\FetchHealthTrendJob->handle()\n#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#4 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#5 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(129): Illuminate\\Container\\Container->call(Array)\n#6 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Bus\\Dispatcher->Illuminate\\Bus\\{closure}(Object(App\\Jobs\\FetchHealthTrendJob))\n#7 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchHealthTrendJob))\n#8 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(133): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#9 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(136): Illuminate\\Bus\\Dispatcher->dispatchNow(Object(App\\Jobs\\FetchHealthTrendJob), false)\n#10 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Queue\\CallQueuedHandler->Illuminate\\Queue\\{closure}(Object(App\\Jobs\\FetchHealthTrendJob))\n#11 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchHealthTrendJob))\n#12 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(129): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#13 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(70): Illuminate\\Queue\\CallQueuedHandler->dispatchThroughMiddleware(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(App\\Jobs\\FetchHealthTrendJob))\n#14 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(102): Illuminate\\Queue\\CallQueuedHandler->call(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Array)\n#15 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(504): Illuminate\\Queue\\Jobs\\Job->fire()\n#16 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(454): Illuminate\\Queue\\Worker->process(\'database\', Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Queue\\WorkerOptions))\n#17 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(212): Illuminate\\Queue\\Worker->runJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), \'database\', Object(Illuminate\\Queue\\WorkerOptions))\n#18 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(149): Illuminate\\Queue\\Worker->daemon(\'database\', \'default\', Object(Illuminate\\Queue\\WorkerOptions))\n#19 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(132): Illuminate\\Queue\\Console\\WorkCommand->runWorker(\'database\', \'default\')\n#20 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Queue\\Console\\WorkCommand->handle()\n#21 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#22 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#23 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#24 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#25 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(211): Illuminate\\Container\\Container->call(Array)\n#26 /var/www/html/vendor/symfony/console/Command/Command.php(341): Illuminate\\Console\\Command->execute(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#27 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(180): Symfony\\Component\\Console\\Command\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#28 /var/www/html/vendor/symfony/console/Application.php(1117): Illuminate\\Console\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#29 /var/www/html/vendor/symfony/console/Application.php(356): Symfony\\Component\\Console\\Application->doRunCommand(Object(Illuminate\\Queue\\Console\\WorkCommand), Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#30 /var/www/html/vendor/symfony/console/Application.php(195): Symfony\\Component\\Console\\Application->doRun(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#31 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(198): Symfony\\Component\\Console\\Application->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#32 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(1235): Illuminate\\Foundation\\Console\\Kernel->handle(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#33 /var/www/html/artisan(16): Illuminate\\Foundation\\Application->handleCommand(Object(Symfony\\Component\\Console\\Input\\ArgvInput))\n#34 {main}','2026-08-05 22:23:36'),(4,'7f0477ad-e683-4b9f-a5ed-3c16341a7a76','database','default','{\"uuid\":\"7f0477ad-e683-4b9f-a5ed-3c16341a7a76\",\"displayName\":\"App\\\\Jobs\\\\FetchHealthTrendJob\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":600,\"retryUntil\":null,\"data\":{\"commandName\":\"App\\\\Jobs\\\\FetchHealthTrendJob\",\"command\":\"O:28:\\\"App\\\\Jobs\\\\FetchHealthTrendJob\\\":2:{s:16:\\\"\\u0000*\\u0000healthTrendId\\\";i:2;s:9:\\\"\\u0000*\\u0000period\\\";s:3:\\\"30d\\\";}\",\"batchId\":null},\"createdAt\":1785968627,\"delay\":null}','Exception: health_trends key not found in AI response. in /var/www/html/app/Jobs/FetchHealthTrendJob.php:142\nStack trace:\n#0 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): App\\Jobs\\FetchHealthTrendJob->handle()\n#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#4 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#5 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(129): Illuminate\\Container\\Container->call(Array)\n#6 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Bus\\Dispatcher->Illuminate\\Bus\\{closure}(Object(App\\Jobs\\FetchHealthTrendJob))\n#7 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchHealthTrendJob))\n#8 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(133): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#9 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(136): Illuminate\\Bus\\Dispatcher->dispatchNow(Object(App\\Jobs\\FetchHealthTrendJob), false)\n#10 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Queue\\CallQueuedHandler->Illuminate\\Queue\\{closure}(Object(App\\Jobs\\FetchHealthTrendJob))\n#11 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchHealthTrendJob))\n#12 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(129): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#13 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(70): Illuminate\\Queue\\CallQueuedHandler->dispatchThroughMiddleware(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(App\\Jobs\\FetchHealthTrendJob))\n#14 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(102): Illuminate\\Queue\\CallQueuedHandler->call(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Array)\n#15 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(504): Illuminate\\Queue\\Jobs\\Job->fire()\n#16 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(454): Illuminate\\Queue\\Worker->process(\'database\', Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Queue\\WorkerOptions))\n#17 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(212): Illuminate\\Queue\\Worker->runJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), \'database\', Object(Illuminate\\Queue\\WorkerOptions))\n#18 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(149): Illuminate\\Queue\\Worker->daemon(\'database\', \'default\', Object(Illuminate\\Queue\\WorkerOptions))\n#19 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(132): Illuminate\\Queue\\Console\\WorkCommand->runWorker(\'database\', \'default\')\n#20 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Queue\\Console\\WorkCommand->handle()\n#21 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#22 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#23 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#24 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#25 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(211): Illuminate\\Container\\Container->call(Array)\n#26 /var/www/html/vendor/symfony/console/Command/Command.php(341): Illuminate\\Console\\Command->execute(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#27 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(180): Symfony\\Component\\Console\\Command\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#28 /var/www/html/vendor/symfony/console/Application.php(1117): Illuminate\\Console\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#29 /var/www/html/vendor/symfony/console/Application.php(356): Symfony\\Component\\Console\\Application->doRunCommand(Object(Illuminate\\Queue\\Console\\WorkCommand), Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#30 /var/www/html/vendor/symfony/console/Application.php(195): Symfony\\Component\\Console\\Application->doRun(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#31 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(198): Symfony\\Component\\Console\\Application->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#32 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(1235): Illuminate\\Foundation\\Console\\Kernel->handle(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#33 /var/www/html/artisan(16): Illuminate\\Foundation\\Application->handleCommand(Object(Symfony\\Component\\Console\\Input\\ArgvInput))\n#34 {main}','2026-08-05 22:23:48'),(5,'2c0306fe-68f9-4f94-80ae-cfcce5f943a4','database','default','{\"uuid\":\"2c0306fe-68f9-4f94-80ae-cfcce5f943a4\",\"displayName\":\"App\\\\Jobs\\\\FetchHealthTrendJob\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":600,\"retryUntil\":null,\"data\":{\"commandName\":\"App\\\\Jobs\\\\FetchHealthTrendJob\",\"command\":\"O:28:\\\"App\\\\Jobs\\\\FetchHealthTrendJob\\\":2:{s:16:\\\"\\u0000*\\u0000healthTrendId\\\";i:1;s:9:\\\"\\u0000*\\u0000period\\\";s:2:\\\"7d\\\";}\",\"batchId\":null},\"createdAt\":1785968634,\"delay\":null}','Exception: health_trends key not found in AI response. in /var/www/html/app/Jobs/FetchHealthTrendJob.php:142\nStack trace:\n#0 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): App\\Jobs\\FetchHealthTrendJob->handle()\n#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#4 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#5 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(129): Illuminate\\Container\\Container->call(Array)\n#6 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Bus\\Dispatcher->Illuminate\\Bus\\{closure}(Object(App\\Jobs\\FetchHealthTrendJob))\n#7 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchHealthTrendJob))\n#8 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(133): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#9 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(136): Illuminate\\Bus\\Dispatcher->dispatchNow(Object(App\\Jobs\\FetchHealthTrendJob), false)\n#10 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Queue\\CallQueuedHandler->Illuminate\\Queue\\{closure}(Object(App\\Jobs\\FetchHealthTrendJob))\n#11 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchHealthTrendJob))\n#12 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(129): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#13 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(70): Illuminate\\Queue\\CallQueuedHandler->dispatchThroughMiddleware(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(App\\Jobs\\FetchHealthTrendJob))\n#14 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(102): Illuminate\\Queue\\CallQueuedHandler->call(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Array)\n#15 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(504): Illuminate\\Queue\\Jobs\\Job->fire()\n#16 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(454): Illuminate\\Queue\\Worker->process(\'database\', Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Queue\\WorkerOptions))\n#17 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(212): Illuminate\\Queue\\Worker->runJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), \'database\', Object(Illuminate\\Queue\\WorkerOptions))\n#18 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(149): Illuminate\\Queue\\Worker->daemon(\'database\', \'default\', Object(Illuminate\\Queue\\WorkerOptions))\n#19 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(132): Illuminate\\Queue\\Console\\WorkCommand->runWorker(\'database\', \'default\')\n#20 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Queue\\Console\\WorkCommand->handle()\n#21 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#22 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#23 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#24 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#25 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(211): Illuminate\\Container\\Container->call(Array)\n#26 /var/www/html/vendor/symfony/console/Command/Command.php(341): Illuminate\\Console\\Command->execute(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#27 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(180): Symfony\\Component\\Console\\Command\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#28 /var/www/html/vendor/symfony/console/Application.php(1117): Illuminate\\Console\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#29 /var/www/html/vendor/symfony/console/Application.php(356): Symfony\\Component\\Console\\Application->doRunCommand(Object(Illuminate\\Queue\\Console\\WorkCommand), Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#30 /var/www/html/vendor/symfony/console/Application.php(195): Symfony\\Component\\Console\\Application->doRun(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#31 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(198): Symfony\\Component\\Console\\Application->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#32 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(1235): Illuminate\\Foundation\\Console\\Kernel->handle(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#33 /var/www/html/artisan(16): Illuminate\\Foundation\\Application->handleCommand(Object(Symfony\\Component\\Console\\Input\\ArgvInput))\n#34 {main}','2026-08-05 22:23:55'),(6,'61ebe2e7-032d-4e08-8ba7-05a1e85411e9','database','default','{\"uuid\":\"61ebe2e7-032d-4e08-8ba7-05a1e85411e9\",\"displayName\":\"App\\\\Jobs\\\\FetchSmartAnalysisJob\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":600,\"retryUntil\":null,\"data\":{\"commandName\":\"App\\\\Jobs\\\\FetchSmartAnalysisJob\",\"command\":\"O:30:\\\"App\\\\Jobs\\\\FetchSmartAnalysisJob\\\":1:{s:18:\\\"\\u0000*\\u0000smartAnalysisId\\\";i:2;}\",\"batchId\":null},\"createdAt\":1785985413,\"delay\":null}','Exception: smart_analysis key missing. in /var/www/html/app/Jobs/FetchSmartAnalysisJob.php:137\nStack trace:\n#0 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): App\\Jobs\\FetchSmartAnalysisJob->handle()\n#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#4 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#5 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(129): Illuminate\\Container\\Container->call(Array)\n#6 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Bus\\Dispatcher->Illuminate\\Bus\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#7 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#8 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(133): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#9 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(136): Illuminate\\Bus\\Dispatcher->dispatchNow(Object(App\\Jobs\\FetchSmartAnalysisJob), false)\n#10 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Queue\\CallQueuedHandler->Illuminate\\Queue\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#11 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#12 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(129): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#13 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(70): Illuminate\\Queue\\CallQueuedHandler->dispatchThroughMiddleware(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(App\\Jobs\\FetchSmartAnalysisJob))\n#14 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(102): Illuminate\\Queue\\CallQueuedHandler->call(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Array)\n#15 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(504): Illuminate\\Queue\\Jobs\\Job->fire()\n#16 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(454): Illuminate\\Queue\\Worker->process(\'database\', Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Queue\\WorkerOptions))\n#17 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(212): Illuminate\\Queue\\Worker->runJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), \'database\', Object(Illuminate\\Queue\\WorkerOptions))\n#18 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(149): Illuminate\\Queue\\Worker->daemon(\'database\', \'default\', Object(Illuminate\\Queue\\WorkerOptions))\n#19 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(132): Illuminate\\Queue\\Console\\WorkCommand->runWorker(\'database\', \'default\')\n#20 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Queue\\Console\\WorkCommand->handle()\n#21 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#22 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#23 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#24 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#25 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(211): Illuminate\\Container\\Container->call(Array)\n#26 /var/www/html/vendor/symfony/console/Command/Command.php(341): Illuminate\\Console\\Command->execute(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#27 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(180): Symfony\\Component\\Console\\Command\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#28 /var/www/html/vendor/symfony/console/Application.php(1117): Illuminate\\Console\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#29 /var/www/html/vendor/symfony/console/Application.php(356): Symfony\\Component\\Console\\Application->doRunCommand(Object(Illuminate\\Queue\\Console\\WorkCommand), Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#30 /var/www/html/vendor/symfony/console/Application.php(195): Symfony\\Component\\Console\\Application->doRun(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#31 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(198): Symfony\\Component\\Console\\Application->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#32 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(1235): Illuminate\\Foundation\\Console\\Kernel->handle(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#33 /var/www/html/artisan(16): Illuminate\\Foundation\\Application->handleCommand(Object(Symfony\\Component\\Console\\Input\\ArgvInput))\n#34 {main}','2026-08-06 03:03:39'),(7,'e71d2225-21e4-4f12-be42-9a18081f16d4','database','default','{\"uuid\":\"e71d2225-21e4-4f12-be42-9a18081f16d4\",\"displayName\":\"App\\\\Jobs\\\\FetchSmartAnalysisJob\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":600,\"retryUntil\":null,\"data\":{\"commandName\":\"App\\\\Jobs\\\\FetchSmartAnalysisJob\",\"command\":\"O:30:\\\"App\\\\Jobs\\\\FetchSmartAnalysisJob\\\":1:{s:18:\\\"\\u0000*\\u0000smartAnalysisId\\\";i:2;}\",\"batchId\":null},\"createdAt\":1785985808,\"delay\":null}','Exception: smart_analysis key missing. in /var/www/html/app/Jobs/FetchSmartAnalysisJob.php:137\nStack trace:\n#0 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): App\\Jobs\\FetchSmartAnalysisJob->handle()\n#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#4 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#5 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(129): Illuminate\\Container\\Container->call(Array)\n#6 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Bus\\Dispatcher->Illuminate\\Bus\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#7 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#8 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(133): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#9 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(136): Illuminate\\Bus\\Dispatcher->dispatchNow(Object(App\\Jobs\\FetchSmartAnalysisJob), false)\n#10 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Queue\\CallQueuedHandler->Illuminate\\Queue\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#11 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#12 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(129): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#13 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(70): Illuminate\\Queue\\CallQueuedHandler->dispatchThroughMiddleware(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(App\\Jobs\\FetchSmartAnalysisJob))\n#14 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(102): Illuminate\\Queue\\CallQueuedHandler->call(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Array)\n#15 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(504): Illuminate\\Queue\\Jobs\\Job->fire()\n#16 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(454): Illuminate\\Queue\\Worker->process(\'database\', Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Queue\\WorkerOptions))\n#17 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(212): Illuminate\\Queue\\Worker->runJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), \'database\', Object(Illuminate\\Queue\\WorkerOptions))\n#18 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(149): Illuminate\\Queue\\Worker->daemon(\'database\', \'default\', Object(Illuminate\\Queue\\WorkerOptions))\n#19 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(132): Illuminate\\Queue\\Console\\WorkCommand->runWorker(\'database\', \'default\')\n#20 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Queue\\Console\\WorkCommand->handle()\n#21 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#22 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#23 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#24 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#25 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(211): Illuminate\\Container\\Container->call(Array)\n#26 /var/www/html/vendor/symfony/console/Command/Command.php(341): Illuminate\\Console\\Command->execute(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#27 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(180): Symfony\\Component\\Console\\Command\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#28 /var/www/html/vendor/symfony/console/Application.php(1117): Illuminate\\Console\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#29 /var/www/html/vendor/symfony/console/Application.php(356): Symfony\\Component\\Console\\Application->doRunCommand(Object(Illuminate\\Queue\\Console\\WorkCommand), Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#30 /var/www/html/vendor/symfony/console/Application.php(195): Symfony\\Component\\Console\\Application->doRun(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#31 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(198): Symfony\\Component\\Console\\Application->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#32 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(1235): Illuminate\\Foundation\\Console\\Kernel->handle(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#33 /var/www/html/artisan(16): Illuminate\\Foundation\\Application->handleCommand(Object(Symfony\\Component\\Console\\Input\\ArgvInput))\n#34 {main}','2026-08-06 03:10:11'),(8,'1afdb88d-0692-4aa1-9f95-8bcf6c57ab5c','database','default','{\"uuid\":\"1afdb88d-0692-4aa1-9f95-8bcf6c57ab5c\",\"displayName\":\"App\\\\Jobs\\\\FetchSmartAnalysisJob\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":600,\"retryUntil\":null,\"data\":{\"commandName\":\"App\\\\Jobs\\\\FetchSmartAnalysisJob\",\"command\":\"O:30:\\\"App\\\\Jobs\\\\FetchSmartAnalysisJob\\\":1:{s:18:\\\"\\u0000*\\u0000smartAnalysisId\\\";i:2;}\",\"batchId\":null},\"createdAt\":1785985822,\"delay\":null}','Exception: smart_analysis key missing. in /var/www/html/app/Jobs/FetchSmartAnalysisJob.php:137\nStack trace:\n#0 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): App\\Jobs\\FetchSmartAnalysisJob->handle()\n#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#4 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#5 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(129): Illuminate\\Container\\Container->call(Array)\n#6 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Bus\\Dispatcher->Illuminate\\Bus\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#7 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#8 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(133): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#9 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(136): Illuminate\\Bus\\Dispatcher->dispatchNow(Object(App\\Jobs\\FetchSmartAnalysisJob), false)\n#10 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Queue\\CallQueuedHandler->Illuminate\\Queue\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#11 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#12 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(129): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#13 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(70): Illuminate\\Queue\\CallQueuedHandler->dispatchThroughMiddleware(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(App\\Jobs\\FetchSmartAnalysisJob))\n#14 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(102): Illuminate\\Queue\\CallQueuedHandler->call(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Array)\n#15 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(504): Illuminate\\Queue\\Jobs\\Job->fire()\n#16 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(454): Illuminate\\Queue\\Worker->process(\'database\', Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Queue\\WorkerOptions))\n#17 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(212): Illuminate\\Queue\\Worker->runJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), \'database\', Object(Illuminate\\Queue\\WorkerOptions))\n#18 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(149): Illuminate\\Queue\\Worker->daemon(\'database\', \'default\', Object(Illuminate\\Queue\\WorkerOptions))\n#19 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(132): Illuminate\\Queue\\Console\\WorkCommand->runWorker(\'database\', \'default\')\n#20 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Queue\\Console\\WorkCommand->handle()\n#21 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#22 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#23 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#24 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#25 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(211): Illuminate\\Container\\Container->call(Array)\n#26 /var/www/html/vendor/symfony/console/Command/Command.php(341): Illuminate\\Console\\Command->execute(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#27 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(180): Symfony\\Component\\Console\\Command\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#28 /var/www/html/vendor/symfony/console/Application.php(1117): Illuminate\\Console\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#29 /var/www/html/vendor/symfony/console/Application.php(356): Symfony\\Component\\Console\\Application->doRunCommand(Object(Illuminate\\Queue\\Console\\WorkCommand), Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#30 /var/www/html/vendor/symfony/console/Application.php(195): Symfony\\Component\\Console\\Application->doRun(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#31 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(198): Symfony\\Component\\Console\\Application->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#32 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(1235): Illuminate\\Foundation\\Console\\Kernel->handle(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#33 /var/www/html/artisan(16): Illuminate\\Foundation\\Application->handleCommand(Object(Symfony\\Component\\Console\\Input\\ArgvInput))\n#34 {main}','2026-08-06 03:10:23'),(9,'080add17-b27a-4980-8bfd-6c51c29562f7','database','default','{\"uuid\":\"080add17-b27a-4980-8bfd-6c51c29562f7\",\"displayName\":\"App\\\\Jobs\\\\FetchHealthTrendJob\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":600,\"retryUntil\":null,\"data\":{\"commandName\":\"App\\\\Jobs\\\\FetchHealthTrendJob\",\"command\":\"O:28:\\\"App\\\\Jobs\\\\FetchHealthTrendJob\\\":2:{s:16:\\\"\\u0000*\\u0000healthTrendId\\\";i:3;s:9:\\\"\\u0000*\\u0000period\\\";s:2:\\\"7d\\\";}\",\"batchId\":null},\"createdAt\":1785985937,\"delay\":null}','Exception: health_trends key not found in AI response. in /var/www/html/app/Jobs/FetchHealthTrendJob.php:142\nStack trace:\n#0 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): App\\Jobs\\FetchHealthTrendJob->handle()\n#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#4 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#5 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(129): Illuminate\\Container\\Container->call(Array)\n#6 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Bus\\Dispatcher->Illuminate\\Bus\\{closure}(Object(App\\Jobs\\FetchHealthTrendJob))\n#7 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchHealthTrendJob))\n#8 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(133): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#9 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(136): Illuminate\\Bus\\Dispatcher->dispatchNow(Object(App\\Jobs\\FetchHealthTrendJob), false)\n#10 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Queue\\CallQueuedHandler->Illuminate\\Queue\\{closure}(Object(App\\Jobs\\FetchHealthTrendJob))\n#11 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchHealthTrendJob))\n#12 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(129): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#13 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(70): Illuminate\\Queue\\CallQueuedHandler->dispatchThroughMiddleware(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(App\\Jobs\\FetchHealthTrendJob))\n#14 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(102): Illuminate\\Queue\\CallQueuedHandler->call(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Array)\n#15 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(504): Illuminate\\Queue\\Jobs\\Job->fire()\n#16 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(454): Illuminate\\Queue\\Worker->process(\'database\', Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Queue\\WorkerOptions))\n#17 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(212): Illuminate\\Queue\\Worker->runJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), \'database\', Object(Illuminate\\Queue\\WorkerOptions))\n#18 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(149): Illuminate\\Queue\\Worker->daemon(\'database\', \'default\', Object(Illuminate\\Queue\\WorkerOptions))\n#19 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(132): Illuminate\\Queue\\Console\\WorkCommand->runWorker(\'database\', \'default\')\n#20 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Queue\\Console\\WorkCommand->handle()\n#21 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#22 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#23 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#24 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#25 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(211): Illuminate\\Container\\Container->call(Array)\n#26 /var/www/html/vendor/symfony/console/Command/Command.php(341): Illuminate\\Console\\Command->execute(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#27 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(180): Symfony\\Component\\Console\\Command\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#28 /var/www/html/vendor/symfony/console/Application.php(1117): Illuminate\\Console\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#29 /var/www/html/vendor/symfony/console/Application.php(356): Symfony\\Component\\Console\\Application->doRunCommand(Object(Illuminate\\Queue\\Console\\WorkCommand), Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#30 /var/www/html/vendor/symfony/console/Application.php(195): Symfony\\Component\\Console\\Application->doRun(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#31 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(198): Symfony\\Component\\Console\\Application->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#32 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(1235): Illuminate\\Foundation\\Console\\Kernel->handle(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#33 /var/www/html/artisan(16): Illuminate\\Foundation\\Application->handleCommand(Object(Symfony\\Component\\Console\\Input\\ArgvInput))\n#34 {main}','2026-08-06 03:12:20'),(10,'16f47428-c0be-4969-8540-ee7d4ccbe96f','database','default','{\"uuid\":\"16f47428-c0be-4969-8540-ee7d4ccbe96f\",\"displayName\":\"App\\\\Jobs\\\\FetchSmartAnalysisJob\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":600,\"retryUntil\":null,\"data\":{\"commandName\":\"App\\\\Jobs\\\\FetchSmartAnalysisJob\",\"command\":\"O:30:\\\"App\\\\Jobs\\\\FetchSmartAnalysisJob\\\":1:{s:18:\\\"\\u0000*\\u0000smartAnalysisId\\\";i:2;}\",\"batchId\":null},\"createdAt\":1785986273,\"delay\":null}','Exception: smart_analysis key missing. in /var/www/html/app/Jobs/FetchSmartAnalysisJob.php:137\nStack trace:\n#0 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): App\\Jobs\\FetchSmartAnalysisJob->handle()\n#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#2 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#3 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#4 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#5 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(129): Illuminate\\Container\\Container->call(Array)\n#6 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Bus\\Dispatcher->Illuminate\\Bus\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#7 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#8 /var/www/html/vendor/laravel/framework/src/Illuminate/Bus/Dispatcher.php(133): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#9 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(136): Illuminate\\Bus\\Dispatcher->dispatchNow(Object(App\\Jobs\\FetchSmartAnalysisJob), false)\n#10 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\\Queue\\CallQueuedHandler->Illuminate\\Queue\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#11 /var/www/html/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}(Object(App\\Jobs\\FetchSmartAnalysisJob))\n#12 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(129): Illuminate\\Pipeline\\Pipeline->then(Object(Closure))\n#13 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(70): Illuminate\\Queue\\CallQueuedHandler->dispatchThroughMiddleware(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(App\\Jobs\\FetchSmartAnalysisJob))\n#14 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(102): Illuminate\\Queue\\CallQueuedHandler->call(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Array)\n#15 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(504): Illuminate\\Queue\\Jobs\\Job->fire()\n#16 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(454): Illuminate\\Queue\\Worker->process(\'database\', Object(Illuminate\\Queue\\Jobs\\DatabaseJob), Object(Illuminate\\Queue\\WorkerOptions))\n#17 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Worker.php(212): Illuminate\\Queue\\Worker->runJob(Object(Illuminate\\Queue\\Jobs\\DatabaseJob), \'database\', Object(Illuminate\\Queue\\WorkerOptions))\n#18 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(149): Illuminate\\Queue\\Worker->daemon(\'database\', \'default\', Object(Illuminate\\Queue\\WorkerOptions))\n#19 /var/www/html/vendor/laravel/framework/src/Illuminate/Queue/Console/WorkCommand.php(132): Illuminate\\Queue\\Console\\WorkCommand->runWorker(\'database\', \'default\')\n#20 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): Illuminate\\Queue\\Console\\WorkCommand->handle()\n#21 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Util.php(43): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#22 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(96): Illuminate\\Container\\Util::unwrapIfClosure(Object(Closure))\n#23 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod(Object(Illuminate\\Foundation\\Application), Array, Object(Closure))\n#24 /var/www/html/vendor/laravel/framework/src/Illuminate/Container/Container.php(799): Illuminate\\Container\\BoundMethod::call(Object(Illuminate\\Foundation\\Application), Array, Array, NULL)\n#25 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(211): Illuminate\\Container\\Container->call(Array)\n#26 /var/www/html/vendor/symfony/console/Command/Command.php(341): Illuminate\\Console\\Command->execute(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#27 /var/www/html/vendor/laravel/framework/src/Illuminate/Console/Command.php(180): Symfony\\Component\\Console\\Command\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Illuminate\\Console\\OutputStyle))\n#28 /var/www/html/vendor/symfony/console/Application.php(1117): Illuminate\\Console\\Command->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#29 /var/www/html/vendor/symfony/console/Application.php(356): Symfony\\Component\\Console\\Application->doRunCommand(Object(Illuminate\\Queue\\Console\\WorkCommand), Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#30 /var/www/html/vendor/symfony/console/Application.php(195): Symfony\\Component\\Console\\Application->doRun(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#31 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php(198): Symfony\\Component\\Console\\Application->run(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#32 /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(1235): Illuminate\\Foundation\\Console\\Kernel->handle(Object(Symfony\\Component\\Console\\Input\\ArgvInput), Object(Symfony\\Component\\Console\\Output\\ConsoleOutput))\n#33 /var/www/html/artisan(16): Illuminate\\Foundation\\Application->handleCommand(Object(Symfony\\Component\\Console\\Input\\ArgvInput))\n#34 {main}','2026-08-06 03:17:54');
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fertility_events`
--

DROP TABLE IF EXISTS `fertility_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fertility_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` bigint unsigned NOT NULL,
  `event_date` date NOT NULL,
  `event_type` enum('fertile_window_start','fertile_window_end','lh_surge','ovulation_predicted','ovulation_confirmed','implantation_window','missed_period') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cycle_day` tinyint unsigned DEFAULT NULL,
  `source` enum('calendar','bbt','opk','mucus','ai','combined') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'calendar',
  `priority_level` tinyint unsigned NOT NULL DEFAULT '1',
  `message` text COLLATE utf8mb4_unicode_ci,
  `is_confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fertility_events_cycle_id_foreign` (`cycle_id`),
  KEY `fertility_events_event_type_index` (`event_type`),
  KEY `fertility_events_event_date_index` (`event_date`),
  KEY `fertility_events_priority_level_index` (`priority_level`),
  CONSTRAINT `fertility_events_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fertility_events`
--

LOCK TABLES `fertility_events` WRITE;
/*!40000 ALTER TABLE `fertility_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `fertility_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `health_goal_profile`
--

DROP TABLE IF EXISTS `health_goal_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `health_goal_profile` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `health_goal_id` bigint unsigned NOT NULL,
  `profile_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `health_goal_profile_health_goal_id_foreign` (`health_goal_id`),
  KEY `health_goal_profile_profile_id_foreign` (`profile_id`),
  CONSTRAINT `health_goal_profile_health_goal_id_foreign` FOREIGN KEY (`health_goal_id`) REFERENCES `health_goals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `health_goal_profile_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `health_goal_profile`
--

LOCK TABLES `health_goal_profile` WRITE;
/*!40000 ALTER TABLE `health_goal_profile` DISABLE KEYS */;
INSERT INTO `health_goal_profile` VALUES (1,8,1,'2026-08-05 21:59:59','2026-08-05 21:59:59'),(2,5,1,'2026-08-05 21:59:59','2026-08-05 21:59:59'),(3,2,1,'2026-08-05 21:59:59','2026-08-05 21:59:59'),(4,1,2,'2026-08-05 22:03:31','2026-08-05 22:03:31'),(5,2,2,'2026-08-05 22:03:31','2026-08-05 22:03:31'),(6,5,2,'2026-08-05 22:03:31','2026-08-05 22:03:31'),(7,8,2,'2026-08-05 22:03:31','2026-08-05 22:03:31'),(8,9,2,'2026-08-05 22:03:31','2026-08-05 22:03:31'),(9,6,2,'2026-08-05 22:03:31','2026-08-05 22:03:31'),(10,6,3,'2026-08-06 03:03:31','2026-08-06 03:03:31'),(11,7,3,'2026-08-06 03:03:31','2026-08-06 03:03:31');
/*!40000 ALTER TABLE `health_goal_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `health_goals`
--

DROP TABLE IF EXISTS `health_goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `health_goals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `health_goals`
--

LOCK TABLES `health_goals` WRITE;
/*!40000 ALTER TABLE `health_goals` DISABLE KEYS */;
INSERT INTO `health_goals` VALUES (1,'Hormonal Balance',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(2,'Better Sleep',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(3,'Weight Management',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(4,'Fertility',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(5,'Skin',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(6,'Stress Reduction',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(7,'Athletic Performance',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(8,'Healthy Aging',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(9,'Energy Boost',1,'2026-08-05 08:29:49','2026-08-05 08:29:49');
/*!40000 ALTER TABLE `health_goals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `health_logs`
--

DROP TABLE IF EXISTS `health_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `health_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `log_date` date NOT NULL,
  `mood` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `energy_level` enum('Very Low','Low','Moderate','High','Very High') COLLATE utf8mb4_unicode_ci NOT NULL,
  `symptoms` json DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `health_logs_user_id_log_date_unique` (`user_id`,`log_date`),
  CONSTRAINT `health_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `health_logs`
--

LOCK TABLES `health_logs` WRITE;
/*!40000 ALTER TABLE `health_logs` DISABLE KEYS */;
INSERT INTO `health_logs` VALUES (1,4,'2026-08-05','?','Moderate','[\"Brain fog\", \"Headache\", \"Back pain\", \"Fatigue\", \"Insomnia\"]',NULL,'2026-08-05 22:27:09','2026-08-05 22:27:09');
/*!40000 ALTER TABLE `health_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `health_trends`
--

DROP TABLE IF EXISTS `health_trends`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `health_trends` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `period` enum('7d','30d') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '30d',
  `range_options` json DEFAULT NULL,
  `sleep_energy_correlation_chart` json DEFAULT NULL,
  `sleep_energy_correlation_diagram` json DEFAULT NULL,
  `hormone_mood` json DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `health_trends_user_id_foreign` (`user_id`),
  CONSTRAINT `health_trends_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `health_trends`
--

LOCK TABLES `health_trends` WRITE;
/*!40000 ALTER TABLE `health_trends` DISABLE KEYS */;
INSERT INTO `health_trends` VALUES (1,4,NULL,'7d',NULL,NULL,NULL,NULL,'failed','2026-08-05 22:23:33','2026-08-05 22:23:55'),(2,4,NULL,'30d',NULL,NULL,NULL,NULL,'failed','2026-08-05 22:23:47','2026-08-05 22:23:48'),(3,8,NULL,'7d',NULL,NULL,NULL,NULL,'failed','2026-08-06 03:12:17','2026-08-06 03:12:20');
/*!40000 ALTER TABLE `health_trends` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hormone_snapshots`
--

DROP TABLE IF EXISTS `hormone_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hormone_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` bigint unsigned NOT NULL,
  `snapshot_date` date NOT NULL,
  `estrogen` enum('very_low','low','moderate','high','peak') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `progesterone` enum('very_low','low','moderate','high','peak') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lh` enum('very_low','low','moderate','high','peak') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fsh` enum('very_low','low','moderate','high') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modeled` tinyint(1) NOT NULL DEFAULT '1',
  `source` enum('phase_model','lab','ai','wearable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'phase_model',
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hormone_snapshots_cycle_id_snapshot_date_unique` (`cycle_id`,`snapshot_date`),
  CONSTRAINT `hormone_snapshots_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hormone_snapshots`
--

LOCK TABLES `hormone_snapshots` WRITE;
/*!40000 ALTER TABLE `hormone_snapshots` DISABLE KEYS */;
/*!40000 ALTER TABLE `hormone_snapshots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `intercourse_logs`
--

DROP TABLE IF EXISTS `intercourse_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `intercourse_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` bigint unsigned NOT NULL,
  `log_date` date NOT NULL,
  `protected` tinyint(1) NOT NULL DEFAULT '0',
  `ejaculation` tinyint(1) NOT NULL DEFAULT '1',
  `inside_fertile_window` tinyint(1) NOT NULL DEFAULT '0',
  `trying_to_conceive` tinyint(1) NOT NULL DEFAULT '1',
  `cycle_day` tinyint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `intercourse_logs_cycle_id_log_date_unique` (`cycle_id`,`log_date`),
  KEY `intercourse_logs_log_date_index` (`log_date`),
  KEY `intercourse_logs_trying_to_conceive_index` (`trying_to_conceive`),
  CONSTRAINT `intercourse_logs_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `intercourse_logs`
--

LOCK TABLES `intercourse_logs` WRITE;
/*!40000 ALTER TABLE `intercourse_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `intercourse_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lab_reports`
--

DROP TABLE IF EXISTS `lab_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lab_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `lab_report` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panel` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `biomarkers` json DEFAULT NULL,
  `ai_insights` json DEFAULT NULL,
  `next_steps` json DEFAULT NULL,
  `analysis_status` enum('pending','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lab_reports_user_id_foreign` (`user_id`),
  CONSTRAINT `lab_reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_reports`
--

LOCK TABLES `lab_reports` WRITE;
/*!40000 ALTER TABLE `lab_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `lab_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `life_journey_features`
--

DROP TABLE IF EXISTS `life_journey_features`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `life_journey_features` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `life_journey_id` bigint unsigned NOT NULL,
  `feature_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `life_journey_features_life_journey_id_foreign` (`life_journey_id`),
  CONSTRAINT `life_journey_features_life_journey_id_foreign` FOREIGN KEY (`life_journey_id`) REFERENCES `life_journeys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=211 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `life_journey_features`
--

LOCK TABLES `life_journey_features` WRITE;
/*!40000 ALTER TABLE `life_journey_features` DISABLE KEYS */;
INSERT INTO `life_journey_features` VALUES (160,1,'Skin Health Tracking','2026-08-06 04:01:25','2026-08-06 04:01:25'),(162,1,'Hydration Monitoring','2026-08-06 04:01:25','2026-08-06 04:01:25'),(163,1,'Energy & Vitality Scores','2026-08-06 04:01:25','2026-08-06 04:01:25'),(165,1,'Sleep Impact Analysis','2026-08-06 04:01:25','2026-08-06 04:01:25'),(167,1,'Skin Trend Monitoring','2026-08-06 04:01:25','2026-08-06 04:01:25'),(170,2,'Cycle Tracking','2026-08-06 04:01:25','2026-08-06 04:01:25'),(172,2,'Ovulation Prediction','2026-08-06 04:01:25','2026-08-06 04:01:25'),(173,2,'Fertility Window Detection','2026-08-06 04:01:25','2026-08-06 04:01:25'),(175,2,'Hormone Pattern Analysis','2026-08-06 04:01:25','2026-08-06 04:01:25'),(177,2,'Cycle Forecasting','2026-08-06 04:01:25','2026-08-06 04:01:25'),(181,3,'Training Load Monitoring','2026-08-06 04:01:25','2026-08-06 04:01:25'),(182,3,'Recovery Analysis','2026-08-06 04:01:25','2026-08-06 04:01:25'),(183,3,'HRV Tracking','2026-08-06 04:01:25','2026-08-06 04:01:25'),(185,3,'Cycle-Based Training Plans','2026-08-06 04:01:25','2026-08-06 04:01:25'),(187,3,'Fatigue Detection','2026-08-06 04:01:25','2026-08-06 04:01:25'),(191,4,'Hot Flash Tracking','2026-08-06 04:01:25','2026-08-06 04:01:25'),(192,4,'Sleep Disturbance Monitoring','2026-08-06 04:01:25','2026-08-06 04:01:25'),(194,4,'Mood Changes','2026-08-06 04:01:25','2026-08-06 04:01:25'),(196,4,'Hormonal Trend Analysis','2026-08-06 04:01:25','2026-08-06 04:01:25'),(198,4,'Menopause Education','2026-08-06 04:01:25','2026-08-06 04:01:25'),(201,5,'Week Tracking','2026-08-06 04:01:25','2026-08-06 04:01:25'),(202,5,'Symptom Monitoring','2026-08-06 04:01:25','2026-08-06 04:01:25'),(203,5,'Health Checkpoints','2026-08-06 04:01:25','2026-08-06 04:01:25'),(204,5,'Sleep Tracking','2026-08-06 04:01:25','2026-08-06 04:01:25'),(205,5,'Nutrition Reminders','2026-08-06 04:01:25','2026-08-06 04:01:25'),(206,6,'Healthy Aging Insights','2026-08-06 04:01:25','2026-08-06 04:01:25'),(207,6,'Cognitive Wellness Monitoring','2026-08-06 04:01:25','2026-08-06 04:01:25'),(208,6,'Vitality Scoring','2026-08-06 04:01:25','2026-08-06 04:01:25'),(209,6,'Mobility Trends','2026-08-06 04:01:25','2026-08-06 04:01:25'),(210,6,'Preventive Health Signals','2026-08-06 04:01:25','2026-08-06 04:01:25');
/*!40000 ALTER TABLE `life_journey_features` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `life_journey_profile`
--

DROP TABLE IF EXISTS `life_journey_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `life_journey_profile` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `life_journey_id` bigint unsigned NOT NULL,
  `profile_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `life_journey_profile_life_journey_id_foreign` (`life_journey_id`),
  KEY `life_journey_profile_profile_id_foreign` (`profile_id`),
  CONSTRAINT `life_journey_profile_life_journey_id_foreign` FOREIGN KEY (`life_journey_id`) REFERENCES `life_journeys` (`id`) ON DELETE CASCADE,
  CONSTRAINT `life_journey_profile_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `life_journey_profile`
--

LOCK TABLES `life_journey_profile` WRITE;
/*!40000 ALTER TABLE `life_journey_profile` DISABLE KEYS */;
INSERT INTO `life_journey_profile` VALUES (1,4,1,'2026-08-05 21:59:59','2026-08-05 21:59:59'),(2,1,1,'2026-08-05 21:59:59','2026-08-05 21:59:59'),(3,6,1,'2026-08-05 21:59:59','2026-08-05 21:59:59'),(4,1,2,'2026-08-05 22:03:31','2026-08-05 22:03:31'),(5,4,2,'2026-08-05 22:03:31','2026-08-05 22:03:31'),(6,6,2,'2026-08-05 22:03:31','2026-08-05 22:03:31'),(7,1,3,'2026-08-06 03:03:31','2026-08-06 03:03:31'),(8,2,3,'2026-08-06 03:03:31','2026-08-06 03:03:31'),(9,6,3,'2026-08-06 03:03:31','2026-08-06 03:03:31');
/*!40000 ALTER TABLE `life_journey_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `life_journeys`
--

DROP TABLE IF EXISTS `life_journeys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `life_journeys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `icon` text COLLATE utf8mb4_unicode_ci,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `life_journeys`
--

LOCK TABLES `life_journeys` WRITE;
/*!40000 ALTER TABLE `life_journeys` DISABLE KEYS */;
INSERT INTO `life_journeys` VALUES (1,'https://api.fightthenumber.com/backend/journeys/beauty.png','Beauty & Radiance','Skin, hair, confidence - the entry wedge.','Recent sleep disruption may be contributing to increased skin redness. A consistent 10pm bedtime could restore your glow within 5-7 days.',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(2,'https://api.fightthenumber.com/backend/journeys/tracking.png','Cycle & Fertility','Cycle, ovulation, conception planning.','You\'re in your Ovulatory Phase (Day 14). Peak fertility window opens in the next 24-48 hours. Estrogen is peaking — expect elevated energy and confidence.',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(3,'https://api.fightthenumber.com/backend/journeys/athlete.png','Athlete','Training, recovery, performance by hormone phase.','Recovery metrics suggest reducing training intensity today. Your HRV indicates moderate nervous system fatigue — a light session will serve you better.',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(4,'https://api.fightthenumber.com/backend/journeys/menopause.png','Peri / Menopause & Vitality','Symptom navigation and long-term vitality.','Hot flash frequency is down 20% this week — a positive trend. Evening flashes correlate strongly with high-stress days. A consistent 9pm wind-down routine could reduce your overnight flash count within 5-7 days.',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(5,'https://api.fightthenumber.com/backend/journeys/pregnancy.png','Pregnancy & Postpartum','Prenatal through recovery, supported.','Week 24 — your baby is the size of a corn cob! Focus on sleep positioning (left side preferred) and stay well hydrated.',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(6,'https://api.fightthenumber.com/backend/journeys/lifelong.png','Lifelong Thriving','Prevention and healthspan for the long run.','Your long-term vitality score has improved 8 points over 6 weeks — sustained sleep quality is the primary driver. Consistent HRV improvement (+12ms) signals cardiovascular adaptation. Your bone density scan is overdue — the preventive window is now.',1,'2026-08-05 08:29:49','2026-08-05 08:29:49');
/*!40000 ALTER TABLE `life_journeys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `life_stages`
--

DROP TABLE IF EXISTS `life_stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `life_stages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `life_stages`
--

LOCK TABLES `life_stages` WRITE;
/*!40000 ALTER TABLE `life_stages` DISABLE KEYS */;
INSERT INTO `life_stages` VALUES (1,'Teen',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(2,'Reproductive Years',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(3,'Pregnancy',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(4,'Postpartum',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(5,'Perimenopause',1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(6,'Menopause',1,'2026-08-05 08:29:49','2026-08-05 08:29:49');
/*!40000 ALTER TABLE `life_stages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `marketplace_life_stage_category`
--

DROP TABLE IF EXISTS `marketplace_life_stage_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_life_stage_category` (
  `marketplace_life_stage_id` tinyint unsigned NOT NULL,
  `category_id` smallint unsigned NOT NULL,
  `display_priority` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`marketplace_life_stage_id`,`category_id`),
  KEY `mkt_life_stage_category_category_fk` (`category_id`),
  CONSTRAINT `mkt_life_stage_category_category_fk` FOREIGN KEY (`category_id`) REFERENCES `provider_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mkt_life_stage_category_stage_fk` FOREIGN KEY (`marketplace_life_stage_id`) REFERENCES `marketplace_life_stages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marketplace_life_stage_category`
--

LOCK TABLES `marketplace_life_stage_category` WRITE;
/*!40000 ALTER TABLE `marketplace_life_stage_category` DISABLE KEYS */;
/*!40000 ALTER TABLE `marketplace_life_stage_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `marketplace_life_stages`
--

DROP TABLE IF EXISTS `marketplace_life_stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_life_stages` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketplace_life_stages_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marketplace_life_stages`
--

LOCK TABLES `marketplace_life_stages` WRITE;
/*!40000 ALTER TABLE `marketplace_life_stages` DISABLE KEYS */;
/*!40000 ALTER TABLE `marketplace_life_stages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menstrual_cycles`
--

DROP TABLE IF EXISTS `menstrual_cycles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menstrual_cycles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `period_start_date` date DEFAULT NULL,
  `period_end_date` date DEFAULT NULL,
  `current_cycle_day` tinyint unsigned DEFAULT NULL,
  `cycle_length` tinyint unsigned DEFAULT NULL,
  `period_length` tinyint unsigned DEFAULT NULL,
  `predicted_ovulation_day` tinyint unsigned DEFAULT NULL,
  `confirmed_ovulation_day` tinyint unsigned DEFAULT NULL,
  `predicted_peak_day` tinyint unsigned DEFAULT NULL,
  `fertile_start_day` tinyint unsigned DEFAULT NULL,
  `fertile_end_day` tinyint unsigned DEFAULT NULL,
  `current_phase` enum('menstrual','follicular','ovulatory','luteal') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prediction_source` enum('calendar','bbt','opk','mucus','combined') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'calendar',
  `is_confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `is_completed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `menstrual_cycles_user_id_period_start_date_index` (`user_id`,`period_start_date`),
  KEY `menstrual_cycles_current_phase_index` (`current_phase`),
  KEY `menstrual_cycles_is_completed_index` (`is_completed`),
  KEY `menstrual_cycles_current_cycle_day_index` (`current_cycle_day`),
  CONSTRAINT `menstrual_cycles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menstrual_cycles`
--

LOCK TABLES `menstrual_cycles` WRITE;
/*!40000 ALTER TABLE `menstrual_cycles` DISABLE KEYS */;
INSERT INTO `menstrual_cycles` VALUES (1,2,'2026-08-05','2026-08-10',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'calendar',0,0,NULL,NULL);
/*!40000 ALTER TABLE `menstrual_cycles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `metros`
--

DROP TABLE IF EXISTS `metros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `metros` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `centroid` point NOT NULL /*!80003 SRID 4326 */,
  `radius_km` smallint NOT NULL,
  `density_tier` tinyint NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `metros`
--

LOCK TABLES `metros` WRITE;
/*!40000 ALTER TABLE `metros` DISABLE KEYS */;
/*!40000 ALTER TABLE `metros` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0000_01_01_000000_create_settings_table',1),(2,'0001_01_01_000000_create_users_table',1),(3,'0001_01_01_000001_create_cache_table',1),(4,'0001_01_01_000002_create_jobs_table',1),(5,'2026_06_10_081600_create_activities_table',1),(6,'2026_06_10_081600_create_connect_devices_table',1),(7,'2026_06_10_081600_create_health_goals_table',1),(8,'2026_06_10_081600_create_life_journeys_table',1),(9,'2026_06_10_081600_create_life_stages_table',1),(10,'2026_06_10_081600_create_profiles_table',1),(11,'2026_06_10_081601_create_health_goal_profile_table',1),(12,'2026_06_10_081602_create_life_journey_profile_table',1),(13,'2026_06_10_081603_create_connect_device_profile_table',1),(14,'2026_06_11_073731_create_permission_tables',1),(15,'2026_06_11_073821_create_personal_access_tokens_table',1),(16,'2026_06_13_014524_create_pages_table',1),(17,'2026_06_13_014702_create_notification_settings_table',1),(18,'2026_06_13_014824_create_notification_preferences_table',1),(19,'2026_06_16_072055_create_notifications_table',1),(20,'2026_07_13_042748_create_community_posts_table',1),(21,'2026_07_13_071828_create_community_comments_table',1),(22,'2026_07_13_071839_create_community_likes_table',1),(23,'2026_07_13_071849_create_community_post_reports_table',1),(24,'2026_07_13_072039_create_subscription_plans_table',1),(25,'2026_07_13_080748_create_topup_products_table',1),(26,'2026_07_13_080749_create_payments_table',1),(27,'2026_07_13_083740_create_user_limits_table',1),(28,'2026_07_14_030901_create_health_logs_table',1),(29,'2026_07_15_070740_create_terra_activity_data_table',1),(30,'2026_07_15_070740_create_terra_connections_table',1),(31,'2026_07_15_075535_create_lab_reports_table',1),(32,'2026_07_18_022122_create_life_journey_features_table',1),(33,'2026_07_18_032318_create_skin_scans_table',1),(34,'2026_07_18_032329_create_skin_scan_recommendations_table',1),(35,'2026_07_20_000001_create_marketplace_life_stages_table',1),(36,'2026_07_20_000002_create_provider_categories_table',1),(37,'2026_07_20_000003_create_marketplace_life_stage_category_table',1),(38,'2026_07_20_000004_create_category_taxonomy_codes_table',1),(39,'2026_07_20_000005_create_category_place_queries_table',1),(40,'2026_07_20_000006_create_metros_table',1),(41,'2026_07_20_000007_create_providers_table',1),(42,'2026_07_20_000008_create_provider_category_table',1),(43,'2026_07_20_000009_create_place_details_cache_table',1),(44,'2026_07_20_000010_create_vetting_records_table',1),(45,'2026_07_20_000011_create_sponsored_slots_table',1),(46,'2026_07_20_000012_create_slate_events_table',1),(47,'2026_07_20_000013_add_is_marketplace_admin_to_users_table',1),(48,'2026_07_20_084858_create_chat_sessions_table',1),(49,'2026_07_20_084911_create_chat_messages_table',1),(50,'2026_07_21_070011_create_daily_scriptures_table',1),(51,'2026_07_21_082542_create_health_trends_table',1),(52,'2026_07_22_020556_create_smart_analyses_table',1),(53,'2026_07_22_054631_create_numera_insights_table',1),(54,'2026_07_27_032241_create_waitlist_entries_table',1),(55,'2026_07_28_060450_create_cycle_modes_table',1),(56,'2026_07_28_060953_create_cycle_settings_table',1),(57,'2026_07_28_061024_create_cycle_statistics_table',1),(58,'2026_07_28_061109_create_menstrual_cycles_table',1),(59,'2026_07_28_061143_create_period_logs_table',1),(60,'2026_07_28_061215_create_cycle_daily_logs_table',1),(61,'2026_07_28_061251_create_bbt_logs_table',1),(62,'2026_07_28_061318_create_opk_logs_table',1),(63,'2026_07_28_061352_create_cervical_mucus_logs_table',1),(64,'2026_07_28_061420_create_symptom_logs_table',1),(65,'2026_07_28_061448_create_ovulation_reconciliations_table',1),(66,'2026_07_28_061515_create_cycle_prediction_caches_table',1),(67,'2026_07_28_061542_create_signal_histories_table',1),(68,'2026_07_28_061616_create_intercourse_logs_table',1),(69,'2026_07_28_061644_create_pregnancy_test_logs_table',1),(70,'2026_07_28_061709_create_fertility_events_table',1),(71,'2026_07_28_061741_create_hormone_snapshots_table',1),(72,'2026_07_28_061806_create_phase_insights_table',1),(73,'2026_07_28_061834_create_notification_histories_table',1),(74,'2026_07_28_061901_create_cycle_consents_table',1),(75,'2026_07_29_080244_create_blog_categories_table',1),(76,'2026_07_29_080249_create_blogs_table',1),(77,'2026_07_29_083620_create_ttc_predictions_table',1),(78,'2026_07_29_084249_create_blog_comments_table',1),(79,'2026_07_30_021639_create_awareness_snapshots_table',1),(80,'2026_08_01_035735_create_opk_data_table',1),(81,'2026_08_02_040053_create_new_awareness_snapshots_table',1),(82,'2026_08_03_044409_create_cycle_calendar_inputs_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `new_awareness_snapshots`
--

DROP TABLE IF EXISTS `new_awareness_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `new_awareness_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `cycle_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cycle_context` json DEFAULT NULL,
  `current_phase` json DEFAULT NULL,
  `luteal_phase` json DEFAULT NULL,
  `hormone_levels` json DEFAULT NULL,
  `what_to_know` json DEFAULT NULL,
  `four_phase_cycle` json DEFAULT NULL,
  `ai_response` json DEFAULT NULL,
  `ai_generated` tinyint(1) NOT NULL DEFAULT '0',
  `ai_cached` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `new_awareness_snapshots_user_id_cycle_id_unique` (`user_id`,`cycle_id`),
  KEY `new_awareness_snapshots_user_id_index` (`user_id`),
  KEY `new_awareness_snapshots_cycle_id_index` (`cycle_id`),
  CONSTRAINT `new_awareness_snapshots_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `new_awareness_snapshots_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `new_awareness_snapshots`
--

LOCK TABLES `new_awareness_snapshots` WRITE;
/*!40000 ALTER TABLE `new_awareness_snapshots` DISABLE KEYS */;
/*!40000 ALTER TABLE `new_awareness_snapshots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_histories`
--

DROP TABLE IF EXISTS `notification_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `type` enum('period_reminder','fertility_reminder','bbt_reminder','opk_reminder','ovulation_reminder','general') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','sent','delivered','read','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notification_histories_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `notification_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_histories`
--

LOCK TABLES `notification_histories` WRITE;
/*!40000 ALTER TABLE `notification_histories` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_preferences`
--

DROP TABLE IF EXISTS `notification_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_preferences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `new_inspection` tinyint(1) NOT NULL DEFAULT '1',
  `upcoming` tinyint(1) NOT NULL DEFAULT '1',
  `reschedule` tinyint(1) NOT NULL DEFAULT '1',
  `cancellation` tinyint(1) NOT NULL DEFAULT '1',
  `email` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notification_preferences_user_id_foreign` (`user_id`),
  CONSTRAINT `notification_preferences_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_preferences`
--

LOCK TABLES `notification_preferences` WRITE;
/*!40000 ALTER TABLE `notification_preferences` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_preferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_settings`
--

DROP TABLE IF EXISTS `notification_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` tinyint(1) NOT NULL DEFAULT '1',
  `push` tinyint(1) NOT NULL DEFAULT '1',
  `security_alert` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_settings`
--

LOCK TABLES `notification_settings` WRITE;
/*!40000 ALTER TABLE `notification_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES ('35624891-43e5-437e-9b06-115d94999f44','App\\Notifications\\AdminIconNotification','App\\Models\\User',1,'{\"type\":\"registration\",\"title\":\"New Registration\",\"message\":\"New Registration Created Successfully.\",\"sender_id\":null}',NULL,'2026-08-06 03:01:30','2026-08-06 03:01:30'),('46c80ad7-01ce-41e4-80ae-765ae1c20f6e','App\\Notifications\\AdminIconNotification','App\\Models\\User',1,'{\"type\":\"registration\",\"title\":\"New Registration\",\"message\":\"New Registration Created Successfully.\",\"sender_id\":null}',NULL,'2026-08-06 02:29:50','2026-08-06 02:29:50'),('5a132349-50c9-4091-aea8-fff1fe27d35e','App\\Notifications\\AdminIconNotification','App\\Models\\User',1,'{\"type\":\"registration\",\"title\":\"New Registration\",\"message\":\"New Registration Created Successfully.\",\"sender_id\":null}',NULL,'2026-08-06 01:47:23','2026-08-06 01:47:23'),('a56682c8-8904-4a09-b065-6ee1990b5e1c','App\\Notifications\\AdminIconNotification','App\\Models\\User',1,'{\"type\":\"registration\",\"title\":\"New Registration\",\"message\":\"New Registration Created Successfully.\",\"sender_id\":null}',NULL,'2026-08-05 21:58:07','2026-08-05 21:58:07'),('e0b4d38b-5c92-4a26-8337-55bb5f5f33a2','App\\Notifications\\AdminIconNotification','App\\Models\\User',1,'{\"type\":\"registration\",\"title\":\"New Registration\",\"message\":\"New Registration Created Successfully.\",\"sender_id\":null}',NULL,'2026-08-05 21:49:32','2026-08-05 21:49:32'),('f5a7f45c-d9f0-4417-b20a-40dbbbf9196e','App\\Notifications\\AdminIconNotification','App\\Models\\User',1,'{\"type\":\"registration\",\"title\":\"New Registration\",\"message\":\"New Registration Created Successfully.\",\"sender_id\":null}',NULL,'2026-08-05 14:59:39','2026-08-05 14:59:39');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `numera_insights`
--

DROP TABLE IF EXISTS `numera_insights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `numera_insights` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tag` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `eyebrow` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `headline` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `cycle_day` int DEFAULT NULL,
  `theme` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `numera_insights_user_id_foreign` (`user_id`),
  CONSTRAINT `numera_insights_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `numera_insights`
--

LOCK TABLES `numera_insights` WRITE;
/*!40000 ALTER TABLE `numera_insights` DISABLE KEYS */;
INSERT INTO `numera_insights` VALUES (1,2,'Neumera Insight','Cycle Day 14','NEUMERA INSIGHT · CYCLE DAY 14','You\'re entering your peak energy window. Ovulation likely within 24-48 hours.','HRV is elevated at 58ms — an ideal window for high-intensity training and deep cognitive work.',14,'peak_energy','high','completed','2026-08-05 22:00:04','2026-08-05 22:00:10'),(2,8,'Neumera Insight','Cycle Day 14','NEUMERA INSIGHT · CYCLE DAY 14','You\'re entering your peak energy window, Gulam. Ovulation likely within 24–48 hours.','HRV is elevated at 58ms — an ideal window for high-intensity training and deep cognitive work.',14,'peak_energy','high','completed','2026-08-06 03:03:33','2026-08-06 03:03:37'),(3,2,'Neumera Insight','Cycle Day 14','NEUMERA INSIGHT · CYCLE DAY 14','You\'re entering your peak energy window. Ovulation likely within 24-48 hours.','HRV is elevated at 58ms — an ideal window for high-intensity training and deep cognitive work.',14,'peak_energy','high','completed','2026-08-06 03:47:06','2026-08-06 03:47:18');
/*!40000 ALTER TABLE `numera_insights` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opk_data`
--

DROP TABLE IF EXISTS `opk_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `opk_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `response_data` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `opk_data_user_id_foreign` (`user_id`),
  CONSTRAINT `opk_data_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opk_data`
--

LOCK TABLES `opk_data` WRITE;
/*!40000 ALTER TABLE `opk_data` DISABLE KEYS */;
/*!40000 ALTER TABLE `opk_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opk_logs`
--

DROP TABLE IF EXISTS `opk_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `opk_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` bigint unsigned NOT NULL,
  `log_date` date NOT NULL,
  `result` enum('negative','low','high','positive','peak') COLLATE utf8mb4_unicode_ci NOT NULL,
  `lh_value` decimal(6,2) DEFAULT NULL,
  `outside_window` tinyint(1) NOT NULL DEFAULT '0',
  `affects_prediction` tinyint(1) NOT NULL DEFAULT '1',
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `opk_logs_cycle_id_log_date_unique` (`cycle_id`,`log_date`),
  KEY `opk_logs_result_index` (`result`),
  CONSTRAINT `opk_logs_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opk_logs`
--

LOCK TABLES `opk_logs` WRITE;
/*!40000 ALTER TABLE `opk_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `opk_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ovulation_reconciliations`
--

DROP TABLE IF EXISTS `ovulation_reconciliations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ovulation_reconciliations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `cycle_id` bigint unsigned NOT NULL,
  `calendar_predicted_day` tinyint unsigned DEFAULT NULL,
  `bbt_confirmed_day` tinyint unsigned DEFAULT NULL,
  `lh_surge_day` tinyint unsigned DEFAULT NULL,
  `mucus_peak_day` tinyint unsigned DEFAULT NULL,
  `final_confirmed_day` tinyint unsigned DEFAULT NULL,
  `final_source` enum('calendar','bbt','opk','mucus','combined') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `offset_days` smallint NOT NULL DEFAULT '0',
  `luteal_phase_length` tinyint unsigned NOT NULL DEFAULT '14',
  `has_discrepancy` tinyint(1) NOT NULL DEFAULT '0',
  `discrepancy_note` text COLLATE utf8mb4_unicode_ci,
  `is_reconciled` tinyint(1) NOT NULL DEFAULT '0',
  `reconciled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ovulation_reconciliations_cycle_id_unique` (`cycle_id`),
  KEY `ovulation_reconciliations_user_id_index` (`user_id`),
  KEY `ovulation_reconciliations_final_source_index` (`final_source`),
  KEY `ovulation_reconciliations_has_discrepancy_index` (`has_discrepancy`),
  KEY `ovulation_reconciliations_is_reconciled_index` (`is_reconciled`),
  CONSTRAINT `ovulation_reconciliations_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ovulation_reconciliations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ovulation_reconciliations`
--

LOCK TABLES `ovulation_reconciliations` WRITE;
/*!40000 ALTER TABLE `ovulation_reconciliations` DISABLE KEYS */;
INSERT INTO `ovulation_reconciliations` VALUES (1,2,1,NULL,NULL,NULL,NULL,NULL,NULL,0,14,0,NULL,0,NULL,NULL,NULL);
/*!40000 ALTER TABLE `ovulation_reconciliations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` json NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pages_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pages`
--

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
INSERT INTO `pages` VALUES (1,'Terms of Service','terms-of-service','{\"heading\": \"Terms of Service\", \"paragraphs\": [\"By using Neumera, you agree to our Terms of Service. Neumera provides health education and tracking tools. It is not a medical device, does not provide medical advice, and is not a substitute for professional healthcare. Use of the app is at your own discretion.\", \"Neumera is a health tracking and wellness app. It is NOT a substitute for professional medical advice, diagnosis, or treatment. Always consult with qualified healthcare providers regarding your health concerns.\", \"All content, features, and functionality of the Services, including but not limited to text, graphics, logos, icons, images, audio clips, video clips, data compilations, and software, are owned by Neumera LLC or its licensors and are protected by United States and international copyright, trademark, patent, trade secret, and other intellectual property laws.\", \"You retain ownership of your personal data and content you submit. By using the Services, you grant us a limited license to use, store, and process your data as described in our Privacy Policy.\"]}',1,NULL,'2026-08-05 08:29:48','2026-08-05 08:29:48'),(2,'Privacy Policy','privacy-policy','{\"heading\": \"Privacy Policy\", \"sections\": [{\"body\": \"Your health data is encrypted end-to-end and never sold to third parties. You may export or delete your data at any time.\", \"title\": \"Data Usage\"}, {\"body\": \"Neumera AI provides health insights for informational purposes only. It is not a substitute for professional medical advice, diagnosis, or treatment.\", \"title\": \"AI Disclaimer\"}, {\"body\": \"You control exactly which data sources are connected. All integrations can be disconnected at any time from your Profile settings.\", \"title\": \"Data Control\"}], \"paragraphs\": [\"Neumera collects health data you choose to input. We do not sell your personal health data. You can request a full data export or deletion at any time under Privacy Settings.\", \"Neumera is a general wellness platform. It is not a medical device and does not provide medical advice, diagnosis, or treatment. \\\"Neumera,\\\" our AI coach, generates generalized wellness guidance and pattern-based insights. All outputs may be inaccurate or incomplete and should not be relied upon for urgent, emergency, or clinical decisions. If you have a medical concern, contact a licensed healthcare professional.\", \"Fight the Number LLC operates the Neumera mobile application and website. We are committed to protecting your privacy.\"]}',1,NULL,'2026-08-05 08:29:48','2026-08-06 04:01:24'),(3,'Medical Advice','medical-advice','{\"heading\": \"Medical Advice\", \"paragraphs\": [\"Neumera does not provide medical advice, diagnosis, or treatment. Health insights, scores, and signals are informational only. Always consult a licensed healthcare professional before making health decisions.\"]}',1,NULL,'2026-08-05 08:29:48','2026-08-05 08:29:48'),(4,'Disclaimer','disclaimer','{\"heading\": \"Disclaimer\", \"paragraphs\": [\"Neumera is not a contraceptive and has not been cleared or approved by the U.S. FDA as a method of contraception. Cycle predictions, fertile window estimates, and ovulation predictions are educational estimates only. They may be inaccurate and cannot tell you which days are safe to avoid pregnancy. Do not use Neumera to prevent pregnancy. Consult a healthcare provider for contraceptive needs.\"], \"sub_heading\": \"? Fertility awareness, not birth control\"}',1,NULL,'2026-08-05 08:29:48','2026-08-06 04:01:24');
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `subscription_plan_id` bigint unsigned DEFAULT NULL,
  `topup_product_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'subscription',
  `billing_cycle` enum('month','year') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_period_start` timestamp NULL DEFAULT NULL,
  `current_period_end` timestamp NULL DEFAULT NULL,
  `stripe_subscription_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(8,2) NOT NULL,
  `stripe_customer_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_payment_intent_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_invoice_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'pending,paid,cancel',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_stripe_subscription_id_unique` (`stripe_subscription_id`),
  KEY `payments_user_id_foreign` (`user_id`),
  KEY `payments_subscription_plan_id_foreign` (`subscription_plan_id`),
  KEY `payments_topup_product_id_foreign` (`topup_product_id`),
  KEY `payments_stripe_customer_id_index` (`stripe_customer_id`),
  KEY `payments_stripe_payment_intent_id_index` (`stripe_payment_intent_id`),
  CONSTRAINT `payments_subscription_plan_id_foreign` FOREIGN KEY (`subscription_plan_id`) REFERENCES `subscription_plans` (`id`),
  CONSTRAINT `payments_topup_product_id_foreign` FOREIGN KEY (`topup_product_id`) REFERENCES `topup_products` (`id`),
  CONSTRAINT `payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,5,1,NULL,'subscription','month','2026-08-05 21:59:59','2026-08-12 21:59:59',NULL,0.00,NULL,NULL,NULL,'paid','2026-08-05 21:59:59','2026-08-05 21:59:59'),(2,4,1,NULL,'subscription','month','2026-08-05 22:03:31','2026-08-12 22:03:31',NULL,0.00,NULL,NULL,NULL,'paid','2026-08-05 22:03:31','2026-08-05 22:03:31'),(3,8,1,NULL,'subscription','month','2026-08-06 03:03:31','2026-08-13 03:03:31',NULL,0.00,NULL,NULL,NULL,'paid','2026-08-06 03:03:31','2026-08-06 03:03:31');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `period_logs`
--

DROP TABLE IF EXISTS `period_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `period_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `cycle_id` bigint unsigned NOT NULL,
  `log_date` date NOT NULL,
  `flow` enum('spotting','light','medium','heavy') COLLATE utf8mb4_unicode_ci NOT NULL,
  `clotting` tinyint(1) NOT NULL DEFAULT '0',
  `cramps` tinyint(1) NOT NULL DEFAULT '0',
  `headache` tinyint(1) NOT NULL DEFAULT '0',
  `fatigue` tinyint(1) NOT NULL DEFAULT '0',
  `pain_level` tinyint unsigned DEFAULT NULL COMMENT '0-10',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `period_logs_user_id_cycle_id_log_date_unique` (`user_id`,`cycle_id`,`log_date`),
  KEY `period_logs_cycle_id_foreign` (`cycle_id`),
  KEY `period_logs_flow_index` (`flow`),
  KEY `period_logs_log_date_index` (`log_date`),
  CONSTRAINT `period_logs_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `period_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `period_logs`
--

LOCK TABLES `period_logs` WRITE;
/*!40000 ALTER TABLE `period_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `period_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (8,'App\\Models\\User',4,'auth_token','84a78c861ed38429e5440766682e4edb13f576fc56dfb078f41ed0a9d2c36bf9','[\"*\"]','2026-08-05 22:55:26',NULL,'2026-08-05 21:50:23','2026-08-05 22:55:26'),(10,'App\\Models\\User',5,'auth_token','b8e94523be10f39450561511af6818173f16c4c658cab1b277f67cff8da63532','[\"*\"]','2026-08-06 03:47:06',NULL,'2026-08-05 21:58:55','2026-08-06 03:47:06'),(15,'App\\Models\\User',1,'auth_token','77960491aecb6aab71c16c61d97e02daa10a1060a4e937e0a4a96349022ccb89','[\"*\"]',NULL,NULL,'2026-08-06 01:39:27','2026-08-06 01:39:27'),(16,'App\\Models\\User',1,'api_auth_token','b8f8b4f20eb9e313b1b6c1836c9fdfdc40322b47d97eb19dd637810d3ffd5081','[\"*\"]','2026-08-06 02:03:20',NULL,'2026-08-06 01:40:10','2026-08-06 02:03:20'),(20,'App\\Models\\User',6,'auth_token','f856bfffb8ebfe84ee8784eee7a1ee11602928bc4694c5965712e8b57d548e20','[\"*\"]',NULL,NULL,'2026-08-06 01:48:02','2026-08-06 01:48:02'),(21,'App\\Models\\User',7,'auth_token','33492be6100b1e41d5339586cd81938fe2e1594ee1f794fe46cf7c148a848588','[\"*\"]','2026-08-06 03:37:40',NULL,'2026-08-06 02:30:07','2026-08-06 03:37:40'),(23,'App\\Models\\User',8,'auth_token','be729e04ad7553ecf262b4a2d72867a84a2d0f006b077f3f42bed42e7764684a','[\"*\"]','2026-08-06 03:32:22',NULL,'2026-08-06 03:02:49','2026-08-06 03:32:22'),(24,'App\\Models\\User',1,'api_auth_token','3af261366e1b2952ddd631ff89c58fd02e55b04c27982910295c023e8e986081','[\"*\"]','2026-08-06 03:57:28',NULL,'2026-08-06 03:55:09','2026-08-06 03:57:28'),(25,'App\\Models\\User',2,'auth_token','c1c316c627a3c9fca3e08577cb1acd4fc4a73c8c01f6ec3ac5063aa13b52b66d','[\"*\"]','2026-08-06 03:55:40',NULL,'2026-08-06 03:55:23','2026-08-06 03:55:40');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `phase_insights`
--

DROP TABLE IF EXISTS `phase_insights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `phase_insights` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` bigint unsigned NOT NULL,
  `insight_date` date NOT NULL,
  `phase` enum('menstrual','follicular','ovulatory','luteal') COLLATE utf8mb4_unicode_ci NOT NULL,
  `education` text COLLATE utf8mb4_unicode_ci,
  `energy_note` text COLLATE utf8mb4_unicode_ci,
  `hormone_note` text COLLATE utf8mb4_unicode_ci,
  `focus_note` text COLLATE utf8mb4_unicode_ci,
  `skin_note` text COLLATE utf8mb4_unicode_ci,
  `nutrition_note` text COLLATE utf8mb4_unicode_ci,
  `exercise_note` text COLLATE utf8mb4_unicode_ci,
  `ai_generated` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phase_insights_cycle_id_insight_date_unique` (`cycle_id`,`insight_date`),
  CONSTRAINT `phase_insights_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `phase_insights`
--

LOCK TABLES `phase_insights` WRITE;
/*!40000 ALTER TABLE `phase_insights` DISABLE KEYS */;
/*!40000 ALTER TABLE `phase_insights` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `place_details_cache`
--

DROP TABLE IF EXISTS `place_details_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `place_details_cache` (
  `provider_id` bigint unsigned NOT NULL,
  `rating` decimal(2,1) DEFAULT NULL,
  `review_count` int DEFAULT NULL,
  `hours_json` json DEFAULT NULL,
  `business_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fetched_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`provider_id`),
  KEY `place_details_cache_expires_at_index` (`expires_at`),
  CONSTRAINT `place_details_cache_provider_id_foreign` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `place_details_cache`
--

LOCK TABLES `place_details_cache` WRITE;
/*!40000 ALTER TABLE `place_details_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `place_details_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pregnancy_test_logs`
--

DROP TABLE IF EXISTS `pregnancy_test_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pregnancy_test_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` bigint unsigned NOT NULL,
  `test_date` date NOT NULL,
  `result` enum('negative','positive','invalid') COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `test_time` enum('morning','afternoon','evening','night') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pregnancy_test_logs_cycle_id_foreign` (`cycle_id`),
  KEY `pregnancy_test_logs_result_index` (`result`),
  KEY `pregnancy_test_logs_test_date_index` (`test_date`),
  CONSTRAINT `pregnancy_test_logs_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pregnancy_test_logs`
--

LOCK TABLES `pregnancy_test_logs` WRITE;
/*!40000 ALTER TABLE `pregnancy_test_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `pregnancy_test_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `profiles`
--

DROP TABLE IF EXISTS `profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `life_stage_id` bigint unsigned DEFAULT NULL,
  `activity_id` bigint unsigned DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `profile_img` text COLLATE utf8mb4_unicode_ci,
  `age` int DEFAULT NULL,
  `height` double DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `stripe_account_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `profiles_user_id_foreign` (`user_id`),
  KEY `profiles_life_stage_id_foreign` (`life_stage_id`),
  KEY `profiles_activity_id_foreign` (`activity_id`),
  CONSTRAINT `profiles_activity_id_foreign` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `profiles_life_stage_id_foreign` FOREIGN KEY (`life_stage_id`) REFERENCES `life_stages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `profiles`
--

LOCK TABLES `profiles` WRITE;
/*!40000 ALTER TABLE `profiles` DISABLE KEYS */;
INSERT INTO `profiles` VALUES (1,5,5,3,NULL,NULL,51,165.1,128,NULL,'2026-08-05 21:59:59','2026-08-05 21:59:59'),(2,4,NULL,NULL,NULL,'http://api.fightthenumber.com/storage/profiles/CghvKOCOFeRsAco7fqAeXSvitE1diDQLfVq14kre.png',48,177.8,147,NULL,'2026-08-05 22:03:31','2026-08-05 22:34:35'),(3,8,4,3,NULL,NULL,25,167.64,160,NULL,'2026-08-06 03:03:31','2026-08-06 03:03:31');
/*!40000 ALTER TABLE `profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `provider_categories`
--

DROP TABLE IF EXISTS `provider_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provider_categories` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vetting_tier` enum('medical','licensed_nonmedical','consumer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `requires_npi` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_categories_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `provider_categories`
--

LOCK TABLES `provider_categories` WRITE;
/*!40000 ALTER TABLE `provider_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `provider_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `provider_category`
--

DROP TABLE IF EXISTS `provider_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provider_category` (
  `provider_id` bigint unsigned NOT NULL,
  `category_id` smallint unsigned NOT NULL,
  `source` enum('nppes_taxonomy','places_match','manual') COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`provider_id`,`category_id`),
  KEY `provider_category_category_id_foreign` (`category_id`),
  CONSTRAINT `provider_category_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `provider_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `provider_category_provider_id_foreign` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `provider_category`
--

LOCK TABLES `provider_category` WRITE;
/*!40000 ALTER TABLE `provider_category` DISABLE KEYS */;
/*!40000 ALTER TABLE `provider_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `providers`
--

DROP TABLE IF EXISTS `providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `providers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `npi` char(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_place_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `org_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_e164` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addr_line1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addr_line2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` point NOT NULL /*!80003 SRID 4326 */,
  `metro_id` smallint unsigned DEFAULT NULL,
  `source_nppes` tinyint(1) NOT NULL DEFAULT '0',
  `source_places` tinyint(1) NOT NULL DEFAULT '0',
  `match_confidence` decimal(3,2) DEFAULT NULL,
  `status` enum('candidate','vetted','active','suspended','excluded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'candidate',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `providers_npi_unique` (`npi`),
  KEY `providers_metro_id_foreign` (`metro_id`),
  SPATIAL KEY `providers_location_spatialindex` (`location`),
  KEY `providers_status_index` (`status`),
  KEY `providers_google_place_id_index` (`google_place_id`),
  CONSTRAINT `providers_metro_id_foreign` FOREIGN KEY (`metro_id`) REFERENCES `metros` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `providers`
--

LOCK TABLES `providers` WRITE;
/*!40000 ALTER TABLE `providers` DISABLE KEYS */;
/*!40000 ALTER TABLE `providers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('1D3o4XIoc48v3Cjoj9s4njmf4iJxpPeqRjE7X9Us',NULL,'18.218.118.203','visionheight.com/scan Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/126.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiSUFlR3BoY0J3eW9Obkx5bXRnNjA1ekxaNm5KbG9iamQ5dzlsazdwcCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjQ6Imh0dHA6Ly81Mi41NC4xNjQuNzk6ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1785944244),('32C3SZMwJtP4kOupvU3zObTRYj0GbOSCtd000dSe',NULL,'172.18.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiQ04zZ3hDaGRuczQ3ejIyclJScTlNNkhMbXpkd1VmN2hUeDF3TUpQQSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjk6Imh0dHA6Ly9hcGkuZmlnaHR0aGVudW1iZXIuY29tIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1785920071),('58HPBmpsYWHsc8iaAvRgpY6UFIfageb2fU5BPPZl',NULL,'198.235.24.35','Hello from Palo Alto Networks, find out more about our scans in https://docs-cortex.paloaltonetworks.com/r/1/Cortex-Xpanse/Scanning-activity','YTozOntzOjY6Il90b2tlbiI7czo0MDoiTmNpTkJiVzNmMU4xODVQZ0dvaHNYVEx5YzhSR3lGajB3bXY3RDM2UiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjQ6Imh0dHA6Ly81Mi41NC4xNjQuNzk6ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1785964706),('9n2Hkt0c7ro3QIN3ZQgxa2eeMIySMSQxOkwkhzzQ',NULL,'172.18.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiRnJaNW8xbkwyTlJNd0dkbUNPelFLeTFtWTAzV3h6R1pKZDY2R0tadCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjk6Imh0dHA6Ly9hcGkuZmlnaHR0aGVudW1iZXIuY29tIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1785920071),('bjBGbMtDt5z5puiXdOXKgmnA5D5dAbxgBb70C6TO',NULL,'172.18.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiWjQ3cHVTbm1aMWF3bjJwUGp1NGNtRWJWeml6U1FIUWJHTlhOMHk2SSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjk6Imh0dHA6Ly9hcGkuZmlnaHR0aGVudW1iZXIuY29tIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1785978902),('bu53iRIJhjlvs5YWzOsgLYu915wt5OqoeBoH3xkW',NULL,'18.218.118.203','visionheight.com/scan Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/126.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiTTNNemJ1OUJnZXB0MnpmVjNRQlZhcnhhb3Nld1VlYnJOWFFyZmREdyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjQ6Imh0dHA6Ly81Mi41NC4xNjQuNzk6ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1785944223),('Dxh45eTRgyYDvSEaBCCQLI26rOLZM8byaZwCCTCO',NULL,'61.129.155.196','Mozilla/5.0(WindowsNT10.0;Win64;x64)AppleWebKit/537.36(KHTML,likeGecko)Chrome/86.0.4240.111Safari/537.36','YToyOntzOjY6Il90b2tlbiI7czo0MDoicXBYbkMxbWUyVzhaMmlGazZSVThmaVZnQnBwSmVTeEZBaVR4VlpRYSI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1785986182),('eQ65iAUBzVjCUHPS8gvWNvnGW8cUxgN6TPohXWzd',NULL,'61.129.155.196','','YTozOntzOjY6Il90b2tlbiI7czo0MDoiQjNpN2NKVjIwZWk4ZXQzRldCR2VjUTdyVHRZaWxGSGRUdjlNT1pSNCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly81Mi41NC4xNjQuNzkiO3M6NToicm91dGUiO047fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1785986217),('FGG6fZ6qqtT4egi49kDpMgexCxDNLJse9FpuWwP4',NULL,'172.18.0.1','Mozilla/5.0 (Linux; Android 11; CPH2185) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.101 Mobile Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiM1NJZnRyRER5WVY3dFRTcUpZdXR3dEpxYVlKc0lxN2hxcU5DNXNUVyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzQ6Imh0dHA6Ly9hcGkuZmlnaHR0aGVudW1iZXIuY29tL2RlbW8iO3M6NToicm91dGUiO3M6NToibG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1785919809),('hhujiDAQSzCjD9ohNhhQLYQXB8xaVv3QLiNLDXR3',NULL,'172.18.0.1','Mozilla/5.0 (Linux; Android 10; HUAWEI P30 Pro) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.105 Mobile Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiaVBLanF2RWlkTElBUlBuUTFKWnNaS0YyMTdtWUxpUFF5UVY1Z21SOSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjk6Imh0dHA6Ly9hcGkuZmlnaHR0aGVudW1iZXIuY29tIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1785919765),('KY2LiHYWbklv8kJZQZ1JcwfaSNIRmR6lX7XbtUVB',NULL,'87.236.176.241','Mozilla/5.0 (compatible; InternetMeasurement/1.0; +https://internet-measurement.com/)','YTozOntzOjY6Il90b2tlbiI7czo0MDoiU1lrejB1TjdwazVoYjhiSzZXZEJSaHlEMWhwbFVmNDdhZGdCMngyTyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly9haS5maWdodHRoZW51bWJlci5jb206ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1785952613),('Lq3PwtqsqBF3vs03J3NScjqrGm6bI7wnWlei9q1E',NULL,'172.18.0.1','Mozilla/5.0 (Linux; Android 11; V2055A) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.101 Mobile Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiU0NvUVpLaXNrTzZlZFVyUTkxRlJsZ240elVQS2hRNDdneTlXdzR1eSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjk6Imh0dHA6Ly9hcGkuZmlnaHR0aGVudW1iZXIuY29tIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1785919807),('M8EFN0KbAx3JgTA8nz2qJzvvsuqghTHUzwILLGNq',NULL,'172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiVGNrU3RFbmhmZlNxNG92aHAwQmp1eWtaS0hybm52c05MV1VJVnNxdyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjk6Imh0dHA6Ly9hcGkuZmlnaHR0aGVudW1iZXIuY29tIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1785919115),('MbEY2a8nEuRvCMciWGTLwoch8dZGI5EzMJ1rAKe9',NULL,'172.18.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiNWttNEtDeW5IdGVXZGFYeHhtTjEzSEoxRlRVSjZGMllPQjBZbDRwWSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjk6Imh0dHA6Ly9hcGkuZmlnaHR0aGVudW1iZXIuY29tIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1785982097),('mZJCVtA8Z3YeKMUthAtZWucNkSZKRtOdHx22q3RL',NULL,'61.129.155.196','','YTozOntzOjY6Il90b2tlbiI7czo0MDoiQ0NmcGtzdWRocGhlbGYyZTR2QlY0RnY0VlFQT3NUQ0VnQk1pR3RuUCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTk6Imh0dHA6Ly8wLjAuMC4wOjgwMDAiO3M6NToicm91dGUiO047fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1785986113),('n4NCnYcyzLpiUBWJVNR4hoKrtyUnABrVUCR5qtAt',NULL,'172.18.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoieWptZm5XN1Vac0lvclNidWdPSjdVNkNRNlZDYlRNN1kzekx6VkgzOCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzQ6Imh0dHA6Ly9hcGkuZmlnaHR0aGVudW1iZXIuY29tL2RlbW8iO3M6NToicm91dGUiO3M6NToibG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1785921455),('Q8RBFRLQBWhnsTcuF0mAJ5UbrrZQghkwsgYfgOH0',NULL,'172.18.0.1','Mozilla/5.0 (Linux; U; Android 8.1.0; zh-cn; MI 8 Build/OPM1.171019.011) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/57.0.2987.108 Mobile Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiWW1KT1lRRVV6RmdrQWgzU3o1dWI0dGFSanE2bjJBbUljYWtQbDRRbiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjk6Imh0dHA6Ly9hcGkuZmlnaHR0aGVudW1iZXIuY29tIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1785919951),('QeosyzxfR6jWa7WeVM2cJQqJHrbMsSYniTUCGrYV',NULL,'66.132.224.232','Mozilla/5.0 (compatible; CensysInspect/1.1; +https://about.censys.io/)','YTozOntzOjY6Il90b2tlbiI7czo0MDoic3BWdzhaeGJmSUZITGVUVEJFd3BOdmdnVkN6SUROaFZjaWtaUzBtaCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjQ6Imh0dHA6Ly81Mi41NC4xNjQuNzk6ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1785934858),('qzEBVbBarI6w2Tep1GT9U5z61M7TAZO3MPniBZ15',NULL,'8.219.239.2','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoick9RVnBBb0VmS2RONVM4MkN0MVZDNUVLdU1MQ2VJc0NOQU1EMDZwdyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly9oci5maWdodHRoZW51bWJlci5jb206ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1785959875),('rGyzCIJha8dgZfVYlSTN32PUN5q6h9VdH778IjDc',NULL,'61.129.155.196','','YTozOntzOjY6Il90b2tlbiI7czo0MDoiQjdYQkVSUU1jamtSNmJpckhSTVprUXBoa3Vub3JMMlBQb2trMEo5NiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8wLjAuMC4wOjgwMDAvP0NBVklUPSI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1785986128),('SpgrAdSmsbwgYHigrj7SoNbTsJoOL2HqTCI3Qo3S',NULL,'172.18.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3','YToyOntzOjY6Il90b2tlbiI7czo0MDoiRDR6SXF4T2J3MkNlaTFDRkJ0aUQzMVpJWWgyaHpVb3lqblBEOWNCTSI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1785950912),('uPk8V0vDl18gWY9ujRrsqpXLbLBZOljwMDCQ4CXu',NULL,'205.210.31.200','curl/7.68.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoieWtySWlOaGZYbWdJWDVJbWxiWUhFYzJSV0FnRzlLckFDSXpWWjNmRCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjQ6Imh0dHA6Ly81Mi41NC4xNjQuNzk6ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1785947368);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `platform_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'NEUMERA','support@neumera.com',NULL,'2026-08-05 08:29:48','2026-08-05 08:29:48');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `signal_histories`
--

DROP TABLE IF EXISTS `signal_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `signal_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `cycle_id` bigint unsigned DEFAULT NULL,
  `log_date` date NOT NULL,
  `calendar_logged` tinyint(1) NOT NULL DEFAULT '0',
  `bbt_logged` tinyint(1) NOT NULL DEFAULT '0',
  `opk_logged` tinyint(1) NOT NULL DEFAULT '0',
  `mucus_logged` tinyint(1) NOT NULL DEFAULT '0',
  `symptoms_logged` tinyint(1) NOT NULL DEFAULT '0',
  `signal_strength` enum('none','low','medium','high') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `signals` json DEFAULT NULL,
  `ai_generated` tinyint(1) NOT NULL DEFAULT '0',
  `ai_cached` tinyint(1) NOT NULL DEFAULT '0',
  `sources` json DEFAULT NULL,
  `backend_errors` json DEFAULT NULL,
  `status_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `signal_histories_user_id_cycle_id_log_date_unique` (`user_id`,`cycle_id`,`log_date`),
  KEY `signal_histories_user_id_index` (`user_id`),
  KEY `signal_histories_cycle_id_index` (`cycle_id`),
  KEY `signal_histories_log_date_index` (`log_date`),
  KEY `signal_histories_signal_strength_index` (`signal_strength`),
  CONSTRAINT `signal_histories_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `signal_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `signal_histories`
--

LOCK TABLES `signal_histories` WRITE;
/*!40000 ALTER TABLE `signal_histories` DISABLE KEYS */;
INSERT INTO `signal_histories` VALUES (1,2,1,'2026-08-06',1,0,0,0,0,'none',NULL,0,0,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `signal_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `skin_scan_recommendations`
--

DROP TABLE IF EXISTS `skin_scan_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `skin_scan_recommendations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `skin_scan_id` bigint unsigned NOT NULL,
  `icon_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'drop',
  `recommendation_text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `skin_scan_recommendations_skin_scan_id_foreign` (`skin_scan_id`),
  CONSTRAINT `skin_scan_recommendations_skin_scan_id_foreign` FOREIGN KEY (`skin_scan_id`) REFERENCES `skin_scans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `skin_scan_recommendations`
--

LOCK TABLES `skin_scan_recommendations` WRITE;
/*!40000 ALTER TABLE `skin_scan_recommendations` DISABLE KEYS */;
/*!40000 ALTER TABLE `skin_scan_recommendations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `skin_scans`
--

DROP TABLE IF EXISTS `skin_scans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `skin_scans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overall_score` int NOT NULL,
  `hydration_score` int NOT NULL,
  `redness_score` int NOT NULL,
  `texture_score` int NOT NULL,
  `glow_index` int NOT NULL,
  `pore_health_score` int NOT NULL,
  `elasticity_score` int NOT NULL,
  `hydration_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Fair',
  `redness_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Low',
  `texture_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Good',
  `glow_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Fair',
  `pore_health_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Low',
  `elasticity_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Good',
  `neumera_insight` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `skin_scans_user_id_foreign` (`user_id`),
  CONSTRAINT `skin_scans_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `skin_scans`
--

LOCK TABLES `skin_scans` WRITE;
/*!40000 ALTER TABLE `skin_scans` DISABLE KEYS */;
/*!40000 ALTER TABLE `skin_scans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `slate_events`
--

DROP TABLE IF EXISTS `slate_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `slate_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `occurred_at` timestamp NOT NULL,
  `metro_id` smallint unsigned DEFAULT NULL,
  `category_id` smallint unsigned DEFAULT NULL,
  `marketplace_life_stage_id` tinyint unsigned DEFAULT NULL,
  `provider_id` bigint unsigned DEFAULT NULL,
  `slot_position` tinyint DEFAULT NULL,
  `sponsored` tinyint(1) NOT NULL DEFAULT '0',
  `event_type` enum('impression','tap','call','directions','website','share') COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_token` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `slate_events_category_id_foreign` (`category_id`),
  KEY `slate_events_mkt_life_stage_fk` (`marketplace_life_stage_id`),
  KEY `slate_events_provider_id_foreign` (`provider_id`),
  KEY `slate_events_metro_id_category_id_occurred_at_index` (`metro_id`,`category_id`,`occurred_at`),
  KEY `slate_events_session_token_index` (`session_token`),
  CONSTRAINT `slate_events_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `provider_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `slate_events_metro_id_foreign` FOREIGN KEY (`metro_id`) REFERENCES `metros` (`id`) ON DELETE SET NULL,
  CONSTRAINT `slate_events_mkt_life_stage_fk` FOREIGN KEY (`marketplace_life_stage_id`) REFERENCES `marketplace_life_stages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `slate_events_provider_id_foreign` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `slate_events`
--

LOCK TABLES `slate_events` WRITE;
/*!40000 ALTER TABLE `slate_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `slate_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `smart_analyses`
--

DROP TABLE IF EXISTS `smart_analyses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `smart_analyses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alerts` json DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `smart_analyses_user_id_foreign` (`user_id`),
  CONSTRAINT `smart_analyses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `smart_analyses`
--

LOCK TABLES `smart_analyses` WRITE;
/*!40000 ALTER TABLE `smart_analyses` DISABLE KEYS */;
INSERT INTO `smart_analyses` VALUES (1,4,NULL,NULL,'failed','2026-08-05 22:00:04','2026-08-05 22:03:32'),(2,8,NULL,NULL,'failed','2026-08-06 03:03:33','2026-08-06 03:17:54'),(3,4,'Smart Alerts','[{\"reason\": \"No cycle, BBT, OPK, or period logs available; fertility timing cannot be determined (estimated).\", \"message\": \"Cycle tracking not active - log period to predict window\", \"category\": \"fertility\", \"priority\": \"low\"}, {\"reason\": \"Recent health log lists insomnia, fatigue, and brain fog with moderate energy; no prior week to compare (estimated).\", \"message\": \"Insomnia and fatigue flagged - energy holding at moderate\", \"category\": \"sleep_energy\", \"priority\": \"high\"}, {\"reason\": \"No skin scan data available; suggestion based on reported headache and fatigue which often align with low hydration (estimated).\", \"message\": \"Boost hydration today to support skin recovery\", \"category\": \"skin_hydration\", \"priority\": \"medium\"}]','completed','2026-08-06 03:47:06','2026-08-06 03:47:15');
/*!40000 ALTER TABLE `smart_analyses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sponsored_slots`
--

DROP TABLE IF EXISTS `sponsored_slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sponsored_slots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `metro_id` smallint unsigned NOT NULL,
  `category_id` smallint unsigned NOT NULL,
  `slot_number` tinyint NOT NULL,
  `provider_id` bigint unsigned NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `status` enum('reserved','active','expired','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reserved',
  `monthly_rate_cents` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sponsored_slots_category_id_foreign` (`category_id`),
  KEY `sponsored_slots_provider_id_foreign` (`provider_id`),
  KEY `sponsored_slots_metro_id_category_id_status_index` (`metro_id`,`category_id`,`status`),
  CONSTRAINT `sponsored_slots_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `provider_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sponsored_slots_metro_id_foreign` FOREIGN KEY (`metro_id`) REFERENCES `metros` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sponsored_slots_provider_id_foreign` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sponsored_slots`
--

LOCK TABLES `sponsored_slots` WRITE;
/*!40000 ALTER TABLE `sponsored_slots` DISABLE KEYS */;
/*!40000 ALTER TABLE `sponsored_slots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscription_plans`
--

DROP TABLE IF EXISTS `subscription_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price_monthly` decimal(8,2) NOT NULL DEFAULT '0.00',
  `price_annual` decimal(8,2) DEFAULT NULL,
  `skin_scans_limit` int NOT NULL DEFAULT '0',
  `ai_coaching_limit` int NOT NULL DEFAULT '0',
  `ai_coaching_model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deep_reports_limit` int NOT NULL DEFAULT '0',
  `daily_summary_frequency` enum('weekly','unlimited') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'weekly',
  `tracking_label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tracking_integrations` json DEFAULT NULL,
  `stripe_product_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_price_monthly_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_price_annual_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_plans_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscription_plans`
--

LOCK TABLES `subscription_plans` WRITE;
/*!40000 ALTER TABLE `subscription_plans` DISABLE KEYS */;
INSERT INTO `subscription_plans` VALUES (1,'free','Free','Basic access with limited features.',0.00,0.00,2,5,'haiku',0,'weekly','Cycle + BBT, manual + limited sync','[\"cycle_tracking\", \"bbt_manual\"]',NULL,NULL,NULL,1,'2026-08-05 08:29:48','2026-08-06 04:01:24'),(2,'premium','Premium','Full multi-stage access with wearables and lab OCR.',14.99,125.00,6,75,'all',0,'unlimited','Full multi-stage, all wearables, lab OCR','[\"wearables\", \"lab_ocr\"]','prod_Uw6mV4BgFmVPmy','price_1TwEZILzK4CIzgxHFmr7z0j7','price_1TwEZILzK4CIzgxHlL23iUBT',1,'2026-08-05 08:29:48','2026-08-06 04:01:24'),(3,'elite','Elite','Everything in Premium plus priority routing and deep reports.',24.99,219.99,12,150,'all',3,'unlimited','Everything in Premium + priority routing','[\"wearables\", \"lab_ocr\", \"priority_routing\"]','prod_Uw6ms8Lj0K1jO3','price_1TwEZJLzK4CIzgxHilP9g59H','price_1TwEZJLzK4CIzgxHPmgOaZUn',1,'2026-08-05 08:29:49','2026-08-06 04:01:24');
/*!40000 ALTER TABLE `subscription_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `symptom_logs`
--

DROP TABLE IF EXISTS `symptom_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `symptom_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` bigint unsigned NOT NULL,
  `log_date` date NOT NULL,
  `pain_level` tinyint unsigned DEFAULT NULL,
  `mood` enum('very_low','low','neutral','good','excellent') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `energy` enum('very_low','low','moderate','high','very_high') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cramps` tinyint(1) NOT NULL DEFAULT '0',
  `bloating` tinyint(1) NOT NULL DEFAULT '0',
  `headache` tinyint(1) NOT NULL DEFAULT '0',
  `fatigue` tinyint(1) NOT NULL DEFAULT '0',
  `acne` tinyint(1) NOT NULL DEFAULT '0',
  `breast_tenderness` tinyint(1) NOT NULL DEFAULT '0',
  `nausea` tinyint(1) NOT NULL DEFAULT '0',
  `insomnia` tinyint(1) NOT NULL DEFAULT '0',
  `libido` tinyint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `symptom_logs_cycle_id_log_date_unique` (`cycle_id`,`log_date`),
  KEY `symptom_logs_mood_index` (`mood`),
  KEY `symptom_logs_energy_index` (`energy`),
  CONSTRAINT `symptom_logs_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `symptom_logs`
--

LOCK TABLES `symptom_logs` WRITE;
/*!40000 ALTER TABLE `symptom_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `symptom_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `terra_activity_data`
--

DROP TABLE IF EXISTS `terra_activity_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `terra_activity_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `terra_user_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json NOT NULL,
  `data_generated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `terra_activity_data_user_id_foreign` (`user_id`),
  CONSTRAINT `terra_activity_data_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `terra_activity_data`
--

LOCK TABLES `terra_activity_data` WRITE;
/*!40000 ALTER TABLE `terra_activity_data` DISABLE KEYS */;
/*!40000 ALTER TABLE `terra_activity_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `terra_connections`
--

DROP TABLE IF EXISTS `terra_connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `terra_connections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `terra_user_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `terra_connections_terra_user_id_unique` (`terra_user_id`),
  KEY `terra_connections_user_id_foreign` (`user_id`),
  CONSTRAINT `terra_connections_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `terra_connections`
--

LOCK TABLES `terra_connections` WRITE;
/*!40000 ALTER TABLE `terra_connections` DISABLE KEYS */;
/*!40000 ALTER TABLE `terra_connections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `topup_products`
--

DROP TABLE IF EXISTS `topup_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `topup_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `topup_kind` enum('coaching_sessions','skin_scans') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `limit` int DEFAULT NULL,
  `price` decimal(8,2) NOT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `topup_products_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `topup_products`
--

LOCK TABLES `topup_products` WRITE;
/*!40000 ALTER TABLE `topup_products` DISABLE KEYS */;
INSERT INTO `topup_products` VALUES (1,'coaching_sessions_20','+20 Coaching Sessions','One-time top-up that expires at the end of the month.','coaching_sessions',20,2.99,1,'2026-08-05 08:29:49','2026-08-05 08:29:49'),(2,'skin_scans_5','+5 Skin Scans','One-time top-up that expires at the end of the month.','skin_scans',5,1.99,1,'2026-08-05 08:29:49','2026-08-05 08:29:49');
/*!40000 ALTER TABLE `topup_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ttc_predictions`
--

DROP TABLE IF EXISTS `ttc_predictions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ttc_predictions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `cycle_id` bigint unsigned NOT NULL,
  `surge_active` tinyint(1) NOT NULL DEFAULT '0',
  `surge_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hours_remaining_estimate` int DEFAULT NULL,
  `cycle_day` tinyint unsigned DEFAULT NULL,
  `lh_surge_day` tinyint unsigned DEFAULT NULL,
  `priority` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority_message` text COLLATE utf8mb4_unicode_ci,
  `priority_ranges` json DEFAULT NULL,
  `ai_generated` tinyint(1) NOT NULL DEFAULT '0',
  `ai_fallback` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ttc_predictions_user_id_foreign` (`user_id`),
  KEY `ttc_predictions_cycle_id_foreign` (`cycle_id`),
  CONSTRAINT `ttc_predictions_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ttc_predictions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ttc_predictions`
--

LOCK TABLES `ttc_predictions` WRITE;
/*!40000 ALTER TABLE `ttc_predictions` DISABLE KEYS */;
/*!40000 ALTER TABLE `ttc_predictions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_limits`
--

DROP TABLE IF EXISTS `user_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_limits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'subscription,topup,refund',
  `payment_id` bigint unsigned DEFAULT NULL,
  `skin_scans_limit` int unsigned NOT NULL DEFAULT '0',
  `ai_coaching_limit` int unsigned NOT NULL DEFAULT '0',
  `deep_reports_limit` int unsigned NOT NULL DEFAULT '0',
  `skin_scans_topup_limit` int unsigned NOT NULL DEFAULT '0',
  `ai_coaching_topup_limit` int unsigned NOT NULL DEFAULT '0',
  `subscription_expires_at` timestamp NULL DEFAULT NULL,
  `topup_expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_limits_user_id_foreign` (`user_id`),
  KEY `user_limits_payment_id_foreign` (`payment_id`),
  CONSTRAINT `user_limits_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_limits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_limits`
--

LOCK TABLES `user_limits` WRITE;
/*!40000 ALTER TABLE `user_limits` DISABLE KEYS */;
INSERT INTO `user_limits` VALUES (1,5,'subscription',1,2,5,0,0,0,'2026-08-12 21:59:59',NULL,'2026-08-05 21:59:59','2026-08-05 21:59:59'),(2,4,'subscription',2,2,5,0,0,0,'2026-08-12 22:03:31',NULL,'2026-08-05 22:03:31','2026-08-05 22:03:31'),(3,8,'subscription',3,2,5,0,0,0,'2026-08-13 03:03:31',NULL,'2026-08-06 03:03:31','2026-08-06 03:03:31');
/*!40000 ALTER TABLE `user_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_marketplace_admin` tinyint(1) NOT NULL DEFAULT '0',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `otp` int DEFAULT NULL,
  `otp_expire_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fcm_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apple_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT 'active,suspended',
  `suspend_reason` text COLLATE utf8mb4_unicode_ci,
  `user_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'user,admin',
  `is_privacy_accepted` tinyint(1) NOT NULL DEFAULT '0',
  `onboardingCompleted` tinyint(1) NOT NULL DEFAULT '0',
  `stripe_customer_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Super Admin','info@fightthenumber.com',0,'2026-08-06 04:01:24','$2y$12$/QIEgN9Heqo2Q9Dp1VBJJ.8ySQ1aCjDDZUXBdXrkNgGtcikzgR/b.',NULL,NULL,NULL,NULL,NULL,'active',NULL,'admin',0,0,NULL,'2026-08-06 01:39:27',NULL,'2026-08-05 08:29:49','2026-08-06 04:01:24'),(2,'User','user@gmail.com',0,'2026-08-06 04:01:25','$2y$12$EnCNGrMXEs6d6dfgRb2ds.JpO5QHTbh1PuEpn6yT8mT4A8fXaswyO',NULL,NULL,NULL,NULL,NULL,'active',NULL,'user',0,0,NULL,'2026-08-06 03:55:23',NULL,'2026-08-05 08:29:49','2026-08-06 04:01:25'),(3,'Eric','fkunder@fightthenumber.com',0,NULL,'$2y$12$2jiX1NNkM.UC0cXgaMcwr.qXO.DDh0dbySx88BmcRgb0BxjQLKFnC',4974,'2026-08-05 15:05:35',NULL,NULL,NULL,'active',NULL,'user',1,0,NULL,NULL,NULL,'2026-08-05 14:59:37','2026-08-05 15:00:35'),(4,'Eric','founder@fightthenumber.com',0,'2026-08-05 21:49:56','$2y$12$vE5tD3s4SZxhhZYue0b.s.DSYg7vEaGTWM2ZojX1lKooLbh1y3kc6',NULL,NULL,NULL,NULL,NULL,'active',NULL,'user',1,1,NULL,'2026-08-05 21:50:23',NULL,'2026-08-05 21:49:31','2026-08-05 22:34:35'),(5,'Hillary Pulse','hillarypulsedc@gmail.com',0,'2026-08-05 21:58:33','$2y$12$7tpzsB.OkDENizExjuvTSOOVHKzG6/antyiF/mmgi/vxx3rBoEozS',NULL,NULL,NULL,NULL,NULL,'active',NULL,'user',1,1,NULL,'2026-08-05 21:58:55',NULL,'2026-08-05 21:58:05','2026-08-05 21:59:59'),(6,'new tester mim','vidako6798@bora4d.com',0,'2026-08-06 01:48:02','$2y$12$x/3IHKo9gNRYVZ70ZNvVFeifqEdEc0Gpvq4qr2agiBhRxL6LfzsOG',NULL,NULL,NULL,NULL,NULL,'active',NULL,'user',1,0,NULL,NULL,NULL,'2026-08-06 01:47:22','2026-08-06 01:48:02'),(7,'Sabbir','bsabbir76@gmail.com',0,'2026-08-06 02:30:07','$2y$12$hywUQ.wyBPUxJG2TFkxopelcRdF1rN3LJ5NAcuiYQMJgZucaMZ2RW',NULL,NULL,NULL,NULL,NULL,'active',NULL,'user',1,0,NULL,NULL,NULL,'2026-08-06 02:29:49','2026-08-06 02:30:07'),(8,'Gulam Rosul','gulamrosul037@gmail.com',0,'2026-08-06 03:01:53','$2y$12$24VJ.JudYTBszR2K5HbNq.GAtrtPkhGWfBXUMIQphKcYvz4jqCeZi',NULL,NULL,NULL,NULL,NULL,'active',NULL,'user',1,1,NULL,'2026-08-06 03:02:49',NULL,'2026-08-06 03:01:27','2026-08-06 03:03:31');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vetting_records`
--

DROP TABLE IF EXISTS `vetting_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vetting_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider_id` bigint unsigned NOT NULL,
  `check_type` enum('license','leie','disciplinary','certification','reputation') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pass','fail','pending','expired') COLLATE utf8mb4_unicode_ci NOT NULL,
  `evidence_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `checked_at` timestamp NULL DEFAULT NULL,
  `next_due_at` timestamp NULL DEFAULT NULL,
  `checked_by` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vetting_records_provider_id_check_type_index` (`provider_id`,`check_type`),
  KEY `vetting_records_next_due_at_index` (`next_due_at`),
  CONSTRAINT `vetting_records_provider_id_foreign` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vetting_records`
--

LOCK TABLES `vetting_records` WRITE;
/*!40000 ALTER TABLE `vetting_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `vetting_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `waitlist_entries`
--

DROP TABLE IF EXISTS `waitlist_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `waitlist_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `life_journey_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_confirmation' COMMENT 'pending_confirmation',
  `confirmation_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `invited_at` timestamp NULL DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `wave_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `waitlist_entries_life_journey_id_foreign` (`life_journey_id`),
  CONSTRAINT `waitlist_entries_life_journey_id_foreign` FOREIGN KEY (`life_journey_id`) REFERENCES `life_journeys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `waitlist_entries`
--

LOCK TABLES `waitlist_entries` WRITE;
/*!40000 ALTER TABLE `waitlist_entries` DISABLE KEYS */;
INSERT INTO `waitlist_entries` VALUES (1,'Kim Bartels','ksb57108@gmail.com',6,'confirmed','IzlKo8EGgbvTXj94Oumq2hTOEWrawG4r','2026-08-05 17:15:00',NULL,NULL,NULL,'2026-08-05 16:57:46','2026-08-05 17:15:00'),(2,'Eric','eric.a.pulse@gmail.com',3,'confirmed','FZHEsaw7FxahARzwae5lNVjdYyNui2SS','2026-08-05 19:27:28',NULL,NULL,NULL,'2026-08-05 19:27:09','2026-08-05 19:27:28'),(3,'Hillary Pulse','hillarypulswdc@gmail.co',NULL,'pending_confirmation','DFxZis9GGxB9owqanujqbE2HlyLsB8My',NULL,NULL,NULL,NULL,'2026-08-05 22:06:10','2026-08-05 22:06:10'),(4,'Hillary Pulse','hillarypulsedc@gmail.co',6,'pending_confirmation','NufgW325YagfPNovtYvCX0KhsF2CO78c',NULL,NULL,NULL,NULL,'2026-08-05 22:06:56','2026-08-05 22:06:56');
/*!40000 ALTER TABLE `waitlist_entries` ENABLE KEYS */;
UNLOCK TABLES;
SET @@SESSION.SQL_LOG_BIN = @MYSQLDUMP_TEMP_LOG_BIN;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-06 10:05:31
