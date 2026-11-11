<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Libraries;

use Picqer\Barcode\Renderers\PngRenderer;
use Picqer\Barcode\Types\TypeCode128;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class HtmlRenderer
{
    private const MM_TO_PX = 3.7795275591;

    public function __construct(
        private TemplateBindingResolver $binding,
    ) {}

    public function render(array $paperConfig, array $elements, array $data = []): string
    {
        $elements = $this->binding->resolveElements($elements, $data);

        $widthMm  = (float) ($paperConfig['width']  ?? 210);
        $heightMm = (float) ($paperConfig['height'] ?? 297);
        if (($paperConfig['orientation'] ?? 'portrait') === 'landscape') {
            [$widthMm, $heightMm] = [$heightMm, $widthMm];
        }
        $widthPx  = round($widthMm * self::MM_TO_PX, 2);
        $heightPx = round($heightMm * self::MM_TO_PX, 2);

        $html = '<div class="print-paper" style="'
            . "position:relative;width:{$widthPx}px;height:{$heightPx}px;"
            . 'background:#fff;font-family:Helvetica,Arial,sans-serif;'
            . 'overflow:hidden;box-sizing:border-box;">';

        foreach ($elements as $el) {
            $html .= $this->renderElement($el);
        }
        $html .= '</div>';
        return $html;
    }

    private function renderElement(array $el): string
    {
        $type = $el['type'] ?? 'text';
        $x    = (int) ($el['x'] ?? 0);
        $y    = (int) ($el['y'] ?? 0);
        $w    = (int) ($el['width'] ?? 100);
        $h    = (int) ($el['height'] ?? 24);

        $props    = $el['props'] ?? [];
        $fontSize = (int) ($props['fontSize'] ?? 12);
        $color    = htmlspecialchars((string) ($props['color'] ?? '#000'));
        $align    = $props['align'] ?? 'left';
        $label    = htmlspecialchars((string) ($props['label'] ?? ''));

        $justify = match ($align) {
            'center' => 'center',
            'right'  => 'flex-end',
            default  => 'flex-start',
        };

        $baseStyle = "position:absolute;left:{$x}px;top:{$y}px;"
            . "width:{$w}px;height:{$h}px;font-size:{$fontSize}px;"
            . "color:{$color};box-sizing:border-box;"
            . "display:flex;align-items:flex-start;justify-content:{$justify};"
            . "white-space:pre-wrap;overflow:hidden;";

        return match ($type) {
            'text'  => "<div style=\"{$baseStyle}\">{$label}</div>",
            'line'  => "<div style=\"{$baseStyle}height:1px;background:{$color};\"></div>",
            'rect'  => "<div style=\"{$baseStyle}border:1px solid {$color};\"></div>",
            'table' => $this->renderTable($el),
            'image' => "<div style=\"{$baseStyle}justify-content:center;align-items:center;\">"
                . "<img src=\"{$label}\" style=\"max-width:100%;max-height:100%;\" /></div>",
            'barcode' => $this->renderBarcode($baseStyle, $label, $h),
            'qr'      => $this->renderQr($baseStyle, $label, min($w, $h)),
            default   => "<div style=\"{$baseStyle}\">{$label}</div>",
        };
    }

    private function renderTable(array $el): string
    {
        $props = $el['props'] ?? [];
        $columns = $props['columns'] ?? [];
        $rows = $props['rows'] ?? [];
        $fontSize = (int) ($props['fontSize'] ?? 10);

        $html = '<div style="position:absolute;left:' . (int)$el['x'] . 'px;'
            . 'top:' . (int)$el['y'] . 'px;'
            . 'width:' . (int)$el['width'] . 'px;'
            . 'font-size:' . $fontSize . 'px;overflow:hidden;">'
            . '<table style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>';
        foreach ($columns as $col) {
            $html .= '<th style="border:1px solid #333;padding:2px 4px;'
                . 'text-align:' . ($col['align'] ?? 'left') . ';'
                . 'width:' . (int)($col['width'] ?? 100) . 'px;">'
                . htmlspecialchars($col['label'] ?? '') . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $html .= '<td style="border:1px solid #333;padding:2px 4px;'
                    . 'text-align:' . ($col['align'] ?? 'left') . ';">'
                    . htmlspecialchars((string)($row[$col['key']] ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</tbody></table></div>';
    }

    private function renderBarcode(string $baseStyle, string $value, int $h): string
    {
        $value = trim($value);
        if ($value === '') {
            return "<div style=\"{$baseStyle}border:1px dashed #999;"
                . "justify-content:center;align-items:center;font-size:10px;\">"
                . "Barcode kosong</div>";
        }
        try {
            $barcode  = (new TypeCode128())->getBarcode($value);
            $renderer = new PngRenderer();
            $png      = $renderer->render($barcode, $barcode->getWidth() * 2, $h);
            $uri      = 'data:image/png;base64,' . base64_encode($png);
            return "<div style=\"{$baseStyle}justify-content:center;align-items:center;\">"
                . "<img src=\"{$uri}\" style=\"max-width:100%;max-height:100%;\" /></div>";
        } catch (\Throwable $e) {
            return "<div style=\"{$baseStyle}border:1px dashed #c00;"
                . "justify-content:center;align-items:center;font-size:10px;\">"
                . "Barcode error</div>";
        }
    }

    private function renderQr(string $baseStyle, string $value, int $size): string
    {
        $value = trim($value);
        if ($value === '') {
            return "<div style=\"{$baseStyle}border:1px dashed #999;"
                . "justify-content:center;align-items:center;font-size:10px;\">"
                . "QR kosong</div>";
        }
        try {
            $qrCode = new QrCode($value, size: max(50, $size));
            $result = (new PngWriter())->write($qrCode);
            $uri    = $result->getDataUri();
            return "<div style=\"{$baseStyle}justify-content:center;align-items:center;\">"
                . "<img src=\"{$uri}\" style=\"max-width:100%;max-height:100%;\" /></div>";
        } catch (\Throwable $e) {
            return "<div style=\"{$baseStyle}border:1px dashed #c00;"
                . "justify-content:center;align-items:center;font-size:10px;\">"
                . "QR error</div>";
        }
    }
}
