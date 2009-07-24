<?php 

function UserAuthenticate($user, $passwd)
	{
	$query = sprintf("SELECT * FROM pelik_users WHERE UserID='%s' AND Password='%s'",
		mysql_real_escape_string($user),
		mysql_real_escape_string($passwd));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function UserInfo($user_id)
	{
	$query = sprintf("SELECT * FROM pelik_users WHERE id='%s'",
		mysql_real_escape_string($user_id));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return mysql_fetch_assoc($result);
	}

function UserUpdateInfo($user_id, $user, $passwd, $name, $email)
	{
	$query = sprintf("
		UPDATE pelik_users SET UserID='%s', Password='%s', Nimi='%s', Email='%s' WHERE ID='%s'",
		mysql_real_escape_string($user),
		mysql_real_escape_string($passwd),
		mysql_real_escape_string($name),
		mysql_real_escape_string($email),
		mysql_real_escape_string($user_id));
	
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
?>
