<?php
session_start();

$merch_id = '';
$merch_id = trim($_POST['merch_id']);

$loginid = $_SESSION['loginid'];

include_once('class/merchant.php');

$obj = new merchant();

$data = '';
$data = $obj->getMerchantCategory($merch_id, $loginid);

echo $data;

?>