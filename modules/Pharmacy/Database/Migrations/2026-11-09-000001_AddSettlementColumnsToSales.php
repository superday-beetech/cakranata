<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menyamakan skema `sales` dengan yang dipakai POS.
 * Idempoten: kolom yang sudah ada (mis. dibuat manual lewat phpMyAdmin) dilewati.
 */
class AddSettlementColumnsToSales extends Migration
{
    protected $DBGroup = 'pharmacy';

    public function up()
    {
        $columns = [
            'amount_received' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
                'after'      => 'paid_amount',
            ],
            'change_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'default'    => 0,
                'after'      => 'amount_received',
            ],
            'settlement_mode' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'paid',
                'after'      => 'payment_status',
            ],
            'due_date' => [
                'type'  => 'DATE',
                'null'  => true,
                'after' => 'reference_number',
            ],
        ];

        $missing = [];

        foreach ($columns as $name => $definition) {
            if (!$this->db->fieldExists($name, 'sales')) {
                $missing[$name] = $definition;
            }
        }

        if ($missing !== []) {
            $this->forge->addColumn('sales', $missing);
        }

        // Index untuk laporan piutang jatuh tempo.
        $hasIndex = $this->db
            ->query("SHOW INDEX FROM sales WHERE Key_name = 'idx_sales_due_date'")
            ->getNumRows() > 0;

        if (!$hasIndex) {
            $this->db->query('CREATE INDEX idx_sales_due_date ON sales (due_date)');
        }
    }

    public function down()
    {
        if ($this->db->query("SHOW INDEX FROM sales WHERE Key_name = 'idx_sales_due_date'")->getNumRows() > 0) {
            $this->db->query('DROP INDEX idx_sales_due_date ON sales');
        }

        foreach (['due_date', 'settlement_mode', 'change_amount', 'amount_received'] as $name) {
            if ($this->db->fieldExists($name, 'sales')) {
                $this->forge->dropColumn('sales', $name);
            }
        }
    }
}
