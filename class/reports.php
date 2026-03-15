<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

/**
 * Description of reports
 *
 * @author anuragsinha
 */
class reports {

    public function varianceReport($from_date, $loginid)
    {
        $from_date = new DateTime($from_date);
        $from_date = $from_date->format('Y-m-01');
        
        $to_date = '';
        $to_date = new DateTime($from_date);
        $to_date = $to_date->format('Y-m-t');

        include_once('class/transactions.php');
        $obj = new transactions();

        include('db.php');

        include_once('class/categories.php');
        $obj1 = new Categories();

        include_once('class/budget.php');
        $obj2 = new budget();

        $categList = $obj1->getCategory($loginid);

        $actualAmount = 0;
        $budgetAmount = 0;

        $superCategList = $obj1->getSuperCategoryMtrack($loginid);

        $amtBySupCateg = array();

        if(!empty($superCategList))
        {
            foreach($superCategList as $supCat)
            {
                $supCatActualAmount = 0;
                $supCatBudgetAmount = 0;

                $superCategDesc = '';
                $superCategDesc = $obj1->getSuperCategoryDescription($supCat);

                if($superCategDesc != 'error')
                {
                    $amtByCateg = array();

                    foreach($categList as $categ)
                    {
                        $catActualAmount = 0;
                        $catBudgetAmount = 0;

                        $categDesc = '';
                        $categDesc = $obj1->getCategoryDescription($categ, $loginid);
                        
                        $budget ='';
                        $budget = ($obj2->getBudget($supCat, $categ, $loginid))/12;
                        
                        //For non credit card
                        $sql = "SELECT t.amount FROM transactions AS t INNER JOIN merchant AS m ON t.merchant_id = m.merchant_id WHERE m.category_id = '" . $categ . "' AND t.username ='" . $loginid . "' AND t.super_category_id='" . $supCat . "' AND t.date BETWEEN '" . $from_date . "' AND '" . $to_date . "' AND t.mode <> 'creditcard'";
                        $result = $conn->query($sql);
                        $num_rows = $result->num_rows;
                        if($num_rows > 0)
                        {
                            for($i=0; $i < $num_rows; $i++)
                            {
                                $row = $result->fetch_assoc();
                                $catActualAmount = $catActualAmount + $row['amount'];
                                $supCatActualAmount = $supCatActualAmount + $row['amount'];
                            }
                        }
                        
                        //For credit card, we will need different dates
                        
                        $date_arr = array();
                        include_once('class/ccDates.php');
                        $obj3 = new ccDates();
                        $date_arr = $obj3->stmtDate($from_date, 'p'); 
                        
                        $from_date_cc = '';
                        $to_date_cc = '';
                        
                        $from_date_cc = $date_arr[0];
                        $to_date_cc = $date_arr[1];
        
                        $sql1 = "SELECT t.amount FROM transactions AS t INNER JOIN merchant AS m ON t.merchant_id = m.merchant_id WHERE m.category_id = '" . $categ . "' AND t.username ='" . $loginid . "' AND t.super_category_id='" . $supCat . "' AND t.date BETWEEN '" . $from_date_cc . "' AND '" . $to_date_cc . "' AND t.mode = 'creditcard'";
                        $result1 = $conn->query($sql1);
                        $num_rows1 = $result1->num_rows;
                        if($num_rows1 > 0)
                        {
                            for($i=0; $i < $num_rows1; $i++)
                            {
                                $row1 = $result1->fetch_assoc();
                                $catActualAmount = $catActualAmount + $row1['amount'];
                                $supCatActualAmount = $supCatActualAmount + $row1['amount'];
                            }
                        }                        
                        
                        if(abs($catActualAmount > 0) || abs($budget) > 0)
                        {
                            
                            $variance = '';
                            $supCatBudgetAmount = $supCatBudgetAmount + $budget;
                            $variance = $budget - $catActualAmount; 

                            $amtByCateg[] = array('category_description' => $categDesc['description'], 'category_id' => $categ, 'category_amount' => number_format((float)$catActualAmount,2,'.',''), 'budget' => number_format((float)$budget,2,'.',''), 'variance' => number_format((float)$variance,2,'.',''));
                        }

                    }
                }
                $amtBySupCateg[] = array('super_category' => $superCategDesc, 'super_category_id' => $supCat, 'super_category_amount' => number_format((float)$supCatActualAmount,2,'.',''), 'super_category_budget' => number_format((float)$supCatBudgetAmount,2,'.',''),'super_category_variance' => number_format((float)($supCatBudgetAmount - $supCatActualAmount),2,'.',''), 'data' => $amtByCateg);
            }    
        }

        return $amtBySupCateg;
        $conn->close();
    }

    public function varianceReportAnnual($current_date, $loginid, $fy_begin_date, $fy_end_date)
    {
        include_once('class/ccDates.php');
        $dateObj = new ccDates();
        
        if($fy_begin_date != '' && $fy_end_date != '')
        {
            $dtRes = $dateObj->putFYdate($fy_begin_date, $fy_end_date, $loginid);
            $from_date = $fy_begin_date;
        }
        else
        {
            $fy_date_arr = array();
            $fy_date_arr = $dateObj->getInceptionDate($loginid);
            $from_date = $fy_date_arr['fyBeginDate'];
            $fy_end_date = $fy_date_arr['fyEndDate'];
        }

        include_once('class/transactions.php');
        $obj = new transactions();

        include('db.php');

        include_once('class/categories.php');
        $obj1 = new Categories();

        include_once('class/budget.php');
        $obj2 = new budget();

        $categList = $obj1->getCategory($loginid);

        $actualAmount = 0;
        $budgetAmount = 0;

        $superCategList = $obj1->getSuperCategory($loginid);

        $amtBySupCateg = array();

        if(!empty($superCategList))
        {
            foreach($superCategList as $supCat)
            {
                $supCatActualAmount = 0;
                $supCatBudgetAmount = 0;

                $superCategDesc = '';
                $superCategDesc = $obj1->getSuperCategoryDescription($supCat);

                if($superCategDesc != 'error')
                {
                    $amtByCateg = array();

                    foreach($categList as $categ)
                    {
                        $catActualAmount = 0;
                        $catBudgetAmount = 0;

                        $categDesc = '';
                        $categDesc = $obj1->getCategoryDescription($categ, $loginid);
                        
                        $monthsElapsed = '';
                        $mm ='';
                        $mm = date('m', strtotime($to_date));
                        
                        $mm_begin = '';
                        $mm_begin = date('m', strtotime($from_date));
                        
                        /*
                        if($mm == $mm_begin)
                        {
                            $monthsElapsed = 1;
                        }
                        else
                        {
                            if($mm > $mm_begin)
                            {
                                $monthsElapsed = ($mm - $mm_begin +1);
                            }
                            else
                            {
                                $monthsElapsed = (12 - $mm_begin + $mm + 1);
                            }
                        }
                        */
                        
                        $monthsInFy = '';
                        $mm_fy_begin = '';
                        $mm_fy_end = '';
                        $mm_fy_months = '';
                        
                        $mm_fy_begin = date('m',strtotime($from_date));
                        $mm_fy_end = date('m', strtotime($fy_end_date));
                        
                        if($mm_fy_end ==  $mm_fy_begin)
                        {
                            $mm_fy_months =1;
                        }
                        else
                        {
                            if($mm_fy_end > $mm_fy_begin)
                            {
                                $mm_fy_months = ($mm_fy_end - $mm_fy_begin + 1);
                            }
                            else
                            {
                                $mm_fy_months = (12 - ($mm_fy_begin - $mm_fy_end) +1);
                            }
                        }
                        
                        $budget ='';
                        $annualBudget = '';
                        $annualBudget = ($obj2->getBudget($supCat, $categ, $loginid));
                        $budget = ($annualBudget/12)*$mm_fy_months;
                        
                        //For non credit card
                        $sql = "SELECT t.amount FROM transactions AS t INNER JOIN merchant AS m ON t.merchant_id = m.merchant_id WHERE m.category_id = '" . $categ . "' AND t.username ='" . $loginid . "' AND t.super_category_id='" . $supCat . "' AND t.date BETWEEN '" . $from_date . "' AND '" . $fy_end_date . "' AND t.mode <> 'creditcard'";
                        $result = $conn->query($sql);
                        $num_rows = $result->num_rows;
                        if($num_rows > 0)
                        {
                            for($i=0; $i < $num_rows; $i++)
                            {
                                $row = $result->fetch_assoc();
                                $catActualAmount = $catActualAmount + $row['amount'];
                                $supCatActualAmount = $supCatActualAmount + $row['amount'];
                            }
                        }
                        
                        //For credit card, we will need different dates
                        
                        $date_arr = array();
                        include_once('class/ccDates.php');
                        $obj3 = new ccDates();
                        $date_arr = $obj3->stmtDate($from_date, 'p'); 
                        
                        $from_date_cc = '';
                        $to_date_cc = '';
                        
                        $from_date_cc = $date_arr[0];
                        $to_date_as_requested = '';
                        $to_date_as_requested = date('Y-m-01', strtotime($fy_end_date));
                        $to_date_arr = array();
                        $to_date_arr = $obj3->stmtDate($to_date_as_requested, 'p');
                        $to_date_cc = $to_date_arr[1];
        
                        $sql1 = "SELECT t.amount FROM transactions AS t INNER JOIN merchant AS m ON t.merchant_id = m.merchant_id WHERE m.category_id = '" . $categ . "' AND t.username ='" . $loginid . "' AND t.super_category_id='" . $supCat . "' AND t.date BETWEEN '" . $from_date_cc . "' AND '" . $to_date_cc . "' AND t.mode = 'creditcard'";
                        $result1 = $conn->query($sql1);
                        $num_rows1 = $result1->num_rows;
                        if($num_rows1 > 0)
                        {
                            for($i=0; $i < $num_rows1; $i++)
                            {
                                $row1 = $result1->fetch_assoc();
                                $catActualAmount = $catActualAmount + $row1['amount'];
                                $supCatActualAmount = $supCatActualAmount + $row1['amount'];
                            }
                        }                        
                        
                        if(abs($catActualAmount > 0) || abs($budget) > 0)
                        {
                            
                            $variance = '';
                            $supCatBudgetAmount = $supCatBudgetAmount + $budget;
                            $variance = $budget - $catActualAmount; 

                            $amtByCateg[] = array('category_description' => $categDesc['description'], 'category_id' => $categ, 'category_amount' => number_format((float)$catActualAmount,2,'.',''), 'budget' => number_format((float)$budget,2,'.',''), 'variance' => number_format((float)$variance,2,'.',''));
                        }

                    }
                }
                $amtBySupCateg[] = array('super_category' => $superCategDesc, 'super_category_id' => $supCat, 'super_category_amount' => number_format((float)$supCatActualAmount,2,'.',''), 'super_category_budget' => number_format((float)$supCatBudgetAmount,2,'.',''),'super_category_variance' => number_format((float)($supCatBudgetAmount - $supCatActualAmount),2,'.',''), 'data' => $amtByCateg);
            }    
        }

        return $amtBySupCateg;
        $conn->close();
    }
}
