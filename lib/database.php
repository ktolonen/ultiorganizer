<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'pelikone');
define('DB_PASSWORD', 'pelikone');
define('DB_DATABASE', 'pelikone');

$connection = 0;

function OpenConnection()
	{
	//echo "<p>connecting<br>";
	global $connection;
	$connection = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD);
	if(!$connection) 
		{
		die('Failed to connect to server: ' . mysql_error());
		}
	//echo "select db<br>";
	$db = mysql_select_db(DB_DATABASE);
	
	if(!$db) 
		{
		die("Unable to select database");
		}
	//echo "return</p>";
	}

function CloseConnection()
	{
	global $connection;
	mysql_close($connection);
	}
?>