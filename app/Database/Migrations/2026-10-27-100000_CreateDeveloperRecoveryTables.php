<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDeveloperRecoveryTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'default'    => 'active',
            ],
            'verification_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'session_fingerprint' => [
                'type'       => 'CHAR',
                'constraint' => 64,
            ],
            'activated_at' => [
                'type' => 'DATETIME',
            ],
            'expires_at' => [
                'type' => 'DATETIME',
            ],
            'revoked_at' => [
                'type'     => 'DATETIME',
                'null'     => true,
                'default'  => null,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(
            ['user_id', 'status', 'expires_at'],
            false,
            false,
            'idx_recovery_user_status_expiry'
        );

        $this->forge->createTable(
            'developer_recovery_sessions',
            true
        );

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'recovery_session_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
            ],
            'actor_user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
            ],
            'event' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'outcome' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
                'default'    => null,
            ],
            'user_agent_hash' => [
                'type'       => 'CHAR',
                'constraint' => 64,
                'null'       => true,
                'default'    => null,
            ],
            'metadata_json' => [
                'type' => 'TEXT',
                'null' => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(
            ['actor_user_id', 'created_at'],
            false,
            false,
            'idx_recovery_audit_actor_time'
        );
        $this->forge->addKey(
            ['event', 'created_at'],
            false,
            false,
            'idx_recovery_audit_event_time'
        );

        $this->forge->createTable(
            'developer_recovery_audits',
            true
        );
    }

    public function down()
    {
        // Hanya menghapus tabel yang dibuat migration ini.
        $this->forge->dropTable(
            'developer_recovery_audits',
            true
        );

        $this->forge->dropTable(
            'developer_recovery_sessions',
            true
        );
    }
}
