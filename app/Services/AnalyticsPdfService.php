<?php

namespace App\Services;

/** Layout for analytics reports; calculations and authorization remain in AnalyticsService. */
class AnalyticsPdfService
{
    private array $pages = [];

    private string $stream = '';

    private float $y = 0;

    private array $report = [];

    public function __construct(private TabularExportService $tabular) {}

    public function render(array $report, array $sheets, string $cover): string
    {
        $this->report = $report;
        $this->pages = [['content' => $cover, 'width' => 595, 'height' => 842]];
        $this->stream = '';
        $this->y = 0;
        foreach ($sheets as $name => $rows) {
            $headers = $rows[6];
            $body = array_slice($rows, 7);
            // Wide tables repeat their first identifier column on every horizontal panel.
            $groups = count($headers) > 7 ? array_map(fn ($columns) => [0, ...$columns], array_chunk(range(1, count($headers) - 1), 6)) : [array_keys($headers)];
            foreach ($groups as $part => $columns) {
                $title = $rows[1][0].(count($groups) > 1 ? ' | Columns '.($part + 1).' of '.count($groups) : '');
                $labels = array_map(fn ($column) => $headers[$column], $columns);
                $weights = array_map(fn ($label) => match (true) {
                    in_array($label, ['Definition', 'Accounting and data coverage notes', 'Reason', 'Observation'], true) => 5,
                    in_array($label, ['Product', 'Seller', 'Metric', 'Filter', 'Result'], true) => 3,
                    $label === 'Order' => 1.6, default => 1.3,
                }, $labels);
                $widths = array_map(fn ($weight) => $weight / array_sum($weights) * 758, $weights);
                if ($this->y < 155) {
                    $this->newPage();
                }
                $this->tableHeading($title, count($body), $labels, $widths);
                foreach ($body as $index => $row) {
                    $cells = [];
                    foreach ($columns as $slot => $column) {
                        $value = $this->display($row[$column] ?? null, $headers[$column], $name, $row);
                        $cells[] = $this->wrap($value, $widths[$slot] - 16, 9);
                    }
                    $length = max(array_map('count', $cells));
                    $offset = 0;
                    while ($offset < $length) {
                        $available = (int) floor(($this->y - 57 - 12) / 12);
                        if ($available < 1 || ($offset === 0 && $length <= 24 && $length > $available)) {
                            $this->newPage();
                            $this->tableHeading($title.' (continued)', count($body), $labels, $widths);
                            $available = (int) floor(($this->y - 57 - 12) / 12);
                        }
                        $take = min($length - $offset, $available);
                        $height = $take * 12 + 12;
                        $this->stream .= ($index % 2 ? '0.965 0.953 0.969' : '1 1 1').' rg 42 '.($this->y - $height).' 758 '.$height." re f\n";
                        $x = 42;
                        foreach ($cells as $slot => $lines) {
                            $numeric = is_int($row[$columns[$slot]] ?? null) || is_float($row[$columns[$slot]] ?? null);
                            foreach (array_slice($lines, $offset, $take) as $line => $value) {
                                $left = $numeric ? $x + $widths[$slot] - 8 - $this->width($value, 9) : $x + 8;
                                $this->stream .= "0.125 0.15 0.2 rg\n".$this->text($left, $this->y - 15 - $line * 12, 9, $value);
                            }
                            $x += $widths[$slot];
                        }
                        $this->y -= $height;
                        $this->stream .= '0.88 0.89 0.92 RG 0.4 w 42 '.$this->y.' m 800 '.$this->y." l S\n";
                        $offset += $take;
                    }
                }
                $this->y -= 26;
            }
        }
        $this->finishPage();
        $total = count($this->pages);
        foreach ($this->pages as $index => &$page) {
            $page['content'] .= "0.38 0.42 0.48 rg\n".$this->text(42, 25, 8, 'EZiCart | Internal report | '.$report['timezone']);
            $page['content'] .= $this->text($page['width'] - 130, 25, 8, 'Page '.($index + 1).' of '.$total);
        }
        unset($page);

        return $this->tabular->pdf([], $this->pages);
    }

    private function newPage(): void
    {
        $this->finishPage();
        $r = $this->report;
        $this->stream = "0.44 0.22 0.31 rg\n".$this->text(42, 559, 15, 'EZiCart | '.$r['title'], true);
        $this->stream .= "0.32 0.37 0.44 rg\n".$this->text(42, 540, 9, $r['filters']['from'].' to '.$r['filters']['to'].' | '.$r['timezone'].' | PHP | Generated '.$r['generated_at']);
        $comparison = $r['comparison'] ? $r['comparison']['from'].' to '.$r['comparison']['to'] : 'None';
        $this->stream .= $this->text(42, 525, 8, 'Comparison: '.$comparison.' | Cohort: order placement dates; latest recorded lifecycle state');
        $this->stream .= "0.85 0.86 0.89 RG 0.5 w 42 515 m 800 515 l S\n";
        $this->y = 495;
    }

    private function finishPage(): void
    {
        if ($this->stream !== '') {
            $this->pages[] = ['content' => $this->stream, 'width' => 842, 'height' => 595];
            $this->stream = '';
        }
    }

    private function tableHeading(string $title, int $count, array $labels, array $widths): void
    {
        foreach ($this->wrap($title, 758, 12, true) as $line) {
            $this->stream .= "0.125 0.15 0.2 rg\n".$this->text(42, $this->y, 12, $line, true);
            $this->y -= 16;
        }
        $this->stream .= "0.38 0.42 0.48 rg\n".$this->text(42, $this->y, 8, number_format($count).' detail rows | All authorized matching rows included');
        $this->y -= 13;
        $lines = array_map(fn ($label, $width) => $this->wrap($label, $width - 16, 9, true), $labels, $widths);
        $height = max(array_map('count', $lines)) * 12 + 12;
        $this->stream .= '0.44 0.22 0.31 rg 42 '.($this->y - $height).' 758 '.$height." re f\n";
        $x = 42;
        foreach ($lines as $column => $values) {
            foreach ($values as $line => $value) {
                $this->stream .= "1 1 1 rg\n".$this->text($x + 8, $this->y - 15 - $line * 12, 9, $value, true);
            }
            $x += $widths[$column];
        }
        $this->y -= $height;
    }

    private function display(mixed $value, string $heading, string $sheet, array $row): string
    {
        if ($value === null) {
            return 'Not available';
        }
        if (is_int($value) || is_float($value)) {
            if ($heading === 'Change %') {
                return ($value > 0 ? '+' : '').number_format($value, 1).'%';
            }
            if (str_contains($heading, 'PHP') || ($sheet === 'Summary' && in_array($heading, ['Current period', 'Comparison period']) && ($row[4] ?? '') === 'PHP')) {
                return number_format($value, 2);
            }
            if ($sheet === 'Summary' && ($row[4] ?? '') === 'Hours') {
                return number_format($value, 2);
            }

            return str_contains($heading, ' ID') ? (string) $value : number_format($value, 0);
        }

        return $value === '' ? 'Not recorded' : (string) $value;
    }

    private function text(float $x, float $y, float $size, string $value, bool $bold = false): string
    {
        $bytes = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: '';
        $bytes = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $bytes);

        return 'BT /'.($bold ? 'F2' : 'F1')." $size Tf 1 0 0 1 $x $y Tm ($bytes) Tj ET\n";
    }

    private function width(string $value, float $size, bool $bold = false): float
    {
        // Standard Helvetica ASCII widths. Wider fallback is conservative for transliterated text.
        static $widths = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];
        $bytes = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: '';
        $width = 0;
        foreach (str_split($bytes) as $char) {
            $width += $widths[ord($char) - 32] ?? 1000;
        }

        return $width / 1000 * $size * ($bold ? 1.08 : 1);
    }

    private function wrap(string $value, float $width, float $size, bool $bold = false): array
    {
        $words = preg_split('/\s+/u', trim($value)) ?: [''];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            if ($line !== '' && $this->width($line.' '.$word, $size, $bold) > $width) {
                $lines[] = $line;
                $line = '';
            }
            if ($this->width($word, $size, $bold) > $width) {
                foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $char) {
                    if ($line !== '' && $this->width($line.$char, $size, $bold) > $width) {
                        $lines[] = $line;
                        $line = '';
                    }
                    $line .= $char;
                }
            } else {
                $line .= ($line === '' ? '' : ' ').$word;
            }
        }
        if ($line !== '' || $lines === []) {
            $lines[] = $line;
        }

        return $lines;
    }
}
