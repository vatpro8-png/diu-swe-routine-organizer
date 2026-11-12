<?php

namespace App\Services;

use App\Models\AcademicRoutine;
use App\Models\ExamRoutine;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class ExamSheetParserService
{
    /**
     * List all worksheets inside an uploaded .xlsx or .csv file.
     *
     * @return array<int, array{index: int, name: string, target: ?string}>
     */
    public function listWorkbookSheets(string $filePath, string $extension): array
    {
        $ext = strtolower(trim($extension));
        if (in_array($ext, ['csv', 'txt'], true)) {
            return [
                ['index' => 0, 'name' => 'CSV Sheet', 'target' => null],
            ];
        }

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('Unable to open the uploaded XLSX workbook.');
        }

        try {
            return $this->extractXlsxSheets($zip);
        } finally {
            $zip->close();
        }
    }

    /**
     * Pick the most appropriate worksheet name for a distinct exam type.
     *
     * @param  array<int, array{index: int, name: string, target: ?string}>  $sheets
     */
    public function pickDefaultSheetName(array $sheets, string $examType): ?string
    {
        if (empty($sheets)) {
            return null;
        }

        $needle = strtolower(ExamRoutine::normalizeExamType($examType));
        foreach ($sheets as $sheet) {
            $nameLower = strtolower($sheet['name']);
            if ($needle === 'midterm' && str_contains($nameLower, 'mid')) {
                return $sheet['name'];
            }
            if ($needle === 'final' && str_contains($nameLower, 'final')) {
                return $sheet['name'];
            }
        }

        return $sheets[0]['name'];
    }

    /**
     * Parse an uploaded exam sheet (.xlsx or .csv) for the requested worksheet and exam type.
     *
     * @return array{
     *     sheets: array<int, array{index: int, name: string, target: ?string}>,
     *     active_sheet: string,
     *     headers: array<int, string>,
     *     raw_rows: array<int, array<int, string>>,
     *     entries: array<int, array<string, mixed>>
     * }
     */
    public function parseFile(string $filePath, string $extension, string $examType, ?string $requestedSheetName = null): array
    {
        $ext = strtolower(trim($extension));
        $normalizedType = ExamRoutine::normalizeExamType($examType);

        if (in_array($ext, ['csv', 'txt'], true)) {
            $sheets = [['index' => 0, 'name' => 'CSV Sheet', 'target' => null]];
            $activeSheet = 'CSV Sheet';
            $matrix = $this->readCsvMatrix($filePath);
        } else {
            $zip = new ZipArchive;
            if ($zip->open($filePath) !== true) {
                throw new RuntimeException('Unable to open the uploaded XLSX workbook.');
            }

            try {
                $sheets = $this->extractXlsxSheets($zip);
                if (empty($sheets)) {
                    throw new RuntimeException('No worksheets found inside the uploaded XLSX workbook.');
                }

                $selectedSheet = null;
                if (! empty($requestedSheetName)) {
                    foreach ($sheets as $sheet) {
                        if (strcasecmp($sheet['name'], $requestedSheetName) === 0) {
                            $selectedSheet = $sheet;
                            break;
                        }
                    }
                }

                if ($selectedSheet === null) {
                    $defaultName = $this->pickDefaultSheetName($sheets, $normalizedType);
                    foreach ($sheets as $sheet) {
                        if ($sheet['name'] === $defaultName) {
                            $selectedSheet = $sheet;
                            break;
                        }
                    }
                    $selectedSheet ??= $sheets[0];
                }

                $activeSheet = $selectedSheet['name'];
                $target = $selectedSheet['target'] ?? 'xl/worksheets/sheet1.xml';
                $matrix = $this->readXlsxSheetMatrix($zip, $target);
            } finally {
                $zip->close();
            }
        }

        return $this->normalizeMatrix($matrix, $sheets, $activeSheet, $normalizedType);
    }

    /**
     * Generate a clean CSV exam sheet template for the distinct exam type.
     */
    public function generateSampleCsv(string $examType): string
    {
        $type = ExamRoutine::normalizeExamType($examType);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");

        fputcsv($stream, [
            'Exam Date',
            'Day',
            'Time Slot',
            'Batch',
            'Section',
            'Major Track',
            'Course Code',
            'Course Title',
            'Room No',
            'Building',
            'Teacher Initials',
            'Notes',
        ], escape: '\\');

        $rows = $type === 'Final'
            ? [
                ['2026-12-12', 'Saturday', '09:00 AM - 11:00 AM', '49', 'A', '', 'SE111', 'Computer Fundamentals', '610', 'AB3', 'FT', 'Final Examination'],
                ['2026-12-13', 'Sunday', '11:30 AM - 01:30 PM', '48', 'B', '', 'SE121', 'Structured Programming', '710', 'AB3', 'MAK', 'Final Examination'],
                ['2026-12-14', 'Monday', '02:00 PM - 04:00 PM', '41', 'A', 'DS', 'DS421', 'Machine Learning Driven Data Analysis I (DS Major)', 'Annex-106', 'Annex', 'AAA', 'Final Examination'],
            ]
            : [
                ['2026-10-24', 'Saturday', '09:00 AM - 10:30 AM', '49', 'A', '', 'SE111', 'Computer Fundamentals', '610', 'AB3', 'FT', 'Midterm Examination'],
                ['2026-10-25', 'Sunday', '11:00 AM - 12:30 PM', '48', 'B', '', 'SE121', 'Structured Programming', '710', 'AB3', 'MAK', 'Midterm Examination'],
                ['2026-10-26', 'Monday', '01:30 PM - 03:00 PM', '41', 'A', 'SE', 'SE442', 'Management Information System/ Open Elective - I', 'Annex-106', 'Annex', 'IM', 'Midterm Examination'],
            ];

        foreach ($rows as $row) {
            fputcsv($stream, $row, escape: '\\');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }

    /**
     * @return array<int, array{index: int, name: string, target: ?string}>
     */
    protected function extractXlsxSheets(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        if ($workbookXml === false) {
            return [];
        }

        $workbook = @simplexml_load_string($workbookXml);
        if (! $workbook instanceof SimpleXMLElement) {
            return [];
        }

        $relMap = [];
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relsXml !== false) {
            $rels = @simplexml_load_string($relsXml);
            if ($rels instanceof SimpleXMLElement) {
                foreach ($rels->Relationship as $rel) {
                    $id = (string) $rel['Id'];
                    $target = ltrim((string) $rel['Target'], '/');
                    if (! str_starts_with($target, 'xl/')) {
                        $target = 'xl/'.$target;
                    }
                    $relMap[$id] = $target;
                }
            }
        }

        $sheets = [];
        $namespaces = $workbook->getNamespaces(true);
        $rNs = $namespaces['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        $index = 0;
        if (isset($workbook->sheets->sheet)) {
            foreach ($workbook->sheets->sheet as $sheet) {
                $name = trim((string) $sheet['name']);
                $rAttrs = $sheet->attributes($rNs);
                $rId = isset($rAttrs['id']) ? (string) $rAttrs['id'] : '';
                $target = $relMap[$rId] ?? ('xl/worksheets/sheet'.($index + 1).'.xml');

                $sheets[] = [
                    'index' => $index,
                    'name' => $name !== '' ? $name : 'Sheet '.($index + 1),
                    'target' => $target,
                ];
                $index++;
            }
        }

        return $sheets;
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function readCsvMatrix(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read the uploaded CSV file.');
        }

        $matrix = [];
        $lineNo = 0;

        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if ($lineNo === 0 && isset($row[0])) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]) ?? '';
                }
                $lineNo++;

                $cleaned = array_map(static fn ($cell) => trim((string) $cell), $row);
                if ($this->isRowEmpty($cleaned)) {
                    continue;
                }

                $matrix[] = $cleaned;
            }
        } finally {
            fclose($handle);
        }

        return $matrix;
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function readXlsxSheetMatrix(ZipArchive $zip, string $sheetTarget): array
    {
        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $zip->getFromName($sheetTarget);
        if ($sheetXml === false) {
            throw new RuntimeException("Worksheet '{$sheetTarget}' could not be read from workbook.");
        }

        $xml = @simplexml_load_string($sheetXml);
        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException("Worksheet '{$sheetTarget}' contains invalid XML.");
        }

        $rawGrid = [];
        $maxCol = 0;
        $maxRow = 0;

        if (isset($xml->sheetData->row)) {
            foreach ($xml->sheetData->row as $row) {
                $rowIdx = max(1, (int) $row['r']);
                $maxRow = max($maxRow, $rowIdx);

                foreach ($row->c as $c) {
                    $ref = (string) $c['r'];
                    if (! preg_match('/^([A-Z]+)(\d+)$/', $ref, $matches)) {
                        continue;
                    }
                    $colIdx = $this->columnLettersToIndex($matches[1]);
                    $maxCol = max($maxCol, $colIdx);
                    $rawGrid[$rowIdx][$colIdx] = $this->extractCellValue($c, $sharedStrings);
                }
            }
        }

        // Propagate merged cell top-left values so grouped date/time cells remain intact
        if (isset($xml->mergeCells->mergeCell)) {
            foreach ($xml->mergeCells->mergeCell as $merge) {
                $ref = (string) $merge['ref'];
                if (! preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $ref, $m)) {
                    continue;
                }
                $c1 = $this->columnLettersToIndex($m[1]);
                $r1 = (int) $m[2];
                $c2 = $this->columnLettersToIndex($m[3]);
                $r2 = (int) $m[4];

                $anchorVal = $rawGrid[$r1][$c1] ?? '';
                if ($anchorVal === '') {
                    continue;
                }

                for ($r = $r1; $r <= $r2; $r++) {
                    for ($c = $c1; $c <= $c2; $c++) {
                        if (! isset($rawGrid[$r][$c]) || $rawGrid[$r][$c] === '') {
                            $rawGrid[$r][$c] = $anchorVal;
                        }
                    }
                }
            }
        }

        $matrix = [];
        for ($r = 1; $r <= $maxRow; $r++) {
            $rowArr = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $rowArr[] = trim((string) ($rawGrid[$r][$c] ?? ''));
            }
            if (! $this->isRowEmpty($rowArr)) {
                $matrix[] = $rowArr;
            }
        }

        return $matrix;
    }

    /**
     * @return array<int, string>
     */
    protected function readSharedStrings(ZipArchive $zip): array
    {
        $strings = [];
        $xmlString = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlString === false) {
            return $strings;
        }

        $xml = @simplexml_load_string($xmlString);
        if (! $xml instanceof SimpleXMLElement) {
            return $strings;
        }

        foreach ($xml->si as $si) {
            $text = '';
            if (isset($si->t)) {
                $text .= (string) $si->t;
            }
            if (isset($si->r)) {
                foreach ($si->r as $r) {
                    $text .= (string) $r->t;
                }
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    protected function extractCellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            if (isset($cell->is->t)) {
                return trim((string) $cell->is->t);
            }
            if (isset($cell->is->r)) {
                $text = '';
                foreach ($cell->is->r as $r) {
                    $text .= (string) $r->t;
                }

                return trim($text);
            }
        }

        $val = isset($cell->v) ? (string) $cell->v : '';
        if ($type === 's' && $val !== '' && isset($sharedStrings[(int) $val])) {
            return trim($sharedStrings[(int) $val]);
        }

        if ($type === 'b') {
            return $val === '1' ? 'TRUE' : 'FALSE';
        }

        return trim($val);
    }

    /**
     * @param  array<int, array<int, string>>  $matrix
     * @param  array<int, array{index: int, name: string, target: ?string}>  $sheets
     * @return array{
     *     sheets: array<int, array{index: int, name: string, target: ?string}>,
     *     active_sheet: string,
     *     headers: array<int, string>,
     *     raw_rows: array<int, array<int, string>>,
     *     entries: array<int, array<string, mixed>>
     * }
     */
    protected function normalizeMatrix(array $matrix, array $sheets, string $activeSheet, string $examType): array
    {
        if (empty($matrix)) {
            return [
                'sheets' => $sheets,
                'active_sheet' => $activeSheet,
                'headers' => [],
                'raw_rows' => [],
                'entries' => [],
            ];
        }

        $headerIdx = $this->detectHeaderRowIndex($matrix);
        $rawHeaders = $matrix[$headerIdx] ?? [];

        // Determine active non-empty column indices
        $maxCols = max(array_map('count', $matrix));
        $headers = [];
        for ($i = 0; $i < $maxCols; $i++) {
            $h = trim((string) ($rawHeaders[$i] ?? ''));
            $headers[] = $h !== '' ? $h : 'Column '.($i + 1);
        }

        // Trim trailing empty header columns when all rows are empty in those columns
        while (! empty($headers)) {
            $lastIdx = count($headers) - 1;
            $hasData = false;
            foreach ($matrix as $rIdx => $row) {
                if ($rIdx >= $headerIdx && trim((string) ($row[$lastIdx] ?? '')) !== '') {
                    $hasData = true;
                    break;
                }
            }
            if (! $hasData) {
                array_pop($headers);
            } else {
                break;
            }
        }

        $colCount = count($headers);
        [$detectedSemester, $detectedYear] = $this->detectBannerTerm($matrix, $headerIdx);

        if ($this->isDepartmentMatrixGrid($headers)) {
            $rawRows = [];
            for ($r = $headerIdx + 1; $r < count($matrix); $r++) {
                $padded = [];
                for ($c = 0; $c < $colCount; $c++) {
                    $padded[] = trim((string) ($matrix[$r][$c] ?? ''));
                }
                if (! $this->isRowEmpty($padded)) {
                    $rawRows[] = $padded;
                }
            }

            $entries = $this->parseDepartmentMatrixRows(
                $matrix,
                $headerIdx,
                $examType,
                $detectedSemester,
                $detectedYear
            );

            return [
                'sheets' => $sheets,
                'active_sheet' => $activeSheet,
                'headers' => $headers,
                'raw_rows' => $rawRows,
                'entries' => $entries,
            ];
        }

        $columnMap = $this->mapSemanticColumns($headers);

        $rawRows = [];
        $entries = [];

        for ($r = $headerIdx + 1; $r < count($matrix); $r++) {
            $row = $matrix[$r];
            $padded = [];
            for ($c = 0; $c < $colCount; $c++) {
                $val = trim((string) ($row[$c] ?? ''));
                if ($c === ($columnMap['exam_date'] ?? -1)) {
                    $val = $this->formatPotentialExcelDate($val);
                } elseif ($c === ($columnMap['time_slot'] ?? -1)) {
                    $val = $this->formatPotentialExcelTime($val);
                }
                $padded[] = $val;
            }

            if ($this->isRowEmpty($padded)) {
                continue;
            }

            $rawRows[] = $padded;

            $entry = $this->buildStructuredEntry($padded, $columnMap, $examType, $headers, $detectedSemester, $detectedYear);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return [
            'sheets' => $sheets,
            'active_sheet' => $activeSheet,
            'headers' => $headers,
            'raw_rows' => $rawRows,
            'entries' => $entries,
        ];
    }

    /**
     * @param  array<int, array<int, string>>  $matrix
     */
    protected function detectHeaderRowIndex(array $matrix): int
    {
        $keywords = ['course', 'code', 'date', 'time', 'slot', 'room', 'batch', 'section', 'title', 'day', 'teacher'];
        $bestIdx = 0;
        $bestScore = -1;

        $limit = min(12, count($matrix));
        for ($i = 0; $i < $limit; $i++) {
            $score = 0;
            $nonEmpty = 0;
            foreach ($matrix[$i] as $cell) {
                $c = strtolower(trim($cell));
                if ($c === '') {
                    continue;
                }
                $nonEmpty++;
                foreach ($keywords as $kw) {
                    if (str_contains($c, $kw)) {
                        $score += 3;
                        break;
                    }
                }
            }

            if ($nonEmpty >= 2 && $score > $bestScore) {
                $bestScore = $score;
                $bestIdx = $i;
            }
        }

        if ($bestScore <= 0) {
            foreach ($matrix as $idx => $row) {
                $nonEmpty = count(array_filter($row, static fn ($v) => trim($v) !== ''));
                if ($nonEmpty >= 2) {
                    return $idx;
                }
            }
        }

        return $bestIdx;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, int>
     */
    protected function mapSemanticColumns(array $headers): array
    {
        $map = [];
        $patterns = [
            'semester' => ['semester', 'term'],
            'year' => ['year'],
            'exam_date' => ['exam date', 'exam_date', 'date'],
            'day_of_week' => ['day of week', 'day_of_week', 'weekday', 'day'],
            'slot_code' => ['slot code', 'slot_code'],
            'time_slot' => ['time slot', 'time_slot', 'exam time', 'slot', 'time', 'schedule', 'duration'],
            'batch' => ['batch', 'level'],
            'section' => ['section', 'sec'],
            'major_track' => ['major track', 'major_track', 'track', 'major'],
            'cohort_scope' => ['cohort scope', 'cohort_scope', 'scope', 'mode'],
            'course_code' => ['course code', 'course_code', 'course id', 'course_id', 'code', 'course'],
            'equivalent_course_code' => ['equivalent course', 'equivalent_course_code', 'equivalent'],
            'course_name' => ['course title', 'course_title', 'course name', 'course_name', 'subject', 'title'],
            'student_count' => ['student count', 'student_count', 'students', 'enrolled', 'headcount', 'count'],
            'room_no' => ['room no', 'room_no', 'classroom', 'room', 'hall', 'venue'],
            'building' => ['building', 'block', 'campus'],
            'teacher_initials' => ['teacher initials', 'teacher_initials', 'faculty', 'invigilator', 'teacher', 'initial'],
            'notes' => ['notes', 'note', 'remarks', 'remark', 'type'],
        ];

        foreach ($headers as $idx => $header) {
            $normalized = strtolower(trim((string) preg_replace('/\s+/', ' ', $header)));
            foreach ($patterns as $field => $aliases) {
                if (isset($map[$field])) {
                    continue;
                }
                foreach ($aliases as $alias) {
                    if ($normalized === $alias || str_contains($normalized, $alias)) {
                        $map[$field] = $idx;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string, int>  $map
     * @param  array<int, string>  $headers
     * @return array<string, mixed>|null
     */
    protected function buildStructuredEntry(
        array $row,
        array $map,
        string $examType,
        array $headers,
        string $defaultSemester = 'Fall',
        string $defaultYear = '2026',
    ): ?array {
        $get = static fn (string $key): string => isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : '';

        $courseCodeRaw = $get('course_code');
        $courseNameRaw = $get('course_name');
        $examDate = $this->normalizeDateString($get('exam_date'));
        $timeSlot = $get('time_slot');

        // Fallback if no semantic course_code header was found: look for code pattern like SE111 / SWE211 / MAT101
        if ($courseCodeRaw === '') {
            foreach ($row as $cell) {
                if (preg_match('/\b([A-Z]{2,4}\s*-?\s*\d{3})\b/i', $cell, $m)) {
                    $courseCodeRaw = strtoupper(str_replace([' ', '-'], '', $m[1]));
                    break;
                }
            }
        }

        if ($courseCodeRaw === '' && $courseNameRaw === '' && $examDate === '' && $timeSlot === '') {
            return null;
        }

        // Split cross-listed course codes e.g. "SE 213 / SWE 223"
        $equivalentCode = $get('equivalent_course_code');
        if (str_contains($courseCodeRaw, '/')) {
            $parts = array_values(array_filter(array_map('trim', explode('/', $courseCodeRaw))));
            $courseCodeRaw = $parts[0] ?? $courseCodeRaw;
            if ($equivalentCode === '' && isset($parts[1])) {
                $equivalentCode = strtoupper(str_replace(' ', '', $parts[1]));
            }
        }

        // Clean course code & extract embedded section/batch if present e.g. "SE214 (45-A)"
        $courseCode = strtoupper(trim($courseCodeRaw));
        $embeddedBatch = null;
        $embeddedSection = null;
        if (preg_match('/^([A-Z0-9]+)\s*\((\d+)\s*-\s*([A-Z0-9]+)\)$/i', $courseCode, $m)) {
            $courseCode = strtoupper($m[1]);
            $embeddedBatch = (int) $m[2];
            $embeddedSection = strtoupper($m[3]);
        } else {
            $courseCode = strtoupper(str_replace(' ', '', $courseCode));
        }

        // Skip summary/total rows
        if (in_array($courseCode, ['TOTAL', 'SUMMARY', 'NOTE'], true)) {
            return null;
        }

        $batchRaw = $get('batch');
        $batch = null;
        if ($batchRaw !== '' && preg_match('/\d+/', $batchRaw, $bm)) {
            $batch = (int) $bm[0];
        } elseif ($embeddedBatch !== null) {
            $batch = $embeddedBatch;
        }

        $section = strtoupper($get('section'));
        if ($section === '' && $embeddedSection !== null) {
            $section = $embeddedSection;
        }

        $roomNo = preg_replace('/\.0$/', '', $get('room_no')) ?? $get('room_no');
        $building = $get('building');
        if ($building === '' && $roomNo !== '') {
            $building = AcademicRoutine::resolveBuilding($roomNo);
        }

        $courseName = $courseNameRaw;
        if ($courseName === '' && $courseCode !== '') {
            $resolved = CourseIntegrationService::resolveCourseName($courseCode);
            $courseName = $resolved !== '' ? $resolved : $courseCode;
        }

        $dayOfWeek = $get('day_of_week');
        if ($dayOfWeek === '' && $examDate !== '') {
            $ts = strtotime($examDate);
            if ($ts !== false) {
                $dayOfWeek = date('l', $ts);
            }
        }

        $studentCountRaw = $get('student_count');
        $studentCount = ($studentCountRaw !== '' && is_numeric($studentCountRaw)) ? max(0, (int) $studentCountRaw) : null;

        $cohortScopeRaw = $get('cohort_scope');
        $majorTrack = ($track = strtoupper($get('major_track'))) !== '' ? $track : null;
        $isSelfStudy = stripos($batchRaw.' '.$cohortScopeRaw, 'self') !== false;
        $isOverlap = stripos($cohortScopeRaw.' '.$get('notes'), 'overlap') !== false;
        $cohortScope = $cohortScopeRaw !== ''
            ? $cohortScopeRaw
            : ($isOverlap ? 'Overlap' : ($isSelfStudy ? 'Self Study' : ($majorTrack !== null ? 'Major' : ($batch !== null ? 'Regular' : 'Unassigned/NA'))));

        $defaultSlot = ExamRoutine::DEFAULT_SLOTS[$examType][0] ?? '09:00 AM - 11:00 AM';

        return [
            'semester' => $get('semester') !== '' ? ucfirst(strtolower($get('semester'))) : $defaultSemester,
            'year' => $get('year') !== '' ? $get('year') : $defaultYear,
            'exam_type' => $examType,
            'exam_date' => $examDate !== '' ? $examDate : null,
            'day_of_week' => $dayOfWeek !== '' ? $dayOfWeek : null,
            'slot_code' => $get('slot_code') !== '' ? $get('slot_code') : null,
            'time_slot' => $timeSlot !== '' ? $timeSlot : $defaultSlot,
            'batch' => $batch,
            'section' => $section !== '' ? $section : null,
            'major_track' => $majorTrack,
            'cohort_scope' => $cohortScope,
            'is_self_study' => $isSelfStudy,
            'is_overlap' => $isOverlap,
            'course_code' => $courseCode !== '' ? $courseCode : 'EXAM',
            'equivalent_course_code' => $equivalentCode !== '' ? strtoupper(str_replace(' ', '', $equivalentCode)) : null,
            'course_name' => $courseName !== '' ? $courseName : ($courseCode ?: 'Exam Course'),
            'student_count' => $studentCount,
            'room_no' => $roomNo !== '' ? $roomNo : null,
            'building' => $building !== '' ? $building : null,
            'teacher_initials' => ($init = strtoupper($get('teacher_initials'))) !== '' ? $init : null,
            'notes' => ($notes = $get('notes')) !== '' ? $notes : null,
        ];
    }

    /**
     * Detect semester name & year from spreadsheet banner rows above the table header.
     *
     * @param  array<int, array<int, string>>  $matrix
     * @return array{0: string, 1: string}
     */
    protected function detectBannerTerm(array $matrix, int $headerIdx): array
    {
        $semester = 'Fall';
        $year = date('Y');
        $limit = min(count($matrix), max(1, $headerIdx + 1));

        for ($i = 0; $i < $limit; $i++) {
            $line = implode(' ', $matrix[$i]);
            if (preg_match('/\b(Spring|Summer|Fall)\s*[-,]?\s*(20\d{2})\b/i', $line, $m)) {
                $semester = ucfirst(strtolower($m[1]));
                $year = $m[2];
                break;
            }
        }

        return [$semester, $year];
    }

    /**
     * Recognize DIU SWE's 7-column multi-slot exam routine matrix form:
     * [Date/Day | Slot A | Batch | Slot B | Batch | Slot C | Batch].
     *
     * @param  array<int, string>  $headers
     */
    protected function isDepartmentMatrixGrid(array $headers): bool
    {
        $joined = strtolower(implode(' | ', $headers));

        return str_contains($joined, 'slot a') && str_contains($joined, 'slot b');
    }

    /**
     * Parse the memorized DIU SWE date-slot matrix grid into normalized exam entries.
     *
     * @param  array<int, array<int, string>>  $matrix
     * @return array<int, array<string, mixed>>
     */
    protected function parseDepartmentMatrixRows(
        array $matrix,
        int $headerIdx,
        string $examType,
        string $semester,
        string $year,
    ): array {
        $entries = [];
        $slotDefs = $this->extractSlotHeaderMap($matrix[$headerIdx] ?? [], $examType);

        $currentDate = null;
        $currentDay = null;

        for ($r = $headerIdx + 1; $r < count($matrix); $r++) {
            $row = $matrix[$r];
            $joinedLower = strtolower(implode(' ', $row));

            // Stop at student instructions / committee footer
            if (str_contains($joinedLower, 'instructions for students') || str_contains($joinedLower, 'committee member')) {
                break;
            }

            // Detect repeated Slot A / Slot B / Slot C header rows before each date block
            if (str_contains($joinedLower, 'slot a') && str_contains($joinedLower, 'slot b')) {
                $slotDefs = $this->extractSlotHeaderMap($row, $examType);

                continue;
            }

            $dateCol = trim((string) ($row[0] ?? ''));
            if ($dateCol !== '') {
                [$parsedDate, $parsedDay] = $this->parseMatrixDateCell($dateCol);
                if ($parsedDate !== null) {
                    $currentDate = $parsedDate;
                    $currentDay = $parsedDay;
                }
            }

            foreach ($slotDefs as $slotInfo) {
                $courseCell = trim((string) ($row[$slotInfo['course_col']] ?? ''));
                $batchCell = trim((string) ($row[$slotInfo['batch_col']] ?? ''));

                if ($courseCell === '' && $batchCell === '') {
                    continue;
                }

                // Handle Overlap rows ("Overlap" in slot cell)
                if (strcasecmp($courseCell, 'Overlap') === 0 || strcasecmp($batchCell, 'Overlap') === 0) {
                    $entries[] = [
                        'semester' => $semester,
                        'year' => $year,
                        'exam_type' => $examType,
                        'exam_date' => $currentDate,
                        'day_of_week' => $currentDay,
                        'slot_code' => $slotInfo['slot_code'],
                        'time_slot' => $slotInfo['time_slot'],
                        'batch' => null,
                        'section' => null,
                        'major_track' => 'OVERLAP',
                        'cohort_scope' => 'Overlap',
                        'is_self_study' => false,
                        'is_overlap' => true,
                        'course_code' => 'OVERLAP',
                        'equivalent_course_code' => null,
                        'course_name' => 'Overlap Examination Slot',
                        'student_count' => null,
                        'room_no' => null,
                        'building' => null,
                        'teacher_initials' => null,
                        'notes' => 'Overlap clash resolution slot',
                    ];

                    continue;
                }

                $parsedCourse = $this->parseMatrixCourseCell($courseCell);
                if ($parsedCourse['course_code'] === '') {
                    continue;
                }

                $parsedBatch = $this->parseMatrixBatchCell($batchCell);
                $isOverlapSlot = $slotInfo['is_overlap'] || $parsedBatch['cohort_scope'] === 'Overlap';

                $entries[] = [
                    'semester' => $semester,
                    'year' => $year,
                    'exam_type' => $examType,
                    'exam_date' => $currentDate,
                    'day_of_week' => $currentDay,
                    'slot_code' => $slotInfo['slot_code'],
                    'time_slot' => $slotInfo['time_slot'],
                    'batch' => $parsedBatch['batch'],
                    'section' => null,
                    'major_track' => $parsedBatch['major_track'],
                    'cohort_scope' => $parsedBatch['cohort_scope'],
                    'is_self_study' => $parsedBatch['is_self_study'],
                    'is_overlap' => $isOverlapSlot,
                    'course_code' => $parsedCourse['course_code'],
                    'equivalent_course_code' => $parsedCourse['equivalent_course_code'],
                    'course_name' => $parsedCourse['course_name'],
                    'student_count' => $parsedCourse['student_count'],
                    'room_no' => null,
                    'building' => null,
                    'teacher_initials' => $parsedBatch['teacher_initials'],
                    'notes' => $parsedBatch['notes'],
                ];
            }
        }

        return $entries;
    }

    /**
     * @param  array<int, string>  $headerRow
     * @return array<int, array{slot_code: string, time_slot: string, course_col: int, batch_col: int, is_overlap: bool}>
     */
    protected function extractSlotHeaderMap(array $headerRow, string $examType): array
    {
        $slots = [];
        $count = count($headerRow);

        for ($i = 0; $i < $count; $i++) {
            $cell = trim((string) ($headerRow[$i] ?? ''));
            if (preg_match('/Slot\s*([A-C])/i', $cell, $m)) {
                $letter = strtoupper($m[1]);
                $slotCode = "Slot {$letter}";
                $defaultIdx = match ($letter) {
                    'A' => 0,
                    'B' => 1,
                    default => 2,
                };
                $timeSlot = ExamRoutine::DEFAULT_SLOTS[$examType][$defaultIdx] ?? '09:00 AM - 11:00 AM';
                $isOverlap = false;

                if (preg_match('/(\d{1,2}:\d{2}\s*(?:am|pm)?\s*[-–]\s*\d{1,2}:\d{2}\s*(?:am|pm)?)/i', $cell, $tm)) {
                    $normalizedTime = $this->normalizePrintedSlotTime($tm[1], $letter);
                    $timeSlot = $normalizedTime;
                    if (str_contains(strtolower($normalizedTime), '08:30 am') || str_contains(strtolower($normalizedTime), '02:30 pm')) {
                        $isOverlap = true;
                    }
                }

                $batchCol = ($i + 1 < $count) ? $i + 1 : $i;
                $slots[] = [
                    'slot_code' => $slotCode,
                    'time_slot' => $timeSlot,
                    'course_col' => $i,
                    'batch_col' => $batchCol,
                    'is_overlap' => $isOverlap,
                ];
            }
        }

        return $slots;
    }

    protected function normalizePrintedSlotTime(string $rawTime, string $slotLetter): string
    {
        $clean = trim((string) preg_replace('/\s+/', ' ', str_replace('–', '-', $rawTime)));

        // Fix known PDF/department printing quirks: 12:00 am - 02:00 pm -> 12:00 PM - 02:00 PM; 2:30 am - 4:00 pm (Slot C) -> 02:30 PM - 04:00 PM
        if ($slotLetter === 'B') {
            $clean = str_ireplace('12:00 am', '12:00 pm', $clean);
        }
        if ($slotLetter === 'C') {
            $clean = str_ireplace('2:30 am', '02:30 pm', $clean);
            $clean = str_ireplace('2:00 am', '02:00 pm', $clean);
            $clean = str_ireplace('3:00 am', '03:00 pm', $clean);
        }

        return strtoupper($clean);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function parseMatrixDateCell(string $cell): array
    {
        $clean = trim($cell);
        $date = null;
        $day = null;

        if (preg_match('/(\d{2}\/\d{2}\/\d{4}|\d{4}-\d{2}-\d{2})/', $clean, $m)) {
            $date = $this->normalizeDateString($m[1]);
        }
        if (preg_match('/\b(Saturday|Sunday|Monday|Tuesday|Wednesday|Thursday|Friday)\b/i', $clean, $dm)) {
            $day = ucfirst(strtolower($dm[1]));
        } elseif ($date !== null && ($ts = strtotime($date)) !== false) {
            $day = date('l', $ts);
        }

        return [$date, $day];
    }

    /**
     * @return array{course_code: string, equivalent_course_code: ?string, course_name: string, student_count: ?int}
     */
    protected function parseMatrixCourseCell(string $cell): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $cell) ?: [])));
        if (empty($lines)) {
            return ['course_code' => '', 'equivalent_course_code' => null, 'course_name' => '', 'student_count' => null];
        }

        $studentCount = null;
        $last = $lines[count($lines) - 1];
        if (count($lines) >= 2 && preg_match('/^\d{1,4}$/', $last)) {
            $studentCount = (int) $last;
            array_pop($lines);
        }

        $firstLine = array_shift($lines) ?? '';
        $courseCode = '';
        $equivCode = null;

        if (preg_match('/^([A-Z]{2,4}\s*-?\s*\d{3})(?:\s*\/\s*([A-Z]{2,4}\s*-?\s*\d{3}))?(.*)$/i', $firstLine, $m)) {
            $courseCode = strtoupper(str_replace([' ', '-'], '', $m[1]));
            if (! empty($m[2])) {
                $equivCode = strtoupper(str_replace([' ', '-'], '', $m[2]));
            }
            $trailingTitle = trim((string) ($m[3] ?? ''));
            if ($trailingTitle !== '') {
                array_unshift($lines, $trailingTitle);
            }
        } elseif (preg_match('/\b([A-Z]{2,4}\s*-?\s*\d{3})\b/i', $cell, $m)) {
            $courseCode = strtoupper(str_replace([' ', '-'], '', $m[1]));
        }

        $courseName = trim(implode(' ', $lines));
        if ($courseName === '' && $courseCode !== '') {
            $courseName = CourseIntegrationService::resolveCourseName($courseCode) ?: $courseCode;
        }

        return [
            'course_code' => $courseCode,
            'equivalent_course_code' => $equivCode,
            'course_name' => $courseName,
            'student_count' => $studentCount,
        ];
    }

    /**
     * @return array{batch: ?int, major_track: ?string, cohort_scope: string, is_self_study: bool, teacher_initials: ?string, notes: ?string}
     */
    protected function parseMatrixBatchCell(string $cell): array
    {
        $clean = trim((string) preg_replace('/\s+/', ' ', $cell));
        $lower = strtolower($clean);

        $batch = null;
        $majorTrack = null;
        $cohortScope = 'Unassigned/NA';
        $isSelfStudy = false;
        $teacherInitials = null;
        $notes = null;

        if ($clean === '' || strcasecmp($clean, 'N/A') === 0) {
            return [
                'batch' => null,
                'major_track' => null,
                'cohort_scope' => 'Unassigned/NA',
                'is_self_study' => false,
                'teacher_initials' => null,
                'notes' => null,
            ];
        }

        if (str_contains($lower, 'merged')) {
            return [
                'batch' => null,
                'major_track' => 'MERGED',
                'cohort_scope' => 'Merged',
                'is_self_study' => false,
                'teacher_initials' => null,
                'notes' => $clean,
            ];
        }

        if (str_contains($lower, 'self study')) {
            $isSelfStudy = true;
            $cohortScope = 'Self Study';
            if (preg_match('/self\s*study\s+([A-Z]{2,5})\b/i', $clean, $im)) {
                $teacherInitials = strtoupper($im[1]);
            }

            return [
                'batch' => null,
                'major_track' => 'SELF-STUDY',
                'cohort_scope' => $cohortScope,
                'is_self_study' => true,
                'teacher_initials' => $teacherInitials,
                'notes' => 'Self Study',
            ];
        }

        if (preg_match('/^[A-Z]{2,4}$/', $clean)) {
            return [
                'batch' => null,
                'major_track' => 'SELF-STUDY',
                'cohort_scope' => 'Self Study',
                'is_self_study' => true,
                'teacher_initials' => strtoupper($clean),
                'notes' => 'Self Study / Special Cohort',
            ];
        }

        if (preg_match('/\b(\d{2})\b/', $clean, $bm)) {
            $batch = (int) $bm[1];
        }

        if (str_contains($lower, 'non-major') || str_contains($lower, 'non major')) {
            $majorTrack = 'Non-Major';
            $cohortScope = 'Non-Major';
        } elseif (str_contains($lower, 'major')) {
            if (preg_match('/\b(DS|CS|ST|RE)\s*Major\b/i', $clean, $mm)) {
                $majorTrack = strtoupper($mm[1]).' Major';
            } else {
                $majorTrack = 'Major';
            }
            $cohortScope = 'Major';
        } elseif (str_contains($lower, 'all')) {
            $majorTrack = 'ALL';
            $cohortScope = 'All';
        } elseif ($batch !== null) {
            $cohortScope = 'Regular';
        }

        return [
            'batch' => $batch,
            'major_track' => $majorTrack,
            'cohort_scope' => $cohortScope,
            'is_self_study' => $isSelfStudy,
            'teacher_initials' => $teacherInitials,
            'notes' => $notes,
        ];
    }

    protected function normalizeDateString(string $date): string
    {
        $clean = trim($date);
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $clean, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return $clean;
    }

    protected function formatPotentialExcelDate(string $value): string
    {
        if ($value === '' || ! is_numeric($value)) {
            return $value;
        }

        $num = (float) $value;
        if ($num >= 40000 && $num <= 65000) {
            $unix = (int) round(($num - 25569) * 86400);

            return gmdate('Y-m-d', $unix);
        }

        return $value;
    }

    protected function formatPotentialExcelTime(string $value): string
    {
        if ($value === '' || ! is_numeric($value)) {
            return $value;
        }

        $num = (float) $value;
        if ($num > 0 && $num < 1) {
            $seconds = (int) round($num * 86400);

            return gmdate('h:i A', $seconds);
        }

        return $value;
    }

    protected function columnLettersToIndex(string $letters): int
    {
        $index = 0;
        $letters = strtoupper($letters);
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }

    /**
     * @param  array<int, string>  $row
     */
    protected function isRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
