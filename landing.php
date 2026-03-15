<?php
include('header.php');
include('displayDate.php');
?>
<div class="fixed-top-buttons-glass">
    <a class="glass-btn" href="findTxn.php">Find Transaction</a>
    <a class="glass-btn" href="bankAccountRecon.php">Upload Bank Statement</a>
    <a class="glass-btn" href="bankAccountReconList.php">Reconcile Bank Statement</a>
</div>
<?php
include('displayCategories.php');
?>   

<div class="boxSummaryWrap">

    <div class="boxContainerSection">
        <div class="boxContainerHeaderCont">
            <div class="boxContainerHeader">Statement</div>
        </div>
        <div class="boxContainerContentCont">
            <div class="boxContainerContent"><span class="boxContainerContentLabel">Opening Balance:</span><span class="boxContainterContentValue" id="stmtOpeningBalance"></span></div>
            <div class="boxContainerContent"><span class="boxContainerContentLabel">Closing Balance:</span><span class="boxContainterContentValue" id="stmtClosingBalance"></span></div>
            <div class="boxContainerContent"><span class="boxContainerContentLabel">Amount (By Statement):</span><span class="boxContainterContentValue" id="stmtAmountSpent"></span></div>
            <div class="boxContainerContent"><span class="boxContainerContentLabel">Amount (By Transactions):</span><span class="boxContainterContentValue" id="stmtAmountSpentActual"></span></div>
            <div class="boxContainerContent"><span class="boxContainerContentLabel">Unreconciled Amount:</span><span class="boxContainterContentValue" id="stmtAmountUnreconciled"></span></div>
            <div class="boxContainerContent"><span class="boxContainerContentLabel">Contollable Expenses:</span><span class="boxContainterContentValue" id="mc_controllable"></span></div>
        </div>
    </div>

    <div class="boxContainerSection">
        <div class="boxContainerHeaderCont">
            <div class="boxContainerHeader">Credit Card</div>
        </div>
        <div class="boxContainerContentCont">
            <div class="boxContainerContent"><span class="boxContainerContentLabel">Amount - This Month:</span><span class="boxContainterContentValue" id="ccTxnAmount"></span></div>
            <div class="boxContainerContent"><span class="boxContainerContentLabel">Amount - Next Month:</span><span class="boxContainterContentValue" id="ccTxnAmountNext"></span></div>
        </div>
    </div>

    <div class="boxContainerSection">
        <div class="boxContainerHeaderCont">
            <div class="boxContainerHeader" id="months_count"></div>
        </div>
        <div class="boxContainerContentCont" id="superCatList">
            
        </div>
    </div>

</div>

<div id="mc_content">
    <form id="downloadForm" method="POST" action="downloadTransactionsXls.php" target="_blank">
        <!-- these will be filled by JS before submit -->
        <div id="dl_hidden_fields"></div>
    </form>
    <div id="downloadXlsBtn">Download Excel</div>
    <div id="refreshCategories">Refresh Data</div>

    <div id="mc_content_1" class="mc_content_pane">
    </div>
</div>

<div id="mc_loader"><img src="images/spinner.gif" width="150px" /></div>

<div id="editTransaction">
    <div id='editTxnSupCont'>
        <div id="editTxnClose">[Close]</div>
        <div id="editTxnHdr">EDIT TRANSACTION</div>
        <div id="editTxnCont">
            <input type='hidden' id='editTxnUid' value='' />
            <input type='text' id='editTxnMerchant' class='editTxnFields' value='' />
            <input type='text' id='editTxnAmount' class='editTxnFields' value='' />
            <input type='text' id='editTxnDate' class='datepicker editTxnFields' value='' readonly='readonly' />
            <select id="editTxnCategory">

            </select>
            <div id='editTxnModeCont'>
                <select id='editTxnMode' class='editTxnFields'>
                    <option>creditcard</option>
                    <option>netbanking</option><!-- comment -->
                    <option>upi</option>
                </select>
            </div>

            <input type='button' id='editTxnSubmit' class='editTxnFields' value='SAVE' />
            <div id="editTxnError"></div>
        </div>
    </div>
</div>

<div id="superCatModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <div id="superCatClose" class="modal-close">×</div>
        <h2 id="superCatTitle"></h2>
        <div class="chart-container">
            <canvas id="superCatChart"></canvas>
        </div>
    </div>
</div>


<script>
    
    $(document).ready(function()
    {
        loadYtdAverages();

        $('#c_s_1').css({"background": "rgb(99,99,102)"});
        $('#c_s_1_1').css({"color": "#f5f5f5"}); 

        $(document).on('change','input[name="addValueToCat"]', function(){
            if($(this).is(':checked'))
            {   
                $(this).parent().css({"background":"rgb(142, 142, 147)", "color": "#fff"});
            }
            else
            {
                $(this).parent().css({"background":"rgb(245,245,245)", "color": "rgb(99,99,102)"});
                $('#mc_content_1_overall').hide();              
            }
            populate_details();

        });
        
        $(document).on('change','input[name="mc_f_inp"]', function(){
            if($(this).is(':checked'))
            {
                $(this).parent().css({"background":"rgba(0,128,128,0.7)", "color": "#fff"});
            }
            else
            {
                $(this).parent().css({"background":"rgb(245,245,245)", "color": "rgba(0,128,128,0.7)"});
                $('#mc_content_1_overall').hide();              
            }
            populate_details();
            
        });
        
    });

    let YTD_SUPERCATS = [];
    let YTD_CATEGORIES = [];
    let YTD_CATEGORIES_GROUPED = {};

    function loadYtdAverages() 
    {
        $.ajax({
            url: "api/getYtdCategoryAverages.php",
            type: "POST",
            dataType: "json",
            success: function(res) {
                YTD_SUPERCATS = res.super_categories;
                YTD_CATEGORIES_GROUPED = res.categories; 
                populateSuperCategories(res);
            }
        });
    }

    function populateSuperCategories(jsonData) 
    {
        // The container where rows will be inserted
        let container = $("#superCatList");

        // Clear previous rows
        container.empty();

        // Loop through super categories
        $('#months_count').text("YTD Metrics - " + (jsonData.months_count) + " Months");
        jsonData.super_categories.forEach(function(item) 
        {
            avgFormatted = " Rs. " + formatINR(item.avg);

            // Build the row
            let row = `
               <div class="boxContainerContent" 
                 data-supcat-id="${item.sup_cat_id}"
                 data-supcat-desc="${item.sup_cat_desc}">
                    <span class="boxContainerContentLabel sup_categ_clickable">${item.sup_cat_desc}</span>
                    <span class="boxContainterContentValue">${avgFormatted}</span>
                </div>
            `;

            // Append to container
            container.append(row);
        });
    }
    
    $( function(){
        $( ".datepicker" ).datepicker({
          changeMonth: true,
          changeYear: true
        });
    });
    
    $('#refreshCategories').click(function(){
        populate_details();
    });
            
    function populate_details()
    {
        $('.overlay').show();
        $('#mc_loader').center();
        $('#mc_loader').show();
        
        var $boxes = $('input[name="addValueToCat"]:checked');
        var $filters = $('input[name="mc_f_inp"]:checked');

        var i = 0;
        var j = 0;
        var f = [""];
        var boxes = [""];
        
        $filters.each(function(){
            f[i] = $(this).val();
            i = i+1;
        });
        
        $boxes.each(function(){
            boxes[j] = $(this).val();
            j = j+1;
        });
        
        var chosen_month = $('#mc_date_inner_1_inp').val().trim();

        $.ajax({
            type: "POST",
            url: "getTransactions.php",
            data: {categories: boxes, chosen_month: chosen_month, filter: f},
            dataType: "json",  // ✅ tell jQuery to parse JSON
            success:function(response) 
            { 
                $('.overlay').hide();
                $('#mc_loader').hide();
                        
                if(!response)
                {
                    alert("Something went wrong");
                }
                else
                {
                    $('#mc_controllable').empty();
                    $('#mc_controllable').append(" Rs. " + formatINR(response.key_expenses));
                    $('#stmtAmountSpentActual').text("Rs. " + formatINR(response.total_monthly_spend));
                    var a = parseFloat(String(response.total_monthly_spend).replace(/[^0-9.]/g, ''));
                    var b = parseFloat($('#stmtAmountSpent').text().replace(/[^0-9.]/g, ''));
                    $('#stmtAmountUnreconciled').text("Rs. " + formatINR(a-b));
                    $('#ccTxnAmount').text("Rs. " + formatINR(response.super_credit_card_amt));
                    $('#ccTxnAmountNext').text("Rs. " + formatINR(response.unbilled_next));

                    var $content = $('#mc_content_1');
                    $content.empty();  // clear previous

                    var allSupCats = response.allSupCats || [];
                                   
                    response['data'].forEach(function(SupcatObj)
                    {
                        var supCatHtml = renderSupCat(SupcatObj, allSupCats);
                        $content.append(supCatHtml);
                    });

                }
                $content.fadeIn();
            }
        });

    }

function renderSupCat(supCatObj, allSupCats) 
{
    var categoryHtml = renderCategoryHtml(supCatObj.all_cat_txn_data, allSupCats);
    var sup_cat_amt = formatINR(supCatObj.sup_cat_amt);
    return `
    <div class='sup_categ_cont'>
        <div class='s_c_s_0'>
            <div class='sup_categ_section'>${supCatObj.sup_cat_desc}</div>
            <div class='s_c_s_amt'>Rs. ${sup_cat_amt}</div>
        </div>

        <div class='s_c_s_data'>
            ${categoryHtml}
        </div>
    </div>`;
}

function renderCategoryHtml(all_cat_txn_data, allSupCats) 
{
    var categoryHtml = '';

    // catArray is expected to be an array of category objects
    all_cat_txn_data.forEach(function (category) {
        var catId       = category.cat_id;
        var description = category.cat_desc;
        var catAmount   = Number(category.cat_amt) || 0;
        var categBudget = Number(category.cat_budget) || 0;
        var innerHtml   = renderCategoryInnerHtml(category.cat_txn_data, allSupCats);

        // Format like PHP number_format(..., 2, '.', '')
        var catAmtFormatted        = catAmount.toFixed(2);
        var monthlyBudgetFormatted = (categBudget / 12).toFixed(2);

        categoryHtml += `
        <div class="categ_section">
        <div class="categ_hdr">
            <input type="hidden" class="category_id" value="${catId}" />
            <div class="c_h_l_0">
            <div class="categ_hdr_l">${description}</div>
            <div class="categ_hdr_amt">
                <div class="categ_hdr_amt_actual">Rs. ${catAmtFormatted}</div>
                <div class="categ_hdr_budg">Rs. ${monthlyBudgetFormatted}</div>
            </div>
            </div>
            <div class="categ_hdr_r">
            <img src="images/add.svg" class="add_tag" />
            </div>
            <div class="cc_data_ctr_div">
            <img class="cc_data_ctrl_img"  src="images/expand.png"   width="15px" />
            <img class="cc_data_ctrl_img2" src="images/collapse.png" width="15px" />
            </div>
        </div>

        <div class="mc_content_1_row_cont">
            ${innerHtml}
        </div>
        </div>`;
    });

    return categoryHtml;
}

function renderCategoryInnerHtml(cat_txn_data, allSupCats) {
    var catInnerHtml = '';

    cat_txn_data.forEach(function (catData) {

        var uid            = catData.uid;
        var reconciledFlag = catData.reconciled_flag;
        var merchant_name  = catData.merchant_name;
        var amount         = catData.amount;
        var dateFullFormat = catData.dateFullFormat;
        var dateHalfFormat = catData.dateHalfFormat;
        var mode           = catData.mode;
        var supCatId       = catData.supCat[0]; // current selection
        var url            = catData.url;

        var supCatHtm = renderSupCatSelect(allSupCats, supCatId);

        var rowClass = (reconciledFlag == 1)
        ? "mc_content_1_row_green"
        : "mc_content_1_row_red";

        catInnerHtml += `
<div class="mc_content_1_row_wrapper">
    <a class="txnRowLink" href="${url}">
        <div class="mc_content_1_row ${rowClass}">
        <input class="mc_content_1_uid" type="hidden" value="${uid}" />
        <div class="mc_content_1_desc mc_content_1_001">${merchant_name}</div>
        <div class="mc_content_1_amount mc_content_1_001">${amount}</div>

        <input type="hidden" class="mc_content_1_date_real" value="${dateFullFormat}" />
        <div class="mc_content_1_date mc_content_1_001">${dateHalfFormat}</div>

        <div class="mc_content_1_notes mc_content_1_001">${mode}</div>
        </div>
    </a>
    <div class="mc_content_1_sup_cat mc_content_1_001">
    ${supCatHtm}
    </div>
 
    <div class="mc_content_1_actions">
        <img src="images/spinner.gif" width="25px" class="mc_content_1_loader" />
        <img src="images/edit-button.png" width="18px" class="mc_content_1_item_edit" />
        <img src="images/delete.png" width="18px" class="mc_content_1_item_delete" />
    </div>
</div>`;
    });

    return catInnerHtml;
}

function renderSupCatSelect(allSupCats, selectedId) 
{
    var optionsHtml = allSupCats.map(function (sc) {
        var selectedAttr = (String(sc.sup_cat_id) === String(selectedId)) ? ' selected' : '';
        return `<option value="${sc.sup_cat_id}"${selectedAttr}>${sc.sup_cat_desc}</option>`;
    }).join('');

    return `<select class="mc_sup_cat_dd">${optionsHtml}</select>`;
}

$(document).on("click", ".sup_categ_clickable", function () 
{
    const supcatId = $(this).parent().data("supcat-id");
    const desc     = $(this).parent().data("supcat-desc");

    // Retrieve the correct category averages
    const catData = YTD_CATEGORIES_GROUPED[supcatId] || [];

    showSuperCategoryPopup(desc, catData);
});


let superCatChart = null;   // Chart.js instance

function showSuperCategoryPopup(desc, catData) 
{

    // Handle cases where no categories exist
    if (!catData || catData.length === 0) 
    {
        alert("No category data available for " + desc);
        return;
    }

    // Extract labels & averages
    const labels = catData.map(item => item.cat_desc);
    const values = catData.map(item => item.avg);

    // Set title
    $("#superCatTitle").text(desc + " - Monthly Category Averages");

    // Show popup
   $("#superCatModal").css("display", "flex").hide().fadeIn(150);

    // Destroy previous chart if exists
    if (superCatChart) {
        superCatChart.destroy();
    }

    // Canvas context
    const ctx = document.getElementById("superCatChart").getContext("2d");

    // Draw chart
    superCatChart = new Chart(ctx, {
        type: "bar",
        data: {
            labels: labels,
            datasets: [{
                label: "Avg Monthly Spend",
                data: values,
                backgroundColor: "rgba(54, 162, 235, 0.7)",
                borderColor: "rgba(54, 162, 235, 1)",
                borderWidth: 1,
                borderRadius: 6,
            }]
        },
        options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        datalabels: {
                            anchor: 'end',
                            align: 'right',
                            color: '#000',
                            formatter: (value) => formatINR(value),
                            font: { weight: 'bold' }
                        },
                        legend: { display: false }
                    },
                    scales: {
                        x: { beginAtZero: true }
                    }
                }
    });
}

$(document).on("click", "#superCatClose", function () {
    $("#superCatModal").fadeOut(150);
});

$(document).on("click", "#superCatModal", function (e) {
    if (e.target.id === "superCatModal") {
        $("#superCatModal").fadeOut(150);
    }
});

    
$(document).on("keyup", ".mc_content_1_new_desc", function(){
    
    var param = $(this).val();
    var category_id = 0;
    
    category_id = $(this).parent().parent().parent().siblings('.categ_hdr').find('.category_id').val();
    
    if(param != '')
    {
        $.ajax({type: "POST",
        dataType: "json",
        url: "getMerchantList.php",
        data: {param: param, category_id: category_id},
        success:function(result) 
        { 
            $(".mc_content_1_suggest").html("");
            $(".mc_content_1_suggest").hide();
            var searchResultHtml = "";
            if(result == "")
            {
                $(".mc_content_1_suggest").text('No matches found');
                //$(".loader").css("display","none");
            }
            else
            {
                if(result[0] == "too_many")
                {
                   $(".mc_content_1_suggest").text('Too many results. Narrow search');

                }
                else
                {
                    $.map(result,function(m){
                    searchResultHtml += suggestion_box(m);
                    $(".mc_content_1_suggest").html(searchResultHtml);    
                    });                    
                }

            }

            $(".mc_content_1_suggest").css({"display":"flex"}); 
            $('.mc_content_1_suggest').fadeIn();
        }
        });
    }
    else
    {
        $('.mc_content_1_suggest').fadeOut();
        $('.mc_content_1_suggest').html('');
    }
    
    function suggestion_box(m)
    {
        //$(".loader").css("display","none");     
        //var div1 = "<tr><td class='opt'>"+ m.fund_name +"<input type='hidden' value='" + m.scheme_code + "' /></td></tr>";
        var div1 = "<div class='a_m_d_f_row'><input class='a_m_d_f_merch_id' type='hidden' value='" + m.merchant_id + "' /><div class='a_m_d_f_merch'>" + m.merchant_desc + "</div><div class='a_m_d_f_cat'>" + m.category_desc + "</div></div>";
        return div1;

    }
    
});

$(document).on('click','.a_m_d_f_row', function(){
    var merchant = $(this).children('.a_m_d_f_merch').text();
    $(this).parent().siblings('.mc_content_1_new_desc').val(merchant);
    $(this).parent().parent().siblings('.mc_content_1_amount').children('.mc_content_1_new_amount').focus();
    $(this).parent().html('').fadeOut();
});

//anurag... need to call this from ADD in creditcardRecon.php
$(document).on('click','.mc_content_1_save', function(){

    var sup_categ_id = $(this).parent().parent().parent().parent().parent().children('.sup_categ_id').val();
    var category_id = $(this).parent().parent().siblings('.categ_hdr').find('.category_id').val();
    var desc = $(this).siblings('.mc_content_1_desc').find('.mc_content_1_new_desc').val();
    var amount = $(this).siblings('.mc_content_1_amount').find('.mc_content_1_new_amount').val();
    var dateObject = $(this).siblings(".mc_content_1_date").find('.mc_content_1_new_date').datepicker("getDate");
    var date = $.datepicker.formatDate("yy-mm-dd", dateObject);
    var mode = $(this).siblings('.mc_content_1_notes').find('.mc_content_1_new_mode').find(':selected').val();
    
    var element = $(this).parent().parent().parent().parent();
    var sub_element = $(this).parent().parent().parent();
    var click_parent = $(this).parent().parent().parent().find('.cc_data_ctrl_img');
    
    var chosen_month = $('#mc_date_inner_1_inp').val();

    if(desc == '' || amount == '' || amount == '0' || date == '')
    {
        alert("Enter valid values");
    }
    
    else
    {
        var $filters = $('input[name="mc_f_inp"]:checked');
        
        var i = 0;
        var f = [""];
        
        $filters.each(function(){
            f[i] = $(this).val();
            i = i+1;
        });
        
        $.ajax({type: "POST",
        url: "updateRecord.php",
        dataType: "json",
        data: {sup_categ_id: sup_categ_id, category_id: category_id, desc: desc, amount: amount, date: date, mode: mode, filter: f, chosen_month: chosen_month, called_from: "landing"},
        success:function(result) 
        { 
            if(result === true)
            {
                alert("Record Successfully Added!");
            }
            else
            {
                alert("Something went wrong");
            }
        }
        });
    }

});
            
$(document).on('click', '.add_tag', function(){
    var html = "<div class='mc_content_1_row_new mc_content_1_row'><input class='mc_content_1_uid' type='hidden' value ='new' /><div class='mc_content_1_desc'><input type='text' class='mc_content_1_new mc_content_1_new_desc' placeholder='Description' value='' /><div class='mc_content_1_suggest'></div></div><div class='mc_content_1_amount'><input type='number' class='mc_content_1_new mc_content_1_new_amount' placeholder='Amount' value='' /></div><div class='mc_content_1_date'><input type='text' name='myinp' class='mc_content_1_new mc_content_1_new_date datepicker' readonly='readonly' placeholder='Date of Transaction' /></div><div class='mc_content_1_notes'><select class='mc_content_1_new mc_content_1_new_mode'><option value='creditcard'>creditcard</option><option value='netbanking'>netbanking</option><option value='upi'>upi</option><option value='cash'>cash</option></select></div><div class='mc_content_1_save'><label>SAVE</label></div></div>";
    $('body').on('focus','.mc_content_1_new_date', function(){
        $(this).datepicker();
    });
    $(this).parent().parent().parent().children('.mc_content_1_row_cont').append(html);
    $(this).parent().parent().siblings('.mc_content_1_row_cont').children('.mc_content_1_row_new').find('.mc_content_1_new_desc').focus();
});

$(document).on('change','.mc_sup_cat_dd', function(){
    var sup_cat = this.value;
    var uid = $(this).parent().siblings('.txnRowLink').children('.mc_content_1_row').find('.mc_content_1_uid').val();
    
    var text = "Are you sure you want to change the Parent Category?";
    if (confirm(text) == true) 
    {
        $.ajax({type: "POST",
        dataType: "json",
        url: "updateSupCat.php",
        data: {uid: uid, sup_cat: sup_cat},
        success:function(result) 
        { 
            if(result === false)
            {
                alert("Something went wrong");
            }
            else
            {
                alert("Super Category Successfully Updated!");
            }
        }
        });
    }
});

$(document).on('click', '.cc_data_ctrl_img',function(){
    expand($(this));
});

$(document).on('click', '.cc_data_ctrl_img2',function(){
    collapse($(this));
});

function expand(elmnt)
{
    elmnt.parent().parent().parent('.categ_section').css({"width": "100%"});
    elmnt.parent().parent().parent('.categ_section').css({"max-width": "100%"});
    elmnt.parent().siblings('.c_h_l_0').css({"flex-direction":"row"});
    elmnt.parent().siblings('.c_h_l_0').find('.categ_hdr_amt').css({"margin-top":"0px"});
    elmnt.parent().siblings('.categ_hdr_r').show();
    elmnt.parent().parent().siblings('.mc_content_1_row_cont').show();
    elmnt.siblings('.cc_data_ctrl_img2').show();
    elmnt.hide();
    var element = elmnt.parent().parent().parent('.categ_section').clone();
    elmnt.parent().parent().parent().parent('.s_c_s_data').prepend(element);
    
    $('html, body').animate({
        scrollTop: elmnt.parent().parent().parent().parent('.s_c_s_data').offset().top - 20 //#DIV_ID is an example. Use the id of your destination on the page
    }, 'slow');
    
    elmnt.parent().parent().parent('.categ_section').remove();  
}

function collapse(element)
{
    element.parent().parent().parent('.categ_section').css({"width": "35%"});
    element.parent().parent().parent('.categ_section').css({"max-width": "200px"});
    element.parent().siblings('.c_h_l_0').css({"flex-direction":"column"});
    element.parent().siblings('.c_h_l_0').find('.categ_hdr_amt').css({"margin-top":"10px"});
    element.parent().siblings('.categ_hdr_r').hide();
    element.parent().parent().siblings('.mc_content_1_row_cont').hide();
    element.siblings('.cc_data_ctrl_img').fadeIn();
    element.hide();
}


$(document).on('click', '.mc_content_1_item_edit', function () {

    // Extract txn-level values
    var row     = $(this).closest('.mc_content_1_row_wrapper');
    var txnRow  = row.find('.txnRowLink .mc_content_1_row');

    var uid     = txnRow.find('.mc_content_1_uid').val();
    var merchant= $.trim(txnRow.find('.mc_content_1_desc').text());
    var amount  = $.trim(txnRow.find('.mc_content_1_amount').text());
    var txnDate = txnRow.find('.mc_content_1_date_real').val();
    var txnMode = $.trim(txnRow.find('.mc_content_1_notes').text());

    // Fetch current category ID for dropdown selection
    var catId = row.closest('.categ_section')
                   .find('.categ_hdr .category_id')
                   .val();

    $('.overlay').show();

    // AJAX #1 → Fetch all categories
    $.ajax({
        url: "api/getAllCategoriesAjax.php",
        type: "POST",
        dataType: "json",
        success: function (allCats) {

            // Populate dropdown
            var $select = $('#editTxnCategory');
            $select.empty();

            $.each(allCats, function (i, cat) {
                var selected = (cat.cat_id == catId) ? "selected" : "";
                $select.append(
                    `<option value="${cat.cat_id}" ${selected}>${cat.cat_desc}</option>`
                );
            });

            // Now populate the other fields
            $('#editTxnUid').val(uid);
            $('#editTxnMerchant').val(merchant);
            $('#editTxnAmount').val(amount);
            $('#editTxnDate').val(txnDate);
            $('#editTxnMode').val(txnMode);

            // Finally show modal
            $('#editTransaction').center();
            $('#editTransaction').fadeIn();
        },
        error: function () {
            alert("Could not fetch categories.");
            $('.overlay').hide();
        }
    });
});


$(document).on('click', '#editTxnSubmit', function(){
    
    var uid = $(this).siblings('#editTxnUid').val();
    var merchant = $(this).siblings('#editTxnMerchant').val();
    var amount = $(this).siblings('#editTxnAmount').val();
    var date = $(this).siblings('#editTxnDate').val();
    var mode = $(this).siblings('#editTxnModeCont').children('#editTxnMode').val();
    var cat_id = $(this).siblings('#editTxnCategory').val();
    
    $.ajax({type: "POST",
        url: "editTransaction.php",
        data: {uid: uid, merchant: merchant, amount: amount, date: date, mode: mode, cat_id: cat_id},
        success:function(result) 
        { 
            var response = JSON.parse(jQuery.trim(result));

            if(response[0] === 'not_ok')
            {
                if(response[1] != '')
                {
                    $('#editTxnError').text(response[1]);
                }
            }
            else
            {    
                $('.overlay').hide();
                $('#editTransaction').hide();
                location.reload();
            }
        }
    });    
});

$(document).on('click', '#editTxnClose',function(){
    $('.overlay').hide();
    $(this).parent().parent().hide();
});

$(document).on('click', '.mc_content_1_item_delete',function()
{
   
    if(confirm("Are you sure you want to delete this record?"))
    {
        var uid = '';
        
        var deleteBtn = $(this);  // store reference
        var wrapper   = deleteBtn.closest('.mc_content_1_row_wrapper');
        var loader    = deleteBtn.siblings('.mc_content_1_loader');
        var editBtn   = deleteBtn.siblings('.mc_content_1_item_edit');

        var uid = wrapper.find('.mc_content_1_uid').val();

        loader.show();
        deleteBtn.hide();
        editBtn.hide();

        //uid = $(this).parent().siblings('.txnRowLink').children('.mc_content_1_row').find('.mc_content_1_uid').val();
        //var element = $(this);

        //element.siblings('.mc_content_1_loader').show();
        //element.hide();
        //element.siblings('.mc_content_1_item_edit').hide();

        $.ajax({
            type: "POST",
            url: "deleteTransaction.php",
            data: { uid: uid },
            success: function (result) {

                if ($.trim(result) === 'ok') {
                    // Nice fade-out effect
                    wrapper.fadeOut(200, function(){
                        wrapper.remove();
                    });
                } else {
                    alert("Delete failed");
                    // Restore UI controls on error
                    loader.hide();
                    deleteBtn.show();
                    editBtn.show();
                }
            },
            error: function () {
                alert("Server error");
                loader.hide();
                deleteBtn.show();
                editBtn.show();
            }
        });
    }
    
});

$('#downloadXlsBtn').on('click', function () 
{
    var $boxes   = $('input[name="addValueToCat"]:checked');
    var $filters = $('input[name="mc_f_inp"]:checked');
    var chosen_month = $('#mc_date_inner_1_inp').val().trim();

    var $hiddenContainer = $('#dl_hidden_fields');
    $hiddenContainer.empty();

    // categories[]
    $boxes.each(function () {
        $('<input>', {
            type: 'hidden',
            name: 'categories[]',
            value: $(this).val()
        }).appendTo($hiddenContainer);
    });

    // filter[]
    $filters.each(function () {
        $('<input>', {
            type: 'hidden',
            name: 'filter[]',
            value: $(this).val()
        }).appendTo($hiddenContainer);
    });

    // chosen_month
    $('<input>', {
        type: 'hidden',
        name: 'chosen_month',
        value: chosen_month
    }).appendTo($hiddenContainer);

    $('#downloadForm').submit();  // will open/save Excel file
});

</script>

<?php
include('footer.php');
?>
        

