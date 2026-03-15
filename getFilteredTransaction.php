<?php
session_start();

if(!isset($_SESSION['authenticated']))
{
    $_SESSION['redirect']=true;
    $_SESSION['auth_error']="Please login first";
    header('Location: index.php');
    die();
}

date_default_timezone_set('Asia/Calcutta');
$_SESSION['timestamp'] = time();

$supCatFilter = array();
$catFilter = array();
$fromDt = '';
$toDt = '';
$searchTerm = '';

$res = array();
$loginid = $_SESSION['loginid'];

$searchTerm = $_POST['searchTerm'];

$toDt = $_POST['toDt'];
$supCatFilter = filter_input(INPUT_POST, 'supCatf', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
$catFilter = filter_input(INPUT_POST, 'catf', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

$validInputs = FALSE;
$error = 'success';

foreach($supCatFilter as $supCat)
{
    if(is_numeric($supCat) || ($supCat == ''))
    {
        $validInputs = TRUE;
    }
    else
    {
        $validInputs = FALSE;
        $error = 'errSupCat';
        break;
    }
}

foreach($catFilter as $cat)
{
    if(is_numeric($cat) || ($cat == ''))
    {
        $validInputs = TRUE;
    }
    else
    {
        $validInputs = FALSE;
        $error = 'errCat';
        break;
    }
}    


if($validInputs)
{
    include_once('class/dateValidation.php');
    $dateObj = new dateValidation();
    
    //CHECK FROM DATES
    if(isset($_POST['fromDt']))
    {
        if($_POST['fromDt'] != '')
        {
            $date_to_check = '';
            $date_to_check = $_POST['fromDt'];
            if($dateObj->checkValidDate($date_to_check))
            {
                $validInputs = TRUE;
                $fromDt = $_POST['fromDt'];
            }
            else
            {
                $validInputs = FALSE;
                $error = "errFromDt";
            }
        }
        else
        {
            $fromDt = "1970-01-01";
            $validInputs = TRUE;
        }
    }
    else
    {
        $error = "errFromDt";
        $validInputs = FALSE;
    }
    
    if($validInputs)
    {
        //CHECK TO DATE
        if(isset($_POST['toDt']))
        {
            if($_POST['toDt'] != '')
            {
                $date_to_check = '';
                $date_to_check = $_POST['toDt'];
                if($dateObj->checkValidDate($date_to_check))
                {
                    if($_POST['toDt'] > $fromDt)
                    {
                        $validInputs = TRUE;
                        $toDt = $_POST['toDt'];
                    }
                    else
                    {
                        $validInputs = FALSE;
                        $error = "errToDtGtFrom";                
                    }
                }
                else
                {
                    $validInputs = FALSE;
                    $error = "errToDt";
                }
            }
            else
            {
                $toDt = date('Y-m-d');
                $validInputs = TRUE;
            }
        }
        else
        {
            $error = "errToDt";
            $validInputs = FALSE;
        }
    }
}

if($validInputs)
{
    //CHECK IF SEARCH TERM DOES NOT HAVE SPECIAL CHARACTERS
    if($searchTerm != '')
    {
        if (ctype_alnum($searchTerm))
        {
            $validInputs = TRUE;
        }
        else
        {
            $validInputs = FALSE;
            $error = 'errSearchTerm';
        }
    }
    else
    {
        $validInputs = TRUE;
    }
}

if($validInputs)
{
    include_once('class/findTransaction.php');
    $obj1 = new findTransaction();
    
    $res = $obj1->findTxn($loginid, $searchTerm, $fromDt, $toDt);
}

$final_array = array();
$final_array[0] = $error;
$final_array[1] = $res;

echo(json_encode($final_array));
?>