<?php

session_start();
date_default_timezone_set('Asia/Calcutta');

$category_name = '';
$loginid = '';

$category_name = strtoupper($_POST['category_name']);
$loginid = $_SESSION['loginid'];

include_once('class/categories.php');
$obj = new categories();

$res = $obj->createCategory($category_name);

echo $res;

?>
