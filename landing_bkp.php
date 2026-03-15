<?php
include('header.php');
include('displayDate.php');
include('displayCategories.php');
?>

<script>
    
    $(document).ready(function(){
        
        $('#c_s_1').css({"background": "rgb(99,99,102)"});
        $('#c_s_1_1').css({"color": "#f5f5f5"}); 

        $(document).on('change','input[name="addValueToCat"]', function(){
            if($(this).is(':checked'))
            {
                $(this).parent().css({"background":"rgb(142, 142, 147)", "color": "#fff"});
            }
            else
            {
                $(this).parent().css({"background":"#fff", "color": "rgb(142, 142, 147)"});
                $('#mc_content_1_overall').hide();              
            }
            populate_details();
            $('#mc_content').show();
        });
        
        $(document).on('change','input[name="mc_f_inp"]', function(){
            if($(this).is(':checked'))
            {
                $(this).parent().css({"background":"rgb(142, 142, 147)", "color": "#fff"});
            }
            else
            {
                $(this).parent().css({"background":"#fff", "color": "rgb(142, 142, 147)"});
                $('#mc_content_1_overall').hide();              
            }
            populate_details();
        });
        
    });
    
    $( function(){
        $( ".datepicker" ).datepicker({
          changeMonth: true,
          changeYear: true
        });
    });
    
            
    function populate_details()
    {
        var $boxes = $('input[name="addValueToCat"]:checked');
        var $filters = $('input[name="mc_f_inp"]:checked');
        
        var i = 0;
        var f = [""];
        
        $filters.each(function(){
            f[i] = $(this).val();
            i = i+1;
        });
        
        $('#mc_content').hide();
        $('#mc_content_1').empty();
        
        var super_global_amount = 0;
        
        $boxes.each(function(){
            var chosen_category = '';
            var chosen_month = '';

            chosen_category = $(this).val();
            chosen_month = $('#mc_date_inner_1_inp').val();

            $.ajax({type: "POST",
            url: "getCategDesc.php",
            data: {chosen_category: chosen_category},
            success:function(result1) 
            { 
                if(!result1)
                {
                    alert("Something went wrong");
                }
                else
                {
                    var data1 = JSON.parse(result1);

                    $.ajax({type: "POST",
                    url: "getDataByCategory.php",
                    data: {chosen_category: chosen_category, chosen_month: chosen_month, filter: f},
                    success:function(result) 
                    { 
                        if(!result)
                        {
                            alert("Something went wrong");
                        }
                        else
                        {
                            $('#mc_content_1').show();
                            var data = '';
                            data = JSON.parse(result);
                            var html_super = '';

                            var html_super1 = "<div class='categ_section'><div class='categ_hdr'><input type='hidden' class='category_id' value='" + chosen_category + "' /><div class='categ_hdr_l'>" + data1.description + "</div>";
                            
                            var super_amt = 0;
                            
                            $.each(data, function (index, value) {
                                var html = '';
                                html = "<div class='mc_content_1_row'><input class='mc_content_1_uid' type='hidden' value ='" + value.uid + "' /><div class='mc_content_1_desc mc_content_1_001'>" + value.merchant_name + "</div><div class='mc_content_1_amount mc_content_1_001'>" + value.amount + "</div><div class='mc_content_1_date mc_content_1_001'>" + value.tran_date + "</div><div class='mc_content_1_notes mc_content_1_001'>" + value.mode + "</div><div class='mc_content_1_sup_cat mc_content_1_001'>" + value.super_category_desc + "</div><div><input type='checkbox' /></div></div>";
                                html_super = html_super + html;
                                super_amt = parseInt(super_amt) + parseInt(value.amount);
                                
                            });
                            
                            if(Math.abs(super_amt) > 0)
                            {
                                var html_super2 = "<div class='categ_hdr_amt'><div class='categ_hdr_amt_actual'><span class='categ_hdr_amt_act_span'>ACTUAL</span><span class='categ_hdr_amt_budg_span_data'>Rs. " + super_amt + "</span></div></div>";
                                var html_super3 = "<div class='categ_hdr_r'><image src='images/add.svg' class='add_tag' /></div></div>";

                                html_super = html_super1 + html_super2 + html_super3 + html_super + "</div>";
                                $('#mc_content_1').append(html_super);
                                super_global_amount = super_global_amount + super_amt;
                                $('#mc_content_1_overall_actual').text(super_global_amount);
                                $('#mc_content_1_overall').show();
                                $('#mc_content').show();
                            }   
                        } 
                    }
                    });
                }
            }
            });


        });
    }

$(document).on('click','.mc_content_1_save', function(){

    var category_id = $(this).parent().siblings('.categ_hdr').find('.category_id').val();
    var desc = $(this).siblings('.mc_content_1_desc').find('.mc_content_1_new_desc').val();
    var amount = $(this).siblings('.mc_content_1_amount').find('.mc_content_1_new_amount').val();
    var dateObject = $(this).siblings(".mc_content_1_date").find('.mc_content_1_new_date').datepicker("getDate");
    var date = $.datepicker.formatDate("yy-mm-dd", dateObject);
    var mode = $(this).siblings('.mc_content_1_notes').find('.mc_content_1_new_mode').find(':selected').val();
    var element = $(this);

    if(desc == '' || amount == '' || amount == '0' || date == '')
    {
        alert("Enter valid values");
    }
    else
    {
        $.ajax({type: "POST",
        url: "updateRecord.php",
        data: {category_id: category_id, desc: desc, amount: amount, date: date, mode: mode},
        success:function(result) 
        { 
            if(!result)
            {
                alert("Something went wrong");
            }
            else
            {
                element.siblings('.mc_content_1_desc').empty();
                element.siblings('.mc_content_1_desc').addClass('mc_content_1_001').html(desc);

                element.siblings('.mc_content_1_amount').empty();
                element.siblings('.mc_content_1_amount').addClass('mc_content_1_001').html(amount);

                element.siblings('.mc_content_1_date').empty();
                element.siblings('.mc_content_1_date').addClass('mc_content_1_001').html(date);

                element.siblings('.mc_content_1_notes').empty();
                element.siblings('.mc_content_1_notes').addClass('mc_content_1_001').html(mode);

                var super_amt = 0;
                element.parent().parent().children('.mc_content_1_row').each(function(){
                    super_amt = super_amt + parseInt($(this).find('.mc_content_1_amount').text());
                });

                element.parent().parent('.categ_section').children('.categ_hdr').find('.categ_hdr_amt_actual').empty().append("<span class='categ_hdr_amt_act_span'>ACTUAL</span>Rs. " + super_amt);
                element.remove();
            }
        }
        });
    }

});
            
$(document).on('click', '.add_tag', function(){
    var html = "<div class='mc_content_1_row'><input class='mc_content_1_uid' type='hidden' value ='new' /><div class='mc_content_1_desc'><input type='text' class='mc_content_1_new mc_content_1_new_desc' placeholder='Description' value='' /></div><div class='mc_content_1_amount'><input type='number' class='mc_content_1_new mc_content_1_new_amount' placeholder='Amount' value='' /></div><div class='mc_content_1_date'><input type='text' name='myinp' class='mc_content_1_new mc_content_1_new_date datepicker' readonly='readonly' placeholder='Date of Transaction' /></div><div class='mc_content_1_notes'><select class='mc_content_1_new mc_content_1_new_mode'><option value='creditcard'>creditcard</option><option value='netbanking'>netbanking</option><option value='upi'>upi</option><option value='cash'>cash</option></select></div><div class='mc_content_1_save'><label>SAVE</label></div></div>";
    $('body').on('focus','.mc_content_1_new_date', function(){
        $(this).datepicker();
    });
    $(this).parent().parent().parent().append(html);
    $(this).parent().siblings('.mc_content_1_row').find('.mc_content_1_new_desc').focus();
});

$(document).on('change','.sup_cat_txn', function(){
    var sup_cat = this.value;
    var uid = $(this).parent().siblings('.mc_content_1_uid').val();
    
    var text = "Are you sure you want to change the Parent Category?";
    if (confirm(text) == true) 
    {
        $.ajax({type: "POST",
        url: "updateSupCat.php",
        data: {uid: uid, sup_cat: sup_cat},
        success:function(result) 
        { 
            if(result === FALSE)
            {
                alert("Something went wrong");
            }
        }
        });
    }
});

</script>
<div id="mc_content">
    <div id="mc_content_1_overall">
        <div id="just_style">
            <div id="mc_c_1_o_a_cont" class="mc_c_1_o_cont">
                <div id="mc_content_1_overall_actual_hdr" class="mc_c_1_o_hdr">ACTUAL</div>
                <div id="mc_content_1_overall_actual" class="mc_c_1_o_val"></div>
            </div>
        </div>
    </div>
    <div id="mc_content_1" class="mc_content_pane">
    </div>
</div>

<?php
include('footer.php');
?>
        

