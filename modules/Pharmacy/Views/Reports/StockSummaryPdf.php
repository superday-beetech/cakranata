<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Ringkasan Persediaan</title>

    <style>
        @page {
            margin: 25px 25px 40px 25px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #263238;
        }

        .header {
            border-bottom: 2px solid #17365D;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .header h1 {
            margin: 0;
            font-size: 17px;
            color: #17365D;
        }

        .header .subtitle {
            margin-top: 4px;
            color: #607D8B;
            font-size: 9px;
        }

        .meta {
            margin-bottom: 12px;
            line-height: 1.7;
        }

        .section-title {
            font-size: 10px;
            font-weight: bold;
            color: #17365D;
            margin: 12px 0 6px;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .summary td {
            border: 1px solid #D9E2F3;
            padding: 6px;
        }

        .summary .label {
            background: #F0F4FA;
            font-weight: bold;
            width: 16%;
        }

        .summary .value {
            font-weight: bold;
            width: 9%;
        }

        .detail {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .detail th {
            background: #17365D;
            color: #FFFFFF;
            border: 1px solid #17365D;
            padding: 6px 3px;
            font-size: 7px;
            text-align: center;
        }

        .detail td {
            border: 1px solid #D9D9D9;
            padding: 5px 3px;
            font-size: 7px;
        }

        .detail tbody tr:nth-child(even) {
            background: #F5F7FA;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .note {
            margin-top: 12px;
            padding: 7px;
            background: #F0F4FA;
            border-left: 3px solid #4472C4;
            line-height: 1.5;
        }

        .footer {
            position: fixed;
            bottom: -25px;
            left: 0;
            right: 0;
            border-top: 1px solid #D9D9D9;
            padding-top: 5px;
            color: #607D8B;
            font-size: 7px;
        }

        .page-number:after {
            content: counter(page);
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>LAPORAN RINGKASAN PERSEDIAAN</h1>
        <div class="subtitle">
            PHARMACY MANAGEMENT SYSTEM
        </div>
    </div>

    <div class="meta">
        <strong>Periode:</strong>
        <?= esc($report['period']['date_from']) ?>
        s.d.
        <?= esc($report['period']['date_to']) ?>
        <br>

        <strong>Tanggal Cetak:</strong>
        <?= esc($generatedAt) ?>
    </div>

    <div class="section-title">
        RINGKASAN PERSEDIAAN
    </div>

    <table class="summary">
        <tr>
            <td class="label">Jumlah Produk</td>
            <td class="value">
                <?= number_format($report['summary']['product_count']) ?>
            </td>

            <td class="label">Saldo Awal</td>
            <td class="value">
                <?= number_format($report['summary']['opening_stock'], 3, ',', '.') ?>
            </td>

            <td class="label">Stok Masuk</td>
            <td class="value">
                <?= number_format($report['summary']['total_in'], 3, ',', '.') ?>
            </td>
        </tr>

        <tr>
            <td class="label">Stok Keluar</td>
            <td class="value">
                <?= number_format($report['summary']['total_out'], 3, ',', '.') ?>
            </td>

            <td class="label">Saldo Akhir</td>
            <td class="value">
                <?= number_format($report['summary']['closing_stock'], 3, ',', '.') ?>
            </td>

            <td class="label">Nilai Persediaan</td>
            <td class="value">
                Rp <?= number_format($report['summary']['stock_value'], 0, ',', '.') ?>
            </td>
        </tr>

        <tr>
            <td class="label">Batch Kedaluwarsa</td>
            <td class="value">
                <?= number_format($report['summary']['expired_batch_count']) ?>
            </td>

            <td class="label">Segera Kedaluwarsa</td>
            <td class="value" colspan="3">
                <?= number_format($report['summary']['expiring_batch_count']) ?>
            </td>
        </tr>
    </table>

    <div class="section-title">
        DETAIL PERSEDIAAN PER PRODUK
    </div>

    <table class="detail">
        <thead>
            <tr>
                <?php foreach ($columns as $column): ?>
                    <th><?= esc($column['label']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($row as $index => $value): ?>
                        <td class="<?= in_array($index, [0, 11, 12], true) ? 'center' : (in_array($index, [5, 6, 7, 8, 9, 10], true) ? 'right' : '') ?>">
                            <?php if (in_array($index, [9, 10], true)): ?>
                                Rp <?= number_format((float) $value, 0, ',', '.') ?>
                            <?php elseif (in_array($index, [5, 6, 7, 8], true)): ?>
                                <?= number_format((float) $value, 3, ',', '.') ?>
                            <?php else: ?>
                                <?= esc((string) $value) ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="<?= count($columns) ?>" class="center">
                        Tidak ada data persediaan pada periode ini.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="note">
        <strong>Catatan:</strong>
        Nilai persediaan dihitung berdasarkan saldo akhir dikalikan
        harga beli produk. Jumlah batch kedaluwarsa dan segera
        kedaluwarsa merupakan informasi batch dengan stok tersisa
        pada saat laporan dihitung.
    </div>

    <div class="footer">
        Pharmacy Management System
        <span style="float:right">
            Halaman <span class="page-number"></span>
        </span>
    </div>

</body>

</html>
