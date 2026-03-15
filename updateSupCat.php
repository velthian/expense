<?php

session_start();
date_default_timezone_set('Asia/Calcutta');

$uid = '';
$sup_cat = '';
$loginid = '';

$uid= $_POST['uid'];
$sup_cat = $_POST['sup_cat'];
$loginid = $_SESSION['loginid'];

include_once('class/transactions.php');
$obj = new transactions();

$res = $obj->updateSupCat($uid, $sup_cat, $loginid);

echo json_encode($res);

?>
