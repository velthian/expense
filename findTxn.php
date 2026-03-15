<?php
include('header.php');

include_once('class/categories.php');
$obj = new categories();

$loginid = $_SESSION['loginid'];
$category_list = array();
$category_list = $obj->getCategory($loginid);

$catDisp = array();
foreach($category_list as $cat)
{
    $category_description = '';
    $category_description = $obj->getCategoryDescription($cat, $loginid);
    $catDisp[] = array('cat' => $cat, 'desc' => $category_description);
}

$supCatDisp = array();
$super_category_list = array();
$super_category_list = $obj->getSuperCategory($loginid);

foreach($super_category_list as $sup)
{
    $supCategory_description = '';
    $supCategory_description = strtoupper($obj->getSuperCategoryDescription($sup));
    $supCatDisp[] = array('supCat' => $sup, 'supDesc' => $supCategory_description);    
} 

?>
<div id="showTranFrame">
    <!---
    <div id="showTranFilters">
        <div class="showTranFilterHdr">
            Super Categories
        </div>
        <div id="supCatCont" class="showTranFilterElements">

        <?php
        foreach($supCatDisp as $supCat)
        {
            ?>
            <div class="supCatelement">
                <label class="supCatEleLbl">
                    <input type="checkbox" name="supCatChoice" value="<?php echo $supCat['supCat']?>" />
                    <?php echo $supCat['supDesc']?>
                </label>
            </div>
            <?php
        }
        ?>
        </div>
        <div class="showTranFilterHdr">
            Categories
        </div>
        <div id="CatCont" class="showTranFilterElements">

        <?php
        foreach($catDisp as $cat)
        {
            ?>
            <div class="catElement">
                <label class="catEleLbl">
                    <input type="checkbox" name="catChoice" value="<?php echo $cat['cat']?>" />
                    <?php echo $cat['desc']['description']?>
                </label>
            </div>
            <?php
        }
        ?>
        </div>
    </div>
    --->
    <div id="showTranMain">
        <div class="showTranFilterHdr">transaction details</div>
        <div class="showTranFilterMore">
            <div id="stf01">
                <input type='text' id='stfDt01' class='stfDate datepicker' readonly='readonly' placeholder='From' />
                <input type='text' id='stfDt02' class='stfDate datepicker' readonly='readonly' placeholder='To' />
            </div>
            <div id="stf02">
                <input type='text' id='stfDt03' class='stfSearch' placeholder='Search keyword...' />
            </div>
            <div id="stf03">
                <input type='button' id='stfDt04' class='stfSubmit' value="Search" />
            </div>
        </div>
    </div>
</div>

<div class="showTranDetails">

</div>

<script>
    $('.stfSubmit').click(function(){        
        
        var $supCatfilters = $('input[name="supCatChoice"]:checked');
        
        var i = 0;
        var supCatf = [""];
        
        $supCatfilters.each(function(){
            supCatf[i] = $(this).val();
            i = i+1;
        });
    
        var $catfilters = $('input[name="catChoice"]:checked');
        
        var j = 0;
        var catf = [""];
        
        $catfilters.each(function(){
            catf[j] = $(this).val();
            j = j+1;
        });
        
        var dateObject1 = $('#stfDt01').datepicker("getDate");
        var dateFrom = $.datepicker.formatDate("yy-mm-dd", dateObject1);
        
        var dateObject2 = $('#stfDt02').datepicker("getDate");
        var dateTo = $.datepicker.formatDate("yy-mm-dd", dateObject2);
        
        var searchTerm = $('#stfDt03').val();
        
        $.ajax({type: "POST",
        url: "getFilteredTransaction.php",
        data: {supCatf: supCatf, catf: catf, searchTerm: searchTerm, fromDt: dateFrom, toDt: dateTo},
        success:function(result) 
        { 
            var resp = jQuery.trim(result);
            resp = JSON.parse(resp);
            
            var error = resp[0];
            var data = resp[1];
            
            var errText = '';
            var html = '';
            
            if(error != 'success')
            {
                switch(error)
                {
                    case('errSupCat'):
                    {
                        errText = "Please choose the right Super Category";
                        break;
                    }
                    case('errCat'):
                    {
                        errText = "Please choose the right Category";
                        break;
                    }
                    case('errFromDt'):
                    {
                        errText = "Please enter a valid FROM date";
                        $('#stfDt01').focus();
                        break;
                    }
                    case('errToDt'):
                    {
                        errText = "Please enter a valid TO date";
                        $('#stfDt02').focus();
                        break;
                    }
                    case('errToDtGtFrom'):
                    {
                        errText = "TO date should be greater than FROM date";
                        $('#stfDt02').focus();
                        break;
                    }
                    case('errSearchTerm'):
                    {
                        errText = "Search term cannot have spaces or special characters";
                        $('#stfDt03').focus();
                        break;
                    }
                }
            }
            else
            {
                var totalvalue =0;
                
                html += '<div class="flex-table-header"><div class="flex-cell wide">Description</div><div class="flex-cell">Date</div><div class="flex-cell">Amount</div><div class="flex-cell">Mode</div></div>';
                
                if (data.length === 0)
                {
                    html += "<div class='findTxnRow'>No matching record found. Please refine your search.</div>";
                }
                else
                {
                    data.forEach(function(value, index) {
                        
                        html += "<div class='findTxnRow'>";
                        var counter = 0;
                        $.each(value, function(key, val){
                            if(counter == 0)
                            {
                                html += "<div class='flex-cell wide'>"+ val +"</div>";
                            }
                            else
                            {
                                html += "<div class='flex-cell'>"+ val +"</div>";
                            }
                            if(key == 'amount')
                            {
                                totalvalue += parseFloat(val);
                            }
                            counter++;
                        });
                        html += "</div>";
                        
                    });
                }
                
                html += "<div class='flex-table-row'>Total Amount: " + Math.round(totalvalue) + " Rs.</div>";
            }
            
            
            $('.showTranDetails').empty();
            $('.showTranDetails').append(html);
            $('.showTranDetails').css('display', 'flex');
        }
        });
    
});
</script>


<?php
include('footer.php');
?>

<?php
/*
include('displaySuperCategories.php');
include('displayCategories.php');

include('db.php');

//require '../investo/vendor/autoload.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$sql = "SELECT m.merchant_name, t.date, t.mode, t.amount FROM transactions t INNER JOIN merchant m ON m.merchant_id = t.merchant_id INNER JOIN categories c ON c.category_id = m.category_id INNER JOIN super_category s ON t.super_category_id = s.super_category_id WHERE s.super_category_id='7' AND c.category_id ='8' ORDER BY t.date ASC";

$result = $conn->query($sql);
$num_rows = $result->num_rows;
?>
<div id='txnCont'>
    <?php
    $data = array();
    
    for($i=0; $i < $num_rows; $i++)
    {
        $row = $result->fetch_assoc();
        ?>
        <div class='txnRowShow'>
            <div class='txnRowElement'><?php echo($row['merchant_name']);?></div>
            <div class='txnRowElement'><?php echo($row['date']);?></div>
            <div class='txnRowElement'><?php echo($row['amount']);?></div>
            <div class='txnRowElement'><?php echo($row['mode']);?></div>
        </div>
    <?php
        $data[] = array('merchant_name' => $row['merchant_name'], 'date' => $row['date'], 'amount' => $row['amount'], 'mode' => $row['mode']);
    }
    create_xls($data);
    $conn->close();

    ?>
    <div id='cc_categ_download'>
        <?php
        $link = '';
        $link = "downloads/em_" . $_SESSION['loginid'] . "_0102.xlsx";
        ?>
        <a href= "<?php echo $link; ?>">Download Statement</a>
    </div>
</div>

<?php
function create_xls($data)
{
    $fname = "downloads/em_" . $_SESSION['loginid'] . "_0102.xlsx";
    if(file_exists($fname))
    {
        unlink($fname);
    }
    $filename = "downloads/em_" . $_SESSION['loginid'] . "_0102.xlsx";
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('a1','Merchant');
    $sheet->setCellValue('b1','Date');
    $sheet->setCellValue('c1','Amount');
    $sheet->setCellValue('d1', 'Mode');

    $writer = new Xlsx($spreadsheet);
    $counter = 2;

    foreach($data as $dat)
    {
        $var = 'a';
        $index1 = $var.$counter;
        $var++;
        $index2 = $var.$counter;
        $var++;
        $index3 = $var.$counter;
        $var++;
        $index4 = $var.$counter;

        $sheet->setCellValue($index1, $dat['merchant_name']);
        $sheet->setCellValue($index2, $dat['date']);
        $sheet->setCellValue($index3, $dat['amount']);
        $sheet->setCellValue($index4, $dat['mode']);

        $writer = new Xlsx($spreadsheet);
        $counter++;        
    }                  
    $writer->save($filename);
}
include('footer.php');

 */
?>