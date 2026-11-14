<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSaleSyncRequests extends Migration
{
    protected $DBGroup = 'pharmacy';

    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => false,
            ],
            'idempotency_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => false,
            ],
            'payload_hash' => [
                'type'       => 'CHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'sale_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(
            ['user_id', 'idempotency_key'],
            false,
            true,
            'uq_sale_sync_user_key'
        );
        $this->forge->addKey('sale_id');

        $this->forge->addForeignKey(
            'sale_id',
            'sales',
            'id',
            'CASCADE',
            'RESTRICT',
            'sale_sync_requests_sale_id_foreign'
        );

        $this->forge->createTable(
            'sale_sync_requests',
            false,
            [
                'ENGINE'  => 'InnoDB',
                'CHARSET' => 'utf8mb4',
                'COLLATE' => 'utf8mb4_unicode_ci',
            ]
        );
    }

    public function down()
    {
        $this->forge->dropTable('sale_sync_requests', true);
    }
}
