<?php
session_start();

$searchTerm = isset($_POST['param']) ? trim($_POST['param']) : '';
$category_id = isset($_POST['category_id']) ? (int) trim($_POST['category_id']) : null;


$data = array();

$loginid = $_SESSION['loginid'];

include_once('class/merchant.php');
include_once('class/categories.php');

$obj = new merchant();
$obj1 = new Categories();

$data = json_decode(($obj->getAllMerchant($searchTerm, $category_id)), true);
$final_data = array();

if(!empty($data))
{
    $n = 0;
    $n = count($data);
    if($n > 60)
    {
        $final_data[0] = 'too_many';
    }
    else
    {
        foreach($data as $dat)
        {
            $category_id = '';
            $category_desc_arr = array();
            
            $category_id = $dat['category_id'];
            $category_desc_arr = $obj1->getCategoryDescription($category_id, $loginid);
            
            $final_data[] = array('merchant_id' => $dat['merchant_id'], 'merchant_desc' =>  $dat['merchant_name'], 'category_id' => $dat['category_id'], 'category_desc' => $category_desc_arr['description']);
            
        }
    }
}

$resp = '';
$resp = json_encode($final_data);
echo $resp;
?>