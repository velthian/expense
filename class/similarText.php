<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

/**
 * Description of similarText
 *
 * @author anuragsinha
 */
class similarText {
    public function isSimilarText($inputName, $dbName, $threshold = 70)
    {
        $input = $this->preprocessMerchantName($inputName);
        $db = $this->preprocessMerchantName($dbName);

        similar_text($input, $db, $percent);

        // Optional: Levenshtein distance can be used for fuzzy matching tolerance
        $lev = levenshtein($input, $db);

        // Example logic:
        if ($percent >= $threshold || $lev <= 3) {
            return true;
        }
        return false;
    }
    
    private function preprocessMerchantName($name) 
    {
        // Convert to lowercase
        $name = strtolower($name);
        // Remove special characters except alphanumerics and spaces
        $name = preg_replace('/[^a-z0-9 ]/', '', $name);
        // Trim extra spaces
        $name = trim(preg_replace('/\s+/', ' ', $name));
        return $name;
    }
}
