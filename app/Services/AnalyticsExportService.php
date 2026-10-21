<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class AnalyticsExportService
{
    public function __construct(private TabularExportService $tabular) {}

    public function sheets(array $report): array
    {
        $summary = [['EZiCart '.$report['title']], ['Generated', $report['generated_at']], ['Timezone', $report['timezone']], ['Currency', 'PHP'],
            ['Date basis', 'Order placement; current lifecycle state'], ['From', $report['filters']['from']], ['To', $report['filters']['to']],
            ['Comparison from', $report['comparison']['from'] ?? 'None'], ['Comparison to', $report['comparison']['to'] ?? 'None'], [], ['Metric', 'Current', 'Previous', 'Change %', 'Unit']];
        foreach ($report['metrics'] as $metric) {
            $summary[] = [$metric['label'], $metric['value'], $metric['previous'], $metric['change'], $metric['unit']];
        }
        $sheets = ['Summary' => $summary, 'Filters' => [['Filter', 'Value']], 'Trends' => [['Period', 'Orders', 'Completed', 'Delivered', ...($report['commercial'] ? ['Net seller sales PHP'] : [])]]];
        foreach ($report['filters'] as $key => $value) {
            $sheets['Filters'][] = [Str::headline($key), $value ?? 'All'];
        }
        foreach ($report['trend'] as $point) {
            $sheets['Trends'][] = [$point['period'], $point['orders'], $point['completed'], $point['delivered'], ...($report['commercial'] ? [$point['net']] : [])];
        }
        foreach ($report['sections'] as $section) {
            $rows = [[$section['title']]];
            if (count($section['rows'])) {
                $rows[] = array_keys($section['rows'][0]);
                foreach ($section['rows'] as $row) {
                    $rows[] = array_values($row);
                }
            } else {
                $rows[] = ['No matching records.'];
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

        return $sheets;
    }

    public function csv(array $report): void
    {
        $this->tabular->csv((function () use ($report) {
            foreach ($this->sheets($report) as $title => $rows) {
                yield [$title];
                foreach ($rows as $row) {
                    yield $row;
                }
                yield [];
            }
        })());
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
            foreach ($this->sheets($report) as $name => $rows) {
                $id = count(explode('<sheet ', $workbook));
                $types .= '<Override PartName="/xl/worksheets/sheet'.$id.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                $workbook .= '<sheet name="'.$xml($name).'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
                $relations .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';
                $data = '';
                foreach ($rows as $index => $row) {
                    $number = $index + 1;
                    $data .= '<row r="'.$number.'">';
                    foreach (array_values($row) as $column => $value) {
                        $letters = '';
                        $n = $column + 1;
                        while ($n > 0) {
                            $letters = chr(65 + ($n - 1) % 26).$letters;
                            $n = intdiv($n - 1, 26);
                        }
                        $style = $index === 0 ? 1 : (is_float($value) ? 2 : 0);
                        $ref = $letters.$number;
                        $data .= is_int($value) || is_float($value)
                            ? '<c r="'.$ref.'" s="'.$style.'"><v>'.$value.'</v></c>'
                            : '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$xml($value ?? 'Not available').'</t></is></c>';
                    }
                    $data .= '</row>';
                }
                $zip->addFromString('xl/worksheets/sheet'.$id.'.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/><cols><col min="1" max="1" width="42" customWidth="1"/><col min="2" max="20" width="24" customWidth="1"/></cols><sheetData>'.$data.'</sheetData><pageSetup orientation="landscape" paperSize="9"/></worksheet>');
            }
            $styleId = count(explode('<sheet ', $workbook));
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$types.'</Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$workbook.'</sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relations.'<Relationship Id="rId'.$styleId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF71384F"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1" applyFont="1"/><xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
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
        $lines = [];
        foreach ($this->sheets($report) as $title => $rows) {
            $lines[] = '';
            $lines[] = strtoupper($title);
            foreach ($rows as $row) {
                $text = implode(' | ', array_map(static fn ($v) => $v === null ? 'Not available' : (string) $v, $row));
                // 50 characters fit the 515pt page body even for wide Helvetica glyphs at 10pt.
                foreach (explode("\n", wordwrap($text, 50, "\n", true)) as $line) {
                    $lines[] = $line;
                }
            }
        }

        return $this->tabular->pdf($lines, [$this->graphCover($report)]);
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
