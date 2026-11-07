<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use App\Core\ApiController;
use Modules\Core\Repositories\StorageFileRepository;
use Modules\Core\Services\Storage\StorageFacade;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Services\Storage\FileHandler;
use RuntimeException;

final class StorageController extends ApiController
{
    private StorageFileRepository $files;
    private StorageFacade $storage;

    public function __construct()
    {
        $this->files = new StorageFileRepository();
        $this->storage = service('storage');
    }

    /**
     * Preview file.
     * GET /api/v1/core/storage/{id}/preview
     */
    public function preview(int $id): ResponseInterface
    {
        return $this->serve($id, false);
    }

    /**
     * Download file.
     * GET /api/v1/core/storage/{id}/download
     */
    public function download(int $id): ResponseInterface
    {
        return $this->serve($id, true);
    }

    private function serve(
        int $id,
        bool $download
    ): ResponseInterface {
        if ($id < 1) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'ID file tidak valid.',
                ]);
        }

        $file = $this->files->findActiveById($id);

        if ($file === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'File tidak ditemukan.',
                ]);
        }

        /*
         * PENTING:
         * Tambahkan pemeriksaan otorisasi pengguna dan organisasi
         * di sini sebelum membuka stream.
         *
         * Jangan menganggap file aman hanya karena ID diketahui.
         */

        $key = $file['storage_key'] ?? null;
        $disk = $file['disk'] ?? null;
        $mime = $file['mime_type'] ?? 'application/octet-stream';
        $name = $file['original_name'] ?? 'download';

        if (
            ! is_string($key) || $key === ''
            || ! is_string($disk) || $disk === ''
        ) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Metadata penyimpanan file tidak valid.',
                ]);
        }

        // Preview hanya untuk tipe yang aman ditampilkan inline.
        if (
            ! $download
            && ! in_array($mime, [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/webp',
            ], true)
        ) {
            return $this->response
                ->setStatusCode(415)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Jenis file tidak mendukung preview.',
                ]);
        }

        try {
            $stream = $this->storage->readStream($key, $disk);

            if (! is_resource($stream)) {
                throw new RuntimeException('Stream file tidak valid.');
            }

            $content = stream_get_contents($stream);
            fclose($stream);

            if ($content === false) {
                throw new RuntimeException('Gagal membaca file.');
            }

            $safeName = str_replace(
                ["\r", "\n", '"', '\\'],
                '_',
                basename($name)
            );

            return $this->response
                ->setStatusCode(200)
                ->setHeader('Content-Type', $mime)
                ->setHeader(
                    'Content-Disposition',
                    ($download ? 'attachment' : 'inline')
                        . '; filename="' . $safeName . '"'
                )
                ->setHeader('X-Content-Type-Options', 'nosniff')
                ->setHeader('Cache-Control', 'private, no-store')
                ->setBody($content);
        } catch (\Throwable $e) {
            log_message(
                'error',
                'StorageController gagal membaca file ID {id}: {error}',
                [
                    'id' => $id,
                    'error' => $e->getMessage(),
                ]
            );

            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'File tidak dapat dibaca.',
                ]);
        }
    }

    public function upload(): ResponseInterface
    {
        $userId = (int) session()->get('user_id');

        if ($userId < 1) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Silakan login terlebih dahulu.',
                ]);
        }

        $file = $this->request->getFile('file');

        if ($file === null || ! $file->isValid()) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'File upload tidak valid.',
                ]);
        }

        if ($file->hasMoved()) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'File sudah dipindahkan.',
                ]);
        }

        try {
            $handler = new FileHandler();

            $result = $handler->upload(
                sourcePath: $file->getTempName(),
                originalName: $file->getClientName(),
                uploadedBy: $userId,
                organizationId: null,
                visibility: 'private'
            );

            return $this->response
                ->setStatusCode(201)
                ->setJSON([
                    'status'  => 'success',
                    'message' => 'File berhasil diunggah.',
                    'data'    => $result,
                ]);
        } catch (\Throwable $e) {
            log_message('error', 'Upload storage gagal: {error}', [
                'error' => $e->getMessage(),
            ]);

            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'File gagal diunggah. Periksa format dan ukuran file.',
                ]);
        }
    }
}
