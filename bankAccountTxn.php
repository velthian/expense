<?php
include('header.php');
$statement_id = isset($_GET['statement_id']) && trim($_GET['statement_id']) !== ''
    ? trim($_GET['statement_id'])
    : null;

if($statement_id !== null)
{
    $loginid = $_SESSION['loginid'];
    include('db.php');

    require_once 'class/reconcileCsvDb.php';

    $reconArray = array();
    $reconObj = new reconcileCsvDb();

    try
    {
        $reconArray = $reconObj->reconcileAndDisplayCsvDbValues($conn, $statement_id, $loginid, "bank");
    }
    catch (Throwable $e)
    {
        error_log("Reconciliation failed: " . $e->getMessage());
        $reconArray = [
            'status'  => false,
            'message' => 'Reconciliation failed. Please check logs.',
            'error'   => $e->getMessage(),
        ];
    }

    $openingBalance      = 0;
    $closingBalance      = 0;
    $netOutGo            = 0;
    $netOutGoByStatement = 0;
    $reconciledFlag      = false;

    if(!empty($reconArray))
    {
        $csvReconciledArray   = $reconArray['csvReconciledArray'];
        $csvUnReconciledArray = $reconArray['csvUnReconciledArray'];
        $dbReconciledArray    = $reconArray['dbReconciledArray'];
        $dbUnReconciledArray  = $reconArray['dbUnReconciledArray'];
        $beginDate = date('d M y', strtotime($reconArray['beginDate']));
        $lastDate  = date('d M y', strtotime($reconArray['lastDate']));

        $openingBalance = $reconArray['opening_balance'];
        $closingBalance = $reconArray['closing_balance'];
        $netOutGo       = $openingBalance - $closingBalance;

        $reconciledAmount = 0;
        foreach($csvReconciledArray as $rec) { $reconciledAmount += $rec['amount']; }

        $unReconciledAmount = 0;
        foreach($csvUnReconciledArray as $unrec) { $unReconciledAmount += $unrec['amount']; }

        $netOutGoByStatement = $reconciledAmount + $unReconciledAmount;

        if($netOutGoByStatement === $netOutGo) { $reconciledFlag = true; }

        require_once("class/convertToIndian.php");
        $currencyFormatObj = new convertToIndian();
    }
    ?>

    <link rel="stylesheet" href="css/wa-bankAccountTxn.css">

    <!-- ── Page header ── -->
    <div id="bat-header">
        <h1>Bank Account Reconciliation</h1>
        <div class="bat-dates"><?php echo $beginDate . ' – ' . $lastDate; ?></div>
    </div>

    <!-- ── Summary card ── -->
    <div id="bat-summary">
        <div class="bat-stat-grid">
            <div class="bat-stat">
                <span class="bat-stat-label">Opening Balance</span>
                <span class="bat-stat-value"><?php echo $currencyFormatObj->convertToIndianCurrency($openingBalance); ?></span>
            </div>
            <div class="bat-stat">
                <span class="bat-stat-label">Closing Balance</span>
                <span class="bat-stat-value"><?php echo $currencyFormatObj->convertToIndianCurrency($closingBalance); ?></span>
            </div>
            <div class="bat-stat">
                <span class="bat-stat-label">Net Expenditure</span>
                <span class="bat-stat-value"><?php echo $currencyFormatObj->convertToIndianCurrency($netOutGo); ?></span>
            </div>
            <div class="bat-stat">
                <span class="bat-stat-label">Sum by Transaction</span>
                <span class="bat-stat-value"><?php echo $currencyFormatObj->convertToIndianCurrency($netOutGoByStatement); ?></span>
            </div>
        </div>
        <div class="bat-recon-badge <?php echo $reconciledFlag ? 'ok' : 'fail'; ?>">
            <?php echo $reconciledFlag ? '✔ Statement Reconciled' : '✖ Statement Not Reconciled'; ?>
        </div>
    </div>

    <!-- ── Needs Action ── -->
    <div class="bat-section">
        <div class="bat-section-header" id="bat-unrecon-toggle">
            <span class="bat-toggle-icon" id="bat-unrecon-icon">▼</span>
            <span class="bat-section-title">Needs Attention</span>
            <?php
                $totalUnrecon = count($csvUnReconciledArray) + count($dbUnReconciledArray);
            ?>
            <span class="bat-count <?php echo $totalUnrecon > 0 ? 'warn' : 'ok'; ?>">
                <?php echo $totalUnrecon; ?> unmatched
            </span>
        </div>

        <div id="bat-unrecon-body" class="bat-unrecon-grid">
            <!-- Left: Statement rows not reconciled -->
            <div class="bat-col">
                <div class="bat-col-label">
                    Statement Rows
                    <span class="bat-count warn" style="margin-left:6px;"><?php echo count($csvUnReconciledArray); ?></span>
                </div>
                <?php foreach($csvUnReconciledArray as $row): ?>
                <div class="bat-row-card csv-card">
                    <input type="hidden" class="csvLineId" value="<?php echo $row['line_id']; ?>" />
                    <div class="bat-row-main">
                        <span class="bat-row-date"><?php echo $row['date']; ?></span>
                        <span class="bat-row-merchant"><?php echo htmlspecialchars($row['merchant_name']); ?></span>
                        <span class="bat-row-amount"><?php echo $row['amount']; ?></span>
                    </div>
                    <div class="bat-actions">
                        <button class="bat-btn bat-btn-add csvAdd">+ Add to DB</button>
                        <button class="bat-btn bat-btn-recon csvReconciled">⇄ Reconcile</button>
                    </div>
                    <div class="bat-recon-input-row">
                        <input class="csvUIDMap" placeholder="Enter UID…" />
                        <button class="bat-btn bat-btn-save csvUpdateBtn">Save</button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($csvUnReconciledArray)): ?>
                    <div style="font-size:12px;color:#34c759;padding:10px 4px;">All statement rows matched ✔</div>
                <?php endif; ?>
            </div>

            <!-- Right: DB rows not reconciled -->
            <div class="bat-col">
                <div class="bat-col-label">
                    Database Rows
                    <span class="bat-count warn" style="margin-left:6px;"><?php echo count($dbUnReconciledArray); ?></span>
                </div>
                <?php foreach($dbUnReconciledArray as $row):
                    if($row['reconciled'] === 1) continue; ?>
                <div class="bat-row-card db-card">
                    <input class="dbUid" value="<?php echo $row['uid']; ?>" type="hidden" />
                    <div class="bat-row-main">
                        <span class="bat-row-uid"><?php echo $row['uid']; ?></span>
                        <span class="bat-row-date"><?php echo $row['date']; ?></span>
                        <span class="bat-row-merchant"><?php echo htmlspecialchars($row['merchant']); ?></span>
                        <span class="bat-row-amount"><?php echo $row['amount']; ?></span>
                    </div>
                    <div class="bat-actions">
                        <button class="bat-btn bat-btn-del dbDelete">✕ Delete</button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($dbUnReconciledArray)): ?>
                    <div style="font-size:12px;color:#34c759;padding:10px 4px;">All DB rows matched ✔</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Matched Pairs ── -->
    <div class="bat-section" style="margin-bottom:30px;">
        <div class="bat-section-header" id="bat-recon-toggle">
            <span class="bat-toggle-icon collapsed" id="bat-recon-icon">▼</span>
            <span class="bat-section-title">Matched Pairs</span>
            <span class="bat-count ok"><?php echo count($csvReconciledArray); ?> matched</span>
        </div>

        <div id="bat-recon-body" class="bat-matched-list" style="display:none;">
            <?php for($k = 0; $k < count($csvReconciledArray); $k++): ?>
            <div class="bat-pair">
                <div class="bat-pair-side">
                    <div class="bat-pair-label">Statement</div>
                    <div class="bat-pair-date"><?php echo $csvReconciledArray[$k]['date']; ?></div>
                    <div class="bat-pair-amount"><?php echo $csvReconciledArray[$k]['amount']; ?></div>
                    <div class="bat-pair-merchant"><?php echo htmlspecialchars($csvReconciledArray[$k]['merchant_name']); ?></div>
                </div>
                <div class="bat-pair-side">
                    <div class="bat-pair-label">Database</div>
                    <div class="bat-pair-date"><?php echo $dbReconciledArray[$k]['date']; ?></div>
                    <div class="bat-pair-amount"><?php echo $dbReconciledArray[$k]['amount']; ?></div>
                    <div class="bat-pair-merchant"><?php echo htmlspecialchars($dbReconciledArray[$k]['merchant']); ?></div>
                </div>
            </div>
            <?php endfor; ?>
            <?php if(empty($csvReconciledArray)): ?>
                <div style="font-size:12px;color:var(--text-sub);padding:10px 4px;">No matched pairs yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    // Modal: categories for adding missing transactions
    require_once "class/categories.php";
    $categoriesObj = new Categories();
    $listOfSuperCategories = $categoriesObj->getAllSuperCategories($loginid);
    $listOfCategoryIds     = $categoriesObj->getCategory($loginid);
    ?>

    <div id="creditCardReconCateg" class="modal">
        <input id="recordItemId" type="hidden" />
        <div id="closeReconModal">✕</div>
        <div id="creditCardReconHdr">Add Transaction to Database</div>

        <div class="select-wrapper">
            <select id="creditCardSupCategories">
                <?php if(!empty($listOfSuperCategories)): ?>
                    <?php foreach($listOfSuperCategories as $lsc): ?>
                    <option value="<?php echo $lsc['super_category_id']; ?>">
                        <?php echo $lsc['super_category_desc']; ?>
                    </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option>Unable to fetch list</option>
                <?php endif; ?>
            </select>
        </div>

        <div class="select-wrapper">
            <select id="creditCardCategories">
                <?php if(!empty($listOfCategoryIds)): ?>
                    <?php foreach($listOfCategoryIds as $lcId): ?>
                    <option value="<?php echo $lcId; ?>">
                        <?php echo $categoriesObj->getCategoryDescription($lcId, $loginid)['description']; ?>
                    </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option>Unable to fetch list</option>
                <?php endif; ?>
            </select>
        </div>

        <div id="creditCardAmount"   class="modal-txn-info creditCardReconCategRows"></div>
        <div id="creditCardDate"     class="modal-txn-info creditCardReconCategRows"></div>
        <div id="creditCardMerchant" class="modal-txn-info creditCardReconCategRows"></div>

        <button id="creditCardAdd" class="creditCardReconCategRows">Save Transaction</button>
    </div>

    <?php
}
else
{
    echo "Invalid Request – either the statement has not been uploaded yet or some other error occurred.";
}
?>

<script>
// ── Toggle sections ──
$('#bat-unrecon-toggle').click(function() {
    var $body = $('#bat-unrecon-body');
    var $icon = $('#bat-unrecon-icon');
    if ($body.is(':visible')) {
        $body.slideUp(200);
        $icon.addClass('collapsed');
    } else {
        $body.slideDown(200);
        $icon.removeClass('collapsed');
    }
});

$('#bat-recon-toggle').click(function() {
    var $body = $('#bat-recon-body');
    var $icon = $('#bat-recon-icon');
    if ($body.is(':visible')) {
        $body.slideUp(200);
        $icon.addClass('collapsed');
    } else {
        $body.slideDown(200);
        $icon.removeClass('collapsed');
    }
});

// ── Add to DB modal ──
$('body').on('click', '.csvAdd', function() {
    var $row = $(this).closest('.bat-row-card');
    var merchant = $row.find('.bat-row-merchant').text();
    var amount   = $row.find('.bat-row-amount').text();
    var date     = $row.find('.bat-row-date').text();
    rowToRemove = $row;

    $('.overlay').show();
    $('#creditCardReconCateg').css('display', 'flex').hide().fadeIn(200);
    $('#creditCardAmount').html("<span class='ccr01'>Amount</span><span class='ccr02'>" + amount + "</span>");
    $('#creditCardMerchant').html("<span class='ccr01'>Merchant</span><span class='ccr02'>" + merchant + "</span>");
    $('#creditCardDate').html("<span class='ccr01'>Date</span><span class='ccr02'>" + date + "</span>");
});

$('#closeReconModal').click(function() {
    $('#creditCardReconCateg').fadeOut(150);
    $('.overlay').hide();
});

$('#creditCardAdd').click(function() {
    var desc     = $('#creditCardMerchant .ccr02').text();
    var amount   = $('#creditCardAmount .ccr02').text();
    var date     = $('#creditCardDate .ccr02').text();
    var sup_categ_id = $('#creditCardSupCategories').val();
    var category_id  = $('#creditCardCategories').val();

    $.ajax({
        type: 'POST',
        url: 'api/addRecordManuallyPostRecon.php',
        data: { sup_categ_id, category_id, desc, amount, date, mode: 'netbanking' },
        success: function(result) {
            if(result === '1') {
                alert('Something went wrong');
            } else {
                $('#creditCardReconCateg').fadeOut(150);
                $('.overlay').hide();
                location.reload();
            }
        }
    });
});

// ── Show reconcile UID input ──
$('body').on('click', '.csvReconciled', function() {
    var $card = $(this).closest('.bat-row-card');
    $card.find('.bat-recon-input-row').slideDown(150).find('.csvUIDMap').focus();
});

$('body').on('click', '.csvUpdateBtn', function() {
    var $card   = $(this).closest('.bat-row-card');
    var uidValue = $card.find('.csvUIDMap').val();
    var lineId   = $card.find('.csvLineId').val();

    $.ajax({
        url: 'api/reconcile_line.php',
        method: 'POST',
        dataType: 'json',
        data: { line_id: lineId, uid: uidValue, calledFrom: 'bank' }
    })
    .done(function(res) {
        if (!res || res.success !== true) {
            alert('Unable to reconcile!');
        } else {
            alert('Successfully reconciled');
            location.reload();
        }
    })
    .fail(function(xhr) {
        try {
            var r = JSON.parse(xhr.responseText || '{}');
            alert(r.error || 'Request failed (' + xhr.status + ')');
        } catch(e) {
            alert('Request failed (' + xhr.status + ')');
        }
    });
});

// ── Delete DB row ──
$('body').on('click', '.dbDelete', function() {
    var $card    = $(this).closest('.bat-row-card');
    var uidValue = $card.find('.dbUid').val();

    $.ajax({
        url: 'api/deleteDbTxn.php',
        method: 'POST',
        dataType: 'json',
        data: { uid: uidValue }
    })
    .done(function(res) {
        if (res !== true) {
            alert('Unable to delete!');
        } else {
            location.reload();
        }
    })
    .fail(function(xhr) {
        try {
            var r = JSON.parse(xhr.responseText || '{}');
            alert(r.error || 'Request failed (' + xhr.status + ')');
        } catch(e) {
            alert('Request failed (' + xhr.status + ')');
        }
    });
});

var rowToRemove = null;
</script>

<?php include('footer.php'); ?>
