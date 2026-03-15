<?php

class csvStmt 
{
    public function storeStatementInDb($conn, $loginid, $csvFile)
    {
        $statement_id = null;               // <-- hold it once we have it

        // Open the file for reading
        if (($handle = fopen($csvFile, "r")) !== FALSE) 
        {
            $header = [
                'username'             => $loginid,
                'card_last4'           => null,     // "2991",
                'source_filename'      => basename($csvFile),
                'file_fingerprint'     => hash_file('sha256', $csvFile),
                'statement_date'       => null,     // ISO (often your period end)       
            ];

            $inTransactions = false;            // simple state flag
            $failedCounter = 0;

            while (($line = fgets($handle)) !== FALSE)
            {
                $line = trim($line);

                // Detect delimiter
                if (strpos($line, '~|~') !== false) 
                {
                    $data = array_map('trim', explode('~|~', $line));   // NEW format
                } 
                else 
                {
                    $data = array_map('trim', explode('~', $line));     // OLD format
                }        
                
                $countOfFields = 0;
                $countOfFields = count($data);

                if(!$inTransactions)
                {
                    switch($countOfFields)
                    {
                        case 1:
                            {
                                $col0 = trim((string)($data[0] ?? ''));   // '' if not set/null
                                if ($col0 !== '' && stripos($col0, 'Card No') !== false) 
                                {
                                    if (preg_match('/Card No\s*:.*?(\d{4})\s*$/i', $data[0], $m)) {
                                        $header['card_last4'] = $m[1];
                                    }
                                }
                                break;
                            }

                        case 2:
                            {
                                $key = trim($data[0]);
                                $val = trim($data[1]);

                                if(strtolower($key) == 'statement date')
                                {
                                    $dateObj = DateTime::createFromFormat('d/m/Y', trim($val));
                                    if ($dateObj) 
                                    {
                                        $header['statement_date'] = $dateObj->format('Y-m-d'); // → 2025-09-25
                                    } 
                                    else 
                                    {
                                        $header['statement_date'] = null; // or handle gracefully
                                    }
                                }
                                break;
                            }

                        default:
                            break;
                    }

                    if ($statement_id === null && $header['statement_date'] && $header['card_last4']) 
                    {
                        $statement_id = $this->storeStatementMetaData($conn, $header);  // <-- insert or fetch existing
                        $inTransactions = true;
                    }
                    else
                    {
                        continue;
                    }
                }

                if($inTransactions)
                {
                    if ($statement_id === null) 
                    {
                        // throw new RuntimeException("Statement ID not set before transactions.");
                        continue;
                    }

                    if (count($data) >= 5 && in_array(strtolower($data[0]), ['domestic','international']))
                    {                
                        $date = '';
                        $orig_date  = '';
                        $amount = 0;
                        $merchant = '';

                        $orig_date = trim($data[2]);

                        $dateObj = DateTime::createFromFormat('d/m/Y H:i:s', $orig_date)
                            ?: DateTime::createFromFormat('d/m/Y', $orig_date);
                        $date = $dateObj ? $dateObj->format('Y-m-d') : null;

                        // Detect NEW format (7 columns and amount is numeric)
                        if (count($data) == 7 && is_numeric(str_replace([','], '', $data[4]))) 
                        {
                            // New format:
                            $amount = $data[4];
                            $dc     = $data[5];
                        } 
                        // Otherwise fallback to OLD format
                        else 
                        {
                            // Old format:
                            $amount = $data[5];
                            $dc     = $data[6];
                        }

                        // Normalize amount
                        $amount = (float) str_replace(',', '', trim($amount));

                        // Normalize CR/DR
                        $dc = strtolower(trim($dc));
                        if (in_array($dc, ['cr','credit','cr.'])) {
                            $amount = -$amount;
                        }

                        $merchant = trim($data[3]);

                        //Now we have Date, Amount and Merchant from the CSV file
                        $tempArray = array();
                        $tempArray = array('statement_id' => $statement_id, 'date' => $date, 'amount' => $amount, 'merchant' => $merchant, 'reconciled' => 'no', 'uid' => '');
                        
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
                }
            }
        }
        else 
        {
            echo "Failed to open the file.\n";
        }

        return $statement_id;
    }

    private function storeStatementMetaData($conn, $header)
    {
        $statementDate = $header['statement_date'];   // 'Y-m-d'
        $username      = trim($header['username']);
        $cardLast4     = trim($header['card_last4']);
        $srcFile       = $header['source_filename'] ?? null;
        $fingerprint   = $header['file_fingerprint'] ?? null;

        $sql = "
            INSERT INTO statements
                (statement_date, username, card_number, source_filename, file_fingerprint)
            VALUES
                (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                -- Make mysqli->insert_id return the existing row's statement_id
                statement_id = LAST_INSERT_ID(statement_id),
                -- Optional: refresh metadata on re-import
                source_filename = VALUES(source_filename),
                file_fingerprint = VALUES(file_fingerprint)
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param(
            "sssss",
            $statementDate,
            $username,
            $cardLast4,
            $srcFile,
            $fingerprint
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
        //$row = array('statement_id' => $statement_id, 'date' => $date, 'amount' => $amount, 'merchant' => $merchant, 'reconciled' => 'no', 'uid' => '');

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

        $recRaw = trim($row['reconciled']) ?? 0;
        $uid = isset($row['uid']) && $row['uid'] !== '' ? (int)trim($row['uid']) : null;

        if ($statementId <= 0) 
        {
            throw new RuntimeException('Invalid statement_id');
        }
        
        if (!$date) 
        {
            throw new RuntimeException('Invalid or missing date');
        }

        $sql = "
            INSERT INTO statement_lines 
                (statement_id, date, amount, merchant_name, reconciled, uid)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                line_id = LAST_INSERT_ID(line_id)
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) 
        {
            throw new RuntimeException('Prepare failed: ' . $conn->error);
        }

        // types: i = int, s = string, d = double
        $ok = $stmt->bind_param("isdsii",
            $statementId,
            $date,
            $amount,
            $merchant,
            $recRaw,
            $uid
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

    public function markDbReconciled(mysqli $conn, $line_id, $uid, $calledFrom)
    {
        // 1) Validate inputs
        $lineId = filter_var($line_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($lineId === false) 
        {
            throw new InvalidArgumentException('line_id must be a positive integer');
        }

        $uidVal = $uid; //already sanitized from the controller

        // 2) Check existence to distinguish "not found" vs "no-op update"

        if($calledFrom == 'cc')
        {
            $chk = $conn->prepare('SELECT 1 FROM statement_lines WHERE line_id = ? LIMIT 1');
        }
        else
        {
            $chk = $conn->prepare('SELECT 1 FROM bank_statement_lines WHERE line_id = ? LIMIT 1');
        }

        if (!$chk) 
        {
            throw new RuntimeException('Prepare failed (existence check): ' . $conn->error);
        }
        
        $chk->bind_param('i', $lineId);
        
        if (!$chk->execute()) 
        {
            $err = $chk->error; $chk->close();
            throw new RuntimeException('Execute failed (existence check): ' . $err);
        }
        
        $exists = false;
        $chk->bind_result($dummy);
        if ($chk->fetch()) 
        { 
            $exists = true; 
        }
        $chk->close();

        if (!$exists) 
        {
            return ['success' => false, 'message' => 'UID given does not exist!'];
        }

        try 
        {
            // 1) Mark statement_lines as reconciled (your existing code)

            if($calledFrom == 'cc')
            {
                $sql1 = 'UPDATE statement_lines
                        SET reconciled = 1, uid = ?
                        WHERE line_id = ?
                        LIMIT 1';
            }
            else
            {
                $sql1 = 'UPDATE bank_statement_lines
                        SET reconciled = 1, uid = ?
                        WHERE line_id = ?
                        LIMIT 1';
            }
            $stmt1 = $conn->prepare($sql1);
            if (!$stmt1) 
            { 
                throw new RuntimeException('Prepare failed: ' . $conn->error); 
            }
            
            $stmt1->bind_param('ii', $uidVal, $lineId);
            if (!$stmt1->execute()) 
            { 
                $err = $stmt1->error; $stmt1->close(); 
                throw new RuntimeException('Execute failed: ' . $err); 
            }
            $rows1 = $stmt1->affected_rows;
            $stmt1->close();

            // 2) Mirror date/amount/merchant_name into transactions for the same uid
            // NOTE: This copies the values FROM the statement_lines row identified by line_id
            $sql2 = 'UPDATE transactions t
                    JOIN statement_lines s ON t.uid = s.uid
                    SET t.date = s.date,
                        t.amount = s.amount,
                        t.reconciled = 1
                    WHERE 
                        s.line_id =?
                        AND t.uid = ?';
                    // add AND t.username = ? AND t.mode = "creditcard" if applicable
            
            $stmt2 = $conn->prepare($sql2);
            
            if (!$stmt2) 
            { 
                throw new RuntimeException('Prepare failed: ' . $conn->error); 
            }
            
            $stmt2->bind_param('ii', $lineId, $uidVal);
            
            if (!$stmt2->execute()) 
            { 
                $err = $stmt2->error; $stmt2->close(); 
                throw new RuntimeException('Execute failed: ' . $err); 
            }
            
            $stmt2->close();

            $conn->commit();

            return [
                'success' => true,
                'message' => 'successfully updated both'
            ];
        } catch (Throwable $e) {
            $conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getStatementList($conn, $loginid)
    {
        $stmtList = [];

        try
        {
            $sql = "SELECT card_number, statement_date, statement_id, failedRecords FROM statements WHERE username = ?";
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
                    $stmtList[] = array('card_number' => $row['card_number'], 'statement_date' => $row['statement_date'], 'statement_id' => $row['statement_id'], 'failedRecords' => $row['failedRecords']);  // append each row as associative array
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
}