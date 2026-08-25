-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 25, 2026 at 06:15 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pulse_e`
--

-- --------------------------------------------------------

--
-- Table structure for table `activities`
--

CREATE TABLE `activities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activities`
--

INSERT INTO `activities` (`id`, `title`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Sedentary', 'Little to no regular exercise', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(2, 'Lightly Active', '1–3 days of exercise per week', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(3, 'Moderately Active', '3–5 days of exercise per week', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(4, 'Very Active', '6–7 days of intense training', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27');

-- --------------------------------------------------------

--
-- Table structure for table `awareness_snapshots`
--

CREATE TABLE `awareness_snapshots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED DEFAULT NULL,
  `phase` varchar(255) DEFAULT NULL,
  `day_range` varchar(255) DEFAULT NULL,
  `current_cycle_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `dominant_hormone_note` text DEFAULT NULL,
  `energy` varchar(255) DEFAULT NULL,
  `skin` varchar(255) DEFAULT NULL,
  `mood` varchar(255) DEFAULT NULL,
  `estrogen` varchar(255) DEFAULT NULL,
  `progesterone` varchar(255) DEFAULT NULL,
  `lh` varchar(255) DEFAULT NULL,
  `modeled` tinyint(1) NOT NULL DEFAULT 0,
  `source` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `bbt_note` text DEFAULT NULL,
  `energy_note` text DEFAULT NULL,
  `hormone_note` text DEFAULT NULL,
  `focus_note` text DEFAULT NULL,
  `current_phase` varchar(255) DEFAULT NULL,
  `phases` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`phases`)),
  `ai_generated` tinyint(1) NOT NULL DEFAULT 0,
  `ai_cached` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bbt_logs`
--

CREATE TABLE `bbt_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED DEFAULT NULL,
  `cycleID` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `log_date` date NOT NULL,
  `temperature` decimal(5,2) NOT NULL,
  `unit` enum('C','F') NOT NULL DEFAULT 'F',
  `logged_at` time DEFAULT NULL,
  `illness` tinyint(1) NOT NULL DEFAULT 0,
  `poor_sleep` tinyint(1) NOT NULL DEFAULT 0,
  `alcohol` tinyint(1) NOT NULL DEFAULT 0,
  `late_wakeup` tinyint(1) NOT NULL DEFAULT 0,
  `travel` tinyint(1) NOT NULL DEFAULT 0,
  `is_excluded` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `coverline_value` decimal(5,2) DEFAULT NULL,
  `ovulation_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `cycle_day` int(11) DEFAULT NULL,
  `phase` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blogs`
--

CREATE TABLE `blogs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `blog_category_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `short_desc` text DEFAULT NULL,
  `content` longtext NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `author_name` varchar(255) DEFAULT NULL,
  `author_avatar` varchar(255) DEFAULT NULL,
  `reading_time` int(10) UNSIGNED DEFAULT NULL,
  `word_count` int(10) UNSIGNED DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `views_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_categories`
--

CREATE TABLE `blog_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_comments`
--

CREATE TABLE `blog_comments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `blog_id` bigint(20) UNSIGNED NOT NULL,
  `author_name` varchar(255) NOT NULL,
  `comment` longtext NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category_place_queries`
--

CREATE TABLE `category_place_queries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` smallint(5) UNSIGNED NOT NULL,
  `places_type` varchar(60) DEFAULT NULL,
  `keyword` varchar(120) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `category_place_queries`
--

INSERT INTO `category_place_queries` (`id`, `category_id`, `places_type`, `keyword`, `created_at`, `updated_at`) VALUES
(1, 1, 'doctor', 'dermatologist', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(2, 2, NULL, 'plastic surgeon', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(3, 3, 'spa', 'medical spa', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(4, 4, 'beauty_salon', 'esthetician', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(5, 5, NULL, 'registered dietitian', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(6, 6, NULL, 'obgyn', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(7, 7, NULL, 'fertility specialist', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(8, 8, NULL, 'fertility clinic', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(9, 9, NULL, 'fertility acupuncture', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(10, 10, NULL, 'genetic counselor', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(11, 11, 'doctor', 'sports medicine doctor', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(12, 12, NULL, 'sports physical therapy', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(13, 13, NULL, 'orthopedic doctor', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(14, 14, NULL, 'sports chiropractor', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(15, 15, NULL, 'sports nutritionist', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(16, 16, 'gym', 'sports performance training', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(17, 16, 'gym', 'athletic recovery', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(18, 17, NULL, 'menopause specialist', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(19, 18, NULL, 'endocrinologist', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(20, 19, NULL, 'hormone replacement therapy clinic', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(21, 20, NULL, 'pelvic floor physical therapy', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(22, 20, NULL, 'pelvic floor physical therapy postpartum', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(23, 21, NULL, 'therapist', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(24, 21, NULL, 'therapist menopause', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(25, 21, NULL, 'women\'s therapist', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(26, 22, NULL, 'maternal fetal medicine', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(27, 23, NULL, 'certified nurse midwife', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(28, 24, NULL, 'doula', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(29, 25, NULL, 'lactation consultant', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(30, 26, NULL, 'postpartum therapist', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(31, 27, NULL, 'primary care physician', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(32, 28, NULL, 'cardiologist', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(33, 29, NULL, 'longevity clinic', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(34, 29, NULL, 'functional medicine', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(35, 30, NULL, 'geriatric doctor', '2026-08-24 22:15:28', '2026-08-24 22:15:28');

-- --------------------------------------------------------

--
-- Table structure for table `category_taxonomy_codes`
--

CREATE TABLE `category_taxonomy_codes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` smallint(5) UNSIGNED NOT NULL,
  `nucc_code` char(10) NOT NULL,
  `nucc_prefix` tinyint(1) NOT NULL DEFAULT 0,
  `label` varchar(160) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `category_taxonomy_codes`
--

INSERT INTO `category_taxonomy_codes` (`id`, `category_id`, `nucc_code`, `nucc_prefix`, `label`, `created_at`, `updated_at`) VALUES
(1, 1, '207N00000X', 0, 'Dermatology', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(2, 2, '208200000X', 0, 'Plastic & Reconstructive Surgery', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(3, 3, '261Q', 1, 'Clinic/Center (org, if physician-directed)', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(4, 5, '133V00000X', 0, 'Dietitian, Registered', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(5, 6, '207V00000X', 0, 'Obstetrics & Gynecology', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(6, 7, '207VE0102X', 0, 'Reproductive Endocrinology', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(7, 8, '261Q', 1, 'Clinic/Center (org NPI)', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(8, 9, '171100000X', 0, 'Acupuncturist', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(9, 10, '170300000X', 0, 'Genetic Counselor, MS', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(10, 11, '207QS0010X', 0, 'Family Medicine, Sports Medicine', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(11, 11, '2081S0010X', 0, 'PM&R, Sports Medicine', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(12, 11, '207XX0005X', 0, 'Orthopaedic Surgery, Sports Medicine', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(13, 12, '225100000X', 0, 'Physical Therapist', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(14, 12, '2251S0007X', 0, 'PT, Sports', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(15, 13, '207X00000X', 0, 'Orthopaedic Surgery', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(16, 14, '111N00000X', 0, 'Chiropractor', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(17, 14, '111NS0005X', 0, 'Chiropractor, Sports', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(18, 15, '133V00000X', 0, 'Dietitian, Registered (CSSD)', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(19, 18, '207RE0101X', 0, 'Endocrinology, Diabetes & Metabolism', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(20, 20, '225100000X', 0, 'Physical Therapist, Pelvic Health', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(21, 21, '103T00000X', 0, 'Psychologist', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(22, 21, '101YM0800X', 0, 'Counselor, Mental Health', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(23, 22, '207VM0101X', 0, 'Maternal & Fetal Medicine', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(24, 23, '367A00000X', 0, 'Advanced Practice Midwife', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(25, 24, '374J00000X', 0, 'Doula', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(26, 25, '174N00000X', 0, 'Lactation Consultant, IBCLC', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(27, 26, '2084P0800X', 0, 'Psychiatry & Neurology, Perinatal Mental Health', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(28, 26, '103T00000X', 0, 'Psychologist (PMH-C)', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(29, 27, '207Q00000X', 0, 'Family Medicine', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(30, 27, '207R00000X', 0, 'Internal Medicine', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(31, 28, '207RC0000X', 0, 'Cardiovascular Disease', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(32, 30, '207QG0300X', 0, 'Family Medicine, Geriatric', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(33, 30, '207RG0300X', 0, 'Internal Medicine, Geriatric', '2026-08-24 22:15:28', '2026-08-24 22:15:28');

-- --------------------------------------------------------

--
-- Table structure for table `cervical_mucus_logs`
--

CREATE TABLE `cervical_mucus_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED NOT NULL,
  `log_date` date NOT NULL,
  `consistency` enum('dry','sticky','creamy','watery','egg_white') NOT NULL,
  `amount` enum('low','medium','high') DEFAULT NULL,
  `color` enum('clear','white','yellow','cloudy') DEFAULT NULL,
  `stretch_cm` decimal(4,1) DEFAULT NULL,
  `fertility_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `sender_type` enum('user','ai') NOT NULL,
  `message` text NOT NULL,
  `data_summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_summary`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_sessions`
--

CREATE TABLE `chat_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_comments`
--

CREATE TABLE `community_comments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `post_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_likes`
--

CREATE TABLE `community_likes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `post_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `liked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_posts`
--

CREATE TABLE `community_posts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 1,
  `is_approved` tinyint(1) NOT NULL DEFAULT 1,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `posted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_post_life_journey`
--

CREATE TABLE `community_post_life_journey` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `community_post_id` bigint(20) UNSIGNED NOT NULL,
  `life_journey_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_post_reports`
--

CREATE TABLE `community_post_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `post_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `report_cause` enum('spam','sexual_content','harassment','other') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `connect_devices`
--

CREATE TABLE `connect_devices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `icon` text DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `connect_devices`
--

INSERT INTO `connect_devices` (`id`, `icon`, `title`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'http://localhost/backend/devices/apple.png', 'Apple HealthKit', 'iOS health & activity data', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(2, 'http://localhost/backend/devices/android.png', 'Android Health Connect', 'Android health & activity data', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(3, 'http://localhost/backend/devices/fitbit.png', 'Fitbit', 'Wearable activity, sleep, HR', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(4, 'http://localhost/backend/devices/fitbit.png', 'MyFitnessPal', 'Wearable activity, sleep, HR', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(5, 'http://localhost/backend/devices/fitbit.png', 'Terra API', 'Unified wearable aggregation (Oura, Whoop, Garmin & more)', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27');

-- --------------------------------------------------------

--
-- Table structure for table `connect_device_profile`
--

CREATE TABLE `connect_device_profile` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `connect_device_id` bigint(20) UNSIGNED NOT NULL,
  `profile_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cycle_calendar_inputs`
--

CREATE TABLE `cycle_calendar_inputs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_day_n` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cycle_consents`
--

CREATE TABLE `cycle_consents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `consent_version` varchar(255) NOT NULL,
  `has_consented` tinyint(1) NOT NULL DEFAULT 0,
  `consented_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cycle_daily_logs`
--

CREATE TABLE `cycle_daily_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED NOT NULL,
  `log_date` date NOT NULL,
  `cycle_day` tinyint(3) UNSIGNED NOT NULL,
  `phase` enum('menstrual','follicular','ovulatory','luteal') NOT NULL,
  `tag` enum('none','period','fertile','ovulation') NOT NULL DEFAULT 'none',
  `is_prediction` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cycle_modes`
--

CREATE TABLE `cycle_modes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `mode` enum('cycle_awareness','trying_to_conceive','avoiding_pregnancy') NOT NULL DEFAULT 'cycle_awareness',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `activated_at` timestamp NULL DEFAULT NULL,
  `has_consented` tinyint(1) NOT NULL DEFAULT 0,
  `consent_version` varchar(255) DEFAULT NULL,
  `consented_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cycle_prediction_caches`
--

CREATE TABLE `cycle_prediction_caches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED NOT NULL,
  `cache_key` varchar(255) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_payload`)),
  `prediction` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`prediction`)),
  `prediction_version` varchar(255) DEFAULT NULL,
  `ai_generated` tinyint(1) NOT NULL DEFAULT 1,
  `ai_cached` tinyint(1) NOT NULL DEFAULT 1,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cycle_settings`
--

CREATE TABLE `cycle_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `average_cycle_length` tinyint(3) UNSIGNED NOT NULL DEFAULT 28,
  `average_period_length` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `luteal_phase_length` tinyint(3) UNSIGNED NOT NULL DEFAULT 14,
  `prediction_method` enum('calendar','bbt','opk','combined') NOT NULL DEFAULT 'combined',
  `temperature_unit` enum('C','F') NOT NULL DEFAULT 'F',
  `period_reminder` tinyint(1) NOT NULL DEFAULT 1,
  `fertility_reminder` tinyint(1) NOT NULL DEFAULT 1,
  `bbt_reminder` tinyint(1) NOT NULL DEFAULT 1,
  `opk_reminder` tinyint(1) NOT NULL DEFAULT 1,
  `allow_ai_prediction` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cycle_statistics`
--

CREATE TABLE `cycle_statistics` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `completed_cycles` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `average_cycle_length` tinyint(3) UNSIGNED NOT NULL DEFAULT 28,
  `average_period_length` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `average_ovulation_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `cycle_variance_days` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `reliability_level` enum('low','medium','high') NOT NULL DEFAULT 'low',
  `reliability_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `last_period_date` date DEFAULT NULL,
  `predicted_next_period` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_scriptures`
--

CREATE TABLE `daily_scriptures` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `scripture_date` date DEFAULT NULL,
  `badge` varchar(255) DEFAULT NULL,
  `verse_text` text DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fertility_events`
--

CREATE TABLE `fertility_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED NOT NULL,
  `event_date` date NOT NULL,
  `event_type` enum('fertile_window_start','fertile_window_end','lh_surge','ovulation_predicted','ovulation_confirmed','implantation_window','missed_period') NOT NULL,
  `cycle_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `source` enum('calendar','bbt','opk','mucus','ai','combined') NOT NULL DEFAULT 'calendar',
  `priority_level` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `message` text DEFAULT NULL,
  `is_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `health_goals`
--

CREATE TABLE `health_goals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `health_goals`
--

INSERT INTO `health_goals` (`id`, `title`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Hormonal Balance', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(2, 'Better Sleep', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(3, 'Weight Management', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(4, 'Fertility', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(5, 'Skin', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(6, 'Stress Reduction', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(7, 'Athletic Performance', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(8, 'Healthy Aging', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(9, 'Energy Boost', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27');

-- --------------------------------------------------------

--
-- Table structure for table `health_goal_profile`
--

CREATE TABLE `health_goal_profile` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `health_goal_id` bigint(20) UNSIGNED NOT NULL,
  `profile_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `health_logs`
--

CREATE TABLE `health_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `log_date` date NOT NULL,
  `mood` varchar(10) NOT NULL,
  `energy_level` enum('Very Low','Low','Moderate','High','Very High') NOT NULL,
  `symptoms` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`symptoms`)),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `health_trends`
--

CREATE TABLE `health_trends` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `period` enum('7d','30d') NOT NULL DEFAULT '30d',
  `range_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`range_options`)),
  `sleep_energy_correlation_chart` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sleep_energy_correlation_chart`)),
  `sleep_energy_correlation_diagram` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sleep_energy_correlation_diagram`)),
  `hormone_mood` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`hormone_mood`)),
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hormone_snapshots`
--

CREATE TABLE `hormone_snapshots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED NOT NULL,
  `snapshot_date` date NOT NULL,
  `estrogen` enum('very_low','low','moderate','high','peak') DEFAULT NULL,
  `progesterone` enum('very_low','low','moderate','high','peak') DEFAULT NULL,
  `lh` enum('very_low','low','moderate','high','peak') DEFAULT NULL,
  `fsh` enum('very_low','low','moderate','high') DEFAULT NULL,
  `modeled` tinyint(1) NOT NULL DEFAULT 1,
  `source` enum('phase_model','lab','ai','wearable') NOT NULL DEFAULT 'phase_model',
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `intercourse_logs`
--

CREATE TABLE `intercourse_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED NOT NULL,
  `log_date` date NOT NULL,
  `protected` tinyint(1) NOT NULL DEFAULT 0,
  `ejaculation` tinyint(1) NOT NULL DEFAULT 1,
  `inside_fertile_window` tinyint(1) NOT NULL DEFAULT 0,
  `trying_to_conceive` tinyint(1) NOT NULL DEFAULT 1,
  `cycle_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_reports`
--

CREATE TABLE `lab_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `lab_report` varchar(255) DEFAULT NULL,
  `panel` varchar(255) DEFAULT NULL,
  `biomarkers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`biomarkers`)),
  `ai_insights` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ai_insights`)),
  `next_steps` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`next_steps`)),
  `analysis_status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `life_journeys`
--

CREATE TABLE `life_journeys` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `icon` text DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `life_journeys`
--

INSERT INTO `life_journeys` (`id`, `icon`, `title`, `subtitle`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'http://localhost/backend/journeys/beauty.png', 'Beauty & Radiance', 'Skin, hair, confidence - the entry wedge.', 'Revealing how skin, energy, and outward vitality reflect inner health, turning daily signals into visible results.', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(2, 'http://localhost/backend/journeys/tracking.png', 'Cycle & Fertility', 'Cycle, ovulation, conception planning.', 'Making sense of your cycle month to month — so ovulation, hormones, and fertile windows stop being a mystery, whether you\'re planning for pregnancy or just getting to know your body.', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(3, 'http://localhost/backend/journeys/athlete.png', 'Athlete', 'Training, recovery, performance by hormone phase.', 'Optimizing training, recovery, and performance around the hormonal rhythms that female-specific data too often ignores.', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(4, 'http://localhost/backend/journeys/menopause.png', 'Perimenopause/Menopause & Vitality', 'Symptom navigation and long-term vitality.', 'Turning the hormonal upheaval of perimenopause and menopause into something you can finally understand — easing symptoms today while protecting your strength for the years ahead.', 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(5, 'http://localhost/backend/journeys/pregnancy.png', 'Pregnancy & Postpartum', 'Prenatal through recovery, supported.', 'Tracking the body\'s rapid changes and supporting recovery through one of life\'s most demanding chapters.', 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(6, 'http://localhost/backend/journeys/lifelong.png', 'Lifelong Thriving', 'Prevention and healthspan for the long run.', 'Sustaining strength, clarity, and well-being across the years, with intelligence that keeps adapting as you do.', 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28');

-- --------------------------------------------------------

--
-- Table structure for table `life_journey_features`
--

CREATE TABLE `life_journey_features` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `life_journey_id` bigint(20) UNSIGNED NOT NULL,
  `feature_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `life_journey_features`
--

INSERT INTO `life_journey_features` (`id`, `life_journey_id`, `feature_name`, `created_at`, `updated_at`) VALUES
(1, 1, 'Skin Health Tracking', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(2, 1, 'Hydration Monitoring', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(3, 1, 'Energy & Vitality Scores', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(4, 1, 'Sleep Impact Analysis', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(5, 1, 'Skin Trend Monitoring', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(6, 2, 'Cycle Tracking', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(7, 2, 'Ovulation Prediction', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(8, 2, 'Fertility Window Detection', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(9, 2, 'Hormone Pattern Analysis', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(10, 2, 'Cycle Forecasting', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(11, 3, 'Training Load Monitoring', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(12, 3, 'Recovery Analysis', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(13, 3, 'HRV Tracking', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(14, 3, 'Cycle-Based Training Plans', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(15, 3, 'Fatigue Detection', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(16, 4, 'Hot Flash Tracking', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(17, 4, 'Sleep Disturbance Monitoring', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(18, 4, 'Mood Changes', '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(19, 4, 'Hormonal Trend Analysis', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(20, 4, 'Menopause Education', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(21, 5, 'Week Tracking', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(22, 5, 'Symptom Monitoring', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(23, 5, 'Health Checkpoints', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(24, 5, 'Sleep Tracking', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(25, 5, 'Nutrition Reminders', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(26, 6, 'Healthy Aging Insights', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(27, 6, 'Cognitive Wellness Monitoring', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(28, 6, 'Vitality Scoring', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(29, 6, 'Mobility Trends', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(30, 6, 'Preventive Health Signals', '2026-08-24 22:15:28', '2026-08-24 22:15:28');

-- --------------------------------------------------------

--
-- Table structure for table `life_journey_profile`
--

CREATE TABLE `life_journey_profile` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `life_journey_id` bigint(20) UNSIGNED NOT NULL,
  `profile_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `life_stages`
--

CREATE TABLE `life_stages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `life_stages`
--

INSERT INTO `life_stages` (`id`, `title`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Teen', 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(2, 'Reproductive Years', 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(3, 'Pregnancy', 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(4, 'Postpartum', 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(5, 'Perimenopause', 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(6, 'Menopause', 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28');

-- --------------------------------------------------------

--
-- Table structure for table `marketplace_life_stages`
--

CREATE TABLE `marketplace_life_stages` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `slug` varchar(40) NOT NULL,
  `name` varchar(80) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `marketplace_life_stages`
--

INSERT INTO `marketplace_life_stages` (`id`, `slug`, `name`, `created_at`, `updated_at`) VALUES
(1, 'beauty-radiance', 'Beauty & Radiance', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(2, 'cycle-fertility', 'Cycle & Fertility', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(3, 'athlete', 'Athlete', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(4, 'perimenopause-menopause-vitality', 'Perimenopause / Menopause & Vitality', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(5, 'pregnancy-postpartum', 'Pregnancy & Postpartum', '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(6, 'lifelong-thriving', 'Lifelong Thriving', '2026-08-24 22:15:28', '2026-08-24 22:15:28');

-- --------------------------------------------------------

--
-- Table structure for table `marketplace_life_stage_category`
--

CREATE TABLE `marketplace_life_stage_category` (
  `marketplace_life_stage_id` tinyint(3) UNSIGNED NOT NULL,
  `category_id` smallint(5) UNSIGNED NOT NULL,
  `display_priority` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `marketplace_life_stage_category`
--

INSERT INTO `marketplace_life_stage_category` (`marketplace_life_stage_id`, `category_id`, `display_priority`) VALUES
(1, 1, 1),
(1, 2, 2),
(1, 3, 3),
(1, 4, 4),
(1, 5, 5),
(2, 6, 1),
(2, 7, 2),
(2, 8, 3),
(2, 9, 4),
(2, 10, 5),
(3, 11, 1),
(3, 12, 2),
(3, 13, 3),
(3, 14, 4),
(3, 15, 5),
(3, 16, 6),
(4, 6, 2),
(4, 17, 1),
(4, 18, 3),
(4, 19, 4),
(4, 20, 5),
(4, 21, 6),
(5, 6, 1),
(5, 20, 6),
(5, 22, 2),
(5, 23, 3),
(5, 24, 4),
(5, 25, 5),
(5, 26, 7),
(6, 5, 5),
(6, 18, 3),
(6, 21, 6),
(6, 27, 1),
(6, 28, 2),
(6, 29, 4),
(6, 30, 7);

-- --------------------------------------------------------

--
-- Table structure for table `menstrual_cycles`
--

CREATE TABLE `menstrual_cycles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `period_start_date` date DEFAULT NULL,
  `period_end_date` date DEFAULT NULL,
  `current_cycle_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `cycle_length` tinyint(3) UNSIGNED DEFAULT NULL,
  `period_length` tinyint(3) UNSIGNED DEFAULT NULL,
  `predicted_ovulation_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `confirmed_ovulation_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `predicted_peak_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `fertile_start_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `fertile_end_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `current_phase` enum('menstrual','follicular','ovulatory','luteal') DEFAULT NULL,
  `prediction_source` enum('calendar','bbt','opk','mucus','combined') NOT NULL DEFAULT 'calendar',
  `is_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `metros`
--

CREATE TABLE `metros` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `state` char(2) NOT NULL,
  `centroid` point NOT NULL,
  `radius_km` smallint(6) NOT NULL,
  `density_tier` tinyint(4) NOT NULL DEFAULT 1,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `metros`
--

INSERT INTO `metros` (`id`, `name`, `state`, `centroid`, `radius_km`, `density_tier`, `active`, `created_at`, `updated_at`) VALUES
(1, 'San Francisco Bay Area', 'CA', 0xe6100000010100000050fc1873d79a5ec0d0d556ec2fe34240, 40, 3, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(2, 'Salt Lake City', 'UT', 0xe610000001010000001b2fdd2406f95bc0fe65f7e461614440, 35, 2, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(3, 'New York City', 'NY', 0xe61000000101000000aaf1d24d628052c05e4bc8073d5b4440, 45, 3, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(4, 'Los Angeles', 'CA', 0xe610000001010000004182e2c7988f5dc0f46c567dae064140, 50, 3, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(5, 'Chicago', 'IL', 0xe6100000010100000055c1a8a44ee855c00e4faf9465f04440, 40, 3, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(6, 'Miami', 'FL', 0xe61000000101000000dcd78173460c54c0fb5c6dc5fec23940, 35, 2, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(7, 'Dallas-Fort Worth', 'TX', 0xe610000001010000005eba490c023358c0cf66d5e76a634040, 45, 3, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(8, 'Seattle', 'WA', 0xe610000001010000001ac05b2041955ec0e86a2bf697cd4740, 35, 2, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0000_01_01_000000_create_settings_table', 1),
(2, '0001_01_01_000000_create_users_table', 1),
(3, '0001_01_01_000001_create_cache_table', 1),
(4, '0001_01_01_000002_create_jobs_table', 1),
(5, '2026_06_10_081600_create_activities_table', 1),
(6, '2026_06_10_081600_create_connect_devices_table', 1),
(7, '2026_06_10_081600_create_health_goals_table', 1),
(8, '2026_06_10_081600_create_life_journeys_table', 1),
(9, '2026_06_10_081600_create_life_stages_table', 1),
(10, '2026_06_10_081600_create_profiles_table', 1),
(11, '2026_06_10_081601_create_health_goal_profile_table', 1),
(12, '2026_06_10_081602_create_life_journey_profile_table', 1),
(13, '2026_06_10_081603_create_connect_device_profile_table', 1),
(14, '2026_06_11_073731_create_permission_tables', 1),
(15, '2026_06_11_073821_create_personal_access_tokens_table', 1),
(16, '2026_06_13_014524_create_pages_table', 1),
(17, '2026_06_13_014702_create_notification_settings_table', 1),
(18, '2026_06_13_014824_create_notification_preferences_table', 1),
(19, '2026_06_16_072055_create_notifications_table', 1),
(20, '2026_07_13_042748_create_community_posts_table', 1),
(21, '2026_07_13_071828_create_community_comments_table', 1),
(22, '2026_07_13_071839_create_community_likes_table', 1),
(23, '2026_07_13_071849_create_community_post_reports_table', 1),
(24, '2026_07_13_072039_create_subscription_plans_table', 1),
(25, '2026_07_13_080748_create_topup_products_table', 1),
(26, '2026_07_13_080749_create_payments_table', 1),
(27, '2026_07_13_083740_create_user_limits_table', 1),
(28, '2026_07_14_030901_create_health_logs_table', 1),
(29, '2026_07_15_070740_create_terra_activity_data_table', 1),
(30, '2026_07_15_070740_create_terra_connections_table', 1),
(31, '2026_07_15_075535_create_lab_reports_table', 1),
(32, '2026_07_18_022122_create_life_journey_features_table', 1),
(33, '2026_07_18_032318_create_skin_scans_table', 1),
(34, '2026_07_18_032329_create_skin_scan_recommendations_table', 1),
(35, '2026_07_20_000001_create_marketplace_life_stages_table', 1),
(36, '2026_07_20_000002_create_provider_categories_table', 1),
(37, '2026_07_20_000003_create_marketplace_life_stage_category_table', 1),
(38, '2026_07_20_000004_create_category_taxonomy_codes_table', 1),
(39, '2026_07_20_000005_create_category_place_queries_table', 1),
(40, '2026_07_20_000006_create_metros_table', 1),
(41, '2026_07_20_000007_create_providers_table', 1),
(42, '2026_07_20_000008_create_provider_category_table', 1),
(43, '2026_07_20_000009_create_place_details_cache_table', 1),
(44, '2026_07_20_000010_create_vetting_records_table', 1),
(45, '2026_07_20_000011_create_sponsored_slots_table', 1),
(46, '2026_07_20_000012_create_slate_events_table', 1),
(47, '2026_07_20_000013_add_is_marketplace_admin_to_users_table', 1),
(48, '2026_07_20_084858_create_chat_sessions_table', 1),
(49, '2026_07_20_084911_create_chat_messages_table', 1),
(50, '2026_07_21_070011_create_daily_scriptures_table', 1),
(51, '2026_07_21_082542_create_health_trends_table', 1),
(52, '2026_07_22_020556_create_smart_analyses_table', 1),
(53, '2026_07_22_054631_create_numera_insights_table', 1),
(54, '2026_07_27_032241_create_waitlist_entries_table', 1),
(55, '2026_07_28_060450_create_cycle_modes_table', 1),
(56, '2026_07_28_060953_create_cycle_settings_table', 1),
(57, '2026_07_28_061024_create_cycle_statistics_table', 1),
(58, '2026_07_28_061109_create_menstrual_cycles_table', 1),
(59, '2026_07_28_061143_create_period_logs_table', 1),
(60, '2026_07_28_061215_create_cycle_daily_logs_table', 1),
(61, '2026_07_28_061251_create_bbt_logs_table', 1),
(62, '2026_07_28_061318_create_opk_logs_table', 1),
(63, '2026_07_28_061352_create_cervical_mucus_logs_table', 1),
(64, '2026_07_28_061420_create_symptom_logs_table', 1),
(65, '2026_07_28_061448_create_ovulation_reconciliations_table', 1),
(66, '2026_07_28_061515_create_cycle_prediction_caches_table', 1),
(67, '2026_07_28_061542_create_signal_histories_table', 1),
(68, '2026_07_28_061616_create_intercourse_logs_table', 1),
(69, '2026_07_28_061644_create_pregnancy_test_logs_table', 1),
(70, '2026_07_28_061709_create_fertility_events_table', 1),
(71, '2026_07_28_061741_create_hormone_snapshots_table', 1),
(72, '2026_07_28_061806_create_phase_insights_table', 1),
(73, '2026_07_28_061834_create_notification_histories_table', 1),
(74, '2026_07_28_061901_create_cycle_consents_table', 1),
(75, '2026_07_29_080244_create_blog_categories_table', 1),
(76, '2026_07_29_080249_create_blogs_table', 1),
(77, '2026_07_29_083620_create_ttc_predictions_table', 1),
(78, '2026_07_29_084249_create_blog_comments_table', 1),
(79, '2026_07_30_021639_create_awareness_snapshots_table', 1),
(80, '2026_08_01_035735_create_opk_data_table', 1),
(81, '2026_08_02_040053_create_new_awareness_snapshots_table', 1),
(82, '2026_08_03_044409_create_cycle_calendar_inputs_table', 1),
(83, '2026_08_07_053500_add_coverline_fields_to_bbt_logs_table', 1),
(84, '2026_08_19_000000_add_unique_index_to_providers_google_place_id', 1);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `new_awareness_snapshots`
--

CREATE TABLE `new_awareness_snapshots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `cycle_context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cycle_context`)),
  `current_phase` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`current_phase`)),
  `luteal_phase` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`luteal_phase`)),
  `hormone_levels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`hormone_levels`)),
  `what_to_know` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`what_to_know`)),
  `four_phase_cycle` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`four_phase_cycle`)),
  `ai_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ai_response`)),
  `ai_generated` tinyint(1) NOT NULL DEFAULT 0,
  `ai_cached` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) UNSIGNED NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_histories`
--

CREATE TABLE `notification_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('period_reminder','fertility_reminder','bbt_reminder','opk_reminder','ovulation_reminder','general') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','sent','delivered','read','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_preferences`
--

CREATE TABLE `notification_preferences` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `new_inspection` tinyint(1) NOT NULL DEFAULT 1,
  `upcoming` tinyint(1) NOT NULL DEFAULT 1,
  `reschedule` tinyint(1) NOT NULL DEFAULT 1,
  `cancellation` tinyint(1) NOT NULL DEFAULT 1,
  `email` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_settings`
--

CREATE TABLE `notification_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` tinyint(1) NOT NULL DEFAULT 1,
  `push` tinyint(1) NOT NULL DEFAULT 1,
  `security_alert` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `numera_insights`
--

CREATE TABLE `numera_insights` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `tag` varchar(255) DEFAULT NULL,
  `eyebrow` varchar(255) DEFAULT NULL,
  `headline` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `cycle_day` int(11) DEFAULT NULL,
  `theme` varchar(255) DEFAULT NULL,
  `priority` varchar(255) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `opk_data`
--

CREATE TABLE `opk_data` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`response_data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `opk_logs`
--

CREATE TABLE `opk_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED NOT NULL,
  `log_date` date NOT NULL,
  `result` enum('negative','low','high','positive','peak') NOT NULL,
  `lh_value` decimal(6,2) DEFAULT NULL,
  `outside_window` tinyint(1) NOT NULL DEFAULT 0,
  `affects_prediction` tinyint(1) NOT NULL DEFAULT 1,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ovulation_reconciliations`
--

CREATE TABLE `ovulation_reconciliations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED NOT NULL,
  `calendar_predicted_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `bbt_confirmed_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `lh_surge_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `mucus_peak_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `final_confirmed_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `final_source` enum('calendar','bbt','opk','mucus','combined') DEFAULT NULL,
  `offset_days` smallint(6) NOT NULL DEFAULT 0,
  `luteal_phase_length` tinyint(3) UNSIGNED NOT NULL DEFAULT 14,
  `has_discrepancy` tinyint(1) NOT NULL DEFAULT 0,
  `discrepancy_note` text DEFAULT NULL,
  `is_reconciled` tinyint(1) NOT NULL DEFAULT 0,
  `reconciled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`content`)),
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `title`, `slug`, `content`, `status`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 'Terms of Service', 'terms-of-service', '{\"heading\":\"Terms of Service\",\"paragraphs\":[\"By using Neumera, you agree to our Terms of Service. Neumera provides health education and tracking tools. It is not a medical device, does not provide medical advice, and is not a substitute for professional healthcare. Use of the app is at your own discretion.\",\"Neumera is a health tracking and wellness app. It is NOT a substitute for professional medical advice, diagnosis, or treatment. Always consult with qualified healthcare providers regarding your health concerns.\",\"All content, features, and functionality of the Services, including but not limited to text, graphics, logos, icons, images, audio clips, video clips, data compilations, and software, are owned by Neumera LLC or its licensors and are protected by United States and international copyright, trademark, patent, trade secret, and other intellectual property laws.\",\"You retain ownership of your personal data and content you submit. By using the Services, you grant us a limited license to use, store, and process your data as described in our Privacy Policy.\"]}', 1, NULL, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(2, 'Privacy Policy', 'privacy-policy', '{\"heading\":\"Privacy Policy\",\"paragraphs\":[\"Neumera collects health data you choose to input. We do not sell your personal health data. You can request a full data export or deletion at any time under Privacy Settings.\",\"Neumera is a general wellness platform. It is not a medical device and does not provide medical advice, diagnosis, or treatment. \\\"Neumera,\\\" our AI coach, generates generalized wellness guidance and pattern-based insights. All outputs may be inaccurate or incomplete and should not be relied upon for urgent, emergency, or clinical decisions. If you have a medical concern, contact a licensed healthcare professional.\",\"Fight the Number LLC operates the Neumera mobile application and website. We are committed to protecting your privacy.\"],\"sections\":[{\"title\":\"Data Usage\",\"body\":\"Your health data is encrypted end-to-end and never sold to third parties. You may export or delete your data at any time.\"},{\"title\":\"AI Disclaimer\",\"body\":\"Neumera AI provides health insights for informational purposes only. It is not a substitute for professional medical advice, diagnosis, or treatment.\"},{\"title\":\"Data Control\",\"body\":\"You control exactly which data sources are connected. All integrations can be disconnected at any time from your Profile settings.\"}]}', 1, NULL, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(3, 'Medical Advice', 'medical-advice', '{\"heading\":\"Medical Advice\",\"paragraphs\":[\"Neumera does not provide medical advice, diagnosis, or treatment. Health insights, scores, and signals are informational only. Always consult a licensed healthcare professional before making health decisions.\"]}', 1, NULL, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(4, 'Disclaimer', 'disclaimer', '{\"heading\":\"Disclaimer\",\"sub_heading\":\"\\ud83d\\udd34 Fertility awareness, not birth control\",\"paragraphs\":[\"Neumera is not a contraceptive and has not been cleared or approved by the U.S. FDA as a method of contraception. Cycle predictions, fertile window estimates, and ovulation predictions are educational estimates only. They may be inaccurate and cannot tell you which days are safe to avoid pregnancy. Do not use Neumera to prevent pregnancy. Consult a healthcare provider for contraceptive needs.\"]}', 1, NULL, '2026-08-24 22:15:27', '2026-08-24 22:15:27');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `subscription_plan_id` bigint(20) UNSIGNED DEFAULT NULL,
  `topup_product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` varchar(255) NOT NULL COMMENT 'subscription',
  `billing_cycle` enum('month','year') DEFAULT NULL,
  `current_period_start` timestamp NULL DEFAULT NULL,
  `current_period_end` timestamp NULL DEFAULT NULL,
  `stripe_subscription_id` varchar(255) DEFAULT NULL,
  `amount` decimal(8,2) NOT NULL,
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  `stripe_invoice_id` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending' COMMENT 'pending,paid,cancel',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `period_logs`
--

CREATE TABLE `period_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED NOT NULL,
  `log_date` date NOT NULL,
  `flow` enum('spotting','light','medium','heavy') NOT NULL,
  `clotting` tinyint(1) NOT NULL DEFAULT 0,
  `cramps` tinyint(1) NOT NULL DEFAULT 0,
  `headache` tinyint(1) NOT NULL DEFAULT 0,
  `fatigue` tinyint(1) NOT NULL DEFAULT 0,
  `pain_level` tinyint(3) UNSIGNED DEFAULT NULL COMMENT '0-10',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `phase_insights`
--

CREATE TABLE `phase_insights` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED NOT NULL,
  `insight_date` date NOT NULL,
  `phase` enum('menstrual','follicular','ovulatory','luteal') NOT NULL,
  `education` text DEFAULT NULL,
  `energy_note` text DEFAULT NULL,
  `hormone_note` text DEFAULT NULL,
  `focus_note` text DEFAULT NULL,
  `skin_note` text DEFAULT NULL,
  `nutrition_note` text DEFAULT NULL,
  `exercise_note` text DEFAULT NULL,
  `ai_generated` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `place_details_cache`
--

CREATE TABLE `place_details_cache` (
  `provider_id` bigint(20) UNSIGNED NOT NULL,
  `rating` decimal(2,1) DEFAULT NULL,
  `review_count` int(11) DEFAULT NULL,
  `hours_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`hours_json`)),
  `business_status` varchar(30) DEFAULT NULL,
  `fetched_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pregnancy_test_logs`
--

CREATE TABLE `pregnancy_test_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED NOT NULL,
  `test_date` date NOT NULL,
  `result` enum('negative','positive','invalid') NOT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `test_time` enum('morning','afternoon','evening','night') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profiles`
--

CREATE TABLE `profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `life_stage_id` bigint(20) UNSIGNED DEFAULT NULL,
  `activity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `profile_img` text DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `height` double DEFAULT NULL,
  `weight` double DEFAULT NULL,
  `stripe_account_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `providers`
--

CREATE TABLE `providers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `npi` char(10) DEFAULT NULL,
  `google_place_id` varchar(255) DEFAULT NULL,
  `display_name` varchar(160) NOT NULL,
  `org_name` varchar(160) DEFAULT NULL,
  `phone_e164` varchar(20) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `addr_line1` varchar(255) DEFAULT NULL,
  `addr_line2` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `zip` varchar(255) DEFAULT NULL,
  `location` point NOT NULL,
  `metro_id` smallint(5) UNSIGNED DEFAULT NULL,
  `source_nppes` tinyint(1) NOT NULL DEFAULT 0,
  `source_places` tinyint(1) NOT NULL DEFAULT 0,
  `match_confidence` decimal(3,2) DEFAULT NULL,
  `status` enum('candidate','vetted','active','suspended','excluded') NOT NULL DEFAULT 'candidate',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `provider_categories`
--

CREATE TABLE `provider_categories` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `slug` varchar(60) NOT NULL,
  `display_name` varchar(120) NOT NULL,
  `vetting_tier` enum('medical','licensed_nonmedical','consumer') NOT NULL,
  `requires_npi` tinyint(1) NOT NULL DEFAULT 0,
  `vetting_source` varchar(255) DEFAULT NULL,
  `launch_phase` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `provider_categories`
--

INSERT INTO `provider_categories` (`id`, `slug`, `display_name`, `vetting_tier`, `requires_npi`, `vetting_source`, `launch_phase`, `active`, `created_at`, `updated_at`) VALUES
(1, 'dermatology', 'Dermatology', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(2, 'plastic-reconstructive-surgery', 'Plastic & Reconstructive Surgery', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(3, 'medical-spa', 'Medical Spa', 'licensed_nonmedical', 0, 'Professional Association / Directory', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(4, 'esthetician-skincare-studio', 'Esthetician / Skincare Studio', 'licensed_nonmedical', 0, 'Professional Association / Directory', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(5, 'registered-dietitian', 'Registered Dietitian', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(6, 'obgyn', 'OB-GYN', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(7, 'reproductive-endocrinology-infertility', 'Reproductive Endocrinology & Infertility (REI)', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(8, 'fertility-clinic', 'Fertility Clinic (org-level)', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(9, 'acupuncturist-fertility', 'Acupuncturist (fertility support)', 'licensed_nonmedical', 0, 'Professional Association / Directory', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(10, 'genetic-counselor', 'Genetic Counselor', 'licensed_nonmedical', 0, 'Professional Association / Directory', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(11, 'sports-medicine-physician', 'Sports Medicine Physician', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(12, 'physical-therapist-sports', 'Physical Therapist (sports)', 'licensed_nonmedical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(13, 'orthopedics', 'Orthopedics', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(14, 'chiropractor-sports', 'Chiropractor', 'licensed_nonmedical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(15, 'sports-dietitian', 'Sports Dietitian', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(16, 'performance-recovery-studio', 'Performance / Recovery Studio', 'consumer', 0, 'Professional Association / Directory', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(17, 'menopause-specialist', 'Menopause Specialist (MSCP)', 'licensed_nonmedical', 0, 'Professional Association / Directory', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(18, 'endocrinology', 'Endocrinology', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(19, 'hrt-hormone-clinic', 'HRT / Hormone Clinic', 'licensed_nonmedical', 0, 'Professional Association / Directory', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(20, 'pelvic-floor-pt', 'Pelvic-Floor Physical Therapist', 'licensed_nonmedical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(21, 'therapist-mental-health', 'Therapist / Mental Health', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(22, 'maternal-fetal-medicine', 'Maternal-Fetal Medicine', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(23, 'certified-nurse-midwife', 'Certified Nurse Midwife', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(24, 'doula', 'Doula', 'licensed_nonmedical', 0, 'Professional Association / Directory', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(25, 'lactation-consultant', 'Lactation Consultant (IBCLC)', 'licensed_nonmedical', 0, 'Professional Association / Directory', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(26, 'perinatal-mental-health', 'Perinatal Mental Health', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(27, 'primary-care', 'Primary Care', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(28, 'cardiology', 'Cardiology', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(29, 'longevity-functional-medicine', 'Longevity / Functional Medicine', 'licensed_nonmedical', 0, 'Professional Association / Directory', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28'),
(30, 'geriatric-medicine', 'Geriatric Medicine', 'medical', 1, 'NPI Registry / State Board', 1, 1, '2026-08-24 22:15:28', '2026-08-24 22:15:28');

-- --------------------------------------------------------

--
-- Table structure for table `provider_category`
--

CREATE TABLE `provider_category` (
  `provider_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` smallint(5) UNSIGNED NOT NULL,
  `source` enum('nppes_taxonomy','places_match','manual') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `platform_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `logo` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `platform_name`, `email`, `logo`, `created_at`, `updated_at`) VALUES
(1, 'NEUMERA', 'support@neumera.com', NULL, '2026-08-24 22:15:27', '2026-08-24 22:15:27');

-- --------------------------------------------------------

--
-- Table structure for table `signal_histories`
--

CREATE TABLE `signal_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED DEFAULT NULL,
  `log_date` date NOT NULL,
  `calendar_logged` tinyint(1) NOT NULL DEFAULT 0,
  `bbt_logged` tinyint(1) NOT NULL DEFAULT 0,
  `opk_logged` tinyint(1) NOT NULL DEFAULT 0,
  `mucus_logged` tinyint(1) NOT NULL DEFAULT 0,
  `symptoms_logged` tinyint(1) NOT NULL DEFAULT 0,
  `signal_strength` enum('none','low','medium','high') NOT NULL DEFAULT 'none',
  `signals` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`signals`)),
  `ai_generated` tinyint(1) NOT NULL DEFAULT 0,
  `ai_cached` tinyint(1) NOT NULL DEFAULT 0,
  `sources` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sources`)),
  `backend_errors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`backend_errors`)),
  `status_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `skin_scans`
--

CREATE TABLE `skin_scans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `overall_score` int(11) NOT NULL,
  `hydration_score` int(11) NOT NULL,
  `redness_score` int(11) NOT NULL,
  `texture_score` int(11) NOT NULL,
  `glow_index` int(11) NOT NULL,
  `pore_health_score` int(11) NOT NULL,
  `elasticity_score` int(11) NOT NULL,
  `hydration_status` varchar(255) NOT NULL DEFAULT 'Fair',
  `redness_status` varchar(255) NOT NULL DEFAULT 'Low',
  `texture_status` varchar(255) NOT NULL DEFAULT 'Good',
  `glow_status` varchar(255) NOT NULL DEFAULT 'Fair',
  `pore_health_status` varchar(255) NOT NULL DEFAULT 'Low',
  `elasticity_status` varchar(255) NOT NULL DEFAULT 'Good',
  `neumera_insight` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `skin_scan_recommendations`
--

CREATE TABLE `skin_scan_recommendations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `skin_scan_id` bigint(20) UNSIGNED NOT NULL,
  `icon_type` varchar(255) NOT NULL DEFAULT 'drop',
  `recommendation_text` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `slate_events`
--

CREATE TABLE `slate_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `occurred_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `metro_id` smallint(5) UNSIGNED DEFAULT NULL,
  `category_id` smallint(5) UNSIGNED DEFAULT NULL,
  `marketplace_life_stage_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `provider_id` bigint(20) UNSIGNED DEFAULT NULL,
  `slot_position` tinyint(4) DEFAULT NULL,
  `sponsored` tinyint(1) NOT NULL DEFAULT 0,
  `event_type` enum('impression','tap','call','directions','website','share') NOT NULL,
  `session_token` char(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `smart_analyses`
--

CREATE TABLE `smart_analyses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `alerts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`alerts`)),
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sponsored_slots`
--

CREATE TABLE `sponsored_slots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `metro_id` smallint(5) UNSIGNED NOT NULL,
  `category_id` smallint(5) UNSIGNED NOT NULL,
  `slot_number` tinyint(4) NOT NULL,
  `provider_id` bigint(20) UNSIGNED NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `status` enum('reserved','active','expired','cancelled') NOT NULL DEFAULT 'reserved',
  `monthly_rate_cents` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price_monthly` decimal(8,2) NOT NULL DEFAULT 0.00,
  `price_annual` decimal(8,2) DEFAULT NULL,
  `skin_scans_limit` int(11) NOT NULL DEFAULT 0,
  `ai_coaching_limit` int(11) NOT NULL DEFAULT 0,
  `ai_coaching_model` varchar(255) DEFAULT NULL,
  `deep_reports_limit` int(11) NOT NULL DEFAULT 0,
  `daily_summary_frequency` enum('weekly','unlimited') NOT NULL DEFAULT 'weekly',
  `tracking_label` varchar(255) NOT NULL,
  `tracking_integrations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tracking_integrations`)),
  `stripe_product_id` varchar(255) DEFAULT NULL,
  `stripe_price_monthly_id` varchar(255) DEFAULT NULL,
  `stripe_price_annual_id` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `slug`, `name`, `description`, `price_monthly`, `price_annual`, `skin_scans_limit`, `ai_coaching_limit`, `ai_coaching_model`, `deep_reports_limit`, `daily_summary_frequency`, `tracking_label`, `tracking_integrations`, `stripe_product_id`, `stripe_price_monthly_id`, `stripe_price_annual_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'free', 'Free', 'Basic access with limited features.', 0.00, 0.00, 2, 5, 'haiku', 0, 'weekly', 'Cycle + BBT, manual + limited sync', '[\"cycle_tracking\",\"bbt_manual\"]', NULL, NULL, NULL, 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(2, 'premium', 'Premium', 'Full multi-stage access with wearables and lab OCR.', 14.99, 125.00, 6, 75, 'all', 0, 'unlimited', 'Full multi-stage, all wearables, lab OCR', '[\"wearables\",\"lab_ocr\"]', NULL, NULL, NULL, 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(3, 'elite', 'Elite', 'Everything in Premium plus priority routing and deep reports.', 24.99, 219.99, 12, 150, 'all', 3, 'unlimited', 'Everything in Premium + priority routing', '[\"wearables\",\"lab_ocr\",\"priority_routing\"]', NULL, NULL, NULL, 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27');

-- --------------------------------------------------------

--
-- Table structure for table `symptom_logs`
--

CREATE TABLE `symptom_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED NOT NULL,
  `log_date` date NOT NULL,
  `pain_level` tinyint(3) UNSIGNED DEFAULT NULL,
  `mood` enum('very_low','low','neutral','good','excellent') DEFAULT NULL,
  `energy` enum('very_low','low','moderate','high','very_high') DEFAULT NULL,
  `cramps` tinyint(1) NOT NULL DEFAULT 0,
  `bloating` tinyint(1) NOT NULL DEFAULT 0,
  `headache` tinyint(1) NOT NULL DEFAULT 0,
  `fatigue` tinyint(1) NOT NULL DEFAULT 0,
  `acne` tinyint(1) NOT NULL DEFAULT 0,
  `breast_tenderness` tinyint(1) NOT NULL DEFAULT 0,
  `nausea` tinyint(1) NOT NULL DEFAULT 0,
  `insomnia` tinyint(1) NOT NULL DEFAULT 0,
  `libido` tinyint(3) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `terra_activity_data`
--

CREATE TABLE `terra_activity_data` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `terra_user_id` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `data_generated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `terra_connections`
--

CREATE TABLE `terra_connections` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `terra_user_id` varchar(255) NOT NULL,
  `reference_id` varchar(255) NOT NULL,
  `provider` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `topup_products`
--

CREATE TABLE `topup_products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `topup_kind` enum('coaching_sessions','skin_scans') DEFAULT NULL,
  `limit` int(11) DEFAULT NULL,
  `price` decimal(8,2) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `topup_products`
--

INSERT INTO `topup_products` (`id`, `slug`, `name`, `description`, `topup_kind`, `limit`, `price`, `status`, `created_at`, `updated_at`) VALUES
(1, 'coaching_sessions_20', '+20 Coaching Sessions', 'One-time top-up that expires at the end of the month.', 'coaching_sessions', 20, 2.99, 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(2, 'skin_scans_5', '+5 Skin Scans', 'One-time top-up that expires at the end of the month.', 'skin_scans', 5, 1.99, 1, '2026-08-24 22:15:27', '2026-08-24 22:15:27');

-- --------------------------------------------------------

--
-- Table structure for table `ttc_predictions`
--

CREATE TABLE `ttc_predictions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `cycle_id` bigint(20) UNSIGNED NOT NULL,
  `surge_active` tinyint(1) NOT NULL DEFAULT 0,
  `surge_message` varchar(255) DEFAULT NULL,
  `hours_remaining_estimate` int(11) DEFAULT NULL,
  `cycle_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `lh_surge_day` tinyint(3) UNSIGNED DEFAULT NULL,
  `priority` varchar(255) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `priority_message` text DEFAULT NULL,
  `priority_ranges` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`priority_ranges`)),
  `ai_generated` tinyint(1) NOT NULL DEFAULT 0,
  `ai_fallback` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `is_marketplace_admin` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `otp` int(11) DEFAULT NULL,
  `otp_expire_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `fcm_token` varchar(255) DEFAULT NULL,
  `apple_id` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active' COMMENT 'active,suspended',
  `suspend_reason` text DEFAULT NULL,
  `user_type` varchar(255) DEFAULT NULL COMMENT 'user,admin',
  `is_privacy_accepted` tinyint(1) NOT NULL DEFAULT 0,
  `onboardingCompleted` tinyint(1) NOT NULL DEFAULT 0,
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `is_marketplace_admin`, `email_verified_at`, `password`, `otp`, `otp_expire_at`, `remember_token`, `fcm_token`, `apple_id`, `status`, `suspend_reason`, `user_type`, `is_privacy_accepted`, `onboardingCompleted`, `stripe_customer_id`, `last_login_at`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'info@fightthenumber.com', 1, '2026-08-24 22:15:27', '$2y$12$dJ7iBcKKceaDEcIISKCviON4O33tvVbwPE5XH9oi6bsGOt1kjXyri', NULL, NULL, NULL, NULL, NULL, 'active', NULL, 'admin', 0, 0, NULL, NULL, NULL, '2026-08-24 22:15:27', '2026-08-24 22:15:27'),
(2, 'User', 'user@gmail.com', 0, '2026-08-24 22:15:27', '$2y$12$hzP0rcBBorBQSLGL8.vNoeBpJkN8vEeecZtTYBVhKdigW8.IkcnYW', NULL, NULL, NULL, NULL, NULL, 'active', NULL, 'user', 0, 0, NULL, NULL, NULL, '2026-08-24 22:15:27', '2026-08-24 22:15:27');

-- --------------------------------------------------------

--
-- Table structure for table `user_limits`
--

CREATE TABLE `user_limits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(255) DEFAULT NULL COMMENT 'subscription,topup,refund',
  `payment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `skin_scans_limit` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ai_coaching_limit` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `deep_reports_limit` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `skin_scans_topup_limit` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ai_coaching_topup_limit` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `subscription_expires_at` timestamp NULL DEFAULT NULL,
  `topup_expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vetting_records`
--

CREATE TABLE `vetting_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `provider_id` bigint(20) UNSIGNED NOT NULL,
  `check_type` enum('license','leie','disciplinary','certification','reputation') NOT NULL,
  `status` enum('pass','fail','pending','expired') NOT NULL,
  `evidence_url` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `checked_at` timestamp NULL DEFAULT NULL,
  `next_due_at` timestamp NULL DEFAULT NULL,
  `checked_by` varchar(80) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `waitlist_entries`
--

CREATE TABLE `waitlist_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `life_journey_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending_confirmation' COMMENT 'pending_confirmation',
  `confirmation_token` varchar(255) DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `invited_at` timestamp NULL DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `wave_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `awareness_snapshots`
--
ALTER TABLE `awareness_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `awareness_snapshots_user_id_cycle_id_unique` (`user_id`,`cycle_id`),
  ADD KEY `awareness_snapshots_cycle_id_foreign` (`cycle_id`),
  ADD KEY `awareness_snapshots_phase_index` (`phase`),
  ADD KEY `awareness_snapshots_current_phase_index` (`current_phase`);

--
-- Indexes for table `bbt_logs`
--
ALTER TABLE `bbt_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bbt_logs_cycle_id_log_date_unique` (`cycle_id`,`log_date`),
  ADD KEY `bbt_logs_log_date_index` (`log_date`),
  ADD KEY `bbt_logs_is_excluded_index` (`is_excluded`);

--
-- Indexes for table `blogs`
--
ALTER TABLE `blogs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `blogs_slug_unique` (`slug`),
  ADD KEY `blogs_blog_category_id_foreign` (`blog_category_id`);

--
-- Indexes for table `blog_categories`
--
ALTER TABLE `blog_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `blog_categories_slug_unique` (`slug`);

--
-- Indexes for table `blog_comments`
--
ALTER TABLE `blog_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blog_comments_blog_id_foreign` (`blog_id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `category_place_queries`
--
ALTER TABLE `category_place_queries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_place_queries_category_id_foreign` (`category_id`);

--
-- Indexes for table `category_taxonomy_codes`
--
ALTER TABLE `category_taxonomy_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_taxonomy_codes_category_id_foreign` (`category_id`),
  ADD KEY `category_taxonomy_codes_nucc_code_index` (`nucc_code`);

--
-- Indexes for table `cervical_mucus_logs`
--
ALTER TABLE `cervical_mucus_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cervical_mucus_logs_cycle_id_log_date_unique` (`cycle_id`,`log_date`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chat_messages_user_id_foreign` (`user_id`),
  ADD KEY `chat_messages_session_id_foreign` (`session_id`);

--
-- Indexes for table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chat_sessions_session_id_unique` (`session_id`),
  ADD KEY `chat_sessions_user_id_foreign` (`user_id`);

--
-- Indexes for table `community_comments`
--
ALTER TABLE `community_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `community_comments_post_id_foreign` (`post_id`),
  ADD KEY `community_comments_user_id_foreign` (`user_id`);

--
-- Indexes for table `community_likes`
--
ALTER TABLE `community_likes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `community_likes_post_id_foreign` (`post_id`),
  ADD KEY `community_likes_user_id_foreign` (`user_id`);

--
-- Indexes for table `community_posts`
--
ALTER TABLE `community_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `community_posts_slug_unique` (`slug`),
  ADD KEY `community_posts_user_id_foreign` (`user_id`);

--
-- Indexes for table `community_post_life_journey`
--
ALTER TABLE `community_post_life_journey`
  ADD PRIMARY KEY (`id`),
  ADD KEY `community_post_life_journey_community_post_id_foreign` (`community_post_id`),
  ADD KEY `community_post_life_journey_life_journey_id_foreign` (`life_journey_id`);

--
-- Indexes for table `community_post_reports`
--
ALTER TABLE `community_post_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `community_post_reports_post_id_user_id_unique` (`post_id`,`user_id`),
  ADD KEY `community_post_reports_user_id_foreign` (`user_id`);

--
-- Indexes for table `connect_devices`
--
ALTER TABLE `connect_devices`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `connect_device_profile`
--
ALTER TABLE `connect_device_profile`
  ADD PRIMARY KEY (`id`),
  ADD KEY `connect_device_profile_connect_device_id_foreign` (`connect_device_id`),
  ADD KEY `connect_device_profile_profile_id_foreign` (`profile_id`);

--
-- Indexes for table `cycle_calendar_inputs`
--
ALTER TABLE `cycle_calendar_inputs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cycle_calendar_inputs_user_id_foreign` (`user_id`);

--
-- Indexes for table `cycle_consents`
--
ALTER TABLE `cycle_consents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cycle_consents_user_id_unique` (`user_id`);

--
-- Indexes for table `cycle_daily_logs`
--
ALTER TABLE `cycle_daily_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cycle_daily_logs_cycle_id_log_date_unique` (`cycle_id`,`log_date`),
  ADD KEY `cycle_daily_logs_phase_index` (`phase`),
  ADD KEY `cycle_daily_logs_tag_index` (`tag`);

--
-- Indexes for table `cycle_modes`
--
ALTER TABLE `cycle_modes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cycle_modes_user_id_unique` (`user_id`),
  ADD KEY `cycle_modes_mode_index` (`mode`),
  ADD KEY `cycle_modes_has_consented_index` (`has_consented`);

--
-- Indexes for table `cycle_prediction_caches`
--
ALTER TABLE `cycle_prediction_caches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cycle_prediction_caches_cache_key_unique` (`cache_key`),
  ADD KEY `cycle_prediction_caches_cycle_id_foreign` (`cycle_id`),
  ADD KEY `cycle_prediction_caches_endpoint_index` (`endpoint`),
  ADD KEY `cycle_prediction_caches_expires_at_index` (`expires_at`);

--
-- Indexes for table `cycle_settings`
--
ALTER TABLE `cycle_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cycle_settings_user_id_unique` (`user_id`);

--
-- Indexes for table `cycle_statistics`
--
ALTER TABLE `cycle_statistics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cycle_statistics_user_id_unique` (`user_id`),
  ADD KEY `cycle_statistics_reliability_level_index` (`reliability_level`);

--
-- Indexes for table `daily_scriptures`
--
ALTER TABLE `daily_scriptures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `daily_scriptures_user_id_foreign` (`user_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `fertility_events`
--
ALTER TABLE `fertility_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fertility_events_cycle_id_foreign` (`cycle_id`),
  ADD KEY `fertility_events_event_type_index` (`event_type`),
  ADD KEY `fertility_events_event_date_index` (`event_date`),
  ADD KEY `fertility_events_priority_level_index` (`priority_level`);

--
-- Indexes for table `health_goals`
--
ALTER TABLE `health_goals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `health_goal_profile`
--
ALTER TABLE `health_goal_profile`
  ADD PRIMARY KEY (`id`),
  ADD KEY `health_goal_profile_health_goal_id_foreign` (`health_goal_id`),
  ADD KEY `health_goal_profile_profile_id_foreign` (`profile_id`);

--
-- Indexes for table `health_logs`
--
ALTER TABLE `health_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `health_logs_user_id_log_date_unique` (`user_id`,`log_date`);

--
-- Indexes for table `health_trends`
--
ALTER TABLE `health_trends`
  ADD PRIMARY KEY (`id`),
  ADD KEY `health_trends_user_id_foreign` (`user_id`);

--
-- Indexes for table `hormone_snapshots`
--
ALTER TABLE `hormone_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hormone_snapshots_cycle_id_snapshot_date_unique` (`cycle_id`,`snapshot_date`);

--
-- Indexes for table `intercourse_logs`
--
ALTER TABLE `intercourse_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `intercourse_logs_cycle_id_log_date_unique` (`cycle_id`,`log_date`),
  ADD KEY `intercourse_logs_log_date_index` (`log_date`),
  ADD KEY `intercourse_logs_trying_to_conceive_index` (`trying_to_conceive`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lab_reports`
--
ALTER TABLE `lab_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lab_reports_user_id_foreign` (`user_id`);

--
-- Indexes for table `life_journeys`
--
ALTER TABLE `life_journeys`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `life_journey_features`
--
ALTER TABLE `life_journey_features`
  ADD PRIMARY KEY (`id`),
  ADD KEY `life_journey_features_life_journey_id_foreign` (`life_journey_id`);

--
-- Indexes for table `life_journey_profile`
--
ALTER TABLE `life_journey_profile`
  ADD PRIMARY KEY (`id`),
  ADD KEY `life_journey_profile_life_journey_id_foreign` (`life_journey_id`),
  ADD KEY `life_journey_profile_profile_id_foreign` (`profile_id`);

--
-- Indexes for table `life_stages`
--
ALTER TABLE `life_stages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `marketplace_life_stages`
--
ALTER TABLE `marketplace_life_stages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `marketplace_life_stages_slug_unique` (`slug`);

--
-- Indexes for table `marketplace_life_stage_category`
--
ALTER TABLE `marketplace_life_stage_category`
  ADD PRIMARY KEY (`marketplace_life_stage_id`,`category_id`),
  ADD KEY `mkt_life_stage_category_category_fk` (`category_id`);

--
-- Indexes for table `menstrual_cycles`
--
ALTER TABLE `menstrual_cycles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `menstrual_cycles_user_id_period_start_date_index` (`user_id`,`period_start_date`),
  ADD KEY `menstrual_cycles_current_phase_index` (`current_phase`),
  ADD KEY `menstrual_cycles_is_completed_index` (`is_completed`),
  ADD KEY `menstrual_cycles_current_cycle_day_index` (`current_cycle_day`);

--
-- Indexes for table `metros`
--
ALTER TABLE `metros`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `new_awareness_snapshots`
--
ALTER TABLE `new_awareness_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `new_awareness_snapshots_user_id_cycle_id_unique` (`user_id`,`cycle_id`),
  ADD KEY `new_awareness_snapshots_user_id_index` (`user_id`),
  ADD KEY `new_awareness_snapshots_cycle_id_index` (`cycle_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`);

--
-- Indexes for table `notification_histories`
--
ALTER TABLE `notification_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notification_histories_user_id_status_index` (`user_id`,`status`);

--
-- Indexes for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notification_preferences_user_id_foreign` (`user_id`);

--
-- Indexes for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `numera_insights`
--
ALTER TABLE `numera_insights`
  ADD PRIMARY KEY (`id`),
  ADD KEY `numera_insights_user_id_foreign` (`user_id`);

--
-- Indexes for table `opk_data`
--
ALTER TABLE `opk_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `opk_data_user_id_foreign` (`user_id`);

--
-- Indexes for table `opk_logs`
--
ALTER TABLE `opk_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `opk_logs_cycle_id_log_date_unique` (`cycle_id`,`log_date`),
  ADD KEY `opk_logs_result_index` (`result`);

--
-- Indexes for table `ovulation_reconciliations`
--
ALTER TABLE `ovulation_reconciliations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ovulation_reconciliations_cycle_id_unique` (`cycle_id`),
  ADD KEY `ovulation_reconciliations_user_id_index` (`user_id`),
  ADD KEY `ovulation_reconciliations_final_source_index` (`final_source`),
  ADD KEY `ovulation_reconciliations_has_discrepancy_index` (`has_discrepancy`),
  ADD KEY `ovulation_reconciliations_is_reconciled_index` (`is_reconciled`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pages_slug_unique` (`slug`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payments_stripe_subscription_id_unique` (`stripe_subscription_id`),
  ADD KEY `payments_user_id_foreign` (`user_id`),
  ADD KEY `payments_subscription_plan_id_foreign` (`subscription_plan_id`),
  ADD KEY `payments_topup_product_id_foreign` (`topup_product_id`),
  ADD KEY `payments_stripe_customer_id_index` (`stripe_customer_id`),
  ADD KEY `payments_stripe_payment_intent_id_index` (`stripe_payment_intent_id`);

--
-- Indexes for table `period_logs`
--
ALTER TABLE `period_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `period_logs_user_id_cycle_id_log_date_unique` (`user_id`,`cycle_id`,`log_date`),
  ADD KEY `period_logs_cycle_id_foreign` (`cycle_id`),
  ADD KEY `period_logs_flow_index` (`flow`),
  ADD KEY `period_logs_log_date_index` (`log_date`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `phase_insights`
--
ALTER TABLE `phase_insights`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phase_insights_cycle_id_insight_date_unique` (`cycle_id`,`insight_date`);

--
-- Indexes for table `place_details_cache`
--
ALTER TABLE `place_details_cache`
  ADD PRIMARY KEY (`provider_id`),
  ADD KEY `place_details_cache_expires_at_index` (`expires_at`);

--
-- Indexes for table `pregnancy_test_logs`
--
ALTER TABLE `pregnancy_test_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pregnancy_test_logs_cycle_id_foreign` (`cycle_id`),
  ADD KEY `pregnancy_test_logs_result_index` (`result`),
  ADD KEY `pregnancy_test_logs_test_date_index` (`test_date`);

--
-- Indexes for table `profiles`
--
ALTER TABLE `profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `profiles_user_id_foreign` (`user_id`),
  ADD KEY `profiles_life_stage_id_foreign` (`life_stage_id`),
  ADD KEY `profiles_activity_id_foreign` (`activity_id`);

--
-- Indexes for table `providers`
--
ALTER TABLE `providers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `providers_npi_unique` (`npi`),
  ADD UNIQUE KEY `providers_google_place_id_unique` (`google_place_id`),
  ADD KEY `providers_metro_id_foreign` (`metro_id`),
  ADD SPATIAL KEY `providers_location_spatialindex` (`location`),
  ADD KEY `providers_status_index` (`status`);

--
-- Indexes for table `provider_categories`
--
ALTER TABLE `provider_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `provider_categories_slug_unique` (`slug`);

--
-- Indexes for table `provider_category`
--
ALTER TABLE `provider_category`
  ADD PRIMARY KEY (`provider_id`,`category_id`),
  ADD KEY `provider_category_category_id_foreign` (`category_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `signal_histories`
--
ALTER TABLE `signal_histories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `signal_histories_user_id_cycle_id_log_date_unique` (`user_id`,`cycle_id`,`log_date`),
  ADD KEY `signal_histories_user_id_index` (`user_id`),
  ADD KEY `signal_histories_cycle_id_index` (`cycle_id`),
  ADD KEY `signal_histories_log_date_index` (`log_date`),
  ADD KEY `signal_histories_signal_strength_index` (`signal_strength`);

--
-- Indexes for table `skin_scans`
--
ALTER TABLE `skin_scans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `skin_scans_user_id_foreign` (`user_id`);

--
-- Indexes for table `skin_scan_recommendations`
--
ALTER TABLE `skin_scan_recommendations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `skin_scan_recommendations_skin_scan_id_foreign` (`skin_scan_id`);

--
-- Indexes for table `slate_events`
--
ALTER TABLE `slate_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `slate_events_category_id_foreign` (`category_id`),
  ADD KEY `slate_events_mkt_life_stage_fk` (`marketplace_life_stage_id`),
  ADD KEY `slate_events_provider_id_foreign` (`provider_id`),
  ADD KEY `slate_events_metro_id_category_id_occurred_at_index` (`metro_id`,`category_id`,`occurred_at`),
  ADD KEY `slate_events_session_token_index` (`session_token`);

--
-- Indexes for table `smart_analyses`
--
ALTER TABLE `smart_analyses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `smart_analyses_user_id_foreign` (`user_id`);

--
-- Indexes for table `sponsored_slots`
--
ALTER TABLE `sponsored_slots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sponsored_slots_category_id_foreign` (`category_id`),
  ADD KEY `sponsored_slots_provider_id_foreign` (`provider_id`),
  ADD KEY `sponsored_slots_metro_id_category_id_status_index` (`metro_id`,`category_id`,`status`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subscription_plans_slug_unique` (`slug`);

--
-- Indexes for table `symptom_logs`
--
ALTER TABLE `symptom_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `symptom_logs_cycle_id_log_date_unique` (`cycle_id`,`log_date`),
  ADD KEY `symptom_logs_mood_index` (`mood`),
  ADD KEY `symptom_logs_energy_index` (`energy`);

--
-- Indexes for table `terra_activity_data`
--
ALTER TABLE `terra_activity_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `terra_activity_data_user_id_foreign` (`user_id`);

--
-- Indexes for table `terra_connections`
--
ALTER TABLE `terra_connections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `terra_connections_terra_user_id_unique` (`terra_user_id`),
  ADD KEY `terra_connections_user_id_foreign` (`user_id`);

--
-- Indexes for table `topup_products`
--
ALTER TABLE `topup_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `topup_products_slug_unique` (`slug`);

--
-- Indexes for table `ttc_predictions`
--
ALTER TABLE `ttc_predictions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ttc_predictions_user_id_foreign` (`user_id`),
  ADD KEY `ttc_predictions_cycle_id_foreign` (`cycle_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Indexes for table `user_limits`
--
ALTER TABLE `user_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_limits_user_id_foreign` (`user_id`),
  ADD KEY `user_limits_payment_id_foreign` (`payment_id`);

--
-- Indexes for table `vetting_records`
--
ALTER TABLE `vetting_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vetting_records_provider_id_check_type_index` (`provider_id`,`check_type`),
  ADD KEY `vetting_records_next_due_at_index` (`next_due_at`);

--
-- Indexes for table `waitlist_entries`
--
ALTER TABLE `waitlist_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `waitlist_entries_life_journey_id_foreign` (`life_journey_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activities`
--
ALTER TABLE `activities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `awareness_snapshots`
--
ALTER TABLE `awareness_snapshots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bbt_logs`
--
ALTER TABLE `bbt_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blogs`
--
ALTER TABLE `blogs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_categories`
--
ALTER TABLE `blog_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_comments`
--
ALTER TABLE `blog_comments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `category_place_queries`
--
ALTER TABLE `category_place_queries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `category_taxonomy_codes`
--
ALTER TABLE `category_taxonomy_codes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `cervical_mucus_logs`
--
ALTER TABLE `cervical_mucus_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `community_comments`
--
ALTER TABLE `community_comments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `community_likes`
--
ALTER TABLE `community_likes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `community_posts`
--
ALTER TABLE `community_posts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `community_post_life_journey`
--
ALTER TABLE `community_post_life_journey`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `community_post_reports`
--
ALTER TABLE `community_post_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `connect_devices`
--
ALTER TABLE `connect_devices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `connect_device_profile`
--
ALTER TABLE `connect_device_profile`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cycle_calendar_inputs`
--
ALTER TABLE `cycle_calendar_inputs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cycle_consents`
--
ALTER TABLE `cycle_consents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cycle_daily_logs`
--
ALTER TABLE `cycle_daily_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cycle_modes`
--
ALTER TABLE `cycle_modes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cycle_prediction_caches`
--
ALTER TABLE `cycle_prediction_caches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cycle_settings`
--
ALTER TABLE `cycle_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cycle_statistics`
--
ALTER TABLE `cycle_statistics`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_scriptures`
--
ALTER TABLE `daily_scriptures`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fertility_events`
--
ALTER TABLE `fertility_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `health_goals`
--
ALTER TABLE `health_goals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `health_goal_profile`
--
ALTER TABLE `health_goal_profile`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `health_logs`
--
ALTER TABLE `health_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `health_trends`
--
ALTER TABLE `health_trends`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hormone_snapshots`
--
ALTER TABLE `hormone_snapshots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `intercourse_logs`
--
ALTER TABLE `intercourse_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_reports`
--
ALTER TABLE `lab_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `life_journeys`
--
ALTER TABLE `life_journeys`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `life_journey_features`
--
ALTER TABLE `life_journey_features`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `life_journey_profile`
--
ALTER TABLE `life_journey_profile`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `life_stages`
--
ALTER TABLE `life_stages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `marketplace_life_stages`
--
ALTER TABLE `marketplace_life_stages`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `menstrual_cycles`
--
ALTER TABLE `menstrual_cycles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `metros`
--
ALTER TABLE `metros`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `new_awareness_snapshots`
--
ALTER TABLE `new_awareness_snapshots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_histories`
--
ALTER TABLE `notification_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_settings`
--
ALTER TABLE `notification_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `numera_insights`
--
ALTER TABLE `numera_insights`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `opk_data`
--
ALTER TABLE `opk_data`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `opk_logs`
--
ALTER TABLE `opk_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ovulation_reconciliations`
--
ALTER TABLE `ovulation_reconciliations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `period_logs`
--
ALTER TABLE `period_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `phase_insights`
--
ALTER TABLE `phase_insights`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pregnancy_test_logs`
--
ALTER TABLE `pregnancy_test_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `profiles`
--
ALTER TABLE `profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `providers`
--
ALTER TABLE `providers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `provider_categories`
--
ALTER TABLE `provider_categories`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `signal_histories`
--
ALTER TABLE `signal_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `skin_scans`
--
ALTER TABLE `skin_scans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `skin_scan_recommendations`
--
ALTER TABLE `skin_scan_recommendations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `slate_events`
--
ALTER TABLE `slate_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `smart_analyses`
--
ALTER TABLE `smart_analyses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sponsored_slots`
--
ALTER TABLE `sponsored_slots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `symptom_logs`
--
ALTER TABLE `symptom_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `terra_activity_data`
--
ALTER TABLE `terra_activity_data`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `terra_connections`
--
ALTER TABLE `terra_connections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `topup_products`
--
ALTER TABLE `topup_products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ttc_predictions`
--
ALTER TABLE `ttc_predictions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_limits`
--
ALTER TABLE `user_limits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vetting_records`
--
ALTER TABLE `vetting_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `waitlist_entries`
--
ALTER TABLE `waitlist_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `awareness_snapshots`
--
ALTER TABLE `awareness_snapshots`
  ADD CONSTRAINT `awareness_snapshots_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `awareness_snapshots_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bbt_logs`
--
ALTER TABLE `bbt_logs`
  ADD CONSTRAINT `bbt_logs_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blogs`
--
ALTER TABLE `blogs`
  ADD CONSTRAINT `blogs_blog_category_id_foreign` FOREIGN KEY (`blog_category_id`) REFERENCES `blog_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blog_comments`
--
ALTER TABLE `blog_comments`
  ADD CONSTRAINT `blog_comments_blog_id_foreign` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `category_place_queries`
--
ALTER TABLE `category_place_queries`
  ADD CONSTRAINT `category_place_queries_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `provider_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `category_taxonomy_codes`
--
ALTER TABLE `category_taxonomy_codes`
  ADD CONSTRAINT `category_taxonomy_codes_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `provider_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cervical_mucus_logs`
--
ALTER TABLE `cervical_mucus_logs`
  ADD CONSTRAINT `cervical_mucus_logs_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `chat_sessions` (`session_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  ADD CONSTRAINT `chat_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `community_comments`
--
ALTER TABLE `community_comments`
  ADD CONSTRAINT `community_comments_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `community_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `community_likes`
--
ALTER TABLE `community_likes`
  ADD CONSTRAINT `community_likes_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `community_likes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `community_posts`
--
ALTER TABLE `community_posts`
  ADD CONSTRAINT `community_posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `community_post_life_journey`
--
ALTER TABLE `community_post_life_journey`
  ADD CONSTRAINT `community_post_life_journey_community_post_id_foreign` FOREIGN KEY (`community_post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `community_post_life_journey_life_journey_id_foreign` FOREIGN KEY (`life_journey_id`) REFERENCES `life_journeys` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `community_post_reports`
--
ALTER TABLE `community_post_reports`
  ADD CONSTRAINT `community_post_reports_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `community_post_reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `connect_device_profile`
--
ALTER TABLE `connect_device_profile`
  ADD CONSTRAINT `connect_device_profile_connect_device_id_foreign` FOREIGN KEY (`connect_device_id`) REFERENCES `connect_devices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `connect_device_profile_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cycle_calendar_inputs`
--
ALTER TABLE `cycle_calendar_inputs`
  ADD CONSTRAINT `cycle_calendar_inputs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cycle_consents`
--
ALTER TABLE `cycle_consents`
  ADD CONSTRAINT `cycle_consents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cycle_daily_logs`
--
ALTER TABLE `cycle_daily_logs`
  ADD CONSTRAINT `cycle_daily_logs_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cycle_modes`
--
ALTER TABLE `cycle_modes`
  ADD CONSTRAINT `cycle_modes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cycle_prediction_caches`
--
ALTER TABLE `cycle_prediction_caches`
  ADD CONSTRAINT `cycle_prediction_caches_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cycle_settings`
--
ALTER TABLE `cycle_settings`
  ADD CONSTRAINT `cycle_settings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cycle_statistics`
--
ALTER TABLE `cycle_statistics`
  ADD CONSTRAINT `cycle_statistics_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `daily_scriptures`
--
ALTER TABLE `daily_scriptures`
  ADD CONSTRAINT `daily_scriptures_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fertility_events`
--
ALTER TABLE `fertility_events`
  ADD CONSTRAINT `fertility_events_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `health_goal_profile`
--
ALTER TABLE `health_goal_profile`
  ADD CONSTRAINT `health_goal_profile_health_goal_id_foreign` FOREIGN KEY (`health_goal_id`) REFERENCES `health_goals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `health_goal_profile_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `health_logs`
--
ALTER TABLE `health_logs`
  ADD CONSTRAINT `health_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `health_trends`
--
ALTER TABLE `health_trends`
  ADD CONSTRAINT `health_trends_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hormone_snapshots`
--
ALTER TABLE `hormone_snapshots`
  ADD CONSTRAINT `hormone_snapshots_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `intercourse_logs`
--
ALTER TABLE `intercourse_logs`
  ADD CONSTRAINT `intercourse_logs_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lab_reports`
--
ALTER TABLE `lab_reports`
  ADD CONSTRAINT `lab_reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `life_journey_features`
--
ALTER TABLE `life_journey_features`
  ADD CONSTRAINT `life_journey_features_life_journey_id_foreign` FOREIGN KEY (`life_journey_id`) REFERENCES `life_journeys` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `life_journey_profile`
--
ALTER TABLE `life_journey_profile`
  ADD CONSTRAINT `life_journey_profile_life_journey_id_foreign` FOREIGN KEY (`life_journey_id`) REFERENCES `life_journeys` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `life_journey_profile_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `marketplace_life_stage_category`
--
ALTER TABLE `marketplace_life_stage_category`
  ADD CONSTRAINT `mkt_life_stage_category_category_fk` FOREIGN KEY (`category_id`) REFERENCES `provider_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mkt_life_stage_category_stage_fk` FOREIGN KEY (`marketplace_life_stage_id`) REFERENCES `marketplace_life_stages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `menstrual_cycles`
--
ALTER TABLE `menstrual_cycles`
  ADD CONSTRAINT `menstrual_cycles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `new_awareness_snapshots`
--
ALTER TABLE `new_awareness_snapshots`
  ADD CONSTRAINT `new_awareness_snapshots_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `new_awareness_snapshots_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_histories`
--
ALTER TABLE `notification_histories`
  ADD CONSTRAINT `notification_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD CONSTRAINT `notification_preferences_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `numera_insights`
--
ALTER TABLE `numera_insights`
  ADD CONSTRAINT `numera_insights_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `opk_data`
--
ALTER TABLE `opk_data`
  ADD CONSTRAINT `opk_data_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `opk_logs`
--
ALTER TABLE `opk_logs`
  ADD CONSTRAINT `opk_logs_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ovulation_reconciliations`
--
ALTER TABLE `ovulation_reconciliations`
  ADD CONSTRAINT `ovulation_reconciliations_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ovulation_reconciliations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_subscription_plan_id_foreign` FOREIGN KEY (`subscription_plan_id`) REFERENCES `subscription_plans` (`id`),
  ADD CONSTRAINT `payments_topup_product_id_foreign` FOREIGN KEY (`topup_product_id`) REFERENCES `topup_products` (`id`),
  ADD CONSTRAINT `payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `period_logs`
--
ALTER TABLE `period_logs`
  ADD CONSTRAINT `period_logs_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `period_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `phase_insights`
--
ALTER TABLE `phase_insights`
  ADD CONSTRAINT `phase_insights_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `place_details_cache`
--
ALTER TABLE `place_details_cache`
  ADD CONSTRAINT `place_details_cache_provider_id_foreign` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pregnancy_test_logs`
--
ALTER TABLE `pregnancy_test_logs`
  ADD CONSTRAINT `pregnancy_test_logs_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `profiles`
--
ALTER TABLE `profiles`
  ADD CONSTRAINT `profiles_activity_id_foreign` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `profiles_life_stage_id_foreign` FOREIGN KEY (`life_stage_id`) REFERENCES `life_stages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `providers`
--
ALTER TABLE `providers`
  ADD CONSTRAINT `providers_metro_id_foreign` FOREIGN KEY (`metro_id`) REFERENCES `metros` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `provider_category`
--
ALTER TABLE `provider_category`
  ADD CONSTRAINT `provider_category_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `provider_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provider_category_provider_id_foreign` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `signal_histories`
--
ALTER TABLE `signal_histories`
  ADD CONSTRAINT `signal_histories_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `signal_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `skin_scans`
--
ALTER TABLE `skin_scans`
  ADD CONSTRAINT `skin_scans_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `skin_scan_recommendations`
--
ALTER TABLE `skin_scan_recommendations`
  ADD CONSTRAINT `skin_scan_recommendations_skin_scan_id_foreign` FOREIGN KEY (`skin_scan_id`) REFERENCES `skin_scans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `slate_events`
--
ALTER TABLE `slate_events`
  ADD CONSTRAINT `slate_events_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `provider_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `slate_events_metro_id_foreign` FOREIGN KEY (`metro_id`) REFERENCES `metros` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `slate_events_mkt_life_stage_fk` FOREIGN KEY (`marketplace_life_stage_id`) REFERENCES `marketplace_life_stages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `slate_events_provider_id_foreign` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `smart_analyses`
--
ALTER TABLE `smart_analyses`
  ADD CONSTRAINT `smart_analyses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sponsored_slots`
--
ALTER TABLE `sponsored_slots`
  ADD CONSTRAINT `sponsored_slots_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `provider_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sponsored_slots_metro_id_foreign` FOREIGN KEY (`metro_id`) REFERENCES `metros` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sponsored_slots_provider_id_foreign` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `symptom_logs`
--
ALTER TABLE `symptom_logs`
  ADD CONSTRAINT `symptom_logs_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `terra_activity_data`
--
ALTER TABLE `terra_activity_data`
  ADD CONSTRAINT `terra_activity_data_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `terra_connections`
--
ALTER TABLE `terra_connections`
  ADD CONSTRAINT `terra_connections_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ttc_predictions`
--
ALTER TABLE `ttc_predictions`
  ADD CONSTRAINT `ttc_predictions_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `menstrual_cycles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ttc_predictions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_limits`
--
ALTER TABLE `user_limits`
  ADD CONSTRAINT `user_limits_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `user_limits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vetting_records`
--
ALTER TABLE `vetting_records`
  ADD CONSTRAINT `vetting_records_provider_id_foreign` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `waitlist_entries`
--
ALTER TABLE `waitlist_entries`
  ADD CONSTRAINT `waitlist_entries_life_journey_id_foreign` FOREIGN KEY (`life_journey_id`) REFERENCES `life_journeys` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
