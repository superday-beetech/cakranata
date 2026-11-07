<?php

declare(strict_types=1);

namespace Modules\Backend\Controllers;

use App\Core\BackendController;
use CodeIgniter\Exceptions\PageNotFoundException;
use Modules\Backend\Repositories\EmailTemplateRepository;
use Throwable;

class EmailTemplatesController extends BackendController
{
    private EmailTemplateRepository $repository;

    public function __construct()
    {
        $this->repository = new EmailTemplateRepository();
        helper(['form', 'url']);
    }


    private function authorize(string $permission): void
    {
        if (! access($permission)) {
            throw new \RuntimeException(
                'Anda tidak memiliki izin untuk mengakses halaman ini.',
                403
            );
        }
    }

    /**
     * Daftar template.
     */
    public function index()
    {
        $this->authorize('backend.email_templates.view');

        $category = trim((string) $this->request->getGet('category'));
        $activeValue = $this->request->getGet('active');

        $active = in_array((string) $activeValue, ['0', '1'], true)
            ? (int) $activeValue
            : null;

        return $this->render('email_templates\index', [
            'title'      => 'Template Email',
            'templates'  => $this->repository->findAll(
                $category !== '' ? $category : null,
                $active
            ),
            'category'   => $category,
            'active'     => $active,
            'canManage'  => access(
                'backend.email_templates.manage'
            ) ?? false,
        ]);
    }

    /**
     * Form tambah.
     */
    public function new()
    {
        $this->authorize('backend.email_templates.manage');

        return $this->renderForm();
    }

    /**
     * Form edit.
     */
    public function edit(int $id)
    {
        $this->authorize('backend.email_templates.manage');

        $template = $this->repository->findById($id);

        if ($template === null) {
            throw PageNotFoundException::forPageNotFound(
                'Template email tidak ditemukan.'
            );
        }

        return $this->renderForm($template);
    }

    /**
     * Simpan template baru.
     */
    public function create()
    {
        $this->authorize('backend.email_templates.manage');

        $data = $this->validatedData();

        if ($data === null) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        if ($this->repository->codeExists($data['code'])) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Kode template sudah digunakan.');
        }

        $userId = auth()->user()?->id;

        $data['created_by'] = $userId !== null ? (int) $userId : null;
        $data['updated_by'] = $data['created_by'];

        try {
            $id = $this->repository->create($data);

            if ($id === false) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Template gagal disimpan.');
            }

            return redirect()->to(
                site_url('backend/email-templates')
            )->with('success', 'Template email berhasil ditambahkan.');
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat template email: {error}', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan saat menyimpan template.');
        }
    }

    /**
     * Perbarui template.
     */
    public function update(int $id)
    {
        $this->authorize('backend.email_templates.manage');

        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw PageNotFoundException::forPageNotFound(
                'Template email tidak ditemukan.'
            );
        }

        $data = $this->validatedData();

        if ($data === null) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        // Kode adalah identitas template dan tidak diubah saat edit.
        unset($data['code']);

        $userId = auth()->user()?->id;
        $data['updated_by'] = $userId !== null ? (int) $userId : null;

        try {
            if (! $this->repository->update($id, $data)) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Template gagal diperbarui.');
            }

            return redirect()->to(
                site_url('backend/email-templates')
            )->with('success', 'Template email berhasil diperbarui.');
        } catch (Throwable $e) {
            log_message('error', 'Gagal memperbarui template email: {error}', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan saat memperbarui template.');
        }
    }

    /**
     * Hapus template.
     */
    public function delete(int $id)
    {
        $this->authorize('backend.email_templates.manage');

        $template = $this->repository->findById($id);

        if ($template === null) {
            throw PageNotFoundException::forPageNotFound(
                'Template email tidak ditemukan.'
            );
        }

        if ((int) $template['is_system'] === 1) {
            return redirect()->to(
                site_url('backend/email-templates')
            )->with('error', 'Template sistem tidak boleh dihapus.');
        }

        try {
            $model = new \Modules\Backend\Models\EmailTemplateModel();

            if (! $model->delete($id)) {
                return redirect()->to(
                    site_url('backend/email-templates')
                )->with('error', 'Template gagal dihapus.');
            }

            return redirect()->to(
                site_url('backend/email-templates')
            )->with('success', 'Template email berhasil dihapus.');
        } catch (Throwable $e) {
            log_message('error', 'Gagal menghapus template email: {error}', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->to(
                site_url('backend/email-templates')
            )->with('error', 'Terjadi kesalahan saat menghapus template.');
        }
    }

    /**
     * Mengaktifkan atau menonaktifkan template.
     */
    public function toggleActive(int $id)
    {
        $this->authorize('backend.email_templates.manage');

        $template = $this->repository->findById($id);

        if ($template === null) {
            throw PageNotFoundException::forPageNotFound(
                'Template email tidak ditemukan.'
            );
        }

        $active = (int) $template['is_active'] !== 1;

        if (! $this->repository->setActive($id, $active)) {
            return redirect()->to(
                site_url('backend/email-templates')
            )->with('error', 'Status template gagal diperbarui.');
        }

        return redirect()->to(
            site_url('backend/email-templates')
        )->with(
            'success',
            $active
                ? 'Template berhasil diaktifkan.'
                : 'Template berhasil dinonaktifkan.'
        );
    }

    /**
     * Render form tambah/edit.
     */
    private function renderForm(?array $template = null)
    {
        return $this->render('email_templates/form', [
            'title'    => $template ? 'Edit Template Email' : 'Tambah Template Email',
            'template' => $template ?? [],
            'isEdit'   => $template !== null,
        ]);
    }

    /**
     * Validasi data dari form.
     */
    private function validatedData(): ?array
    {
        $isEdit = $this->request->getPost('id') !== null;

        $rules = [
            'code' => $isEdit
                ? 'permit_empty|max_length[100]'
                : 'required|alpha_numeric_punct|max_length[100]',
            'name'        => 'required|max_length[150]',
            'category'    => 'required|max_length[50]',
            'description' => 'permit_empty|max_length[500]',
            'channel'     => 'required|in_list[email]',
            'subject'     => 'permit_empty|max_length[255]',
            'body_html'   => 'permit_empty',
            'body_text'   => 'permit_empty',
            'variables'   => 'permit_empty',
            'from_name'   => 'permit_empty|max_length[150]',
            'from_email'  => 'permit_empty|valid_email|max_length[254]',
            'reply_to'    => 'permit_empty|valid_email|max_length[254]',
            'version'     => 'required|is_natural_no_zero',
            'is_system'   => 'permit_empty|in_list[0,1]',
            'is_active'   => 'permit_empty|in_list[0,1]',
        ];

        if (! $this->validate($rules)) {
            return null;
        }

        $variablesInput = trim((string) $this->request->getPost('variables'));
        $variables = null;

        if ($variablesInput !== '') {
            $decoded = json_decode($variablesInput, true);

            if (
                json_last_error() !== JSON_ERROR_NONE
                || ! is_array($decoded)
                || ! array_is_list($decoded)
                || array_filter($decoded, 'is_string') !== $decoded
            ) {
                $this->validator->setError(
                    'variables',
                    'Variables harus berupa JSON array yang berisi daftar nama variabel.'
                );

                return null;
            }

            $variables = json_encode(
                array_values(array_unique($decoded)),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if ($variables === false) {
                $this->validator->setError(
                    'variables',
                    'Variables gagal diproses.'
                );

                return null;
            }
        }

        $data = [
            'name'        => trim((string) $this->request->getPost('name')),
            'category'    => trim((string) $this->request->getPost('category')),
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'channel'     => 'email',
            'subject'     => trim((string) $this->request->getPost('subject')),
            'body_html'   => (string) $this->request->getPost('body_html'),
            'body_text'   => (string) $this->request->getPost('body_text'),
            'variables'   => $variables,
            'from_name'   => trim((string) $this->request->getPost('from_name')) ?: null,
            'from_email'  => trim((string) $this->request->getPost('from_email')) ?: null,
            'reply_to'    => trim((string) $this->request->getPost('reply_to')) ?: null,
            'version'     => max(1, (int) $this->request->getPost('version')),
            'is_system'   => $this->request->getPost('is_system') === '1' ? 1 : 0,
            'is_active'   => $this->request->getPost('is_active') === '1' ? 1 : 0,
        ];

        if (! $isEdit) {
            $data['code'] = trim((string) $this->request->getPost('code'));
        }

        return $data;
    }

    /**
     * Preview template email.
     */
    public function preview(int $id)
    {
        $this->authorize('backend.email_templates.view');

        $template = $this->repository->findById($id);

        if ($template === null) {
            throw PageNotFoundException::forPageNotFound(
                'Template email tidak ditemukan.'
            );
        }

        return $this->render('email_templates\preview', [
            'title'    => 'Preview Template Email',
            'template' => $template,
        ]);
    }
}
