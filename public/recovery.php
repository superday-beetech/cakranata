<?php

declare(strict_types=1);

use CodeIgniter\Boot;
use CodeIgniter\CodeIgniter;
use CodeIgniter\Shield\Entities\User;
use Config\Paths;

if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    http_response_code(503);
    exit('PHP 8.2 atau lebih baru diperlukan.');
}

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

require FCPATH . '../app/Config/Paths.php';

$paths = new Paths();

require $paths->systemDirectory . '/Boot.php';

/**
 * Bootstrap CI4 tanpa menjalankan router utama.
 */
final class DeveloperRecoveryBoot extends Boot
{
    public static ?CodeIgniter $recoveryApp = null;

    protected static function runCodeIgniter(CodeIgniter $app): void
    {
        self::$recoveryApp = $app;
    }
}

DeveloperRecoveryBoot::bootWeb($paths);

$app = DeveloperRecoveryBoot::$recoveryApp;

if ($app === null) {
    http_response_code(500);
    exit('Bootstrap Developer Recovery gagal.');
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

function recoveryEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$keyConfigured = trim((string) env('DEVELOPER_RECOVERY_KEY', ''));
$message = '';
$created = null;

if ($keyConfigured === '') {
    http_response_code(503);
    exit('DEVELOPER_RECOVERY_KEY belum dikonfigurasi di .env.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submittedKey = (string) ($_POST['recovery_key'] ?? '');

    if (
        $submittedKey === ''
        || ! hash_equals($keyConfigured, $submittedKey)
    ) {
        http_response_code(403);
        $message = 'Kunci recovery tidak valid.';
    } else {
        try {
            /*
             * Gunakan provider pengguna Shield.
             * Tidak menulis langsung ke tabel database.
             */
            $users = auth()->getProvider();

            $username = 'recovery_' . bin2hex(random_bytes(5));
            $email = $username . '@localhost.invalid';
            $password = bin2hex(random_bytes(24));

            $user = new User([
                'username' => $username,
                'email'    => $email,
                'active'   => 1,
            ]);

            $user->setPassword($password);

            if (! $users->save($user)) {
                throw new RuntimeException(
                    'Gagal membuat pengguna Shield: '
                        . implode('; ', $users->errors())
                );
            }

            $userId = (int) $users->getInsertID();

            if ($userId <= 0) {
                throw new RuntimeException(
                    'ID pengguna baru tidak ditemukan.'
                );
            }

            $savedUser = $users->findById($userId);

            if ($savedUser === null) {
                throw new RuntimeException(
                    'Pengguna berhasil disimpan, tetapi tidak dapat dibaca.'
                );
            }

            /*
             * Berikan grup melalui API entitas Shield.
             * Nama grup harus sudah ada di database Shield.
             */
            $savedUser->addGroup('superadmin');

            $created = [
                'id'       => $userId,
                'username' => $username,
                'email'    => $email,
                'password' => $password,
            ];

            $message = 'Akun sementara berhasil dibuat.';
        } catch (Throwable $e) {
            log_message(
                'error',
                'Developer Recovery: {message}',
                ['message' => $e->getMessage()]
            );

            http_response_code(500);
            $message = 'Recovery gagal. Periksa writable/logs untuk detail.';
        }
    }
}
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Developer Recovery</title>
</head>

<body>
    <main style="max-width:600px;margin:40px auto;font-family:Arial,sans-serif">
        <h1>Developer Recovery</h1>
        <p>
            Membuat akun Shield sementara melalui bootstrap terpisah.
        </p>

        <?php if ($message !== ''): ?>
            <p><?= recoveryEscape($message) ?></p>
        <?php endif; ?>

        <?php if ($created !== null): ?>
            <h2>Akun sementara</h2>
            <p>Simpan kredensial ini dengan aman.</p>

            <dl>
                <dt>User ID</dt>
                <dd><?= recoveryEscape($created['id']) ?></dd>

                <dt>Username</dt>
                <dd><?= recoveryEscape($created['username']) ?></dd>

                <dt>Email</dt>
                <dd><?= recoveryEscape($created['email']) ?></dd>

                <dt>Password sementara</dt>
                <dd><?= recoveryEscape($created['password']) ?></dd>
            </dl>

            <p>
                Gunakan kredensial tersebut pada halaman login aplikasi
                yang sudah ada. Setelah berhasil masuk, pastikan grup
                superadmin benar-benar memberikan akses yang diperlukan.
            </p>
        <?php else: ?>
            <form method="post">
                <label for="recovery_key">Kunci developer</label>
                <input
                    id="recovery_key"
                    name="recovery_key"
                    type="password"
                    autocomplete="current-password"
                    required
                    style="display:block;width:100%;box-sizing:border-box;padding:10px;margin:8px 0 16px">
                <button type="submit">Buat akun sementara</button>
            </form>
        <?php endif; ?>
    </main>
</body>

</html>
