<?php

class reconcileCsvDb
{
    public function reconcileAndDisplayCsvDbValues($conn, $statement_id, $loginid, $calledFrom)
    {
        $csvReconciledArray = [];
        $csvUnReconciledArray = [];
        $dbReconciledArray = [];
        $dbUnReconciledArray = [];

        $statementDate = null;
        $openingBalance = null;
        $closingBalance = null;

        if($calledFrom === 'cc')
        {
            $sql = "SELECT statement_date 
                    FROM statements 
                    WHERE statement_id = ? 
                    LIMIT 1";
        }
        else
        {
            $sql = "SELECT statement_date, opening_balance, closing_balance 
                    FROM bankStatements 
                    WHERE statement_id = ? 
                    LIMIT 1";
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) 
        {
            throw new RuntimeException('Prepare failed: ' . $conn->error);
        }

        $statementId = (int)$statement_id;           // ensure integer
        if (!$stmt->bind_param('i', $statementId)) 
        {
            $stmt->close();
            throw new RuntimeException('Bind failed: ' . $stmt->error);
        }

        if (!$stmt->execute()) 
        {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Execute failed: ' . $err);
        }

        if($calledFrom === "cc")
        {
            $stmt->bind_result($statementDate);            
        }
        else
        {
            $stmt->bind_result($statementDate, $openingBalance, $closingBalance);
        }
        
        $ok = $stmt->fetch();   // true if a row, false if none, null on error

        if ($ok !== true) 
        {
            $statementDate = null; // not found or error -> keep null
        }
        $stmt->close();

        // $statementDate is either 'YYYY-MM-DD' or null if not found

        if($statementDate !== null)
        {
            if($calledFrom === 'cc')
            {
                $statementDatesArray = $this->computeStatementPeriod($statementDate);
                $beginDate = $statementDatesArray['period_start'];
                $lastDate = $statementDatesArray['period_end'];
            }
            else
            {
                $date = new DateTime($statementDate);
                $lastDate = $date->format('Y-m-t');
                $beginDate = $statementDate;
            }

            $dbArray = [];

            if($calledFrom === 'cc')
            {
                $sql = "
                            SELECT 
                                t.date, 
                                m.merchant_name, 
                                t.amount,
                                t.uid,
                                t.reconciled
                            FROM 
                                transactions t
                            INNER JOIN 
                                merchant m ON t.merchant_id = m.merchant_id
                            WHERE 
                                t.date BETWEEN ? AND ?
                            AND
                                t.mode = 'creditcard'
                            AND
                                t.username = ?
                            ORDER BY t.date, t.amount ASC
                        ";
            }
            else
            {
                $sql = "
                        SELECT 
                            t.date, 
                            m.merchant_name, 
                            t.amount,
                            t.uid,
                            t.reconciled
                        FROM 
                            transactions t
                        INNER JOIN 
                            merchant m ON t.merchant_id = m.merchant_id
                        WHERE 
                            t.date BETWEEN ? AND ?
                        AND
                            t.mode <> 'creditcard'
                        AND
                            t.username = ?
                        ORDER BY t.date, t.amount ASC
                    ";
            }


            // Prepare statement
            $stmt = $conn->prepare($sql);

            if (!$stmt) { throw new RuntimeException('Prepare failed: ' . $conn->error); }

            if (!$stmt->bind_param(
                    "sss",
                    $beginDate,
                    $lastDate,
                    $loginid
                )) 
            {
                $err = $stmt->error; $stmt->close();
                throw new RuntimeException('Bind failed: ' . $err);
            }

            if (!$stmt->execute()) 
            {
                $err = $stmt->error; $stmt->close();
                throw new RuntimeException('Execute failed: ' . $err);
            }
            
            $date = $merchant_name = $amount = $uid = $reconciled = null;

            $stmt->bind_result($date, $merchant_name, $amount, $uid, $reconciled);

            while ($stmt->fetch()) 
            {
                $dbArray[] = [
                    'date' => $date,
                    'amount' => $amount,
                    'merchant' => $merchant_name,
                    'reconciled' => $reconciled,
                    'uid' => $uid,
                    'matched' => false
                ];
            }
            $stmt->close();



            //Now dbArray has been created, now create csvArray and loop through each entry
            //Check if UID exists, if yes then simply find the corresponding dbArray element and bind it
            //if UID does not exist then go through the matching logic

            if($calledFrom === 'cc')
            {
                $sql = "SELECT * FROM statement_lines WHERE statement_id = ?";
            }
            else
            {
                $sql = "SELECT * FROM bank_statement_lines WHERE statement_id = ?";
            }
            
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) 
            {
                throw new RuntimeException('Prepare failed: ' . $conn->error);
            }

            $stmt->bind_param("i",$statement_id);

            if (!$stmt->execute()) 
            {
                error_log('Execute failed: ' . $stmt->error, 0);
            }

            $result = $stmt->get_result();   // <-- gives a mysqli_result object

            $csvArray = [];
            while ($row = $result->fetch_assoc()) 
            {
                $csvArray[] = array('line_id' =>$row['line_id'], 'date' => $row['date'], 'amount' => $row['amount'], 'merchant_name' => $row['merchant_name'], 'reconciled' => $row['reconciled'], 'uid' => $row['uid'], 'matched' => false);
            }

            if(!empty($csvArray))
            {
                foreach ($csvArray as &$row) 
                {
                    if ($row['reconciled'] == 1 && !is_null($row['uid'])) 
                    {
                        foreach ($dbArray as &$db) 
                        {
                            if ($db['uid'] === $row['uid']) 
                            {
                                $row['matched'] = true;
                                $csvReconciledArray[] = $row;       // goes into first array
                                $db['matched'] = true;              // ✅ create new element dynamically
                                $dbReconciledArray[] = $db;
                            }
                        }
                        unset($db);
                    } 
                    else 
                    {
                        $csvUnReconciledArray[] = $row;   // everything else
                    }
                }

                $dbUnReconciledArray = array_filter($dbArray, fn($db) => $db['matched'] === false);

                // Build DB index by normalized amount -> list of candidates (keep original index so we can mark used)
                $dbIndex = [];
                foreach ($dbUnReconciledArray as $idx => $entry) 
                {
                    $key = $this->normalizeAmount($entry['amount']);
                    $dbIndex[$key][] = [
                        'idx'   => $idx,
                        'entry' => $entry,
                        'used'  => false
                    ];
                }

                // For each CSV row, find best DB match with same amount and date within ±1 day
                $DAY_WINDOW = 1;

                require_once "class/csvStmt.php";
                $csvObj = new csvStmt();

                $i = 0;
                include 'db_new.php'; // gives $conn_new with autocommit OFF

                foreach ($csvUnReconciledArray as &$csvEntry) 
                {   
                    $amtKey = $this->normalizeAmount($csvEntry['amount']);


                    if (!isset($dbIndex[$amtKey])) 
                    {
                        $i++;
                        continue; // no DB entries with same amount
                    }

                    $csvDate = $this->dateObj($csvEntry['date']);
                    if (!$csvDate) 
                    { 
                        $i++;
                        continue; 
                    }

                    $bestK = null;
                    $bestDiff = PHP_INT_MAX;

                    // Scan candidates with same amount; pick the unused one with the smallest day difference ≤ window
                    foreach ($dbIndex[$amtKey] as $k => $cand) 
                    {
                        if ($cand['used'])
                        {
                            $i++;
                            continue;
                        }

                        $dbDate = $this->dateObj($cand['entry']['date']);
                        if (!$dbDate) 
                        {
                            $i++;
                            continue;
                        }

                        $diff = $this->daysApart($csvDate, $dbDate);
                        if ($diff <= $DAY_WINDOW && $diff < $bestDiff) 
                        {
                            $bestDiff = $diff;
                            $bestK = $k;
                            $uid = $cand['entry']['uid'];
                            // if exact same day, you can break early if you like
                            if ($bestDiff === 0) break;
                        }
                    }

                    if ($bestK !== null) 
                    {
                        // Mark used and push to display arrays

                        try 
                        {
                            $conn_new->begin_transaction();

                            // Step 1 — mark bank/CC statement row reconciled
                            $res = $csvObj->markDbReconciled($conn_new, $csvEntry['line_id'], $uid, $calledFrom);
                            if (!$res['success']) throw new Exception("Statement reconcile failed");

                            // Step 2 — mark transaction table row reconciled
                            $stmt = $conn_new->prepare("UPDATE transactions SET reconciled = 1 WHERE uid = ? AND username = ?");
                            $stmt->bind_param("is", $uid, $loginid);
                            if (!$stmt->execute()) throw new Exception("Failed to update transaction");

                            $conn_new->commit();
                            
                            $dbIndex[$amtKey][$bestK]['used'] = true;
                            $csvEntry['matched'] = true;
                            $csvReconciledArray[] = $csvEntry;
                            $dbIdx = $dbIndex[$amtKey][$bestK]['idx'];
                            $dbReconciledArray[] = $dbUnReconciledArray[$dbIdx];
                            $dbUnReconciledArray[$dbIdx]['matched'] = true;

                        } 
                        catch (Throwable $e) 
                        {
                            $conn_new->rollback();
                            $err_msg = '';
                            $err_msg = $uid . " failed to update transactions or bank/credit card statement_lines db";
                            error_log($err_msg, 0);    
                        }
                    }
                    $i++;
                }

                $csvUnReconciledArray = array_filter($csvUnReconciledArray, fn($un) => $un['matched'] === false);
                $dbUnReconciledArray = array_filter($dbUnReconciledArray, fn($un) => $un['matched'] === false);
            }
        }
        return ['csvReconciledArray' => $csvReconciledArray, 'csvUnReconciledArray' => $csvUnReconciledArray, 'dbReconciledArray' => $dbReconciledArray, 'dbUnReconciledArray' => $dbUnReconciledArray, 'beginDate' =>$beginDate, 'lastDate' => $lastDate, 'opening_balance' => $openingBalance, 'closing_balance' => $closingBalance];
    }

        private function amtKey($val): string 
    {
        // Canonicalize amounts (works for float or numeric strings)
        return is_float($val) ? number_format($val, 2, '.', '') : (string)$val;
    }

    private function normalizeMerchant(string $s): string 
    {
        $s = mb_strtoupper($s, 'UTF-8');
        $s = preg_replace('/[^A-Z0-9 ]+/u', ' ', $s); // letters/digits/spaces
        $s = preg_replace('/\s+/', ' ', trim($s));
        return $s;
    }

    private function merchantSimilarity(string $a, string $b): float 
    {
        $a = $this->normalizeMerchant($a);
        $b = $this->normalizeMerchant($b);

        $simPercent = 0.0;
        similar_text($a, $b, $simPercent);

        $maxLen = max(strlen($a), strlen($b));
        $levPercent = $maxLen ? max(0.0, (1 - (levenshtein($a, $b) / $maxLen)) * 100) : 100.0;

        // Composite score (tweak weights if you like)
        return 0.6 * $simPercent + 0.4 * $levPercent;
    }

    private function normalizeAmount($amt) 
    {
        // Avoid float issues: treat as integer paise/cents
        return (int) round(((float)$amt) * 100);
    }

    private function dateObj($d) 
    {
        // Expecting 'YYYY-MM-DD'
        return DateTimeImmutable::createFromFormat('Y-m-d', $d);
    }

    private function daysApart(DateTimeImmutable $a, DateTimeImmutable $b): int 
    {
        return (int)$a->diff($b)->format('%a');
    }


    private function computeStatementPeriod(string $statementDate): array
    {
        $end = new DateTimeImmutable($statementDate);
        $startMonthFirstPrev = $end->modify('first day of this month')->modify('-1 month');
        $start = $startMonthFirstPrev->setDate(
            (int)$startMonthFirstPrev->format('Y'),
            (int)$startMonthFirstPrev->format('m'),
            25
        );

        return [
            'period_start' => $start->format('Y-m-d'),
            'period_end'   => $end->format('Y-m-d'),
        ];
    }
}
?>