<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Kartu Stok</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #333;
        }

        h2,
        h3 {
            text-align: center;
            margin: 3px 0;
        }

        .meta {
            text-align: center;
            margin: 8px 0 15px;
            color: #555;
        }

        .summary {
            width: 45%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .summary td {
            border: 1px solid #ccc;
            padding: 5px;
        }

        table.detail {
            width: 100%;
            border-collapse: collapse;
        }

        .detail th,
        .detail td {
            border: 1px solid #ccc;
            padding: 5px;
        }

        .detail th {
            background: #17365d;
            color: white;
            text-align: center;
        }

        .number {
            text-align: right;
        }

        .footer {
            margin-top: 15px;
            text-align: right;
            font-size: 8px;
        }
    </style>
</head>

<body>
    <h2>LAPORAN KARTU STOK</h2>
    <h3>PHARMACY MANAGEMENT SYSTEM</h3>

    <div class="meta">
        Produk: <?= esc($report['product']['name']) ?>
        | SKU: <?= esc($report['product']['sku']) ?>
        <br>
        Periode:
        <?= esc($report['filters']['date_from'] ?? '-') ?>
        s.d.
        <?= esc($report['filters']['date_to'] ?? '-') ?>
        <br>
        Dicetak: <?= esc($generatedAt) ?>
    </div>

    <table class="summary">
        <tr>
            <td>Saldo Awal</td>
            <td class="number">
                <?= number_format((float) $report['summary']['opening_stock'], 3) ?>
            </td>
        </tr>
        <tr>
            <td>Total Stok Masuk</td>
            <td class="number">
                <?= number_format((float) $report['summary']['total_in'], 3) ?>
            </td>
        </tr>
        <tr>
            <td>Total Stok Keluar</td>
            <td class="number">
                <?= number_format((float) $report['summary']['total_out'], 3) ?>
            </td>
        </tr>
        <tr>
            <td>Saldo Akhir</td>
            <td class="number">
                <?= number_format((float) $report['summary']['closing_stock'], 3) ?>
            </td>
        </tr>
    </table>

    <table class="detail">
        <thead>
            <tr>
                <?php foreach ($columns as $column): ?>
                    <th><?= esc($column['label']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>

        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="<?= count($columns) ?>" style="text-align:center">
                        Tidak ada pergerakan stok pada periode ini.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($row as $index => $value): ?>
                            <td class="<?= in_array($index, [6, 7, 8, 9], true) ? 'number' : '' ?>">
                                <?= esc((string) $value) ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        Pharmacy Management System
    </div>
</body>

</html>
