DROP TABLE IF EXISTS `activities`;
CREATE TABLE `activities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` varchar(192) NOT NULL DEFAULT '',
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `object_id` int(10) unsigned NOT NULL DEFAULT 0,
  `type` varchar(64) NOT NULL DEFAULT '',
  `raw` text DEFAULT NULL,
  `signature_data` text DEFAULT NULL,
  `published` timestamp NOT NULL DEFAULT current_timestamp(),
  `unlisted` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_local` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_public` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_deleted` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `activities_activity_id_IDX` (`activity_id`) USING BTREE,
  KEY `activities_object_id_IDX` (`object_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `followers`;
CREATE TABLE `followers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0:received1:accepted2:rejected',
  `follow_activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `accept_activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `followers_profile_id_IDX` (`profile_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `following`;
CREATE TABLE `following` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0:received1:accepted2:rejected',
  `follow_activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `accept_activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `following_profile_id_IDX` (`profile_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `interactions`;
CREATE TABLE `interactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `object_id` int(10) unsigned NOT NULL DEFAULT 0,
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `type` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '1likes 2shares 3replies',
  `published` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  `published` timestamp NOT NULL DEFAULT current_timestamp(),
  `unlisted` tinyint(3) NOT NULL DEFAULT 0,
  `is_local` tinyint(4) NOT NULL DEFAULT 0,
  `is_public` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_sensitive` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_liked` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_boosted` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `objects_raw_object_id_IDX` (`raw_object_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `replies`;
CREATE TABLE `replies` (
  `id` int(10) unsigned NOT NULL DEFAULT 0,
  `activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `object_id` int(10) unsigned NOT NULL DEFAULT 0,
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `published` timestamp NOT NULL DEFAULT current_timestamp(),
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cat` varchar(191) NOT NULL DEFAULT '',
  `k` varchar(191) NOT NULL DEFAULT '',
  `v` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cat_k` (`cat`, `k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `shares`;
CREATE TABLE `shares` (
  `id` int(10) unsigned NOT NULL DEFAULT 0,
  `activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `object_id` int(10) unsigned NOT NULL DEFAULT 0,
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `published` timestamp NOT NULL DEFAULT current_timestamp(),
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task` varchar(64) NOT NULL DEFAULT '',
  `params` text NOT NULL,
  `retried` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_retries` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `priority` tinyint(3) unsigned NOT NULL DEFAULT 99,
  `delay` int(10) unsigned NOT NULL DEFAULT 60,
  `timer` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_loop` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `status` int(11) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tasks_timer_IDX` (`timer`,`priority`,`retried`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `task_logs`;
CREATE TABLE `task_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task` varchar(64) NOT NULL DEFAULT '',
  `params` text NOT NULL,
  `retried` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_retries` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `priority` tinyint(3) unsigned NOT NULL DEFAULT 99,
  `delay` int(10) unsigned NOT NULL DEFAULT 60,
  `timer` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_loop` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `status` int(11) unsigned NOT NULL DEFAULT 0,
  `reason` TEXT NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `polls`;
CREATE TABLE `polls` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `object_id` int(10) unsigned NOT NULL DEFAULT 0,
  `choices` text DEFAULT NULL,
  `voters_count` int(10) unsigned NOT NULL DEFAULT 0,
  `end_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `multiple` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_voted` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_closed` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp,
  PRIMARY KEY (`id`),
  KEY `polls_activity_id_IDX` (`activity_id`) USING BTREE,
  KEY `polls_is_closed_IDX` (`is_closed`) USING BTREE,
  KEY `polls_is_updated_at_IDX` (`updated_at`) USING BTREE,
  KEY `polls_object_id_IDX` (`object_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `poll_choices`;
CREATE TABLE `poll_choices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `poll_id` int(10) unsigned NOT NULL DEFAULT 0,
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `choice` varchar(100) NOT NULL DEFAULT '',
  `activity_id` int(10) unsigned NOT NULL DEFAULT 0,
  `object_id` int(10) unsigned NOT NULL DEFAULT 0,
  `vote_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `poll_choices_poll_id_IDX` (`poll_id`) USING BTREE,
  KEY `poll_choices_profile_id_IDX` (`profile_id`) USING BTREE,
  KEY `poll_choices_object_id_IDX` (`object_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `actor_aliases`;
CREATE TABLE `actor_aliases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int(10) unsigned NOT NULL DEFAULT 0,
  `alias` varchar(255) NOT NULL DEFAULT '',
  `real_host` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `actor_aliases_profile_id_IDX` (`profile_id`) USING BTREE,
  KEY `actor_aliases_alias_IDX` (`alias`, `real_host`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;