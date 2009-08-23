<?php 
include_once '../lib/database.php';
include_once '../lib/season.functions.php';
include_once '../lib/serie.functions.php';

include_once 'usermenubuilder.php';

function pageTop($printable=false)
	{
	pageTopHeadOpen();
	pageTopHeadClose($printable);
	}
function pageTopHeadOpen()
	{
	include 'validate_user.php'; //just to make sure that check is made in each page	
	echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
		<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='fi' lang='fi'>
		<head>
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />
		<title>Liitokiekkoliiton Pelikone</title>
		<link rel=\"stylesheet\" href=\"../styles/layout.css\" type=\"text/css\" />
		<link rel=\"stylesheet\" href=\"../styles/font.css\" type=\"text/css\" />
		<link rel=\"stylesheet\" href=\"../styles/default.css\" type=\"text/css\" />\n";
	}

function pageTopHeadClose($printable=false)
	{
		
	echo "</head>
		<body>
		<div class='page_top'>\n";
		
	if(!$printable)
		{
		echo "
		<table>
		<tr>
		<td>
		<div class='header_left'>
			<a href='index.php'>
			<img class='header_logo' src='../images/slkllogo.gif' alt='SLKL'/>
			</a>
		</div>
		</td>
		<td>
		<div class='header_middle'>
			<a href='http://www.liitokiekkoliitto.fi/' class='header_text'>Suomen Liitokiekkoliitto</a><br/>
			<a href='http://www.liitokiekkoliitto.fi/english' class='header_text_small'>Finnish Flying Disc Association</a>
		</div>
		</td>
		</tr>
		</table>\n";
		}
	
	echo "</div><div class='page_middle'>\n";
	}
	
function pageEnd()
	{
	echo "</body></html>";
	}	
	
function leftMenu($id, $printable=false)
	{
	if($printable)
		{
		echo "<table><tr>";
		return;
		}
		
	echo "<table><tr>
		  <td class='menu_left'>
		  <form method='post' action='authenticate.php'>
		  <table cellspacing='5' cellpadding='2'>\n";
	
	echo "<tr><td><a class=\"nav\" href=\"../timetables.php\">Peliaikataulut</a></td></tr>\n";
	echo "<tr><td><a class=\"nav\" href=\"../played.php\">Pelatut pelit</a></td></tr>\n";
	echo "<tr><td><a class=\"nav\" href=\"../teams.php\">Joukkueet</a></td></tr>\n";	
	
	OpenConnection();
	$curseason = CurrenSeason();
	$series = Series($curseason);
	
	echo "<tr><td class='menuseparator'></td></tr><tr><td>Sarjatilanteet:</td></tr>
	<tr><td style='padding-left:5px'>\n";	
	while($row = mysql_fetch_assoc($series))
		{
		echo "<a href='../seriestatus.php?Serie=".$row['sarja_id']."'>&raquo; ".htmlentities($row['nimi'])."</a><br/>\n";
		}
		
	echo "</td></tr><tr><td class='menuseparator'></td></tr>";

	//build user menu
	userMenu($id);

	echo "<tr><td class='menuseparator'></td></tr>\n";
	
	echo "<tr><td>".CurrenSeasonName()."</td></tr>\n";
	echo "<tr><td><a href=\"../seasonlist.php\">&raquo; Vanhat kaudet</a></td></tr>\n";	
	echo "<tr><td class='menuseparator'></td></tr>\n";
	
	echo "<tr><td><a href=\"../ext/index.php\">&raquo; Pelikone linkit</a></td></tr>\n";	
	echo "<tr><td style='height:100px'></td></tr>\n";
	echo "<tr><td>";
	echo "<a href='http://www.liitokiekkoliitto.fi/'>Suomen Liitokiekkoliitto</a><br/>\n";
	echo "<a href='mailto:pelikone@liitokiekkoliitto.fi'>Yll&auml;pito</a><br/>";
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