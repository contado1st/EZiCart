<?php

namespace Tests\Feature;

use App\Services\AnalyticsExportService;
use App\Services\TabularExportService;
use Tests\TestCase;
use ZipArchive;

class AnalyticsExportFormattingTest extends TestCase
{
    public function test_excel_has_printable_headers_formats_sortable_dates_and_literal_identifiers(): void
    {
        $content = app(AnalyticsExportService::class)->xlsx($this->report());
        $path = tempnam(sys_get_temp_dir(), 'analytics-format-');
        try {
            file_put_contents($path, $content);
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $summary = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
            $this->assertSame('A8', (string) $summary->sheetViews->sheetView->pane['topLeftCell']);
            $this->assertSame('7', (string) $summary->sheetViews->sheetView->pane['ySplit']);
            $this->assertSame('A7:E9', (string) $summary->autoFilter['ref']);
            $this->assertSame('landscape', (string) $summary->pageSetup['orientation']);
            $this->assertSame('5', (string) $summary->mergeCells['count']);
            $cells = [];
            foreach ($summary->sheetData->row as $row) {
                foreach ($row->c as $cell) {
                    $cells[(string) $cell['r']] = $cell;
                }
            }
            $this->assertSame('100', (string) $cells['B8']->v);
            $this->assertSame('7', (string) $cells['B8']['s']);
            $this->assertSame('-0.25', (string) $cells['D8']->v);
            $this->assertSame('11', (string) $cells['D8']['s']);
            $this->assertSame('Not available', (string) $cells['B9']->is->t);
            $this->assertStringContainsString('_xlnm.Print_Titles', $zip->getFromName('xl/workbook.xml'));
            $this->assertStringContainsString('yyyy-mm-dd', $zip->getFromName('xl/styles.xml'));
            $sheetXml = '';
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->getFromIndex($index);
                $this->assertNotFalse(simplexml_load_string($entry));
                $sheetXml .= $entry;
            }
            $this->assertStringContainsString('000123', $sheetXml);
            $this->assertStringContainsString('=UNTRUSTED-TEXT', $sheetXml);
            $this->assertStringNotContainsString('<f>', $sheetXml);
            $this->assertStringContainsString('<v>45932</v>', $sheetXml); // 2025-10-02 as an Excel serial date.
            $zip->close();
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_csv_uses_consistent_columns_safe_text_and_numeric_negative_values(): void
    {
        ob_start();
        app(AnalyticsExportService::class)->csv($this->report());
        $csv = ob_get_clean();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $rows = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), explode("\n", trim(substr($csv, 3))));
        $width = count($rows[0]);
        foreach ($rows as $row) {
            $this->assertCount($width, $row);
        }
        $this->assertSame('Report section', $rows[0][0]);
        $this->assertStringContainsString("'=UNTRUSTED-TEXT", $csv);
        $this->assertStringContainsString('-25.00', $csv);
        $this->assertStringNotContainsString("'-25.00", $csv);
        $this->assertStringContainsString('100.00', $csv);
        $this->assertStringContainsString('Not available', $csv);
    }

    public function test_pdf_tables_paginate_repeat_identifiers_and_keep_legacy_pdf_output_working(): void
    {
        $report = $this->report();
        $row = $report['sections'][0]['rows'][0];
        for ($column = 1; $column <= 10; $column++) {
            $row['Additional column '.$column] = $column;
        }
        $report['sections'][0]['rows'] = array_map(fn ($index) => array_replace($row, ['Order' => 'ROW-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT)]), range(1, 60));
        $pdf = app(AnalyticsExportService::class)->pdf($report);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('/MediaBox [0 0 595 842]', $pdf);
        $this->assertStringContainsString('/MediaBox [0 0 842 595]', $pdf);
        $this->assertStringContainsString('Helvetica-Bold', $pdf);
        $this->assertStringContainsString('Columns 1 of 3', $pdf);
        $this->assertStringContainsString('Columns 3 of 3', $pdf);
        $this->assertGreaterThanOrEqual(3, substr_count($pdf, '(ROW-060)'));
        $this->assertStringContainsString('Page 1 of ', $pdf);
        $this->assertStringNotContainsString('rgBT', $pdf);
        $legacy = app(TabularExportService::class)->pdf(['Legacy sales record']);
        $this->assertStringContainsString('(Legacy sales record)', $legacy);
        $this->assertStringContainsString('/MediaBox [0 0 595 842]', $legacy);
    }

    private function report(): array
    {
        return [
            'title' => 'Seller analytics', 'role' => 'seller', 'commercial' => true,
            'generated_at' => '2025-10-03T12:00:00+08:00', 'timezone' => 'Asia/Manila', 'currency' => 'PHP',
            'filters' => ['from' => '2025-10-01', 'to' => '2025-10-03', 'interval' => 'day', 'comparison' => 'previous'],
            'comparison' => ['from' => '2025-09-28', 'to' => '2025-09-30'],
            'metrics' => [
                ['key' => 'net', 'label' => 'Net seller sales', 'unit' => 'money', 'value' => 100, 'previous' => 133.33, 'change' => -25],
                ['key' => 'duration', 'label' => 'Delivery duration', 'unit' => 'hours', 'value' => null, 'previous' => null, 'change' => null],
            ],
            'trend' => [['period' => '2025-10-02', 'orders' => 1, 'completed' => 1, 'delivered' => 1, 'net' => 100]],
            'statuses' => [['label' => 'Completed', 'value' => 1]],
            'sections' => [['key' => 'orders', 'title' => 'Source orders', 'rows' => [
                ['Order' => '000123', 'Placed' => '2025-10-02', 'Status' => 'COMPLETED', 'Product' => '=UNTRUSTED-TEXT', 'Net PHP' => -12.5],
            ]]],
            'notes' => ['Current lifecycle state grouped by placement date.'], 'insights' => [],
        ];
    }
}
