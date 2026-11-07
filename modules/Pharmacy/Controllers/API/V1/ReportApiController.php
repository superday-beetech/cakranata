<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Controllers\API\V1;

use App\Core\ApiController;
use Dompdf\Dompdf;
use Dompdf\Options;
use InvalidArgumentException;
use Modules\Pharmacy\Services\ReportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class ReportApiController extends ApiController
{
    private ReportService $reportService;

    public function __construct()
    {
        $this->reportService = new ReportService();
    }

    /**
     * GET /pharmacy/report-api/stock-summary
     */
    public function stockSummary()
    {
        try {
            $result = $this->reportService->getStockSummary(
                $this->request->getGet()
            );

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Laporan ringkasan stok berhasil dimuat.',
                'data'    => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleError('Stock Summary', $e);
        }
    }

    /**
     * GET /pharmacy/report-api/stock-summary/csv
     */
    public function stockSummaryCsv()
    {
        try {
            $data = $this->getStockSummaryExportData();

            $filename = $this->exportFilename('csv');

            $columns = $this->stockSummaryColumns();

            $output = fopen('php://temp', 'r+');

            // UTF-8 BOM agar karakter terbaca baik di Excel.
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'LAPORAN RINGKASAN PERSEDIAAN',
            ]);

            fputcsv($output, [
                'Periode',
                $data['period']['date_from'] . ' s.d. ' .
                    $data['period']['date_to'],
            ]);

            fputcsv($output, []);

            fputcsv($output, [
                'Ringkasan',
            ]);

            fputcsv($output, ['Jumlah Produk', $data['summary']['product_count']]);
            fputcsv($output, ['Saldo Awal', $data['summary']['opening_stock']]);
            fputcsv($output, ['Total Stok Masuk', $data['summary']['total_in']]);
            fputcsv($output, ['Total Stok Keluar', $data['summary']['total_out']]);
            fputcsv($output, ['Saldo Akhir', $data['summary']['closing_stock']]);
            fputcsv($output, ['Nilai Persediaan', $data['summary']['stock_value']]);
            fputcsv($output, ['Batch Kedaluwarsa', $data['summary']['expired_batch_count']]);
            fputcsv($output, ['Batch Segera Kedaluwarsa', $data['summary']['expiring_batch_count']]);

            fputcsv($output, []);
            fputcsv($output, array_column($columns, 'label'));

            foreach ($data['items'] as $index => $item) {
                fputcsv($output, $this->stockSummaryRow($item, $index + 1));
            }

            rewind($output);
            $content = stream_get_contents($output);
            fclose($output);

            return $this->downloadResponse(
                $filename,
                (string) $content,
                'text/csv; charset=UTF-8'
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleError('Stock Summary CSV', $e);
        }
    }

    /**
     * GET /pharmacy/report-api/stock-summary/excel
     */
    public function stockSummaryExcel()
    {
        $spreadsheet = null;

        try {
            $data = $this->getStockSummaryExportData();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Ringkasan Stok');

            $columns = $this->stockSummaryColumns();
            $columnCount = count($columns);
            $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

            // Judul laporan.
            $sheet->mergeCells("A1:{$lastColumn}1");
            $sheet->setCellValue('A1', 'LAPORAN RINGKASAN PERSEDIAAN');

            $sheet->mergeCells("A2:{$lastColumn}2");
            $sheet->setCellValue('A2', 'PHARMACY MANAGEMENT SYSTEM');

            $sheet->mergeCells("A3:{$lastColumn}3");
            $sheet->setCellValue(
                'A3',
                'Periode: ' . $data['period']['date_from'] .
                    ' s.d. ' . $data['period']['date_to']
            );

            $sheet->mergeCells("A4:{$lastColumn}4");
            $sheet->setCellValue(
                'A4',
                'Dicetak: ' . date('d-m-Y H:i:s')
            );

            // Ringkasan.
            $sheet->setCellValue('A6', 'RINGKASAN PERSEDIAAN');

            $summaryRows = [
                ['Jumlah Produk', $data['summary']['product_count']],
                ['Saldo Awal', $data['summary']['opening_stock']],
                ['Total Stok Masuk', $data['summary']['total_in']],
                ['Total Stok Keluar', $data['summary']['total_out']],
                ['Saldo Akhir', $data['summary']['closing_stock']],
                ['Nilai Persediaan', $data['summary']['stock_value']],
                ['Batch Kedaluwarsa', $data['summary']['expired_batch_count']],
                ['Batch Segera Kedaluwarsa', $data['summary']['expiring_batch_count']],
            ];

            $row = 6;

            // foreach ($summaryRows as $summaryRow) {
            //     $sheet->setCellValue("A{$row}", $summaryRow[0]);
            //     $sheet->setCellValue("C{$row}", $summaryRow[1]);
            //     $row++;
            // }
            foreach ($summaryRows as $summaryRow) {
                $sheet->mergeCells("A{$row}:C{$row}");

                $sheet->setCellValue("A{$row}", $summaryRow[0]);
                $sheet->setCellValue("D{$row}", $summaryRow[1]);

                $row++;
            }

            // Tabel detail.
            $headerRow = 17;

            foreach ($columns as $index => $column) {
                $cell = Coordinate::stringFromColumnIndex($index + 1) . $headerRow;

                $sheet->setCellValue($cell, $column['label']);
            }

            $detailRow = $headerRow + 1;

            foreach ($data['items'] as $index => $item) {
                $values = $this->stockSummaryRow($item, $index + 1);

                foreach ($values as $columnIndex => $value) {
                    $cell = Coordinate::stringFromColumnIndex($columnIndex + 1) . $detailRow;

                    $sheet->setCellValue($cell, $value);
                }

                $detailRow++;
            }

            // Styling judul.
            $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 18,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => '17365D'],
                ],
                'alignment' => [
                    'horizontal' => 'center',
                ],
            ]);

            $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 11,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => '17365D'],
                ],
                'alignment' => [
                    'horizontal' => 'center',
                ],
            ]);

            $sheet->getStyle("A3:{$lastColumn}4")->applyFromArray([
                'font' => [
                    'size' => 10,
                    'color' => ['rgb' => '555555'],
                ],
                'alignment' => [
                    'horizontal' => 'center',
                ],
            ]);

            // Header ringkasan.
            $sheet->getStyle('A6:B6')->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => '4472C4'],
                ],
            ]);

            // Header tabel.
            $sheet->getStyle(
                "A{$headerRow}:{$lastColumn}{$headerRow}"
            )->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => '17365D'],
                ],
                'alignment' => [
                    'horizontal' => 'center',
                    'vertical' => 'center',
                    'wrapText' => true,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => 'thin',
                        'color' => ['rgb' => 'D9E2F3'],
                    ],
                ],
            ]);

            // Border tabel.
            if ($detailRow > $headerRow + 1) {
                $sheet->getStyle(
                    "A{$headerRow}:{$lastColumn}" . ($detailRow - 1)
                )->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => 'thin',
                            'color' => ['rgb' => 'D9D9D9'],
                        ],
                    ],
                ]);
            }

            // Format angka dan mata uang.
            foreach (['F', 'G', 'H', 'I'] as $column) {
                $sheet->getStyle(
                    "{$column}" . ($headerRow + 1) . ":{$column}" . max($headerRow + 1, $detailRow - 1)
                )->getNumberFormat()->setFormatCode('#,##0.000');
            }

            foreach (['J', 'K'] as $column) {
                $sheet->getStyle(
                    "{$column}" . ($headerRow + 1) . ":{$column}" . max($headerRow + 1, $detailRow - 1)
                )->getNumberFormat()->setFormatCode('"Rp" #,##0');
            }

            $sheet->getColumnDimension('A')->setWidth(8);

            foreach (range('B', $lastColumn) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $sheet->getRowDimension($headerRow)->setRowHeight(32);
            $sheet->freezePane('A18');
            $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}" . max($headerRow, $detailRow - 1));

            $sheet->getPageSetup()
                ->setOrientation(
                    \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
                )
                ->setPaperSize(
                    \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4
                )
                ->setFitToWidth(1)
                ->setFitToHeight(0);

            $sheet->getPageSetup()->setFitToPage(true);

            $writer = new Xlsx($spreadsheet);

            ob_start();
            $writer->save('php://output');
            $content = ob_get_clean();

            return $this->downloadResponse(
                $this->exportFilename('xlsx'),
                (string) $content,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleError('Stock Summary Excel', $e);
        } finally {
            $spreadsheet?->disconnectWorksheets();
        }
    }

    /**
     * GET /pharmacy/report-api/stock-summary/pdf
     */
    public function stockSummaryPdf()
    {
        try {
            $data = $this->getStockSummaryExportData();

            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new Dompdf($options);

            $html = view(
                'Modules\Pharmacy\Reports\StockSummaryPdf',
                [
                    'report' => $data,
                    'columns' => $this->stockSummaryColumns(),
                    'rows' => array_map(
                        fn($item, $index) => $this->stockSummaryRow($item, $index + 1),
                        $data['items'],
                        array_keys($data['items'])
                    ),
                    'generatedAt' => date('d-m-Y H:i:s'),
                ]
            );

            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            return $this->downloadResponse(
                $this->exportFilename('pdf'),
                $dompdf->output(),
                'application/pdf'
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleError('Stock Summary PDF', $e);
        }
    }

    /**
     * GET /pharmacy/report-api/stock-card
     */
    public function stockCard()
    {
        try {
            $result = $this->reportService->getStockCard(
                $this->request->getGet()
            );

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Laporan kartu stok berhasil dimuat.',
                'data' => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleError('Stock Card', $e);
        }
    }

    /**
     * GET /pharmacy/report-api/batch-expiry
     */
    public function batchExpiry()
    {
        try {
            $result = $this->reportService->getBatchExpiry(
                $this->request->getGet()
            );

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Laporan batch dan kedaluwarsa berhasil dimuat.',
                'data' => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleError('Batch Expiry', $e);
        }
    }

    /**
     * Ambil seluruh baris Stock Summary untuk export.
     */
    private function getStockSummaryExportData(): array
    {
        $filters = $this->request->getGet();

        $filters['page'] = 1;
        $filters['per_page'] = 5000;

        $result = $this->reportService->getStockSummary($filters);

        if (
            $result['pagination']['total'] >
            count($result['items'])
        ) {
            throw new InvalidArgumentException(
                'Jumlah produk melebihi batas export 5.000 baris. ' .
                    'Gunakan filter kategori atau kata kunci.'
            );
        }

        return $result;
    }

    private function stockSummaryColumns(): array
    {
        return [
            ['label' => 'No'],
            ['label' => 'SKU'],
            ['label' => 'Nama Produk'],
            ['label' => 'Kategori'],
            ['label' => 'Satuan'],
            ['label' => 'Saldo Awal'],
            ['label' => 'Stok Masuk'],
            ['label' => 'Stok Keluar'],
            ['label' => 'Saldo Akhir'],
            ['label' => 'Harga Beli'],
            ['label' => 'Nilai Persediaan'],
            ['label' => 'Batch Kedaluwarsa'],
            ['label' => 'Batch Segera Kedaluwarsa'],
        ];
    }

    private function stockSummaryRow(array $item, int $number): array
    {
        return [
            $number,
            $item['sku'] ?? '',
            $item['product_name'] ?? '',
            $item['category_name'] ?? '',
            $item['unit_abbreviation'] ?? '',
            (float) ($item['opening_stock'] ?? 0),
            (float) ($item['total_in'] ?? 0),
            (float) ($item['total_out'] ?? 0),
            (float) ($item['closing_stock'] ?? 0),
            (float) ($item['purchase_price'] ?? 0),
            (float) ($item['stock_value'] ?? 0),
            (int) ($item['expired_batch_count'] ?? 0),
            (int) ($item['expiring_batch_count'] ?? 0),
        ];
    }

    private function exportFilename(string $extension): string
    {
        $filters = $this->request->getGet();

        $from = $filters['date_from'] ?? 'all';
        $to = $filters['date_to'] ?? date('Y-m-d');

        return "stock-summary-{$from}-{$to}.{$extension}";
    }

    private function downloadResponse(
        string $filename,
        string $content,
        string $contentType
    ) {
        return $this->response
            ->setHeader('Content-Type', $contentType)
            ->setHeader(
                'Content-Disposition',
                'attachment; filename="' . $filename . '"'
            )
            ->setHeader('Content-Length', (string) strlen($content))
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody($content);
    }

    private function handleError(string $context, Throwable $e)
    {
        log_message('error', '[Pharmacy Report] {context}: {message}', [
            'context' => $context,
            'message' => $e->getMessage(),
        ]);

        return $this->errorResponse(
            'Terjadi kesalahan saat menghasilkan laporan.',
            500
        );
    }

    /**
     * GET /pharmacy/report-api/stock-card-csv
     */
    public function stockCardCsv()
    {
        try {
            $data = $this->getStockCardExportData();
            $output = fopen('php://temp', 'r+');

            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, ['LAPORAN KARTU STOK']);
            fputcsv($output, ['Produk', $data['product']['name']]);
            fputcsv($output, ['SKU', $data['product']['sku']]);
            fputcsv($output, [
                'Periode',
                ($data['filters']['date_from'] ?? '-') . ' s.d. ' .
                    ($data['filters']['date_to'] ?? '-'),
            ]);

            fputcsv($output, []);
            fputcsv($output, ['RINGKASAN']);
            fputcsv($output, ['Saldo Awal', $data['summary']['opening_stock']]);
            fputcsv($output, ['Total Masuk', $data['summary']['total_in']]);
            fputcsv($output, ['Total Keluar', $data['summary']['total_out']]);
            fputcsv($output, ['Saldo Akhir', $data['summary']['closing_stock']]);

            fputcsv($output, []);
            fputcsv($output, array_column($this->stockCardColumns(), 'label'));

            foreach ($data['items'] as $index => $item) {
                fputcsv($output, $this->stockCardRow($item, $index + 1));
            }

            rewind($output);
            $content = stream_get_contents($output);
            fclose($output);

            return $this->downloadResponse(
                $this->stockCardExportFilename('csv'),
                (string) $content,
                'text/csv; charset=UTF-8'
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleError('Stock Card CSV', $e);
        }
    }

    /**
     * GET /pharmacy/report-api/stock-card-excel
     */
    public function stockCardExcel()
    {
        $spreadsheet = null;

        try {
            $data = $this->getStockCardExportData();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Kartu Stok');

            $columns = $this->stockCardColumns();
            $columnCount = count($columns);
            $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

            $sheet->mergeCells("A1:{$lastColumn}1");
            $sheet->setCellValue('A1', 'LAPORAN KARTU STOK');

            $sheet->mergeCells("A2:{$lastColumn}2");
            $sheet->setCellValue('A2', 'PHARMACY MANAGEMENT SYSTEM');

            $sheet->mergeCells("A3:{$lastColumn}3");
            $sheet->setCellValue(
                'A3',
                'Produk: ' . $data['product']['name'] .
                    ' | SKU: ' . $data['product']['sku']
            );

            $sheet->mergeCells("A4:{$lastColumn}4");
            $sheet->setCellValue(
                'A4',
                'Periode: ' .
                    ($data['filters']['date_from'] ?? '-') .
                    ' s.d. ' .
                    ($data['filters']['date_to'] ?? '-')
            );

            $sheet->mergeCells("A5:{$lastColumn}5");
            $sheet->setCellValue(
                'A5',
                'Dicetak: ' . date('d-m-Y H:i:s')
            );

            $sheet->setCellValue('A7', 'RINGKASAN KARTU STOK');

            $summaryRows = [
                ['Saldo Awal', $data['summary']['opening_stock']],
                ['Total Stok Masuk', $data['summary']['total_in']],
                ['Total Stok Keluar', $data['summary']['total_out']],
                ['Saldo Akhir', $data['summary']['closing_stock']],
            ];

            $row = 7;

            foreach ($summaryRows as $summaryRow) {
                $sheet->mergeCells("A{$row}:B{$row}");
                $sheet->setCellValue("A{$row}", $summaryRow[0]);
                $sheet->setCellValue("C{$row}", $summaryRow[1]);
                $row++;
            }

            $headerRow = 13;

            foreach ($columns as $index => $column) {
                $cell = Coordinate::stringFromColumnIndex($index + 1)
                    . $headerRow;

                $sheet->setCellValue($cell, $column['label']);
            }

            $detailRow = $headerRow + 1;

            foreach ($data['items'] as $index => $item) {
                $values = $this->stockCardRow($item, $index + 1);

                foreach ($values as $columnIndex => $value) {
                    $cell = Coordinate::stringFromColumnIndex($columnIndex + 1)
                        . $detailRow;

                    $sheet->setCellValue($cell, $value);
                }

                $detailRow++;
            }

            $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 18,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => '17365D'],
                ],
                'alignment' => ['horizontal' => 'center'],
            ]);

            $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => '17365D'],
                ],
                'alignment' => ['horizontal' => 'center'],
            ]);

            $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")
                ->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => 'solid',
                        'startColor' => ['rgb' => '17365D'],
                    ],
                    'alignment' => [
                        'horizontal' => 'center',
                        'vertical' => 'center',
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => 'thin',
                            'color' => ['rgb' => 'D9E2F3'],
                        ],
                    ],
                ]);

            if ($detailRow > $headerRow + 1) {
                $sheet->getStyle(
                    "A{$headerRow}:{$lastColumn}" . ($detailRow - 1)
                )->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => 'thin',
                            'color' => ['rgb' => 'D9D9D9'],
                        ],
                    ],
                ]);
            }

            $sheet->getStyle(
                'F' . ($headerRow + 1) . ':H' . max($headerRow + 1, $detailRow - 1)
            )->getNumberFormat()->setFormatCode('#,##0.000');

            foreach (range('A', $lastColumn) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $sheet->getRowDimension($headerRow)->setRowHeight(32);
            $sheet->freezePane('A14');
            $sheet->setAutoFilter(
                "A{$headerRow}:{$lastColumn}" . max($headerRow, $detailRow - 1)
            );

            $sheet->getPageSetup()
                ->setOrientation(
                    \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
                )
                ->setPaperSize(
                    \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4
                )
                ->setFitToWidth(1)
                ->setFitToHeight(0);

            $sheet->getPageSetup()->setFitToPage(true);

            $writer = new Xlsx($spreadsheet);

            ob_start();
            $writer->save('php://output');
            $content = ob_get_clean();

            return $this->downloadResponse(
                $this->stockCardExportFilename('xlsx'),
                (string) $content,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleError('Stock Card Excel', $e);
        } finally {
            $spreadsheet?->disconnectWorksheets();
        }
    }

    /**
     * GET /pharmacy/report-api/stock-card-pdf
     */
    public function stockCardPdf()
    {
        try {
            $data = $this->getStockCardExportData();

            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new Dompdf($options);

            $html = view(
                'Modules\Pharmacy\Reports\StockCardPdf',
                [
                    'report' => $data,
                    'columns' => $this->stockCardColumns(),
                    'rows' => array_map(
                        fn($item, $index) => $this->stockCardRow(
                            $item,
                            $index + 1
                        ),
                        $data['items'],
                        array_keys($data['items'])
                    ),
                    'generatedAt' => date('d-m-Y H:i:s'),
                ]
            );

            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            return $this->downloadResponse(
                $this->stockCardExportFilename('pdf'),
                $dompdf->output(),
                'application/pdf'
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleError('Stock Card PDF', $e);
        }
    }

    /**
     * Ambil seluruh transaksi Stock Card untuk export.
     * getStockCard membatasi per_page maksimal 500.
     */
    private function getStockCardExportData(): array
    {
        $filters = $this->request->getGet();

        $filters['page'] = 1;
        $filters['per_page'] = 500;

        $result = $this->reportService->getStockCard($filters);

        $total = (int) $result['pagination']['total'];

        if ($total > 5000) {
            throw new InvalidArgumentException(
                'Jumlah transaksi melebihi batas export 5.000 baris. ' .
                    'Persempit periode atau pilih batch tertentu.'
            );
        }

        $items = $result['items'];
        $totalPages = (int) $result['pagination']['total_pages'];

        for ($page = 2; $page <= $totalPages; $page++) {
            $filters['page'] = $page;

            $pageResult = $this->reportService->getStockCard($filters);

            $items = array_merge($items, $pageResult['items']);
        }

        $result['items'] = $items;
        $result['filters'] = $filters;

        return $result;
    }

    private function stockCardColumns(): array
    {
        return [
            ['label' => 'No'],
            ['label' => 'Tanggal'],
            ['label' => 'Batch'],
            ['label' => 'Tanggal Kedaluwarsa'],
            ['label' => 'Jenis Pergerakan'],
            ['label' => 'Referensi'],
            ['label' => 'Stok Masuk'],
            ['label' => 'Stok Keluar'],
            ['label' => 'Saldo'],
            ['label' => 'Biaya Satuan'],
            ['label' => 'Keterangan'],
        ];
    }

    private function stockCardRow(array $item, int $number): array
    {
        return [
            $number,
            $item['movement_date'] ?? '',
            $item['batch_number'] ?? '-',
            $item['expired_at'] ?? '-',
            $item['movement_type'] ?? '',
            trim(
                ($item['reference_type'] ?? '') . ' #' .
                    ($item['reference_id'] ?? '')
            ),
            (float) ($item['quantity_in'] ?? 0),
            (float) ($item['quantity_out'] ?? 0),
            (float) ($item['balance'] ?? 0),
            (float) ($item['unit_cost'] ?? 0),
            $item['notes'] ?? '',
        ];
    }

    private function stockCardExportFilename(string $extension): string
    {
        $filters = $this->request->getGet();

        $productId = (int) ($filters['product_id'] ?? 0);
        $from = $filters['date_from'] ?? 'all';
        $to = $filters['date_to'] ?? date('Y-m-d');

        return "stock-card-product-{$productId}-{$from}-{$to}.{$extension}";
    }
}
