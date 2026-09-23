<?php

require __DIR__.'/../../vendor/autoload.php';

use ZipArchive;

function buildXlsx(array $headers, array $rows, array $opts): string
{
    $withCols = $opts['cols'] ?? false;
    $withFreeze = $opts['freeze'] ?? false;
    $withStyles = $opts['styled'] ?? false;

    $sheetName = $opts['sheet'] ?? 'Worksheet';

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

    $colCount = count($headers);
    $allRows = array_merge([$headers], $rows);
    $sheetRowsXml = [];

    foreach ($allRows as $rIdx => $row) {
        $rowNum = $rIdx + 1;
        $rowAttrs = ($withStyles && $rIdx === 0) ? ' r="'.$rowNum.'" ht="42" customHeight="1"' : ' r="'.$rowNum.'"';
        $cells = [];
        foreach (array_values($row) as $cIdx => $value) {
            if ($cIdx >= $colCount) {
                break;
            }
            $col = $columnLetter($cIdx + 1);
            $ref = $col.$rowNum;
            $style = '';
            if ($withStyles) {
                $isTotal = $rIdx > 0 && mb_strtoupper(trim((string) ($row[0] ?? ''))) === 'ИТОГО';
                if ($rIdx === 0) {
                    $style = ' s="1"';
                } elseif ($isTotal) {
                    $style = ' s="'.($cIdx === 0 ? 3 : 4).'"';
                } else {
                    $style = ' s="'.($cIdx === 0 ? 5 : 2).'"';
                }
            }
            if (is_int($value) || is_float($value)) {
                $cells[] = '<c r="'.$ref.'"'.$style.'><v>'.$value.'</v></c>';
            } elseif ($value === null || $value === '') {
                if ($withStyles) {
                    $cells[] = '<c r="'.$ref.'"'.$style.'/>';
                }
            } else {
                $si = $addShared((string) $value);
                $cells[] = '<c r="'.$ref.'" t="s"'.$style.'><v>'.$si.'</v></c>';
            }
        }
        $sheetRowsXml[] = '<row'.$rowAttrs.'>'.implode('', $cells).'</row>';
    }

    $lastCol = $columnLetter(max(1, $colCount));
    $lastRow = max(1, count($allRows));

    $colsXml = '';
    if ($withCols) {
        $parts = [];
        for ($i = 1; $i <= $colCount; $i++) {
            $w = $i === 1 ? 22.8 : 14;
            $parts[] = '<col min="'.$i.'" max="'.$i.'" width="'.$w.'" customWidth="1"/>';
        }
        $colsXml = '<cols>'.implode('', $parts).'</cols>';
    }

    $withExtras = $opts['extras'] ?? false;
    $withFormatPr = ($opts['formatpr'] ?? false) || $withExtras;
    $withMargins = ($opts['margin'] ?? false) || $withExtras;

    $sheetViewsXml = $withFreeze
        ? '<sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozenSplit"/>'
            .'<selection pane="bottomLeft"/></sheetView></sheetViews>'
        : '';

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .$sheetViewsXml
        .'<dimension ref="A1:'.$lastCol.$lastRow.'"/>'
        .$colsXml
        .($withFormatPr ? '<sheetFormatPr defaultRowHeight="15"/>' : '')
        .'<sheetData>'.implode('', $sheetRowsXml).'</sheetData>'
        .($withMargins ? '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>' : '')
        .'</worksheet>';

    $sstParts = [];
    foreach ($shared as $s) {
        $sstParts[] = '<si><t>'.$xmlText($s).'</t></si>';
    }
    $sst = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'
        .count($shared).'" uniqueCount="'.count($shared).'">'
        .implode('', $sstParts).'</sst>';

    if ($withStyles) {
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE8EDF3"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border/><border><left style="thin"><color rgb="FFD0D5DD"/></left><right style="thin"><color rgb="FFD0D5DD"/></right>'
            .'<top style="thin"><color rgb="FFD0D5DD"/></top><bottom style="thin"><color rgb="FFD0D5DD"/></bottom></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="6">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="3" fontId="1" fillId="2" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'<dxfs count="0"/>'
            .'</styleSheet>';
    } else {
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
    }

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheets><sheet name="'.$xmlText($sheetName).'" sheetId="1" r:id="rId1"/></sheets>'
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

$headers = ['Город', 'Приход с Заказов', 'Зарплата промоутеров', 'Инкас', 'Расход объявления', 'Общий расход', 'Остаток'];
$rows = [
    ['ИТОГО', 509102, 77097, 0, 29327, 245737, 263365],
    ['Нижний Новгород', 398611, 0, 0, 0, 0, 398611],
];

$variants = [
    'base' => [],
    'cols' => ['cols' => true],
    'freeze' => ['freeze' => true],
    'styled' => ['styled' => true],
    'cols_freeze' => ['cols' => true, 'freeze' => true],
    'cols_styled' => ['cols' => true, 'styled' => true],
    'cols_styled7' => ['cols' => true, 'styled' => true, 'headers' => $headers, 'rows' => $rows],
    'cols_styled7_cyr' => ['cols' => true, 'styled' => true, 'headers' => $headers, 'rows' => $rows, 'sheet' => 'Сводка ДДС'],
    'cols_styled7_total' => ['cols' => true, 'styled' => true, 'headers' => $headers, 'rows' => $rows, 'sheet' => 'Сводка ДДС', 'extras' => true],
    'cols_styled7_fmt' => ['cols' => true, 'styled' => true, 'headers' => $headers, 'rows' => $rows, 'sheet' => 'Сводка ДДС', 'formatpr' => true],
    'cols_styled7_margin' => ['cols' => true, 'styled' => true, 'headers' => $headers, 'rows' => $rows, 'sheet' => 'Сводка ДДС', 'margin' => true],
    'freeze_styled' => ['freeze' => true, 'styled' => true],
    'all' => ['cols' => true, 'freeze' => true, 'styled' => true],
];

foreach ($variants as $name => $opts) {
    $h = $opts['headers'] ?? ['Город', 'Приход с Заказов', 'Остаток'];
    $r = $opts['rows'] ?? [['ИТОГО', 509102, 263365], ['Нижний Новгород', 398611, 398611]];
    $sheet = $opts['sheet'] ?? 'Worksheet';
    unset($opts['headers'], $opts['rows'], $opts['sheet']);
    $bytes = buildXlsx($h, $r, $opts + ['sheet' => $sheet]);
    file_put_contents("/tmp/xlsx-$name.xlsx", $bytes);
    echo "$name\n";
}
