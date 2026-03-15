<?php

session_start();
date_default_timezone_set('Asia/Calcutta');

$category_id = '';

$category_id = $_POST['category_id'];

include_once('class/categories.php');
$obj = new categories();

$res = $obj->deleteCategory($category_id);

echo $res;

?>

