<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

/**
 * Description of transactions
 *
 * @author anuragsinha
 */
class transactions {
    
    public function getTransactions($categories, $date_from, $filter)
    {
        $loginid = '';
        $loginid = $_SESSION['loginid'];
        $allSupCats = array();
        
        include('db.php');
        
        include_once('class/categories.php');
        $obj = new categories();
        
        $expensesThatMatter = 0;
        $super_credit_card_amt = 0;
        $finalArrayToReturn = array();
        
        $arrayNotEmpty = FALSE;
        
        foreach($categories as $c)
        {
            if($c != '')
            {
                $arrayNotEmpty = TRUE;
            }
        }
        
        $totalMonthlySpend = 0;

        if($arrayNotEmpty && ($date_from != ''))
        {
            $sql0 = "SELECT DISTINCT super_category_id FROM super_category WHERE username='" . $loginid . "'";
            $result0 = $conn->query($sql0);
            $num_rows0 = $result0->num_rows;
            if($num_rows0 > 0)
            {

                $date_from = new DateTime($date_from);
                $date_from = $date_from->format('Y-m-01');

                for($k=0; $k < $num_rows0; $k++)
                {
                    $row0 = $result0->fetch_assoc();
                    $sup_cat_id = '';
                    $sup_cat_desc = '';
                    $sup_cat_amt = 0;
                    $sup_cat_budg = 0;

                    $sup_cat_id = $row0['super_category_id'];
                    $sup_cat_desc = $obj->getSuperCategoryDescription($sup_cat_id);

                    $htmByCategory = '';

                    $cat_txn_data = array();

                    foreach($categories as $cat)
                    {
                        $returnCatData =  array();
                        $htmThisCategory = '';
                        $txn_data = array();
                        $categ_amount = 0;
                        $reconciled_cat_amt = 0;
                        $categ_budget = 0;
                        
                        $returnCatData = $this->getCategoryData($conn, $cat, $filter, $sup_cat_id, $date_from);

                        if ($returnCatData === null) 
                        {
                            // nothing to do for this category
                            continue; // assuming this is inside a loop over $cat
                        }

                        $txn_data = $returnCatData['cat_data'];
                        $categ_amount = $returnCatData['cat_amt'];
                        $reconciled_cat_amt = $returnCatData['reconciled_cat_amt'];
                        $cat_budget = $returnCatData['cat_budget'];
                        $cat_desc = $returnCatData['cat_desc'];
                        $super_credit_card_amt += $returnCatData['cat_credit_card_amt'];
                        
                        $sup_cat_amt = $sup_cat_amt + $categ_amount;
                        $totalMonthlySpend += $reconciled_cat_amt;

                        $cat_txn_data[] = array('cat_id' => $cat, 'cat_desc' => $cat_desc, 'cat_txn_data' => $txn_data, 'cat_amt' => $categ_amount, 'cat_reconciled_amt' => $reconciled_cat_amt, 'cat_budget' => $cat_budget);

                    } //end of loop for each category

                    //For Monthly Expenses, Others, Richa Dad and Annual Expenses create a sumtotal view as that matters
                    if($sup_cat_id === '1' || $sup_cat_id === '2' || $sup_cat_id === '3' || $sup_cat_id === '5')
                    {
                        $expensesThatMatter += $sup_cat_amt;
                    }

                    $finalArrayToReturn[] = array('sup_cat_id' => $sup_cat_id, 'sup_cat_desc' => $sup_cat_desc, 'sup_cat_amt' => $sup_cat_amt, 'all_cat_txn_data' => $cat_txn_data);
                    $allSupCats[] = array('sup_cat_id' => $sup_cat_id, 'sup_cat_desc' => $sup_cat_desc);

                } //end of loop for super categories
            } 
        }

        //GET THIS MONTH'S UNBILLED (TO BE SHOWN IN NEXT MONTH PAGE) CREDIT CARD AMOUNT
        $unbilledNext = 0;
        
        $date_arr = $this->getCycleDates($date_from);
        
        $next_from = $date_arr[0];
        $next_to = $date_arr[1];

        $unbilledNext = $this->getUnbilledCcAmount($conn, $loginid, $next_from, $next_to);
        
        $conn->close();

        return array('key_expenses' => $expensesThatMatter, 'total_monthly_spend' => $totalMonthlySpend, 'data' => $finalArrayToReturn, 'allSupCats' => $allSupCats, 'super_credit_card_amt' => $super_credit_card_amt, 'unbilled_next' => $unbilledNext);
    }
    
    private function getCategoryData($conn, $cat, $filter, $sup_cat_id, $date_from)
    {
        $cat_id = '';
        $cat_desc = '';
        $cat_amt = 0;
        $reconciled_cat_amt = 0;
        $cat_credit_card_amt = 0;

        $cat_txn_data = array();

        $loginid = '';
        $loginid = $_SESSION['loginid'];
        
        include_once('class/categories.php');
        $obj = new categories();
        
        include_once('class/budget.php');
        $budget_obj = new budget();
        
        $cat_id = $cat;
        $cat_desc = $obj->getCategoryDescription($cat_id, $loginid);

        $month_from = (new DateTime($date_from))->format('Y-m-01');
        $month_to   = (new DateTime($date_from))->format('Y-m-t');

        $modes       = is_array($filter) ? array_values(array_filter($filter)) : [];
        $wantCC      = in_array('creditcard', $modes, true);
        $nonCCModes  = array_values(array_filter($modes, fn($m) => $m !== 'creditcard')); // array
        $nonCC       = !empty($nonCCModes); 

        if (empty($modes)) 
        {
            $nonCC = []; // empty list means "all non-CC"
        }

        $queries = []; // store ['sql' => ..., 'params' => ..., 'types' => ...]

        // ----------------------
        // 1) NON-credit-card query
        // ----------------------
        if ($nonCC) 
        {
            // Safely escape each mode
            $safeModes = array_map(fn($m) => "'" . $conn->real_escape_string($m) . "'", $nonCCModes);
            $whereMode = "t.mode IN (" . implode(',', $safeModes) . ")";

            // Using simple <> 'creditcard'
            $sqlNonCC = "SELECT t.uid, t.merchant_id, m.merchant_name, t.amount, t.date, t.mode, 
                    t.super_category_id, t.reconciled
                FROM transactions t
                JOIN merchant m ON t.merchant_id = m.merchant_id
                WHERE m.category_id = ?
                AND t.username = ?
                AND t.date BETWEEN ? AND ?
                AND {$whereMode}
                AND t.super_category_id = ?
                ORDER BY t.date DESC";

            $queries[] = [
                'sql'    => $sqlNonCC,
                'types'  => "ssssi",
                'params' => [$cat_id, $loginid, $month_from, $month_to, $sup_cat_id]
            ];
        }

        // ----------------------
        // 2) Credit-card query
        // ----------------------
        if ($wantCC) 
        {
            include_once('class/ccDates.php');
            $cc = new ccDates();
            [$cc_from, $cc_to] = $cc->stmtDate($date_from, 'p');

            $sqlCC = "SELECT t.uid, t.merchant_id, m.merchant_name, t.amount, t.date, t.mode, 
                    t.super_category_id, t.reconciled
                FROM transactions t
                JOIN merchant m ON t.merchant_id = m.merchant_id
                WHERE m.category_id = ?
                AND t.username = ?
                AND t.date BETWEEN ? AND ?
                AND t.mode = 'creditcard'
                AND t.super_category_id = ?
                ORDER BY t.date DESC";

            $queries[] = [
                'sql'    => $sqlCC,
                'types'  => "ssssi",
                'params' => [$cat_id, $loginid, $cc_from, $cc_to, $sup_cat_id]
            ];
        }

        // ----------------------
        // Execute both queries
        // ----------------------
        foreach ($queries as $q) 
        {
            $categoryData = $this->populateCategoryData(
                $conn,
                $q['sql'],
                $q['types'],
                $q['params'],
                $loginid
            );

            //$data   .= $categoryData['txn_data'];
            if($categoryData !== null)
            {
                $cat_txn_data = array_merge($cat_txn_data, $categoryData['txn_data']);
                $cat_credit_card_amt += $categoryData['credit_card_amt'];
                $cat_amt += $categoryData['cat_amt'];
                $reconciled_cat_amt += $categoryData['reconciled_cat_amt'];
            }
        }

        if (abs($cat_amt) == 0) 
        {
            return null;
        }
        
        $categ_budget = 0;
        $categ_budget = $budget_obj->getBudget($sup_cat_id, $cat_id, $loginid);

        $returnCatData = array('cat_id' => $cat_id, 'cat_desc' => $cat_desc['description'], 'cat_amt' => $cat_amt, 'cat_data' => $cat_txn_data, 'reconciled_cat_amt' => $reconciled_cat_amt, 'cat_budget' => $categ_budget, 'cat_credit_card_amt' => $cat_credit_card_amt);
        return $returnCatData;
    }

    private function populateCategoryData($conn, $sql, $types, $params, $loginid)
    {
        $creditCardAmount = 0;
        $arrayToBeReturned = array();

        $stmt = $conn->prepare($sql);
        if (!$stmt) 
        {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if (!$stmt->bind_param($types,
            $params[0],
            $params[1],
            $params[2],
            $params[3], //cat_id
            $params[4]
        )) 
        {
            throw new Exception("Bind failed: " . $stmt->error);
        }

        if (!$stmt->execute()) 
        {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        $stmt->close();

        $cat_amt = 0;
        $reconciled_cat_amt = 0;
        $dataByCategory = array();

        include_once('class/categories.php');
        $SupCatObj = new Categories();

        while($row = $result->fetch_assoc())
        {
            $d = '';
            $d = $row['date'];
            $d = new DateTime($d);
            $d = $d->format('d M');
            
            $dReal = '';
            $dReal = $row['date'];

            $sup_cat_htm = '';
            $sup_cat_htm = $this->getSupCatHtm($row['super_category_id'], $loginid);

            $sup_cat_desc = $SupCatObj->getSuperCategoryDescription($row['super_category_id']);
            $supCatData = array($row['super_category_id'], $sup_cat_desc);

            $cat_amt = $cat_amt + $row['amount'];

            $statementDate = $this->calcStatementDate($row['date'], $row['mode']);
            $statementId   = $this->getStatementIdByMode($conn, $statementDate, $row['mode'], $loginid);

            if($row['mode'] == 'creditcard')
            {
                $url = "creditCardTxn.php?statement_id=" . $statementId;
            }
            else
            {
                $url = "bankAccountTxn.php?statement_id=" . $statementId;
            }

            if((int)$row['reconciled'] === 1)
            {
                $reconciled_cat_amt += $row['amount'];
            }
            if($row['mode'] == 'creditcard')
            {
                $creditCardAmount += $row['amount'];
            }
            
            $dataByCategory[] = array('reconciled_flag' => (int)$row['reconciled'], 'url' => $url, 'uid' => $row['uid'], 'merchant_name' => $row['merchant_name'], 'amount' => $row['amount'], 'dateFullFormat' => $dReal, 'dateHalfFormat' => $d, 'mode' => $row['mode'], 'supCat' => $supCatData, 'sup_cat_id' => $sup_cat_htm);

        }

        if (empty($dataByCategory)) 
        {
            return null;
        }

        return [
            'txn_data'              => $dataByCategory,
            'cat_amt'               => $cat_amt,
            'reconciled_cat_amt'    => $reconciled_cat_amt,
            'credit_card_amt'      => $creditCardAmount
        ];
    }

    private function getUnbilledCcAmount($conn, $loginid, $from, $to)
    {
        $sql = "SELECT SUM(t.amount) AS total 
                FROM transactions t
                JOIN merchant m ON t.merchant_id = m.merchant_id
                WHERE t.username = ?
                AND t.mode = 'creditcard'
                AND t.date BETWEEN ? AND ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sss', $loginid, $from, $to);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return (float)($result['total'] ?? 0);
    }

    
    public function updateTransactionManual($sup_categ_id, $category_id, $desc, $amount, $date, $mode, $called_from)
    {
        $loginid = '';
        $loginid = $_SESSION['loginid'];
        $status = FALSE;
        
        include(__DIR__ .'/../db_new.php');
        
        require_once __DIR__ . '/merchant.php';

        $obj = new merchant();
        $res = $obj->putMerchant($desc, $category_id, $loginid);
        
        if($res)
        {
            $merchant_id = '';
            $merchant_id = $obj->getMerchantId($desc, $loginid);
            if($merchant_id != 'error')
            {
                $uid = '';
                $uid = $this->generateUid($conn_new);
                if($uid != 'error')
                {               
                    $reconciled = 0;

                    if($called_from === 'addRecordManuallyPostRecon')
                    {
                        $reconciled = 1;
                    }
                    
                    $sql = "INSERT INTO transactions (uid, merchant_id, amount, date, mode, username, super_category_id, reconciled) VALUES ('" . $uid . "','" . $merchant_id . "','" . $amount . "','" . $date . "','" . $mode . "','" . $loginid . "','" . $sup_categ_id . "','" . $reconciled . "')";
                    $result = $conn_new->query($sql);
                    if($result)
                    {
                        $status = TRUE;
                    }
                    else
                    {
                        $status = FALSE;
                    }    
                }
                else
                {
                    $status = FALSE;
                }
            }
            else
            {
                $status = FALSE;
            }
        }
        else
        {
            $status = FALSE;
        }
        
        if($status)
        {
            mysqli_commit($conn_new);
        }
        else
        {
            mysqli_rollback($conn_new);
        }
        $conn_new->close();
        
        return $status;
    }
    
    public function getRecordDetails($record_id)
    {
        $loginid = '';
        $loginid = $_SESSION['loginid'];
        
        include('db.php'); 

        $sql = "SELECT * FROM categories_data WHERE record_id='" . $record_id . "' AND username='" . $loginid . "'";
        $result = $conn->query($sql);
        $num_rows = '';
        $num_rows = $result->num_rows;
        
        if($num_rows > 0)
        {
                $row = $result->fetch_assoc();
                $data_inner = array('record_id' => $row['record_id'], 'description' => $row['description'], 'amount' => $row['amount'], 'notes' => $row['notes']);  
        }
        
        $conn->close();
        return $data_inner;
        
    }
    
    //for the imap version, put transactions in the transactions table
    
    public function putTransactions($uid, $merchant_id, $amount, $date, $mode, $loginid)
    {
        include('db_new.php');
        $flag = FALSE;
        
        $sql = "INSERT INTO transactions (uid, merchant_id, amount, date, mode, username) VALUE ('" . $uid . "','" . $merchant_id . "','" . $amount . "','" . $date . "','" . $mode . "','" . $loginid . "')";
        if($conn_new->query($sql) === FALSE)
        {
            $flag = FALSE;
        }
        else
        {
            $flag = TRUE;
            mysqli_commit($conn_new);
        }
        
        mysqli_close($conn_new);
        return $flag;
    }
    
    public function generateUid($conn_new)
    {
        $loginid = '';
        $uid = 99999999;
        $loginid = $_SESSION['loginid'];
        
        $sql = "SELECT * FROM manual_uid WHERE username ='" . $loginid ."'";
        $result = $conn_new->query($sql);
        $num_rows = $result->num_rows;
        if($num_rows > 0)
        {
            $row = $result->fetch_assoc();
            $uid = (int) $row['max_manual_uid'];
        }
        $uid = $uid+1;
        
        $res = 1;
        if($uid != 100000000)
        {
            $sql1 = "DELETE FROM manual_uid WHERE username='" . $loginid . "'";
            if ($conn_new->query($sql1) === TRUE) 
            {
                $res = 0; 
                $sql2 = "INSERT INTO manual_uid (max_manual_uid, username) VALUES ('" . $uid . "','" . $loginid . "')";
                if($conn_new->query($sql2) === TRUE)
                {
                    $res = 0;
                }
                else
                {
                    $res = 1;
                }
            } 
            else 
            {
                $res = 1;
            }
        }
        else 
        {
            $sql2 = "INSERT INTO manual_uid (max_manual_uid, username) VALUES ('" . $uid . "','" . $loginid . "')";
            if($conn_new->query($sql2) === TRUE)
            {
                $res = 0;
            }
            else
            {
                $res = 1;
            }            
        }
        if($res == '0')
        {
            return $uid;
        }
        else
        {
            return('error');
        }
        
        
    }
    
    public function getAllUid($loginid)
    {   
        include('db.php');
        
        $sql = "SELECT uid FROM transactions WHERE username='" . $loginid . "'";
        $result = $conn->query($sql);
        $num_rows = $result->num_rows;
        
        $data = array();
        if ($num_rows > 0)
        {
            for($i=0; $i<$num_rows; $i++)
            {
                $uid = '';
                $row = $result->fetch_assoc();
                $uid = $row['uid'];
                array_push($data, $uid);
            }
        }
        
        return $data;
        $conn->close();
    }
    
    public function getCreditCardTxn($from_date, $to_date)
    {
        $loginid = '';
        $loginid = $_SESSION['loginid'];
        
        $data = array();
        
        include('db.php');
        
        $super_amount = 0;
        
        $sql0 = "SELECT DISTINCT super_category_id FROM super_category WHERE username='" . $loginid . "'";
        $result0 = $conn->query($sql0);
        $num_rows0 = $result0->num_rows;
        if($num_rows0 > 0)
        {
            include_once('class/categories.php');
            $obj = new categories();
            
            include_once('class/budget.php');
            $budget_obj = new budget();
            
            $sup_cat_wise_data = array();
                        
            for($k=0; $k < $num_rows0; $k++)
            {
                $row0 = $result0->fetch_assoc();
                $sup_cat_id = '';
                $sup_cat_desc = '';
                $sup_cat_amt = 0;
                
                $sup_cat_id = $row0['super_category_id'];
                $sup_cat_desc = $obj->getSuperCategoryDescription($sup_cat_id);
                
                $sql1 = "SELECT DISTINCT category_description, category_id FROM categories WHERE username='" . $loginid . "'";
                $result1 = $conn->query($sql1);
                $num_rows1 = $result1->num_rows;
                
                if($num_rows1 > 0)
                {
                                            
                    $categ_wise_data = array();
                        
                    for($j=0; $j<$num_rows1; $j++)
                    {
                        $row1 = $result1->fetch_assoc();
                        
                        $category_id = '';
                        $category_description = '';
                        $category_amount = 0;

                        $category_id = $row1['category_id'];
                        $category_description = $row1['category_description'];

                        $sql = "SELECT t.uid, t.date, m.merchant_name, t.amount, t.mode, t.super_category_id FROM transactions AS t INNER JOIN merchant AS m ON t.merchant_id = m.merchant_id WHERE t.username='" . $loginid . "' AND m.category_id='" . $category_id . "' AND t.mode = 'creditcard' AND DATE BETWEEN '" . $from_date . "' AND '" . $to_date . "' AND t.super_category_id ='" . $sup_cat_id . "' ORDER BY t.date DESC";
                        $result = $conn->query($sql);
                        $num_rows = $result->num_rows;

                        $data1 = array();
                        $data2 = array();
                        if($num_rows > 0)
                        {
                            for($i=0; $i<$num_rows; $i++)
                            {
                                $row = $result->fetch_assoc();

                                $sup_cat_data = $this->getSupCatHtm($row['super_category_id'], $loginid);

                                array_push($data2, array('uid' => $row['uid'], 'date' => date('d M',strtotime($row['date'])), 'merchant_name' => $row['merchant_name'], 'amount' => number_format((float)$row['amount'],2,'.',''), 'mode' => $row['mode'], 'sup_cat' => $sup_cat_data));
                                $category_amount = $category_amount + $row['amount'];
                            }
                        }

                        $data1[0] = $category_id;
                        $data1[1] = $category_description;
                        $data1[2] = number_format((float)$category_amount,2,'.','');
                        $data1[3] = $data2;
                        $categ_budget = 0;
                        $categ_budget = $budget_obj->getBudget($sup_cat_id, $category_id, $loginid);
                        $data1[4] = number_format((float)($categ_budget/12),2,'.','');
                         
                        
                        array_push($categ_wise_data, $data1);
                        $sup_cat_amt = $sup_cat_amt + $data1[2];
                    }

                }
                $sup_cat_wise_data[] = array('sup_cat_id' => $sup_cat_id, 'sup_cat_desc' => $sup_cat_desc, 'sup_cat_amt' => number_format((float)$sup_cat_amt,2,'.',''), 'sup_cat_data' => $categ_wise_data);
                
                $super_amount = $super_amount + $sup_cat_amt; //see if you want this amount
            }
        }
        
        $to_send_from_dt = '';
        $to_send_to_dt = '';
        
        $to_send_from_dt = date('d M',strtotime($from_date));
        $to_send_to_dt = date('d M',strtotime($to_date));
        
        $data = array('from_dt' => $to_send_from_dt, 'to_dt' => $to_send_to_dt, 'super_amt' => number_format((float)$super_amount,2,'.',''), 'expense_data' => $sup_cat_wise_data);
        
        return($data);
        $conn->close();
    }    
    
    public function updateSupCat($uid, $sup_cat, $loginid)
    {
        include('db.php');
        $res = FALSE;
        
        $sql = "UPDATE transactions SET super_category_id = '" . $sup_cat . "' WHERE uid='" . $uid . "' AND username ='" . $loginid . "'";
        if($conn->query($sql))
        {
            $res = TRUE;
        }
        return $res;
        $conn->close();
    }


    private function getSupCatHtm($super_category_id, $loginid)
    {
        include_once('class/categories.php');
        $obj = new Categories();

        $chosenSupCat = array();
                
        $super_cat_data = array();
        $super_cat_data = $obj->getAllSuperCategories($loginid);

        if(!empty($super_cat_data))
        { 
            foreach($super_cat_data as $sup_dat)
            {
                if($sup_dat['super_category_id'] == $super_category_id)
                {
                    $chosenSupCat = array('super_category_id' => $sup_dat['super_category_id'], 'super_category_desc' => $sup_dat['super_category_desc']);
                }
                else
                {
                    $chosenSupCat = array('super_category_id' => $sup_dat['super_category_id'], 'super_category_desc' => $sup_dat['super_category_desc']);
                }
            }
        }
       
        return $chosenSupCat;
    }
    
    public function updateReconciledStatus($reconciledFlag, $loginid, $uid)
    {
        
        $res = FALSE;
        
        if($reconciledFlag == '1' || $reconciledFlag == '0')
        {
            include('db.php');
            $sql = "UPDATE transactions SET reconciled=? WHERE uid=? AND username=?";
            $stmt = $conn->prepare($sql); 
            //$my = $conn->error;
            if($stmt != FALSE)
            {
                $stmt->bind_param("iis", $reconciledFlag, $uid, $loginid);
                $status = $stmt->execute();
                if($status)
                {
                   $res = TRUE; 
                }
            }
            else
            {
                $res = FALSE;
            }
        }
        $conn->close();
        return $res;
    }
    
    public function deleteTransaction($uid, $loginid)
    {
        $res_delete = false;

        include(__DIR__ . '/../db_new.php');  // gives $conn_new
        
        // Start SQL transaction
        $conn_new->begin_transaction();

        try {
            // 1) Fetch the transaction
            $sql = "SELECT * FROM transactions WHERE `uid`=? AND username=?";
            $stmt = $conn_new->prepare($sql);

            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn_new->error);
            }

            $stmt->bind_param("is", $uid, $loginid);
            $stmt->execute();

            $result = $stmt->get_result();
            if ($result === false) {
                throw new Exception("get_result failed: " . $conn_new->error);
            }

            $row = $result->fetch_assoc();
            $stmt->close();

            // If no such transaction → nothing to delete
            if (!$row) {
                throw new Exception("Transaction not found for UID: $uid");
            }

            $mode        = $row['mode'];
            $reconStatus = (int)$row['reconciled'];

            // 2) Reverse reconciled status if needed
            $res = true; // default

            if ($reconStatus == 1) {
                if ($mode === 'creditcard') {
                    $res = $this->markCCStatementLineUnreconciled($uid, $conn_new);
                } else {
                    $res = $this->markBankLineUnreconciled($uid, $conn_new);
                }
            }

            if (!$res) {
                throw new Exception("Failed to unreconcile corresponding statement line.");
            }

            // 3) Now delete the transaction itself
            $sql = "DELETE FROM transactions WHERE uid=? AND username=?";
            $stmt = $conn_new->prepare($sql);

            if ($stmt === false) {
                throw new Exception("Prepare failed for delete: " . $conn_new->error);
            }

            $stmt->bind_param("is", $uid, $loginid);
            $status = $stmt->execute();
            $stmt->close();

            if (!$status) {
                throw new Exception("Delete execute failed: " . $conn_new->error);
            }

            $res_delete = true;

            // Commit only when everything succeeds
            $conn_new->commit();
        }
        catch (Exception $e) {
            // Rollback everything
            $conn_new->rollback();
            error_log("deleteTransaction error: " . $e->getMessage());
            $res_delete = false;
        }
        $conn_new->close();
        return $res_delete;
    }

    private function markBankLineUnreconciled($uid, $conn_new)
    {
        $sql = "UPDATE bank_statement_lines SET reconciled = 0 WHERE uid = ?";
        $stmt = $conn_new->prepare($sql);

        if ($stmt === false) return false;

        $stmt->bind_param("i", $uid);
        $stmt->execute();

        $ok = ($stmt->affected_rows > 0);
        $stmt->close();

        return $ok;
    }

    private function markCCStatementLineUnreconciled($uid, $conn_new)
    {
        $sql = "UPDATE statement_lines SET reconciled = 0 WHERE uid = ?";
        $stmt = $conn_new->prepare($sql);

        if ($stmt === false) return false;

        $stmt->bind_param("i", $uid);
        $stmt->execute();

        $ok = ($stmt->affected_rows > 0);
        $stmt->close();

        return $ok;
    }
    
    public function getLastTransactionForMerchant($merchant_id, $loginid)
    {
        include('db.php');
        
        $amount = 0;
        
        $sql = "SELECT * FROM `transactions` WHERE `merchant_id` ='" . $merchant_id  . "' AND username = '" . $loginid  . "' AND timestamp = (SELECT MAX(timestamp) FROM transactions WHERE merchant_id = '" . $merchant_id . "' AND username = '" . $loginid . "') ORDER BY timestamp";
        
        $result = $conn->query($sql);
        $num_rows = $result->num_rows;
        if($num_rows > 0)
        {
            $row = $result->fetch_assoc();
            $amount = $row['amount'];
        }
        
        $conn->close();
        return $amount;
    }
    
    public function editTransaction($uid, $merchant_name, $amount, $date, $mode, $loginid)
    {
        $res = FALSE;
        
        //FIRST CHECK IF UID EXISTS
        include('db.php');
        $sql = "SELECT * FROM transactions WHERE uid=? AND username=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $uid, $loginid);
        $stmt->execute();
        $result = $stmt->get_result();
        $num_rows = $result->num_rows;
        if($num_rows > 0)
        {
            $row = $result->fetch_assoc();
            $merchant_id = '';
            $merchant_id = $row['merchant_id'];
            
            $sql1 = "SELECT * FROM merchant WHERE merchant_id=? AND username=?";
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bind_param("ss", $merchant_id, $loginid);
            $stmt1->execute();
            $result1 = $stmt1->get_result();
            $num1 = $result1->num_rows;
            if($num1 > 0)
            {
                $readyForUpdate = FALSE;
                
                $row1 = $result1->fetch_assoc();
                $merchant_name_db = $row1['merchant_name'];
                if($merchant_name_db == $merchant_name)
                {
                    //NO CHANGE IN MERCHANT NAME, UPDATE THE REST
                    $readyForUpdate = TRUE;
                }
                else
                {
                    //CREATE A NEW MERCHANT AND GET THE NEW MERCHANT ID
                    $category_id = $row1['category_id'];
                    include_once('class/merchant.php');
                    $merchObj = new merchant();
                    $merchantCreated = FALSE;
                    $merchantCreated = $merchObj->putMerchant($merchant_name, $category_id, $loginid);
                    if($merchantCreated)
                    {
                        $merchant_id = $merchObj->getMerchantId($merchant_name, $loginid);
                        if($merchant_id != 'error')
                        {
                            //UPDATE THE RECORD
                            $readyForUpdate = TRUE;
                        }
                    }
                }
                
                if($readyForUpdate)
                {
                    $sql2 = "UPDATE transactions SET merchant_id=?, amount=?, `date`=?, mode=? WHERE uid=? AND username=?";
                    $stmt2 = $conn->prepare($sql2);
                    $stmt2->bind_param('idssis', $merchant_id, $amount, $date, $mode, $uid, $loginid);

                    if($stmt2->execute())
                    {
                        $res = TRUE;
                        $res1 = $this->updateReconciledStatus("1", $loginid, $uid);
                    }
                    else
                    {
                        $res = FALSE;
                    }
                }
                else
                {
                    $res = FALSE;
                }
            }
            else
            {
                $res = FALSE;
            }
        }
        else
        {
            $res = FALSE;
        }
        
        $conn->close();
        return $res;
    }
    
    public function getTransactionBySupCatByFY($supCatId)
    {
        include('db.php');

        $sql = "
            SELECT 
                t.date,
                m.merchant_name,
                t.amount,
                CASE 
                    WHEN MONTH(t.date) >= 5 
                        THEN YEAR(t.date)
                    ELSE YEAR(t.date) - 1
                END AS fy_start
            FROM transactions t
            JOIN merchant m ON t.merchant_id = m.merchant_id
            WHERE t.super_category_id = ?
            ORDER BY fy_start DESC, t.date DESC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $supCatId);
        $stmt->execute();
        $result = $stmt->get_result();

        require_once "class/convertToIndian.php";
        $convObj = new convertToIndian();

        $fyData = [];

        while ($row = $result->fetch_assoc()) {

            $fyLabel = $row['fy_start'] . "-" . substr($row['fy_start'] + 1, 2);

            if (!isset($fyData[$fyLabel])) {
                $fyData[$fyLabel] = [
                    'total' => 0,
                    'data'  => []
                ];
            }

            $fyData[$fyLabel]['total'] += $row['amount'];

            $fyData[$fyLabel]['data'][] = [
                'date' => $row['date'],
                'merchant_name' => $row['merchant_name'],
                'amount' => $convObj->convertToIndianCurrency($row['amount'])
            ];
        }

        // Format totals
        foreach ($fyData as $fy => $vals) {
            $fyData[$fy]['totalAmt'] =
                $convObj->convertToIndianCurrency(round($vals['total'], 2));
            unset($fyData[$fy]['total']);
        }

        $stmt->close();
        $conn->close();

        return $fyData;
    }



    private function calcStatementDate(string $transaction_date, string $mode, int $cutoffDay = 25, string $tz = 'Asia/Kolkata'): string 
    {
        $dt = new DateTimeImmutable($transaction_date, new DateTimeZone($tz));

        // If not credit card → always 1st of the same month
        if (strtolower($mode) !== 'creditcard') 
        {
            return $dt->modify('first day of this month')->format('Y-m-d');
        }

        // Credit card logic
        $day = (int)$dt->format('j');
        $base = ($day > $cutoffDay) 
            ? $dt->modify('first day of next month') 
            : $dt->modify('first day of this month');

        $statement = $base
            ->setDate((int)$base->format('Y'), (int)$base->format('n'), $cutoffDay)
            ->setTime(0, 0, 0);

        return $statement->format('Y-m-d');
    }

    private function getStatementIdByMode(mysqli $conn, string $statementDate, string $mode, ?string $username = null): ?int 
    {
        // Choose table based on mode
        $table = (strtolower($mode) === 'creditcard') ? 'statements' : 'bankStatements';

        // Build SQL (with or without username filter)
        if ($username !== null) 
        {
            $sql = "SELECT statement_id FROM {$table} WHERE statement_date = ? AND username = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            if (!$stmt) { throw new RuntimeException('Prepare failed: ' . $conn->error); }
            $stmt->bind_param("ss", $statementDate, $username);
        } 
        else 
        {
            $sql = "SELECT statement_id FROM {$table} WHERE statement_date = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            if (!$stmt) { throw new RuntimeException('Prepare failed: ' . $conn->error); }
            $stmt->bind_param("s", $statementDate);
        }

        if (!$stmt->execute()) 
        {
            $err = $stmt->error; $stmt->close();
            throw new RuntimeException('Execute failed: ' . $err);
        }

        $stmt->bind_result($statementId);
        $found = $stmt->fetch();
        $stmt->close();

        return $found ? (int)$statementId : null; // null if not found
    }

    private function getCycleDates($date)
    {
        $dt = new DateTime($date);

        // To date: 24 of same month
        $to = (clone $dt)->setDate(
            $dt->format('Y'),
            $dt->format('m'),
            24
        )->format('Y-m-d');

        // From date: 25 of previous month
        $prev = (clone $dt)->modify('-1 month');
        $from = $prev->setDate(
            $prev->format('Y'),
            $prev->format('m'),
            25
        )->format('Y-m-d');

        return [$from, $to];
    }

    function getYtdCategoryAverages($conn, $loginid)
    {
        // Determine current FY
        $today = new DateTime();
        $year  = (int)$today->format('Y');

        // FY is May 1 to Apr 30
        if ($today->format('m') < 5) {
            // we are in Jan–Apr => FY started last year
            $fyStart = new DateTime(($year - 1) . '-05-01');
            $fyEnd   = new DateTime($year . '-04-30');
        } else {
            // we are in May–Dec => FY starts this year
            $fyStart = new DateTime($year . '-05-01');
            $fyEnd   = new DateTime(($year + 1) . '-04-30');
        }

        // Months passed in FY
        $months = ($fyStart->diff($today)->m + 1);

        // --------------------------
        // 1) Category-level summary
        // --------------------------
        $sqlCat = "
            SELECT 
                m.category_id,
                c.category_description,
                t.super_category_id,
                SUM(t.amount) AS total
            FROM transactions t
            JOIN merchant m 
                ON t.merchant_id = m.merchant_id
            JOIN categories c
                ON c.category_id = m.category_id   -- <--- added
            AND c.username = t.username         -- <--- ensures correct user
            WHERE t.username = ?
            AND t.date BETWEEN ? AND ?
            GROUP BY 
                m.category_id,
                c.category_description,
                t.super_category_id
        ";

        $stmt = $conn->prepare($sqlCat);
        $start = $fyStart->format('Y-m-d');
        $end   = $today->format('Y-m-d');
        $stmt->bind_param("sss", $loginid, $start, $end);
        $stmt->execute();

        $catRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // -------------
        // GROUP THEM
        // -------------

        $cats_grouped = [];   // final grouped structure

        foreach ($catRows as $row) {

            $supCatId = (int)$row['super_category_id'];

            if (!isset($cats_grouped[$supCatId])) {
                $cats_grouped[$supCatId] = [];
            }

            $cats_grouped[$supCatId][] = [
                'cat_id'   => (int)$row['category_id'],
                'cat_desc' => $row['category_description'],
                'ytd_amt'  => (float)$row['total'],
                'avg'      => round($row['total'] / $months, 2)
            ];
        }


        // --------------------------
        // 2) Super-category summary
        // --------------------------
        $sqlSup = "
            SELECT 
                t.super_category_id,
                SUM(t.amount) AS total
            FROM transactions t
            WHERE t.username = ?
            AND t.date BETWEEN ? AND ?
            GROUP BY t.super_category_id
        ";

        $stmt = $conn->prepare($sqlSup);
        $stmt->bind_param("sss", $loginid, $start, $end);
        $stmt->execute();
        $supRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $sups = [];

        require_once(__DIR__ . '/categories.php');
        $sup_cat_obj = new Categories();

        foreach ($supRows as $row) {
            $sups[] = [
                'sup_cat_id' => $row['super_category_id'],
                'sup_cat_desc' => $sup_cat_obj->getSuperCategoryDescription($row['super_category_id']),
                'ytd_amt'    => (float)$row['total'],
                'avg'        => round($row['total'] / $months, 2)
            ];
        }

        //Actually we need only super category data but passing category also for future need
        return [
            'FY_start' => $fyStart->format('Y-m-d'),
            'FY_end'   => $fyEnd->format('Y-m-d'),
            'months_count' => $months,
            'categories' => $cats_grouped,
            'super_categories' => $sups
        ];
    }

}
