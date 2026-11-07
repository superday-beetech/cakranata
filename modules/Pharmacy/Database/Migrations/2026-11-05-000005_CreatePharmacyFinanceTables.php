<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePharmacyFinanceTables extends Migration
{
    protected $DBGroup = 'pharmacy';

    public function up()
    {
        // SUPPLIER PAYMENTS
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'payment_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'supplier_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'purchase_order_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'payment_date' => ['type' => 'DATE'],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
            ],
            'payment_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'reference_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('payment_number', false, true);
        $this->forge->addKey('supplier_id');
        $this->forge->addKey('purchase_order_id');
        $this->forge->addKey('payment_date');

        $this->forge->addForeignKey(
            'supplier_id',
            'suppliers',
            'id',
            'CASCADE',
            'RESTRICT'
        );
        $this->forge->addForeignKey(
            'purchase_order_id',
            'purchase_orders',
            'id',
            'CASCADE',
            'RESTRICT'
        );

        $this->forge->createTable('supplier_payments', true);

        // CUSTOMER PAYMENTS
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'payment_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'customer_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'distribution_order_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'payment_date' => ['type' => 'DATE'],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
            ],
            'payment_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'reference_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('payment_number', false, true);
        $this->forge->addKey('customer_id');
        $this->forge->addKey('distribution_order_id');
        $this->forge->addKey('payment_date');

        $this->forge->addForeignKey(
            'customer_id',
            'customers',
            'id',
            'CASCADE',
            'RESTRICT'
        );
        $this->forge->addForeignKey(
            'distribution_order_id',
            'distribution_orders',
            'id',
            'CASCADE',
            'RESTRICT'
        );

        $this->forge->createTable('customer_payments', true);

        // EXPENSES
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'expense_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'expense_date' => ['type' => 'DATE'],
            'category' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
            ],
            'payment_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'reference_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
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
        $this->forge->addKey('expense_number', false, true);
        $this->forge->addKey('expense_date');
        $this->forge->addKey('category');

        $this->forge->createTable('expenses', true);

        // CASH TRANSACTIONS
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'transaction_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'transaction_date' => ['type' => 'DATETIME'],
            'transaction_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'reference_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'reference_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
                'null'       => true,
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
            ],
            'payment_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'created_by' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('transaction_number', false, true);
        $this->forge->addKey('transaction_date');
        $this->forge->addKey('transaction_type');
        $this->forge->addKey(['reference_type', 'reference_id']);

        $this->forge->createTable('cash_transactions', true);
    }

    public function down()
    {
        $this->forge->dropTable('cash_transactions', true);
        $this->forge->dropTable('expenses', true);
        $this->forge->dropTable('customer_payments', true);
        $this->forge->dropTable('supplier_payments', true);
    }
}
