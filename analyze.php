<?php

include('header.php');

//require '../investo/vendor/autoload.php';
//require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

include('displayDate.php');

$loginid = $_SESSION['loginid'];

include_once('class/ccDates.php');
$dateObj = new ccDates();
$fy_date_arr = $dateObj->getInceptionDate($loginid);
$fy_date = $fy_date_arr['fyBeginDate'];
$fy_end_date = $fy_date_arr['fyEndDate'];

?>
<div id='analyze_cont'>
    <div id='a_c_1'>
        <div id='a_c_1_1'>
            <img src='images/calendar.png' width='50px' />
        </div>
        <div id='a_c_1_2'>
            <div id='a_c_1_1_1' class='section_header'>
               Please choose the begin and end dates for the Fiscal Year you want YTD reports to track. You can set it once and change at any time.
            </div>
            <div id='a_c_1_1_2'>
                <div id="fy_dates_inp"><input id='a_c_1_1_2_dt' type='text' class='datepicker field_separator' readonly='readonly' placeholder='FY Start Date' /><input id='a_c_1_1_2_dt_end' type='text' class='datepicker field_separator' readonly='readonly' placeholder='FY End Date' /></div>
                <div class="critical_date_card">
                    <div class="critical_date_row">
                        <div class="critical_date_label">Start Date</div>
                        <div class='critical_date_value'><?php echo $fy_date; ?></div>
                    </div>
                    <div class="critical_date_row">
                        <div class="critical_date_label">End Date</div>
                        <div class='critical_date_value'><?php echo $fy_end_date; ?></div>
                    </div>
                </div>
            </div>
            <div id='a_c_d'>
                <button id="analyze_btn" class='field_separator'>GENERATE REPORT</button>
                <?php
                    $link = '';
                    $link = "downloads/em_" . $_SESSION['loginid'] . "_02.xlsx";
                ?>
                <a id="a_c_d_1_1" href="<?php echo $link; ?>"><img src="images/download.png" alt="" width="20px" /></a>
            </div>
        </div>
    </div>
    <div id="analyze_show_cont"></div>
    <div id='ytd_cont'></div>
</div>
<script>
$(document).ready(function(){
    $('#c_s_6').css({"background": "rgb(99,99,102)"});
    $('#c_s_6_1').css({"color": "#f5f5f5"});  
    if($('#a_c_1_1_2_1').text() != '')
    {
        $('#a_c_1_1_2_1').show();
    }
    if($('#a_c_1_1_2_2').text() != '')
    {
        $('#a_c_1_1_2_2').show();
    }
});

$('#a_c_1_1_2_dt').change(function(){
    var dateObject = $('#a_c_1_1_2_dt').datepicker("getDate");
    var fy_date = $.datepicker.formatDate("yy-mm-dd", dateObject);

    $('#a_c_1_1_2_1').text(fy_date); 
    $('#a_c_1_1_2_1').show();
});

$('#a_c_1_1_2_dt_end').change(function(){
    var dateObject = $('#a_c_1_1_2_dt_end').datepicker("getDate");
    var fy_end_date = $.datepicker.formatDate("yy-mm-dd", dateObject);

    $('#a_c_1_1_2_2').text(fy_end_date); 
    $('#a_c_1_1_2_2').show();
});

$( function(){
    $( ".datepicker" ).datepicker({
      changeMonth: true,
      changeYear: true
    });
});

$('#mc_date_left').click(function(){
    $('#analyze_show_cont').empty();
    $('#ytd_cont').empty();
});

$('#mc_date_right').click(function(){
    $('#analyze_show_cont').empty();
    $('#ytd_cont').empty();
});

$('#analyze_btn').click(function(){
    
    var fy_date = $('#a_c_1_1_2_1').text();
    var fy_end_date = $('#a_c_1_1_2_2').text();
        
    if(fy_date == '')
    {
        alert("Please choose a FY begin date first");
        $('#a_c_1_1_2_dt').focus();
    }
    else
    {
        var from_date = $('#mc_date_inner_1_inp').val();

        $.ajax({type: "POST",
        url: "getReports.php",
        data: {from_date: from_date, type:'monthly', fy_date: fy_date},
        success:function(result) 
        { 
            if(!result)
            {
                alert("Something went wrong");
            }
            else
            {
                var dat = JSON.parse(result);
                $('#analyze_show_cont').empty();
                $.each(dat, function (index, value) {
                    if(value.data.length > '0')
                    {
                        var htm = '';
                        htm ="<div class='analyze_row'><div class='report_type'>Monthly Report</div><div class='a_sup_0'>" + "<div class='a_sup'>" + value.super_category + "</div><div class='a_sup_dat_cont'><div class='a_sup_act'><span class='a_sup_h_1'>Actual</span><span class='a_sup_v'>" + value.super_category_amount + "</span></div><div class='a_sup_bud'><span class='a_sup_h_2'>Budget</span><span class='a_sup_v'>" + value.super_category_budget + "</span></div></div></div><div class='a_sup_dat'><div class='a_sup_dat_hdr'><div class='a_s_d_h_0'>CATEGORY</div><div class='a_s_d_h_1'>BUDGET</div><div class='a_s_d_h_1'>ACTUAL</div><div class='a_s_d_h_1'>VARIANCE</div></div>";
                        $.each(value.data, function (index1, value1){
                            htm = htm + "<div class='a_dat'><div class='a_cat'>" + value1.category_description + "</div><div class='a_budget a_item'>" + value1.budget + "</div><div class='a_actual a_item'>" + value1.category_amount + "</div><div class='a_variance a_item'>" + value1.variance + "</div></div>";
                        });
                        htm = htm + "</div></div>";
                        $('#analyze_show_cont').append(htm);
                    }
                });
            }
        }

        });
    
    
        $.ajax({type: "POST",
        url: "getReports.php",
        data: {from_date: from_date, type:'annual', fy_date: fy_date, fy_end_date: fy_end_date},
        success:function(result) 
        {   
            if(!result)
            {
                alert("Something went wrong");
            }
            else
            {
                var dat = JSON.parse(result);

                $('#ytd_cont').empty();
                $.each(dat, function (index, value) {
                    if(value.data.length > '0')
                    {
                        var htm = '';
                        htm ="<div class='analyze_row analyze_ytd'><div class='report_type'>YTD Report</div><div class='a_sup_0'>" + "<div class='a_sup'>" + value.super_category + "</div><div class='a_sup_dat_cont'><div class='a_sup_act'><span class='a_sup_h_1'>Actual</span><span class='a_sup_v'>" + value.super_category_amount + "</span></div><div class='a_sup_bud'><span class='a_sup_h_2'>Budget</span><span class='a_sup_v'>" + value.super_category_budget + "</span></div></div></div><div class='a_sup_dat'><div class='a_sup_dat_hdr'><div class='a_s_d_h_0'>CATEGORY</div><div class='a_s_d_h_1'>BUDGET</div><div class='a_s_d_h_1'>ACTUAL</div><div class='a_s_d_h_1'>VARIANCE</div></div>";
                        $.each(value.data, function (index1, value1){
                            htm = htm + "<div class='a_dat'><div class='a_cat'>" + value1.category_description + "</div><div class='a_budget a_item'>" + value1.budget + "</div><div class='a_actual a_item'>" + value1.category_amount + "</div><div class='a_variance a_item'>" + value1.variance + "</div></div>";
                        });
                        htm = htm + "</div></div>";
                        $('#ytd_cont').append(htm);
                    }
                });
            }
        }
        });
    }
});

</script>

<?php
include('footer.php');
?>

