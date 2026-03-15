<?php
include('header.php');

require '../investo/vendor/autoload.php';
//require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$param = '';
if(isset($_GET['param']))
{
    include_once('class/transactions.php');    
    $obj = new transactions();
    
    include_once('class/ccDates.php');
    $obj1 = new ccDates();
    
    if($_GET['param'] == 'c')
    {
        $dt_c = '';
        $dt_c = date('Y-m-d');
        
        $date_arr = $obj1->stmtDate($dt_c, 'c');
        $from_date = $date_arr[0];
        $to_date = $date_arr[1];
    }
    else
    {    
        $from_date = $_GET['from_date'];
        $to_date = $_GET['to_date'];
    }
    
    //$data = $obj->getCreditCardTxn($from_date, $to_date);
    $filter[0] = 'creditcard';
    $data = $obj->getTransactions($categories, $date_from, $filter);

    $continue = FALSE;

    if(empty($data))
    {
        $continue = FALSE;
    }
    else
    {
        $continue = TRUE;
    }
    
}

?>

<div id="content_wrap_2">
    <div id="content_wrap_header"><span class='title'>Credit Card Transactions</span></div>
    <div id="stmt_dates">
    <?php 
        $date = new DateTime(date('Y-m-d'));
        $curr_date = '';
        $curr_date = $date->format('Y-m-d');
        
        $html='';
        $html = "<div id='link1'><a href='creditCard.php?param=c'>Current Period</a></div>";
        echo $html;

        include_once('class/ccDates.php');

        $obj1 = new ccDates();
        
        for($i=0; $i< 5; $i++)
        {
            $dt = $date->format('Y-m-d');
            $dt_arr = array();
            $dt_arr = $obj1->stmtDate($dt, 'p');
            $htm = '';
            $htm = "<div id='link1'><a href='creditCard.php?param=p&from_date=" . $dt_arr[0] . "&to_date=" . $dt_arr[1] . "'>" . date('d M y', strtotime($dt_arr[0])) . " to " . date('d M y', strtotime($dt_arr[1])) . "</a></div>";
            echo($htm);
            $date = $date->modify('-1 month');
        }
    ?>
    </div>

    <?php
    if($continue)
    {
        create_xls($data);

    ?>
        <div id="cc_categ_sup_cont">
            <div id="cc_categ_sup_amt_cont">
                <div id='cc_categ_sup_amt'><div id='cc_categ_sup_amt_hdr'>AMOUNT SPENT</div><div id='cc_categ_sup_amt_val'><?php echo $data['super_amt'];?></div></div>
                <div id='cc_categ_dates'>
                    <?php echo("{" . $data['from_dt'] . " to " . $data['to_dt'] ."}");?>
                </div>
                <div id='cc_decoration'></div>
            </div>
            <div id='cc_categ_download'>
                <?php
                $link = '';
                $link = "downloads/em_" . $_SESSION['loginid'] . "_01.xlsx";
                ?>
                <a href= "<?php echo $link; ?>">Download Statement</a>
            </div>
            <div id="cc_categ_sup_amt_rows_cont">
    <?php
        foreach($data['expense_data'] as $dat)
        {
    ?>
            <div class="cc_sup_categ_wrap">
                <div class="cc_sup_categ_desc">
                    <span class='cc_sup_categ_d_h cc_sup_categ_d_h_l'><?php echo(strtoupper($dat['sup_cat_desc'])); ?></span>
                    <span class='cc_sup_categ_d_h'><?php echo($dat['sup_cat_amt']); ?></span>
                </div>
                <input type='hidden' class='cc_sup_categ_val' value='<?php echo($dat['sup_cat_id']); ?>' />
                <div class='cc_categ_wrap_cont'>
                <?php
                foreach($dat['sup_cat_data'] as $da)
                {
                    if(abs($da[2]) > 0)
                    {
                ?>
                    <div class="cc_categ_wrap">
                        <div class="cc_data">
                            <div class="cc_data_hdr1">
                                <div class="cc_data_desc cc_data_fmt">
                                    <?php echo($da[1]);?>
                                </div>
                                <div class="cc_data_summ">
                                    <div class="cc_data_amt cc_data_fmt1">
                                        <span><?php echo($da[2]);?></span>
                                    </div>
                                    <div class="cc_data_budg cc_data_fmt1">
                                        <span><?php echo($da[4]);?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="cc_data_ctrl">
                                <img src="images/collapse.png" class="cc_data_ctrl_img" width="15px" alt=""/>
                                <img src="images/expand.png" class="cc_data_ctrl_img2" width="10px" alt=""/>
                            </div>
                        </div>
                        <div class="cc_data_cont">
                <?php
                        foreach($da[3] as $d)
                        {
                ?>
                        <div class="cc_data_row">
                            <div class="cc_data_row_merch">
                                <?php echo $d['merchant_name'];?>
                            </div>
                            <input class="cc_data_row_uid" type="hidden" value="<?php echo $d['uid'];?>" />
                            <div class="cc_data_row_01">
                                <div class="cc_data_row_amount cc_data_row_fmt">
                                    <?php echo $d['amount'];?>
                                </div>
                                <div class="cc_data_row_date cc_data_row_fmt">
                                    <?php echo $d['date'];?>
                                </div>
                                <div class="cc_data_row_sup_cat cc_data_row_fmt">
                                    <?php echo $d['sup_cat'];?>
                                </div>
                            </div>
                        </div>
                <?php
                        }
                        ?>
                        </div>
                    </div> 
            <?php
                    }
                }
            ?>
                </div><!-- cor cc_categ_Wrap_cont -->
            </div> <!<!-- for sup_categ_wrap -->
            <?php
        }
        ?>
    </div>
    <?php
    }
    ?>
        </div>
</div>
<script>
    $(document).ready(function(){
        $('#c_s_4').css({"background": "rgb(99,99,102)"});
        $('#c_s_4_1').css({"color": "#f5f5f5"}); 
        
        $('.cc_data_hdr1').css({"flex-direction":"column","font-size":"12px"});
        $('.cc_data_hdr1').find('.cc_data_amt').css({"text-align":"left"});
        $('.cc_data_cont').hide();
        //$('.cc_categ_wrap').css({"width":"20%"});
        $('.cc_data_ctrl_img2').show();
        $('.cc_data_ctrl_img').hide();
    });
    
    //COLLAPSE
    $('.cc_data_ctrl_img').click(function(){
        $(this).parent().parent().parent('.cc_categ_wrap').css({"max-height":"150px"});
        $(this).parent().siblings('.cc_data_hdr1').css({"flex-direction":"column","font-size":"12px"});
        $(this).parent().siblings('.cc_data_hdr1').find('.cc_data_amt').css({"text-align":"right"});
        $(this).parent().siblings('.cc_data_hdr1').find('.cc_data_budg').css({"text-align":"right"});
        $(this).parent().parent().siblings('.cc_data_cont').hide();
        $(this).parent().parent().find('.cc_data_amt').css({"margin-top":"10px"});
        $(this).parent().parent().parent().css({"max-width":"150px"});
        $(this).parent().parent().parent().css({"width":"25%"});
        $(this).siblings('.cc_data_ctrl_img2').show();
        $(this).hide();
    });
    
    //EXPAND
    $('.cc_data_ctrl_img2').click(function(){
        $(this).parent().parent().parent('.cc_categ_wrap').css({"max-height":"none"});
        $(this).parent().siblings('.cc_data_hdr1').css({"flex-direction":"row","font-size":"14px"});
        $(this).parent().siblings('.cc_data_hdr1').find('.cc_data_amt').css({"text-align":"right"});
        $(this).parent().siblings('.cc_data_hdr1').find('.cc_data_budg').css({"text-align":"right"});
        $(this).parent().parent().siblings('.cc_data_cont').show();
        $(this).parent().parent().find('.cc_data_amt').css({"margin-top":"0px"});
        $(this).parent().parent().parent().css({"max-width":"100%"});
        $(this).parent().parent().parent().css({"width":"100%"});
        $(this).siblings('.cc_data_ctrl_img').show();
        $(this).hide();
    });
    
    $(document).on('change','.sup_cat_txn', function(){
        var sup_cat = this.value;
        var uid = $(this).parent().parent().siblings('.cc_data_row_uid').val();

        var text = "Are you sure you want to change the Parent Category?";
        if (confirm(text) == true) 
        {
            $.ajax({type: "POST",
            url: "updateSupCat.php",
            data: {uid: uid, sup_cat: sup_cat},
            success:function(result) 
            { 
                if(!result)
                {
                    alert("Something went wrong");
                }
                else
                {
                    location.reload();
                }
            }
            });
        }
    });
</script>

<?php
function create_xls($data)
{
    $fname = "downloads/em_" . $_SESSION['loginid'] . "_01.xlsx";
    if(file_exists($fname))
    {
        unlink($fname);
    }
    $filename = "downloads/em_" . $_SESSION['loginid'] . "_01.xlsx";
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('a1','Super Category');
    $sheet->setCellValue('b1','Category');
    $sheet->setCellValue('c1','Txn Date');
    $sheet->setCellValue('d1', 'Merchant');
    $sheet->setCellValue('e1', 'Amount');

    $writer = new Xlsx($spreadsheet);
    $counter = 2;

    foreach($data['expense_data'] as $dat)
    {
        $sup_cat_desc = '';
        $sup_cat_desc = $dat['sup_cat_desc'];

        foreach($dat['sup_cat_data'] as $da)
        {
            $categ = '';
            $categ = $da[1];
            foreach($da[3] as $d)
            {
                $var = 'a';
                $index1 = $var.$counter;
                $var++;
                $index2 = $var.$counter;
                $var++;
                $index3 = $var.$counter;
                $var++;
                $index4 = $var.$counter;
                $var++;
                $index5 = $var.$counter;

                $sheet->setCellValue($index1, $sup_cat_desc);
                $sheet->setCellValue($index2, $categ);
                $sheet->setCellValue($index3, $d['date']);
                $sheet->setCellValue($index4, $d['merchant_name']);
                $sheet->setCellValue($index5, $d['amount']);

                $writer = new Xlsx($spreadsheet);
                $counter++;        
            }

        }
    }                  
    $writer->save($filename);
}
include('footer.php');
?>
