<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

/**
 * Description of dateValidation
 *
 * @author anuragsinha
 */
class dateValidation {
    
    public function checkValidDate($date_to_check)
    {
        list($year,$month,$day) = explode('-', $date_to_check); 
        if(checkdate($month, $day, $year))
        {
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }
}
