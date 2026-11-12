<?php
/**
 * Appointment report exporters: CSV, styled Excel workbook, and PDF.
 * PDF is drawn as text and shapes so a logo image cannot overlap the page,
 * and the footer is the clinic name plus a page number (no web address).
 */

function report_count($value): int
{
    if ($value === null || $value === '') {
        return 0;
    }
    return (int) round((float) $value);
}

function report_filename(string $title, string $start, string $end, string $ext): string
{
    $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', $title);
    $base = trim((string) $base, '_');
    if ($base === '') {
        $base = 'Appointment_Report';
    }
    return $base . '_' . $start . '_to_' . $end . '.' . $ext;
}

function report_period_label(string $period, string $startDisplay, string $endDisplay, string $start): string
{
    switch ($period) {
        case 'daily':
            return 'Daily — ' . $startDisplay;
        case 'weekly':
            return 'Weekly — ' . $startDisplay . ' to ' . $endDisplay;
        case 'monthly':
            $ts = strtotime($start);
            return 'Monthly — ' . ($ts ? date('F Y', $ts) : $startDisplay);
        case 'annually':
            $ts = strtotime($start);
            return 'Annual — ' . ($ts ? date('Y', $ts) : $startDisplay);
        default:
            return 'Custom range — ' . $startDisplay . ' to ' . $endDisplay;
    }
}

function report_send_csv(array $report): void
{
    $name = report_filename($report['title'], $report['start'], $report['end'], 'csv');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($out, ['Clinic', $report['clinic']]);
    fputcsv($out, ['Report', $report['title']]);
    fputcsv($out, ['Period', $report['period_label']]);
    fputcsv($out, ['Start date', $report['start_display']]);
    fputcsv($out, ['End date', $report['end_display']]);
    fputcsv($out, ['Generated', $report['generated']]);
    fputcsv($out, []);

    fputcsv($out, ['Summary']);
    fputcsv($out, ['Metric', 'Count']);
    $totals = $report['totals'];
    foreach ([
        'Total Appointments' => 'total',
        'Completed' => 'completed',
        'Pending' => 'pending',
        'Cancelled' => 'cancelled',
        'Confirmed' => 'confirmed',
        'No-show' => 'no_show',
    ] as $label => $key) {
        fputcsv($out, [$label, (int) $totals[$key]]);
    }
    fputcsv($out, []);

    fputcsv($out, ['Daily breakdown']);
    fputcsv($out, ['Date', 'Total Appointments', 'Completed', 'Pending', 'Cancelled', 'Confirmed', 'No-show']);
    if (!$report['daily']) {
        fputcsv($out, ['No appointments in this period', 0, 0, 0, 0, 0, 0]);
    } else {
        foreach ($report['daily'] as $row) {
            fputcsv($out, [
                $row['date'],
                (int) $row['total'],
                (int) $row['completed'],
                (int) $row['pending'],
                (int) $row['cancelled'],
                (int) $row['confirmed'],
                (int) $row['no_show'],
            ]);
        }
        fputcsv($out, [
            'Period total',
            (int) $totals['total'],
            (int) $totals['completed'],
            (int) $totals['pending'],
            (int) $totals['cancelled'],
            (int) $totals['confirmed'],
            (int) $totals['no_show'],
        ]);
    }
    fputcsv($out, []);

    fputcsv($out, ['Service statistics']);
    fputcsv($out, ['Service', 'Number of Appointments']);
    if (!$report['services']) {
        fputcsv($out, ['No services in this period', 0]);
    } else {
        foreach ($report['services'] as $row) {
            fputcsv($out, [$row['name'], (int) $row['count']]);
        }
    }

    fclose($out);
    exit;
}

function report_send_xlsx(array $report): void
{
    if (!class_exists('ZipArchive')) {
        report_send_xls_html($report);
    }

    $name = report_filename($report['title'], $report['start'], $report['end'], 'xlsx');
    $binary = report_build_xlsx($report);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Content-Length: ' . strlen($binary));
    echo $binary;
    exit;
}

function report_send_pdf(array $report): void
{
    $name = report_filename($report['title'], $report['start'], $report['end'], 'pdf');
    $pdf = (new ReportPdf())->render($report);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

function report_xlsx_col(int $index): string
{
    $s = '';
    while ($index > 0) {
        $index--;
        $s = chr(65 + ($index % 26)) . $s;
        $index = intdiv($index, 26);
    }
    return $s;
}

function report_build_xlsx(array $report): string
{
    $sheet = report_build_sheet_xml($report);
    $files = [
        '[Content_Types].xml' => report_xlsx_content_types(),
        '_rels/.rels' => report_xlsx_root_rels(),
        'xl/workbook.xml' => report_xlsx_workbook($report['title']),
        'xl/_rels/workbook.xml.rels' => report_xlsx_workbook_rels(),
        'xl/styles.xml' => report_xlsx_styles(),
        'xl/worksheets/sheet1.xml' => $sheet,
    ];

    $tmp = tempnam(sys_get_temp_dir(), 'dhr');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        report_send_xls_html($report);
    }
    foreach ($files as $path => $xml) {
        $zip->addFromString($path, $xml);
    }
    $zip->close();
    $binary = file_get_contents($tmp);
    @unlink($tmp);
    if ($binary === false) {
        report_send_xls_html($report);
    }
    return $binary;
}

function report_xlsx_content_types(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';
}

function report_xlsx_root_rels(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
}

function report_xlsx_workbook(string $title): string
{
    $name = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $title);
    $name = trim((string) $name);
    if ($name === '') {
        $name = 'Appointment Report';
    }
    if (strlen($name) > 31) {
        $name = substr($name, 0, 31);
    }
    $name = htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';
}

function report_xlsx_workbook_rels(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
}

function report_xlsx_styles(): string
{
    $fonts = [
        '<font><sz val="11"/><color rgb="FF212529"/><name val="Calibri"/><family val="2"/></font>',
        '<font><b/><sz val="20"/><color rgb="FF004AAD"/><name val="Calibri"/><family val="2"/></font>',
        '<font><b/><sz val="13"/><color rgb="FF004AAD"/><name val="Calibri"/><family val="2"/></font>',
        '<font><sz val="11"/><color rgb="FF5C6570"/><name val="Calibri"/><family val="2"/></font>',
        '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>',
        '<font><b/><sz val="12"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>',
        '<font><b/><sz val="11"/><color rgb="FF212529"/><name val="Calibri"/><family val="2"/></font>',
        '<font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>',
        '<font><b/><sz val="16"/><color rgb="FF212529"/><name val="Calibri"/><family val="2"/></font>',
    ];
    $fills = [
        '<fill><patternFill patternType="none"/></fill>',
        '<fill><patternFill patternType="gray125"/></fill>',
        '<fill><patternFill patternType="solid"><fgColor rgb="FF004AAD"/><bgColor indexed="64"/></patternFill></fill>',
        '<fill><patternFill patternType="solid"><fgColor rgb="FF063E86"/><bgColor indexed="64"/></patternFill></fill>',
        '<fill><patternFill patternType="solid"><fgColor rgb="FFF4F8FD"/><bgColor indexed="64"/></patternFill></fill>',
        '<fill><patternFill patternType="solid"><fgColor rgb="FFE7F0FB"/><bgColor indexed="64"/></patternFill></fill>',
        '<fill><patternFill patternType="solid"><fgColor rgb="FF198754"/><bgColor indexed="64"/></patternFill></fill>',
        '<fill><patternFill patternType="solid"><fgColor rgb="FFFFC107"/><bgColor indexed="64"/></patternFill></fill>',
        '<fill><patternFill patternType="solid"><fgColor rgb="FFDC3545"/><bgColor indexed="64"/></patternFill></fill>',
        '<fill><patternFill patternType="solid"><fgColor rgb="FF0AA2C0"/><bgColor indexed="64"/></patternFill></fill>',
        '<fill><patternFill patternType="solid"><fgColor rgb="FF363848"/><bgColor indexed="64"/></patternFill></fill>',
    ];
    $thin = '<left style="thin"><color rgb="FFD0D7E2"/></left><right style="thin"><color rgb="FFD0D7E2"/></right><top style="thin"><color rgb="FFD0D7E2"/></top><bottom style="thin"><color rgb="FFD0D7E2"/></bottom>';
    $borders = [
        '<border><left/><right/><top/><bottom/><diagonal/></border>',
        '<border>' . $thin . '<diagonal/></border>',
    ];

    $align = '<alignment horizontal="center" vertical="center" wrapText="1"/>';
    $left = '<alignment horizontal="left" vertical="center"/>';
    $xfs = [
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>',
        '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1">' . $left . '</xf>',
        '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1">' . $left . '</xf>',
        '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1">' . $left . '</xf>',
        '<xf numFmtId="0" fontId="5" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1">' . $left . '</xf>',
        '<xf numFmtId="0" fontId="4" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>',
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">' . $left . '</xf>',
        '<xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>',
        '<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1">' . $left . '</xf>',
        '<xf numFmtId="1" fontId="0" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>',
        '<xf numFmtId="0" fontId="6" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $left . '</xf>',
        '<xf numFmtId="1" fontId="6" fillId="5" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>',
        '<xf numFmtId="0" fontId="4" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>',
        '<xf numFmtId="1" fontId="7" fillId="2" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>',
        '<xf numFmtId="0" fontId="4" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>',
        '<xf numFmtId="1" fontId="7" fillId="6" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>',
        '<xf numFmtId="0" fontId="6" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>',
        '<xf numFmtId="1" fontId="8" fillId="7" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>',
        '<xf numFmtId="0" fontId="4" fillId="8" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>',
        '<xf numFmtId="1" fontId="7" fillId="8" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>',
        '<xf numFmtId="0" fontId="4" fillId="9" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>',
        '<xf numFmtId="1" fontId="7" fillId="9" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>',
        '<xf numFmtId="0" fontId="4" fillId="10" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>',
        '<xf numFmtId="1" fontId="7" fillId="10" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $align . '</xf>',
    ];

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="' . count($fonts) . '">' . implode('', $fonts) . '</fonts>'
        . '<fills count="' . count($fills) . '">' . implode('', $fills) . '</fills>'
        . '<borders count="' . count($borders) . '">' . implode('', $borders) . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="' . count($xfs) . '">' . implode('', $xfs) . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

function report_sheet_cell(int $col, int $row, $value, int $style, bool $number): string
{
    $ref = report_xlsx_col($col) . $row;
    if ($number) {
        return '<c r="' . $ref . '" s="' . $style . '"><v>' . (int) $value . '</v></c>';
    }
    $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    return '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . $text . '</t></is></c>';
}

function report_build_sheet_xml(array $report): string
{
    $rows = [];
    $merges = [];
    $heights = [];
    $r = 1;
    $t = $report['totals'];

    $rows[$r] = [report_sheet_cell(1, $r, $report['clinic'], 1, false)];
    $merges[] = 'A1:G1';
    $heights[$r] = 28;
    $r++;

    $rows[$r] = [report_sheet_cell(1, $r, $report['title'], 2, false)];
    $merges[] = 'A' . $r . ':G' . $r;
    $heights[$r] = 20;
    $r++;

    foreach ([
        'Period: ' . $report['period_label'],
        'From ' . $report['start_display'] . ' to ' . $report['end_display'],
        'Generated ' . $report['generated'],
    ] as $line) {
        $rows[$r] = [report_sheet_cell(1, $r, $line, 3, false)];
        $merges[] = 'A' . $r . ':G' . $r;
        $r++;
    }

    $r++;
    $rows[$r] = [report_sheet_cell(1, $r, 'SUMMARY', 4, false)];
    $merges[] = 'A' . $r . ':G' . $r;
    $heights[$r] = 22;
    $r++;

    $summary = [
        ['Total Appointments', (int) $t['total'], 12, 13],
        ['Completed', (int) $t['completed'], 14, 15],
        ['Pending', (int) $t['pending'], 16, 17],
        ['Cancelled', (int) $t['cancelled'], 18, 19],
        ['Confirmed', (int) $t['confirmed'], 20, 21],
        ['No-show', (int) $t['no_show'], 22, 23],
    ];
    $labelRow = $r;
    $valueRow = $r + 1;
    $labels = [];
    $values = [];
    foreach ($summary as $i => $item) {
        $labels[] = report_sheet_cell($i + 1, $labelRow, $item[0], $item[2], false);
        $values[] = report_sheet_cell($i + 1, $valueRow, $item[1], $item[3], true);
    }
    $rows[$labelRow] = $labels;
    $rows[$valueRow] = $values;
    $heights[$labelRow] = 22;
    $heights[$valueRow] = 28;
    $r = $valueRow + 2;

    $rows[$r] = [report_sheet_cell(1, $r, 'DAILY BREAKDOWN', 4, false)];
    $merges[] = 'A' . $r . ':G' . $r;
    $heights[$r] = 22;
    $r++;

    $headers = ['Date', 'Total', 'Completed', 'Pending', 'Cancelled', 'Confirmed', 'No-show'];
    $head = [];
    foreach ($headers as $i => $label) {
        $head[] = report_sheet_cell($i + 1, $r, $label, 5, false);
    }
    $rows[$r] = $head;
    $heights[$r] = 20;
    $r++;

    if (!$report['daily']) {
        $rows[$r] = [report_sheet_cell(1, $r, 'No appointments in this period', 6, false)];
        for ($c = 2; $c <= 7; $c++) {
            $rows[$r][] = report_sheet_cell($c, $r, 0, 7, true);
        }
        $r++;
    } else {
        foreach ($report['daily'] as $index => $day) {
            $textStyle = ($index % 2 === 1) ? 8 : 6;
            $numStyle = ($index % 2 === 1) ? 9 : 7;
            $rows[$r] = [
                report_sheet_cell(1, $r, $day['date'], $textStyle, false),
                report_sheet_cell(2, $r, (int) $day['total'], $numStyle, true),
                report_sheet_cell(3, $r, (int) $day['completed'], $numStyle, true),
                report_sheet_cell(4, $r, (int) $day['pending'], $numStyle, true),
                report_sheet_cell(5, $r, (int) $day['cancelled'], $numStyle, true),
                report_sheet_cell(6, $r, (int) $day['confirmed'], $numStyle, true),
                report_sheet_cell(7, $r, (int) $day['no_show'], $numStyle, true),
            ];
            $r++;
        }
        $rows[$r] = [
            report_sheet_cell(1, $r, 'Period total', 10, false),
            report_sheet_cell(2, $r, (int) $t['total'], 11, true),
            report_sheet_cell(3, $r, (int) $t['completed'], 11, true),
            report_sheet_cell(4, $r, (int) $t['pending'], 11, true),
            report_sheet_cell(5, $r, (int) $t['cancelled'], 11, true),
            report_sheet_cell(6, $r, (int) $t['confirmed'], 11, true),
            report_sheet_cell(7, $r, (int) $t['no_show'], 11, true),
        ];
        $r++;
    }

    $r++;
    $rows[$r] = [report_sheet_cell(1, $r, 'SERVICE STATISTICS', 4, false)];
    $merges[] = 'A' . $r . ':G' . $r;
    $heights[$r] = 22;
    $r++;

    $rows[$r] = [
        report_sheet_cell(1, $r, 'Service', 5, false),
        report_sheet_cell(2, $r, 'Number of Appointments', 5, false),
    ];
    $heights[$r] = 20;
    $r++;

    if (!$report['services']) {
        $rows[$r] = [
            report_sheet_cell(1, $r, 'No services in this period', 6, false),
            report_sheet_cell(2, $r, 0, 7, true),
        ];
        $r++;
    } else {
        foreach ($report['services'] as $index => $service) {
            $textStyle = ($index % 2 === 1) ? 8 : 6;
            $numStyle = ($index % 2 === 1) ? 9 : 7;
            $rows[$r] = [
                report_sheet_cell(1, $r, $service['name'], $textStyle, false),
                report_sheet_cell(2, $r, (int) $service['count'], $numStyle, true),
            ];
            $r++;
        }
    }

    $sheetRows = '';
    ksort($rows);
    foreach ($rows as $num => $cells) {
        $ht = isset($heights[$num]) ? ' ht="' . $heights[$num] . '" customHeight="1"' : '';
        $sheetRows .= '<row r="' . $num . '"' . $ht . '>' . implode('', $cells) . '</row>';
    }

    $mergeXml = '';
    if ($merges) {
        $mergeXml = '<mergeCells count="' . count($merges) . '">';
        foreach ($merges as $ref) {
            $mergeXml .= '<mergeCell ref="' . $ref . '"/>';
        }
        $mergeXml .= '</mergeCells>';
    }

    $footer = 'Denthub Dental Clinic  |  ' . htmlspecialchars($report['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8');

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
        . '<dimension ref="A1:G' . max(1, $r - 1) . '"/>'
        . '<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="18"/>'
        . '<cols>'
        . '<col min="1" max="1" width="36" customWidth="1"/>'
        . '<col min="2" max="7" width="22" customWidth="1"/>'
        . '</cols>'
        . '<sheetData>' . $sheetRows . '</sheetData>'
        . $mergeXml
        . '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.25" footer="0.25"/>'
        . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/>'
        . '<headerFooter>'
        . '<oddFooter xml:space="preserve">&amp;L' . $footer . '&amp;RPage &amp;P of &amp;N</oddFooter>'
        . '</headerFooter>'
        . '</worksheet>';
}

function report_send_xls_html(array $report): void
{
    $name = report_filename($report['title'], $report['start'], $report['end'], 'xls');
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo report_xls_html($report);
    exit;
}

function report_xls_html(array $report): string
{
    $h = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    $t = $report['totals'];
    $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8">'
        . '<style>'
        . 'td{font-family:Calibri,Arial,sans-serif;font-size:11pt;padding:6px 8px;}'
        . '.title{font-size:20pt;font-weight:bold;color:#004AAD;}'
        . '.meta{color:#5c6570;}'
        . '.section{background:#004AAD;color:#fff;font-weight:bold;}'
        . '.th{background:#063E86;color:#fff;font-weight:bold;border:1px solid #d0d7e2;text-align:center;}'
        . '.td{border:1px solid #d0d7e2;}'
        . '.num{border:1px solid #d0d7e2;text-align:center;mso-number-format:"0";}'
        . '.zebra{background:#F4F8FD;}'
        . '.total{background:#E7F0FB;font-weight:bold;}'
        . '</style></head><body><table>';
    $html .= '<tr><td class="title" colspan="7">' . $h($report['clinic']) . '</td></tr>';
    $html .= '<tr><td colspan="7"><b>' . $h($report['title']) . '</b></td></tr>';
    $html .= '<tr><td class="meta" colspan="7">' . $h($report['period_label']) . '</td></tr>';
    $html .= '<tr><td class="meta" colspan="7">From ' . $h($report['start_display']) . ' to ' . $h($report['end_display']) . '</td></tr>';
    $html .= '<tr><td class="meta" colspan="7">Generated ' . $h($report['generated']) . '</td></tr>';
    $html .= '<tr><td colspan="7"></td></tr>';
    $html .= '<tr><td class="section" colspan="7">SUMMARY</td></tr>';
    $pairs = [
        ['Total Appointments', (int) $t['total'], '#004AAD'],
        ['Completed', (int) $t['completed'], '#198754'],
        ['Pending', (int) $t['pending'], '#FFC107'],
        ['Cancelled', (int) $t['cancelled'], '#DC3545'],
        ['Confirmed', (int) $t['confirmed'], '#0AA2C0'],
        ['No-show', (int) $t['no_show'], '#363848'],
    ];
    $html .= '<tr>';
    foreach ($pairs as $pair) {
        $color = $pair[0] === 'Pending' ? '#212529' : '#fff';
        $html .= '<td style="background:' . $pair[2] . ';color:' . $color . ';font-weight:bold;text-align:center;">' . $h($pair[0]) . '</td>';
    }
    $html .= '<td></td></tr><tr>';
    foreach ($pairs as $pair) {
        $color = $pair[0] === 'Pending' ? '#212529' : '#fff';
        $html .= '<td class="num" style="background:' . $pair[2] . ';color:' . $color . ';font-weight:bold;font-size:16pt;text-align:center;">' . $pair[1] . '</td>';
    }
    $html .= '<td></td></tr>';
    $html .= '<tr><td colspan="7"></td></tr><tr><td class="section" colspan="7">DAILY BREAKDOWN</td></tr>';
    $html .= '<tr><td class="th">Date</td><td class="th">Total</td><td class="th">Completed</td><td class="th">Pending</td><td class="th">Cancelled</td><td class="th">Confirmed</td><td class="th">No-show</td></tr>';
    if (!$report['daily']) {
        $html .= '<tr><td class="td">No appointments in this period</td><td class="num">0</td><td class="num">0</td><td class="num">0</td><td class="num">0</td><td class="num">0</td><td class="num">0</td></tr>';
    } else {
        foreach ($report['daily'] as $i => $day) {
            $z = ($i % 2 === 1) ? ' zebra' : '';
            $html .= '<tr>'
                . '<td class="td' . $z . '">' . $h($day['date']) . '</td>'
                . '<td class="num' . $z . '">' . (int) $day['total'] . '</td>'
                . '<td class="num' . $z . '">' . (int) $day['completed'] . '</td>'
                . '<td class="num' . $z . '">' . (int) $day['pending'] . '</td>'
                . '<td class="num' . $z . '">' . (int) $day['cancelled'] . '</td>'
                . '<td class="num' . $z . '">' . (int) $day['confirmed'] . '</td>'
                . '<td class="num' . $z . '">' . (int) $day['no_show'] . '</td>'
                . '</tr>';
        }
        $html .= '<tr class="total"><td class="td total">Period total</td>'
            . '<td class="num total">' . (int) $t['total'] . '</td>'
            . '<td class="num total">' . (int) $t['completed'] . '</td>'
            . '<td class="num total">' . (int) $t['pending'] . '</td>'
            . '<td class="num total">' . (int) $t['cancelled'] . '</td>'
            . '<td class="num total">' . (int) $t['confirmed'] . '</td>'
            . '<td class="num total">' . (int) $t['no_show'] . '</td></tr>';
    }
    $html .= '<tr><td colspan="7"></td></tr><tr><td class="section" colspan="7">SERVICE STATISTICS</td></tr>';
    $html .= '<tr><td class="th">Service</td><td class="th">Number of Appointments</td></tr>';
    if (!$report['services']) {
        $html .= '<tr><td class="td">No services in this period</td><td class="num">0</td></tr>';
    } else {
        foreach ($report['services'] as $i => $service) {
            $z = ($i % 2 === 1) ? ' zebra' : '';
            $html .= '<tr><td class="td' . $z . '">' . $h($service['name']) . '</td><td class="num' . $z . '">' . (int) $service['count'] . '</td></tr>';
        }
    }
    $html .= '</table></body></html>';
    return $html;
}

/**
 * Compact PDF writer for the appointment report.
 */
class ReportPdf
{
    private float $pageW = 595.28;
    private float $pageH = 841.89;
    private float $margin = 36.0;
    private float $bottom = 42.0;
    private float $cursor = 0;
    private int $pageIndex = 0;
    private bool $open = false;
    private string $buf = '';
    private array $pages = [];
    private array $report = [];

    public function render(array $report): string
    {
        $this->report = $report;
        $this->pages = [];
        $this->buf = '';
        $this->open = false;
        $this->pageIndex = 0;

        $this->newPage(true);
        $this->drawSummary();
        $this->drawTable(
            'Daily breakdown',
            [
                ['key' => 'date', 'label' => 'Date', 'width' => 118, 'align' => 'left'],
                ['key' => 'total', 'label' => 'Total', 'width' => 62, 'align' => 'center'],
                ['key' => 'completed', 'label' => 'Completed', 'width' => 70, 'align' => 'center'],
                ['key' => 'pending', 'label' => 'Pending', 'width' => 62, 'align' => 'center'],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'width' => 68, 'align' => 'center'],
                ['key' => 'confirmed', 'label' => 'Confirmed', 'width' => 70, 'align' => 'center'],
                ['key' => 'no_show', 'label' => 'No-show', 'width' => 73, 'align' => 'center'],
            ],
            $report['daily'],
            [
                'date' => 'Period total',
                'total' => (int) $report['totals']['total'],
                'completed' => (int) $report['totals']['completed'],
                'pending' => (int) $report['totals']['pending'],
                'cancelled' => (int) $report['totals']['cancelled'],
                'confirmed' => (int) $report['totals']['confirmed'],
                'no_show' => (int) $report['totals']['no_show'],
            ]
        );
        $this->drawTable(
            'Service statistics',
            [
                ['key' => 'name', 'label' => 'Service', 'width' => 400, 'align' => 'left'],
                ['key' => 'count', 'label' => 'Appointments', 'width' => 123, 'align' => 'center'],
            ],
            $report['services'],
            null
        );
        $this->finishPage();
        return $this->buildFile();
    }

    private function newPage(bool $first): void
    {
        if ($this->open) {
            $this->drawFooter();
            $this->pages[] = $this->buf;
            $this->buf = '';
        }
        $this->open = true;
        $this->pageIndex++;
        $this->drawHeader($first);
    }

    private function finishPage(): void
    {
        if (!$this->open) {
            return;
        }
        $this->drawFooter();
        $this->pages[] = $this->buf;
        $this->buf = '';
        $this->open = false;
    }

    private function ensure(float $height): void
    {
        if ($this->cursor + $height > $this->pageH - $this->bottom) {
            $this->newPage(false);
        }
    }

    private function drawHeader(bool $first): void
    {
        $this->fill(0, 0, $this->pageW, 62, [0.0, 0.290, 0.678]);
        $this->fill(0, 62, $this->pageW, 4, [0.733, 0.835, 0.976]);
        $this->text(36, 16, 16, 'DENTHUB', true, [1, 1, 1]);
        $this->text(36, 36, 9, $this->report['clinic'], false, [0.85, 0.91, 0.98]);

        $title = $this->fit($this->report['title'], 250, 11, true);
        $this->text($this->pageW - 36 - $this->textWidth($title, 11, true), 16, 11, $title, true, [1, 1, 1]);
        $period = $this->fit($this->report['period_label'], 280, 8, false);
        $this->text($this->pageW - 36 - $this->textWidth($period, 8, false), 34, 8, $period, false, [0.85, 0.91, 0.98]);

        $meta = 'From ' . $this->report['start_display'] . ' to ' . $this->report['end_display']
            . '    Generated ' . $this->report['generated'];
        if (!$first) {
            $meta .= '    (continued)';
        }
        $this->text(36, 78, 8, $this->fit($meta, $this->pageW - 72, 8, false), false, [0.36, 0.40, 0.45]);
        $this->cursor = 96;
    }

    private function drawFooter(): void
    {
        $y = $this->pageH - 28;
        $this->buf .= sprintf("0.82 0.85 0.88 RG 0.6 w %.2f %.2f m %.2f %.2f l S\n", 36, $this->pageH - $y, $this->pageW - 36, $this->pageH - $y);
        $left = $this->report['clinic'] . '  |  Appointment report';
        $this->text(36, $y + 6, 8, $left, false, [0.36, 0.40, 0.45]);
        $page = 'Page ' . $this->pageIndex;
        $this->text($this->pageW - 36 - $this->textWidth($page, 8, false), $y + 6, 8, $page, false, [0.36, 0.40, 0.45]);
    }

    private function drawSummary(): void
    {
        $this->ensure(92);
        $this->text($this->margin, $this->cursor, 11, 'Summary', true, [0.0, 0.290, 0.678]);
        $this->cursor += 16;

        $cards = [
            ['Total Appointments', (int) $this->report['totals']['total'], [0.0, 0.290, 0.678], [1, 1, 1]],
            ['Completed', (int) $this->report['totals']['completed'], [0.098, 0.529, 0.329], [1, 1, 1]],
            ['Pending', (int) $this->report['totals']['pending'], [1.0, 0.757, 0.027], [0.13, 0.15, 0.16]],
            ['Cancelled', (int) $this->report['totals']['cancelled'], [0.863, 0.208, 0.271], [1, 1, 1]],
        ];
        $gap = 8;
        $width = ($this->pageW - ($this->margin * 2) - ($gap * 3)) / 4;
        $x = $this->margin;
        $top = $this->cursor;
        foreach ($cards as $card) {
            $this->fill($x, $top, $width, 52, $card[2]);
            $label = $this->fit($card[0], $width - 12, 8, false);
            $this->text($x + 8, $top + 8, 8, $label, false, $card[3]);
            $value = (string) $card[1];
            $this->text($x + 8, $top + 26, 16, $value, true, $card[3]);
            $x += $width + $gap;
        }
        $this->cursor += 64;

        $extra = [];
        if ((int) $this->report['totals']['confirmed'] > 0) {
            $extra[] = 'Confirmed ' . (int) $this->report['totals']['confirmed'];
        }
        if ((int) $this->report['totals']['no_show'] > 0) {
            $extra[] = 'No-show ' . (int) $this->report['totals']['no_show'];
        }
        if ($extra) {
            $this->text($this->margin, $this->cursor, 8, 'Also in this period: ' . implode('   ', $extra), false, [0.36, 0.40, 0.45]);
            $this->cursor += 16;
        }
    }

    private function drawTable(string $heading, array $columns, array $rows, ?array $totals): void
    {
        $this->ensure(16 + 20 + 18);
        $this->cursor += 6;
        $this->text($this->margin, $this->cursor, 11, $heading, true, [0.0, 0.290, 0.678]);
        $this->cursor += 16;
        $this->drawColumnHeader($columns);

        if (!$rows) {
            $this->ensure(18);
            $this->fill($this->margin, $this->cursor, $this->tableWidth($columns), 18, [0.976, 0.980, 0.984]);
            $this->text($this->margin + 6, $this->cursor + 5, 8, 'No records for this period.', false, [0.36, 0.40, 0.45]);
            $this->cursor += 18;
            return;
        }

        foreach ($rows as $index => $row) {
            $this->ensure(18);
            if ($this->cursor < 100) {
                $this->drawColumnHeader($columns);
            }
            $bg = ($index % 2 === 1) ? [0.957, 0.973, 0.992] : [1, 1, 1];
            $this->drawDataRow($columns, $row, $bg, false);
        }

        if ($totals) {
            $this->ensure(18);
            if ($this->cursor < 100) {
                $this->drawColumnHeader($columns);
            }
            $this->drawDataRow($columns, $totals, [0.906, 0.941, 0.984], true);
        }
    }

    private function drawColumnHeader(array $columns): void
    {
        $this->ensure(20);
        $this->fill($this->margin, $this->cursor, $this->tableWidth($columns), 20, [0.024, 0.243, 0.525]);
        $x = $this->margin;
        foreach ($columns as $col) {
            $label = $this->fit($col['label'], $col['width'] - 8, 8, true);
            $tx = $col['align'] === 'left'
                ? $x + 6
                : $x + ($col['width'] - $this->textWidth($label, 8, true)) / 2;
            $this->text($tx, $this->cursor + 6, 8, $label, true, [1, 1, 1]);
            $x += $col['width'];
        }
        $this->cursor += 20;
    }

    private function drawDataRow(array $columns, array $row, array $bg, bool $bold): void
    {
        $this->fill($this->margin, $this->cursor, $this->tableWidth($columns), 18, $bg);
        $x = $this->margin;
        foreach ($columns as $col) {
            $raw = $row[$col['key']] ?? '';
            $text = $this->fit((string) $raw, $col['width'] - 10, 8, $bold);
            $tx = $col['align'] === 'left'
                ? $x + 6
                : $x + ($col['width'] - $this->textWidth($text, 8, $bold)) / 2;
            $this->text($tx, $this->cursor + 5, 8, $text, $bold, [0.13, 0.15, 0.16]);
            $x += $col['width'];
        }
        $this->cursor += 18;
    }

    private function tableWidth(array $columns): float
    {
        $w = 0;
        foreach ($columns as $col) {
            $w += $col['width'];
        }
        return $w;
    }

    private function fill(float $x, float $top, float $w, float $h, array $rgb): void
    {
        $y = $this->pageH - $top - $h;
        $this->buf .= sprintf(
            "%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f\n",
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $x,
            $y,
            $w,
            $h
        );
    }

    private function text(float $x, float $top, float $size, string $str, bool $bold, array $rgb): void
    {
        $y = $this->pageH - $top - $size;
        $font = $bold ? 'F2' : 'F1';
        $this->buf .= sprintf(
            "%.3f %.3f %.3f rg BT /%s %.2f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $font,
            $size,
            $x,
            $y,
            $this->esc($str)
        );
    }

    private function textWidth(string $str, float $size, bool $bold): float
    {
        return strlen($this->esc($str)) * $size * ($bold ? 0.56 : 0.50);
    }

    private function fit(string $str, float $maxWidth, float $size, bool $bold): string
    {
        $str = trim(preg_replace('/\s+/', ' ', $str) ?? '');
        if ($this->textWidth($str, $size, $bold) <= $maxWidth) {
            return $str;
        }
        $ellipsis = '...';
        while ($str !== '' && $this->textWidth($str . $ellipsis, $size, $bold) > $maxWidth) {
            $str = substr($str, 0, -1);
        }
        return $str . $ellipsis;
    }

    private function esc(string $text): string
    {
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        return (string) preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '', $text);
    }

    private function buildFile(): string
    {
        $objects = [];
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $id = 5;
        $pageIds = [];
        foreach ($this->pages as $stream) {
            if ($stream === '' || substr($stream, -1) !== "\n") {
                $stream .= "\n";
            }
            $pageId = $id++;
            $contentId = $id++;
            $pageIds[] = $pageId;
            $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Contents ' . $contentId . ' 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> >>';
        }
        $kids = implode(' ', array_map(static function ($pageId) {
            return $pageId . ' 0 R';
        }, $pageIds));
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pageIds) . ' >>';

        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        $max = max(array_keys($objects));
        for ($i = 1; $i <= $max; $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n" . '0 ' . ($max + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }
}
