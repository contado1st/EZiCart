<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class AnalyticsExportService
{
    public function __construct(private TabularExportService $tabular, private AnalyticsPdfService $pdfReports) {}

    public function sheets(array $report): array
    {
        $summary = [['Metric', 'Current period', 'Comparison period', 'Change %', 'Unit']];
        foreach ($report['metrics'] as $metric) {
            $summary[] = [$metric['label'], $metric['value'], $report['comparison'] ? $metric['previous'] : 'Not compared', $metric['change'], match ($metric['unit']) {
                'money' => 'PHP', 'hours' => 'Hours', default => 'Count',
            }];
        }
        $sheets = ['Summary' => $summary, 'Filters' => [['Filter', 'Value']], 'Trends' => [['Period', 'Orders', 'Completed', 'Delivered', ...($report['commercial'] ? ['Net seller sales PHP'] : [])]]];
        foreach ($report['filters'] as $key => $value) {
            $sheets['Filters'][] = [Str::headline($key), $value === null || $value === '' ? 'All / not restricted' : $value];
        }
        foreach ($report['trend'] as $point) {
            $sheets['Trends'][] = [$point['period'], $point['orders'], $point['completed'], $point['delivered'], ...($report['commercial'] ? [$point['net']] : [])];
        }
        foreach ($report['sections'] as $section) {
            $rows = [];
            if (count($section['rows'])) {
                $rows[] = array_keys($section['rows'][0]);
                foreach ($section['rows'] as $row) {
                    $rows[] = array_values($row);
                }
            } else {
                $rows = [['Result'], ['No matching records for the selected filters.']];
            }
            $sheets[Str::limit(Str::headline($section['key']), 31, '')] = $rows;
        }
        $sheets['Definitions'] = [['Accounting and data coverage notes']];
        foreach ($report['notes'] as $note) {
            $sheets['Definitions'][] = [$note];
        }
        $sheets['Observations'] = [['Metric', 'Observation']];
        foreach ($report['insights'] as $row) {
            $sheets['Observations'][] = array_values($row);
        }
        if (count($sheets['Observations']) === 1) {
            $sheets['Observations'][] = ['No observations', 'No configured observation rule was triggered for this report.'];
        }
        $titles = collect($report['sections'])->mapWithKeys(fn ($s) => [Str::limit(Str::headline($s['key']), 31, '') => $s['title']])->all();
        foreach ($sheets as $name => $rows) {
            $comparison = $report['comparison'] ? $report['comparison']['from'].' to '.$report['comparison']['to'] : 'None';
            $sheets[$name] = [
                ['EZiCart | '.$report['title']], [$titles[$name] ?? $name],
                ['Reporting period: '.$report['filters']['from'].' to '.$report['filters']['to'].' | Workspace: '.Str::headline($report['role'])],
                ['Generated: '.$report['generated_at'].' | Timezone: '.$report['timezone'].' | Currency: PHP'],
                ['Comparison: '.$comparison.' | Detail rows: '.max(0, count($rows) - 1).' | Date basis: placement; latest recorded state'], [],
                ...$rows,
            ];
        }

        return $sheets;
    }

    public function csv(array $report): void
    {
        $sheets = $this->sheets($report);
        $width = max(array_map(fn ($rows) => max(array_map('count', $rows)), $sheets)) + 1;
        $this->tabular->csv((function () use ($sheets, $width) {
            yield ['Report section', ...array_map(fn ($column) => 'Content / column '.$column, range(1, $width - 1))];
            foreach ($sheets as $title => $rows) {
                foreach ($rows as $index => $row) {
                    $headers = $rows[6];
                    if ($index > 6) {
                        foreach ($row as $column => $value) {
                            if ($value === null) {
                                $row[$column] = 'Not available';
                            } elseif (is_numeric($value) && ($headers[$column] ?? '') === 'Change %') {
                                $row[$column] = (float) $value;
                            } elseif (is_numeric($value) && (str_contains($headers[$column] ?? '', 'PHP') || ($title === 'Summary' && in_array($column, [1, 2]) && ($row[4] ?? '') === 'PHP'))) {
                                $row[$column] = (float) $value;
                            }
                        }
                    }
                    yield array_pad([$title, ...$row], $width, '');
                }
                yield array_fill(0, $width, '');
            }
        })(), true);
    }

    /** Genuine OOXML workbook: typed numeric cells, safe inline strings, frozen headers. */
    public function xlsx(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ezicart-analytics-');
        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary report.');
        }
        $zip = new ZipArchive;
        $opened = false;
        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create the spreadsheet.');
            }
            $opened = true;
            $types = '';
            $workbook = '';
            $relations = '';
            $xml = static fn ($value) => htmlspecialchars(preg_replace('/[^\P{C}\t\r\n]/u', '', (string) $value) ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $printTitles = '';
            foreach ($this->sheets($report) as $name => $rows) {
                $id = count(explode('<sheet ', $workbook));
                $types .= '<Override PartName="/xl/worksheets/sheet'.$id.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                $workbook .= '<sheet name="'.$xml($name).'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
                $relations .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';
                $sheetReference = "'".str_replace("'", "''", $name)."'!";
                $wide = count($rows[6]) > 7;
                $printTitles .= '<definedName name="_xlnm.Print_Titles" localSheetId="'.($id - 1).'">'.$xml($sheetReference.'$1:$7'.($wide ? ','.$sheetReference.'$A:$A' : '')).'</definedName>';
                $columns = max(5, max(array_map('count', $rows)));
                $lastColumn = $this->columnName($columns);
                $widths = [];
                for ($column = 0; $column < $columns; $column++) {
                    $lengths = [];
                    foreach (array_slice($rows, 6, null, true) as $index => $row) {
                        [$value, $style] = $this->cell($row[$column] ?? '', $index, $column, $rows[6], $name, $row);
                        $display = (string) $value;
                        if (is_int($value) || is_float($value)) {
                            $display = match (true) {
                                in_array($style, [7, 8]) => 'PHP '.number_format($value, 2),
                                in_array($style, [11, 12]) => number_format($value * 100, 1).'%',
                                in_array($style, [13, 14]) => number_format($value, 2).' hrs',
                                in_array($style, [15, 16, 17, 18]) => (string) ($row[$column] ?? ''),
                                in_array($style, [19, 20]) => (string) $value,
                                default => number_format($value, 0),
                            };
                        }
                        $lengths[] = mb_strlen($display);
                    }
                    $widths[$column] = max(16, min($column === 0 ? 54 : 42, max($lengths ?: [16]) + 3));
                }
                if (count($rows[6]) === 1) {
                    $widths = [100, 2, 2, 2, 2];
                }
                $data = '';
                foreach ($rows as $index => $row) {
                    $number = $index + 1;
                    $lines = 1;
                    if ($index > 6) {
                        foreach ($row as $column => $value) {
                            $lines = max($lines, (int) ceil(mb_strlen((string) $value) * 1.25 / max(8, $widths[$column] - 5)));
                        }
                    } elseif ($index < 5) {
                        $scale = match ($index) {
                            0 => 1.8, 1 => 1.25, default => .95
                        };
                        $lines = max(1, (int) ceil(mb_strlen((string) ($row[0] ?? '')) * $scale / max(20, array_sum($widths) - 4)));
                    }
                    $height = match (true) {
                        $index === 0 => max(32, $lines * 23 + 8), $index === 1 => max(28, $lines * 18 + 8), $index === 6 => 34, $index === 5 => 10, default => min(409, max(24, $lines * 15 + 10))
                    };
                    $data .= '<row r="'.$number.'" ht="'.$height.'" customHeight="1">';
                    foreach (array_values($row) as $column => $value) {
                        $letters = $this->columnName($column + 1);
                        [$value, $style] = $this->cell($value, $index, $column, $rows[6], $name, $row);
                        $ref = $letters.$number;
                        $data .= is_int($value) || is_float($value)
                            ? '<c r="'.$ref.'" s="'.$style.'"><v>'.$value.'</v></c>'
                            : '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$xml($value ?? 'Not available').'</t></is></c>';
                    }
                    $data .= '</row>';
                }
                $cols = '';
                foreach ($widths as $column => $width) {
                    $cols .= '<col min="'.($column + 1).'" max="'.($column + 1).'" width="'.$width.'" customWidth="1"/>';
                }
                $merge = '';
                for ($line = 1; $line <= 5; $line++) {
                    $merge .= '<mergeCell ref="A'.$line.':'.$lastColumn.$line.'"/>';
                }
                $breaks = '';
                for ($column = 7; $column < count($rows[6]); $column += 6) {
                    $breaks .= '<brk id="'.$column.'" min="0" max="1048575" man="1"/>';
                }
                $breakCount = substr_count($breaks, '<brk ');
                $zip->addFromString('xl/worksheets/sheet'.$id.'.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetPr><pageSetUpPr fitToPage="'.($wide ? '0' : '1').'"/></sheetPr><dimension ref="A1:'.$lastColumn.count($rows).'"/><sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="7" topLeftCell="A8" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="24"/><cols>'.$cols.'</cols><sheetData>'.$data.'</sheetData><autoFilter ref="A7:'.$this->columnName(count($rows[6])).count($rows).'"/><mergeCells count="5">'.$merge.'</mergeCells><printOptions horizontalCentered="1"/><pageMargins left="0.3" right="0.3" top="0.4" bottom="0.4" header="0.15" footer="0.2"/><pageSetup orientation="landscape" paperSize="9" '.($wide ? 'scale="90"' : 'fitToWidth="1" fitToHeight="0"').'/><headerFooter><oddHeader>&amp;LEZiCart | Internal report&amp;R'.$xml($name).'</oddHeader><oddFooter>&amp;L'.$xml($report['filters']['from'].' to '.$report['filters']['to']).'&amp;CPage &amp;P of &amp;N&amp;RPHP | '.$xml($report['timezone']).'</oddFooter></headerFooter>'.($wide ? '<colBreaks count="'.$breakCount.'" manualBreakCount="'.$breakCount.'">'.$breaks.'</colBreaks>' : '').'</worksheet>');
            }
            $styleId = count(explode('<sheet ', $workbook));
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$types.'</Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets>'.$workbook.'</sheets><definedNames>'.$printTitles.'</definedNames></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relations.'<Relationship Id="rId'.$styleId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml', $this->styles());
            $zip->close();
            $opened = false;
            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException('Unable to read the spreadsheet.');
            }

            return $content;
        } finally {
            if ($opened) {
                $zip->close();
            }
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function pdf(array $report): string
    {
        return $this->pdfReports->render($report, $this->sheets($report), $this->graphCover($report));
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $name = chr(65 + ($number - 1) % 26).$name;
            $number = intdiv($number - 1, 26);
        }

        return $name;
    }

    private function cell(mixed $value, int $row, int $column, array $headers, string $sheet, array $values): array
    {
        if ($row < 7) {
            return [$value, match ($row) {
                0 => 1, 1 => 2, 6 => 4, default => 3
            }];
        }
        $band = ($row - 7) % 2;
        if ($value === null) {
            return ['Not available', 5 + $band];
        }
        $heading = $headers[$column] ?? '';
        if (is_int($value) || is_float($value)) {
            if ($heading === 'Change %') {
                return [$value / 100, 11 + $band];
            }
            if (str_contains($heading, 'PHP') || ($sheet === 'Summary' && in_array($column, [1, 2]) && ($values[4] ?? '') === 'PHP')) {
                return [$value, 7 + $band];
            }
            if ($sheet === 'Summary' && in_array($column, [1, 2]) && ($values[4] ?? '') === 'Hours') {
                return [$value, 13 + $band];
            }
            if (str_contains($heading, ' ID')) {
                return [$value, 19 + $band];
            }

            return [$value, 9 + $band];
        }
        if (in_array($heading, ['Period', 'Placed', 'Updated', 'Attempted', 'Requested at', 'Resolved at', 'Verified at', 'Assigned at', 'Accepted at'], true)
            && preg_match('/^\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2}:\d{2})?$/', (string) $value)) {
            $date = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));

            return [$date->getTimestamp() / 86400 + 25569, (strlen($value) > 10 ? 17 : 15) + $band];
        }

        return [$value, 5 + $band];
    }

    private function styles(): string
    {
        $formats = [164 => '"PHP "#,##0.00;[Red]("PHP "#,##0.00)', 165 => '0.0%;[Red](0.0%)', 166 => '0.00" hrs"', 167 => 'yyyy-mm-dd', 168 => 'yyyy-mm-dd hh:mm'];
        $numberFormats = '';
        foreach ($formats as $id => $code) {
            $numberFormats .= '<numFmt numFmtId="'.$id.'" formatCode="'.htmlspecialchars($code, ENT_XML1 | ENT_QUOTES, 'UTF-8').'"/>';
        }
        $xf = static fn ($font, $fill, $format = 0, $align = 'left') => '<xf numFmtId="'.$format.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="0" xfId="0" applyFont="1" applyFill="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="'.$align.'" vertical="top" indent="'.(in_array($font, [0, 4]) ? 1 : 0).'" wrapText="1"/></xf>';
        $cells = $xf(0, 0).$xf(1, 0).$xf(2, 0).$xf(3, 0).$xf(4, 2);
        foreach ([0, 164, 3, 165, 166, 167, 168] as $format) {
            $cells .= $xf(0, 0, $format, $format ? 'right' : 'left').$xf(0, 3, $format, $format ? 'right' : 'left');
        }
        $cells .= $xf(0, 0, 0, 'right').$xf(0, 3, 0, 'right');

        return '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="5">'.$numberFormats.'</numFmts><fonts count="5"><font><sz val="11"/><color rgb="FF202634"/><name val="Calibri"/></font><font><b/><sz val="18"/><color rgb="FF71384F"/><name val="Calibri"/></font><font><b/><sz val="13"/><color rgb="FF202634"/><name val="Calibri"/></font><font><sz val="10"/><color rgb="FF657084"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF71384F"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF5F2F6"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="21">'.$cells.'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function graphCover(array $report): string
    {
        $text = static function ($x, $y, $size, $value) {
            $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', (string) $value);
            $value = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $value);

            return "BT /F1 $size Tf 1 0 0 1 $x $y Tm ($value) Tj ET\n";
        };
        $stream = "0.74 0.16 0.33 rg\n".$text(40, 794, 20, 'EZiCart | '.$report['title']);
        $stream .= "0.25 0.29 0.36 rg\n".$text(40, 768, 10, $report['filters']['from'].' to '.$report['filters']['to'].' | '.$report['timezone'].' | PHP');
        $stream .= $text(40, 748, 9, 'Generated '.$report['generated_at']);
        foreach (array_slice($report['metrics'], 0, 4) as $index => $metric) {
            $x = 40 + ($index % 2) * 270;
            $y = 704 - intdiv($index, 2) * 62;
            $stream .= "0.35 0.39 0.45 rg\n".$text($x, $y, 10, $metric['label']);
            $value = $metric['value'] === null ? 'No data' : ($metric['unit'] === 'money' ? 'PHP ' : '').number_format($metric['value'], $metric['unit'] === 'count' ? 0 : 2);
            $stream .= "0.12 0.15 0.2 rg\n".$text($x, $y - 25, 18, $value);
        }
        $stream .= $text(40, 562, 14, 'Orders placed over time');
        $max = max(1, ...array_column($report['trend'], 'orders'));
        $stream .= "0.87 0.89 0.92 RG 0.5 w 50 355 m 545 355 l S\n";
        foreach ([0, .5, 1] as $fraction) {
            $y = 355 + $fraction * 175;
            $stream .= "0.35 0.39 0.45 rg\n".$text(25, $y, 9, number_format($max * $fraction, 1));
        }
        $stream .= "0.74 0.16 0.33 RG 2 w\n";
        foreach ($report['trend'] as $index => $point) {
            $x = round(50 + $index / max(1, count($report['trend']) - 1) * 495, 2);
            $y = round(355 + $point['orders'] / $max * 175, 2);
            $stream .= "$x $y ".($index ? 'l' : 'm')."\n";
        }
        $stream .= "S\n0.35 0.39 0.45 rg\n".$text(50, 337, 9, $report['trend'][0]['period'] ?? '');
        $stream .= $text(470, 337, 9, $report['trend'][array_key_last($report['trend'])]['period'] ?? '');
        $stream .= "0.12 0.15 0.2 rg\n".$text(40, 292, 14, 'Current order status distribution');
        $statuses = array_slice($report['statuses'], 0, 6);
        $maximum = max(1, ...array_column($statuses, 'value'));
        foreach ($statuses as $index => $status) {
            $y = 264 - $index * 28;
            $width = round($status['value'] / $maximum * 260, 2);
            $stream .= "0.25 0.29 0.36 rg\n".$text(40, $y, 9, $status['label']);
            $stream .= '0.74 0.16 0.33 rg 220 '.($y - 2)." $width 10 re f\n";
            $stream .= "0.25 0.29 0.36 rg\n".$text(500, $y, 9, $status['value']);
        }
        $stream .= $text(40, 70, 9, 'Summary graphs. All statuses and source records follow in the full report.');
        $stream .= $text(40, 52, 9, 'Date basis: order placement; outcomes reflect the latest recorded state.');

        return $stream;
    }
}
