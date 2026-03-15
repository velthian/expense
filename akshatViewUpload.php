<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') 
{
    http_response_code(405);
    echo "Invalid request";
    exit;
}

if (!isset($_POST['formType']) || $_POST['formType'] !== 'csvUpload') 
{
    http_response_code(400);
    echo "Invalid form submission";
    exit;
}

$status = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
    $whatGotSubmitted = '';
    $whatGotSubmitted = $_POST['formType'];
    
    switch($whatGotSubmitted)
    {
        case("csvUpload"):
        {
            $goodToUpload = FALSE;
            $target_dir = "datafiles/";
            $source_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
            $fileType = strtolower(pathinfo($source_file,PATHINFO_EXTENSION));
            //CHECK IF FILE IS A VALID EXCEL FILE
            if($fileType == "csv")
            {
                $goodToUpload = true;
            } 
            else 
            {
                $error = "Only CSV files are allowed";
            }

            if($goodToUpload)
            {
                $target_file = $target_dir . 'akshatView.' . $fileType;
                
                //check if file already exists
                if (file_exists($target_file)) 
                {
                    unlink($target_file);
                }

                if (!isset($_FILES['fileToUpload']) || $_FILES['fileToUpload']['error'] !== UPLOAD_ERR_OK) 
                {
                    http_response_code(400);
                    echo "File upload failed";
                    exit;
                }

                if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], ($target_file))) 
                {
                    $status = "proceedToProcess";
                    $error = "uploadSuccess";
                } 
                else 
                {
                    $error = "There was an error in uploading the file";
                }
            }
            break;
        }
    }   
}
       
if($status == "proceedToProcess")
{
    // --- FILE ---
    $file = __DIR__ . "/datafiles/akshatView.csv";

    if (!file_exists($file)) 
    {
        die("CSV file not found\n");
    }

    // --- CSV READ ---
    $handle = fopen($file, "r");
    $header = fgetcsv($handle); // skip header

    require_once "class/manualUpdateForexCard.php";
    $manualUpdateObj = new manualUpdateForexCard();

    while (($row = fgetcsv($handle)) !== false) 
    {
        [$dateStr, $details, $amount, $balance] = $row;

        $dateStr = trim($dateStr);

        // Normalize 2-digit year to 4-digit
        if (preg_match('/\b(\d{2})$/', $dateStr, $m)) {
            $yy = (int)$m[1];
            $yyyy = ($yy >= 70) ? (1900 + $yy) : (2000 + $yy);
            $dateStr = preg_replace('/\b\d{2}$/', $yyyy, $dateStr);
        }

        $formats = ['d M Y', 'd-M-Y'];

        $dateObj = false;
        foreach ($formats as $fmt) {
            $dateObj = DateTime::createFromFormat($fmt, $dateStr);
            if ($dateObj !== false) {
                break;
            }
        }

        if ($dateObj === false) {
            echo "Skipping row due to invalid date: {$dateStr}<br>";
            continue;
        }

        $txnDate = $dateObj->format('Y-m-d');
        $txnTime = "00:00:00";
        $txnDateTime = "{$txnDate} {$txnTime}";


        // --- Merchant extraction
        $merchant = extractMerchant($details);
        $rawAmount = parseAmountRaw($amount);

        // Invert sign to match internal convention
        $amount = -1 * $rawAmount;

        $res = $manualUpdateObj->manualUpdateForexTxn('modal', '', $txnDate, $amount, $merchant, 'ComBank');

        if($res)
        {
            echo("Updated Successfully | " . $txnDate . " | " . $amount . " | " . $merchant . "<br>");
        }
        else
        {
            echo("Update Unsuccessful | " . $txnDate . " | " . $amount . " | " . $merchant . "<br>");
        }
    }

    fclose($handle);
    echo "__INGEST_DONE__";
    exit;

}


// --------------------
// Helper function
// --------------------
function extractMerchant(string $details): string
{
    // Remove card / value date noise
    $details = preg_replace('/Card.*$/i', '', $details);
    $details = preg_replace('/Value Date:.*$/i', '', $details);

    // Trim country / network markers
    $details = preg_replace('/\bVI\b|\bAUS\b/i', '', $details);

    return trim($details);
}

function parseAmountRaw($raw)
{
    $raw = trim($raw);

    // Unicode minus → ASCII minus
    $raw = str_replace("\u{2212}", '-', $raw);

    // Remove currency symbols, commas, spaces
    $raw = preg_replace('/[^\d\.\-]/', '', $raw);

    return (float)$raw;
}

?>