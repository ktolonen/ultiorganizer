<?php
include_once 'view_ids.inc.php';
include_once 'builder.php';
include_once '../lib/database.php';
include_once '../lib/season.functions.php';
include_once '../lib/common.functions.php';
include_once '../lib/serie.functions.php';
include_once '../lib/team.functions.php';

$LAYOUT_ID = EXTINDEX;

//common page
pageTop();
leftMenu($LAYOUT_ID);
contentStart();
OpenConnection();

$seltournament;
$selserie;
$selteam;

$baseurl = GetURLBase();

$styles = array(urlencode("$baseurl/pelikone.css"),urlencode("$baseurl/black.css"),urlencode("$baseurl/noborder.css"));
$stylenames = array("oletus","mustavalkoinen","rajaton");
$update = $_POST['update'];

if(isset($update))
	{
	$selstyle = $_POST['ownstyle'];
	if(empty($selstyle) || strlen($selstyle)<8)
		$selstyle = $_POST['style'];
	else
		$selstyle = urlencode($_POST['ownstyle']);
		
	$seltournament = $_POST['tournamentname'];
	$selserie = $_POST['serieid'];
	$selteam = $_POST['teamid'];
	}
	
//content
?>
<h1>Pelikoneen linkitys muille sivuille</h1>
<p>Voit linkitt&auml;&auml; seuraavia tilastoja pelikoneesta suoraan omille sivuillesi.</p>

<form method='post' action='index.php'>
<p>Valitse tyyli:
<select class='dropdown' name="style">
<?php
if(empty($selstyle))
	$selstyle=$styles[0];
		
for($i=0;$i<count($styles);$i++) 
	{
	if($selstyle == $styles[$i])
		echo "<option class='dropdown' selected='selected' value='$styles[$i]'>$stylenames[$i]</option>";
	else
		echo "<option class='dropdown' value='$styles[$i]'>$stylenames[$i]</option>";
	}	
?>
</select>
<br/>
tai linkki omaan tyylim&auml;&auml;rittelyyn: 
<?php
$lastown = $_POST['ownstyle'];
if(empty($lastown))
	$lastown='http://';
echo "<input class='input' size='50' name='ownstyle' value='$lastown'/>";
?>
<br/>
<input class='button' type='submit' name='update' value='P&auml;ivit&auml;' />
</p>

<h2>Turnauksen kaikki pelit</h2>

<p>Valitse turnaus:
<select class='dropdown' name="tournamentname">
<?php
$season = CurrenSeason();
$tours = TournamentNames($season);

while($row = mysql_fetch_assoc($tours))
	{
	if(empty($seltournament))
		$seltournament=$row['Turnaus'];
		
	if($row['Turnaus'] == $seltournament)
		echo "<option class='dropdown' selected='selected' value='". $row['Turnaus'] . "'>". $row['Turnaus'] ."</option>";
	else
		echo "<option class='dropdown' value='". $row['Turnaus'] . "'>". $row['Turnaus'] ."</option>";
	}
	
?>
</select>
<input class='button' type='submit' name='update' value='P&auml;ivit&auml;' />
</p>
<?php
echo "<p class='highlight' ><code>
	&lt;object data='$baseurl/tournament.php?Tournament=$seltournament&amp;Style=$selstyle' <br/>
	type='text/html' width='300px' height='300px'&gt;&lt;/object&gt;
	</code></p>\n";
	
echo "<p><object data='$baseurl/tournament.php?Tournament=$seltournament&amp;Style=$selstyle' type='text/html' width='300px' height='300px'></object></p>\n";
?>

<h2>Sarjatilanne ja pistep&ouml;rssi</h2>
<p>Valitse sarja:
<select class='dropdown' name="serieid">
<?php
$season = CurrenSeason();
$series = Series($season);

while($row = mysql_fetch_assoc($series))
	{
	if(empty($selserie))
		$selserie=$row['sarja_id'];
		
	if($row['sarja_id'] == $selserie)
		echo "<option class='dropdown' selected='selected' value='". $row['sarja_id'] . "'>". $row['nimi'] ."</option>";
	else
		echo "<option class='dropdown' value='". $row['sarja_id'] . "'>". $row['nimi'] ."</option>";
	}
?>
</select>
<input class='button' type='submit' name='update' value='P&auml;ivit&auml;' />
</p>
<?php
echo "<p class='highlight' ><code>
	&lt;object data='$baseurl/seriestatus.php?Serie=$selserie&amp;Style=$selstyle' <br/>
	type='text/html' width='300px' height='200px'&gt;&lt;/object&gt;
	</code></p>\n";
	
echo "<p><object data='$baseurl/seriestatus.php?Serie=$selserie&amp;Style=$selstyle' type='text/html' width='400px' height='200px'></object></p>\n";

echo "<p class='highlight' ><code>
	&lt;object data='$baseurl/seriescoreboard.php?Serie=$selserie&amp;Style=$selstyle' <br/>
	type='text/html' width='300px' height='200px'&gt;&lt;/object&gt;
	</code></p>\n";
	
echo "<p><object data='$baseurl/seriescoreboard.php?Serie=$selserie&amp;Style=$selstyle' type='text/html' width='400px' height='200px'></object></p>\n";

?>


<h2>Joukkuueen pelatut ja tulevat pelit sek&auml; pistep&ouml;rssi</h2>

<p>Valitse joukkue:
<select class='dropdown' name="teamid">
<?php
$season = CurrenSeason();
$series = Series($season);

while($serie = mysql_fetch_assoc($series))
	{
	$teams = Teams($serie['sarja_id']);

	while($row = mysql_fetch_assoc($teams))
		{
		if(empty($selteam))
			$selteam=$row['Joukkue_ID'];
		
		if($row['Joukkue_ID'] == $selteam)
			echo "<option class='dropdown' selected='selected' value='". $row['Joukkue_ID'] . "'>". $row['Nimi'] ." (".$serie['nimi'] .")</option>";
		else
			echo "<option class='dropdown' value='". $row['Joukkue_ID'] . "'>". $row['Nimi'] ." (".$serie['nimi'] .")</option>";
		}
	}
?>
</select>
<input class='button' type='submit' name='update' value='P&auml;ivit&auml;' />
</p>

<?php
echo "<p class='highlight' ><code>
	&lt;object data='$baseurl/teamplayed.php?Team=$selteam&amp;Style=$selstyle' <br/>
	type='text/html' width='300px' height='300px'&gt;&lt;/object&gt;
	</code></p>\n";
	
echo "<p><object data='$baseurl/teamplayed.php?Team=$selteam&amp;Style=$selstyle' type='text/html' width='300px' height='300px'></object></p>\n";

echo "<p class='highlight' ><code>
	&lt;object data='$baseurl/teampcoming.php?Team=$selteam&amp;Style=$selstyle' <br/>
	type='text/html' width='300px' height='300px'&gt;&lt;/object&gt;
	</code></p>\n";
	
echo "<p><object data='$baseurl/teamcoming.php?Team=$selteam&amp;Style=$selstyle' type='text/html' width='300px' height='300px'></object></p>\n";

echo "<p class='highlight' ><code>
	&lt;object data='$baseurl/teamscoreboard.php?Team=$selteam&amp;Style=$selstyle' <br/>
	type='text/html' width='300px' height='200px'&gt;&lt;/object&gt;
	</code></p>\n";
	
echo "<p><object data='$baseurl/teamscoreboard.php?Team=$selteam&amp;Style=$selstyle' type='text/html' width='300px' height='200px'></object></p>\n";
?>

<?php
CloseConnection();
contentEnd();
pageEnd();
?>