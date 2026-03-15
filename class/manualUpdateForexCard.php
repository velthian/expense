<?php

class manualUpdateForexCard
{
    public function manualUpdateForexTxn($from, $uid, $date, $amount, $text, $dest)
    {

        include('db.php');
        $res = FALSE;

        // optional but recommended: throw exceptions on MySQL errors
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn->set_charset('utf8mb4');

        try 
        {
            require_once "class/forex.php";
            $forexObj = new forex();

            $audToINR = 1;
            $audToINR = $forexObj->getForexFromAPI("AUD","INR");
            if (!$audToINR || !is_numeric($audToINR)) 
            {
                $audToINR = 58;     //Safe value in case the API doesnt work
                error_log("manualUpdateForexCard.php >> AUD to INR conversion failed >> Line 24", 0);
            }

            require_once "class/forexCard.php";
            $forexCardObj = new forexCard();

            $conn->begin_transaction();
            if($from == "modal")
            {
                // 1) Insert into the counter table
                $stmt1 = $conn->prepare("INSERT INTO manual_forex_card (`date`) VALUES (?)");
                $stmt1->bind_param('s', $date);
                $stmt1->execute();

                // 2) Get the auto-increment id generated above
                $uniqueId = $conn->insert_id; // connection-scoped, concurrency-safe
                $uid               = "MAN" . $uniqueId;
            }
            
            // 3) Insert into your target table using that id

            
            $currency_code     = "AUD";
            $converted_amount  = $amount * $audToINR;
            $merchant_name     = $text;
            $txn_date          = $date;
            $txn_date          = date('Y-m-d', strtotime($txn_date));
            $txn_time          = "00:00:00";
            $source            = $dest;

            $res = $forexCardObj->updatedForexCard($uid, $currency_code, $amount, $converted_amount, $merchant_name, $txn_date, $txn_time, $source);

        } catch (mysqli_sql_exception $e) {
            // rollback on any failure
            $conn->rollback();
            $res = FALSE;
            // log the error in real apps; echo for demo
            error_log("manualUpdateForexCard.php >> " . $e->getMessage() . " >> Line 61", 0);
        }

        return $res;
    }
}

?>