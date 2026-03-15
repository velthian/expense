<?php
/**
 * Description of convertToIndian2
 *
 * @author anuragsinha
 */
class convertToIndian {
    public static function convertToIndianCurrency($number) 
    {
        $first_in_loop = true;
        if($number <0)
        {
            $is_negative = true;
        }
        else
        {
            $is_negative = false;
        }
        $clean_no = abs(round($number));
        

        $length = strlen($clean_no);
        switch($length)
        {
            case(8):
            {   
                //single digit crore
                $str1 = substr($clean_no,-3);
                $str2 = substr($clean_no,-5,-3);
                $str3 = substr($clean_no,-7,-5);
                $str4 = substr($clean_no,-8,-7);
                $final_str = $str4.",".$str3.",".$str2.",".$str1;
                break;
            }
            case(7):
            {   
                //double digit lakh
                $str1 = substr($clean_no,-3);
                $str2 = substr($clean_no,-5,-3);
                $str3 = substr($clean_no,-7,-5);
                $final_str = $str3.",".$str2.",".$str1;
                break;
            }
            case(6):
            {   
                //single digit lakh
                $str1 = substr($clean_no,-3);
                $str2 = substr($clean_no,-5,-3);
                $str3 = substr($clean_no,-6,-5);
                $final_str = $str3.",".$str2.",".$str1;
                break;
            }
            case(5):
            {   
                //double digit thousand
                $str1 = substr($clean_no,-3);
                $str2 = substr($clean_no,-5,-3);
                $final_str = $str2.",".$str1;
                break;
            }
            case(4):
            {   
                //single digit thousand
                $str1 = substr($clean_no,-3);
                $str2 = substr($clean_no,-4,-3);
                $final_str = $str2.",".$str1;
                break;
            }
            default:
            {   
                $final_str = $clean_no;
                break;
            }
        }
        if($is_negative)
        {
            $final_str = "-".$final_str;
        }
        return $final_str;
    }
}
?>