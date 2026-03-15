<?php


while (($line = fgets($fh)) !== false) 
{
    // Normalize whitespace
    $norm = trim($line);

    // Match variants like:
    // "Statement Date~25/07/2025"
    // "Statement  Date  ~  25/07/2025"
    // Case-insensitive
    if (preg_match('/^statement\s*date\s*~\s*([0-3]?\d\/[01]?\d\/\d{4})/i', $norm, $m)) {
        $statementDateStr = $m[1];
        break;
    }
}



    include('db.php');
    $sql = "
                SELECT 
                    t.date, 
                    m.merchant_name, 
                    t.amount,
                    t.uid
                FROM 
                    transactions t
                INNER JOIN 
                    merchant m ON t.merchant_id = m.merchant_id
                WHERE 
                    t.date BETWEEN DATE_SUB(?, INTERVAL 1 DAY) AND DATE_ADD(?, INTERVAL 1 DAY)
                AND
                    t.amount = ?
                AND
                    t.mode = 'creditcard'
            ";

    // Prepare statement
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssd", $date, $date, $amount);

    $stmt->execute();
    $result = $stmt->get_result();

?>