<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use App\Core\BackendController;
use App\Services\DeveloperRecoverySessionService;
use App\Services\DeveloperRecoveryTotpService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Traits\Viewable;
use Config\Database;
use Throwable;

class DeveloperRecoveryController extends BackendController
{
    use Viewable;

    private BaseConnection $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    private function currentUserId(): int
    {
        return (int) (auth()->user()->id ?? 0);
    }

    private function isDeveloperAccount(): bool
    {
        $user = auth()->user();
        $userId = (int) ($user->id ?? 0);
        $developerId = (int) env('DEVELOPER_RECOVERY_USER_ID', 0);

        return $user !== null
            && $userId > 0
            && $developerId > 0
            && $userId === $developerId
            && $this->db->table('users')
            ->where('id', $userId)
            ->where('deleted_at', null)
            ->countAllResults() === 1;
    }

    private function guard()
    {
        if (! auth()->loggedIn()) {
            return redirect()->to(site_url('auth/login'));
        }

        if (! $this->isDeveloperAccount()) {
            return $this->response
                ->setStatusCode(403)
                ->setBody('403 Forbidden');
        }

        return null;
    }

    private function pageRedirect(): RedirectResponse
    {
        return redirect()->to(site_url('auth/developer-recovery'));
    }

    private function otpConfigured(int $userId): bool
    {
        return $this->db->table('developer_recovery_totp')
            ->where('user_id', $userId)
            ->where('confirmed_at IS NOT NULL', null, false)
            ->where('secret_encrypted IS NOT NULL', null, false)
            ->countAllResults() > 0;
    }

    public function index()
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $user = auth()->user();
        $userId = $this->currentUserId();

        $activeRecovery = $this->db->table('developer_recovery_sessions')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('session_fingerprint', hash('sha256', session('session')->getId()))
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->where('revoked_at', null)
            ->get()
            ->getRowArray();

        return $this->view('Modules\Auth\Views\developer_recovery', [
            'otpConfigured' => $this->otpConfigured($userId),
            'setupSecret' => session()->getFlashdata('recovery_setup_secret'),
            'setupUri' => session()->getFlashdata('recovery_setup_uri'),
            'recoveryActive' => $activeRecovery !== null,
            'recoveryExpiresAt' => $activeRecovery['expires_at'] ?? null,
        ]);
    }

    public function setupOtp(): RedirectResponse
    {
        if ($response = $this->guard()) {
            return $response;
        }

        try {
            $userId = $this->currentUserId();

            if ($this->otpConfigured($userId)) {
                return $this->pageRedirect()
                    ->with('error', 'OTP sudah dikonfigurasi.');
            }

            $otp = new DeveloperRecoveryTotpService();
            $setup = $otp->beginSetup($userId);

            session()->setFlashdata([
                'recovery_setup_secret' => $setup['secret'],
                'recovery_setup_uri' => $setup['otpauth_uri'],
            ]);

            return $this->pageRedirect()
                ->with('success', 'Secret OTP dibuat. Pindai QR atau masukkan secret ke aplikasi authenticator.');
        } catch (Throwable $e) {
            log_message('error', 'Developer Recovery setup failed: {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->pageRedirect()
                ->with('error', 'Setup OTP gagal. Periksa log aplikasi.');
        }
    }

    public function verifyOtpSetup(): RedirectResponse
    {
        if ($response = $this->guard()) {
            return $response;
        }

        try {
            $code = trim((string) $this->request->getPost('otp'));

            if (! preg_match('/^\d{6}$/', $code)) {
                return $this->pageRedirect()
                    ->with('error', 'Masukkan kode OTP enam digit.');
            }

            $otp = new DeveloperRecoveryTotpService();

            if (! $otp->confirmSetup($this->currentUserId(), $code)) {
                return $this->pageRedirect()
                    ->with('error', 'Kode OTP tidak valid. Coba kode terbaru.');
            }

            return $this->pageRedirect()
                ->with('success', 'OTP berhasil dikonfirmasi.');
        } catch (Throwable $e) {
            log_message('error', 'Developer Recovery OTP confirmation failed.');

            return $this->pageRedirect()
                ->with('error', 'Verifikasi OTP gagal. Periksa log aplikasi.');
        }
    }

    public function activate(): RedirectResponse
    {
        if ($response = $this->guard()) {
            return $response;
        }

        try {
            $code = trim((string) $this->request->getPost('otp'));

            if (! preg_match('/^\d{6}$/', $code)) {
                return $this->pageRedirect()
                    ->with('error', 'Masukkan kode OTP enam digit.');
            }

            $recovery = new DeveloperRecoverySessionService();
            $recoveryId = $recovery->activate($code);

            $row = $this->db->table('developer_recovery_sessions')
                ->select('expires_at')
                ->where('id', $recoveryId)
                ->get()
                ->getRowArray();

            return $this->pageRedirect()
                ->with(
                    'success',
                    'Developer Recovery aktif sampai '
                        . ($row['expires_at'] ?? 'batas waktu yang ditetapkan')
                );
        } catch (Throwable $e) {
            log_message('warning', 'Developer Recovery activation failed: {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->pageRedirect()
                ->with('error', $e->getMessage());
        }
    }

    public function deactivate(): RedirectResponse
    {
        if ($response = $this->guard()) {
            return $response;
        }

        try {
            $recovery = new DeveloperRecoverySessionService();
            $revoked = $recovery->revokeCurrent();

            return $this->pageRedirect()->with(
                $revoked ? 'success' : 'error',
                $revoked
                    ? 'Developer Recovery telah dinonaktifkan.'
                    : 'Tidak ada sesi recovery aktif pada browser ini.'
            );
        } catch (Throwable $e) {
            log_message('error', 'Developer Recovery deactivation failed.');

            return $this->pageRedirect()
                ->with('error', 'Penonaktifan gagal. Periksa log aplikasi.');
        }
    }
}
