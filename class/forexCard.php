<?php
class forexCard

{
    public function getForexUID()
    {
        include('db.php');

        $uid_list = array();
        
        $sql = "SELECT uid FROM forexCard";
        $result = $conn->query($sql);
        
        while($row = $result->fetch_assoc())
        {
            $uid_list[] = $row['uid'];
        }

        $conn->close();
        return $uid_list;
    }

    public function updatedForexCard($uid, $currency_code, $amount, $converted_amount, $merchant_name, $txn_date, $txn_time, $mode)
    {
        $res = FALSE;

        include('db.php');
        // Assuming $conn is your MySQLi connection

        $sql = "INSERT INTO forexCard
          (uid, currency_code, amount, converted_amount, merchant_name,
           txn_date, txn_time, txn_datetime, created_at, mode)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE
          currency_code = VALUES(currency_code),
          amount = VALUES(amount),
          converted_amount = VALUES(converted_amount),
          merchant_name = VALUES(merchant_name),
          txn_date = VALUES(txn_date),
          txn_time = VALUES(txn_time),
          txn_datetime = VALUES(txn_datetime),
          mode = VALUES(mode)";

        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }

        $txn_datetime      = $txn_date . " " . $txn_time;

        $stmt->bind_param(
            "ssddsssss", 
            $uid,
            $currency_code, 
            $amount, 
            $converted_amount, 
            $merchant_name, 
            $txn_date, 
            $txn_time, 
            $txn_datetime,
            $mode
        );

        if ($stmt->execute()) 
        {
            $res = TRUE;
        } 
        else 
        {
            $res = FALSE;
        }

        $stmt->close();
        return $res;
    }

    public function getForexCardTxn($fromDate, $toDate)
    {
        include('db.php');

        $txnList = array();

        // Prepare statement
        $sql = "SELECT * FROM forexCard WHERE txn_date BETWEEN ? AND ? ORDER BY txn_datetime DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $fromDate, $toDate);

        // Execute
        $stmt->execute();
        $result = $stmt->get_result();

        // Fetch results
        while ($row = $result->fetch_assoc()) 
        {
            $txnList[] = array('uid' => $row['uid'], 'date' => $row["txn_date"], 'time' => $row["txn_time"], 'merchant' => $row["merchant_name"], 'amount' => $row["amount"], "amountInINR" => $row['converted_amount'], 'mode' => $row['mode']);
        }

        $stmt->close();
        $conn->close();

        return $txnList;
    }
}


?>