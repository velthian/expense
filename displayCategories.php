<?php
    include_once('class/categories.php');
    $obj = new categories();

    $loginid = $_SESSION['loginid'];
    $category_list = array();
    $category_list = $obj->getCategory($loginid);      
?>
<div id="mc_categories_add">
    <div id="mc_categories_add_close_cont"><span id="mc_categories_add_close">CLOSE</span></div>
    <div id="mc_categories_add_01">Add a category</div>
    <input type="text" id="mc_categories_add_text" />
    <div class="action_div"><button id="mc_categories_add_btn">SUBMIT</button></div>
</div>
<div id="mc_categories_delete">
    <div id="mc_categories_delete_close_cont"><span id="mc_categories_delete_close">CLOSE</span></div>
    <div id="mc_categories_delete_01">Delete a category</div>
    <div id="mc_categories_delete_lbl_cont">
        <?php
            $j = 0;

            foreach($category_list as $cat)
            {
                $category_description = array();
                $category_decription = $obj->getCategoryDescription($cat, $loginid);

                if($j == '0')
                {
                    echo("<label><input type='radio' name='deleteCat' value='" . $cat . "' checked />" . $category_decription['description'] ."</label>");
                }
                else
                {
                    echo("<label><input type='radio' name='deleteCat' value='" . $cat . "' />" . $category_decription['description'] . "</label>");
                }

                $j++;
            }

            ?>
    </div>
    <div class="action_div"><button id="mc_categories_delete_btn">SUBMIT</button></div>
</div>

<div id="mc_filters" class="boxContainer100wide">
    <div class="boxContainerHeader">Filters</div>
    <div id="mc_f_2">
        <label class="glass-btn mc_f_2_label"><input class="mc_f_1" value="netbanking" type="checkbox" name="mc_f_inp" checked />netbanking</label>
        <label class="glass-btn mc_f_2_label"><input class="mc_f_1" value="upi" type="checkbox" name="mc_f_inp" checked />upi</label>
        <label class="glass-btn mc_f_2_label"><input class="mc_f_1" value="creditcard" type="checkbox" name="mc_f_inp" checked />creditcard</label>
    </div>
</div>

<div id="mc_categories" class="boxContainer100wide">
    
    <div>
        <div class="boxContainerHeader">Expense Categories</div>
        <div id="mc_categories_display_list">
        <?php
            foreach($category_list as $cat)
            {
                $cat_data = array();
                $cat_data = $obj->getCategoryDescription($cat, $loginid);

                echo("<label class='glass-btn mc_f_2_label'><input type='checkbox' name='addValueToCat' value='" . $cat . "' />" . $cat_data['description'] . "</label>");
            }
        ?>
        </div>
        <div id="mc_categories_actions">
            <div id="mc_categories_act_selectall">
                <input type="hidden" id="selectall_inp" value="0" />
                <a id="selectall_a" href="#">Select All</a>
            </div>
            <div>
                <span id='mc_categories_display_add'><img src='images/add.svg' width='25px' /></span>
                <span id='mc_categories_display_delete'><img src='images/delete.png' width='25px' /></span>
            </div>
        </div>
    </div>

</div>

<?php
    include_once('class/categories.php');
    $obj= new categories();

    $unassignedCatFlag = '';
    $unassignedCatFlag = $obj->checkUnassignedCategories(); //TRUE means there are unassigned categories
    
    if($unassignedCatFlag == '1')
    {
        //echo("<div id='mc_categries_display_unassigned'><span id='mc_c_d_u_01'><img src='images/red-flag.png' width='15px' /></span><span id='mc_c_d_u_02'>There are unassigned categories. Click <a href='assignCategory.php'>here</a> to assign them to merchants.</span></div>");
        ?>
        <div class="alert-container">
            <div class="alert-message">
                <div id="warningMessage" style="display:flex; align-items:center; color:#E67E22;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" style="margin-right:6px;">
                        <path d="M8.982 1.566a1.13 1.13 0 0 0-1.964 0L.165 13.233c-.457.778.091 1.767.982 1.767h13.707c.89 0 1.438-.99.982-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1-2.002 0 1 1 0 0 1 2.002 0z"/>
                    </svg>
                    <span style="color: rgb(99,99,102)">There are unassigned categories</span>
                </div>
                <a href='assignCategory.php' class="glass-btn mc_f_2_label">Assign to Merchants</a>
            </div>
        </div>
        <?php
    }

?>

<script>
    $(document).on('dateReady', function() {
        selectAllAndTriggerClick(); // this will indirectly call populate_details()
    });

    function selectAllAndTriggerClick()
    {
        $('input[name="addValueToCat"]').prop('checked', true);
        $('#mc_categories_act_selectall').trigger('click');
    }


    $('#mc_categories_act_selectall').click(function(){
        var flag = $('#selectall_inp').val();
        if(flag == '0')
        {
            $('input[name="addValueToCat"]').prop('checked', false);
            $('input[name="addValueToCat"]').prop('checked', true);
            $('input[name="addValueToCat"]').parent().css({"background":"rgb(142, 142, 147)", "color": "#fff"});
            populate_details();
            $('#selectall_inp').val('1');
            $("#selectall_a").text('Deselect All');
        }
        else
        {
            $('input[name="addValueToCat"]').prop('checked', false);
            $('input[name="addValueToCat"]').parent().css({"background":"rgb(199,199,204)", "color": "rgb(142, 142, 147)"});
            $('#mc_content_1_overall_budget').text('');
            $('#mc_content_1_overall_actual').text('');
            $('#mc_content_1').empty();
            $('#selectall_inp').val('0');
            $("#selectall_a").text('Select All');
        }
        
    });
    
    $('#mc_categories_display_add').click(function(){
        $('#mc_categories_delete').hide();
        $('.overlay').show();
        $('#mc_categories_add').fadeIn();
        $('#mc_categories_add').centreInBody();
        $('#mc_categories_add_text').focus();
    });

    $("#mc_categories_add_close").click(function(){
        $("#mc_categories_add").hide();
        $(".overlay").hide();
    });

    $("#mc_categories_delete_close").click(function(){
        $("#mc_categories_delete").hide();
        $(".overlay").hide();
    });

    $('#mc_categories_display_delete').click(function(){
        $('#mc_categories_add').hide();
        $('.overlay').show();
        $('#mc_categories_delete').fadeIn();
        $('#mc_categories_delete').centreInBody();
        
    });

    $('#mc_categories_add_btn').click(function(){

        var category_name = $('#mc_categories_add_text').val();

        if(category_name == '')
        {
            alert("Please enter a valid category");
            $('#mc_categories_add_text').focus();
        }
        else
        {     
            $.ajax({type: "POST",
            url: "addCategory.php",
            data: {category_name: category_name},
            success:function(result) 
            { 
                if(!result || result == 1)
                {
                    alert("Something went wrong");
                }
                else
                {
                    if(result == 9)
                    {
                        alert('Category already exists');
                        $('#mc_categories_add_text').focus();
                    }
                    else
                    {
                        location.reload();
                    }
                }
            }
            });
        }
    });

    $('#mc_categories_delete_btn').click(function(){
        if (!$("input[name='deleteCat']:checked").val()) 
        {
           alert('Nothing is checked!');
            return false;
        }
        else 
        {
            var category_id = $("input[name='deleteCat']:checked").val();

            $.ajax({type: "POST",
                url: "deleteCategory.php",
                data: {category_id: category_id},
                success:function(result) 
                { 
                    if(!result || result == 1)
                    {
                        alert("Something went wrong");
                    }
                    else
                    {
                        if(result == 9)
                        {
                            alert('Category not empty');
                        }
                        else
                        {
                            location.reload();
                        }
                    }
                }
            });
        }
    });

</script>