<?php

if (session_status() === PHP_SESSION_NONE) 
{
    session_start();
}

date_default_timezone_set('Asia/Calcutta');
if(!isset($_SESSION['authenticated']))
{
    header('Location: index.php');
    die();
}

$_SESSION['timestamp'] = time();

//VALIDATE INPUTS

$uid = '';
$merchant_name = '';
$amount = '';
$date = '';
$mode = '';
$error = '';
$validInputs = FALSE;
$loginid = $_SESSION['loginid'];
$cat_id = '';

if(isset($_POST['uid']) && ($_POST['uid'] != '') && (is_numeric($_POST['uid'])))
{
    $uid = (int)$_POST['uid'];
    $validInputs = TRUE;
}

if($validInputs)
{
    if(isset($_POST['merchant']) && ($_POST['merchant'] != ''))
    {
        $merchant_name = $_POST['merchant'];
    }
    else
    {
        $validInputs = FALSE;
        $error = 'Please select a valid merchant name';
    }
}

if($validInputs)
{
    if(isset($_POST['amount']) && (is_numeric($_POST['amount'])))
    {
        $amount = $_POST['amount'];
    }
    else
    {
        $validInputs = FALSE;
        $error = 'Please select a valid amount';
    }
}

if($validInputs)
{
    if(isset($_POST['date']) && ($_POST['date'] != ''))
    {
        $date_to_check = '';
        $date_to_check = $_POST['date'];
        list($year,$month,$day) = explode('-', $date_to_check); 
        if(checkdate($month, $day, (int)$year))
        {
            $date = '';
            $date = $date_to_check;
            $validInputs = TRUE;
        }
        else
        {
            $validInputs = FALSE;
            $error = "Please select a valid date";
        }
    }
    else
    {
        $error = 'Please select a valid date';
        $validInputs = FALSE;
    }
}

if($validInputs)
{
    if(isset($_POST['mode']) && ($_POST['mode'] == 'netbanking' || $_POST['mode'] == 'creditcard' || $_POST['mode'] == 'upi'))
    {
        $mode = $_POST['mode'];
    }
    else
    {
        $validInputs = FALSE;
        $error = 'Please select a valid mode';
    }
}

if($validInputs)
{
    if(isset($_POST['cat_id']) && is_numeric($_POST['cat_id']))
    {
        $cat_id = $_POST['cat_id'];
    }
    else
    {
        $validInputs = FALSE;
        $error = 'Please select a valid category';
    }
}

if($validInputs)
{
    //input is clean now and now we should make the edits
    include_once('class/transactions.php');
    $obj = new transactions();
    $res = FALSE;
    $res = $obj->editTransaction($uid, $merchant_name, $amount, $date, $mode, $loginid);
}
else
{
    $res = FALSE;
}

if($res)
{
    echo(json_encode(array('ok', $error)));
}
else
{
    echo(json_encode(array('not_ok', $error)));
}
?>
