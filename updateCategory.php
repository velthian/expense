<?php
if (session_status() === PHP_SESSION_NONE) 
{
    session_start();
}

if(!isset($_SESSION['authenticated']))
{
    $_SESSION['redirect']=true;
    $_SESSION['auth_error']="Please login first";
    header('Location: index.php');
    die();
}


date_default_timezone_set('Asia/Calcutta');

$category_id = '';
$merchant_id = '';
$validInputs = FALSE;

if(isset($_POST['category_id']) &&  isset($_POST['merchant_id']))
{
    $validInputs = TRUE;
    $category_id = $_POST['category_id'];
    $merchant_id = $_POST['merchant_id'];
}


if($validInputs)
{
    if($category_id != '' && is_numeric($category_id))
    {
        $validInputs = TRUE;
    }
}

if($validInputs)
{
    if($merchant_id != '' && is_numeric($merchant_id))
    {
        $validInputs = TRUE;
    }
    else
    {
        $validInputs = FALSE;
    }
}

$result = 0;
$res = FALSE;

if($validInputs)
{
    include_once('class/merchant.php');
    $obj = new merchant();
    $res = $obj->updateCategoryId($merchant_id, $category_id);
    if($res)
    {
        $result = 1;
    }
}
echo $result;

?>

