-- MySQL dump 10.13  Distrib 5.6.42, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: cherry
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.13-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activities`
--

DROP TABLE IF EXISTS `activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` varchar(192) NOT NULL DEFAULT '',
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `object_id` int(10) unsigned NOT NULL DEFAULT 0,
  `type` varchar(64) NOT NULL DEFAULT '',
  `raw` text DEFAULT NULL,
  `signature_data` text DEFAULT NULL,
  `published` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_local` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_public` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_deleted` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `activities_activity_id_IDX` (`activity_id`) USING BTREE,
  KEY `activities_published_IDX` (`published`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=22138 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attachments`
--

DROP TABLE IF EXISTS `attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `object_id` int(10) unsigned NOT NULL DEFAULT 0,
  `type` varchar(32) NOT NULL DEFAULT '',
  `media_type` varchar(32) NOT NULL DEFAULT '',
  `url` varchar(192) NOT NULL DEFAULT '',
  `name` varchar(32) NOT NULL DEFAULT '',
  `hash` varchar(128) NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1033 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `followers`
--

DROP TABLE IF EXISTS `followers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `followers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0:received1:accepted2:rejected',
  `follow_activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `accept_activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `followers_profile_id_IDX` (`profile_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `following`
--

DROP TABLE IF EXISTS `following`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `following` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0:received1:accepted2:rejected',
  `follow_activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `accept_activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `following_profile_id_IDX` (`profile_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `interactions`
--

DROP TABLE IF EXISTS `interactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `interactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `object_id` int(10) unsigned NOT NULL DEFAULT 0,
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `type` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '1likes 2shares 3replies',
  `published` timestamp NOT NULL DEFAULT '1970-01-01 16:00:01',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=119 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `actor` varchar(192) NOT NULL DEFAULT '',
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `follower_id` int(10) unsigned NOT NULL DEFAULT 0,
  `type` varchar(24) NOT NULL DEFAULT '',
  `viewed` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `objects`
--

DROP TABLE IF EXISTS `objects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `objects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '索引编号',
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `raw_object_id` varchar(192) NOT NULL DEFAULT '',
  `origin_id` int(10) unsigned NOT NULL DEFAULT 0,
  `parent_id` int(10) unsigned NOT NULL DEFAULT 0,
  `type` varchar(64) NOT NULL DEFAULT '' COMMENT '类型',
  `content` text DEFAULT NULL,
  `summary` varchar(100) NOT NULL DEFAULT '',
  `url` varchar(192) NOT NULL DEFAULT '',
  `likes` int(10) unsigned NOT NULL DEFAULT 0,
  `replies` int(10) unsigned NOT NULL DEFAULT 0,
  `shares` int(10) unsigned NOT NULL DEFAULT 0,
  `published` timestamp NOT NULL DEFAULT '1970-01-01 16:00:01',
  `is_local` tinyint(4) NOT NULL DEFAULT 0,
  `is_public` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_sensitive` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_liked` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_boosted` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `objects_raw_object_id_IDX` (`raw_object_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6616 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `profiles`
--

DROP TABLE IF EXISTS `profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `profiles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `actor` varchar(192) NOT NULL DEFAULT '',
  `name` varchar(32) NOT NULL DEFAULT '',
  `preferred_name` varchar(32) NOT NULL DEFAULT '',
  `account` varchar(64) NOT NULL DEFAULT '',
  `url` varchar(192) NOT NULL DEFAULT '',
  `avatar` varchar(192) NOT NULL DEFAULT '',
  `summary` text NOT NULL DEFAULT '',
  `inbox` varchar(192) NOT NULL DEFAULT '',
  `outbox` varchar(192) NOT NULL DEFAULT '',
  `following` varchar(192) NOT NULL DEFAULT '',
  `followers` varchar(192) NOT NULL DEFAULT '',
  `likes` varchar(192) NOT NULL DEFAULT '',
  `featured` varchar(192) NOT NULL DEFAULT '',
  `shared_inbox` varchar(192) NOT NULL DEFAULT '',
  `public_key` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `profiles_actor_IDX` (`actor`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=460 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `replies`
--

DROP TABLE IF EXISTS `replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `replies` (
  `id` int(10) unsigned NOT NULL DEFAULT 0,
  `activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `object_id` int(10) unsigned NOT NULL DEFAULT 0,
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `published` timestamp NOT NULL DEFAULT '1970-01-01 16:00:01',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL DEFAULT '',
  `content` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sessions_name_IDX` (`name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2270 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(64) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `public_key` text DEFAULT NULL,
  `private_key` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shares`
--

DROP TABLE IF EXISTS `shares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shares` (
  `id` int(10) unsigned NOT NULL DEFAULT 0,
  `activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `object_id` int(10) unsigned NOT NULL DEFAULT 0,
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `published` timestamp NOT NULL DEFAULT '1970-01-01 16:00:01',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `term` varchar(128) NOT NULL DEFAULT '',
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `object_id` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3985 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `task_logs`
--

DROP TABLE IF EXISTS `task_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task` varchar(64) NOT NULL DEFAULT '',
  `params` varchar(255) DEFAULT NULL,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `reason` varchar(192) NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=808 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task` varchar(64) NOT NULL DEFAULT '',
  `params` varchar(255) DEFAULT NULL,
  `retried` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_retries` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `priority` tinyint(3) unsigned NOT NULL DEFAULT 99,
  `delay` int(10) unsigned NOT NULL DEFAULT 60,
  `timer` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_loop` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tasks_timer_IDX` (`timer`,`priority`,`retried`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=22630 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2021-06-21 19:28:55
