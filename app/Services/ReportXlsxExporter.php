<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class ReportXlsxExporter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<mixed>>  $rows
     * @param  array<string, mixed>  $options
     */
    public function download(string $filename, array $headers, iterable $rows, array $options = []): Response
    {
        $normalized = [];
        foreach ($rows as $row) {
            $normalized[] = array_values($row);
        }

        $this->auditExport($filename);

        $bytes = $this->buildWorkbook($headers, $normalized, $options);
        $safeName = str_replace(['"', "\r", "\n"], '', $filename);

        return response($bytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$safeName.'"',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     * @param  array<string, mixed>  $options
     */
    public function buildWorkbook(array $headers, array $rows, array $options = []): string
    {
        $sheetName = $this->sanitizeSheetName((string) ($options['sheet_name'] ?? 'Отчет'));
        $numericColumns = $this->detectNumericColumns($headers, $rows);
        $colCount = max(1, count($headers));
        $lastCol = $this->columnLetter($colCount);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);

        $matrix = array_merge([$headers], $rows);
        $sheet->fromArray($matrix, null, 'A1', true);

        $lastRow = count($matrix);
        $headerRange = 'A1:'.$lastCol.'1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E8EDF3'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D0D5DD'],
                ],
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(42);

        if ($lastRow >= 2 && $this->isTotalRow($rows[0] ?? [])) {
            $totalRange = 'A2:'.$lastCol.'2';
            $sheet->getStyle($totalRange)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8EDF3'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D0D5DD'],
                    ],
                ],
            ]);
        }

        if ($lastRow > 2) {
            $dataRange = 'A3:'.$lastCol.$lastRow;
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D0D5DD'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
        }

        foreach ($numericColumns as $colIdx) {
            $col = $this->columnLetter($colIdx + 1);
            $sheet->getStyle($col.'2:'.$col.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle($col.'2:'.$col.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $sheet->getStyle('A2:A'.$lastRow)->getAlignment()->setWrapText(true);

        $this->applyColumnWidths($sheet, $headers, $rows, $numericColumns);

        $tmp = tempnam(sys_get_temp_dir(), 'lc_xlsx_');
        if ($tmp === false) {
            throw new \RuntimeException('Не удалось создать xlsx');
        }

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($tmp);

        $bytes = file_get_contents($tmp) ?: '';
        @unlink($tmp);
        $spreadsheet->disconnectWorksheets();

        if ($bytes === '' || ! str_starts_with($bytes, "PK\x03\x04")) {
            throw new \RuntimeException('Сгенерированный xlsx повреждён');
        }

        return $bytes;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     * @param  list<int>  $numericColumns
     */
    private function applyColumnWidths(Worksheet $sheet, array $headers, array $rows, array $numericColumns): void
    {
        foreach ($this->computeColumnWidths($headers, $rows, $numericColumns) as $idx => $width) {
            $sheet->getColumnDimension($this->columnLetter($idx + 1))->setWidth($width);
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     * @param  list<int>  $numericColumns
     * @return list<float>
     */
    private function computeColumnWidths(array $headers, array $rows, array $numericColumns): array
    {
        $colCount = max(1, count($headers));
        $maxLens = array_fill(0, $colCount, 0);
        $allRows = array_merge([$headers], $rows);

        foreach ($allRows as $row) {
            foreach (array_values($row) as $cIdx => $value) {
                if ($cIdx >= $colCount) {
                    break;
                }
                $maxLens[$cIdx] = max($maxLens[$cIdx], $this->displayLength($value));
            }
        }

        $widths = [];
        foreach ($maxLens as $cIdx => $maxLen) {
            $isNumeric = in_array($cIdx, $numericColumns, true);
            if ($cIdx === 0) {
                $widths[] = min(42.0, max(18.0, $maxLen * 1.1 + 3));
            } elseif ($isNumeric) {
                $widths[] = min(18.0, max(12.0, $maxLen * 1.05 + 2));
            } else {
                $widths[] = min(36.0, max(14.0, $maxLen * 1.1 + 2));
            }
        }

        return $widths;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     * @return list<int>
     */
    private function detectNumericColumns(array $headers, array $rows): array
    {
        $colCount = count($headers);
        $numeric = [];

        for ($cIdx = 0; $cIdx < $colCount; $cIdx++) {
            if ($cIdx === 0) {
                continue;
            }

            $hasValue = false;
            $allNumeric = true;
            foreach ($rows as $row) {
                $value = array_values($row)[$cIdx] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $hasValue = true;
                if (! is_int($value) && ! is_float($value)) {
                    $allNumeric = false;
                    break;
                }
            }

            if ($hasValue && $allNumeric) {
                $numeric[] = $cIdx;
            }
        }

        return $numeric;
    }

    /**
     * @param  list<mixed>  $row
     */
    private function isTotalRow(array $row): bool
    {
        $first = trim((string) ($row[0] ?? ''));

        return in_array(mb_strtoupper($first), ['ИТОГО', 'ИТОГ', 'TOTAL'], true)
            || strcasecmp($first, 'Итого') === 0;
    }

    private function displayLength(mixed $value): int
    {
        if (is_int($value) || is_float($value)) {
            return mb_strlen(number_format((float) $value, 0, '.', ' '));
        }

        return mb_strlen((string) $value);
    }

    private function sanitizeSheetName(string $name): string
    {
        $name = trim($name) !== '' ? trim($name) : 'Отчет';
        $name = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name);

        return mb_substr($name, 0, 31);
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }

    private function auditExport(string $filename): void
    {
        try {
            app(SecurityAuditService::class)->log(
                'export_xlsx',
                auth()->user(),
                'report',
                $filename,
                ['filename' => $filename],
            );
        } catch (\Throwable) {
        }
    }
}
