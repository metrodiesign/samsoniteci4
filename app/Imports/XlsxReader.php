<?php

namespace App\Imports;

use InvalidArgumentException;
use SimpleXMLElement;
use XMLReader;
use ZipArchive;

final class XlsxReader
{
    /** @return list<list<string>> */
    public function rows(string $path): array
    {
        if (! is_file($path) || filesize($path) < 1 || filesize($path) > 5_242_880) {
            throw new InvalidArgumentException('Invalid XLSX size.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true || $zip->numFiles > 1000) {
            throw new InvalidArgumentException('Invalid XLSX archive.');
        }
        try {
            $total = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $total += (int) ($stat['size'] ?? 0);
                if ($total > 20_971_520) {
                    throw new InvalidArgumentException('XLSX expands beyond limit.');
                }
            }
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            if (! is_string($sheet) || strlen($sheet) > 10_485_760) {
                throw new InvalidArgumentException('Missing XLSX sheet.');
            }
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
            $shared = is_string($sharedXml) ? $this->sharedStrings($sharedXml) : [];
        } finally {
            $zip->close();
        }
        if (str_contains($sheet, '<!DOCTYPE') || str_contains($sheet, '<!ENTITY')) {
            throw new InvalidArgumentException('Unsafe XLSX XML.');
        }

        $reader = new XMLReader();
        if (! $reader->XML($sheet, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new InvalidArgumentException('Invalid XLSX XML.');
        }
        $rows = [];
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }
            if (count($rows) >= 501) {
                throw new InvalidArgumentException('XLSX row limit exceeded.');
            }
            $row = array_fill(0, 9, '');
            $outer = $reader->readOuterXML();
            $rowReader = new XMLReader();
            $rowReader->XML($outer, null, LIBXML_NONET | LIBXML_COMPACT);
            while ($rowReader->read()) {
                if ($rowReader->nodeType !== XMLReader::ELEMENT || $rowReader->localName !== 'c') {
                    continue;
                }
                $reference = (string) $rowReader->getAttribute('r');
                $column = $this->columnIndex($reference);
                $type = (string) $rowReader->getAttribute('t');
                $cell = $rowReader->readOuterXML();
                if ($column >= 0 && $column < 9) {
                    $row[$column] = $this->cellValue($cell, $type, $shared);
                }
            }
            $rowReader->close();
            $rows[] = $row;
        }
        $reader->close();
        if ($rows === []) {
            throw new InvalidArgumentException('Empty XLSX workbook.');
        }

        return $rows;
    }

    /** @return list<string> */
    private function sharedStrings(string $xml): array
    {
        if (strlen($xml) > 10_485_760 || str_contains($xml, '<!DOCTYPE') || str_contains($xml, '<!ENTITY')) {
            throw new InvalidArgumentException('Unsafe XLSX shared strings.');
        }
        $reader = new XMLReader();
        $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);
        $strings = [];
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                $strings[] = $this->textNodes($reader->readOuterXML());
            }
        }
        $reader->close();

        return $strings;
    }

    /** @param list<string> $shared */
    private function cellValue(string $xml, string $type, array $shared): string
    {
        if ($type === 'inlineStr') {
            return $this->textNodes($xml);
        }
        $cell = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if (! $cell instanceof SimpleXMLElement) {
            return '';
        }
        $nodes = $cell->xpath('//*[local-name()="v"]');
        $value = isset($nodes[0]) ? (string) $nodes[0] : '';

        return $type === 's' ? ($shared[(int) $value] ?? '') : $value;
    }

    private function textNodes(string $xml): string
    {
        $element = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if (! $element instanceof SimpleXMLElement) {
            return '';
        }
        $nodes = $element->xpath('//*[local-name()="t"]') ?: [];

        return implode('', array_map(static fn (SimpleXMLElement $node): string => (string) $node, $nodes));
    }

    private function columnIndex(string $reference): int
    {
        if (preg_match('/\A([A-Z]+)[1-9][0-9]*\z/D', $reference, $matches) !== 1) {
            return -1;
        }
        $index = 0;
        foreach (str_split($matches[1]) as $letter) {
            $index = $index * 26 + ord($letter) - 64;
        }

        return $index - 1;
    }
}
