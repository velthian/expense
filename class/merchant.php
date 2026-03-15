<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

/**
 * Description of merchant
 *
 * @author anuragsinha
 */
class merchant {
    public function putMerchant($merchant, $category_id, $loginid)
    {
        $flag = FALSE;
        
        include(__DIR__ .'/../db_new.php');
        
        $merchant = strtoupper($merchant);
        
        $sql0 = "SELECT * FROM merchant WHERE merchant_name='" . $merchant . "' AND username='" . $loginid . "'";
        $result0 = $conn_new->query($sql0);
        $num_rows0 = $result0->num_rows;
        if($num_rows0 > 0)
        {
            $flag = TRUE;
        }
        else
        {
            $sql1 = "SELECT * FROM max_merchant_count WHERE username='" . $loginid . "'";
            $result1 = $conn_new->query($sql1);
            $num_rows = $result1->num_rows;
            if($num_rows > 0)
            {
                $current_merchant_id = '';
                $new_merchant_id = '';

                $row1 = $result1->fetch_assoc();
                $current_merchant_id = $row1['max_merchant_id'];
                $new_merchant_id = $current_merchant_id+1;

                $sql2 = "UPDATE max_merchant_count SET max_merchant_id='" . $new_merchant_id . "' WHERE username='" . $loginid . "'";
                if($conn_new->query($sql2) === TRUE)
                {
                    $flag = TRUE;
                }
                else
                {
                    $flag = FALSE;
                }
            }
            else
            {
                $new_merchant_id = '1';

                $sql3 = "INSERT INTO max_merchant_count (max_merchant_id, username) VALUE ('" . $new_merchant_id . "','" . $loginid. "')";
                if($conn_new->query($sql3) === FALSE)
                {
                    $flag = FALSE;
                }
                else
                {
                    $flag = TRUE;
                }
            }

            if($flag)
            {
                $sql = "INSERT INTO merchant (merchant_id, merchant_name, category_id, username) VALUE ('" . $new_merchant_id . "','" . $merchant . "', '" . $category_id ."','" . $loginid . "')";

                if($conn_new->query($sql) === FALSE)
                {
                    $flag = FALSE;
                }
                else
                {
                    $flag = TRUE;
                }
            }

            if($flag)
            {
                mysqli_commit($conn_new);
            }
            else
            {
                mysqli_rollback($conn_new);
            }
        }
        
        mysqli_close($conn_new);
        return $flag;
    }
    
    public function getAllMerchant($searchTerm, $category_id)
    {
        $loginid = '';
        $loginid = $_SESSION['loginid'];
        
        include(__DIR__ .'/../db.php');
        $searchTerm = strtoupper($searchTerm);
        
        //first see if category_id is set or not
        if(!is_null($category_id))
        {
            
            if($searchTerm == '')
            {        
                $sql = "SELECT * FROM merchant WHERE username ='" . $loginid . "' AND category_id = '" . $category_id . "' ORDER BY merchant_name ASC";
            }
            else
            {
                $sql = "SELECT * FROM merchant WHERE username ='" . $loginid . "' AND category_id = '" . $category_id . "' AND merchant_name LIKE '%" . $searchTerm . "%' ORDER BY merchant_name ASC";
            }
        }
        else
        {
            if($searchTerm == '')
            {        
                $sql = "SELECT * FROM merchant WHERE username ='" . $loginid . "' ORDER BY merchant_name ASC";
            }
            else
            {
                $sql = "SELECT * FROM merchant WHERE username ='" . $loginid . "' AND merchant_name LIKE '%" . $searchTerm . "%' ORDER BY merchant_name ASC";
            }
        }
        
        $result = $conn->query($sql);
        $num_rows = $result->num_rows;
        $data = array();
        
        if($num_rows > 0)
        {
            for($i=0; $i < $num_rows; $i++)
            {
                $row = $result->fetch_assoc();
                $temp = array('merchant_id' => $row['merchant_id'], 'merchant_name' => $row['merchant_name'], 'category_id' => $row['category_id'] );
                array_push($data, $temp);
            }
        }
        
        $conn->close();
        return json_encode($data);
    }
    
    public function getMerchantId($merchant, $loginid)
    {
        include(__DIR__ .'/../db.php');
        
        $sql = "SELECT merchant_id FROM merchant WHERE merchant_name ='" . $merchant . "' AND username='" . $loginid . "'";
        $result = $conn->query($sql);
        $num_rows = $result->num_rows;
        
        $merchant_id = '';
        
        if($num_rows > 0)
        {
            $row = $result->fetch_assoc();
            $merchant_id = $row['merchant_id'];
        }
        else
        {
            $merchant_id = 'error';
        }
        
        $conn->close();
        
        return $merchant_id;
    }
    
    public function updateCategoryId($merchant_id, $category_id)
    {
        include(__DIR__ .'/../db.php');
        $loginid = '';
        $loginid = $_SESSION['loginid'];
        $flag = FALSE;
        
        $sql = "UPDATE merchant SET category_id='" . $category_id . "' WHERE username='" . $loginid . "' AND merchant_id='" . $merchant_id . "'";
        if($conn->query($sql) === TRUE)
        {
            $flag = TRUE;
        }
        else
        {
            $flag = FALSE;
        }  
        
        if($flag === FALSE)
        {
            $j=0;
        }
        
        $conn->close();
        return $flag;
    }
    
    public function getMerchantCategory($merch_id, $loginid)
    {
        include(__DIR__ .'/../db.php');
        
        $category_id = '';
        $merchant_name = '';
        $data = array();
        
        $sql = "SELECT merchant_name, category_id FROM merchant WHERE merchant_id='" . $merch_id . "' AND username='" . $loginid . "'";
        $result = $conn->query($sql);
        $num_rows = $result->num_rows;
        if($num_rows > 0)
        {
            $row = $result->fetch_assoc();
            $category_id = $row['category_id'];
            $merchant_name = $row['merchant_name'];
        }
        
        include_once('class/categories.php');
        $obj = new Categories();
        
        $category_list = array();
        $category_list = $obj->getCategory($loginid);

        $html = '';
            
        if(!empty($category_list) && $category_id != '')
        {
            
            $html = "<div class='ac_merch_name_cont'><div class='ac_merch_name_cont_cont'><div class='ac_merch_name'>" . $merchant_name . "</div><input type='hidden' class='ac_merch_id' value='" . $merch_id ."' /></div><div class='ac_merch_cat_list_cont'>";

            foreach($category_list as $cat)
            {
                $category_description = array();
                $category_description = $obj->getCategoryDescription($cat, $loginid); 

                $htm = '';
                if($category_id == $cat)
                {
                    $htm = "<label class='ac_merch_cat_list'><input type='radio' class ='ac_merch_cat_list_inp' value='" . $cat . "' checked='checked' name ='" . $merch_id ."' />" . $category_description['description'] . "</label>";
                }
                else
                {
                    $htm = "<label class='ac_merch_cat_list'><input type='radio' class ='ac_merch_cat_list_inp' value='" . $cat . "' name ='" . $merch_id ."' />" . $category_description['description'] . "</label>";
                }

                $html = $html . $htm;
            }
            
            $html = $html . "</div></div>";
        }
        
        return $html;
        $conn->close();
    }
}
