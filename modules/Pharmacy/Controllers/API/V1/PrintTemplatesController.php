<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use Modules\Pharmacy\Libraries\HtmlRenderer;
use Modules\Pharmacy\Libraries\TemplateBindingResolver;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Pharmacy\Models\PrintTemplateModel;
use Psr\Log\LoggerInterface;

class PrintTemplatesController extends ApiController
{
    protected $modelName = PrintTemplateModel::class;
    protected $format    = 'json';

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
    }

    public function index()
    {
        $rows = $this->model
            ->select('id, name, type, paper_type, created_by, created_at, updated_at')
            ->orderBy('updated_at', 'DESC')->findAll();
        return $this->respond(['data' => $rows]);
    }

    public function show($id = null)
    {
        if ($id === null || ! ctype_digit((string) $id)) return $this->failValidationErrors('ID tidak valid.');
        $row = $this->model->find($id);
        if ($row === null) return $this->failNotFound('Template tidak ditemukan.');
        return $this->respond(['data' => $this->hydrateRow($row)]);
    }

    public function create()
    {
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) return $this->failValidationErrors('Body harus JSON.');

        $allowed = ['name', 'type', 'paper_type', 'paper_config', 'design_json'];
        $payload = array_intersect_key($payload, array_flip($allowed));
        if (isset($payload['paper_type'])) $payload['paper_type'] = strtolower((string) $payload['paper_type']);

        $error = $this->validatePayload($payload);
        if ($error !== null) return $this->failValidationErrors($error);

        $userId = $this->currentUserId();
        if ($userId === null) return $this->failUnauthorized('User tidak terautentikasi.');

        $payload = $this->encodeJsonFields($payload);
        $payload['created_by'] = $userId;

        $id = $this->model->insert($payload);
        if ($id === false) return $this->failValidationErrors($this->model->errors() ?: 'Gagal menyimpan.');

        $row = $this->model->find($id);
        if ($row === null) return $this->fail('Gagal memuat data setelah disimpan.', 500);
        return $this->respondCreated(['data' => $this->hydrateRow($row)]);
    }

    public function update($id = null)
    {
        if ($id === null || ! ctype_digit((string) $id)) return $this->failValidationErrors('ID tidak valid.');
        if ($this->model->find($id) === null) return $this->failNotFound('Template tidak ditemukan.');

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) return $this->failValidationErrors('Body harus JSON.');

        $allowed = ['name', 'type', 'paper_type', 'paper_config', 'design_json'];
        $payload = array_intersect_key($payload, array_flip($allowed));
        if (isset($payload['paper_type'])) $payload['paper_type'] = strtolower((string) $payload['paper_type']);

        $error = $this->validatePayload($payload, true);
        if ($error !== null) return $this->failValidationErrors($error);

        $payload = $this->encodeJsonFields($payload);

        if (! $this->model->update($id, $payload)) {
            return $this->failValidationErrors($this->model->errors() ?: 'Gagal memperbarui.');
        }
        $row = $this->model->find($id);
        if ($row === null) return $this->fail('Gagal memuat data setelah diperbarui.', 500);
        return $this->respond(['data' => $this->hydrateRow($row)]);
    }

    public function delete($id = null)
    {
        if ($id === null || ! ctype_digit((string) $id)) return $this->failValidationErrors('ID tidak valid.');
        $row = $this->model->find($id);
        if ($row === null) return $this->failNotFound('Template tidak ditemukan.');
        if (! $this->model->delete($id)) return $this->fail('Gagal menghapus template.', 500);
        return $this->respondDeleted(['data' => ['id' => (int) $id]]);
    }

    /** GET/POST /print-templates/{id}/render */
    public function render($id = null)
    {
        if ($id === null || ! ctype_digit((string) $id)) return $this->failValidationErrors('ID tidak valid.');
        $template = $this->model->find($id);
        if ($template === null) return $this->failNotFound('Template tidak ditemukan.');

        $data = $this->request->getJSON(true)['data']
            ?? $this->resolveContextData($this->request->getGet());

        $design = $this->decodeJson($template['design_json'] ?? '{}');
        $paper  = $this->decodeJson($template['paper_config'] ?? '{}');

        $renderer = new HtmlRenderer(new TemplateBindingResolver());
        $html = $renderer->render($paper, $design['elements'] ?? [], $data);

        return $this->response
            ->setHeader('Content-Type', 'text/html; charset=utf-8')
            ->setBody($html);
    }

    /** GET /print-templates/{id}/render.pdf?inline=1 */
    public function renderPdf($id = null)
    {
        if ($id === null || ! ctype_digit((string) $id)) return $this->failValidationErrors('ID tidak valid.');
        $template = $this->model->find($id);
        if ($template === null) return $this->failNotFound('Template tidak ditemukan.');

        $data = $this->request->getJSON(true)['data']
            ?? $this->resolveContextData($this->request->getGet());

        $design = $this->decodeJson($template['design_json'] ?? '{}');
        $paper  = $this->decodeJson($template['paper_config'] ?? '{}');

        $renderer = new HtmlRenderer(new TemplateBindingResolver());
        $inner = $renderer->render($paper, $design['elements'] ?? [], $data);
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">'
            . '<style>@page { margin: 0; } body { margin: 0; }</style>'
            . '</head><body>' . $inner . '</body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper(
            ($paper['type'] ?? '') === '80mm' ? 'A7' : strtoupper((string) ($paper['type'] ?? 'A4')),
            (string) ($paper['orientation'] ?? 'portrait')
        );
        $dompdf->render();

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="template-' . $id . '.pdf"')
            ->setBody($dompdf->output());
    }

    // ---------- helpers ----------
    private function hydrateRow(array $row): array
    {
        $row['paper_config'] = $this->decodeJson($row['paper_config'] ?? null);
        $row['design_json']  = $this->decodeJson($row['design_json'] ?? null);
        return $row;
    }
    private function encodeJsonFields(array $p): array
    {
        foreach (['paper_config', 'design_json'] as $f) {
            if (array_key_exists($f, $p) && (is_array($p[$f]) || is_object($p[$f]))) {
                $p[$f] = json_encode($p[$f], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        return $p;
    }
    private function validatePayload(array $p, bool $partial = false): ?string
    {
        $required = $partial ? [] : ['name', 'type', 'paper_type', 'paper_config', 'design_json'];
        foreach ($required as $f) {
            if (! array_key_exists($f, $p) || $p[$f] === '' || $p[$f] === null) return "Field '{$f}' wajib diisi.";
        }
        if (
            array_key_exists('paper_type', $p) && $p['paper_type'] !== null
            && ! in_array($p['paper_type'], ['a4', 'a5', '80mm', 'custom'], true)
        ) {
            return "paper_type harus salah satu: a4, a5, 80mm, custom.";
        }
        foreach (['paper_config', 'design_json'] as $jf) {
            if (! array_key_exists($jf, $p)) continue;
            $v = $p[$jf];
            if ($v === null || $v === '') {
                if (! $partial) return "Field '{$jf}' wajib diisi.";
                continue;
            }
            if (is_string($v)) {
                json_decode($v);
                if (json_last_error() !== JSON_ERROR_NONE) return "Field '{$jf}' bukan JSON valid.";
                continue;
            }
            if (! is_array($v) && ! is_object($v)) return "Field '{$jf}' harus object/array atau string JSON.";
        }
        return null;
    }
    private function decodeJson($v)
    {
        if (is_string($v) && $v !== '') {
            $d = json_decode($v, true);
            if (json_last_error() === JSON_ERROR_NONE) return $d;
        }
        return $v;
    }
    private function currentUserId(): ?int
    {
        $user = auth()->user();
        return $user?->id;
    }
    private function resolveContextData(array $query): array
    {
        $data = [];
        // Sesuaikan: ambil dari model berdasarkan query param
        // if (! empty($query['patient_id'])) $data['patient'] = model(PatientModel::class)->find($query['patient_id']);
        // if (! empty($query['invoice_id'])) $data['invoice'] = model(InvoiceModel::class)->find($query['invoice_id']);
        return $data;
    }
}
