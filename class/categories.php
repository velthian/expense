<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

/**
 * Description of Categories
 *
 * @author anuragsinha
 */
class Categories 
{
    
    public function getNewCategoryId()
    {
        include('db.php');
        
        $sql = "SELECT max_categ_count FROM max_category_count";
        $result = $conn->query($sql);
        
        $category_id ='';
        
        if(!$result)
        {
            $category_id ='error';
        }
        else
        {
            $row = $result->fetch_assoc();
            $category_id = $row['max_categ_count'] + 1;
        }
        
        $conn->close();
        return $category_id;
    }
    
    public function updateMaxCatCount($max_category_id)
    {
        include('db.php');
        
        $sql = "DELETE FROM max_category_count";

        if($conn->query($sql) === TRUE) 
        {
            $sql2 = "INSERT INTO max_category_count (max_categ_count) VALUE ('" . $max_category_id . "')";
            if ($conn->query($sql2) != TRUE)
            {
                $conn->close();
                return 1;
            }
            else
            {
                $conn->close();
                return 0;
            }
        }
        else
        {
            $conn->close();
            return 1;
        }
         
    }
    
    public function getCategory($loginid)
    {
        include(__DIR__ .'/../db.php');
        
        $sql = "SELECT * FROM categories WHERE username='" . $loginid ."' AND status='1'";
        
        $result = $conn->query($sql);
        $num_rows = '';
        $num_rows = $result->num_rows;
        
        $data = array();
        if($num_rows > 0)
        {
            for($i=0; $i< $num_rows; $i++)
            {
                $row = $result->fetch_assoc();
                array_push($data, $row['category_id']);
            }
        }
        
        $conn->close();
        return $data;
    }

    
    public function getCategoryDescription($category_id, $loginid)
    {
        include(__DIR__ .'/../db.php');
        
        $category_description = '';
        
        $sql = "SELECT * FROM categories WHERE category_id='" . $category_id . "' AND username='" . $loginid . "'";
        $result = $conn->query($sql);
        
        
        if(!$result)
        {
            $category_description = 'error';
        }
        else
        {
            $row = $result->fetch_assoc();
            $category_description = $row['category_description'];
            $data = array('description' => $category_description);
        }
        
        $conn->close();
        return $data;
        
    }

    
    public function createCategory($category_description)
    {
        include('db.php');
        $res = '';
        
        $loginid = $_SESSION['loginid'];
        
        $sql = "SELECT * FROM categories WHERE category_description ='" . $category_description . "' AND username ='" . $loginid . "'";
        $result = $conn->query($sql);
        $num_rows = $result->num_rows;
        
        if($num_rows > 0)
        {
            //category already exists for the user
            $res = 9;
        }
        else
        {       
            $new_category_id = '';
            $new_category_id = $this->getNewCategoryId();
            
            if($new_category_id == 'error')
            {
                $res = 1;
            }
            else
            {
                //create a new category
                $sql1 = "INSERT into categories (category_id, category_description, username, status) VALUES ('" . $new_category_id . "','" . $category_description . "','" . $loginid . "', '1')";
                $result1 = $conn->query($sql1);

                if($result1 === TRUE)
                {
                    $res = 0;
                    
                    //update max category id table
                    
                    $res = $this->updateMaxCatCount($new_category_id);
                    if($res == 1)
                    {
                        //rollback
                    }
                }
                else 
                {
                    $res = 1;
                }  
            }
        }
        
        $conn->close();
        return $res;
    }
    
    public function deleteCategory($category_id)
    {
        include('db.php');
        
        $loginid = '';
        $res = '';
        
        $loginid = $_SESSION['loginid'];
        
        if(!empty($category_id))
        {
            $sql = "SELECT * FROM merchant WHERE username='" . $loginid . "' AND category_id ='" . $category_id . "'";
            $result = $conn->query($sql);
            $num_rows = $result->num_rows;
            
            if($num_rows > 0)
            {
                $res = 9; //categories associated with a merchant
            }
            else
            {
            
                $sql1 = "DELETE FROM categories WHERE category_id ='" . $category_id . "'";
                if ($conn->query($sql1) === TRUE) 
                {
                    $res = 0;  //successfully deleted
                } 
                else 
                {
                    $res = 1; //error while deleting
                }
                
            }
        }
        else
        {
            $res = 1; //error while deleting
        }
        
        $conn->close();
        return($res);        
    }
    
    public function checkUnassignedCategories()
    {
        $loginid = '';
        $loginid = $_SESSION['loginid'];
        
        include('db.php');
        $sql = "SELECT * FROM merchant WHERE category_id='0' AND username='" . $loginid . "'";
        $result = $conn->query($sql);
        $num_rows = $result->num_rows;
        
        $flag = '0';
        
        if($num_rows > 0)
        {
            $flag = '1';
        }
        else 
        {
            $flag = '0';
        }
        
        return $flag;
        $conn->close();
    }
    
    public function getAllSuperCategories($loginid)
    {
        include('db.php');
        
        $sql = "SELECT * FROM super_category WHERE username='" . $loginid . "'";
        $result = $conn->query($sql);
        $num_rows = $result->num_rows;
        $data = array();
        
        if($num_rows > 0)
        {
            for($i=0; $i < $num_rows; $i++)
            {
                $row = $result->fetch_assoc();
                array_push($data, array('super_category_id' => $row['super_category_id'], 'super_category_desc' => $row['super_category_desc'], 'super_category_monthly_track' => $row['monthlyTrack']));
            }
        }
        
        return $data;
        $conn->close();
    }
    
    
    public function getSuperCategory($loginid) //duplicate of above but will live with this for now
    {
        include('db.php');
        
        $sql = "SELECT * FROM super_category WHERE username='" . $loginid ."'";
        
        $result = $conn->query($sql);
        $num_rows = '';
        $num_rows = $result->num_rows;
        
        $data = array();
        if($num_rows > 0)
        {
            for($i=0; $i< $num_rows; $i++)
            {
                $row = $result->fetch_assoc();
                array_push($data, $row['super_category_id']);
            }
        }
        
        $conn->close();
        return $data;
    }
    
    public function getSuperCategoryMtrack($loginid) //duplicate of above but will live with this for now
    {
        include('db.php');
        
        $sql = "SELECT * FROM super_category WHERE username='" . $loginid ."' AND monthlyTrack='yes'";
        
        $result = $conn->query($sql);
        $num_rows = '';
        $num_rows = $result->num_rows;
        
        $data = array();
        if($num_rows > 0)
        {
            for($i=0; $i< $num_rows; $i++)
            {
                $row = $result->fetch_assoc();
                array_push($data, $row['super_category_id']);
            }
        }
        
        $conn->close();
        return $data;
    }
    
    
    public function getSuperCategoryDescription($super_category_id)
    {
        include(__DIR__ . '/../db.php');
        
        $category_description = '';
        
        $sql = "SELECT * FROM super_category WHERE super_category_id='" . $super_category_id . "'";
        $result = $conn->query($sql);
        
        
        if(!$result)
        {
            $super_category_description = 'error';
        }
        else
        {
            $row = $result->fetch_assoc();
            $super_category_description = $row['super_category_desc'];
        }
        
        return $super_category_description;
        $conn->close();
        
    }
    
    public function manageSupCat($action,$supCatId,$supCatDesc,$mTrack,$loginid)
    {
        include('db.php');
        $flag = FALSE;
        
        switch($action)
        {
            case('update'):
            {
                if($mTrack == 'true')
                {
                    $mTrack = 'yes';
                }
                else
                {
                    $mTrack = 'no';
                }
                
                if($supCatId == 'new')
                {
                    //First check if a super category with same name does not exit
                    $sql1 = "SELECT * FROM super_category WHERE super_category_desc ='" . $supCatDesc . "' AND username='" . $loginid . "'";
                    $result1 = $conn->query($sql1);
                    $num_rows1 = $result1->num_rows;
                    if($num_rows1 >0)
                    {
                        $flag = FALSE;
                    }
                    else
                    {
                        $sql = "INSERT INTO super_category (super_category_desc, monthlyTrack, username) VALUES ('" . $supCatDesc . "','" . $mTrack . "','" . $loginid . "')";
                        $flag = TRUE;
                    }
                }
                else
                {
                    $sql = "UPDATE super_category SET super_category_desc = '" . $supCatDesc . "', monthlyTrack ='" . $mTrack . "' WHERE super_category_id='" . $supCatId . "' AND username ='" . $loginid . "'";
                    $flag = TRUE;
                }
                
                if($flag)
                {
                    if($conn->query($sql) === TRUE)
                    {
                        $flag = TRUE;
                    }
                    else
                    {
                        $flag = FALSE;
                    }
                }
                break;
            }
            
            case('delete'):
            {
                //first check if there are transactions attached to this super category
                $sql1 = "SELECT * FROM transactions WHERE super_category_id ='" . $supCatId . "'";
                $result1 = $conn->query($sql1);
                $num_rows1 = $result1->num_rows;
                if($num_rows1 >0)
                {
                    $flag = FALSE;
                }
                else
                {
                    $sql = "DELETE FROM super_category WHERE super_category_id='" . $supCatId . "'";
                    if($conn->query($sql) === TRUE)
                    {
                        $flag = TRUE;
                    }
                    else
                    {
                        $flag = FALSE;
                    }
                }
                break;
            }
        }

        return $flag;
        $conn->close();
    }
}
