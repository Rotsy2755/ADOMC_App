-- =====================================================================
-- ADOMC_App — Complete database schema (MySQL 8.0 / utf8mb4)
-- Multi-Objective Multi-Criteria Decision platform (MOKP + MCDM + AHP)
-- =====================================================================
-- Database: mokp
-- Engine:   InnoDB, CHARSET utf8mb4, COLLATE utf8mb4_unicode_ci
-- Tables:   12 (users, projects, problems, items, solutions,
--               ahp_matrices, mcdm_results, sensitivity_analyses,
--               share_links, problem_snapshots, audit_logs,
--               messenger_messages)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `mokp`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE `mokp`;

-- ---------------------------------------------------------------------
-- 1) users — authentication, roles, Argon2id password hashes
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`             INT AUTO_INCREMENT NOT NULL,
    `email`          VARCHAR(180) NOT NULL,
    `full_name`      VARCHAR(120) NOT NULL,
    `roles`          JSON NOT NULL,
    `password`       VARCHAR(255) NOT NULL COMMENT 'Argon2id hash',
    `enabled`        TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`     DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    `last_login_at`  DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    UNIQUE INDEX `uniq_user_email` (`email`),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) projects — owner container for one or many problems
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
    `id`          INT AUTO_INCREMENT NOT NULL,
    `user_id`     INT NOT NULL,
    `name`        VARCHAR(180) NOT NULL,
    `description` LONGTEXT DEFAULT NULL,
    `created_at`  DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    `updated_at`  DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX `idx_project_user` (`user_id`),
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_project_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) problems — MOKP instances (capacity, p objectives, status…)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `problems`;
CREATE TABLE `problems` (
    `id`               INT AUTO_INCREMENT NOT NULL,
    `project_id`       INT NOT NULL,
    `name`             VARCHAR(180) NOT NULL,
    `description`      LONGTEXT DEFAULT NULL,
    `capacity`         DOUBLE PRECISION NOT NULL,
    `objective_count`  INT NOT NULL,
    `objectives`       JSON NOT NULL COMMENT 'List of {code,label,type,unit}',
    `status`           VARCHAR(32) NOT NULL DEFAULT 'draft'
                       COMMENT 'draft|ready|solving|solved|failed',
    `algorithm`        VARCHAR(32) DEFAULT NULL
                       COMMENT 'epsilon|nsga2',
    `fuzzy_enabled`    TINYINT(1) NOT NULL DEFAULT 0,
    `progress`         INT NOT NULL DEFAULT 0,
    `created_at`       DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    `updated_at`       DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX `idx_problem_project` (`project_id`),
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_problem_project`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) items — alternatives with weight and p-dimensional value vector
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
    `id`           INT AUTO_INCREMENT NOT NULL,
    `problem_id`   INT NOT NULL,
    `name`         VARCHAR(180) NOT NULL,
    `weight`       DOUBLE PRECISION NOT NULL,
    `values`       JSON NOT NULL COMMENT 'Crisp objective vector (list<float>)',
    `fuzzy_values` JSON DEFAULT NULL COMMENT 'Optional triangular {l,m,u} per objective',
    `position`     INT NOT NULL DEFAULT 0,
    INDEX `idx_item_problem` (`problem_id`),
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_item_problem`
        FOREIGN KEY (`problem_id`) REFERENCES `problems` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5) solutions — Pareto solutions (selected subset + objective values)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `solutions`;
CREATE TABLE `solutions` (
    `id`                 INT AUTO_INCREMENT NOT NULL,
    `problem_id`         INT NOT NULL,
    `selected_item_ids`  JSON NOT NULL COMMENT 'List of item ids selected in this solution',
    `total_weight`       DOUBLE PRECISION NOT NULL,
    `objective_values`   JSON NOT NULL COMMENT 'Aggregated objective vector',
    `is_pareto`          TINYINT(1) NOT NULL DEFAULT 1,
    `dominated_by_id`    INT DEFAULT NULL,
    `algorithm`          VARCHAR(32) NOT NULL COMMENT 'epsilon|nsga2',
    `created_at`         DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX `idx_solution_problem` (`problem_id`),
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_solution_problem`
        FOREIGN KEY (`problem_id`) REFERENCES `problems` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6) ahp_matrices — pairwise comparison matrices, λmax, CI, CR
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `ahp_matrices`;
CREATE TABLE `ahp_matrices` (
    `id`          INT AUTO_INCREMENT NOT NULL,
    `problem_id`  INT NOT NULL,
    `author_id`   INT DEFAULT NULL,
    `label`       VARCHAR(120) NOT NULL,
    `matrix`      JSON NOT NULL COMMENT 'p x p pairwise comparison matrix',
    `weights`     JSON NOT NULL COMMENT 'Priority vector (Saaty eigenvector)',
    `lambda_max`  DOUBLE PRECISION NOT NULL,
    `ci`          DOUBLE PRECISION NOT NULL COMMENT 'Consistency Index',
    `cr`          DOUBLE PRECISION NOT NULL COMMENT 'Consistency Ratio',
    `consistent`  TINYINT(1) NOT NULL COMMENT '1 if CR < 0.10',
    `created_at`  DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX `idx_ahp_problem` (`problem_id`),
    INDEX `idx_ahp_author`  (`author_id`),
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ahp_problem`
        FOREIGN KEY (`problem_id`) REFERENCES `problems` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_ahp_author`
        FOREIGN KEY (`author_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7) mcdm_results — TOPSIS/VIKOR/PROMETHEE-II rankings on a Pareto front
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `mcdm_results`;
CREATE TABLE `mcdm_results` (
    `id`                       INT AUTO_INCREMENT NOT NULL,
    `problem_id`               INT NOT NULL,
    `ahp_matrix_id`            INT DEFAULT NULL,
    `method`                   VARCHAR(32) NOT NULL COMMENT 'topsis|vikor|promethee2',
    `normalization`            VARCHAR(16) NOT NULL COMMENT 'vector|minmax|zscore',
    `rankings`                 JSON NOT NULL COMMENT 'List of {solution_id,rank,score}',
    `params`                   JSON NOT NULL COMMENT 'Method-specific parameters (v, p, q, preference fn)',
    `weights`                  JSON NOT NULL COMMENT 'λ weights used for this ranking',
    `recommended_solution_id`  INT DEFAULT NULL,
    `chosen`                   TINYINT(1) NOT NULL DEFAULT 0
                               COMMENT 'Marked by the decider (inverse preference learning input)',
    `created_at`               DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX `idx_mcdm_problem` (`problem_id`),
    INDEX `idx_mcdm_ahp`     (`ahp_matrix_id`),
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_mcdm_problem`
        FOREIGN KEY (`problem_id`) REFERENCES `problems` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_mcdm_ahp`
        FOREIGN KEY (`ahp_matrix_id`) REFERENCES `ahp_matrices` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8) sensitivity_analyses — weight-interval stability studies
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `sensitivity_analyses`;
CREATE TABLE `sensitivity_analyses` (
    `id`                     INT AUTO_INCREMENT NOT NULL,
    `mcdm_result_id`         INT NOT NULL,
    `criterion`              INT NOT NULL COMMENT '0-based criterion index being varied',
    `min_value`              DOUBLE PRECISION NOT NULL,
    `max_value`              DOUBLE PRECISION NOT NULL,
    `step`                   DOUBLE PRECISION NOT NULL,
    `top_k`                  INT NOT NULL DEFAULT 3,
    `robustness_threshold`   DOUBLE PRECISION NOT NULL DEFAULT 0.80,
    `configurations`         JSON NOT NULL COMMENT 'Per-weight ranking snapshots',
    `robust_solutions`       JSON NOT NULL COMMENT 'Solutions always in top-k',
    `summary`                LONGTEXT DEFAULT NULL,
    `created_at`             DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX `idx_sens_mcdm` (`mcdm_result_id`),
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_sens_mcdm`
        FOREIGN KEY (`mcdm_result_id`) REFERENCES `mcdm_results` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9) share_links — tokenised public read-only share URLs
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `share_links`;
CREATE TABLE `share_links` (
    `id`          INT AUTO_INCREMENT NOT NULL,
    `problem_id`  INT NOT NULL,
    `owner_id`    INT DEFAULT NULL,
    `token_hash`  VARCHAR(64) NOT NULL COMMENT 'SHA-256 of raw token',
    `created_at`  DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    `expires_at`  DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    `revoked`     TINYINT(1) NOT NULL DEFAULT 0,
    `views`       INT NOT NULL DEFAULT 0,
    UNIQUE INDEX `uniq_share_token` (`token_hash`),
    INDEX `idx_share_problem` (`problem_id`),
    INDEX `idx_share_owner`   (`owner_id`),
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_share_problem`
        FOREIGN KEY (`problem_id`) REFERENCES `problems` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_share_owner`
        FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10) problem_snapshots — historical JSON snapshots for rollback
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `problem_snapshots`;
CREATE TABLE `problem_snapshots` (
    `id`          INT AUTO_INCREMENT NOT NULL,
    `problem_id`  INT NOT NULL,
    `author_id`   INT DEFAULT NULL,
    `payload`     JSON NOT NULL COMMENT 'Full problem serialised state',
    `reason`      VARCHAR(180) DEFAULT NULL,
    `created_at`  DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX `idx_snapshot_problem` (`problem_id`),
    INDEX `idx_snapshot_author`  (`author_id`),
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_snapshot_problem`
        FOREIGN KEY (`problem_id`) REFERENCES `problems` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_snapshot_author`
        FOREIGN KEY (`author_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11) audit_logs — security & compliance trail
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
    `id`           INT AUTO_INCREMENT NOT NULL,
    `action`       VARCHAR(64) NOT NULL,
    `entity_type`  VARCHAR(64) DEFAULT NULL,
    `entity_id`    INT DEFAULT NULL,
    `user_email`   VARCHAR(180) DEFAULT NULL,
    `ip_address`   VARCHAR(45) DEFAULT NULL,
    `context`      JSON DEFAULT NULL,
    `created_at`   DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX `idx_audit_action`     (`action`),
    INDEX `idx_audit_created_at` (`created_at`),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12) messenger_messages — Symfony Messenger transport (async workers)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `messenger_messages`;
CREATE TABLE `messenger_messages` (
    `id`            BIGINT AUTO_INCREMENT NOT NULL,
    `body`          LONGTEXT NOT NULL,
    `headers`       LONGTEXT NOT NULL,
    `queue_name`    VARCHAR(190) NOT NULL,
    `created_at`    DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    `available_at`  DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    `delivered_at`  DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX `idx_messenger_queue`      (`queue_name`),
    INDEX `idx_messenger_available`  (`available_at`),
    INDEX `idx_messenger_delivered`  (`delivered_at`),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- Seed data — minimal accounts (passwords must be regenerated with Argon2id
-- via `php bin/console doctrine:fixtures:load`, which loads
-- HumanitarianOngFixtures with the exact ONG reference case).
--
-- Fixtures seed:
--   admin@adomc.local / Admin1234!     (ROLE_ADMIN)
--   ong@adomc.local   / User1234!      (ROLE_USER)
--   Reference problem: 8 items, capacity 30 kg, 3 objectives
--     items: Medicines, Food, Water, Blankets, Tents,
--            Hygiene, Radio, Generator
--     expected norm √(9²+10²+8²+6²+7²+9²+5²+6²) = √472 ≈ 21.732
--     AHP weights: λ = [0.50, 0.30, 0.20], CR < 0.10
--     ≥ 5 non-dominated Pareto solutions by ε-constraint
-- =====================================================================
