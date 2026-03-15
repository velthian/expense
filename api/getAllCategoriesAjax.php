<?php
session_start();
header('Content-Type: application/json');

include_once(__DIR__ . '/../class/categories.php');

$loginid = $_SESSION['loginid'] ?? null;
if (!$loginid) {
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$obj = new categories();
$cats = $obj->getCategory($loginid);  // returns array of category_id

// Now get descriptions too
$catList = [];
foreach ($cats as $catId) {
    $descArr = $obj->getCategoryDescription($catId, $loginid);
    $catList[] = [
        'cat_id'   => $catId,
        'cat_desc' => $descArr['description']
    ];
}

echo json_encode($catList);
?>