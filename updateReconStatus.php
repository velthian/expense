<?php
session_start();
date_default_timezone_set('Asia/Calcutta');

if(!isset($_SESSION['authenticated']))
{
    header('Location: index.php');
    die();
}

$uid = '';
$reconStatus = '';

$uid= $_POST['uid'];
$reconStatus = $_POST['reconStatus'];

$validInputs = FALSE;

if($uid != '' && is_numeric($uid))
{
    $validInputs = TRUE;
}

if($validInputs)
{
    if($reconStatus == '1' || $reconStatus == '0')
    {
        $validInputs = TRUE;
    }
    else
    {
        $validInputs = FALSE;
    }
}

if($validInputs)
{
    include_once('class/transactions.php');
    $obj = new transactions();
    $loginid = $_SESSION['loginid'];
    
    $res = $obj->updateReconciledStatus($reconStatus, $loginid, $uid);
    
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