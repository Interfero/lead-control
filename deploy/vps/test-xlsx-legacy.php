<?php

require __DIR__.'/../../vendor/autoload.php';

use ZipArchive;

/** Build legacy-minimal xlsx (pre-formatting) for Excel compatibility test. */
function buildLegacyXlsx(array $headers, array $rows): string
{
    $shared = [];
    $sharedIndex = [];
    $addShared = function (string $value) use (&$shared, &$sharedIndex): int {
        if (array_key_exists($value, $sharedIndex)) {
            return $sharedIndex[$value];
        }
        $idx = count($shared);
        $shared[] = $value;
        $sharedIndex[$value] = $idx;

        return $idx;
    };

    $columnLetter = function (int $index): string {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    };

    $xmlText = fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $sheetRowsXml = [];
    $allRows = array_merge([$headers], $rows);
    foreach ($allRows as $rIdx => $row) {
        $rowNum = $rIdx + 1;
        $cells = [];
        foreach (array_values($row) as $cIdx => $value) {
            $col = $columnLetter($cIdx + 1);
            $ref = $col.$rowNum;
            if (is_int($value) || is_float($value)) {
                $cells[] = '<c r="'.$ref.'"><v>'.$value.'</v></c>';
            } elseif ($value === null || $value === '') {
                continue;
            } else {
                $si = $addShared((string) $value);
                $cells[] = '<c r="'.$ref.'" t="s"><v>'.$si.'</v></c>';
            }
        }
        $sheetRowsXml[] = '<row r="'.$rowNum.'">'.implode('', $cells).'</row>';
    }

    $lastCol = $columnLetter(max(1, count($headers)));
    $lastRow = max(1, count($allRows));

    $sstParts = [];
    foreach ($shared as $s) {
        $sstParts[] = '<si><t>'.$xmlText($s).'</t></si>';
    }
    $sst = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'
        .count($shared).'" uniqueCount="'.count($shared).'">'
        .implode('', $sstParts).'</sst>';

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<dimension ref="A1:'.$lastCol.$lastRow.'"/>'
        .'<sheetData>'.implode('', $sheetRowsXml).'</sheetData>'
        .'</worksheet>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheets><sheet name="Worksheet" sheetId="1" r:id="rId1"/></sheets>'
        .'</workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
        .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        .'</Relationships>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        .'</Relationships>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        .'</Types>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
        .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
        .'<borders count="1"><border/></borders>'
        .'<cellStyleXfs count="1"><xf/></cellStyleXfs>'
        .'<cellXfs count="1"><xf/></cellXfs>'
        .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        .'<dxfs count="0"/>'
        .'</styleSheet>';

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive;
    $zip->open($tmp, ZipArchive::OVERWRITE | ZipArchive::CREATE);
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rootRels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->addFromString('xl/sharedStrings.xml', $sst);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->close();

    $bytes = file_get_contents($tmp) ?: '';
    @unlink($tmp);

    return $bytes;
}

$headers = ['Город', 'Приход с Заказов', 'Остаток'];
$rows = [
    ['ИТОГО', 509102, 263365],
    ['Нижний Новгород', 398611, 398611],
];

$legacy = buildLegacyXlsx($headers, $rows);
file_put_contents('/tmp/test-legacy.xlsx', $legacy);

$e = new App\Services\ReportXlsxExporter();
$current = $e->buildWorkbook($headers, $rows, ['sheet_name' => 'Сводка ДДС']);
file_put_contents('/tmp/test-current.xlsx', $current);

echo "legacy=".strlen($legacy)." current=".strlen($current)."\n";
