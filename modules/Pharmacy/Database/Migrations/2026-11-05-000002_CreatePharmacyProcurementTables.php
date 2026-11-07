<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePharmacyProcurementTables extends Migration
{
    protected $DBGroup = 'pharmacy';

    public function up()
    {
        // PURCHASE ORDERS
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'order_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'supplier_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'order_date' => ['type' => 'DATE'],
            'expected_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'draft',
            ],
            'subtotal' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
            ],
            'discount_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
            ],
            'tax_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
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
        $this->forge->addKey('order_number', false, true);
        $this->forge->addKey('supplier_id');
        $this->forge->addKey('order_date');
        $this->forge->addKey('status');
        $this->forge->addForeignKey(
            'supplier_id',
            'suppliers',
            'id',
            'CASCADE',
            'RESTRICT'
        );
        $this->forge->createTable('purchase_orders', true);

        // PURCHASE ORDER ITEMS
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'purchase_order_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'product_id' => [
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
            'discount_amount' => [
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
        $this->forge->addKey('purchase_order_id');
        $this->forge->addKey('product_id');
        $this->forge->addForeignKey(
            'purchase_order_id',
            'purchase_orders',
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
        $this->forge->createTable('purchase_order_items', true);

        // GOODS RECEIPTS
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'receipt_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'purchase_order_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'received_date' => ['type' => 'DATE'],
            'supplier_delivery_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
            ],
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
        $this->forge->addKey('receipt_number', false, true);
        $this->forge->addKey('purchase_order_id');
        $this->forge->addKey('received_date');
        $this->forge->addKey('status');
        $this->forge->addForeignKey(
            'purchase_order_id',
            'purchase_orders',
            'id',
            'CASCADE',
            'RESTRICT'
        );
        $this->forge->createTable('goods_receipts', true);

        // GOODS RECEIPT ITEMS
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'goods_receipt_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'purchase_order_item_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'product_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'quantity_received' => [
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
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('goods_receipt_id');
        $this->forge->addKey('purchase_order_item_id');
        $this->forge->addKey('product_id');
        $this->forge->addForeignKey(
            'goods_receipt_id',
            'goods_receipts',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->forge->addForeignKey(
            'purchase_order_item_id',
            'purchase_order_items',
            'id',
            'CASCADE',
            'RESTRICT'
        );
        $this->forge->addForeignKey(
            'product_id',
            'products',
            'id',
            'CASCADE',
            'RESTRICT'
        );
        $this->forge->createTable('goods_receipt_items', true);
    }

    public function down()
    {
        $this->forge->dropTable('goods_receipt_items', true);
        $this->forge->dropTable('goods_receipts', true);
        $this->forge->dropTable('purchase_order_items', true);
        $this->forge->dropTable('purchase_orders', true);
    }
}
