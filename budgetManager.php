<?php
include('header.php');

$from_date = '2023-12-01';
$to_date = '';
$to_date = new DateTime($from_date);
$to_date = $to_date->format('Y-m-t');

$loginid = $_SESSION['loginid'];

include_once('class/categories.php');
$obj1 = new Categories();
        
include_once('class/budget.php');
$obj_budget = new budget();

include_once('class/convertToIndian.php');
$obj_conv = new convertToIndian();

$categList = $obj1->getCategory($loginid);

$actualAmount = 0;
$budgetAmount = 0;

$superCategList = $obj1->getSuperCategory($loginid);
if(!empty($superCategList))
{
    $annualBudget = 0;
    
    foreach($superCategList as $supCat)
    {
        $superCategDesc = '';
        $budgetArray = array();
        $superCatBudget = 0;
        
        $superCategDesc = $obj1->getSuperCategoryDescription($supCat);
        if($superCategDesc != 'error')
        {
            foreach($categList as $categ)
            {
                $categDesc = '';
                
                $categDesc = $obj1->getCategoryDescription($categ, $loginid);
                $superCatBudget = $superCatBudget + ($obj_budget->getBudget($supCat, $categ, $loginid));
                
                if($categDesc['description'] != 'error')
                {
                    $budgetArray[] = array('catId' => $categ, 'catDesc' => $categDesc['description']);
                }
            }
        }
        $supBudgetArray[] = array('supCatId' => $supCat, 'supCatDesc' => $superCategDesc, 'supCatBudget' => $superCatBudget, 'data' => $budgetArray);
        $annualBudget = $annualBudget + $superCatBudget;
    }
}
?>

<div id="b_main">
    <div id="b_main_hdr">
        <span class="title">Manage Budget</span>
    </div>
    <div id="b_main_data">
        <div id="b_main_data_tip" class="upper_cut">
            <div id="b_m_d_t_01"><img src='images/lightbulb.png' width="40px" /></div>
            <div id="b_m_d_t_02">One should enter annual budgets here. Categories that need to be tracked monthly will automatically track on a monthly basis.</div>
        </div>
        <?php
        
        if(abs($annualBudget) > 0)
        {
        ?>
        <div id="b_m_d_details">
            <div id="b_m_d_summary">
                <?php echo("ANNUAL BUDGET - " . $obj_conv->convertToIndianCurrency($annualBudget) . " Rs."); ?>
            </div>
        <?php
            foreach($supBudgetArray as $sup)
            {
                if(abs($sup['supCatBudget']) > 0)
                {
            ?>
                <div class="b_m_d_r_sup">
                    <div class="b_m_d_r_sup_1">
                        <?php echo strtoupper($sup['supCatDesc']); ?>
                    </div>
                    <div class="b_m_d_r_sup_3">
                        <?php echo("ANNUAL BUDGET - " .($obj_conv->convertToIndianCurrency($sup['supCatBudget'])) . " Rs.");?>
                    </div>
                    <input type="hidden" class="b_m_d_r_sup_2" value="<?php echo $sup['supCatId']; ?>" />
                    <div class="b_main_data_row_section">
                <?php
                    foreach($sup['data'] as $b)
                    {

                    ?>
                        <div class="b_main_data_row">
                            <div class="b_m_d_r_cat b_m_row_items">
                                <div class="b_m_d_r_cat_1">
                                    <?php echo $b['catDesc']; ?>
                                </div>
                                <input type="hidden" class="b_m_d_r_cat_2" value="<?php echo $b['catId']; ?>" />                    
                            </div>
                            <div class="b_m_d_r_bud b_m_row_items">
                                <input type="number" class="b_m_d_r_bud_val" value="<?php echo($obj_budget->getBudget($sup['supCatId'], $b['catId'], $loginid));?>" placeholder="Enter budgeted amount"/>
                            </div>
                            <div class="b_m_d_r_act">
                                UPDATE
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
            </div>
            <?php
        }
        ?>
    </div>
</div>

<script>
$(document).ready(function(){

    $('#c_s_5').css({"background": "rgb(99,99,102)"});
    $('#c_s_5_1').css({"color": "#f5f5f5"}); 
});

$('.b_m_d_r_act').click(function(){
    
    var supCatId = $(this).parent().parent().siblings('.b_m_d_r_sup_2').val();
    var catId = $(this).siblings('.b_m_d_r_cat').find('.b_m_d_r_cat_2').val();
    var budget = $(this).siblings('.b_m_d_r_bud').find('.b_m_d_r_bud_val').val();
    
    $.ajax({type: "POST",
    url: "updateBudget.php",
    data: {supCatId: supCatId, catId: catId, budget: budget},
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

});
    
</script>

<?php
include('footer.php');
?>

