<div id="mc_date">
    <div id="mc_date_left" class="dateMovement">
        <
    </div>
    <div id="mc_date_inner">
        <div id="mc_date_inner_1">
        </div>
        <input type='hidden' id='mc_date_inner_1_inp' value='' />
        <input type="hidden" value="" id="mc_date_current_date" />
    </div>
    <div id="mc_date_right" class="dateMovement">
        >
    </div>
</div>

<script>
    
    $(document).ready(function(){ 
        
        $.ajax({type: "POST",
        url: "dateProcess.php",
        data: {state: "at_present", curr_display_date:''},
        success:function(result) 
        { 
            if(!result)
            {
                alert("Something went wrong");
            }
            else
            {
                $('#mc_date_inner_1_inp').val(result);
                $(document).trigger('dateReady');
                $('#statementMonth').text(result);
                getAndPopulateBalances(result);
                var mm = result.substring(5,7);
                var yy = result.substring(2,4);
                var mm = getMonth(mm);
                $('#mc_date_inner_1').text(mm + " " + yy);
            }
        }
        });
        

    });

    $('#mc_cont_1_btn').click(function(){
        var hideoptions = {  "direction" : "left",  "mode" : "hide"};
        var showoptions = {"direction" : "right","mode" : "show"};
        $( "#mc_content_1" ).effect( "slide", hideoptions, 1000);
        $( "#mc_content_2" ).effect( "slide", showoptions, 1000);
    });

    $('#mc_cont_2_btn').click(function(){
        var hideoptions = {  "direction" : "left",  "mode" : "hide"};
        var showoptions = {"direction" : "right","mode" : "show"};
        $( "#mc_content_2" ).effect( "slide", hideoptions, 1000);
        $( "#mc_content_3" ).effect( "slide", showoptions, 1000);
    });
    
    $('#mc_date_left').click(function()
    {
        curr_display_date = $('#mc_date_inner_1_inp').val().trim();

        $.ajax({type: "POST",
        url: "dateProcess.php",
        data: {state: "minus_one", curr_display_date:curr_display_date},
        success:function(result) 
        { 
            if(!result)
            {
                alert("Something went wrong");
            }
            else
            {
                var showoptions = {"direction" : "left","mode" : "show"};
                var hideoptions = {  "direction" : "right",  "mode" : "hide"};
                $('#mc_date_inner_1').effect( "slide", hideoptions, 300);
                setTimeout(
                    function() 
                    {
                        $('#mc_date_inner_1_inp').val(result);
                        $(document).trigger('dateReady');
                        $('#statementMonth').text(result);
                        getAndPopulateBalances(result);
                        var mm = result.substring(5,7);
                        var yy = result.substring(2,4);
                        var mm = getMonth(mm);
                        $('#mc_date_inner_1').text(mm + " " + yy);

                        $('#mc_date_inner_1').effect( "slide", showoptions, 300);
                        $('input[name="addValueToCat"]').parent().css({"background":"#f5f5f5", "color": "rgb(142, 142, 147)"});
                        $('#mc_content_1_overall_budget').text('');
                        $('#mc_content_1_overall_actual').text('');
                        $('#selectall_inp').val('0');
                        $("#selectall_a").text('Select All');
                        $('#selectall_a').trigger('click');
                    }, 150);
            }
        }
        });
    });

    $('#mc_date_right').click(function()
    {

        curr_display_date = $('#mc_date_inner_1_inp').val().trim();

        $.ajax({type: "POST",
        url: "dateProcess.php",
        data: {state: "plus_one", curr_display_date:curr_display_date},
        success:function(result)
        { 
            if(!result)
            {
                alert("Something went wrong");
            }
            else
            {
                var showoptions = {"direction" : "right","mode" : "show"};
                var hideoptions = {  "direction" : "left",  "mode" : "hide"};
                $('#mc_date_inner_1').effect( "slide", hideoptions, 300);
                setTimeout(
                    function() 
                    {
                        $('#mc_date_inner_1_inp').val(result);
                        $(document).trigger('dateReady');
                        $('#statementMonth').text(result);
                        getAndPopulateBalances(result);
                        var mm = result.substring(5,7);
                        var yy = result.substring(2,4);
                        var mm = getMonth(mm);
                        $('#mc_date_inner_1').text(mm + " " + yy);
                        
                        $('#mc_date_inner_1').effect( "slide", showoptions, 300);
                        $('#mc_content_1_overall_budget').text('');
                        $('#mc_content_1_overall_actual').text('');
                        $('#selectall_a').trigger('click');
                    }, 150);
            }
        }
        });
    });
    
    function getMonth(mm)
    {
        var mWord;
        switch(mm)
        {
            case('01'):
            {
                mWord = "Jan";
                break;
            }
            
            case('02'):
            {
                mWord = "Feb";
                break;
            }            
            
            case('03'):
            {
                mWord = "Mar";
                break;
            }
            
            case('04'):
            {
                mWord = "Apr";
                break;
            }
            
            case('05'):
            {
                mWord = "May";
                break;
            }
            
            case('06'):
            {
                mWord = "Jun";
                break;
            }
            
            case('07'):
            {
                mWord = "Jul";
                break;
            }
            case('08'):
            {
                mWord = "Aug";
                break;
            }
            case('09'):
            {
                mWord = "Sep";
                break;
            }
            case('10'):
            {
                mWord = "Oct";
                break;
            }
            case('11'):
            {
                mWord = "Nov";
                break;
            }
            case('12'):
            {
                mWord = "Dec";
                break;
            }
            
            
        }
        return mWord;
    }

    function getAndPopulateBalances(date)
    {   
        date = jQuery.trim(date);

        $.ajax({
            url: 'api/getBalances.php',
            method: 'POST',
            dataType: 'json',
            data: { statement_date: date }, // pass your chosen date here
            success: function(response) {
                if (response.success) 
                {
                    console.log('Opening Balance:', response.opening_balance);
                    console.log('Closing Balance:', response.closing_balance);
                    // e.g. update UI:
                    $('#stmtOpeningBalance').text(response.opening_balance);
                    $('#stmtClosingBalance').text(response.closing_balance);
                    $('#stmtAmountSpent').text(response.amount_spent);
                } 
                else 
                {
                    console.error('Error:', response.message);
                    alert("Statement Dates Error: " + response.message);
                    $('#stmtOpeningBalance').text("Error");
                    $('#stmtClosingBalance').text("Error");
                    $('#stmtAmountSpent').text("Error");
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Unable to fetch balances. Please try again later.');
            }
        });
    }
    
</script>
