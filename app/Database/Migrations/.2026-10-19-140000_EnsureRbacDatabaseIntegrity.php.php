<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class EnsureRbacDatabaseIntegrity extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        // Shield tables must already exist.
        foreach (
            [
                'users',
                'auth_groups',
                'auth_permissions',
                'auth_groups_permissions',
                'auth_groups_users',
                'auth_permissions_users',
            ] as $table
        ) {
            if (! $db->tableExists($table)) {
                throw new RuntimeException(
                    "Required RBAC table '{$table}' does not exist."
                );
            }
        }

        // Prevent duplicate group memberships.
        $this->ensureUniqueRelation(
            'auth_groups_users',
            'uq_agu_user_group',
            ['user_id', '`group`'],
            'user_id',
            '`group`'
        );

        // Prevent duplicate direct user permissions.
        $this->ensureUniqueRelation(
            'auth_permissions_users',
            'uq_apu_user_permission',
            ['user_id', 'permission'],
            'user_id',
            'permission'
        );

        // Optimize reverse lookups.
        $this->ensureIndex(
            'auth_groups_users',
            'idx_agu_group_user',
            ['`group`', 'user_id']
        );

        $this->ensureIndex(
            'auth_permissions_users',
            'idx_apu_permission_user',
            ['permission', 'user_id']
        );

        // Create audit table only when it does not exist.
        if (! $db->tableExists('auth_rbac_audit_logs')) {
            $db->query(<<<'SQL'
CREATE TABLE `auth_rbac_audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_user_id` INT UNSIGNED DEFAULT NULL,
    `target_user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(100) NOT NULL,
    `entity_id` VARCHAR(100) DEFAULT NULL,
    `old_values` JSON DEFAULT NULL,
    `new_values` JSON DEFAULT NULL,
    `ip_address` VARBINARY(16) DEFAULT NULL,
    `user_agent` VARCHAR(512) DEFAULT NULL,
    `request_id` VARCHAR(100) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    KEY `idx_rbac_audit_actor_time`
        (`actor_user_id`, `created_at`),

    KEY `idx_rbac_audit_target_time`
        (`target_user_id`, `created_at`),

    KEY `idx_rbac_audit_entity`
        (`entity_type`, `entity_id`),

    KEY `idx_rbac_audit_action_time`
        (`action`, `created_at`),

    KEY `idx_rbac_audit_request` (`request_id`),

    CONSTRAINT `fk_rbac_audit_actor`
        FOREIGN KEY (`actor_user_id`)
        REFERENCES `users` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT `fk_rbac_audit_target`
        FOREIGN KEY (`target_user_id`)
        REFERENCES `users` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL);
        }

        // Ensure audit indexes exist even if the table was created manually.
        $this->ensureIndex(
            'auth_rbac_audit_logs',
            'idx_rbac_audit_actor_time',
            ['actor_user_id', 'created_at']
        );

        $this->ensureIndex(
            'auth_rbac_audit_logs',
            'idx_rbac_audit_target_time',
            ['target_user_id', 'created_at']
        );

        $this->ensureIndex(
            'auth_rbac_audit_logs',
            'idx_rbac_audit_entity',
            ['entity_type', 'entity_id']
        );

        $this->ensureIndex(
            'auth_rbac_audit_logs',
            'idx_rbac_audit_action_time',
            ['action', 'created_at']
        );

        $this->ensureIndex(
            'auth_rbac_audit_logs',
            'idx_rbac_audit_request',
            ['request_id']
        );
    }

    private function ensureUniqueRelation(
        string $table,
        string $index,
        array $columns,
        string $groupColumn,
        string $valueColumn
    ): void {
        if ($this->indexExists($table, $index)) {
            return;
        }

        $duplicates = $this->db->query(
            "SELECT 1
             FROM `{$table}`
             GROUP BY `{$groupColumn}`, {$valueColumn}
             HAVING COUNT(*) > 1
             LIMIT 1"
        )->getRowArray();

        if ($duplicates !== null) {
            throw new RuntimeException(
                "Cannot create unique index '{$index}': duplicate relations exist."
            );
        }

        $this->addIndex($table, $index, $columns, true);
    }

    private function ensureIndex(
        string $table,
        string $index,
        array $columns
    ): void {
        if ($this->indexExists($table, $index)) {
            return;
        }

        $this->addIndex($table, $index, $columns);
    }

    private function indexExists(string $table, string $index): bool
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists($table)) {
            return false;
        }

        $indexes = $this->db
            ->query("SHOW INDEX FROM `{$table}`")
            ->getResultArray();

        foreach ($indexes as $row) {
            if ($row['Key_name'] === $index) {
                return true;
            }
        }

        return false;
    }

    private function addIndex(
        string $table,
        string $index,
        array $columns,
        bool $unique = false
    ): void {
        $columnList = implode(', ', $columns);
        $type = $unique ? 'UNIQUE KEY' : 'KEY';

        $this->db->query(
            "ALTER TABLE `{$table}`
             ADD {$type} `{$index}` ({$columnList})"
        );
    }

    public function down()
    {
        // Intentionally non-destructive:
        // preserve existing RBAC structures and audit history.
    }
}
