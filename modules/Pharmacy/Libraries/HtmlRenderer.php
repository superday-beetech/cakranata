<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Libraries;

use Picqer\Barcode\Renderers\PngRenderer;
use Picqer\Barcode\Types\TypeCode128;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class HtmlRenderer
{
    public function __construct(
        private TemplateBindingResolver $binding,
    ) {}

    public function render(array $paperConfig, array $elements, array $data = [], ?array $logo = null): string
    {
        $elements = $this->binding->resolveElements($elements, $data);

        $widthMm  = (float) ($paperConfig['width']  ?? 210);
        $heightMm = (float) ($paperConfig['height'] ?? 297);
        if (($paperConfig['orientation'] ?? 'portrait') === 'landscape') {
            [$widthMm, $heightMm] = [$heightMm, $widthMm];
        }
        if (($paperConfig['type'] ?? '') === '80mm' && $heightMm <= 0) {
            $heightMm = 120;
        }

        $html  = '<div class="print-paper" style="'
            . 'position:relative;'
            . "width:{$widthMm}mm;height:{$heightMm}mm;"
            . 'background:#fff;font-family:Helvetica,Arial,sans-serif;'
            . 'overflow:hidden;box-sizing:border-box;">';

        if (! empty($logo['url'])) {
            $lw = (float) ($logo['width']  ?? 20);
            $lh = (float) ($logo['height'] ?? 20);
            $lx = (float) ($logo['x']      ?? 10);
            $ly = (float) ($logo['y']      ?? 8);
            $html .= '<img src="' . esc($logo['url']) . '" style="'
                . 'position:absolute;'
                . "left:{$lx}mm;top:{$ly}mm;"
                . "width:{$lw}mm;height:{$lh}mm;"
                . 'object-fit:contain;" />';
        }

        foreach ($elements as $el) {
            $html .= $this->renderElement($el);
        }
        $html .= '</div>';
        return $html;
    }

    private function renderElement(array $el): string
    {
        $type = $el['type'] ?? 'text';
        $x    = (float) ($el['x']      ?? 0);
        $y    = (float) ($el['y']      ?? 0);
        $w    = (float) ($el['width']  ?? 40);
        $h    = (float) ($el['height'] ?? 8);

        $props    = $el['props'] ?? [];
        $fontSize = (float) ($props['fontSize'] ?? 11);
        $color    = htmlspecialchars((string) ($props['color'] ?? '#000'));
        $align    = $props['align'] ?? 'left';
        $label    = (string) ($props['label'] ?? '');

        $textAlign = match ($align) {
            'center' => 'center',
            'right'  => 'right',
            default  => 'left',
        };

        $baseStyle = 'position:absolute;'
            . "left:{$x}mm;top:{$y}mm;"
            . "width:{$w}mm;height:{$h}mm;"
            . "font-size:{$fontSize}pt;"
            . "color:{$color};text-align:{$textAlign};"
            . 'line-height:1.25;overflow:hidden;box-sizing:border-box;'
            . 'white-space:pre-wrap;word-wrap:break-word;';

        switch ($type) {
            case 'text':
                return "<div style=\"{$baseStyle}\">" . nl2br(htmlspecialchars($label)) . '</div>';
            case 'line':
                return "<div style=\"position:absolute;left:{$x}mm;top:{$y}mm;width:{$w}mm;height:0;"
                    . "border-top:0.3mm solid {$color};\"></div>";
            case 'rect':
                return "<div style=\"{$baseStyle}border:1px solid {$color};\"></div>";
            case 'table':
                return $this->renderTable($el);
            case 'image':
            case 'logo':
                $src = (string) ($props['src'] ?? '');
                return "<div style=\"{$baseStyle}\">"
                    . ($src ? "<img src=\"" . esc($src) . "\" style=\"width:100%;height:100%;object-fit:contain;\" />" : '')
                    . '</div>';
            case 'qr':
                return $this->renderQr($baseStyle, (string) ($props['binding'] ?? $label), min($w, $h));
            case 'barcode':
                return $this->renderBarcode($baseStyle, (string) ($props['binding'] ?? $label), $h);
            default:
                return "<div style=\"{$baseStyle}\">" . htmlspecialchars($label) . '</div>';
        }
    }

    private function renderTable(array $el): string
    {
        $props    = $el['props'] ?? [];
        $columns  = $props['columns'] ?? [];
        $rows     = $props['rows'] ?? [];
        $fontSize = (float) ($props['fontSize'] ?? 10);

        $x = (float) ($el['x']      ?? 0);
        $y = (float) ($el['y']      ?? 0);
        $w = (float) ($el['width']  ?? 100);
        $h = (float) ($el['height'] ?? 40);

        $totalMm = 0;
        foreach ($columns as $col) {
            $totalMm += (float) ($col['width'] ?? 20);
        }
        $scale = ($totalMm > 0) ? $w / $totalMm : 1;

        $html = '<div style="position:absolute;'
            . "left:{$x}mm;top:{$y}mm;"
            . "width:{$w}mm;height:{$h}mm;"
            . "font-size:{$fontSize}pt;"
            . 'overflow:hidden;box-sizing:border-box;">'
            . '<table style="width:100%;border-collapse:collapse;table-layout:fixed;">'
            . '<thead><tr>';

        foreach ($columns as $col) {
            $cw = (float) ($col['width'] ?? 20) * $scale;
            $align = htmlspecialchars((string) ($col['align'] ?? 'left'));
            $html .= "<th style=\"border:0.3mm solid #333;padding:0.5mm 1mm;"
                . "text-align:{$align};width:{$cw}mm;font-weight:bold;"
                . 'overflow:hidden;word-wrap:break-word;">'
                . htmlspecialchars((string) ($col['label'] ?? '')) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $cw = (float) ($col['width'] ?? 20) * $scale;
                $align = htmlspecialchars((string) ($col['align'] ?? 'left'));
                $val = (string) ($row[$col['key']] ?? '');
                $html .= "<td style=\"border:0.3mm solid #333;padding:0.5mm 1mm;"
                    . "text-align:{$align};width:{$cw}mm;"
                    . 'overflow:hidden;word-wrap:break-word;">'
                    . htmlspecialchars($val) . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</tbody></table></div>';
    }

    private function renderBarcode(string $baseStyle, string $value, float $hMm): string
    {
        $value = trim($value);
        if ($value === '') {
            return "<div style=\"{$baseStyle}border:0.3mm dashed #999;"
                . 'display:flex;justify-content:center;align-items:center;font-size:9pt;">'
                . 'Barcode kosong</div>';
        }
        try {
            $barcode  = (new TypeCode128())->getBarcode($value);
            $renderer = new PngRenderer();
            $png      = $renderer->render($barcode, (int) ($barcode->getWidth() * 2), (int) max(20, $hMm * 3));
            $uri      = 'data:image/png;base64,' . base64_encode($png);
            return "<div style=\"{$baseStyle}display:flex;justify-content:center;align-items:center;\">"
                . "<img src=\"{$uri}\" style=\"max-width:100%;max-height:100%;object-fit:contain;\" />"
                . '</div>';
        } catch (\Throwable $e) {
            return "<div style=\"{$baseStyle}border:0.3mm dashed #c00;"
                . 'display:flex;justify-content:center;align-items:center;font-size:9pt;">'
                . 'Barcode error</div>';
        }
    }

    private function renderQr(string $baseStyle, string $value, float $sizeMm): string
    {
        $value = trim($value);
        if ($value === '') {
            return "<div style=\"{$baseStyle}border:0.3mm dashed #999;"
                . 'display:flex;justify-content:center;align-items:center;font-size:9pt;">'
                . 'QR kosong</div>';
        }
        try {
            $px = (int) max(100, $sizeMm * 4);
            $qrCode = new QrCode($value, size: $px);
            $result = (new PngWriter())->write($qrCode);
            $uri    = $result->getDataUri();
            return "<div style=\"{$baseStyle}display:flex;justify-content:center;align-items:center;\">"
                . "<img src=\"{$uri}\" style=\"max-width:100%;max-height:100%;object-fit:contain;\" />"
                . '</div>';
        } catch (\Throwable $e) {
            return "<div style=\"{$baseStyle}border:0.3mm dashed #c00;"
                . 'display:flex;justify-content:center;align-items:center;font-size:9pt;">'
                . 'QR error</div>';
        }
    }
}
