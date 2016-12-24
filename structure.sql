CREATE TABLE IF NOT EXISTS `user` (
  `id` bigint COMMENT 'Unique user identifier',
  `first_name` CHAR(255) NOT NULL DEFAULT '' COMMENT 'User\'s first name',
  `last_name` CHAR(255) DEFAULT NULL COMMENT 'User\'s last name',
  `username` CHAR(255) DEFAULT NULL COMMENT 'User\'s username',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date update',

  PRIMARY KEY (`id`),
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `game` (
  `id` bigint UNSIGNED AUTO_INCREMENT COMMENT 'Unique identifier for this entry',
  `host_id` bigint NULL DEFAULT NULL COMMENT 'Unique user identifier',
  `guest_id` bigint NULL DEFAULT NULL COMMENT 'Unique user identifier',
  `game` CHAR(50) NULL DEFAULT NULL COMMENT 'Game script identifier',
  `data` TEXT DEFAULT 'NULL' COMMENT 'Game data',
  `inline_message_id` CHAR(255) NULL DEFAULT NULL COMMENT 'Identifier of the message',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date update',

  PRIMARY KEY (`id`),

  FOREIGN KEY (`host_id`)  REFERENCES `user` (`id`),
  FOREIGN KEY (`guest_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `query` (
  `id` bigint UNSIGNED COMMENT 'Unique identifier for this query',
  `user_id` bigint NULL COMMENT 'Unique user identifier',
  `inline_message_id` CHAR(255) NULL DEFAULT NULL COMMENT 'Identifier of the message sent via the bot in inline mode, that originated the query',
  `data` CHAR(100) NOT NULL DEFAULT '' COMMENT 'Data associated with the callback button',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',

  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),

  FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `request` (
  `id` bigint UNSIGNED AUTO_INCREMENT COMMENT 'Unique identifier for this entry',
  `method` CHAR(100) NULL DEFAULT NULL COMMENT 'Request method',
  `data` TEXT NULL DEFAULT NULL COMMENT 'Request data',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',

  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `state` (
  `id` bigint(20) unsigned AUTO_INCREMENT COMMENT 'Row unique id',
  `user_id` bigint NULL DEFAULT NULL COMMENT 'User id',
  `status` ENUM('active', 'cancelled', 'stopped') NOT NULL DEFAULT 'active' COMMENT 'State status',
  `command` varchar(150) DEFAULT '' COMMENT 'Default Command to execute',
  `notes` varchar(1000) DEFAULT 'NULL' COMMENT 'Data stored from command',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date update',

  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),

  FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `waiting_list` (
  `user_id` bigint COMMENT 'Unique user identifier',
  `games` varchar(255) NOT NULL COMMENT 'Games user is willing to play',
  `delete_at` timestamp NULL DEFAULT NULL COMMENT 'When to delete this entry',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',

  PRIMARY KEY (`user_id`),

  FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS `contact` (
  `id` bigint UNSIGNED AUTO_INCREMENT COMMENT 'Entry identifier',
  `user_id` bigint COMMENT 'Unique user identifier',
  `mention` text NULL COMMENT 'User username or full name',
  `text` text NOT NULL COMMENT 'Text or Caption',
  `object` text DEFAULT NULL COMMENT 'Object type (if any)',
  `file_id` text DEFAULT NULL COMMENT 'Object file_id',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',

  PRIMARY KEY (`id`),

  FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;