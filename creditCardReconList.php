<?php

include('header.php');

include('db.php');

$loginid = $_SESSION['loginid'];
$csvFile = 'datafiles/stmtToProcess.csv'; // replace with your actual file path

require_once "class/csvStmt.php";
$csvObj = new csvStmt();

//ingest the csv filed into the statement databases - only once

$statement_id = $csvObj->storeStatementInDb($conn, $loginid, $csvFile);           //Now all entries from CSV should be populated in the CSV Database

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
            $url = 'creditCardTxn.php?' . http_build_query($params);
            ?>
            <div><a href="<?php echo $url ?>"><?php echo($st['card_number'] . " | " . $st['statement_date']); ?></a><span class="reconListHorizSeparator"><?php echo($st['failedRecords'] . " Records failed to update"); ?></span></div>
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