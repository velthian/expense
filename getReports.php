<?php

session_start();
date_default_timezone_set('Asia/Calcutta');

$from_date = '';
$type = '';
$fy_date = '';

$from_date = $_POST['from_date'];
$type = $_POST['type'];
$fy_date = $_POST['fy_date'];
$fy_end_date = $_POST['fy_end_date'];

$loginid = $_SESSION['loginid'];

include_once('class/reports.php');
$obj = new reports();

if($type == 'monthly')
{
    $res = $obj->varianceReport($from_date, $loginid);
}

if($type == 'annual')
{
    $current_date = '';
    $current_date = date('Y-m-t', strtotime($from_date));
    $res = $obj->varianceReportAnnual($current_date, $loginid, $fy_date, $fy_end_date);
}

echo json_encode($res);

?>
