<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPharmacyTransactionModes extends Migration
{
    protected $DBGroup = 'pharmacy';

    public function up()
    {
        // Pembelian langsung atau melalui pengiriman.
        $this->forge->addColumn('purchase_orders', [
            'purchase_mode' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'delivery',
                'after'      => 'supplier_id',
            ],
        ]);

        // Pembayaran pelanggan dapat mengacu pada POS
        // atau pesanan distribusi.
        $this->forge->addColumn('customer_payments', [
            'sale_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'customer_id',
            ],
        ]);

        $this->forge->addKey(
            'sale_id',
            false,
            false,
            'customer_payments'
        );

        $this->forge->addForeignKey(
            'sale_id',
            'sales',
            'id',
            'CASCADE',
            'RESTRICT',
            'fk_customer_payments_sale'
        );

        // Retur dapat berasal dari POS atau delivery.
        $this->forge->modifyColumn('sales_returns', [
            'delivery_id' => [
                'name'     => 'delivery_id',
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
        ]);

        $this->forge->addColumn('sales_returns', [
            'sale_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'customer_id',
            ],
        ]);

        $this->forge->addKey(
            'sale_id',
            false,
            false,
            'sales_returns'
        );

        $this->forge->addForeignKey(
            'sale_id',
            'sales',
            'id',
            'CASCADE',
            'RESTRICT',
            'fk_sales_returns_sale'
        );

        // Detail retur dapat berasal dari item POS atau delivery.
        $this->forge->modifyColumn('sales_return_items', [
            'delivery_item_id' => [
                'name'     => 'delivery_item_id',
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
        ]);

        $this->forge->addColumn('sales_return_items', [
            'sale_item_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'sales_return_id',
            ],
        ]);

        $this->forge->addKey(
            'sale_item_id',
            false,
            false,
            'sales_return_items'
        );

        $this->forge->addForeignKey(
            'sale_item_id',
            'sale_items',
            'id',
            'CASCADE',
            'RESTRICT',
            'fk_sales_return_items_sale_item'
        );
    }

    public function down()
    {
        $this->forge->dropForeignKey(
            'sales_return_items',
            'fk_sales_return_items_sale_item'
        );

        $this->forge->dropColumn(
            'sales_return_items',
            'sale_item_id'
        );

        $this->forge->modifyColumn('sales_return_items', [
            'delivery_item_id' => [
                'name'     => 'delivery_item_id',
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => false,
            ],
        ]);

        $this->forge->dropForeignKey(
            'sales_returns',
            'fk_sales_returns_sale'
        );

        $this->forge->dropColumn(
            'sales_returns',
            'sale_id'
        );

        $this->forge->modifyColumn('sales_returns', [
            'delivery_id' => [
                'name'     => 'delivery_id',
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => false,
            ],
        ]);

        $this->forge->dropForeignKey(
            'customer_payments',
            'fk_customer_payments_sale'
        );

        $this->forge->dropColumn(
            'customer_payments',
            'sale_id'
        );

        $this->forge->dropColumn(
            'purchase_orders',
            'purchase_mode'
        );
    }
}
