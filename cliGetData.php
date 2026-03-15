<?php
date_default_timezone_set('Asia/Calcutta');

$continue = "no";

if (php_sapi_name() == 'cli') 
{
    $continue = "yes";
    $loginid = '';
    $loginid = 'anuragsinha24@yahoo.com';
}
else
{
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
    $server = "{imap.mail.me.com:993/imap/ssl}Junk";
    $username = 'anuragsinha24';
    $password = 'xsgd-ktab-olct-weai';

    $connection = imap_open($server, $username, $password) or die('Cannot connect to email: ' . imap_last_error());

    include_once('class/merchant.php');
    $obj = new merchant();

    include_once('class/transactions.php');
    $obj1 = new transactions();

    $uid_data = array();
    $uid_data = $obj1->getAllUid($loginid);

    $imap_str = '';
    
    //$imap_str = 'FROM "alerts@hdfcbank.net" SINCE "1 JANUARY 2024"';
    
    $dt = date('Y-m-d',strtotime('-20 days' ,strtotime(date('Y-m-d'))));
    $m = '';
    $d = '';
    $y = '';
    
    $m = date('F',strtotime($dt));
    $d = date('d',strtotime($dt));
    $y = date('Y',strtotime($dt));
    
    $imap_str = 'FROM "alerts@hdfcbank.bank.in" SINCE ' . '"' . $d . ' ' . strtoupper($m) . ' ' . $y . '"';

    $some = imap_search($connection, $imap_str);

    if ($some === false) {
        echo "imap_last_error():\n";
        var_dump(imap_last_error());

        echo "\nimap_errors():\n";
        var_dump(imap_errors());

        echo "\nimap_alerts():\n";
        var_dump(imap_alerts());
    }

    foreach($some as $s)
    {    
        $flag = '';
        $pos = FALSE;
        $pos1 = FALSE;
        $pos2 = FALSE;
        $pos3 = FALSE;
        $amount_pos = '';
        $amount = 0;
        $substr='';
        $merchant = '';
        $substr1 = '';
        $nb_1 = FALSE;
        $nb_2 = FALSE;
        $nb_substr = '';
        $db_status = '';
        $message = '';


        $msg_date = '';
        $uid = '';

        $overview = imap_fetch_overview($connection,$s,0);
        $msg_date = $overview[0]->date;
        $msg_date=strtotime($msg_date);
        $msg_date = date("Y-m-d", $msg_date);

        $uid = $overview[0]->uid;
        
        $subject = '';
        if (!empty($overview[0]->subject)) 
        {
            $subject = imap_utf8($overview[0]->subject);
            echo "Subject: " . $subject;
        }

        $msg = (imap_fetchbody($connection,$s,"1"));
        $message = quoted_printable_decode($msg);

        if(!in_array($uid, $uid_data))
        {
            $pos = stripos($subject,"Credit Card",0);
            if(!$pos)
            {
                $pos1 = strpos($message, "UPI", 0);
                if(!$pos1)
                {
                    $pos2 = strpos($message, "HDFC Bank A/c",0);
                    if(!$pos2)
                    {
                        $pos3 = strpos($message, "NetBanking for payment of",0);
                        if(!$pos3)
                        {
                            $flag = 'genskip';
                        }
                        else
                        {
                            $flag = 'netbanking';

                            $amount_pos = strpos($message, "Rs.",0);
                            if(!$amount_pos)
                            {
                                $flag = "genskip";
                            }
                            else
                            {
                                $substr = substr($message, ($amount_pos+4));            
                                $amount = trim(strtok($substr, ' from A/c'));

                                //now get the merchant name
                                $nb_1 = strpos($message, " to ",0);
                                if(!$nb_1)
                                {
                                    $flag = "genskip";
                                }
                                else
                                {
                                    $nb_substr = substr($message, ($nb_1+4));
                                    $nb_2 = strpos($nb_substr, "Not you?",0);
                                    if(!$nb_2)
                                    {
                                        $flag = "genskip";
                                    }
                                    else
                                    {
                                        $merchant = trim(substr($nb_substr,0,$nb_2));
                                    }
                                }
                            }
                        }
                    }
                    else
                    {
                        $flag = 'banktransfer';
                        $pos_bt = '';
                        $pos_bt = strpos($message, "Amount deducted",0);
                        if(!$pos_bt)
                        {
                            $flag = 'genskip';
                        }
                        else
                        {
                            $amount_pos = $pos_bt;
                            $substr = substr($message, ($amount_pos+23));            
                            $amount = trim(strtok($substr, ' From '));

                            //merchant name is not available for bank transfer cases
                            $merchant = "Bank Transfer - Payee Unknown";
                        }
                    }
                }
                else
                {
                    $flag = 'upi';
                    $amount_pos = strpos($message, "Rs.",0);
                    $substr = substr($message, ($amount_pos+3));            
                    $amount = trim(strtok($substr, ' has been'));

                    $substr = substr($message, ($amount_pos+3));            
                    $amount = trim(strtok($substr, ' at '));

                    //now get the merchant name
                    $nb_1 = strpos($message, " to ",0);
                    if(!$nb_1)
                    {
                        $flag = "genskip";
                    }
                    else
                    {
                        $nb_substr = substr($message, ($nb_1+4));
                        $nb_2 = strpos($nb_substr, " on ",0);
                        if(!$nb_2)
                        {
                            $flag = "genskip";
                        }
                        else
                        {
                            $merchant = trim(substr($nb_substr,0,$nb_2));
                        }
                    }

                }
            }
            else
            {
                $flag = 'creditcard';
                $pos11 = '';
                $pos11 = strpos($message, "transaction reversal",0);
                if(!$pos11)
                {
                    /*
                    $substr = substr($message, ($amount_pos+3));            
                    $amount = trim(strtok($substr, ' at '));

                    //now get the merchant name
                    $len = strlen($amount);
                    $substr1 = substr($message, ($amount_pos + 3 + $len + 3));
                    $merchant = trim(substr($substr1,0,stripos($substr1," on ",0)));
                    */

                    $returnValue = parseHdfcCcEmail($message);
                    if(!$returnValue['ok'])
                    {
                        error_log("HDFC parse error for new regex: " . $returnValue['error']);

                        $returnValueOldFormat = array();
                        $returnValueOldFormat = parseHdfcCcEmailOldFormat($message);
                        
                        $amount = $returnValueOldFormat['amount'];
                        $merchant = $returnValueOldFormat['merchant'];

                        if(!is_numeric($amount))
                        {
                            $flag = 'genskip';
                        }
                    }
                    else
                    {
                        $amount = $returnValue['amount'];
                        $merchant = $returnValue['merchant'];
                    }
                }
                else
                {
                    //this means there is a reversal entry for credit card

                    $amount_pos = $pos11;
                    $substr = substr($message, ($amount_pos+47));            
                    $amount = trim(strtok($substr, ', from '));

                    //now get the merchant name
                    $len = strlen($amount);
                    $substr1 = substr($message, ($amount_pos + 47 + $len + 7));
                    $amount = -$amount;
                    $merchant = trim(substr($substr1,0,stripos($substr1," to ",0)));
                    
                }

            }

            if($flag != 'genskip' && $merchant != '')
            {
                $category_id = '0';
                
                
                $res = $obj->putMerchant($merchant,$category_id, $loginid);
                if($res)
                {
                    $db_status = "db updated";
                }
                else
                {
                    $db_status = "db not updated";
                }


                //first get the merchant_id
                $merchant_id = '';
                $merchant_id = $obj->getMerchantId($merchant, $loginid);

                if($merchant_id != 'error')
                {
                    //now update the transactions table with the transaction
                    $tran_status = FALSE;                
                    $mode = $flag;

                    $tran_status = $obj1->putTransactions($uid, $merchant_id, $amount, $msg_date, $mode, $loginid);

                    if($tran_status)
                    {
                        echo("Transaction updated " . $merchant . " --- " . $amount . "<br>");
                    }
                }

            }
            else
            {
                echo("UID >> " . $uid . " No match found for transaction<br>");
            }
        }
        else 
        {
            echo("Transaction skipped " . $uid . "<br><br><br>");
        }
    }
}

function parseHdfcCcEmail(string $raw): array
{
    // 1) Normalize to plain text and trim CSS/header noise
    $message = html_entity_decode(strip_tags($raw));
    // Keep from "Dear Customer" onward if present
    $message = preg_replace('/^.*?(Dear Customer\b)/si', '$1', $message);
    // Collapse whitespace (handles "Dear Customer,Greetings" etc.)
    $message = trim(preg_replace('/\s+/s', ' ', $message));

    // 2) Purchase pattern: Rs/INR/₹, optional last4, merchant after "towards/at", date+time

    $purchasePattern = '/(?:Rs\.?|INR|₹)\s*(?P<amount>[0-9][0-9,]*(?:\.\d{1,2})?)\s+is\s+debited\s+from\s+your\s+HDFC\s+Bank\s+Credit\s+Card(?:\s+ending\s+(?P<last4>\d{4}))?\s+(?:towards|at)\s+(?P<merchant>.+?)\s+on\s+(?P<date>\d{1,2}\s+[A-Za-z]{3,9},?\s+\d{4})\s+at\s+(?P<time>\d{1,2}:\d{2}(?::\d{2})?\s*(?:[AP]M)?)\b/i';

    /*
    $purchasePattern = '/
        (?:Rs\.?|INR|₹)\s*                                  # currency
        (?P<amount>[0-9][0-9,]*(?:\.\d{1,2})?)              # amount
        \s+is\s+debited\s+from\s+your\s+HDFC\s+Bank\s+Credit\s+Card
        (?:\s+ending\s+(?P<last4>\d{4}))?                   # optional last4
        \s+(?:towards|at)\s+(?P<merchant>.+?)\s+            # merchant
        on\s+(?P<date>\d{1,2}\s+[A-Za-z]{3,9},?\s+\d{4})\s+ # date
        at\s+(?P<time>\d{1,2}:\d{2}(?::\d{2})?\s*(?:[AP]M)?)\b # time
    /i';
    */

    if (!preg_match($purchasePattern, $message, $m)) {
        return ['ok' => false, 'error' => 'Pattern not matched', 'raw' => $message];
    }

    // 3) Normalize amount & merchant
    $amount   = (float) str_replace([',', ' '], '', $m['amount']);
    $merchant = trim(preg_replace('/\s+/', ' ', $m['merchant']), " \t\n\r\0\x0B.-");

    // 4) Parse date/time (comma/no-comma, 24h/12h)
    $dateStr = trim($m['date']);
    $timeStr = trim($m['time']);
    $dtIso   = null;
    $formats = [
        'd M, Y H:i:s','d M, Y H:i',
        'd M Y H:i:s','d M Y H:i',
        'd M, Y h:i:s A','d M, Y h:i A',
        'd M Y h:i:s A','d M Y h:i A',
    ];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, "$dateStr $timeStr");
        if ($dt instanceof DateTime) { $dtIso = $dt->format('Y-m-d H:i:s'); break; }
    }

    return [
        'ok'        => true,
        'type'      => 'purchase',
        'currency'  => 'INR',
        'amount'    => $amount,                 // 1517.70
        'merchant'  => $merchant,               // PASSPORTSEVAMOPSACC
        'last4'     => $m['last4'] ?? null,     // 2991 (or null)
        'raw_date'  => $dateStr,                // 11 Aug, 2025
        'raw_time'  => $timeStr,                // 22:55:14
        'date_iso'  => $dtIso,                  // 2025-08-11 22:55:14
        'raw'       => $message,
    ];
}

function parseHdfcCcEmailOldFormat(string $raw): array
{
    // Normalize whitespace
    $text = trim(preg_replace('/\s+/u', ' ', $raw));

    $out = [
        'currency'        => 'INR',
        'amount'          => null,
        'merchant'        => null,
        'datetime_raw'    => null,  // "14-08-2025 00:05:58"
        'datetime_iso'    => null,  // "2025-08-14T00:05:58+05:30"
        'card_last4'      => null,
        'auth_code'       => null,
        'without_otp_pin' => null,
        'source'          => 'hdfc_email',
    ];

    // Amount: Rs./Re + number (commas optional)
    if (preg_match('/\b(?:Rs\.?|Re\.?)\s*([0-9][0-9,]*(?:\.\d{1,2})?)/i', $text, $m)) {
        $out['amount'] = str_replace(',', '', $m[1]);
    }

    // Merchant + datetime: "... at <MERCHANT> on dd-mm-yyyy hh:mm:ss"
    // Also works for "... towards <MERCHANT> on ..."
    if (preg_match('/\b(?:at|towards)\s+(.+?)\s+on\s+(\d{2}-\d{2}-\d{4}\s+\d{2}:\d{2}:\d{2})/i', $text, $m)) {
        $out['merchant']     = trim($m[1]);
        $out['datetime_raw'] = $m[2];

        // Convert to ISO 8601 (assume India time)
        $dt = DateTime::createFromFormat('d-m-Y H:i:s', $m[2], new DateTimeZone('Asia/Kolkata'));
        if ($dt !== false) {
            $out['datetime_iso'] = $dt->format(DateTime::ATOM); // e.g. 2025-08-14T00:05:58+05:30
        }
    }

    // Card last 4: appears as "Card XX2991" (or similar)
    if (preg_match('/\bCard\s+[X*]+?(\d{4})\b/i', $text, $m)) {
        $out['card_last4'] = $m[1];
    } elseif (preg_match('/\bending\s+(\d{4})\b/i', $text, $m)) {
        $out['card_last4'] = $m[1];
    }

    // Authorization code
    if (preg_match('/Authorization\s*code[:\-]?\s*([A-Za-z0-9]+)/i', $text, $m)) {
        $out['auth_code'] = $m[1];
    }

    // Without OTP / PIN note
    if (preg_match('/without\s+OTP\s*\/?\s*PIN/i', $text)) {
        $out['without_otp_pin'] = true;
    } elseif (preg_match('/with\s+OTP\s*\/?\s*PIN/i', $text)) {
        $out['without_otp_pin'] = false; // in case they explicitly say "with"
    }

    return $out;
}


?>