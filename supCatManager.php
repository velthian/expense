<?php
include('header.php');

include_once('class/categories.php');
$obj = new categories();

$loginid = $_SESSION['loginid'];

$data = $obj->getAllSuperCategories($loginid);
?>
<div id="s_c_cont">
    <div id ="s_c_hdr">
        <span class="title">Manage Super Categories</span>
        <img src="images/add.svg" width="20px" class="s_c_add" />
    </div>
    <div id="s_c_dat">
        <div id="s_c_dat_hdr">
            <div id="s_c_d_h">Super Category</div>
            <div id="s_c_d_h_m">Monthly Tracking</div><!-- comment -->
            <div id="s_c_d_h_a">Actions</div>
        </div>
        <div id="s_c_row_data">
<?php
if(!empty($data))
{
    $row = [];
    foreach($data as $dat)
    {
        $htm = '';
        $mTrackHtm = '';

        if($dat['super_category_monthly_track'] == 'yes')
        {
            $mTrackHtm = "<input type='checkbox' name='monthlyTrack' class='s_c_row2' value='true' checked />";
        }
        else
        {
            $mTrackHtm = "<input type='checkbox' name='monthlyTrack' class='s_c_row2' value='' />";
        }

        $htm = "<div class='s_c_row'><input type='hidden' class='s_c_row0' value='" . $dat['super_category_id'] . "' /><input type='text' class='s_c_row1' value='" . $dat['super_category_desc'] . "' />" . $mTrackHtm . "<div class='s_c_row_actions'><div class='s_c_row_updt'>UPDATE</div><div class='s_c_row_delete'><img src='images/delete.png' width='20px' /></div></div></div>";
        echo $htm;
    }
}
?>
        </div>
    </div>
</div>

<script>
    
    $(document).ready(function(){
        $('#c_s_7').css({"background": "rgb(99,99,102)"});
        $('#c_s_7_1').css({"color": "#f5f5f5"}); 
    });
    
    $(document).on('click','.s_c_row_updt', function(){
        var supCatId = $(this).parent().siblings('.s_c_row0').val();
        var supCatDesc = $(this).parent().siblings('.s_c_row1').val();
        var mTrack = $(this).parent().siblings('.s_c_row2').is(":checked");

        $.ajax({type: "POST",
            url: "manageSupCat.php",
            data: {'action': "update", supCatId: supCatId, supCatDesc: supCatDesc, mTrack: mTrack},
            success:function(result) 
            { 
                if(result == false)
                {
                    alert("Try renaming the super category or try again later!");
                }
                else
                {
                    location.reload();
                }
            }
        });
    });
    
    $('.s_c_add').click(function(){
        var htm = "<div class='s_c_row'><input type='hidden' class='s_c_row0' value='new' /><input type='text' class='s_c_row1' value='' /><input type='checkbox' name='monthlyTrack' class='s_c_row2' value='' /><div class='s_c_row_actions'><div class='s_c_row_updt'>UPDATE</div><div class='s_c_row_filler'></div></div></div>";
        $('#s_c_row_data').append(htm);
        $('.s_c_row1').focus();
    });
    
    $(document).on('click','.s_c_row_delete', function(){
        var supCatId = $(this).parent().siblings('.s_c_row0').val();
        var supCatDesc = $(this).parent().siblings('.s_c_row1').val();
        var mTrack = $(this).parent().siblings('.s_c_row2').is(":checked");

        $.ajax({type: "POST",
            url: "manageSupCat.php",
            data: {'action': "delete", supCatId: supCatId, supCatDesc: supCatDesc, mTrack: mTrack},
            success:function(res) 
            { 
                if(res == false)
                {
                    alert("There are transactions attached to this Super Category!");
                }
                else
                {
                    location.reload();
                }
            }
        });
    });
    
</script>

<?php
include('footer.php');
?>
