<?php
include_once '../lib/database.php';
include_once 'lib/user.functions.php';
ob_start();

$logout = $_POST['logout'];

if (empty($logout))
	{
	//"login" button is clicked

	$myusername=$_POST['myusername'];
	$mypassword=$_POST['mypassword'];

	OpenConnection();
	$myusername = mysql_real_escape_string($myusername);
	$mypassword = mysql_real_escape_string($mypassword);

	$user = UserAuthenticate($myusername, $mypassword);

	$count=mysql_num_rows($user);
	CloseConnection();

	// If exactly one row then correct $myusername and $mypassword
	if($count==1)
		{
		session_start();
		session_regenerate_id();
		$_SESSION['uid'] = $myusername;
		$_SESSION['pwd'] = $mypassword;
		$userinfo = mysql_fetch_assoc($user);
		
		$_SESSION['user'] = $userinfo['nimi'];
		$_SESSION['id'] = $userinfo['id'];
		
		//redirection in case of authentication success
		header("location:index.php");
		}
	else 
		{
		//redirection in case of authentication failed
		header("location:../login_failed.php");
		}
	}
else 
	{
	//"logout" button is clicked

	session_start();
	
	// Unset all of the session variables.
	$_SESSION = array();

	// Delete the session cookie.
	if (isset($_COOKIE[session_name()])) 
		{
		setcookie(session_name(), '', time()-42000, '/');
		}
	
	//destroy session
	session_destroy();

	header("location:../index.php");
	} 

ob_end_flush();
?>