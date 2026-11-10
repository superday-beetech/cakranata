<!DOCTYPE html>
<html lang="en" dir="ltr" data-bs-theme="light" data-color-theme="Blue_Theme" data-layout="vertical" data-boxed-layout="boxed" data-card="shadow">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="<?= esc(csrf_hash(), 'attr') ?>">
    <title>ACN ERP</title>
    <link href="<?= base_url(); ?>themes/adminmart/plugins/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= base_url(); ?>themes/adminmart/plugins/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="<?= base_url(); ?>themes/adminmart/plugins/icheck-bootstrap/icheck-bootstrap.min.css" rel="stylesheet">
    <link href="<?= base_url(); ?>themes/adminmart/plugins/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <link href="<?= base_url(); ?>themes/adminmart/css/styles.css" rel="stylesheet">
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
    <script>
        /* window.APP_CONFIG = {
            baseURL: <?= json_encode(base_url()) ?>,
            siteURL: <?= json_encode(site_url()) ?>,
        }; */

        const config = (window.ACN_API_CONFIG ??= {});
        config.bearer ??= {};
        config.bearer.enabled ??= false;
        config.bearer.getToken ??= () => null;
        config.timeout ??= 30000;
        const backendOrigin = "<?= site_url() ?>";

        config.allowedOrigins ??= [];

        if (!config.allowedOrigins.includes(backendOrigin)) {
            config.allowedOrigins.push(backendOrigin);
        }
    </script>

</head>

<body>
    <div id="erp-app"></div>

    <script src="<?= base_url(); ?>themes/adminmart/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?= base_url(); ?>themes/adminmart/plugins/simplebar/js/simplebar.min.js"></script>
    <script src="<?= base_url(); ?>themes/adminmart/js/theme.js"></script>
    <script src="<?= base_url(); ?>themes/adminmart/js/api.js"></script>
    <script src="<?= base_url(); ?>themes/adminmart/js/app.min.js"></script>
    <script src="<?= base_url(); ?>themes/adminmart/js/sidebarmenu.js"></script>
    <script src="<?= base_url(); ?>themes/adminmart/js/superday.js"></script>
    <script src="<?= base_url(); ?>themes/adminmart/plugins/sweetalert2/sweetalert2.min.js"></script>
    <script src="<?= base_url(); ?>themes/adminmart/plugins/Numeral-js/min/numeral.min.js"></script>
    <?= vite_scripts('resources/js/erp/main.js') ?>
</body>

</html>
