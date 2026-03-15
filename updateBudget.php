<?php

session_start();
date_default_timezone_set('Asia/Calcutta');

$loginid = $_SESSION['loginid'];

$supCatId = '';
$catId = '';
$budget = '';

$supCatId = $_POST['supCatId'];
$catId = $_POST['catId'];
$budget = $_POST['budget'];

include_once('class/budget.php');
$obj = new budget();

$res = $obj->updateBudget($supCatId, $catId, $budget, $loginid);

echo $res;

?>

