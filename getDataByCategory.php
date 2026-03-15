<?php

session_start();
date_default_timezone_set('Asia/Calcutta');

$chosen_category = '';
$chosen_month = '';
$filter = array();

$chosen_category = $_POST['chosen_category'];
$chosen_month = $_POST['chosen_month'];

$filter = filter_input(INPUT_POST, 'filter', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

$date = new DateTime($chosen_month);
$chosen_month = $date->format('Y-m');

$chosen_month = $chosen_month . "-01";

include_once('class/transactions.php');
$obj = new transactions();

$res = $obj->getTransactions($chosen_category, $chosen_month, $filter);

echo json_encode($res);

?>


