<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePharmacyDistributionTables extends Migration
{
    protected $DBGroup = 'pharmacy';

    public function up()
    {
        // DISTRIBUTION ORDERS
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
            'customer_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'order_date' => ['type' => 'DATE'],
            'requested_delivery_date' => [
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
        $this->forge->addKey('customer_id');
        $this->forge->addKey('order_date');
        $this->forge->addKey('status');

        $this->forge->addForeignKey(
            'customer_id',
            'customers',
            'id',
            'CASCADE',
            'RESTRICT'
        );

        $this->forge->createTable('distribution_orders', true);

        // DISTRIBUTION ITEMS
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'distribution_order_id' => [
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
            'unit_price' => [
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
        $this->forge->addKey('distribution_order_id');
        $this->forge->addKey('product_id');

        $this->forge->addForeignKey(
            'distribution_order_id',
            'distribution_orders',
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

        $this->forge->createTable('distribution_items', true);

        // DELIVERIES
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'delivery_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'distribution_order_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'delivery_date' => ['type' => 'DATE'],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'draft',
            ],
            'recipient_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'recipient_phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
            'shipping_address' => [
                'type' => 'TEXT',
                'null' => true,
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
        $this->forge->addKey('delivery_number', false, true);
        $this->forge->addKey('distribution_order_id');
        $this->forge->addKey('delivery_date');
        $this->forge->addKey('status');

        $this->forge->addForeignKey(
            'distribution_order_id',
            'distribution_orders',
            'id',
            'CASCADE',
            'RESTRICT'
        );

        $this->forge->createTable('deliveries', true);

        // DELIVERY ITEMS
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'delivery_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'distribution_item_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'batch_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'quantity_dispatched' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'default'    => 0,
            ],
            'unit_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('delivery_id');
        $this->forge->addKey('distribution_item_id');
        $this->forge->addKey('batch_id');

        $this->forge->addForeignKey(
            'delivery_id',
            'deliveries',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->forge->addForeignKey(
            'distribution_item_id',
            'distribution_items',
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

        $this->forge->createTable('delivery_items', true);
    }

    public function down()
    {
        $this->forge->dropTable('delivery_items', true);
        $this->forge->dropTable('deliveries', true);
        $this->forge->dropTable('distribution_items', true);
        $this->forge->dropTable('distribution_orders', true);
    }
}
