<?php

declare(strict_types=1);

/**
 * @var array $entry
 * @var array $manifest
 */

$baseUrl = rtrim(base_url('acn'), '/') . '/';

$preloads = [];
$stylesheets = [];

/**
 * Collect static imports recursively.
 */
$collectImports = function (
    array $chunk,
    array &$visited
) use (&$collectImports, &$preloads, &$stylesheets, $manifest): void {
    foreach ($chunk['imports'] ?? [] as $import) {
        if (isset($visited[$import])) {
            continue;
        }

        $visited[$import] = true;
        $dependency = $manifest[$import] ?? null;

        if (! is_array($dependency)) {
            continue;
        }

        if (! empty($dependency['file'])) {
            $preloads[] = $dependency['file'];
        }

        foreach ($dependency['css'] ?? [] as $css) {
            $stylesheets[$css] = true;
        }

        $collectImports($dependency, $visited);
    }
};

$visited = [];
$preloads = [];

$collectImports($entry, $visited);

foreach ($entry['css'] ?? [] as $css) {
    $stylesheets[$css] = true;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="api-base-url" content="<?= esc(site_url('api/v1')) ?>">
    <script>
        var userSettings = {
            Layout: "vertical", // vertical | horizontal
            SidebarType: "full", // full | mini-sidebar
            BoxedLayout: true, // true | false
            Direction: "ltr", // ltr | rtl
            Theme: "light", // light | dark
            ColorTheme: "Blue_Theme", // Blue_Theme | Aqua_Theme | Purple_Theme | Green_Theme | Cyan_Theme | Orange_Theme
            cardBorder: false, // true | false
        };
    </script>
    <title>ACN ERP</title>

    <?php foreach (array_keys($stylesheets) as $css): ?>
        <link
            rel="stylesheet"
            href="<?= esc($baseUrl . $css) ?>">
    <?php endforeach; ?>

    <?php foreach ($preloads as $file): ?>
        <link
            rel="modulepreload"
            crossorigin
            href="<?= esc($baseUrl . $file) ?>">
    <?php endforeach; ?>
</head>

<body>
    <div id="app"></div>

    <script
        type="module"
        src="<?= esc($baseUrl . $entry['file']) ?>"></script>
</body>

</html>
