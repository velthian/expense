<?php
session_start();
date_default_timezone_set('Asia/Calcutta');

$state = '';
$curr_display_date = '';
$display_date = '';

$state = $_POST['state'];
$curr_display_date = $_POST['curr_display_date'];

switch($state)
{
    case('at_present'):
    {
        $display_date = date('Y-m-d');
        break;   
    }
    
    case('minus_one'):
    {
        $date = new DateTime($curr_display_date);
        $date->modify('-1 month');
        $display_date = $date->format('Y-m-d');
        break;
    }
    
    case('plus_one'):
    {
        $date = new DateTime($curr_display_date);
        $date->modify('+1 month');
        $display_date = $date->format('Y-m-d');
        break;
    }
}

echo $display_date;

?>

