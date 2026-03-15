<?php
date_default_timezone_set('Australia/Melbourne');

$continue = "no";

if (php_sapi_name() === 'cli' || defined('STDIN')) 
{
    // CLI request
    $continue = "yes";
    $loginid = '';
    $loginid = 'anuragsinha24@yahoo.com';
} 
else 
{
    // Browser or HTTP request
    session_start();
    include('header.php');
    $loginid = $_SESSION['loginid'];
    if($loginid == 'anuragsinha24@yahoo.com')
    {
        $continue = "yes";
    }
    else
    {
        $continue = "no";
    }
}



if($continue == "yes")
{
    //GET DATA FROM FOREX CARD (ANURAGSINHA24@ICLOUD.COM INBOX)
    $returnArray = [];
    $returnArray = getForexCardData();

    $some = $returnArray[0];
    $connection = $returnArray[1];
    $pregMatch = '/payment of ([A-Z]{3})\s*([\d.]+)\s+at\s+(.+?)\s+on\s+(\d{2}-\d{2}-\d{4} \d{2}:\d{2})/i';
    processSource($some, $connection, $pregMatch, 'forexCard');

    //GET DATA FOR COMBANK (AKSHATSINHA164@GMAIL.COM INBOX)
    $returnArray = [];
    $returnArray = getComBankData();

    $some = $returnArray[0];
    $connection = $returnArray[1];
    //$pregMatch = '/To:\s*(.+)\n.*?Amount:\s*\$(\d+\.\d{2})\n.*?Description:\s*(.+)\n.*?Date:\s*([0-9]{2}\s\w+\s[0-9]{4}\s[0-9]{1,2}:[0-9]{2}[ap]m)/i';
    $pregMatch = '/To:\s*(.+?)\s+PayID:.*?Amount:\s*\$(\d+\.\d{2})\s+From:.*?Description:\s*(.*?)\s+Date:\s*([0-9]{2}\s\w+\s[0-9]{4}\s[0-9]{1,2}:[0-9]{2}[ap]m)/is';
    processSource($some, $connection, $pregMatch, 'ComBank');
}

function getForexCardData()
{
    $server = "{imap.mail.me.com:993/imap/ssl}INBOX";
    $username = 'anuragsinha24';
    $password = 'xlhu-kfsq-whub-yfiu';
    $connection = imap_open($server, $username, $password) or die('Cannot connect to email: ' . imap_last_error());

    $imap_str = '';
    
    //$imap_str = 'FROM "PrepaidCards@hdfcbank.net" SINCE "1 JANUARY 2024"';
    
    $dt = date('Y-m-d',strtotime('-5 days' ,strtotime(date('Y-m-d'))));
    $m = '';
    $d = '';
    $y = '';
    
    $m = date('F',strtotime($dt));
    $d = date('d',strtotime($dt));
    $y = date('Y',strtotime($dt));
    
    $imap_str = 'FROM "PrepaidCards@hdfcbank.net" SINCE ' . '"' . $d . ' ' . strtoupper($m) . ' ' . $y . '"';
    //$imap_str = 'FROM "PrepaidCards@hdfcbank.net" SINCE "1 JANUARY 2025"';

    $some = imap_search($connection, $imap_str);

    $returnArray = [];
    $returnArray = array($some, $connection);
    return $returnArray;
}

function getComBankData()
{
    //$server = "{imap.gmail.com:993/imap/ssl}INBOX";
    $server = "{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX";
    $username = 'akshatsinha164@gmail.com';
    $password = 'olld awmi pzni wgpu';

    $connection = imap_open($server, $username, $password) or die('Cannot connect to email: ' . imap_last_error());

    $imap_str = '';
    
    //$imap_str = 'FROM "PrepaidCards@hdfcbank.net" SINCE "1 JANUARY 2024"';
    
    $dt = date('Y-m-d',strtotime('-5 days' ,strtotime(date('Y-m-d'))));
    $m = '';
    $d = '';
    $y = '';
    
    $m = date('F',strtotime($dt));
    $d = date('d',strtotime($dt));
    $y = date('Y',strtotime($dt));
    
    //$imap_str = 'FROM "NetBankNotification@cba.com.au" SINCE "1 JANUARY 2025"';
    $imap_str = 'FROM "NetBankNotification@cba.com.au" SINCE ' . '"' . $d . ' ' . strtoupper($m) . ' ' . $y . '"';

    $some = imap_search($connection, $imap_str);

    $returnArray = [];
    $returnArray = array($some, $connection);
    return $returnArray;
}

function processSource($some, $connection, $pregMatch, $source)
{
    require_once "class/forexCard.php";
    $forexCardObj = new forexCard();

    $uid_data = array();
    $uid_data = $forexCardObj->getForexUID();

    $aggregateAmount = 0;
    require_once "class/forex.php";
    $forexObj = new forex();
    $txn = array();

    foreach($some as $s)
    {    
        $msg_date = '';
        $uid = '';
        $conv = 1;

        $overview = imap_fetch_overview($connection,$s,0);
        if (!$overview || empty($overview[0])) 
        {
            echo "❌ Failed to fetch message overview for UID: $s<br>";
            continue;
        }

        $msg_date = $overview[0]->date;
        $msg_date=strtotime($msg_date);
        $msg_date = date("Y-m-d", $msg_date);

        $uid = (int)$overview[0]->uid;

        if(!in_array($uid, $uid_data))
        {
            //error_log("📍 DEBUG: Starting message processing...");
                $structure = imap_fetchstructure($connection, $s);
                if (!$structure) 
                {
                    echo "❌ Could not fetch structure for message UID: $s<br>";
                    continue;
                }

                $message = '';

                if (!empty($structure->parts)) {
                    foreach ($structure->parts as $index => $part) {
                        $partNum = $index + 1;
                        if ($part->subtype === 'PLAIN') {
                            $msgPart = imap_fetchbody($connection, $s, $partNum);
                            $msgPart = quoted_printable_decode($msgPart);
                            $message .= $msgPart;
                        }
                    }
                } else {
                    $message = imap_body($connection, $s);
                    //error_log("📍 DEBUG: Raw body length = " . strlen($message));
                    $message = quoted_printable_decode($message);
                    //error_log("📍 DEBUG: After quoted_printable_decode, length = " . strlen($message));
                }


                // Decode and sanitize safely
                $plainText = strip_tags($message);
                $plainText = preg_replace('/[^\P{C}\n\t]/u', '', $plainText); // remove control chars

                // Optional: limit size to prevent runaway regex
                $plainText = substr($plainText, 0, 2000);

                // Log preview for debug
                //error_log("📍 DEBUG: Plaintext preview: " . substr($plainText, 0, 300));


                //echo $plainText;

                // Use regex to extract amount, merchant, and date
                if (preg_match($pregMatch, $plainText, $matches))
                {
                    switch($source)
                    {
                        case('forexCard'):
                        {
                            $currency = $matches[1];      // AUD, EUR
                            if($currency == "EUR")
                            {
                                $conv = $forexObj->getForexFromAPI("EUR","AUD");
                                if (!$conv || !is_numeric($conv)) 
                                {
                                    echo "❌ Failed to get forex rate for EUR-AUD. Skipping UID: $uid<br>";
                                    break;
                                }
                            }
                            
                            $amount_local   = $matches[2];      // e.g., 19.6
                            $amount_local = $amount_local*$conv;
                            $merchant = trim($matches[3]); // e.g., BIG W/SWANSTON & LONSDALE
                            $datetime = $matches[4];      // e.g., 01-08-2025 08:46
                            list($date, $time) = explode(' ', $datetime);
                            break;
                        }

                        case('ComBank'):
                        {
                            $currency = "AUD";  // Since the email uses "$", we assume AUD
                            $conv = 1;

                            if ($currency != "AUD") 
                            {
                                $conv = $forexObj->getForexFromAPI($currency, "AUD");
                                if (!$conv || !is_numeric($conv)) 
                                {
                                    echo "❌ Failed to get forex rate for $currency-AUD. Skipping UID: $uid<br>";
                                    break;
                                }
                            }

                            $amount_local = $matches[2];  // 155.00
                            $amount_local = $amount_local * $conv;

                            $merchant = trim($matches[3]);  // Woollies
                            $datetime = $matches[4];        // 03 Aug 2025 12:52am

                            // Convert to standard date + time
                            $dt = date_create_from_format('d M Y g:ia', $datetime);
                            if (!$dt) 
                            {
                                echo "❌ Failed to parse datetime: $datetime<br>";
                                break;
                            }
                            $date = $dt->format('Y-m-d');
                            $time = $dt->format('H:i');
                            break;
                        }
                    }

                    echo "Currency: $currency<br>";
                    if($currency == 'EUR')
                    {
                        echo("Forex Rate: " . $conv . "<br>");
                    }
                    echo "Amount: $amount_local<br>";
                    echo "Merchant: $merchant<br>";
                    echo "Date: $date<br>";
                    echo "Time: $time<br>";
                    echo("<br>------------------------------------<br>");
                    $txn[] = array('uid' => $uid, 'currency' => $currency, 'conversion' => $conv, 'amount' => $amount_local, 'merchant' => $merchant, 'date' => $date, 'time' => $time);
                    $aggregateAmount += $amount_local;
                }
        }
        else 
        {
            echo("Transaction already updated<br>");
        }
    }

    if (!empty($txn)) 
    {
        updateForexDb($txn, $forexCardObj, $source); 
    } 
    imap_close($connection);
}

function updateForexDb($txn, $forexCardObj, $source)
{
    require_once "class/forex.php";
    $forexObj = new forex();
    
    if(!empty($txn))
    {
        $audToINR = 1;
        $audToINR = $forexObj->getForexFromAPI("AUD","INR");
        if (!$audToINR || !is_numeric($audToINR)) 
        {
            echo "❌ Failed to get forex rate AUD-INR. Skipping updates.<br>";
            return;
        }

        foreach($txn as $tx)
        {
            $res = FALSE;

            $uid = 0;
            $currency_code     = 'AUD';
            $amount            = 0;
            $converted_amount  = 0;
            $merchant_name     = '';
            $txn_date          = '';
            $txn_time          = '';

            $uid               = "IMP" . $tx['uid'];
            $amount            = $tx['amount'];
            $converted_amount  = $tx['amount']*$audToINR;
            $merchant_name     = $tx['merchant'];
            $txn_date          = $tx['date'];
            $txn_date          = date('Y-m-d', strtotime($txn_date));
            $txn_time          = $tx['time'];

            $res = $forexCardObj->updatedForexCard($uid, $currency_code, $amount, $converted_amount, $merchant_name, $txn_date, $txn_time, $source);
            if($res)
            {
                echo("Transaction inserted successfully <br>");
            }
            else
            {
                echo("Insert Failed for UID: $uid | $merchant_name on $txn_date $txn_time<br>");
            }
        }
    }
}
?>