<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

    //$servername = "localhost:8889";
    $servername = "localhost";
    $username = "mainTw";
    $password = "Akshat@2312";
    $dbname = "expense";

    // Create connection
    $conn_new = new mysqli($servername, $username, $password, $dbname);
    // Check connection
    if ($conn_new->connect_error) 
    {
        error_log("Connection failed: " . $conn->connect_error,0);
        die("Connection failed: " . $conn_new->connect_error);
    }
    mysqli_autocommit($conn_new,FALSE);
    
?>