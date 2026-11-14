<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixPharmacySchemaIssues extends Migration
{
    protected $DBGroup = 'pharmacy';

    public function up()
    {
        // Fix 1: sales.collateral_note VARCHAR tanpa constraint
        $this->forge->modifyColumn('sales', [
            'collateral_note' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
                'default'    => null,
            ],
        ]);

        // Fix 2: product_batches.status default 'available'
        $this->forge->modifyColumn('product_batches', [
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'null'       => false,
                'default'    => 'available',
            ],
        ]);

        // Fix 3: product_batches.goods_receipt_item_id nullable
        $this->forge->modifyColumn('product_batches', [
            'goods_receipt_item_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
            ],
        ]);

        // Fix 4: sales_returns.customer_id nullable
        $this->forge->modifyColumn('sales_returns', [
            'customer_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
            ],
        ]);

        // Fix 5: composite index sales (status, id)
        $this->db->query('ALTER TABLE sales ADD INDEX status_id (status, id)');

        // Fix 6: composite index sale_items (sale_id, id)
        $this->db->query('ALTER TABLE sale_items ADD INDEX sale_id_id (sale_id, id)');

        // Fix 7: Update existing batches 'active' → 'available'
        $this->db->query("UPDATE product_batches SET status = 'available' WHERE status = 'active'");
    }

    public function down()
    {
        $this->db->query('DROP INDEX status_id ON sales');
        $this->db->query('DROP INDEX sale_id_id ON sale_items');

        $this->forge->modifyColumn('sales', [
            'collateral_note' => ['type' => 'TEXT', 'null' => true],
        ]);

        $this->forge->modifyColumn('product_batches', [
            'status' => ['type' => 'VARCHAR', 'constraint' => '20', 'null' => false, 'default' => 'active'],
        ]);

        $this->forge->modifyColumn('product_batches', [
            'goods_receipt_item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
        ]);

        $this->forge->modifyColumn('sales_returns', [
            'customer_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
        ]);
    }
}
