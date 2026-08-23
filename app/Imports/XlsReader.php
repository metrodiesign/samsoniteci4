<?php

namespace App\Imports;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsSpreadsheetReader;
use Throwable;

final class XlsReader
{
    /** @return list<list<string>> */
    public function rows(string $path): array
    {
        if (! is_file($path) || filesize($path) < 1 || filesize($path) > 5_242_880) {
            throw new InvalidArgumentException('Invalid XLS size.');
        }
        $filter = new class implements IReadFilter {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= 502;
            }
        };
        try {
            $reader = new XlsSpreadsheetReader();
            $reader->setReadDataOnly(true);
            $reader->setReadFilter($filter);
            $spreadsheet = $reader->load($path);
            $raw = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);
            $spreadsheet->disconnectWorksheets();
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Invalid XLS workbook.', 0, $exception);
        }
        if (count($raw) > 501) {
            throw new InvalidArgumentException('XLS row limit exceeded.');
        }
        $rows = [];
        foreach ($raw as $cells) {
            $row = array_fill(0, 9, '');
            $index = 0;
            foreach (array_values($cells) as $value) {
                if ($index >= 9) {
                    break;
                }
                $row[$index] = is_scalar($value) ? (string) $value : '';
                $index++;
            }
            $rows[] = $row;
        }
        if ($rows === []) {
            throw new InvalidArgumentException('Empty XLS workbook.');
        }

        return $rows;
    }
}
