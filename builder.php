<?php 
include_once 'lib/database.php';
include_once 'lib/season.functions.php';
include_once 'lib/serie.functions.php';

include_once 'user/usermenubuilder.php';

function pageTop($printable)
	{
	pageTopHeadOpen();
	pageTopHeadClose($printable);
	}

function pageTopHeadOpen()
	{
		
	echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
		<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='fi' lang='fi'>
		<head>
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />
		<title>"._("Liitokiekkoliiton Pelikone")."</title>
		<link rel=\"stylesheet\" href=\"styles/layout.css\" type=\"text/css\" />
		<link rel=\"stylesheet\" href=\"styles/font.css\" type=\"text/css\" />
		<link rel=\"stylesheet\" href=\"styles/default.css\" type=\"text/css\" />\n";
	}

function pageTopHeadClose($printable)
	{
		
	echo "</head>
		<body>
		<div class='page_top'>\n";
		
	if(!$printable)
		{
		include_once('header.php');
		}
	
	echo "</div><div class='page_middle'>\n";
	}
	
function pageEnd()
	{
	echo "</body></html>";
	}	
	
function leftMenu($id, $printable)
	{
	if($printable)
		{
		echo "<table><tr>";
		return;
		}
	echo "<table><tr>
		  <td class='menu_left'>
		  <form method='post' action='user/authenticate.php'>
		  <table cellspacing='5' cellpadding='2'>\n";
	
	echo "<tr><td><a class=\"nav\" href=\"timetables.php\">"._("Peliaikataulut")."</a></td></tr>\n";
	echo "<tr><td><a class=\"nav\" href=\"played.php\">"._("Pelatut pelit")."</a></td></tr>\n";
	echo "<tr><td><a class=\"nav\" href=\"teams.php\">"._("Joukkueet")."</a></td></tr>\n";	
	
	OpenConnection();
	$curseason = CurrenSeason();
	$series = Series($curseason);
	
	echo "<tr><td class='menuseparator'></td></tr><tr><td>"._("Sarjatilanteet").":</td></tr>
	<tr><td style='padding-left:5px'>\n";	
	while($row = mysql_fetch_assoc($series))
		{
		echo "<a href='seriestatus.php?Serie=".$row['sarja_id']."'>&raquo; ".$row['nimi']."</a><br/>\n";
		}
		
	echo "</td></tr><tr><td class='menuseparator'></td></tr>";
	
	//build user menus if user has logged in
	userMenu($id);
	
	echo "<tr><td class='menuseparator'></td></tr>\n";
	
	echo "<tr><td>".CurrenSeasonName()."</td></tr>\n";
	echo "<tr><td><a href=\"seasonlist.php\">&raquo; "._("Vanhat kaudet")."</a></td></tr>\n";	
	echo "<tr><td class='menuseparator'></td></tr>\n";
	
	echo "<tr><td><a href=\"ext/index.php\">&raquo; "._("Pelikone linkit")."</a></td></tr>\n";	
	echo "<tr><td style='height:100px'></td></tr>\n";
	echo "<tr><td>";
	echo "<a href='"._("http://www.liitokiekkoliitto.fi/")."'>"._("Suomen Liitokiekkoliitto")."</a><br/>\n";
	echo "<a href='mailto:"._("pelikone@liitokiekkoliitto.fi")."'>"._("Yll&auml;pito")."</a><br/>";
	echo "</td></tr>\n";
	
	echo "<tr><td class='menuseparator'></td></tr>\n";
	echo "<tr><td>&copy; SLKL 2009</td></tr>\n";
		
	echo "</table></form></td>\n";

	CloseConnection();
	}

	
function contentStart()
	{
	echo "\n<td align='left' valign='top'><div class='content'>\n";
	}
	
function contentEnd()
	{
	echo "\n</div></td></tr></table></div>\n";
	}
?>
