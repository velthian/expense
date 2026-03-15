<?php

session_start();
date_default_timezone_set('Asia/Calcutta');

$chosen_category = '';

$chosen_category = $_POST['chosen_category'];

include_once('class/categories.php');
$obj = new categories();

$data = array();
$data = $obj->getCategoryDescription($chosen_category, $_SESSION['loginid']);

echo(json_encode($data));

?>