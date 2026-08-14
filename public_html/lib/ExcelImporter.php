<?php
/**
 * Excel import with conversation normalization
 */

declare(strict_types=1);

class ExcelImporter
{
    private const FIXED_HEADERS = [
        'date',
        'patient name',
        'patient\'s mother\'s name',
        'number',
        'remarks',
        'country',
        'city',
        'occupation',
        'details of concern',
    ];

    /**
     * Parse uploaded Excel/CSV into a preview structure (no DB writes except import record).
     */
    public static function preview(string $filePath, string $originalName): array
    {
        $rows = self::readSpreadsheet($filePath);
        if (count($rows) < 2) {
            throw new RuntimeException('The file appears empty or has no data rows.');
        }

        $headerRow = array_map(fn($h) => self::normalizeHeader((string) $h), $rows[0]);
        $map = self::mapColumns($headerRow);

        if ($map['name'] === null || $map['number'] === null) {
            throw new RuntimeException('Required columns not found. Expected at least Patient Name and Number.');
        }

        $previewRows = [];
        $errors = [];
        $warnings = [];
        $messageCount = 0;
        $duplicateNumberGroups = [];
        $numbersSeen = [];

        for ($i = 1; $i < count($rows); $i++) {
            $rowNum = $i + 1;
            $raw = $rows[$i];
            if (self::rowIsEmpty($raw)) {
                continue;
            }

            $patient = [
                'name' => self::cell($raw, $map['name']),
                'mother_name' => self::cell($raw, $map['mother_name']),
                'number' => self::cell($raw, $map['number']),
                'country' => self::cell($raw, $map['country']),
                'city' => self::cell($raw, $map['city']),
                'occupation' => self::cell($raw, $map['occupation']),
                'notes' => self::cell($raw, $map['remarks']),
            ];

            $excelDate = self::cell($raw, $map['date']);
            $parsedDate = parse_date($excelDate);

            $rowErrors = [];
            if ($patient['name'] === '') {
                $rowErrors[] = "Row {$rowNum} is missing a required patient name.";
            }
            if ($patient['number'] === '') {
                $rowErrors[] = "Row {$rowNum} is missing a required number.";
            }

            $messages = self::extractMessages($raw, $map, $parsedDate);
            $messageCount += count($messages);

            if (empty($messages)) {
                $warnings[] = "Row {$rowNum}: no conversation messages detected.";
            }

            foreach ($rowErrors as $err) {
                $errors[] = $err;
            }

            $existing = [];
            if ($patient['number'] !== '') {
                $existing = PatientRepository::findByNumber($patient['number']);
                $numbersSeen[$patient['number']] = ($numbersSeen[$patient['number']] ?? 0) + 1;
            }

            $previewRows[] = [
                'row_number' => $rowNum,
                'patient' => $patient,
                'excel_date' => $excelDate,
                'parsed_date' => $parsedDate,
                'messages' => $messages,
                'message_count' => count($messages),
                'existing_matches' => $existing,
                'errors' => $rowErrors,
                'resolution' => null, // set by user: create_new | use_existing:{id} | skip
            ];
        }

        // Flag same-number occurrences within the file
        foreach ($previewRows as &$pr) {
            $num = $pr['patient']['number'];
            $pr['file_duplicate_number'] = $num !== '' && ($numbersSeen[$num] ?? 0) > 1;
            if (!empty($pr['existing_matches']) || $pr['file_duplicate_number']) {
                $key = $num;
                if (!isset($duplicateNumberGroups[$key])) {
                    $duplicateNumberGroups[$key] = [
                        'number' => $num,
                        'existing' => $pr['existing_matches'],
                        'import_rows' => [],
                    ];
                }
                $duplicateNumberGroups[$key]['import_rows'][] = $pr['row_number'];
            }
        }
        unset($pr);

        $validRows = array_values(array_filter($previewRows, fn($r) => empty($r['errors'])));
        $needsResolution = array_values(array_filter($validRows, fn($r) => !empty($r['existing_matches']) || !empty($r['file_duplicate_number'])));

        $pdo = db();
        $stmt = $pdo->prepare('INSERT INTO excel_imports (filename, status, total_rows, messages_created, errors_count, warnings_count, preview_json)
            VALUES (?, \'preview\', ?, ?, ?, ?, ?)');
        $previewPayload = [
            'rows' => $previewRows,
            'column_map' => $map,
            'duplicate_groups' => array_values($duplicateNumberGroups),
        ];
        $previewJson = json_encode(
            $previewPayload,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($previewJson === false) {
            throw new RuntimeException('Could not prepare import preview data: ' . json_last_error_msg());
        }
        $stmt->execute([
            $originalName,
            count($previewRows),
            $messageCount,
            count($errors),
            count($warnings),
            $previewJson,
        ]);
        $importId = (int) $pdo->lastInsertId();

        foreach ($errors as $err) {
            $pdo->prepare('INSERT INTO excel_import_errors (import_id, row_number, severity, message) VALUES (?, NULL, \'error\', ?)')
                ->execute([$importId, $err]);
        }
        foreach ($warnings as $warn) {
            $pdo->prepare('INSERT INTO excel_import_errors (import_id, row_number, severity, message) VALUES (?, NULL, \'warning\', ?)')
                ->execute([$importId, $warn]);
        }

        return [
            'import_id' => $importId,
            'filename' => $originalName,
            'total_rows' => count($previewRows),
            'valid_rows' => count($validRows),
            'invalid_rows' => count($previewRows) - count($validRows),
            'messages_detected' => $messageCount,
            'needs_resolution_count' => count($needsResolution),
            'errors' => $errors,
            'warnings' => $warnings,
            'duplicate_groups' => array_values($duplicateNumberGroups),
            'rows' => $previewRows,
        ];
    }

    /**
     * Confirm import after user resolutions.
     * $resolutions: [ row_number => ['action' => 'create_new'|'use_existing'|'skip'|'none', 'patient_id' => ?] ]
     */
    public static function confirm(int $importId, array $resolutions): array
    {
        $stmt = db()->prepare('SELECT * FROM excel_imports WHERE id = ? AND status = \'preview\'');
        $stmt->execute([$importId]);
        $import = $stmt->fetch();
        if (!$import) {
            throw new RuntimeException('Import preview not found or already finalized.');
        }

        $payload = json_decode($import['preview_json'] ?? '', true);
        if (!is_array($payload) || empty($payload['rows'])) {
            throw new RuntimeException('Import preview data is missing.');
        }

        // Validate resolutions for rows with existing matches / duplicate-number decisions
        foreach ($payload['rows'] as $row) {
            if (!empty($row['errors'])) {
                continue;
            }
            $rn = (int) $row['row_number'];
            $needsDecision = !empty($row['existing_matches']) || !empty($row['file_duplicate_number']);
            if ($needsDecision) {
                $res = $resolutions[$rn] ?? $resolutions[(string) $rn] ?? null;
                if (!$res || empty($res['action'])) {
                    throw new RuntimeException("Row {$rn}: choose Create or None before confirming.");
                }
                $action = $res['action'];
                if (!in_array($action, ['create_new', 'skip', 'none'], true)) {
                    throw new RuntimeException("Row {$rn}: invalid resolution action.");
                }
            }
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $imported = 0;
            $newPatients = 0;
            $updatedPatients = 0;
            $messagesCreated = 0;

            foreach ($payload['rows'] as $row) {
                if (!empty($row['errors'])) {
                    continue;
                }
                $rn = (int) $row['row_number'];
                $needsDecision = !empty($row['existing_matches']) || !empty($row['file_duplicate_number']);
                $res = $resolutions[$rn] ?? $resolutions[(string) $rn] ?? ['action' => 'create_new'];
                $action = $res['action'] ?? 'create_new';

                // None / skip = do not create this patient
                if ($action === 'skip' || $action === 'none') {
                    continue;
                }

                if ($needsDecision && $action !== 'create_new') {
                    continue;
                }

                $patientId = PatientRepository::create($row['patient']);
                $newPatients++;

                $order = MessageRepository::nextImportOrder($patientId);
                foreach ($row['messages'] as $msg) {
                    MessageRepository::create([
                        'patient_id' => $patientId,
                        'sender_type' => $msg['sender_type'],
                        'message_text' => $msg['message_text'],
                        'message_date' => $msg['message_date'],
                        'import_order' => $order++,
                    ]);
                    $messagesCreated++;
                }
                $imported++;
            }

            $pdo->prepare('UPDATE excel_imports SET status = \'completed\', imported_rows = ?, new_patients = ?, updated_patients = ?, messages_created = ?, completed_at = NOW(), preview_json = NULL WHERE id = ?')
                ->execute([$imported, $newPatients, $updatedPatients, $messagesCreated, $importId]);

            $pdo->commit();

            return [
                'import_id' => $importId,
                'imported_rows' => $imported,
                'new_patients' => $newPatients,
                'updated_patients' => $updatedPatients,
                'messages_created' => $messagesCreated,
            ];
        } catch (Throwable $e) {
            $pdo->rollBack();
            $pdo->prepare('UPDATE excel_imports SET status = \'failed\' WHERE id = ?')->execute([$importId]);
            throw $e;
        }
    }

    public static function cancel(int $importId): void
    {
        $stmt = db()->prepare('UPDATE excel_imports SET status = \'cancelled\', preview_json = NULL WHERE id = ? AND status = \'preview\'');
        $stmt->execute([$importId]);
    }

    private static function extractMessages(array $raw, array $map, ?string $parsedDate): array
    {
        $messages = [];

        // 1) Details of Concern = first patient message
        // 2) Then alternating Ameer / patient columns left → right (Followup Date columns are ignored)
        // 3) Only the single Date column exists — it is the date of the LAST message only.
        //    Earlier messages have no recoverable date → null (shown as —).
        $concern = self::meaningfulText(self::cell($raw, $map['concern']));
        if ($concern !== '') {
            $messages[] = [
                'sender_type' => 'patient',
                'message_text' => $concern,
                'message_date' => null,
            ];
        }

        foreach ($map['conversation'] as $col) {
            // Ignore any Followup Date columns between responses
            if (($col['kind'] ?? '') === 'followup_date') {
                continue;
            }

            $text = self::meaningfulText(self::cell($raw, $col['index']));
            if ($text === '') {
                continue;
            }

            $messages[] = [
                'sender_type' => $col['sender_type'],
                'message_text' => $text,
                'message_date' => null,
            ];
        }

        // Date column = last response date (patient or Ameer)
        if ($parsedDate !== null && count($messages) > 0) {
            $messages[count($messages) - 1]['message_date'] = $parsedDate;
        }

        return $messages;
    }

    private static function meaningfulText(string $text): string
    {
        $text = trim($text);
        if ($text === '' || $text === '-' || $text === '—' || $text === '–') {
            return '';
        }
        return $text;
    }

    private static function mapColumns(array $headers): array
    {
        $map = [
            'date' => null,
            'name' => null,
            'mother_name' => null,
            'number' => null,
            'remarks' => null,
            'country' => null,
            'city' => null,
            'occupation' => null,
            'concern' => null,
            'conversation' => [],
            // kept for preview/debug compatibility
            'response_columns' => [],
        ];

        foreach ($headers as $idx => $header) {
            if ($header === '') {
                continue;
            }

            // Skip serial / index columns
            if (in_array($header, ['sr no', 'sr. no', 'sr', 's.no', 's no', 'serial', 'serial no', '#'], true)) {
                continue;
            }

            // Follow-up date columns are NOT used — sheet has only one Date (last response).
            // Skip them so they never become messages.
            if (
                $header === 'followup date'
                || $header === 'follow up date'
                || $header === 'follow-up date'
                || $header === 'followup_date'
            ) {
                continue;
            }

            if ($map['date'] === null && in_array($header, ['date', 'dated', 'case date'], true)) {
                $map['date'] = $idx;
                continue;
            }
            if ($map['name'] === null && in_array($header, ['patient name', 'patient_name', 'name'], true)) {
                $map['name'] = $idx;
                continue;
            }
            if ($map['mother_name'] === null && (
                str_contains($header, 'mother') || $header === 'patient\'s mother\'s name'
            )) {
                $map['mother_name'] = $idx;
                continue;
            }
            if ($map['number'] === null && in_array($header, ['number', 'phone', 'phone number', 'mobile'], true)) {
                $map['number'] = $idx;
                continue;
            }
            // Standalone Remarks (not Followup Remarks)
            if ($map['remarks'] === null && in_array($header, ['remarks', 'remark', 'case remarks', 'status'], true)) {
                $map['remarks'] = $idx;
                continue;
            }
            if ($map['country'] === null && $header === 'country') {
                $map['country'] = $idx;
                continue;
            }
            if ($map['city'] === null && $header === 'city') {
                $map['city'] = $idx;
                continue;
            }
            if ($map['occupation'] === null && in_array($header, ['occupation', 'job'], true)) {
                $map['occupation'] = $idx;
                continue;
            }
            if ($map['concern'] === null && (
                str_contains($header, 'details of concern')
                || in_array($header, ['concern', 'patient concern', 'issue'], true)
                || (str_contains($header, 'concern') && !str_contains($header, 'response'))
            )) {
                $map['concern'] = $idx;
                continue;
            }

            // Patient follow-up remarks (after concern columns)
            if (
                $header === 'followup remarks'
                || $header === 'follow up remarks'
                || $header === 'follow-up remarks'
                || $header === 'followup remark'
                || $header === 'follow up remark'
            ) {
                $entry = ['kind' => 'message', 'index' => $idx, 'sender_type' => 'patient', 'label' => $header];
                $map['conversation'][] = $entry;
                $map['response_columns'][] = $entry;
                continue;
            }

            // Ameer Sahab responses
            if (
                str_contains($header, 'ameer sahab response')
                || $header === 'ameer response'
                || ($header === 'ameer sahab')
                || (str_contains($header, 'ameer') && str_contains($header, 'response'))
            ) {
                $entry = ['kind' => 'message', 'index' => $idx, 'sender_type' => 'ameer_sahab', 'label' => $header];
                $map['conversation'][] = $entry;
                $map['response_columns'][] = $entry;
                continue;
            }

            // Legacy patient response columns
            if (
                str_contains($header, 'patient response')
                || $header === 'patient response'
                || ($header === 'response' && !str_contains($header, 'ameer'))
            ) {
                if (!str_contains($header, 'name') && !str_contains($header, 'mother')) {
                    $entry = ['kind' => 'message', 'index' => $idx, 'sender_type' => 'patient', 'label' => $header];
                    $map['conversation'][] = $entry;
                    $map['response_columns'][] = $entry;
                }
            }
        }

        return $map;
    }

    private static function normalizeHeader(string $header): string
    {
        $header = trim($header);
        // Strip UTF-8 BOM
        if (str_starts_with($header, "\xEF\xBB\xBF")) {
            $header = substr($header, 3);
        }
        $header = strtolower($header);
        // Normalize dashes/quotes/spaces from Excel exports
        $header = str_replace(["\xC2\xA0", '–', '—', '−'], [' ', '-', '-', '-'], $header);
        $header = preg_replace('/\s+/', ' ', $header) ?? $header;
        return trim($header);
    }

    private static function cell(array $row, ?int $index): string
    {
        if ($index === null || !array_key_exists($index, $row)) {
            return '';
        }
        $val = $row[$index];
        if ($val === null) {
            return '';
        }
        if (is_float($val) || is_int($val)) {
            // Avoid scientific notation for phone numbers
            if (floor((float) $val) == $val) {
                return trim(sprintf('%.0f', $val));
            }
            return trim((string) $val);
        }
        return utf8_sanitize(trim((string) $val));
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

    private static function readSpreadsheet(string $filePath): array
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($ext, ['csv', 'txt'], true)) {
            return self::readCsv($filePath);
        }

        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new RuntimeException('PhpSpreadsheet is required for Excel files. Run: composer install');
        }

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cellIter = $row->getCellIterator();
            $cellIter->setIterateOnlyExistingCells(false);
            $cells = [];
            foreach ($cellIter as $cell) {
                $cells[] = $cell->getValue();
            }
            $rows[] = $cells;
        }
        return $rows;
    }

    private static function readCsv(string $filePath): array
    {
        $rows = [];
        $fh = fopen($filePath, 'r');
        if (!$fh) {
            throw new RuntimeException('Unable to open uploaded file.');
        }
        // Detect delimiter from header line
        $first = fgets($fh);
        if ($first === false) {
            fclose($fh);
            return [];
        }
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
        $comma = substr_count($first, ',');
        $semi = substr_count($first, ';');
        $tab = substr_count($first, "\t");
        $delimiter = ',';
        if ($semi > $comma && $semi >= $tab) {
            $delimiter = ';';
        } elseif ($tab > $comma && $tab >= $semi) {
            $delimiter = "\t";
        }
        rewind($fh);
        while (($data = fgetcsv($fh, 0, $delimiter)) !== false) {
            if (isset($data[0])) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]) ?? (string) $data[0];
            }
            $rows[] = $data;
        }
        fclose($fh);
        return $rows;
    }
}
