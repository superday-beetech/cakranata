<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePharmacyInventoryTables extends Migration
{
    protected $DBGroup = 'pharmacy';

    public function up()
    {
        // PRODUCT BATCHES
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'product_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'goods_receipt_item_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'batch_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
            ],
            'expired_at' => ['type' => 'DATE'],
            'received_quantity' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'default'    => 0,
            ],
            'quantity_remaining' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'default'    => 0,
            ],
            'unit_cost' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'active',
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('product_id');
        $this->forge->addKey('goods_receipt_item_id');
        $this->forge->addKey('batch_number');
        $this->forge->addKey('expired_at');
        $this->forge->addKey(['product_id', 'expired_at']);

        $this->forge->addForeignKey(
            'product_id',
            'products',
            'id',
            'CASCADE',
            'RESTRICT'
        );
        $this->forge->addForeignKey(
            'goods_receipt_item_id',
            'goods_receipt_items',
            'id',
            'CASCADE',
            'RESTRICT'
        );

        $this->forge->createTable('product_batches', true);

        // STOCK MOVEMENTS
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'product_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'batch_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'movement_date' => ['type' => 'DATETIME'],
            'movement_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'reference_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'reference_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'quantity_in' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'default'    => 0,
            ],
            'quantity_out' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'default'    => 0,
            ],
            'unit_cost' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
            ],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('product_id');
        $this->forge->addKey('batch_id');
        $this->forge->addKey('movement_date');
        $this->forge->addKey(['reference_type', 'reference_id']);
        $this->forge->addKey(['product_id', 'movement_date']);

        $this->forge->addForeignKey(
            'product_id',
            'products',
            'id',
            'CASCADE',
            'RESTRICT'
        );
        $this->forge->addForeignKey(
            'batch_id',
            'product_batches',
            'id',
            'CASCADE',
            'RESTRICT'
        );

        $this->forge->createTable('stock_movements', true);

        // STOCK OPNAMES
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'opname_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'opname_date' => ['type' => 'DATE'],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'draft',
            ],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'updated_by' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('opname_number', false, true);
        $this->forge->addKey('opname_date');
        $this->forge->addKey('status');

        $this->forge->createTable('stock_opnames', true);

        // STOCK OPNAME ITEMS
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'stock_opname_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'product_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'batch_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'system_quantity' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'default'    => 0,
            ],
            'counted_quantity' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'default'    => 0,
            ],
            'difference_quantity' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'default'    => 0,
            ],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('stock_opname_id');
        $this->forge->addKey('product_id');
        $this->forge->addKey('batch_id');
        $this->forge->addKey(
            ['stock_opname_id', 'batch_id'],
            false,
            true
        );

        $this->forge->addForeignKey(
            'stock_opname_id',
            'stock_opnames',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->forge->addForeignKey(
            'product_id',
            'products',
            'id',
            'CASCADE',
            'RESTRICT'
        );
        $this->forge->addForeignKey(
            'batch_id',
            'product_batches',
            'id',
            'CASCADE',
            'RESTRICT'
        );

        $this->forge->createTable('stock_opname_items', true);
    }

    public function down()
    {
        $this->forge->dropTable('stock_opname_items', true);
        $this->forge->dropTable('stock_opnames', true);
        $this->forge->dropTable('stock_movements', true);
        $this->forge->dropTable('product_batches', true);
    }
}
