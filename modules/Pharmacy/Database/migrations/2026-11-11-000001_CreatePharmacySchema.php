<?php

namespace Modules\Pharmacy\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePharmacySchema extends Migration
{
    protected $DBGroup = 'pharmacy';

    public function up()
    {
        // Table: cash_transactions
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'transaction_number' => ['type' => 'VARCHAR', 'constraint' => '40', 'null' => false],
            'transaction_date' => ['type' => 'DATETIME', 'null' => false],
            'transaction_type' => ['type' => 'VARCHAR', 'constraint' => '20', 'null' => false],
            'reference_type' => ['type' => 'VARCHAR', 'constraint' => '40', 'null' => false],
            'reference_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'description' => ['type' => 'VARCHAR', 'constraint' => '180', 'null' => true, 'default' => null],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'payment_method' => ['type' => 'VARCHAR', 'constraint' => '30', 'null' => false],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('transaction_number', false, true, 'transaction_number');
        $this->forge->addKey('transaction_date', false, false, 'transaction_date');
        $this->forge->addKey('transaction_type', false, false, 'transaction_type');
        $this->forge->addKey(['reference_type', 'reference_id'], false, false, 'reference_type_reference_id');
        $this->forge->createTable('cash_transactions', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: categories
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => '100', 'null' => false],
            'description' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => '1', 'null' => false, 'default' => '1'],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('name', false, true, 'name');
        $this->forge->createTable('categories', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: customers
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => '30', 'null' => false],
            'member_number' => ['type' => 'VARCHAR', 'constraint' => '50', 'null' => true, 'default' => null],
            'barcode' => ['type' => 'VARCHAR', 'constraint' => '100', 'null' => true, 'default' => null],
            'name' => ['type' => 'VARCHAR', 'constraint' => '150', 'null' => false],
            'customer_type' => ['type' => 'VARCHAR', 'constraint' => '30', 'null' => false, 'default' => 'retail'],
            'phone' => ['type' => 'VARCHAR', 'constraint' => '30', 'null' => true, 'default' => null],
            'email' => ['type' => 'VARCHAR', 'constraint' => '150', 'null' => true, 'default' => null],
            'address' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => '1', 'null' => false, 'default' => '1'],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('code', false, true, 'code');
        $this->forge->addKey('member_number', false, true, 'uq_customers_member_number');
        $this->forge->addKey('barcode', false, true, 'uq_customers_barcode');
        $this->forge->addKey('name', false, false, 'name');
        $this->forge->addKey('customer_type', false, false, 'customer_type');
        $this->forge->createTable('customers', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: distribution_orders
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'order_number' => ['type' => 'VARCHAR', 'constraint' => '40', 'null' => false],
            'customer_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'order_date' => ['type' => 'DATE', 'null' => false],
            'requested_delivery_date' => ['type' => 'DATE', 'null' => true, 'default' => null],
            'status' => ['type' => 'VARCHAR', 'constraint' => '20', 'null' => false, 'default' => 'draft'],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'total_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('order_number', false, true, 'order_number');
        $this->forge->addKey('customer_id', false, false, 'customer_id');
        $this->forge->addKey('order_date', false, false, 'order_date');
        $this->forge->addKey('status', false, false, 'status');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'RESTRICT', 'distribution_orders_customer_id_foreign');
        $this->forge->createTable('distribution_orders', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: customer_payments
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'payment_number' => ['type' => 'VARCHAR', 'constraint' => '40', 'null' => false],
            'customer_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'sale_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'distribution_order_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'payment_date' => ['type' => 'DATE', 'null' => false],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'payment_method' => ['type' => 'VARCHAR', 'constraint' => '30', 'null' => false],
            'reference_number' => ['type' => 'VARCHAR', 'constraint' => '100', 'null' => true, 'default' => null],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('payment_number', false, true, 'payment_number');
        $this->forge->addKey('customer_id', false, false, 'customer_id');
        $this->forge->addKey('distribution_order_id', false, false, 'distribution_order_id');
        $this->forge->addKey('payment_date', false, false, 'payment_date');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'RESTRICT', 'customer_payments_customer_id_foreign');
        $this->forge->addForeignKey('distribution_order_id', 'distribution_orders', 'id', 'CASCADE', 'RESTRICT', 'customer_payments_distribution_order_id_foreign');
        $this->forge->createTable('customer_payments', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: deliveries
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'delivery_number' => ['type' => 'VARCHAR', 'constraint' => '40', 'null' => false],
            'distribution_order_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'delivery_date' => ['type' => 'DATE', 'null' => false],
            'status' => ['type' => 'VARCHAR', 'constraint' => '20', 'null' => false, 'default' => 'draft'],
            'recipient_name' => ['type' => 'VARCHAR', 'constraint' => '150', 'null' => true, 'default' => null],
            'recipient_phone' => ['type' => 'VARCHAR', 'constraint' => '30', 'null' => true, 'default' => null],
            'shipping_address' => ['type' => 'TEXT', 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('delivery_number', false, true, 'delivery_number');
        $this->forge->addKey('distribution_order_id', false, false, 'distribution_order_id');
        $this->forge->addKey('delivery_date', false, false, 'delivery_date');
        $this->forge->addKey('status', false, false, 'status');
        $this->forge->addForeignKey('distribution_order_id', 'distribution_orders', 'id', 'CASCADE', 'RESTRICT', 'deliveries_distribution_order_id_foreign');
        $this->forge->createTable('deliveries', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: suppliers
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => '30', 'null' => false],
            'name' => ['type' => 'VARCHAR', 'constraint' => '150', 'null' => false],
            'phone' => ['type' => 'VARCHAR', 'constraint' => '30', 'null' => true, 'default' => null],
            'email' => ['type' => 'VARCHAR', 'constraint' => '150', 'null' => true, 'default' => null],
            'address' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => '1', 'null' => false, 'default' => '1'],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('code', false, true, 'code');
        $this->forge->addKey('name', false, false, 'name');
        $this->forge->createTable('suppliers', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: purchase_orders
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'order_number' => ['type' => 'VARCHAR', 'constraint' => '40', 'null' => false],
            'supplier_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'purchase_mode' => ['type' => 'VARCHAR', 'constraint' => '20', 'null' => true, 'default' => 'delivery'],
            'order_date' => ['type' => 'DATE', 'null' => false],
            'expected_date' => ['type' => 'DATE', 'null' => true, 'default' => null],
            'status' => ['type' => 'VARCHAR', 'constraint' => '20', 'null' => false, 'default' => 'draft'],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'total_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('order_number', false, true, 'order_number');
        $this->forge->addKey('supplier_id', false, false, 'supplier_id');
        $this->forge->addKey('order_date', false, false, 'order_date');
        $this->forge->addKey('status', false, false, 'status');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'CASCADE', 'RESTRICT', 'purchase_orders_supplier_id_foreign');
        $this->forge->createTable('purchase_orders', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: goods_receipts
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'receipt_number' => ['type' => 'VARCHAR', 'constraint' => '40', 'null' => false],
            'purchase_order_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'received_date' => ['type' => 'DATE', 'null' => false],
            'supplier_delivery_number' => ['type' => 'VARCHAR', 'constraint' => '80', 'null' => true, 'default' => null],
            'status' => ['type' => 'VARCHAR', 'constraint' => '20', 'null' => false, 'default' => 'draft'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('receipt_number', false, true, 'receipt_number');
        $this->forge->addKey('purchase_order_id', false, false, 'purchase_order_id');
        $this->forge->addKey('received_date', false, false, 'received_date');
        $this->forge->addKey('status', false, false, 'status');
        $this->forge->addForeignKey('purchase_order_id', 'purchase_orders', 'id', 'CASCADE', 'RESTRICT', 'goods_receipts_purchase_order_id_foreign');
        $this->forge->createTable('goods_receipts', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: units
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => '60', 'null' => false],
            'abbreviation' => ['type' => 'VARCHAR', 'constraint' => '20', 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('name', false, true, 'name');
        $this->forge->addKey('abbreviation', false, true, 'abbreviation');
        $this->forge->createTable('units', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: products
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'sku' => ['type' => 'VARCHAR', 'constraint' => '50', 'null' => false],
            'barcode' => ['type' => 'VARCHAR', 'constraint' => '100', 'null' => true, 'default' => null],
            'name' => ['type' => 'VARCHAR', 'constraint' => '180', 'null' => false],
            'category_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'unit_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'purchase_price' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'selling_price' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'description' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => '1', 'null' => false, 'default' => '1'],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('sku', false, true, 'sku');
        $this->forge->addKey('barcode', false, false, 'barcode');
        $this->forge->addKey('category_id', false, false, 'category_id');
        $this->forge->addKey('unit_id', false, false, 'unit_id');
        $this->forge->addForeignKey('category_id', 'categories', 'id', 'CASCADE', 'RESTRICT', 'products_category_id_foreign');
        $this->forge->addForeignKey('unit_id', 'units', 'id', 'CASCADE', 'RESTRICT', 'products_unit_id_foreign');
        $this->forge->createTable('products', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: purchase_order_items
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'purchase_order_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => false, 'default' => '0.000'],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('purchase_order_id', false, false, 'purchase_order_id');
        $this->forge->addKey('product_id', false, false, 'product_id');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'RESTRICT', 'purchase_order_items_product_id_foreign');
        $this->forge->addForeignKey('purchase_order_id', 'purchase_orders', 'id', 'CASCADE', 'CASCADE', 'purchase_order_items_purchase_order_id_foreign');
        $this->forge->createTable('purchase_order_items', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: goods_receipt_items
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'goods_receipt_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'purchase_order_item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'quantity_received' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => false, 'default' => '0.000'],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('goods_receipt_id', false, false, 'goods_receipt_id');
        $this->forge->addKey('purchase_order_item_id', false, false, 'purchase_order_item_id');
        $this->forge->addKey('product_id', false, false, 'product_id');
        $this->forge->addForeignKey('goods_receipt_id', 'goods_receipts', 'id', 'CASCADE', 'CASCADE', 'goods_receipt_items_goods_receipt_id_foreign');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'RESTRICT', 'goods_receipt_items_product_id_foreign');
        $this->forge->addForeignKey('purchase_order_item_id', 'purchase_order_items', 'id', 'CASCADE', 'RESTRICT', 'goods_receipt_items_purchase_order_item_id_foreign');
        $this->forge->createTable('goods_receipt_items', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: product_batches
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'goods_receipt_item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'batch_number' => ['type' => 'VARCHAR', 'constraint' => '80', 'null' => false],
            'expired_at' => ['type' => 'DATE', 'null' => true, 'default' => null],
            'received_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => false, 'default' => '0.000'],
            'quantity_remaining' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => false, 'default' => '0.000'],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'status' => ['type' => 'VARCHAR', 'constraint' => '20', 'null' => false, 'default' => 'available'],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('product_id', false, false, 'product_id');
        $this->forge->addKey('goods_receipt_item_id', false, false, 'goods_receipt_item_id');
        $this->forge->addKey('batch_number', false, false, 'batch_number');
        $this->forge->addKey('expired_at', false, false, 'expired_at');
        $this->forge->addKey(['product_id', 'expired_at'], false, false, 'product_id_expired_at');
        $this->forge->addForeignKey('goods_receipt_item_id', 'goods_receipt_items', 'id', 'CASCADE', 'RESTRICT', 'product_batches_goods_receipt_item_id_foreign');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'RESTRICT', 'product_batches_product_id_foreign');
        $this->forge->createTable('product_batches', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: distribution_items
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'distribution_order_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => false, 'default' => '0.000'],
            'unit_price' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('distribution_order_id', false, false, 'distribution_order_id');
        $this->forge->addKey('product_id', false, false, 'product_id');
        $this->forge->addForeignKey('distribution_order_id', 'distribution_orders', 'id', 'CASCADE', 'CASCADE', 'distribution_items_distribution_order_id_foreign');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'RESTRICT', 'distribution_items_product_id_foreign');
        $this->forge->createTable('distribution_items', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: delivery_items
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'delivery_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'distribution_item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'batch_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'quantity_dispatched' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => false, 'default' => '0.000'],
            'unit_price' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('delivery_id', false, false, 'delivery_id');
        $this->forge->addKey('distribution_item_id', false, false, 'distribution_item_id');
        $this->forge->addKey('batch_id', false, false, 'batch_id');
        $this->forge->addForeignKey('batch_id', 'product_batches', 'id', 'CASCADE', 'RESTRICT', 'delivery_items_batch_id_foreign');
        $this->forge->addForeignKey('delivery_id', 'deliveries', 'id', 'CASCADE', 'CASCADE', 'delivery_items_delivery_id_foreign');
        $this->forge->addForeignKey('distribution_item_id', 'distribution_items', 'id', 'CASCADE', 'RESTRICT', 'delivery_items_distribution_item_id_foreign');
        $this->forge->createTable('delivery_items', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: expenses
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'expense_number' => ['type' => 'VARCHAR', 'constraint' => '40', 'null' => false],
            'expense_date' => ['type' => 'DATE', 'null' => false],
            'category' => ['type' => 'VARCHAR', 'constraint' => '80', 'null' => false],
            'description' => ['type' => 'VARCHAR', 'constraint' => '180', 'null' => false],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'payment_method' => ['type' => 'VARCHAR', 'constraint' => '30', 'null' => false],
            'reference_number' => ['type' => 'VARCHAR', 'constraint' => '100', 'null' => true, 'default' => null],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('expense_number', false, true, 'expense_number');
        $this->forge->addKey('expense_date', false, false, 'expense_date');
        $this->forge->addKey('category', false, false, 'category');
        $this->forge->createTable('expenses', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: purchase_returns
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'return_number' => ['type' => 'VARCHAR', 'constraint' => '40', 'null' => false],
            'supplier_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'goods_receipt_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'return_date' => ['type' => 'DATE', 'null' => false],
            'status' => ['type' => 'VARCHAR', 'constraint' => '20', 'null' => false, 'default' => 'draft'],
            'reason' => ['type' => 'TEXT', 'null' => true],
            'total_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('return_number', false, true, 'return_number');
        $this->forge->addKey('supplier_id', false, false, 'supplier_id');
        $this->forge->addKey('goods_receipt_id', false, false, 'goods_receipt_id');
        $this->forge->addKey('return_date', false, false, 'return_date');
        $this->forge->addKey('status', false, false, 'status');
        $this->forge->addForeignKey('goods_receipt_id', 'goods_receipts', 'id', 'CASCADE', 'RESTRICT', 'purchase_returns_goods_receipt_id_foreign');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'CASCADE', 'RESTRICT', 'purchase_returns_supplier_id_foreign');
        $this->forge->createTable('purchase_returns', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: purchase_return_items
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'purchase_return_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'goods_receipt_item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'batch_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => false, 'default' => '0.000'],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('purchase_return_id', false, false, 'purchase_return_id');
        $this->forge->addKey('goods_receipt_item_id', false, false, 'goods_receipt_item_id');
        $this->forge->addKey('batch_id', false, false, 'batch_id');
        $this->forge->addForeignKey('batch_id', 'product_batches', 'id', 'CASCADE', 'RESTRICT', 'purchase_return_items_batch_id_foreign');
        $this->forge->addForeignKey('goods_receipt_item_id', 'goods_receipt_items', 'id', 'CASCADE', 'RESTRICT', 'purchase_return_items_goods_receipt_item_id_foreign');
        $this->forge->addForeignKey('purchase_return_id', 'purchase_returns', 'id', 'CASCADE', 'CASCADE', 'purchase_return_items_purchase_return_id_foreign');
        $this->forge->createTable('purchase_return_items', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: sales
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'sale_number' => ['type' => 'VARCHAR', 'constraint' => '40', 'null' => false],
            'customer_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'sale_date' => ['type' => 'DATETIME', 'null' => false],
            'status' => ['type' => 'VARCHAR', 'constraint' => '20', 'null' => false, 'default' => 'draft'],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'total_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'paid_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'amount_received' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'change_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'payment_status' => ['type' => 'VARCHAR', 'constraint' => '20', 'null' => false, 'default' => 'unpaid'],
            'settlement_mode' => ['type' => 'VARCHAR', 'constraint' => '20', 'null' => false, 'default' => 'paid'],
            'payment_method' => ['type' => 'VARCHAR', 'constraint' => '30', 'null' => true, 'default' => null],
            'reference_number' => ['type' => 'VARCHAR', 'constraint' => '100', 'null' => true, 'default' => null],
            'due_date' => ['type' => 'DATE', 'null' => true, 'default' => null],
            'collateral_note' => ['type' => 'VARCHAR', 'constraint' => '255', 'null' => true, 'default' => null],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('sale_number', false, true, 'sale_number');
        $this->forge->addKey('customer_id', false, false, 'customer_id');
        $this->forge->addKey('sale_date', false, false, 'sale_date');
        $this->forge->addKey('status', false, false, 'status');
        $this->forge->addKey('payment_status', false, false, 'payment_status');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'RESTRICT', 'sales_customer_id_foreign');
        $this->forge->createTable('sales', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: sales_returns
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'return_number' => ['type' => 'VARCHAR', 'constraint' => '40', 'null' => false],
            'customer_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'sale_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'delivery_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'return_date' => ['type' => 'DATE', 'null' => false],
            'status' => ['type' => 'VARCHAR', 'constraint' => '20', 'null' => false, 'default' => 'draft'],
            'reason' => ['type' => 'TEXT', 'null' => true],
            'total_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('return_number', false, true, 'return_number');
        $this->forge->addKey('customer_id', false, false, 'customer_id');
        $this->forge->addKey('delivery_id', false, false, 'delivery_id');
        $this->forge->addKey('return_date', false, false, 'return_date');
        $this->forge->addKey('status', false, false, 'status');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'RESTRICT', 'sales_returns_customer_id_foreign');
        $this->forge->addForeignKey('delivery_id', 'deliveries', 'id', 'CASCADE', 'RESTRICT', 'sales_returns_delivery_id_foreign');
        $this->forge->createTable('sales_returns', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: sales_return_items
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'sales_return_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'sale_item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'delivery_item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'batch_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => false, 'default' => '0.000'],
            'unit_price' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'restockable' => ['type' => 'TINYINT', 'constraint' => '1', 'null' => false, 'default' => '0'],
            'condition_notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('sales_return_id', false, false, 'sales_return_id');
        $this->forge->addKey('delivery_item_id', false, false, 'delivery_item_id');
        $this->forge->addKey('batch_id', false, false, 'batch_id');
        $this->forge->addForeignKey('batch_id', 'product_batches', 'id', 'CASCADE', 'RESTRICT', 'sales_return_items_batch_id_foreign');
        $this->forge->addForeignKey('delivery_item_id', 'delivery_items', 'id', 'CASCADE', 'RESTRICT', 'sales_return_items_delivery_item_id_foreign');
        $this->forge->addForeignKey('sales_return_id', 'sales_returns', 'id', 'CASCADE', 'CASCADE', 'sales_return_items_sales_return_id_foreign');
        $this->forge->createTable('sales_return_items', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: sale_items
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'sale_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'batch_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => false, 'default' => '0.000'],
            'unit_price' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('sale_id', false, false, 'sale_id');
        $this->forge->addKey('product_id', false, false, 'product_id');
        $this->forge->addKey('batch_id', false, false, 'batch_id');
        $this->forge->addForeignKey('batch_id', 'product_batches', 'id', 'CASCADE', 'RESTRICT', 'sale_items_batch_id_foreign');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'RESTRICT', 'sale_items_product_id_foreign');
        $this->forge->addForeignKey('sale_id', 'sales', 'id', 'CASCADE', 'CASCADE', 'sale_items_sale_id_foreign');
        $this->forge->createTable('sale_items', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: stock_movements
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'batch_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'movement_date' => ['type' => 'DATETIME', 'null' => false],
            'movement_type' => ['type' => 'VARCHAR', 'constraint' => '30', 'null' => false],
            'reference_type' => ['type' => 'VARCHAR', 'constraint' => '40', 'null' => false],
            'reference_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'quantity_in' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => false, 'default' => '0.000'],
            'quantity_out' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => false, 'default' => '0.000'],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('product_id', false, false, 'product_id');
        $this->forge->addKey('batch_id', false, false, 'batch_id');
        $this->forge->addKey('movement_date', false, false, 'movement_date');
        $this->forge->addKey(['reference_type', 'reference_id'], false, false, 'reference_type_reference_id');
        $this->forge->addKey(['product_id', 'movement_date'], false, false, 'product_id_movement_date');
        $this->forge->addForeignKey('batch_id', 'product_batches', 'id', 'CASCADE', 'RESTRICT', 'stock_movements_batch_id_foreign');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'RESTRICT', 'stock_movements_product_id_foreign');
        $this->forge->createTable('stock_movements', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: stock_opnames
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'opname_number' => ['type' => 'VARCHAR', 'constraint' => '40', 'null' => false],
            'opname_date' => ['type' => 'DATE', 'null' => false],
            'status' => ['type' => 'VARCHAR', 'constraint' => '20', 'null' => false, 'default' => 'draft'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('opname_number', false, true, 'opname_number');
        $this->forge->addKey('opname_date', false, false, 'opname_date');
        $this->forge->addKey('status', false, false, 'status');
        $this->forge->createTable('stock_opnames', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: stock_opname_items
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'stock_opname_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'batch_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'system_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => false, 'default' => '0.000'],
            'counted_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => true, 'default' => null],
            'difference_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => true, 'default' => null],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey(['stock_opname_id', 'batch_id'], false, true, 'stock_opname_id_batch_id');
        $this->forge->addKey('stock_opname_id', false, false, 'stock_opname_id');
        $this->forge->addKey('product_id', false, false, 'product_id');
        $this->forge->addKey('batch_id', false, false, 'batch_id');
        $this->forge->addForeignKey('batch_id', 'product_batches', 'id', 'CASCADE', 'RESTRICT', 'stock_opname_items_batch_id_foreign');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'RESTRICT', 'stock_opname_items_product_id_foreign');
        $this->forge->addForeignKey('stock_opname_id', 'stock_opnames', 'id', 'CASCADE', 'CASCADE', 'stock_opname_items_stock_opname_id_foreign');
        $this->forge->createTable('stock_opname_items', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // Table: supplier_payments
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false, 'auto_increment' => true],
            'payment_number' => ['type' => 'VARCHAR', 'constraint' => '40', 'null' => false],
            'supplier_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'purchase_order_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'payment_date' => ['type' => 'DATE', 'null' => false],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => '0.00'],
            'payment_method' => ['type' => 'VARCHAR', 'constraint' => '30', 'null' => false],
            'reference_number' => ['type' => 'VARCHAR', 'constraint' => '100', 'null' => true, 'default' => null],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true, false);
        $this->forge->addKey('payment_number', false, true, 'payment_number');
        $this->forge->addKey('supplier_id', false, false, 'supplier_id');
        $this->forge->addKey('purchase_order_id', false, false, 'purchase_order_id');
        $this->forge->addKey('payment_date', false, false, 'payment_date');
        $this->forge->addForeignKey('purchase_order_id', 'purchase_orders', 'id', 'CASCADE', 'RESTRICT', 'supplier_payments_purchase_order_id_foreign');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'CASCADE', 'RESTRICT', 'supplier_payments_supplier_id_foreign');
        $this->forge->createTable('supplier_payments', false, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);
    }

    public function down()
    {
        $this->forge->dropTable('supplier_payments', true);
        $this->forge->dropTable('stock_opname_items', true);
        $this->forge->dropTable('stock_opnames', true);
        $this->forge->dropTable('stock_movements', true);
        $this->forge->dropTable('sale_items', true);
        $this->forge->dropTable('sales_return_items', true);
        $this->forge->dropTable('sales_returns', true);
        $this->forge->dropTable('sales', true);
        $this->forge->dropTable('purchase_return_items', true);
        $this->forge->dropTable('purchase_returns', true);
        $this->forge->dropTable('expenses', true);
        $this->forge->dropTable('delivery_items', true);
        $this->forge->dropTable('distribution_items', true);
        $this->forge->dropTable('product_batches', true);
        $this->forge->dropTable('goods_receipt_items', true);
        $this->forge->dropTable('purchase_order_items', true);
        $this->forge->dropTable('products', true);
        $this->forge->dropTable('units', true);
        $this->forge->dropTable('goods_receipts', true);
        $this->forge->dropTable('purchase_orders', true);
        $this->forge->dropTable('suppliers', true);
        $this->forge->dropTable('deliveries', true);
        $this->forge->dropTable('customer_payments', true);
        $this->forge->dropTable('distribution_orders', true);
        $this->forge->dropTable('customers', true);
        $this->forge->dropTable('categories', true);
        $this->forge->dropTable('cash_transactions', true);
    }
}
