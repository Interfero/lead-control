<?php

require __DIR__.'/../../vendor/autoload.php';

$e = new App\Services\ReportXlsxExporter();
$headers = [
    'Город',
    'Приход с Заказов',
    'Зарплата промоутеров',
    'Инкас',
    'Расход объявления',
    'Общий расход',
    'Остаток',
];
$rows = [
    ['ИТОГО', 509102, 77097, 0, 29327, 245737, 263365],
    ['Нижний Новгород', 398611, 0, 0, 0, 0, 398611],
    ['Долгопрудный (МСК)', 0, 0, 0, 0, 0, 0],
    ['Белгород', 15000, 5000, 0, 2000, 7000, 8000],
];

$bytes = $e->buildWorkbook($headers, $rows, ['sheet_name' => 'Сводка ДДС']);
$path = '/tmp/test-cfm-summary.xlsx';
file_put_contents($path, $bytes);

$z = new ZipArchive;
$z->open($path);
$sheet = $z->getFromName('xl/worksheets/sheet1.xml') ?: '';
$styles = $z->getFromName('xl/styles.xml') ?: '';
$z->close();

$checks = [
    'bytes' => strlen($bytes),
    'has_cols' => str_contains($sheet, '<cols>'),
    'no_freeze' => ! str_contains($sheet, '<pane'),
    'has_cellStyles' => str_contains($styles, '<cellStyles'),
    'has_dxfs' => str_contains($styles, '<dxfs'),
    'builtin_numfmt' => str_contains($styles, 'numFmtId="3"'),
    'no_theme_color' => ! str_contains($styles, 'theme='),
];

foreach ($checks as $k => $v) {
    echo $k.'='.$v."\n";
}

$fail = ! $checks['no_freeze'] || ! $checks['has_cellStyles'] || ! $checks['has_cols'];
exit($fail ? 1 : 0);
