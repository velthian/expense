<?php

include('db.php');
$sql = "SELECT * FROM merchant";
$result = $conn->query($sql);

while($row = $result->fetch_assoc())
{
    
    $merchantName = '';
    $merchantName = $row['merchant_name'];
    $merchantName = strtoupper($merchantName);
    
    echo("fetched >> " . $row['merchant_name'] . "<br>");
    
    $merchantId = '';
    $merchantId = $row['merchant_id'];
    
    $sql1 = "UPDATE merchant SET merchant_name = '" . $merchantName . "' WHERE merchant_id='" . $merchantId . "'";
    $result1 = $conn->query($sql1);
    
    if($result1)
    {
        echo($merchantId . " >> update succeeded<br>");
    }
    else
    {
        echo($merchantId . " >> update failed<br>");
    }
}

$conn->close();
?>