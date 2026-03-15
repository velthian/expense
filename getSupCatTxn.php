<?php
session_start();
date_default_timezone_set('Asia/Calcutta');

$supCatId = '';

$supCatId = $_POST['supCatId'];

$returnArray = array();

if(is_numeric($supCatId))
{
    require_once 'class/transactions.php';
    $txnobj = new transactions();
    $returnArray = $txnobj->getTransactionBySupCatByFY($supCatId);

}

echo json_encode($returnArray);
?>