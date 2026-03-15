<?php
//https://trackwealth.in/expense/setCookie.php?access=4dbe31e58e50e835b713fd2fd606e52e

$valid_token = '4dbe31e58e50e835b713fd2fd606e52e';

if (isset($_GET['access']) && $_GET['access'] === $valid_token) 
{
    // Set a cookie valid for 30 days
    setcookie("akshat_access", $valid_token, time() + (60 * 60 * 24 * 365), "/");
    // Redirect to the private page
    header("Location: akshatView.php");
    exit;
} 
else 
{
    http_response_code(403);
    echo "Access Denied";
}
?>
