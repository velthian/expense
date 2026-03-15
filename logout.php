<?php
session_start();

if(!isset($_SESSION['authenticated']))
{
	$_SESSION['redirect']=true;
	$_SESSION['auth_error']="Please login first";
	header('Location: index.php');
}
else
{
        session_unset();
	session_destroy();
        header('Location: index.php');
}
?>