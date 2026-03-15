<?php

class ccDates {
    
    public function stmtDate($dt,$mode)
    {
        switch($mode)
        {
            case('c'):
            {
                $d = date('d', strtotime($dt));
                if($d > 24)
                {
                    $m = date('m', strtotime($dt));
                    $y = date('Y', strtotime($dt));
                    $from_date = $y . "-" . $m . "-25";
                }
                else
                {
                    $dt1 = new DateTime($dt);
                    $dt1->modify("-1 months");
                    $m = $dt1->format('m');
                    $y = $dt1->format('Y');
                    $from_date = $y . "-" . $m . "-25";
                }
                $to_date = date('Y-m-d', strtotime($dt));
                break;
            }
            case('p'):
            {
                $dt = date('Y-m-d', strtotime($dt . ' -1 month'));
                $d = date('d', strtotime($dt));

                if($d > 24)
                {
                    $m = date('m', strtotime($dt));
                    $y = date('Y', strtotime($dt));
                    $from_date = $y . "-" . $m . "-25";
                    $to_date = date('Y-m-24', strtotime($dt . ' +1 month'));
                }
                else
                {
                    $dt = date('Y-m-d', strtotime($dt . ' -1 month'));
                    $d = date('d', strtotime($dt));
                    $m = date('m', strtotime($dt));
                    $y = date('Y', strtotime($dt));
                    $from_date = $y . "-" . $m . "-25";
                    $to_date = date('Y-m-24', strtotime($dt . ' +1 month'));
                }
                break;
            }
        }

        $res = array($from_date, $to_date);
        return($res);   
    }
    
    public function getInceptionDate($loginid)
    {
        include('db.php');
        
        $sql = "SELECT * FROM profile WHERE username='" . $loginid . "'";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        
        $fyBeginDate = '';
        $fyBeginDate = $row['fyBeginDate'];
        
        $fyEndDate = '';
        $fyEndDate = $row['fyEndDate'];
        
        $date = array('fyBeginDate' => $fyBeginDate, 'fyEndDate' => $fyEndDate);

        return $date;
        $conn->close();
    }
    
    public function putFYdate($fy_date, $fy_end_date, $loginid)
    {
        include('db.php');
        $flag = FALSE;
        // prepare and bind
        $stmt = '';
        $stmt = $conn->prepare("INSERT INTO profile (username, fyBeginDate, fyEndDate) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE fyBeginDate=VALUES(fyBeginDate), fyEndDate =VALUES(fyEndDate)");
        $stmt->bind_param("sss", $loginid, $fy_date, $fy_end_date);
        
        if($stmt->execute())
        {
            $flag = TRUE;
        }
        else
        {
            $flag = FALSE;
        }
        
        return $flag;
        $conn->close();
    }
}
