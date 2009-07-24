<?php
session_start();
if(!isset($_SESSION['uid']) || !isset($_SESSION['pwd']) || (trim($_SESSION['uid']) == '') || (trim($_SESSION['pwd']) == ''))
	{
	header("location:../login_failed.php");
	}
?>