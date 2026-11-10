<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePharmacyPaymentTables extends Migration
{
    protected $DBGroup = 'pharmacy';

    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'channel' => ['type' => 'VARCHAR', 'constraint' => 30],
            'processing_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'requires_confirmation' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'provider_required' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order' => ['type' => 'INT', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->addKey(['is_active', 'sort_order']);
        $this->forge->createTable('payment_methods', true);

        $now = date('Y-m-d H:i:s');
        $this->db->table('payment_methods')->insertBatch([
            ['code' => 'cash', 'name' => 'Tunai', 'channel' => 'cash', 'processing_type' => 'manual', 'requires_confirmation' => 0, 'provider_required' => 0, 'is_active' => 1, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'bank_transfer', 'name' => 'Transfer Bank', 'channel' => 'bank_transfer', 'processing_type' => 'manual', 'requires_confirmation' => 1, 'provider_required' => 0, 'is_active' => 1, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'qris', 'name' => 'QRIS', 'channel' => 'qris', 'processing_type' => 'gateway', 'requires_confirmation' => 1, 'provider_required' => 1, 'is_active' => 1, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ewallet', 'name' => 'E-Wallet', 'channel' => 'ewallet', 'processing_type' => 'gateway', 'requires_confirmation' => 1, 'provider_required' => 1, 'is_active' => 1, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'virtual_account', 'name' => 'Virtual Account', 'channel' => 'virtual_account', 'processing_type' => 'gateway', 'requires_confirmation' => 1, 'provider_required' => 1, 'is_active' => 1, 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'card', 'name' => 'Kartu Debit/Kredit', 'channel' => 'card', 'processing_type' => 'gateway', 'requires_confirmation' => 1, 'provider_required' => 1, 'is_active' => 1, 'sort_order' => 60, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'other', 'name' => 'Lainnya', 'channel' => 'other', 'processing_type' => 'manual', 'requires_confirmation' => 1, 'provider_required' => 0, 'is_active' => 1, 'sort_order' => 70, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'adapter' => ['type' => 'VARCHAR', 'constraint' => 80],
            'credentials_env' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'config_json' => ['type' => 'JSON', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('payment_providers', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'provider_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'payment_method_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'provider_method_code' => ['type' => 'VARCHAR', 'constraint' => 80],
            'config_json' => ['type' => 'JSON', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['provider_id', 'payment_method_id'], 'uq_provider_payment_method');
        $this->forge->addForeignKey('provider_id', 'payment_providers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('payment_method_id', 'payment_methods', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('payment_provider_methods', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'payment_number' => ['type' => 'VARCHAR', 'constraint' => 50],
            'sale_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'payment_method_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'provider_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending'],
            'processing_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'reference_number' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'external_reference' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'verified_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'paid_at' => ['type' => 'DATETIME', 'null' => true],
            'expires_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('payment_number');
        $this->forge->addUniqueKey('idempotency_key');
        $this->forge->addKey(['sale_id', 'status']);
        $this->forge->addKey('payment_method_id');
        $this->forge->addForeignKey('sale_id', 'sales', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('payment_method_id', 'payment_methods', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('provider_id', 'payment_providers', 'id', 'SET NULL', 'RESTRICT');
        $this->forge->createTable('sale_payments', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'sale_payment_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'provider_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'merchant_reference' => ['type' => 'VARCHAR', 'constraint' => 120],
            'provider_reference' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending'],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'currency' => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'IDR'],
            'payment_url' => ['type' => 'TEXT', 'null' => true],
            'qr_string' => ['type' => 'TEXT', 'null' => true],
            'qr_url' => ['type' => 'TEXT', 'null' => true],
            'request_payload' => ['type' => 'JSON', 'null' => true],
            'response_payload' => ['type' => 'JSON', 'null' => true],
            'expires_at' => ['type' => 'DATETIME', 'null' => true],
            'paid_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('merchant_reference');
        $this->forge->addKey('provider_reference');
        $this->forge->addKey(['sale_payment_id', 'status']);
        $this->forge->addForeignKey('sale_payment_id', 'sale_payments', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('provider_id', 'payment_providers', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('payment_transactions', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'provider_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'event_id' => ['type' => 'VARCHAR', 'constraint' => 160],
            'event_type' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'payload' => ['type' => 'JSON'],
            'signature_valid' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'processing_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'received'],
            'error_message' => ['type' => 'TEXT', 'null' => true],
            'received_at' => ['type' => 'DATETIME'],
            'processed_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['provider_id', 'event_id'], 'uq_provider_event');
        $this->forge->addKey(['processing_status', 'received_at']);
        $this->forge->addForeignKey('provider_id', 'payment_providers', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('payment_webhook_events', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'sale_payment_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'verification_status' => ['type' => 'VARCHAR', 'constraint' => 20],
            'reference_number' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'verified_by' => ['type' => 'BIGINT', 'unsigned' => true],
            'verified_at' => ['type' => 'DATETIME'],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['sale_payment_id', 'verified_at']);
        $this->forge->addForeignKey('sale_payment_id', 'sale_payments', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('payment_verifications', true);
    }

    public function down()
    {
        $this->forge->dropTable('payment_verifications', true);
        $this->forge->dropTable('payment_webhook_events', true);
        $this->forge->dropTable('payment_transactions', true);
        $this->forge->dropTable('sale_payments', true);
        $this->forge->dropTable('payment_provider_methods', true);
        $this->forge->dropTable('payment_providers', true);
        $this->forge->dropTable('payment_methods', true);
    }
}
