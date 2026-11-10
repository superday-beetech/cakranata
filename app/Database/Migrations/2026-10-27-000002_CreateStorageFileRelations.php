<?php

declare(strict_types=1);

namespace Modules\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStorageFileRelations extends Migration
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
            'file_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'module' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'entity_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
            ],
            'entity_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
            ],
            'purpose' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'attachment',
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('file_id');
        $this->forge->addKey([
            'module',
            'entity_type',
            'entity_id',
        ]);
        $this->forge->addKey('purpose');

        $this->forge->createTable('storage_file_relations', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('storage_file_relations', true);
    }
}
