<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class bankStmt 
{
    private static function norm(?string $s): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string)$s)));
    }

    private static function cellVal(Worksheet $sheet, int $col, int $row, bool $formatted = true): string
    {
        // PhpSpreadsheet indices are 1-based
        $addr = Coordinate::stringFromColumnIndex($col) . $row;
        $cell = $sheet->getCell($addr);
        return (string)($formatted ? $cell->getFormattedValue() : $cell->getValue());
    }

    private static function isMasked(?string $s): bool
    {
        $t = trim((string)$s);
        return $t !== '' && preg_match('/^\*+$/', $t) === 1;
    }

    private static function parseDateToYmd(?string $s): ?string
    {
        if ($s === null) return null;
        $t = (string)$s;

        // Trim and normalize whitespace (including NBSP variants)
        $t = preg_replace('/[\x{00A0}\x{2007}\x{202F}]/u', ' ', $t); // NBSP, figure space, narrow NBSP
        $t = trim($t);
        if ($t === '' || self::isMasked($t)) return null;

        // Excel serial date (numeric) — do this early
        if (is_numeric($t) && (int)$t > 30) {
            $ts = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp((float)$t);
            return date('Y-m-d', $ts);
        }

        // Normalize separators: Unicode slashes → '/', then '-', '.' and spaces → '/'
        $t = str_replace(["\u{2044}", "\u{2215}"], '/', $t); // fraction & division slash
        $t = str_replace(['-', '.', ' '], '/', $t);
        $t = preg_replace('#/+#', '/', $t); // collapse repeats like '01//09//25'

        // Try explicit DD/MM/YY(YY) first using regex, so we can control 2-digit year
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2}|\d{4})$#', $t, $m)) {
            $d = (int)$m[1];
            $mth = (int)$m[2];
            $yraw = $m[3];

            // 2-digit year pivot: 00–69 -> 2000–2069, 70–99 -> 1970–1999
            if (strlen($yraw) === 2) {
                $yy = (int)$yraw;
                $year = ($yy <= 69) ? (2000 + $yy) : (1900 + $yy);
            } else {
                $year = (int)$yraw;
            }

            if (checkdate($mth, $d, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $mth, $d);
            }
            return null; // invalid date
        }

        // Fallback to strict known formats (after normalization)
        foreach (['d/m/Y','d/m/y','Y-m-d'] as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $t);
            if ($dt instanceof DateTime) {
                // If format had 2-digit year, PHP will already pivot to 19xx/20xx
                $errors = DateTime::getLastErrors();
                if (!$errors['warning_count'] && !$errors['error_count']) {
                    return $dt->format('Y-m-d');
                }
            }
        }

        // Last resort: loose parser (can misinterpret), so try to guard weird results
        $dt = date_create($t);
        if ($dt) {
            $y = (int)$dt->format('Y');
            // Protect against "year 25" type mis-parses
            if ($y < 1900) return null;
            return $dt->format('Y-m-d');
        }

        return null;
    }

    private static function normAmount(?string $s): ?float
    {
        if ($s === null) return null;
        $t = trim(str_replace([',', ' '], '', $s));
        if ($t === '' || self::isMasked($t)) return null;
        // Handle parentheses for negatives: (123.45) -> -123.45
        if (preg_match('/^\((.*)\)$/', $t, $m)) {
            $t = '-' . $m[1];
        }
        return is_numeric($t) ? (float)$t : null;
    }

    public function storeStatementInDb($conn, $loginid, \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $inputFilePath)
    {

        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        // 1) Scan entire sheet for account number and statement date
        $accountNumber = null;
        $statementDateRaw = null;
        $dateCandidates = [];

        for ($r = 1; $r <= $highestRow; $r++) {
            for ($c = 1; $c <= $highestColIndex; $c++) {
                $cell = self::cellVal($sheet, $c, $r, true);
                $n = self::norm($cell);
                if ($n === '') continue;

                // Account number heuristics
                if (preg_match('/\b(account|a\/c|a\/c no|account no|acct)\b/i', $cell)) {
                    // try to capture digits near this cell text
                    // capture sequences of digits, slashes, hyphens
                    if (preg_match('/([A-Z0-9\-\s]{6,})$/i', trim($cell), $m)) {
                        $candidate = trim($m[1]);
                        // prefer digit-heavy candidate
                        if (preg_match('/\d{4,}/', $candidate)) {
                            $accountNumber = $candidate;
                        }
                    }
                    // if cell is label and next column/row contains actual number, try those
                    if (!$accountNumber) {
                        // right cell
                        $right = trim(self::cellVal($sheet,$c+1, $r));
                        if ($right && preg_match('/\d{4,}/', $right)) $accountNumber = $right;
                        // below cell
                        $below = trim(self::cellVal($sheet,$c, $r+1));
                        if (!$accountNumber && $below && preg_match('/\d{4,}/', $below)) $accountNumber = $below;
                    }
                }

                // Statement date heuristics
                if (preg_match('/statement date|stmt date|statement from|period/i', $cell)) {
                    // try capture date inside same cell
                    if (preg_match('/(\d{1,2}[\/\-\.\s]\d{1,2}[\/\-\.\s]\d{2,4})/', $cell, $m)) {
                        $statementDateRaw = $m[1];
                    } else {
                        // check adjacent cells
                        $right = trim(self::cellVal($sheet,$c+1, $r));
                        $below = trim(self::cellVal($sheet,$c, $r+1));
                        foreach ([$right, $below] as $cand) {
                            if ($cand && preg_match('/(\d{1,2}[\/\-\.\s]\d{1,2}[\/\-\.\s]\d{2,4})/', $cand, $m2)) {
                                $statementDateRaw = $m2[1];
                                break;
                            }
                        }
                    }
                }

                // Collect any cells that look like a standalone date (for fallback)
                if (preg_match('/^\d{1,2}[\/\-\.\s]\d{1,2}[\/\-\.\s]\d{2,4}$/', trim($cell))) {
                    $dateCandidates[] = trim($cell);
                }
            }
        }

        // fallback: if still no statementDateRaw, use first date candidate near top rows
        if (!$statementDateRaw && !empty($dateCandidates)) {
            // pick the earliest row date among top 10 rows
            for ($r = 1; $r <= min(10, $highestRow); $r++) {
                for ($c = 1; $c <= $highestColIndex; $c++) {
                    $cell = trim(self::cellVal($sheet, $c, $r, true));
                    if ($cell && preg_match('/^\d{1,2}[\/\-\.\s]\d{1,2}[\/\-\.\s]\d{2,4}$/', $cell)) {
                        $statementDateRaw = $cell;
                        break 2;
                    }
                }
            }
        }

        // normalize statement date to YYYY-MM-DD if possible
        $statementDateNormalized = self::parseDateToYmd($statementDateRaw);

        // 2) Find header row for transactions (look for "date" and "description" words)
        $headerRow = null;
        $headerMap = []; // columnName => columnIndex
        for ($r = 1; $r <= $highestRow; $r++) 
        {
            $rowText = '';
            $cells = [];
            for ($c = 1; $c <= $highestColIndex; $c++) {
                $val = self::norm(self::cellVal($sheet, $c, $r, true));
                $cells[$c] = $val;
                $rowText .= ' ' . $val;
            }
            if (strpos($rowText, 'date') !== false && 
                (strpos($rowText, 'description') !== false || strpos($rowText, 'particulars') !== false || strpos($rowText, 'narration') !== false || strpos($rowText, 'transaction') !== false)) {
                $headerRow = $r;
                // identify columns
                foreach ($cells as $colIdx => $txt) {
                    if (strpos($txt, 'date') !== false) $headerMap['date'] = $colIdx;
                    if (strpos($txt, 'description') !== false || strpos($txt, 'particulars') !== false || strpos($txt, 'narration') !== false || strpos($txt, 'transaction') !== false) $headerMap['description'] = $colIdx;
                    if (strpos($txt, 'debit') !== false || strpos($txt, 'withdrawal') !== false) $headerMap['debit'] = $colIdx;
                    if (strpos($txt, 'credit') !== false || strpos($txt, 'deposit') !== false) $headerMap['credit'] = $colIdx;
                    if (strpos($txt, 'balance') !== false) $headerMap['balance'] = $colIdx;
                }
                break;
            }
        }

        // If header row not found, attempt to find using "particulars" or typical headers anywhere
        if (!$headerRow) {
            for ($r = 1; $r <= min(30, $highestRow); $r++) {
                $rowText = '';
                for ($c = 1; $c <= $highestColIndex; $c++) {
                    $rowText .= ' ' . self::norm(self::cellVal($sheet, $c, $r, true));
                }
                if (strpos($rowText, 'particulars') !== false || strpos($rowText, 'narration') !== false) {
                    $headerRow = $r;
                    // map will be found similarly (simple pass)
                    for ($c = 1; $c <= $highestColIndex; $c++) {
                        $txt = self::norm(self::cellVal($sheet, $c, $r, true));
                        if (strpos($txt, 'date') !== false) $headerMap['date'] = $c;
                        if (strpos($txt, 'particulars') !== false || strpos($txt, 'narration') !== false || strpos($txt, 'description') !== false) $headerMap['description'] = $c;
                        if (strpos($txt, 'debit') !== false) $headerMap['debit'] = $c;
                        if (strpos($txt, 'credit') !== false) $headerMap['credit'] = $c;
                        if (strpos($txt, 'balance') !== false) $headerMap['balance'] = $c;
                    }
                    break;
                }
            }
        }

        // 3) Extract transactions from rows below headerRow
        $transactions = [];

        if ($headerRow && isset($headerMap['date']) && isset($headerMap['description'])) {
            $r = $headerRow + 1;
            $blankStreak = 0;
            while ($r <= $highestRow && $blankStreak < 6) 
            {   
                // read row
                $dateCell = trim(self::cellVal($sheet, $headerMap['date'], $r));
                $descCell = trim(self::cellVal($sheet, $headerMap['description'], $r));

                $debitRaw   = isset($headerMap['debit'])   ? self::cellVal($sheet, $headerMap['debit'], $r)   : '';
                $creditRaw  = isset($headerMap['credit'])  ? self::cellVal($sheet, $headerMap['credit'], $r)  : '';
                $balanceRaw = isset($headerMap['balance']) ? self::cellVal($sheet, $headerMap['balance'], $r) : '';

                // normalize
                $dateNormalized = self::parseDateToYmd($dateCell);
                $debit   = self::normAmount($debitRaw);
                $credit  = self::normAmount($creditRaw);
                $balance = self::normAmount($balanceRaw);

                // ✔ simple, robust filter:
                // keep only if row STARTS with a valid date AND at least one of debit/credit is numeric
                $startsWithDate = ($dateNormalized !== null);
                $hasAmount = ($debit !== null || $credit !== null);

                if (!$startsWithDate || !$hasAmount) {
                    // treat as blank for early stop heuristic; or just skip without streaking
                    $blankStreak++;
                    $r++;
                    continue;
                }
                $blankStreak = 0;

                // push
                $transactions[] = [
                    'row'         => $r,
                    'date_raw'    => $dateCell,
                    'date'        => $dateNormalized,
                    'description' => $descCell,
                    'debit'       => $debit,    // now clean floats or null
                    'credit'      => $credit,   // clean float or null
                    'balance'     => $balance,  // clean float or null
                ];

                $r++;
            }
        } 
        else 
        {
            // fallback: try scanning for rows that look like transactions: any row with a cell like dd/mm/yyyy in columns
            for ($r = 1; $r <= $highestRow; $r++) 
            {
                for ($c = 1; $c <= $highestColIndex; $c++) 
                {
                    $cell = trim(self::cellVal($sheet, $c, $r));
                    $desc = trim(self::cellVal($sheet, $c+1, $r));
                    // Skip obvious masked/separator rows
                    if (self::isMasked($cell) || self::isMasked($desc)) 
                    {
                        $r++; 
                        continue;
                    }
                    $debit = trim(self::cellVal($sheet, $c+2, $r));
                    $credit = trim(self::cellVal($sheet, $c+3, $r));
                    $balance = trim(self::cellVal($sheet, $c+4, $r));

                    if ($cell) 
                    {
                        $dateNormalized = self::parseDateToYmd($cell);
                        $debitF  = self::normAmount($debit);
                        $creditF = self::normAmount($credit);

                        $startsWithDate = ($dateNormalized !== null);
                        $hasAmount = ($debitF !== null || $creditF !== null);

                        if ($startsWithDate && $hasAmount) {
                            $transactions[] = [
                                'row'         => $r,
                                'date_raw'    => $cell,
                                'date'        => $dateNormalized,
                                'description' => $desc,
                                'debit'       => $debitF,
                                'credit'      => $creditF,
                                'balance'     => self::normAmount($balance),
                            ];
                        }
                    }
                }
            }
        }

        // Output result
        $result = [
            'file' => basename($inputFilePath),
            'account_number' => $accountNumber,
            'statement_date_raw' => $statementDateRaw,
            'statement_date' => $statementDateNormalized,
            'header_row' => $headerRow,
            'header_map' => $headerMap,
            'transactions_count' => count($transactions),
            'transactions' => $transactions,
        ];

        // NEW: compute month-wise opening & closing
        $monthly = self::computeMonthlyBalances($result['transactions']);
        $opening_balance = null;
        $closing_balance = null;

        if (!empty($monthly)) 
        {
            $opening_balance = $monthly[0]['opening_balance'] ?? null;
            $closing_balance = $monthly[0]['closing_balance'] ?? null;
        }

        $header = [];
        $header['statement_date'] = $result['statement_date'];
        $header['username'] = $loginid;
        $header['file_fingerprint'] = hash_file('sha256', $inputFilePath);
        $header['opening_balance']  = $opening_balance;   // float|null
        $header['closing_balance']  = $closing_balance;   // float|null
        $inTransactions = false;
        $statement_id = null;

        if ($statement_id === null && $header['statement_date']) 
        {
            $statement_id = self::storeStatementMetaData($conn, $header);
            $inTransactions = true;
        }

        if($inTransactions)
        {
            $failedCounter = 0;
            foreach($result['transactions'] as $r)
            {
                $tempArray = array();

                $tempArray = array('statement_id' => $statement_id, 'date' => $r['date'], 'amount' => ($r['debit'] - $r['credit']), 'merchant' => $r['description'], 'reconciled' => 0);
                try 
                {
                    $insertResult = $this->storeStatementLines($conn, $tempArray);
                    if($insertResult['success'])
                    {
                        $tempArray['line_id'] = $insertResult['line_id'];
                    }
                    else
                    {
                        $failedCounter ++;
                    }
                } 
                catch (RuntimeException $e) 
                {
                    error_log("Skipping row: " . $e->getMessage());
                    $failedCounter ++;
                    continue; // move to next row
                }
            }
            //Update Statement Meta Data with Failed Record Count
            $stmt = $conn->prepare("
                UPDATE bankStatements
                SET failedRecords = ?
                WHERE statement_id = ?
            ");

            if (!$stmt) {
                throw new RuntimeException('Prepare failed: ' . $conn->error);
            }

            // Assume you already have $failedRecords (int or string) and $statementId (int)
            $stmt->bind_param("ii", $failedCounter, $statement_id);

            if (!$stmt->execute()) {
                throw new RuntimeException('Execute failed: ' . $stmt->error);
            }

            $stmt->close();
        }

        return $statement_id;
    }

    private function storeStatementMetaData($conn, $header)
    {
        $statementDate = $header['statement_date'];   // 'Y-m-d'
        $username      = trim($header['username']);
        $fingerprint = $header['file_fingerprint'];
        $open          = $header['opening_balance'] ?? null;   // float|null
        $close         = $header['closing_balance'] ?? null;   // float|null

        $sql = "
            INSERT INTO bankStatements
                (statement_date, username, file_fingerprint, opening_balance, closing_balance)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                -- keep existing if new is NULL, otherwise overwrite
                opening_balance = COALESCE(VALUES(opening_balance), opening_balance),
                closing_balance = COALESCE(VALUES(closing_balance), closing_balance),
                -- preserve the LAST_INSERT_ID behavior
                statement_id = LAST_INSERT_ID(statement_id)
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param(
            "sssdd",
            $statementDate,
            $username,
            $fingerprint,
            $open,
            $close
        );

        if (!$stmt->execute()) {
            error_log('Execute failed: ' . $stmt->error, 0);
        }

        // Works for both insert and duplicate: thanks to LAST_INSERT_ID trick
        $id = (int)$conn->insert_id;

        $stmt->close();
        return $id;
    }

    private function storeStatementLines($conn, $row)
    {
        $insertStatus = FALSE;
        $insertedId = null;

        $statementId  = (int)($row['statement_id'] ?? 0);
        
        $dateIn = trim((string)($row['date'] ?? ''));
        $date   = null;
        if ($dateIn !== '') 
        {
            $dt = DateTime::createFromFormat('Y-m-d', $dateIn)
               ?: DateTime::createFromFormat('d/m/Y H:i:s', $dateIn)
               ?: DateTime::createFromFormat('d/m/Y', $dateIn);
            if ($dt) 
            { $date = $dt->format('Y-m-d'); }
        }
        
        $amount       = (float)str_replace([',',' '], '', (string)($row['amount'] ?? 0));
        $merchant     = substr(trim((string)($row['merchant'] ?? '')), 0, 256);

        $recRaw = $row['reconciled'] ?? 0;

        if ($statementId <= 0) 
        {
            throw new RuntimeException('Invalid statement_id');
        }
        
        if (!$date) 
        {
            throw new RuntimeException('Invalid or missing date');
        }

        $sql = "
            INSERT INTO bank_statement_lines 
                (statement_id, date, amount, merchant_name, reconciled)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                line_id = LAST_INSERT_ID(line_id)
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) 
        {
            throw new RuntimeException('Prepare failed: ' . $conn->error);
        }

        // types: i = int, s = string, d = double
        $ok = $stmt->bind_param("isdsi",
            $statementId,
            $date,
            $amount,
            $merchant,
            $recRaw
        );

        if (!$ok) {
            $stmt->close();
            throw new RuntimeException('Bind failed: ' . $stmt->error);
        }

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Execute failed: ' . $err);
        }
        else
        {
            $insertStatus = TRUE;
            $insertedId = $conn->insert_id;
        }

        $stmt->close();
        return [
        'success'  => $insertStatus,
        'line_id'  => $insertedId
    ]   ;
    }

    public function getStatementList($conn, $loginid)
    {
        $stmtList = [];

        try
        {
            $sql = "SELECT statement_date, statement_id, failedRecords FROM bankStatements WHERE username = ?";
            $stmt = $conn->prepare($sql);
            if(!$stmt)
            {
                throw new RuntimeException('Prepare failed: ' . $conn->error); 
            }
            $stmt->bind_param('s', $loginid);
            if(!$stmt->execute())
            {
                $err = $stmt->error; $stmt->close(); 
                throw new RuntimeException('Execute failed: ' . $err); 
            }

            $result = $stmt->get_result();
            if ($result) 
            {
                while ($row = $result->fetch_assoc()) 
                {
                    $stmtList[] = array('statement_date' => $row['statement_date'], 'statement_id' => $row['statement_id'], 'failedRecords' => $row['failedRecords']);  // append each row as associative array
                }
                $result->free();
            }

            $stmt->close();
            return ['status' => true, 'arrayList' => $stmtList, 'message' => 'success'];
        }
        catch (Throwable $e) {
            return ['status' => false, 'arrayList' => $stmtList, 'message' => $e->getMessage()];
        }

    }

    // NEW
    private static function computeMonthlyBalances(array $txs): array
    {
        // Ensure chronological order (tie-breaker by 'row' if present)
        usort($txs, function($a, $b) {
            $da = $a['date'] ?? '';
            $db = $b['date'] ?? '';
            if ($da === $db) {
                return ($a['row'] ?? 0) <=> ($b['row'] ?? 0);
            }
            return strcmp($da, $db);
        });

        $buckets = []; // 'YYYY-MM' => ['first'=>tx, 'last'=>tx, 'sum_debit'=>, 'sum_credit'=>]
        foreach ($txs as $t) {
            if (empty($t['date'])) continue;
            $ym = substr($t['date'], 0, 7); // YYYY-MM
            if (!isset($buckets[$ym])) {
                $buckets[$ym] = [
                    'first'      => $t,
                    'last'       => $t,
                    'sum_debit'  => (float)($t['debit']  ?? 0.0),
                    'sum_credit' => (float)($t['credit'] ?? 0.0),
                ];
            } else {
                $buckets[$ym]['last'] = $t;
                $buckets[$ym]['sum_debit']  += (float)($t['debit']  ?? 0.0);
                $buckets[$ym]['sum_credit'] += (float)($t['credit'] ?? 0.0);
            }
        }

        $out = [];
        $prevClosingByMonth = [];

        foreach ($buckets as $ym => $b) {
            $first = $b['first'];
            $last  = $b['last'];

            $hasFirstBal = isset($first['balance']) && $first['balance'] !== null;
            $hasLastBal  = isset($last['balance'])  && $last['balance']  !== null;

            // Convention: running balance is AFTER applying the row.
            // Then: opening_before_first = balance_after_first + debit_first - credit_first
            $opening = null;
            if ($hasFirstBal) 
            {
                $opening = (float)$first['balance'] + (float)($first['debit'] ?? 0) - (float)($first['credit'] ?? 0);
            } 
            else 
            {
                // Fallback: if previous month exists, use its closing; else null (unknown)
                // (We only know chronological order, but not continuity across statements without a balance)
                $opening = $prevClosingByMonth ? end($prevClosingByMonth) : null;
            }

            // Closing: prefer last row's balance; else derive = opening - sum(debits) + sum(credits)
            $closing = null;
            if ($hasLastBal) 
            {
                $closing = (float)$last['balance'];
            } 
            else 
            {
                if ($opening !== null) {
                    $closing = $opening - (float)$b['sum_debit'] + (float)$b['sum_credit'];
                }
            }

            $monthStart = $ym . '-01';
            $out[] = [
                'month_start'     => $monthStart,
                'opening_balance' => $opening,
                'closing_balance' => $closing,
            ];

            $prevClosingByMonth[$ym] = $closing;
        }

        return $out;
    }


    public static function getBalances(mysqli $conn, string $statement_date): ?array
    {
        $statement_date = date('Y-m-01', strtotime($statement_date));
        $sql = "SELECT opening_balance, closing_balance 
                FROM bankStatements 
                WHERE statement_date = ? LIMIT 1";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param('s', $statement_date);
        $stmt->execute();
        $result = $stmt->get_result();

        $balances = $result->fetch_assoc() ?: null;
        $stmt->close();

        return $balances;
    }

}