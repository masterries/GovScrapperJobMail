CREATE TABLE `filter_keywords` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filter_id` int(11) NOT NULL,
  `keyword` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `filter_id` (`filter_id`),
  CONSTRAINT `filter_keywords_ibfk_1` FOREIGN KEY (`filter_id`) REFERENCES `filter_sets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `filter_sets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `mode` varchar(10) NOT NULL DEFAULT 'soft',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `filter_sets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `job_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `target_type` varchar(50) NOT NULL COMMENT 'z.B. "job", "job_group", …',
  `target_key` varchar(255) NOT NULL COMMENT 'ID oder Key des Ziel‑Objekts',
  `note` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_job_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `added_at` datetime DEFAULT NULL,
  `application_deadline` varchar(255) DEFAULT NULL,
  `application_submission` varchar(255) DEFAULT NULL,
  `conditions_d_admission` varchar(255) DEFAULT NULL,
  `contract_type` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `education_level` varchar(255) DEFAULT NULL,
  `extracted_id` int(11) DEFAULT NULL,
  `full_description` text DEFAULT NULL,
  `general_information` text DEFAULT NULL,
  `group_classification` varchar(255) DEFAULT NULL,
  `how_to_apply` text DEFAULT NULL,
  `imported_at` datetime DEFAULT NULL,
  `job_category` varchar(255) DEFAULT NULL,
  `job_details` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `ministry` varchar(255) DEFAULT NULL,
  `missions` text DEFAULT NULL,
  `nationality` varchar(255) DEFAULT NULL,
  `organization` varchar(255) DEFAULT NULL,
  `profile` text DEFAULT NULL,
  `recruiter` text DEFAULT NULL,
  `required_documents` text DEFAULT NULL,
  `salary_group` varchar(255) DEFAULT NULL,
  `source_file` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `task` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `vacancy_count` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `link` (`link`),
  UNIQUE KEY `idx_link` (`link`)
) ENGINE=InnoDB AUTO_INCREMENT=4485 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pin_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pin_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pin_notes_pin` (`pin_id`),
  CONSTRAINT `fk_pin_notes_pin` FOREIGN KEY (`pin_id`) REFERENCES `user_pins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pinned_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_job` (`user_id`,`job_id`),
  KEY `job_id` (`job_id`),
  CONSTRAINT `pinned_jobs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pinned_jobs_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE ALGORITHM=UNDEFINED DEFINER=`testjobs`@`localhost` SQL SECURITY DEFINER VIEW `unique_jobs` AS with duplicate_groups as (select `j`.`id` AS `id`,`j`.`added_at` AS `added_at`,`j`.`application_deadline` AS `application_deadline`,`j`.`application_submission` AS `application_submission`,`j`.`conditions_d_admission` AS `conditions_d_admission`,`j`.`contract_type` AS `contract_type`,`j`.`created_at` AS `created_at`,`j`.`education_level` AS `education_level`,`j`.`extracted_id` AS `extracted_id`,`j`.`full_description` AS `full_description`,`j`.`general_information` AS `general_information`,`j`.`group_classification` AS `group_classification`,`j`.`how_to_apply` AS `how_to_apply`,`j`.`imported_at` AS `imported_at`,`j`.`job_category` AS `job_category`,`j`.`job_details` AS `job_details`,`j`.`link` AS `link`,`j`.`location` AS `location`,`j`.`ministry` AS `ministry`,`j`.`missions` AS `missions`,`j`.`nationality` AS `nationality`,`j`.`organization` AS `organization`,`j`.`profile` AS `profile`,`j`.`recruiter` AS `recruiter`,`j`.`required_documents` AS `required_documents`,`j`.`salary_group` AS `salary_group`,`j`.`source_file` AS `source_file`,`j`.`status` AS `status`,`j`.`task` AS `task`,`j`.`title` AS `title`,`j`.`updated_at` AS `updated_at`,`j`.`vacancy_count` AS `vacancy_count`,trim(substring_index(`j`.`title`,'(',1)) AS `base_title`,cast(`j`.`created_at` as date) AS `post_date`,concat(trim(substring_index(`j`.`title`,'(',1)),'--',cast(`j`.`created_at` as date)) AS `group_key` from `jobs` `j`)select `duplicate_groups`.`id` AS `id`,`duplicate_groups`.`title` AS `title`,`duplicate_groups`.`link` AS `link`,`duplicate_groups`.`created_at` AS `created_at`,`duplicate_groups`.`group_classification` AS `group_classification`,`duplicate_groups`.`base_title` AS `base_title`,`duplicate_groups`.`post_date` AS `post_date`,`duplicate_groups`.`group_key` AS `group_key`,`duplicate_groups`.`education_level` AS `education_level`,`duplicate_groups`.`full_description` AS `full_description`,`duplicate_groups`.`application_deadline` AS `application_deadline`,`duplicate_groups`.`job_category` AS `job_category`,`duplicate_groups`.`ministry` AS `ministry`,`duplicate_groups`.`missions` AS `missions`,`duplicate_groups`.`organization` AS `organization`,`duplicate_groups`.`status` AS `status`,group_concat(`duplicate_groups`.`id` order by `duplicate_groups`.`id` ASC separator ',') AS `grouped_ids` from `duplicate_groups` group by `duplicate_groups`.`group_key`;

CREATE TABLE `user_pins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `target_type` varchar(50) NOT NULL COMMENT 'z.B. "job", "job_group", …',
  `target_key` varchar(255) NOT NULL COMMENT 'z.B. job.id oder group_key',
  `pinned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_user_target` (`user_id`,`target_type`,`target_key`),
  CONSTRAINT `fk_user_pins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `time_frame` int(11) NOT NULL DEFAULT 7,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `verification_token` varchar(255) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Marker-Historie für Jobs und Gruppen
CREATE TABLE `job_markers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `target_type` varchar(50) NOT NULL COMMENT 'z.B. "job", "job_group"',
  `target_key` varchar(255) NOT NULL COMMENT 'ID oder group_key',
  `marker` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_markers_user` (`user_id`),
  CONSTRAINT `fk_markers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;