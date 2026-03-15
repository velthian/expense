<?php
session_start();
date_default_timezone_set('Asia/Calcutta');

if(!isset($_SESSION['authenticated']))
{
    header('Location: index.php');
    die();
}

$uid = '';

$uid= $_POST['uid'];

$validInputs = FALSE;

if($uid != '' && is_numeric($uid))
{
    $validInputs = TRUE;
}

if($validInputs)
{
    include_once('class/transactions.php');
    $obj = new transactions();
    $loginid = $_SESSION['loginid'];
    
    $res = $obj->deleteTransaction($uid, $loginid);
    
    if($res)
    {
        echo('ok');
    }
    else
    {
        echo('not_ok');
    }
}
else
{
    echo('not_ok');
}

?>
