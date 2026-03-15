<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

/**
 * Description of findTransaction
 *
 * @author anuragsinha
 */
class findTransaction 
{
    public function findTxn($loginid, $searchTerm, $fromDt, $toDt)
    {
        $txnList = array();
        
        include('db.php');
        
        $searchTerm = strtoupper($searchTerm);
        
        $sql = "SELECT t.date, t.mode, t.amount, m.merchant_name FROM transactions t INNER JOIN merchant m ON t.merchant_id = m.merchant_id WHERE t.username='" . $loginid . "' AND m.merchant_name LIKE '%" . $searchTerm . "%' AND (t.date BETWEEN '" . $fromDt . "' AND '" . $toDt ."') ORDER BY t.date DESC";
        $result = $conn->query($sql);
        if($result->num_rows > 0)
        {
            while($row = $result->fetch_assoc())
            {
                $txnList[] = array('merchantName' => $row['merchant_name'], 'date' => date('j M y',strtotime($row['date'])), 'amount' => round($row['amount'],2), 'mode' => $row['mode']);
            }
        }
        $conn->close();
        return $txnList;
    }
}
