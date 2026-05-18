<?php
/**
 * Masterlist spreadsheets: find "Students" anchor row, map LRN/Section columns, emit data rows.
 */

namespace XPLabs\Services;

class MasterlistSpreadsheetParser
{
    /**
     * Load file into a 2D grid of trimmed strings (row-major).
     *
     * @throws \RuntimeException
     */
    public static function fileToGrid(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            return self::csvFileToGrid($path);
        }
        if (in_array($ext, ['xlsx', 'xls'], true)) {
            return self::spreadsheetFileToGrid($path);
        }
        throw new \RuntimeException('Unsupported file type. Use .csv, .xlsx, or .xls.');
    }

    /**
     * @return array{rows: list<list<string>>, column_mapping: array<string,int>}
     */
    public static function extractStudentRows(array $grid): array
    {
        $anchor = self::findStudentsAnchor($grid);
        if ($anchor === null) {
            throw new \RuntimeException(
                'Could not find a "Students" section title in the spreadsheet. Add a cell containing "Students" above the student table.'
            );
        }

        [$anchorRow, $anchorCol] = $anchor;
        $headerRowIndex = self::findNextNonEmptyRow($grid, $anchorRow + 1);
        if ($headerRowIndex === null) {
            throw new \RuntimeException('No header row found after the Students title.');
        }

        $headers = $grid[$headerRowIndex] ?? [];
        $mapping = self::mapHeaderRow($headers);
        if ($mapping['lrn'] === null || $mapping['first_name'] === null || $mapping['last_name'] === null) {
            throw new \RuntimeException(
                'Student headers must include columns for LRN (or Learner Reference Number), First name, and Last name.'
            );
        }
        if ($mapping['section'] === null) {
            throw new \RuntimeException('Student headers must include a Section column (or abbreviation "Sec").');
        }

        $columnMapping = [
            'lrn' => $mapping['lrn'],
            'first_name' => $mapping['first_name'],
            'last_name' => $mapping['last_name'],
            'email' => $mapping['email'],
            'section' => $mapping['section'],
        ];

        $rows = [];
        for ($r = $headerRowIndex + 1; $r < count($grid); $r++) {
            $row = $grid[$r];
            $lrn = trim((string) ($row[$mapping['lrn']] ?? ''));
            if ($lrn === '' && self::rowIsEmpty($row)) {
                continue;
            }
            if ($lrn === '') {
                continue;
            }
            $rows[] = $row;
        }

        if ($rows === []) {
            throw new \RuntimeException('No student data rows found under the Students section.');
        }

        return ['rows' => $rows, 'column_mapping' => $columnMapping];
    }

    private static function csvFileToGrid(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Could not read CSV file.');
        }
        $raw = preg_replace("/^\xEF\xBB\xBF/", '', $raw);
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $grid = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $grid[] = array_map('trim', str_getcsv($line));
        }
        return $grid;
    }

    private static function spreadsheetFileToGrid(string $path): array
    {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new \RuntimeException(
                'Excel support requires Composer dependencies. Run `composer install` in the project root.'
            );
        }
        require_once $autoload;

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = (int) $sheet->getHighestRow();
        $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $grid = [];
        for ($r = 1; $r <= $highestRow; $r++) {
            $line = [];
            for ($c = 1; $c <= $highestCol; $c++) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $r;
                $val = $sheet->getCell($coord)->getCalculatedValue();
                $line[] = trim((string) ($val ?? ''));
            }
            $grid[] = $line;
        }
        return $grid;
    }

    /**
     * @return array{0:int,1:int}|null [row, col]
     */
    private static function findStudentsAnchor(array $grid): ?array
    {
        foreach ($grid as $ri => $row) {
            foreach ($row as $ci => $cell) {
                if (preg_match('/students/i', (string) $cell) === 1) {
                    return [$ri, $ci];
                }
            }
        }
        return null;
    }

    private static function findNextNonEmptyRow(array $grid, int $startRow): ?int
    {
        for ($r = $startRow; $r < count($grid); $r++) {
            if (!self::rowIsEmpty($grid[$r])) {
                return $r;
            }
        }
        return null;
    }

    private static function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @return array{lrn:?int,first_name:?int,last_name:?int,email:?int,section:?int}
     */
    private static function mapHeaderRow(array $headers): array
    {
        $norm = [];
        foreach ($headers as $i => $h) {
            $norm[$i] = strtolower(preg_replace('/\s+/', ' ', trim((string) $h)));
        }

        $out = [
            'lrn' => null,
            'first_name' => null,
            'last_name' => null,
            'email' => null,
            'section' => null,
        ];

        foreach ($norm as $i => $h) {
            if ($h === '') {
                continue;
            }
            if ($out['lrn'] === null && preg_match('/lrn|learner\s*reference(\s*number)?/', $h)) {
                $out['lrn'] = $i;
                continue;
            }
            if ($out['section'] === null && ($h === 'sec' || $h === 'section')) {
                $out['section'] = $i;
                continue;
            }
            if ($out['section'] === null && preg_match('/^section\b|\bsection$/', $h)) {
                $out['section'] = $i;
                continue;
            }
            if ($out['first_name'] === null && preg_match('/first\s*name|given\s*name/', $h)) {
                $out['first_name'] = $i;
                continue;
            }
            if ($out['last_name'] === null && preg_match('/last\s*name|surname|family\s*name/', $h)) {
                $out['last_name'] = $i;
                continue;
            }
            if ($out['email'] === null && str_contains($h, 'email')) {
                $out['email'] = $i;
            }
        }

        return $out;
    }
}
