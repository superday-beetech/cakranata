<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePharmacyReturnTables extends Migration
{
    protected $DBGroup = 'pharmacy';

    public function up()
    {
        // PURCHASE RETURNS
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'return_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'supplier_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'goods_receipt_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'return_date' => ['type' => 'DATE'],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'draft',
            ],
            'reason' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'total_amount' => [
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
            'updated_by' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('return_number', false, true);
        $this->forge->addKey('supplier_id');
        $this->forge->addKey('goods_receipt_id');
        $this->forge->addKey('return_date');
        $this->forge->addKey('status');

        $this->forge->addForeignKey(
            'supplier_id',
            'suppliers',
            'id',
            'CASCADE',
            'RESTRICT'
        );
        $this->forge->addForeignKey(
            'goods_receipt_id',
            'goods_receipts',
            'id',
            'CASCADE',
            'RESTRICT'
        );

        $this->forge->createTable('purchase_returns', true);

        // PURCHASE RETURN ITEMS
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'purchase_return_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'goods_receipt_item_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'batch_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'quantity' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'default'    => 0,
            ],
            'unit_cost' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
            ],
            'subtotal' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
            ],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('purchase_return_id');
        $this->forge->addKey('goods_receipt_item_id');
        $this->forge->addKey('batch_id');

        $this->forge->addForeignKey(
            'purchase_return_id',
            'purchase_returns',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->forge->addForeignKey(
            'goods_receipt_item_id',
            'goods_receipt_items',
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

        $this->forge->createTable('purchase_return_items', true);

        // SALES RETURNS
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'return_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'customer_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'delivery_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'return_date' => ['type' => 'DATE'],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'draft',
            ],
            'reason' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'total_amount' => [
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
            'updated_by' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('return_number', false, true);
        $this->forge->addKey('customer_id');
        $this->forge->addKey('delivery_id');
        $this->forge->addKey('return_date');
        $this->forge->addKey('status');

        $this->forge->addForeignKey(
            'customer_id',
            'customers',
            'id',
            'CASCADE',
            'RESTRICT'
        );
        $this->forge->addForeignKey(
            'delivery_id',
            'deliveries',
            'id',
            'CASCADE',
            'RESTRICT'
        );

        $this->forge->createTable('sales_returns', true);

        // SALES RETURN ITEMS
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'sales_return_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'delivery_item_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'batch_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'quantity' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'default'    => 0,
            ],
            'unit_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
            ],
            'subtotal' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
            ],
            'restockable' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'condition_notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('sales_return_id');
        $this->forge->addKey('delivery_item_id');
        $this->forge->addKey('batch_id');

        $this->forge->addForeignKey(
            'sales_return_id',
            'sales_returns',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->forge->addForeignKey(
            'delivery_item_id',
            'delivery_items',
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

        $this->forge->createTable('sales_return_items', true);
    }

    public function down()
    {
        $this->forge->dropTable('sales_return_items', true);
        $this->forge->dropTable('sales_returns', true);
        $this->forge->dropTable('purchase_return_items', true);
        $this->forge->dropTable('purchase_returns', true);
    }
}
