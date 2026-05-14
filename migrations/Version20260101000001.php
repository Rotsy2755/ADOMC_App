<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema — creates every table for ADOMC_App.
 *
 * Tables: users, projects, problems, items, solutions, ahp_matrices,
 * mcdm_results, sensitivity_analyses, share_links, problem_snapshots,
 * audit_logs, messenger_messages.
 */
final class Version20260101000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial ADOMC schema — users, projects, problems, items, solutions, AHP, MCDM, sensitivity, share, snapshots, audit, messenger.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(180) NOT NULL,
            full_name VARCHAR(120) NOT NULL,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            enabled TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            last_login_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_user_email (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE projects (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            name VARCHAR(180) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_project_user (user_id),
            PRIMARY KEY(id),
            CONSTRAINT fk_project_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE problems (
            id INT AUTO_INCREMENT NOT NULL,
            project_id INT NOT NULL,
            name VARCHAR(180) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            capacity DOUBLE PRECISION NOT NULL,
            objective_count INT NOT NULL,
            objectives JSON NOT NULL,
            status VARCHAR(32) NOT NULL,
            algorithm VARCHAR(32) DEFAULT NULL,
            fuzzy_enabled TINYINT(1) NOT NULL,
            progress INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_problem_project (project_id),
            PRIMARY KEY(id),
            CONSTRAINT fk_problem_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE items (
            id INT AUTO_INCREMENT NOT NULL,
            problem_id INT NOT NULL,
            name VARCHAR(180) NOT NULL,
            weight DOUBLE PRECISION NOT NULL,
            `values` JSON NOT NULL,
            fuzzy_values JSON DEFAULT NULL,
            position INT NOT NULL,
            INDEX idx_item_problem (problem_id),
            PRIMARY KEY(id),
            CONSTRAINT fk_item_problem FOREIGN KEY (problem_id) REFERENCES problems (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE solutions (
            id INT AUTO_INCREMENT NOT NULL,
            problem_id INT NOT NULL,
            selected_item_ids JSON NOT NULL,
            total_weight DOUBLE PRECISION NOT NULL,
            objective_values JSON NOT NULL,
            is_pareto TINYINT(1) NOT NULL,
            dominated_by_id INT DEFAULT NULL,
            algorithm VARCHAR(32) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_solution_problem (problem_id),
            PRIMARY KEY(id),
            CONSTRAINT fk_solution_problem FOREIGN KEY (problem_id) REFERENCES problems (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE ahp_matrices (
            id INT AUTO_INCREMENT NOT NULL,
            problem_id INT NOT NULL,
            author_id INT DEFAULT NULL,
            label VARCHAR(120) NOT NULL,
            matrix JSON NOT NULL,
            weights JSON NOT NULL,
            lambda_max DOUBLE PRECISION NOT NULL,
            ci DOUBLE PRECISION NOT NULL,
            cr DOUBLE PRECISION NOT NULL,
            consistent TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_ahp_problem (problem_id),
            INDEX idx_ahp_author (author_id),
            PRIMARY KEY(id),
            CONSTRAINT fk_ahp_problem FOREIGN KEY (problem_id) REFERENCES problems (id) ON DELETE CASCADE,
            CONSTRAINT fk_ahp_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE mcdm_results (
            id INT AUTO_INCREMENT NOT NULL,
            problem_id INT NOT NULL,
            ahp_matrix_id INT DEFAULT NULL,
            method VARCHAR(32) NOT NULL,
            normalization VARCHAR(16) NOT NULL,
            rankings JSON NOT NULL,
            params JSON NOT NULL,
            weights JSON NOT NULL,
            recommended_solution_id INT DEFAULT NULL,
            chosen TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_mcdm_problem (problem_id),
            INDEX idx_mcdm_ahp (ahp_matrix_id),
            PRIMARY KEY(id),
            CONSTRAINT fk_mcdm_problem FOREIGN KEY (problem_id) REFERENCES problems (id) ON DELETE CASCADE,
            CONSTRAINT fk_mcdm_ahp FOREIGN KEY (ahp_matrix_id) REFERENCES ahp_matrices (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE sensitivity_analyses (
            id INT AUTO_INCREMENT NOT NULL,
            mcdm_result_id INT NOT NULL,
            criterion INT NOT NULL,
            min_value DOUBLE PRECISION NOT NULL,
            max_value DOUBLE PRECISION NOT NULL,
            step DOUBLE PRECISION NOT NULL,
            top_k INT NOT NULL,
            robustness_threshold DOUBLE PRECISION NOT NULL,
            configurations JSON NOT NULL,
            robust_solutions JSON NOT NULL,
            summary LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_sens_mcdm (mcdm_result_id),
            PRIMARY KEY(id),
            CONSTRAINT fk_sens_mcdm FOREIGN KEY (mcdm_result_id) REFERENCES mcdm_results (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE share_links (
            id INT AUTO_INCREMENT NOT NULL,
            problem_id INT NOT NULL,
            owner_id INT DEFAULT NULL,
            token_hash VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            revoked TINYINT(1) NOT NULL,
            views INT NOT NULL,
            UNIQUE INDEX uniq_share_token (token_hash),
            INDEX idx_share_problem (problem_id),
            INDEX idx_share_owner (owner_id),
            PRIMARY KEY(id),
            CONSTRAINT fk_share_problem FOREIGN KEY (problem_id) REFERENCES problems (id) ON DELETE CASCADE,
            CONSTRAINT fk_share_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE problem_snapshots (
            id INT AUTO_INCREMENT NOT NULL,
            problem_id INT NOT NULL,
            author_id INT DEFAULT NULL,
            payload JSON NOT NULL,
            reason VARCHAR(180) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_snapshot_problem (problem_id),
            INDEX idx_snapshot_author (author_id),
            PRIMARY KEY(id),
            CONSTRAINT fk_snapshot_problem FOREIGN KEY (problem_id) REFERENCES problems (id) ON DELETE CASCADE,
            CONSTRAINT fk_snapshot_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE audit_logs (
            id INT AUTO_INCREMENT NOT NULL,
            action VARCHAR(64) NOT NULL,
            entity_type VARCHAR(64) DEFAULT NULL,
            entity_id INT DEFAULT NULL,
            user_email VARCHAR(180) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            context JSON DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_audit_action (action),
            INDEX idx_audit_created_at (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE messenger_messages (
            id BIGINT AUTO_INCREMENT NOT NULL,
            body LONGTEXT NOT NULL,
            headers LONGTEXT NOT NULL,
            queue_name VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_messenger_queue (queue_name),
            INDEX idx_messenger_available (available_at),
            INDEX idx_messenger_delivered (delivered_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
        $this->addSql('DROP TABLE IF EXISTS audit_logs');
        $this->addSql('DROP TABLE IF EXISTS problem_snapshots');
        $this->addSql('DROP TABLE IF EXISTS share_links');
        $this->addSql('DROP TABLE IF EXISTS sensitivity_analyses');
        $this->addSql('DROP TABLE IF EXISTS mcdm_results');
        $this->addSql('DROP TABLE IF EXISTS ahp_matrices');
        $this->addSql('DROP TABLE IF EXISTS solutions');
        $this->addSql('DROP TABLE IF EXISTS items');
        $this->addSql('DROP TABLE IF EXISTS problems');
        $this->addSql('DROP TABLE IF EXISTS projects');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}
