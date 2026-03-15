<?php

session_start();
date_default_timezone_set('Asia/Calcutta');

if(!isset($_SESSION['authenticated']))
{
    header('Location: index.php');
    die();
}

$record_id = '';
$desc = '';
$amount = '';
$notes = '';

$record_id = $_POST['record_id'];

include_once('class/transactions.php');
$obj = new transactions();

$res = $obj->getRecordDetails($record_id);

echo json_encode($res);

?>