<?php

include('header.php');

include_once('class/merchant.php');
include_once('class/categories.php');

$loginid = '';
$loginid = $_SESSION['loginid'];

$searchTerm = '';
$category_id = 0;
$obj = new merchant();
$data = $obj->getAllMerchant($searchTerm, $category_id);

$obj1 = new Categories();
$data1 = $obj1->getCategory($loginid);

?>
<link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<div id="a_main">
    <div id="a_main_hdr">
        <span class="title">ASSIGN CATEGORY</span>
    </div>
    <div id="a_main_data">
        <div id="a_m_d_filter">
            <div id="a_m_d_1">
                <div id="a_m_d_1_1">
                    <img src="images/lightbulb.png" width="40px" />
                </div>
                <div id="a_m_d_1_2">
                    Please choose the merchant to see or change the assigned category
                </div>
            </div>
            <input id="a_m_d_f_inp" class="input" autocomplete="off" placeholder="Enter Merchant Name"/>
            <div id="a_m_d_f_suggest">
                
            </div>
        </div>
        <div id="a_m_d_list">
<?php

include_once('class/transactions.php');
$tranObj = new transactions();

$dataAsArray = array();
$dataAsArray = json_decode($data, true);

if(!empty($dataAsArray) && !empty($data1))
{
    foreach($dataAsArray as $dat)
    {
        $html='';
        $html1 = '';


        if($dat['category_id'] == '0')
        {
            $amt = 0;
            $amt = $tranObj->getLastTransactionForMerchant($dat['merchant_id'], $loginid);
            
            $html = "<div class='ac_merch_name_cont ac_merch_name_cont_red'><div class='ac_merch_name_cont_cont'><div class='ac_merch_name'>" . $dat['merchant_name'] . "</div><div class='ac_merch_name ac_merch_name_cont_amt'>Amount: Rs. " . $amt . "</div><input type='hidden' class='ac_merch_id' value='" . $dat['merchant_id'] . "' /></div><div class='ac_merch_cat_list_cont'>";

            foreach($data1 as $d1)
            {
                $category_decription = '';
                $category_decription = $obj1->getCategoryDescription($d1, $loginid);
                if($category_decription != 'error')
                {

                    if($d1 == $dat['category_id'])
                    {
                        $html1 = $html1 . "<label class='ac_merch_cat_list'><input type='radio' class ='ac_merch_cat_list_inp' value='" . $d1 . "' checked='checked' name ='" . $dat['merchant_id'] ."' />" . $category_decription['description'] . "</label>";
                    }
                    else 
                    {
                        $html1 = $html1 . "<label class='ac_merch_cat_list'><input type='radio' class ='ac_merch_cat_list_inp' value='" . $d1 . "' name ='" . $dat['merchant_id'] ."' />" . $category_decription['description'] . "</label>";
                    }   

                }
            }
            echo($html.$html1 . "</div></div>");
        }
    }
}
else
{
    echo("Nothing to show!");
}
    
?>
        </div>
    </div>    
</div>


    <script>
    
    $(document).ready(function(){
        $('#c_s_3').css({"background": "rgb(99,99,102)"});
        $('#c_s_3_1').css({"color": "#f5f5f5"}); 
        
        $("#a_m_d_f_inp").keyup(function(){
            
            var param = $(this).val();
            
            if(param != '')
            {
                $.ajax({type: "POST",
                url: "getMerchantList.php",
                data: {param: param},
                success:function(result) 
                { 
                    var data1 = JSON.parse(result);
                    $("#a_m_d_f_suggest").html("");
                    $("#a_m_d_f_suggest").hide();
                    var searchResultHtml = "";
                    if(data1 == "")
                    {
                        $("#a_m_d_f_suggest").text('No matches found');
                        //$(".loader").css("display","none");
                    }
                    else
                    {
                        if(data1[0] == "too_many")
                        {
                           $("#a_m_d_f_suggest").text('Too many results. Narrow search');

                        }
                        else
                        {
                            $.map(data1,function(m){
                            searchResultHtml += suggestion_box(m);
                            $("#a_m_d_f_suggest").html(searchResultHtml);    
                            });                    
                        }

                    }

                    $("#a_m_d_f_suggest").css({"display":"flex"}); 
                    $('#a_m_d_f_suggest').fadeIn();
                }
                });
            }
            else
            {
                $('#a_m_d_f_suggest').fadeOut();
                $('#a_m_d_f_suggest').html('');
            }
        });
        
        function suggestion_box(m)
        {
            //$(".loader").css("display","none");     
            //var div1 = "<tr><td class='opt'>"+ m.fund_name +"<input type='hidden' value='" + m.scheme_code + "' /></td></tr>";
            var div1 = "<div class='a_m_d_f_row'><input class='a_m_d_f_merch_id' type='hidden' value='" + m.merchant_id + "' /><div class='a_m_d_f_merch'>" + m.merchant_desc + "</div><div class='a_m_d_f_cat'>" + m.category_desc + "</div></div>";
            return div1;

        }
    });
    
    $('body').on('change','.ac_merch_cat_list', function(){
        var category_id = $(this).children('.ac_merch_cat_list_inp').val();

        if(typeof category_id === 'undefined')
        {
            alert("Please select a category");
        }
        else
        {
            var merchant_id;
            merchant_id = $(this).parent().siblings('.ac_merch_name_cont_cont').children('.ac_merch_id').val();
            
            
            $.ajax({type: "POST",
                url: "updateCategory.php",
                data: {merchant_id: merchant_id, category_id: category_id},
                success:function(result)
                {
                    if(jQuery.trim(result) === '0')
                    {
                        alert("Update unsuccessful");
                    }
                    else
                    {
                        location.reload();
                    }
                }
                });
        }        
    });
    
    $(document).on('click','.a_m_d_f_row', function(){
        var merch_id = $(this).children('.a_m_d_f_merch_id').val();
        
        $.ajax({type: "POST",
        url: "getMerchantDetails.php",
        data: {merch_id: merch_id},
        success:function(result) 
        { 
            if(result === '')
            {
                alert("Something went wrong");
            }
            else
            {
                $('#a_m_d_f_suggest').hide();
                $('#a_m_d_list').prepend(result);
            }
        }
        });
    });

    </script>
<?php
include('footer.php');
?>
