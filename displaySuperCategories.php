<div class="supCatCont">
    <div id="mc_cat_disp_hdr"><span id='mc_c_d_h_1' class='title'>Super Categories</span><div classs="deco"></div></div>
    <div class="supCatLabelCont">
        <?php
        include_once('class/categories.php');
        $obj = new categories();

        $loginid = $_SESSION['loginid'];

        $super_category_list = array();
        $super_category_list = $obj->getSuperCategory($loginid);

        foreach($super_category_list as $sup)
        {
            $super_category_description = '';
            $super_category_description = strtoupper($obj->getSuperCategoryDescription($sup));

            echo("<label class='mc_sup_categories_label'><input type='checkbox' name='supCat' value='" . $sup . "' />" . $super_category_description . "</label>");
        } 
        ?>
    </div>
    <div id="mc_sup_cat_actions">
        <div id="mc_sup_categories_act_selectall">
            <input type="hidden" id="sup_cat_selectall_inp" value="0" />
            <a id="sup_cat_selectall_a" href="#">Select All</a>
        </div>
    </div>
</div>
