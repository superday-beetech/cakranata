<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use App\Core\BackendController;
use App\Libraries\Uploader;
use CodeIgniter\HTTP\RedirectResponse;

class ProfileController extends BackendController
{
    public function uploadPhoto(): RedirectResponse
    {
        $file = $this->request->getFile('photo');

        if ($file === null) {
            return redirect()->back()
                ->with('error', 'File foto tidak ditemukan.');
        }

        $uploader = new Uploader();

        $result = $uploader->uploadWithThumbnail(
            file: $file,
            folder: 'profile',
            allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
            thumbWidth: 150
        );

        if (! $result['status']) {
            return redirect()->back()
                ->with('error', $result['message']);
        }

        // ID file Storage, bukan nama file fisik.
        $fileId = $result['file_id'];

        // Langkah berikutnya:
        // Hubungkan file ke pengguna melalui StorageFacade,
        // lalu tetapkan sebagai foto profil aktif.

        return redirect()->back()->with(
            'success',
            'Foto berhasil diunggah. File ID: ' . $fileId
        );
    }
}