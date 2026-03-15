<?php
/*
category_id: category_id, desc: desc, amount: amount, date: date, mode: mode
 */

session_start();
date_default_timezone_set('Asia/Calcutta');

$sup_categ_id = '';
$category_id = '';
$desc = '';
$amount = '';
$date = '';
$mode = '';
$chosen_month = '';
$filter = array();

$sup_categ_id = $_POST['sup_categ_id'];
$category_id = $_POST['category_id'];
$desc = $_POST['desc'];
$amount = $_POST['amount'];
$date = $_POST['date'];
$mode = $_POST['mode'];
$chosen_month = $_POST['chosen_month'];
$called_from = $_POST['called_from'];
$filter = filter_input(INPUT_POST, 'filter', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

$date1 = new DateTime($chosen_month);
$chosen_month = $date1->format('Y-m');

$chosen_month = $chosen_month . "-01";

$desc = strtolower($desc);
$desc = ucwords($desc);

$mode = strtolower($mode);

include_once('class/transactions.php');
$obj = new transactions();

include('db.php');

$status = FALSE;

$status = $obj->updateTransactionManual($sup_categ_id, $category_id, $desc, $amount, $date, $mode, $called_from);

$conn->close();
echo json_encode($status);

?>
