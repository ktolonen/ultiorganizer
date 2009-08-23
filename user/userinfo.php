<?php
include_once 'view_ids.inc.php';
include_once 'builder.php';
include_once '../lib/database.php';
include_once '../lib/team.functions.php';
include_once '../lib/common.functions.php';

include_once 'lib/user.functions.php';
$LAYOUT_ID = USERINFO;

//common page
pageTopHeadOpen();
include_once 'lib/disable_enter.js.inc';
pageTopHeadClose();
leftMenu($LAYOUT_ID);
contentStart();
//content
OpenConnection();

//process itself if submit was pressed
if(!empty($_POST['save']))
	{
	$newUsername=$_POST['UserName'];
	$newPassword=$_POST['Password'];
	$newName=$_POST['Name'];
	$newEmail=$_POST['Email'];
	$error = 0;
	
	if(empty($newUsername)|| strlen($newUsername) < 3 || strlen($newUsername) > 20)
		{
		echo "<p>K&auml;ytt&auml;j&auml;tunnus liian lyhyt (min. 3 merkki&auml;).</p>";
		$error = 1;
		}
	if(empty($newPassword) || strlen($newPassword) <8 || strlen($newPassword) > 20)
		{
		echo "<p>Salasana liian lyhyt (min. 8 merkki&auml;).</p>";
		$error = 1;
		}
	if(empty($newName))
		{
		echo "<p>Nimi ei voi olla tyhj&auml;.</p>";
		$error = 1;
		}
	
	$uidcheck = mysql_real_escape_string($newUsername);
	
	if($uidcheck != $newUsername)
		{
		echo "<p>Laittomia merkkej&auml; k&auml;ytt&auml;j&auml;tunnuksessa.</p>";
		$error = 1;
		}
		
	$pswcheck = mysql_real_escape_string($newPassword);
	
	if($pswcheck != $newPassword)
		{
		echo "<p>Laittomia merkkej&auml; salasanassa.</p>";
		$error = 1;
		}
	
	if(!$error)
		{
		$suceess = UserUpdateInfo($_SESSION['id'],$newUsername, $newPassword, $newName, $newEmail);
		if($suceess)
			{
			$_SESSION['uid'] = mysql_real_escape_string($newUsername);
			$_SESSION['pwd'] = mysql_real_escape_string($newPassword);
			$_SESSION['user'] = mysql_real_escape_string($newName);
			}
		}
	
	if(!$error)
		{
		echo "<p>Tiedot tallennettu!</p><hr/>";
		}
	else
		{
		echo "<p>Tietoja EI tallennettu!</p><hr/>";
		}
	}

$userinfo = UserInfo($_SESSION['id']);

//double check to have valid user
if($userinfo['userid'] != $_SESSION['uid'] || $userinfo['password'] != $_SESSION['pwd'])
	{
	exit("problem to receive user information");
	}
echo "<form method='post' action='userinfo.php'>";
	
echo "<table cellpadding='8px'>
	<tr><td class='infocell'>Nimi:</td>
		<td><input class='input' maxlength='256' id='Name' name='Name' value='".$userinfo['nimi']."'/></td></tr>
	<tr><td class='infocell'>K&auml;ytt&auml;j&auml;tunnus:</td>
		<td><input class='input' maxlength='20' id='UserName' name='UserName' value='".$userinfo['userid']."'/></td></tr>
	<tr><td class='infocell'>Salasana:</td>
		<td><input class='input' maxlength='20' id='Password' name='Password' value='".$userinfo['password']."'/></td></tr>
	<tr><td class='infocell'>S&auml;hk&ouml;posti:</td>
		<td><input class='input' maxlength='512' id='Email' name='Email' size='40' value='".$userinfo['email']."'/></td></tr>
	<tr><td class='infocell'>Joukkue:</td>
		<td>".htmlentities(TeamName($userinfo['team']))."</td></tr>		
	<tr><td class='infocell'>Yll&auml;pit&auml;j&auml;:</td><td>";
if(intval($userinfo['admin']))
	{
	echo "kyll&auml;</td></tr>
		<tr><td class='infocell'>Kausi</td>
		<td>".$userinfo['kausi']."</td></tr>";
	}
else
	{	
		echo "ei</td></tr>";
	}

echo "<tr><td colspan = '2' align='right'><br/>
      <input class='button' type='submit' name='save' value='Tallenna' />
      <input class='button' type='submit' name='cancel' value='Peruuta' />
      </td></tr>\n";

			
echo "</table>\n";
echo "</form>";


CloseConnection();
//common end
contentEnd();
pageEnd();
?>