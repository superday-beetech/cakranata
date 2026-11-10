<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStorageFiles extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'disk' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'storage_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 512,
            ],
            'original_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'mime_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'extension' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'size' => [
                'type'     => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'checksum' => [
                'type'       => 'CHAR',
                'constraint' => 64,
            ],
            'organization_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
            ],
            'uploaded_by' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'active',
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['disk', 'storage_key'], false, true);
        $this->forge->addKey('organization_id');
        $this->forge->addKey('uploaded_by');
        $this->forge->addKey('status');
        $this->forge->addKey('checksum');

        $this->forge->createTable('storage_files', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('storage_files', true);
    }
}
