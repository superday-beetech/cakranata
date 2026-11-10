<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use App\Core\BackendController;
use App\Services\DeveloperRecoveryService;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

class DeveloperRecoveryController extends BackendController
{
    public function index()
    {
        if (! auth()->loggedIn()) {
            return redirect()->to(site_url('auth/login'));
        }

        return $this->render('developer_recovery');
    }

    public function activate(): RedirectResponse
    {
        if (! auth()->loggedIn()) {
            return redirect()->to(site_url('auth/login'));
        }

        $otp = trim((string) $this->request->getPost('otp'));

        try {
            $recovery = new DeveloperRecoveryService();
            $result = $recovery->activate($otp, $this->request);

            return redirect()
                ->to(site_url('auth/developer-recovery'))
                ->with('success', 'Developer Recovery aktif sampai ' . $result['expires_at']);
        } catch (Throwable $e) {
            log_message('warning', 'Developer Recovery activation rejected: {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->to(site_url('auth/developer-recovery'))
                ->with('error', 'Aktivasi gagal. Periksa akun, OTP, dan konfigurasi recovery.');
        }
    }

    public function deactivate(): RedirectResponse
    {
        if (! auth()->loggedIn()) {
            return redirect()->to(site_url('auth/login'));
        }

        try {
            $recovery = new DeveloperRecoveryService();
            $recovery->deactivate($this->request);

            return redirect()
                ->to(site_url('auth/developer-recovery'))
                ->with('success', 'Developer Recovery dinonaktifkan.');
        } catch (Throwable $e) {
            log_message('error', 'Developer Recovery deactivation failed: {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->to(site_url('auth/developer-recovery'))
                ->with('error', 'Recovery belum dapat dinonaktifkan. Periksa log aplikasi.');
        }
    }
}
