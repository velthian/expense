<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

/**
 * Description of budget
 *
 * @author anuragsinha
 */
class budget {
    public function getBudget($supCatId, $catId, $loginid)
    {
        include('db.php');
        
        $budget = 0;
        
        $sql = "SELECT * FROM budget WHERE username='" . $loginid . "' AND super_category_id='" . $supCatId . "' AND category_id='" . $catId ."'";
        $result = $conn->query($sql);
        $num_rows = $result->num_rows;
        if($num_rows > 0)
        {
            for($i=0; $i<$num_rows; $i++)
            {
                $row = $result->fetch_assoc();
                $budget = $row['budget'];
            }
        }
        
        return $budget;
        $conn->close();
    }
    
    
    public function updateBudget($supCatId, $catId, $budget, $loginid)
    {
        include('db.php');
        $res = FALSE;
        
        $sql = "INSERT INTO budget VALUES ('" . $supCatId . "','" . $catId . "','" . $budget . "','" . $loginid . "') ON DUPLICATE KEY UPDATE budget ='" . $budget . "'";
        
        $result = $conn->query($sql);

        if($result === TRUE)
        {
            $res = TRUE;
        }
        else
        {
            $res = FALSE;
        }
        $conn->close();
        return $res;
    }
}
