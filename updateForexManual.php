<?php
session_start();
$err_code = 0;
$validInputs = FALSE;

if($_SESSION['akshatAuthenticated'])
{
    $date = $_POST['date'];
    $from = $_POST['from'];
    $uid = $_POST['uid'];

    if($date == '' || !isset($date))
    {
        $err_code = 1; //Date Error
    }
    else
    {
        $date = trim($date); 
        if($from == 'inline')
        {
            $dt = DateTime::createFromFormat('d M y', $date); // ! = reset time to 00:00:00
            $date = $dt->format('Y-m-d');
        }
        $validInputs = TRUE;
    }

    if($validInputs)
    {
        $amount = trim($_POST['amount']);
        if(!is_numeric($amount) || !isset($amount))
        {
            $err_code = 2; // Amount Error
            $validInputs = FALSE;
        }
    }

    if($validInputs)
    {
        $text = trim($_POST['text']);
        if($text == null || !isset($text))
        {
            $err_code = 3; // Text Error
            $validInputs = FALSE;
        } 
    }

    if($validInputs)
    {
        $dest = trim($_POST['dest']);
        if($dest != 'ForexCard' && $dest != 'ComBank' && !isset($dest))
        {
            $err_code = 4; // Destination Error
            $validInputs = FALSE;
        } 
    }

    if($validInputs)
    {
        require_once "class/manualUpdateForexCard.php";
        $forexCardManualObj = new manualUpdateForexCard();
        $res = $forexCardManualObj->manualUpdateForexTxn($from, $uid, $date, $amount, $text, $dest);
        if(!$res)
        {
            $err_code = 5; //Update failed
        }
    }

}
else
{
    $err_code = 6; //Access Denied
}

echo($err_code);
?>