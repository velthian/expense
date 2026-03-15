<?php

session_start();
date_default_timezone_set('Asia/Calcutta');

$action = '';
$supCatId = '';
$supCatDesc = '';
$mTrack = '';

$action = $_POST['action'];
$supCatId = $_POST['supCatId'];
$supCatDesc = $_POST['supCatDesc'];
$mTrack = $_POST['mTrack'];
$loginid = $_SESSION['loginid'];

include_once('class/categories.php');
$obj = new Categories();

$res = $obj->manageSupCat($action,$supCatId,$supCatDesc,$mTrack, $loginid);

echo $res;

?>

