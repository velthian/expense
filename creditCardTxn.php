<?php
include('header.php');
$statement_id = isset($_GET['statement_id']) && trim($_GET['statement_id']) !== ''
    ? trim($_GET['statement_id'])
    : null;

if($statement_id !== null && $statement_id !== '')
{
    $loginid = $_SESSION['loginid'];
    include('db.php');

    //Now call the recon logic and create the display arrays
    require_once 'class/reconcileCsvDb.php';

    $reconArray = array();
    $reconObj = new reconcileCsvDb();
    $reconArray = $reconObj->reconcileAndDisplayCsvDbValues($conn, $statement_id, $loginid, "cc");

    if(!empty($reconArray))
    {
        $csvReconciledArray = $reconArray['csvReconciledArray'];
        $csvUnReconciledArray = $reconArray['csvUnReconciledArray'];
        $dbReconciledArray = $reconArray['dbReconciledArray'];
        $dbUnReconciledArray = $reconArray['dbUnReconciledArray'];
        $beginDate = date('d M y', strtotime($reconArray['beginDate']));
        $lastDate = date('d M y', strtotime($reconArray['lastDate']));
        $totalAmountDue = $reconArray['total_amount_due'];
        $sumOfLines = $reconArray['sum_of_lines'];
    }
    ?>
    <div id="ccReconTitle">CREDIT CARD RECONCILIATION</div>
    <div id="statementDates"><?php echo($beginDate . " - " . $lastDate);?></div>
    <?php if($totalAmountDue !== null && $sumOfLines !== null):
        $match = (abs($totalAmountDue - $sumOfLines) < 1.0);
    ?>
    <div id="ccAmountCheck" class="<?php echo $match ? 'ccAmountMatch' : 'ccAmountMismatch'; ?>">
        <span>Statement Total Due: <?php echo number_format($totalAmountDue, 2); ?></span>
        <span>Sum of Imported Transactions: <?php echo number_format($sumOfLines, 2); ?></span>
        <span><?php echo $match ? '✔ Amounts match' : '⚠ Mismatch — difference: ' . number_format(abs($totalAmountDue - $sumOfLines), 2); ?></span>
    </div>
    <?php endif; ?>
    <div class="creditCardReconContainer">
        <div class="creditCardReconColumnList">
            <div class="creditCardReconSeparator">
                <span id="csvShowNotReconList" class="reconExpand">-</span>Statement Rows Not Reconciled
                <?php
                    echo("[" .count($csvUnReconciledArray). "]");
                ?>
            </div>
            <div id="bankStmtNotReconList">
            <?php
                foreach($csvUnReconciledArray as $csvRowNotReconciled)
                {
                    ?>
                    <div class="bankRecord cardReconRow">
                        <input type='hidden' class="csvLineId" value="<?php echo $csvRowNotReconciled['line_id'];?>" />
                        <div class="recordItem creditCardReconDate"><?php echo $csvRowNotReconciled['date']; ?></div>
                        <div class="recordItem creditCardReconAmount"><?php echo $csvRowNotReconciled['amount']; ?></div>
                        <div class="recordItem creditCardReconMerchant"><?php echo $csvRowNotReconciled['merchant_name']; ?></div>
                        <div class="recordItem csvAddBtn"><div class="csvAdd">Add</div><div class="csvReconciled">Reconcile</div><div class="csvUpdateCont"><input class="csvUIDMap" placeholder="UID" /><button class="csvUpdateBtn" width="15px">SAVE</button></div></div>
                    </div>
                    <?php
                }
            ?>
            </div>
        </div>

        <div class="creditCardReconColumnList">
            <div class="creditCardReconSeparator">
                <span id="dbShowNotReconList" class="reconExpand">-</span>Database Rows Not Reconciled
                <?php
                    echo("[" .count($dbUnReconciledArray) . "]");
                ?>
            </div>
            <div id="dbNotReconList">
            <?php
                foreach($dbUnReconciledArray as $dbArrayNotReconciled)
                {
                    if($dbArrayNotReconciled['reconciled'] !== 1)
                    {
                    ?>
                    <div class="dbRecord cardReconRow">
                        <input class="dbUid" value="<?php echo $dbArrayNotReconciled['uid']; ?>" type="hidden" />
                        <div class="recordItem creditCardReconDate creditCardUid"><?php echo $dbArrayNotReconciled['uid']; ?></div>
                        <div class="recordItem creditCardReconDate"><?php echo $dbArrayNotReconciled['date']; ?></div>
                        <div class="recordItem creditCardReconAmount"><?php echo $dbArrayNotReconciled['amount']; ?></div>
                        <div class="recordItem creditCardReconMerchant"><?php echo $dbArrayNotReconciled['merchant']; ?></div>
                        <div class="csvAddBtn dbActions">
                            <div class="dbDelete">Delete</div>
                        </div>
                    </div>
                    <?php
                    }
                }
            ?>
            </div>
        </div>
    </div>

    <div class="creditCardReconContainer">
        <div class="creditCardReconColumnList">
            <div class="creditCardReconSeparator"><span id="showReconList" class="reconExpand">-</span><?php 
                echo "Bank Statement Rows Reconciled. (" . 
                    count($csvReconciledArray) . 
                    ")";
                ?>
            </div>
        </div>
    </div>
    <div id="reconList">
        <?php
        for($k=0; $k < count($csvReconciledArray); $k++)
        {
        ?>
            <div class="recordBlock">
                <div class="cardBankRecon0101">FROM BANK STATEMENT</div>
                <div class="bankRecord cardReconRow">
                    <div class="recordItem"><?php echo $csvReconciledArray[$k]['date']; ?></div>
                    <div class="recordItem"><?php echo $csvReconciledArray[$k]['amount']; ?></div>
                    <div class="recordItem"><?php echo $csvReconciledArray[$k]['merchant_name']; ?></div>
                </div>
                <div class="cardBankRecon0101">FROM DATABASE</div>
                <div class="dbRecord cardReconRow">
                    <div class="recordItem"><?php echo $dbReconciledArray[$k]['date']; ?></div>
                    <div class="recordItem"><?php echo $dbReconciledArray[$k]['amount']; ?></div>
                    <div class="recordItem"><?php echo $dbReconciledArray[$k]['merchant']; ?></div>
                </div>
            </div>
        <?php
        }
        ?>
    </div>





    <?php
    //MODAL FORM FOR ADDS AND EDITS
    //Get list of super categories and categories for updating transactions that are missing in database
    $listOfSuperCategories = array();
    require_once "class/categories.php";
    $categoriesObj = new Categories();
    $listOfSuperCategories = $categoriesObj->getAllSuperCategories($loginid);

    $listOfCategoryIds = array();
    $listOfCategoryIds = $categoriesObj->getCategory($loginid);

    //Now Both Way Reconciliation Is Complete
    //Display the reconciled pairs on screen
    ?>

    <div id="creditCardReconCateg" class="modal">
        <input id="recordItemId" type="hidden" />
        <div id="closeReconModal" class="creditCardRecon-close-btn">✖</div>
        <div id="creditCardReconHdr" class="creditCardReconCategRows">CHOOSE SUPER CATEGORY AND CATEGORY</div>
        <div class="select-wrapper creditCardReconCategRows">
            <select id="creditCardSupCategories">
                <?php
                if (!empty($listOfSuperCategories)) {
                    foreach ($listOfSuperCategories as $lsc) {
                        ?>
                        <option value="<?php echo $lsc['super_category_id']; ?>">
                            <?php echo $lsc['super_category_desc']; ?>
                        </option>
                        <?php
                    }
                } else {
                    ?>
                    <option>UNABLE TO FETCH LIST</option>
                    <?php
                }
                ?>
            </select>
        </div>
        <div class="select-wrapper creditCardReconCategRows">
            <select id="creditCardCategories">
                <?php
                if (!empty($listOfCategoryIds)) {
                    foreach ($listOfCategoryIds as $lcId) {
                        ?>
                        <option value="<?php echo $lcId; ?>">
                            <?php echo $categoriesObj->getCategoryDescription($lcId, $loginid)['description']; ?>
                        </option>
                        <?php
                    }
                } else {
                    ?>
                    <option>UNABLE TO FETCH LIST</option>
                    <?php
                }
                ?>
            </select>
        </div>
        <div id="creditCardAmount" class="creditCardReconCategRows"></div>
        <div id="creditCardDate" class="creditCardReconCategRows"></div>
        <div id="creditCardMerchant" class="creditCardReconCategRows"></div>
        <button id="creditCardAdd" class="creditCardReconCategRows">SAVE</button>
    </div>

    <?php
}
else
{
    echo "Invalid Request - Either the statement has not been uploaded yet or some other error occurred!";
}
?>
<script>
var rowToRemove = null;

$('#csvShowNotReconList').click(function(){
    let $btn = $(this);
    let $list = $('#bankStmtNotReconList');

    if ($list.is(':visible')) {
        $list.slideUp();
        $btn.text('+');   // change back to +
    } else {
        $list.slideDown();
        $btn.text('-');   // change to -
    }
});

$('#dbShowNotReconList').click(function(){
    let $btn = $(this);
    let $list = $('#dbNotReconList');

    if ($list.is(':visible')) {
        $list.slideUp();
        $btn.text('+');   // change back to +
    } else {
        $list.slideDown();
        $btn.text('-');   // change to -
    }
});

$('#showReconList').click(function(){
    let $btn = $(this);
    let $list = $('#reconList');

    if ($list.is(':visible')) {
        $list.slideUp();
        $btn.text('+');   // change back to +
    } else {
        $list.slideDown();
        $btn.text('-');   // change to -
    }
});

$('body').on('click','.csvAdd',function(){
    var merchant = $(this).parent().siblings('.creditCardReconMerchant').text();
    var amount = $(this).parent().siblings('.creditCardReconAmount').text();
    var date = $(this).parent().siblings('.creditCardReconDate').text();
    rowToRemove = $(this).parent().parent('.cardReconRow');

    $('.overlay').show();
    $('#creditCardReconCateg').css('display','flex');
    $('#creditCardReconCateg').fadeIn();
    $('#creditCardAmount').html("<span class='ccr01'>Amount:</span><span class='ccr02'>" + amount + "</span>");
    $('#creditCardMerchant').html("<span class='ccr01'>Merchant:</span><span class='ccr02'>"+merchant + "</span>");
    $('#creditCardDate').html("<span class='ccr01'>Date:</span><span class='ccr02'>" + date + "</span>");
    
});

$('#closeReconModal').click(function(){
    $('#creditCardReconCateg').fadeOut();
    $('.overlay').hide();
});

$('#creditCardAdd').click(function(){
    var desc = $(this).siblings('#creditCardMerchant').children('.ccr02').text();
    var amount = $(this).siblings('#creditCardAmount').children('.ccr02').text();
    var date = $(this).siblings('#creditCardDate').children('.ccr02').text();
    var sup_categ_id = $(this).siblings('.creditCardReconCategRows').find('#creditCardSupCategories').val();
    var category_id = $(this).siblings('.creditCardReconCategRows').find('#creditCardCategories').val();
    var mode = 'creditcard';

    $.ajax({type: "POST",
        url: "api/addRecordManuallyPostRecon.php",
        data: {sup_categ_id: sup_categ_id, category_id: category_id, desc: desc, amount: amount, date: date, mode: mode},
        success:function(result) 
        { 
            if(result === '1')
            {
                alert("Something went wrong");
            }
            else
            {
                $('#creditCardReconCateg').fadeOut();
                $('.overlay').hide();
                location.reload();
            }
        }
        });
});

$('body').on('click','.csvReconciled', function(){
    $(this).siblings('.csvUpdateCont').show();
    $(this).siblings('.csvUpdateCont').children('.csvUIDMap').focus();
});

$('body').on('click','.csvUpdateBtn', function(){

    var uidValue = $(this).siblings('.csvUIDMap').val();
    var lineId = $(this).parent().parent().siblings('.csvLineId').val();

    $.ajax({
        url: 'api/reconcile_line.php',
        method: 'POST',
        dataType: 'json',
        data: {line_id: lineId, uid: uidValue, calledFrom: "cc"} // uid is mandatory per your latest requirement
        })
        .done(function (res) 
        {
            // Controller always returns a JSON object with at least { ok: boolean }
            if (!res || res.success !== true) 
            {
                alert('Unable to reconcile!');
                return;
            }
            else
            {
                // Success: it actually changed
                alert("Successfully updated both csv and db");
                location.reload();
            }
        })
        .fail(function (xhr) {
        // HTTP/network error or non-JSON output
        try {
            const r = JSON.parse(xhr.responseText || '{}');
            alert(r.error || `Request failed (${xhr.status})`);
        } catch {
            alert(`Request failed (${xhr.status})`);
        }
    });

});

$(document).on('click', '#editTxnClose',function(){
    $('.overlay').hide();
    $(this).parent().parent().hide();
});

$(document).on('click', '.dbDelete', function(){
    var uidValue = $(this).parent().siblings('.creditCardUid').text();

    $.ajax({
        url: 'api/deleteDbTxn.php',
        method: 'POST',
        dataType: 'json',
        data: {uid: uidValue} // uid is mandatory per your latest requirement
        })
        .done(function (res) 
        {
            // Controller always returns a JSON object with at least { ok: boolean }
            if (res !== true) 
            {
                alert('Unable to delete!');
                return;
            }
            else
            {
                // Success: it actually changed
                location.reload();
            }
        })
        .fail(function (xhr) {
        // HTTP/network error or non-JSON output
        try {
            const r = JSON.parse(xhr.responseText || '{}');
            alert(r.error || `Request failed (${xhr.status})`);
        } catch {
            alert(`Request failed (${xhr.status})`);
        }
    });
});
</script>

<?php
include('footer.php');
?>