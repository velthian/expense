<?php
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Calcutta');

if(!isset($_SESSION['authenticated']))
{
    $_SESSION['redirect']=true;
    $_SESSION['auth_error']="Please login first";
    header('Location: index.php');
    die();
}

include_once(__DIR__ . '/../class/transactions.php');
include(__DIR__ . '/../db.php');

$loginid = $_SESSION['loginid'] ?? null;

if (!$loginid) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$obj = new transactions();

$result = $obj->getYtdCategoryAverages($conn, $loginid);

echo json_encode($result);
?>