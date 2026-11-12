<?php
require __DIR__ . '/includes/report_export.php';

$report = [
    'clinic' => 'Denthub Dental Clinic',
    'title' => 'Appointment Report',
    'period_label' => 'Monthly — November 2026',
    'start_display' => 'November 01, 2026',
    'end_display' => 'November 12, 2026',
    'start' => '2026-11-01',
    'end' => '2026-11-12',
    'generated' => 'November 12, 2026 8:11 PM',
    'totals' => [
        'total' => 4,
        'completed' => 0,
        'pending' => 0,
        'cancelled' => 1,
        'confirmed' => 3,
        'no_show' => 0,
    ],
    'daily' => [
        ['date' => 'November 02, 2026', 'total' => 2, 'completed' => 0, 'pending' => 0, 'cancelled' => 1, 'confirmed' => 1, 'no_show' => 0],
        ['date' => 'November 10, 2026', 'total' => 2, 'completed' => 0, 'pending' => 0, 'cancelled' => 0, 'confirmed' => 2, 'no_show' => 0],
    ],
    'services' => [
        ['name' => 'Oral Prophylaxis', 'count' => 3],
        ['name' => 'Tooth Extraction', 'count' => 1],
    ],
];

$pdf = (new ReportPdf())->render($report);
$pdfPath = sys_get_temp_dir() . '/denthub-report-test.pdf';
file_put_contents($pdfPath, $pdf);
$xlsx = report_build_xlsx($report);
$xlsxPath = sys_get_temp_dir() . '/denthub-report-test.xlsx';
file_put_contents($xlsxPath, $xlsx);

echo 'pdf_bytes=' . strlen($pdf) . PHP_EOL;
echo 'pdf_header=' . substr($pdf, 0, 8) . PHP_EOL;
echo 'has_eof=' . (str_contains($pdf, '%%EOF') ? 'yes' : 'no') . PHP_EOL;
echo 'has_http=' . (stripos($pdf, 'http') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_image=' . (str_contains($pdf, '/Image') ? 'yes' : 'no') . PHP_EOL;
echo 'has_zero=' . (str_contains($pdf, '(0)') ? 'yes' : 'no') . PHP_EOL;
echo 'has_wordmark=' . (str_contains($pdf, '(DENTHUB)') ? 'yes' : 'no') . PHP_EOL;
echo 'pages=' . substr_count($pdf, '/Type /Page ') . PHP_EOL;

$z = new ZipArchive();
if ($z->open($xlsxPath) !== true) {
    echo "xlsx_open=fail\n";
    exit(1);
}
$sheet = $z->getFromName('xl/worksheets/sheet1.xml');
$styles = $z->getFromName('xl/styles.xml');
echo 'xlsx_zero=' . (str_contains($sheet, '<v>0</v>') ? 'yes' : 'no') . PHP_EOL;
echo 'xlsx_four=' . (str_contains($sheet, '<v>4</v>') ? 'yes' : 'no') . PHP_EOL;
echo 'xlsx_styles=' . ($styles !== false ? 'yes' : 'no') . PHP_EOL;
echo 'xlsx_http_footer=' . (stripos($sheet, 'http://') !== false || stripos($sheet, 'https://') !== false ? 'yes' : 'no') . PHP_EOL;
libxml_use_internal_errors(true);
$xml = simplexml_load_string($sheet);
$styleXml = simplexml_load_string($styles);
echo 'sheet_xml=' . ($xml ? 'ok' : 'bad') . PHP_EOL;
echo 'styles_xml=' . ($styleXml ? 'ok' : 'bad') . PHP_EOL;
$z->close();
