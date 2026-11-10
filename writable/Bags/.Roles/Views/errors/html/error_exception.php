<?php
$applicationFrame = null;

foreach ($trace ?? [] as $frame) {
    $frameFile = $frame['file'] ?? '';

    if (
        $frameFile !== ''
        && (
            str_starts_with(
                $frameFile,
                rtrim(APPPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            )
            || str_starts_with(
                $frameFile,
                ROOTPATH . 'modules' . DIRECTORY_SEPARATOR
            )
        )
    ) {
        $applicationFrame = $frame;
        break;
    }
}
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ACN ERP — Exception</title>
    <style>
        body {
            margin: 0;
            padding: 32px;
            background: #f1f5f9;
            color: #1e293b;
            font: 14px/1.6 Arial, sans-serif;
        }

        main {
            max-width: 1100px;
            margin: auto;
        }

        section {
            background: #fff;
            padding: 22px;
            margin-bottom: 18px;
            border: 1px solid #dbe2ea;
            border-radius: 8px;
            overflow-wrap: anywhere;
        }

        h1 {
            color: #b91c1c;
        }

        h2 {
            font-size: 17px;
        }

        code,
        pre {
            font-family: Consolas, monospace;
        }

        pre {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .path {
            color: #075985;
        }

        .line {
            color: #b91c1c;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <main>
        <h1>ACN ERP — Application Exception</h1>

        <section>
            <h2>Detail Error</h2>
            <p><strong>Jenis:</strong> <?= esc($type ?? 'Throwable') ?></p>
            <p><strong>Kode:</strong> <?= esc((string) ($code ?? 0)) ?></p>
            <p><strong>Pesan:</strong></p>
            <pre><?= esc($message ?? 'Unknown error') ?></pre>
        </section>

        <section>
            <h2>Lokasi Exception Asli</h2>
            <p class="path"><?= esc($file ?? 'Unknown file') ?></p>
            <p>Baris: <span class="line"><?= esc((string) ($line ?? 0)) ?></span></p>
        </section>

        <section>
            <h2>Lokasi Kode Aplikasi Pertama di Stack Trace</h2>

            <?php if ($applicationFrame !== null): ?>
                <p class="path"><?= esc($applicationFrame['file'] ?? '') ?></p>
                <p>Baris: <span class="line"><?= esc((string) ($applicationFrame['line'] ?? 0)) ?></span></p>
                <p>Fungsi: <?= esc(($applicationFrame['class'] ?? '') . ($applicationFrame['type'] ?? '') . ($applicationFrame['function'] ?? '')) ?></p>
            <?php else: ?>
                <p>Tidak ditemukan frame dari direktori aplikasi atau modul.</p>
            <?php endif ?>
        </section>

        <section>
            <h2>Stack Trace</h2>
            <?php foreach ($trace ?? [] as $index => $frame): ?>
                <pre>#<?= esc((string) $index) ?>
<?= esc(($frame['file'] ?? '[internal]') . ':' . ($frame['line'] ?? '?')) ?>
<?= esc(($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '')) ?></pre>
            <?php endforeach ?>
        </section>
    </main>
</body>

</html>
