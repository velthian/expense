<?php
if (file_exists('../investo/vendor/autoload.php')) {
    require '../investo/vendor/autoload.php';
} else {
    require 'vendor/autoload.php';
}

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

include('header.php');
include('db.php');

$loginid = $_SESSION['loginid'];

$inputFilePath = 'datafiles/bankStmtToProcess.xls'; // <- change as needed
if (!file_exists($inputFilePath)) 
{
    die("File not found: $inputFilePath");
}

// Load the spreadsheet
$reader = IOFactory::createReaderForFile($inputFilePath);
$spreadsheet = $reader->load($inputFilePath);
$sheet = $spreadsheet->getActiveSheet();   // ✅ This is the Worksheet object

require_once "class/bankStmt.php";
$csvObj = new bankStmt();

//ingest the csv filed into the statement databases - only once

$statement_id = $csvObj->storeStatementInDb($conn, $loginid, $sheet, $inputFilePath);           //Now all entries from CSV should be populated in the CSV Database

if($statement_id !== null)
{
    ?>
        <div id="creditCardReconListCont">
        List of Uploaded Statements
        </div>
    <?php
    $stmtList = $csvObj->getStatementList($conn, $loginid);
    if(!empty($stmtList) && $stmtList['status'])
    {
        foreach($stmtList['arrayList'] as $st)
        {
            $params = ['statement_id' => $st['statement_id']];
            $url = 'bankAccountTxn.php?' . http_build_query($params);
            ?>
            <div><a href="<?php echo $url ?>"><?php echo($st['statement_date']); ?></a><span class="reconListHorizSeparator"><?php echo($st['failedRecords'] . " Records failed to update"); ?></span></div>
            <?php
        }

    }
    else
    {
        echo("No Statements Found");
    }
    ?>
    
    <?php
    $conn->close();
}

?>