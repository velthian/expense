<?php
session_start();

$expectedToken = '4dbe31e58e50e835b713fd2fd606e52e';
if (!isset($_COOKIE['akshat_access']) || $_COOKIE['akshat_access'] !== $expectedToken) {
    http_response_code(403);
    $_SESSION['akshatAuthenticated'] = FALSE;
    die("Access denied");
}
else
{
    $_SESSION['akshatAuthenticated'] = TRUE;
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta HTTP-EQUIV="CACHE-CONTROL" CONTENT="NO-CACHE">
        <title>Expense Manager 1.0</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" />
        <link href="css/master.css?version=46n13" rel="stylesheet" type="text/css"/>
        <link rel="preconnect" href="https://fonts.googleapis.com"> 
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Lobster&family=Overpass+Mono:wght@300&family=Poppins:wght@300&display=swap" rel="stylesheet">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
        
        <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css">
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
        <script src="js/expense_js.js?version=1c" type="text/javascript"></script>

    </head>

    <body>
            <div class="overlay"></div>
            <div class="loader-overlay is-hidden" role="status" aria-live="polite">
                <div id="loaderModal"><img src="images/spinner.gif" width="50px" /></div>
            </div>
    <?php
    include('db.php');

    require_once "class/forexCard.php";
    $forexObj = new forexCard();

    $txnList = array();
            
    $fromDate = "2025-01-01";
    $toDate = date('Y-m-d');

    $txnList = $forexObj->getForexCardTxn($fromDate, $toDate);

    if(!empty($txnList))
    {
        $totalAmount = 0;
        $totalAmountINR = 0;
        $groupedTxns = [];

        foreach ($txnList as $tx) 
        {
            $totalAmount    += (float)$tx['amount'];
            $totalAmountINR += (float)$tx['amountInINR'];

            $ts      = strtotime($tx['date']);
            $month   = date('F Y', $ts);
            $weekNum = (int)ceil(date('j', $ts) / 7); // 1–5

            // init month bucket
            if (!isset($groupedTxns[$month])) {
                $groupedTxns[$month] = [
                    'monthAUD' => 0,
                    'monthINR' => 0,
                    'weeks'    => []
                ];
            }

            // init week bucket
            if (!isset($groupedTxns[$month]['weeks'][$weekNum])) {
                $groupedTxns[$month]['weeks'][$weekNum] = [
                    'sumAUD' => 0,
                    'sumINR' => 0,
                    'txns'   => []
                ];
            }

            // accumulate
            $groupedTxns[$month]['monthAUD'] += (float)$tx['amount'];
            $groupedTxns[$month]['monthINR'] += (float)$tx['amountInINR'];

            $groupedTxns[$month]['weeks'][$weekNum]['sumAUD'] += (float)$tx['amount'];
            $groupedTxns[$month]['weeks'][$weekNum]['sumINR'] += (float)$tx['amountInINR'];
            $groupedTxns[$month]['weeks'][$weekNum]['txns'][]  = $tx;
        }

    ?>
    
    <div id="akshatHdr">
        <div class="akshatViewAggregate">
            <div class="aVAg01"><?php echo "AUD " . $totalAmount;?></div>
            <div class="aVAg01"><?php echo "₹ " . $totalAmountINR;?></div>
        </div>
        <div class="akshatActions">
            <button id="akshatAdd">ADD</button>
            <button id="akshatUpload">UPLOAD</button>
        </div>
    </div>

    <div id="addManual">
        <span id="closeAddManual" class="close-btn">&times;</span>
        <div id="addM01">ADD A TRANSACTION</div>
        <div class="date-picker-wrapper">
            <input type="text" id="addMDate" placeholder="Date" readonly />
            <span id="calendarIcon" style="cursor:pointer;">📅</span>
        </div>
        <input type="text" id="addMAmount" placeholder="Amount" />
        <input type="text" id="addMText" placeholder="Description" />
        <select id="addMDest">
            <option value="ComBank" selected>ComBank</option>
            <option value="ForexCard">ForexCard</option>
        </select>
        <button id="addMSubmit">SAVE</button>
        <div id="addMMsg"></div>
    </div>


    <?php
    require_once "class/convertToIndian.php";
    $convINRObj = new convertToIndian();

    foreach ($groupedTxns as $month => $bucket) 
    {
        // keep week order: 1,2,3,4,5
        $weeks = $bucket['weeks'];
        krsort($weeks);
        ?>
        <div id="akshatViewCont">
            <div class="monthHdg">
                <?php echo $month; ?>
                <div class="weekTotals">
                    <span class='reconExpand'>+</span><span class='glass totalMonthAUD'>Total: AUD <?php echo $bucket['monthAUD']; ?></span>
                    <span class='glass totalMonthINR'>Total: ₹ <?php echo ($convINRObj->convertToIndianCurrency($bucket['monthINR'])); ?></span>
                    <span class='glass avgWeekAUD'>Weekly Avg: AUD <?php echo (round(($bucket['monthAUD']/count($bucket['weeks'])),0)); ?></span>
                </div>
            </div>
            <div class='monthlyDetails'>
                <?php foreach ($weeks as $weekNum => $data) { ?>
                    <div class="weekHdg">
                        <?php echo "Week " . $weekNum; ?>
                        <span class="weekTotals">
                            <?php
                            echo " | AUD " . $data['sumAUD']
                            . " | ₹ " . $convINRObj->convertToIndianCurrency($data['sumINR']);
                            ?>
                        </span>
                    </div>

                    <div class="aVRowHdg">
                        <div class="aVRowCell">DATE</div>
                        <div class="aVRowCell">TIME</div>
                        <div class="aVRowCellLarge">MERCHANT</div>
                        <div class="aVRowCell amount">AMOUNT (AUD)</div>
                        <div class="aVRowCell amount">AMOUNT (INR)</div>
                        <div class="aVRowCell amount">MODE</div>
                        <div class="aVRowCell edit">ACT</div>
                    </div>

                    <?php 
                    foreach ($data['txns'] as $tx) 
                    { ?>
                        <div class="aVRow">
                            <input class="aVuid" type="hidden" value="<?php echo $tx['uid']; ?>" />
                            <div class="aVRowCell tranDate"><?php echo date('d M y', strtotime($tx['date']));?></div>
                            <div class="aVRowCell tranTime"><?php echo $tx['time'];?></div>
                            <div class="aVRowCellLarge"><?php echo $tx['merchant'];?></div>
                            <?php
                            if(substr($tx['uid'], 0, 3) == "MAN")
                            {
                            ?>
                                <div class="aVRowCellLarge editManualMerch"><input type="text" class="editManualMerch01" /></div>
                            <?php
                            }
                            ?>
                            <div class="aVRowCell amount realAmount"><?php echo "AUD " . $tx['amount'];?></div>
                            <?php
                            if(substr($tx['uid'], 0, 3) == "MAN")
                            {
                            ?>
                                <div class="aVRowCell amount editManualAmt"><input type="text" class="editManualAmt01" /></div>
                            <?php
                            }
                            ?>
                            <div class="aVRowCell amount"><?php echo "₹ " . $convINRObj->convertToIndianCurrency($tx['amountInINR']);?></div>
                            <div class="aVRowCell amount mode"><?php echo $tx['mode'];?></div>
                            <?php
                            if(substr($tx['uid'], 0, 3) == "MAN")
                            {
                            ?>
                                <div class="aVRowCell edit"><div class="editManual">EDIT</div></div>
                                <div class="aVRowCell save"><div class="saveManual">SAVE</div></div>
                            <?php
                            }
                            else
                            {
                            ?>
                                <div class="aVRowCell edit"></div>
                            <?php
                            }
                            ?>
                        </div>
                    <?php 
                    } ?>
                <?php } ?>
            </div>
        </div>
        <?php
    }
}
?>

<div id="uploadModal" style="display:none;">
    <span id="closeUploadModal" class="close-btn">&times;</span>
    <div id="uploadTitle">UPLOAD STATEMENT</div>

    <form id="csvUploadForm" enctype="multipart/form-data">
        <input type="file" id="fileInput" name="fileToUpload" accept=".csv" />
        <input type="hidden" name="formType" value="csvUpload" />
        <input id="uploadBtn" type="submit" value="Upload File" disabled />
    </form>

    <div id="uploadStatus"></div>
    <button id="uploadClose">CLOSE</button>
</div>

<script>

$(document).ready(function(){
    $('.loader-overlay').addClass('is-hidden');
});

$("#addMDate").datepicker({
        dateFormat: "yy-mm-dd",
        changeMonth: true,
        changeYear: true,
        showOn: "focus", // We'll trigger manually
        onSelect: function(dateText, inst) {
        $("#addMAmount").focus();
        } // move cursor to amount field
    });

$("#calendarIcon").click(function() {
$("#addMDate").datepicker("show");
});

$("#akshatAdd").click(function(){
    $('.overlay').show();
    $('#addManual').fadeIn();

});
$("#closeAddManual, .overlay").click(function(){
    $("#addManual").fadeOut();
    $(".overlay").fadeOut();
});

$('#addMSubmit').click(function()
{

    $('.loader-overlay').removeClass('is-hidden');
    var date = $('#addMDate').val();
    var amount = $('#addMAmount').val();
    var text = $('#addMText').val();
    var dest = $('#addMDest').val();
    saveTransaction('modal', "NEW", date, amount, text, dest)
    .then(msg => {
    alert(msg);
    if (msg === 'Database Updated Successfully') {
        window.location.reload(); // reload AFTER success
      }
    })
    .catch(err => {
    console.error("Error:", err);
    $('.loader-overlay').addClass('is-hidden');
    });
    
});

$('.saveManual').on("click", function()
{
    $('.loader-overlay').removeClass('is-hidden');

    var uid = $(this).parent().siblings('.aVuid').val();
    var date = $(this).parent().siblings('.tranDate').text();
    var amount = $(this).parent().siblings('.editManualAmt').children('.editManualAmt01').val();
    var text = $(this).parent().siblings('.editManualMerch').children('.editManualMerch01').val();
    var dest = $(this).parent().siblings('.mode').text();
    var msg = '';

    saveTransaction('inline', uid, date, amount, text, dest)
    .then(msg => {
    alert(msg);
    if (msg === 'Database Updated Successfully') {
        window.location.reload(); // reload AFTER success
      }
    })
    .catch(err => {
    console.error("Error:", err);
    $('.loader-overlay').addClass('is-hidden');
    });
    
});

$(".editManual").on("click", function()
{
    var merch = $(this).parent().siblings(".aVRowCellLarge").text();
    var amt = Number($(this).parent().siblings(".realAmount").text().replace(/^AUD\s*/, ''));

    $(this).parent().hide();
    $(this).parent().siblings(".save").fadeIn();
    $(this).parent().siblings(".aVRowCellLarge").hide();
    $(this).parent().siblings(".realAmount").hide();
    $(this).parent().siblings(".editManualMerch").children(".editManualMerch01").val(merch);
    $(this).parent().siblings(".editManualAmt").children(".editManualAmt01").val(amt);
    $(this).parent().siblings(".editManualMerch").fadeIn();
    $(this).parent().siblings(".editManualAmt").fadeIn();
    $(this).parent().siblings().children(".editManualMerch01").focus();
});


async function saveTransaction(from, uid, date, amount, text, dest) 
{
  const result = await $.ajax({
    type: "POST",
    url: "updateForexManual.php",
    dataType: "text",
    data: { from, uid, date, amount, text, dest }
  });

  const r = $.trim(result);
  switch (r) 
  {
    case '0': return "Database Updated Successfully";
    case '1': $('#addMDate').focus();   return "Invalid Date";
    case '2': $('#addMAmount').focus(); return "Enter a Valid Amount";
    case '3': $('#addMText').focus();   return "Enter a Valid Merchant Name";
    case '4': $('#addMDest').focus();   return "Invalid Category";
    case '5': return "Database Update Failed";
    case '6': return "Access Denied";
    default:  return `Unknown response: ${r}`;
  }
}

$('.reconExpand').click(function(){
    let $btn = $(this);
    let $list = $(this).parent().parent().siblings('.monthlyDetails');

    if ($list.is(':visible')) {
        $list.slideUp();
        $btn.text('+');   // change back to +
    } else {
        $list.slideDown();
        $btn.text('-');   // change to -
    }
});

$("#akshatUpload").click(function(){
    $('.overlay').show();
    $('#uploadModal').fadeIn();
});

$("#closeUploadModal, .overlay").click(function(){
    $('#uploadModal').fadeOut();
    $('.overlay').fadeOut();
});

$('#fileInput').on('change', function () {
    if (this.files.length > 0) {
        $('#uploadBtn').prop('disabled', false);
    } else {
        $('#uploadBtn').prop('disabled', true);
    }
});

$('#csvUploadForm').on('submit', function(e) {
    e.preventDefault();

    $('.loader-overlay').removeClass('is-hidden');
    $('#uploadStatus').html('Uploading & processing...');

    let formData = new FormData(this);

    $.ajax({
        url: 'akshatViewUpload.php',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function(resp) {
            $('#uploadStatus').html(resp.replace('__INGEST_DONE__', ''));

            if (resp.includes('__INGEST_DONE__')) {
                $('.loader-overlay').addClass('is-hidden');
            }
        },
        error: function() {
            $('#uploadStatus').html('❌ Upload failed');
            $('.loader-overlay').addClass('is-hidden');
        }
    });
});

$('#uploadClose').click(function()
{
    $('#uploadModa').fadeOut();
    window.location.reload();
});

</script>

</body>
</html>