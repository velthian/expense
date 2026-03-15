<?php
/*
category_id: category_id, desc: desc, amount: amount, date: date, mode: mode
 */

session_start();
date_default_timezone_set('Asia/Calcutta');

if(!isset($_SESSION['authenticated']))
{
    $_SESSION['redirect']=true;
    $_SESSION['auth_error']="Please login first";
    header('Location: index.php');
    die();
}

$sup_categ_id = '';
$category_id = '';
$desc = '';
$amount = '';
$date = '';
$mode = '';
$status = FALSE;

$sup_categ_id = $_POST['sup_categ_id'];
$category_id = $_POST['category_id'];
$desc = $_POST['desc'];
$amount = $_POST['amount'];
$date = $_POST['date'];
$mode = $_POST['mode'];

$desc = strtoupper($desc);

$mode = strtolower($mode);

include_once('../class/transactions.php');
$obj = new transactions();

$called_from = "addRecordManuallyPostRecon";
$status = $obj->updateTransactionManual($sup_categ_id, $category_id, $desc, $amount, $date, $mode, $called_from);
if($status)
{
    echo("0");    
}
else
{
    echo("1");
}
