DROP TABLE IF EXISTS `activities`;
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

DROP TABLE IF EXISTS `attachments`;
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

DROP TABLE IF EXISTS `followers`;
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

DROP TABLE IF EXISTS `following`;
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

DROP TABLE IF EXISTS `interactions`;
CREATE TABLE `interactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `object_id` int(10) unsigned NOT NULL DEFAULT 0,
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `type` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '1likes 2shares 3replies',
  `published` timestamp NOT NULL DEFAULT '1970-01-01 16:00:01',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=119 DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `notifications`;
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

DROP TABLE IF EXISTS `objects`;
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

DROP TABLE IF EXISTS `profiles`;
CREATE TABLE `profiles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `actor` varchar(192) NOT NULL DEFAULT '',
  `type` varchar(32) NOT NULL DEFAULT 'Person',
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

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL DEFAULT '',
  `content` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sessions_name_IDX` (`name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2270 DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(64) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `public_key` text DEFAULT NULL,
  `private_key` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `shares`;
CREATE TABLE `shares` (
  `id` int(10) unsigned NOT NULL DEFAULT 0,
  `activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `object_id` int(10) unsigned NOT NULL DEFAULT 0,
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `published` timestamp NOT NULL DEFAULT '1970-01-01 16:00:01',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `term` varchar(128) NOT NULL DEFAULT '',
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `object_id` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3985 DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `task_logs`;
CREATE TABLE `task_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task` varchar(64) NOT NULL DEFAULT '',
  `params` varchar(255) DEFAULT NULL,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `reason` varchar(192) NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=808 DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `tasks`;
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
