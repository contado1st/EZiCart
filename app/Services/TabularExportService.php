<?php

namespace App\Services;

class TabularExportService
{
    public function csv(iterable $rows): void
    {
        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($output, array_map(function ($value) {
                $value = (string) $value;

                return preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;
            }, $row), ',', '"', '');
        }
        fclose($output);
    }

    /** A paginated text report using the PDF base font; no external renderer is required. */
    public function pdf(array $lines, array $graphicPages = []): string
    {
        $pages = array_merge($graphicPages, array_chunk($lines, 48));
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        foreach ($pages as $index => $page) {
            $pageId = 4 + $index * 2;
            $contentId = $pageId + 1;
            $kids[] = "$pageId 0 R";
            $content = is_string($page) ? $page : "BT /F1 10 Tf 40 800 Td 15 TL\n";
            if (! is_string($page)) {
                foreach ($page as $line) {
                    $line = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', (string) $line);
                    $line = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $line);
                    $content .= "($line) Tj T*\n";
                }
                $content .= 'ET';
            }
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $contentId 0 R >>";
            $objects[$contentId] = '<< /Length '.strlen($content)." >>\nstream\n$content\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$object\nendobj\n";
        }
        $xref = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 $count\n0000000000 65535 f \n";
        for ($id = 1; $id < $count; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        return $pdf."trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    }
}
