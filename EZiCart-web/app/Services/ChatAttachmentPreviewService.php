<?php

namespace App\Services;

use App\Models\MessageAttachment;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ChatAttachmentPreviewService
{
    private const MAX_XML_BYTES = 2097152;

    private const MAX_TEXT_BYTES = 100000;

    public function describe(MessageAttachment $attachment): array
    {
        if (in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return ['kind' => 'image'];
        }
        if ($attachment->mime_type === 'application/pdf') {
            return ['kind' => 'pdf'];
        }
        $extension = strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));
        if ($extension === 'txt') {
            $stream = Storage::disk('private')->readStream($attachment->path);
            if (! is_resource($stream)) {
                return ['kind' => 'unsupported'];
            }
            try {
                $text = stream_get_contents($stream, self::MAX_TEXT_BYTES + 1);
            } finally {
                fclose($stream);
            }

            return ['kind' => 'text', 'text' => mb_scrub(substr($text, 0, self::MAX_TEXT_BYTES)), 'limited' => strlen($text) > self::MAX_TEXT_BYTES];
        }
        if (! in_array($extension, ['docx', 'xlsx'], true) || ! class_exists(ZipArchive::class)) {
            return ['kind' => 'unsupported'];
        }
        $zip = new ZipArchive;
        if ($zip->open(Storage::disk('private')->path($attachment->path), ZipArchive::RDONLY) !== true) {
            return ['kind' => 'unsupported'];
        }
        try {
            if ($zip->numFiles > 2000) {
                return ['kind' => 'unsupported'];
            }

            return $extension === 'docx' ? $this->word($zip) : $this->spreadsheet($zip);
        } finally {
            $zip->close();
        }
    }

    private function xml(ZipArchive $zip, string $name): ?DOMDocument
    {
        $stat = $zip->statName($name);
        if (! $stat || $stat['size'] > self::MAX_XML_BYTES) {
            return null;
        }
        $xml = $zip->getFromName($name, self::MAX_XML_BYTES + 1);
        if (! is_string($xml) || strlen($xml) > self::MAX_XML_BYTES || stripos($xml, '<!DOCTYPE') !== false) {
            return null;
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;

            return $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT) ? $document : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function word(ZipArchive $zip): array
    {
        $document = $this->xml($zip, 'word/document.xml');
        if (! $document) {
            return ['kind' => 'unsupported'];
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paragraphs = $xpath->query('//w:p');
        $text = '';
        $limited = false;
        foreach ($paragraphs as $paragraph) {
            if (strlen($text) >= self::MAX_TEXT_BYTES) {
                $limited = true;
                break;
            }
            $text .= $paragraph->textContent."\n";
        }

        return ['kind' => 'text', 'text' => mb_scrub(substr($text, 0, self::MAX_TEXT_BYTES)),
            'limited' => $limited || strlen($text) > self::MAX_TEXT_BYTES, 'office' => true];
    }

    private function spreadsheet(ZipArchive $zip): array
    {
        $strings = [];
        $limited = false;
        $shared = $this->xml($zip, 'xl/sharedStrings.xml');
        if ($zip->locateName('xl/sharedStrings.xml') !== false && ! $shared) {
            return ['kind' => 'unsupported'];
        }
        if ($shared) {
            $xpath = new DOMXPath($shared);
            $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($xpath->query('//s:si') as $node) {
                if (count($strings) >= 2000) {
                    $limited = true;
                    break;
                }
                $limited = $limited || mb_strlen($node->textContent) > 1000;
                $strings[] = mb_substr($node->textContent, 0, 1000);
            }
        }
        $names = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (preg_match('~^xl/worksheets/sheet\d+\.xml$~', $name)) {
                $names[] = $name;
            }
        }
        sort($names, SORT_NATURAL);
        $limited = $limited || count($names) > 5;
        $sheets = [];
        foreach (array_slice($names, 0, 5) as $name) {
            $document = $this->xml($zip, $name);
            if (! $document) {
                return ['kind' => 'unsupported'];
            }
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rows = [];
            foreach ($xpath->query('//s:sheetData/s:row') as $row) {
                if (count($rows) >= 200) {
                    $limited = true;
                    break;
                }
                $cells = [];
                foreach ($xpath->query('s:c', $row) as $cell) {
                    if (! $cell instanceof DOMElement) {
                        continue;
                    }
                    if (count($cells) >= 20) {
                        $limited = true;
                        break;
                    }
                    $value = $xpath->evaluate('string(s:v)', $cell);
                    if ($cell->getAttribute('t') === 's') {
                        $value = $strings[(int) $value] ?? '[Text omitted]';
                    }
                    if ($cell->getAttribute('t') === 'inlineStr') {
                        $value = $xpath->evaluate('string(s:is)', $cell);
                    }
                    $limited = $limited || mb_strlen($value) > 1000;
                    $cells[] = ['reference' => $cell->getAttribute('r'), 'value' => mb_substr($value, 0, 1000)];
                }
                $rows[] = $cells;
            }
            $sheets[] = ['name' => basename($name, '.xml'), 'rows' => $rows];
        }

        return $sheets ? ['kind' => 'spreadsheet', 'sheets' => $sheets, 'limited' => $limited] : ['kind' => 'unsupported'];
    }
}
