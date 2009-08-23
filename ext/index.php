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
pageTop(false);
leftMenu($LAYOUT_ID);
contentStart();
OpenConnection();

$seltournament;
$selserie;
$selteam;
$season;

$baseurl = GetURLBase();

$styles = array(urlencode("$baseurl/pelikone.css"),urlencode("$baseurl/black.css"),urlencode("$baseurl/noborder.css"));
$stylenames = array("oletus","mustavalkoinen","rajaton");


if(!empty($_POST['update']))
	{
	$selstyle = $_POST['ownstyle'];
	if(empty($selstyle) || strlen($selstyle)<8)
		$selstyle = $_POST['style'];
	else
		$selstyle = urlencode($_POST['ownstyle']);
		
	$seltournament = $_POST['tournamentname'];
	$selserie = $_POST['serieid'];
	$selteam = $_POST['teamid'];
	$season = $_POST['season'];
	}
	
//content
?>
<h1>Pelikoneen linkitys muille sivuille</h1>
<p>Voit linkitt&auml;&auml; seuraavia tilastoja pelikoneesta suoraan omille sivuillesi.</p>

<h2>Tyyli</h2>
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
$lastown='http://';
if(!empty($_POST['ownstyle']))
	$lastown = $_POST['ownstyle'];
echo "<input class='input' size='50' name='ownstyle' value='$lastown'/>";
?>
<br/>
<input class='button' type='submit' name='update' value='P&auml;ivit&auml;' />
</p>

<h2>Kausi</h2>
<p>Valitse kausi:
<select class='dropdown' name="season">
<?php
if(empty($season))
	$season = CurrenSeason();

$seasons = Seasons();
		
while($row = mysql_fetch_assoc($seasons))
	{
	$aYear = strtok($row['kausi'], "."); 
	$aSeason = strtok(".");
	$name = $row['kausi'];
	
	if ($aSeason == "1")
		$name = "Kes&auml; $aYear";
	elseif ($aSeason == "2")
		$name = "Talvi $aYear";
		
	if($row['kausi'] == $season)
		echo "<option class='dropdown' selected='selected' value='". $row['kausi'] . "'>". $name ."</option>";
	else
		echo "<option class='dropdown' value='". $row['kausi'] . "'>". $name ."</option>";
	}
?>
</select>
<br/>
<input class='button' type='submit' name='update' value='P&auml;ivit&auml;' />
</p>

<h2>Turnauksen kaikki pelit</h2>

<p>Valitse turnaus:
<select class='dropdown' name="tournamentname">
<?php
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
	&lt;object data='$baseurl/tournament.php?Tournament=$seltournament&amp;Season=$season&amp;Style=$selstyle' <br/>
	type='text/html' width='300px' height='300px'&gt;&lt;/object&gt;
	</code></p>\n";
	
echo "<p><object data='$baseurl/tournament.php?Tournament=$seltournament&amp;Season=$season&amp;Style=$selstyle' type='text/html' width='300px' height='300px'></object></p>\n";
?>

<h2>Sarjatilanne ja pistep&ouml;rssi</h2>
<p>Valitse sarja:
<select class='dropdown' name="serieid">
<?php
$series = Series($season);

while($row = mysql_fetch_assoc($series))
	{
	if(empty($selserie))
		$selserie=$row['sarja_id'];
		
	if($row['sarja_id'] == $selserie)
		echo "<option class='dropdown' selected='selected' value='". $row['sarja_id'] . "'>". htmlentities($row['nimi']) ."</option>";
	else
		echo "<option class='dropdown' value='". $row['sarja_id'] . "'>". htmlentities($row['nimi']) ."</option>";
	}
?>
</select>
<input class='button' type='submit' name='update' value='P&auml;ivit&auml;' />
</p>
<?php
echo "<p class='highlight' ><code>
	&lt;object data='$baseurl/seriestatus.php?Serie=$selserie&amp;Season=$season&amp;Style=$selstyle' <br/>
	type='text/html' width='300px' height='200px'&gt;&lt;/object&gt;
	</code></p>\n";
	
echo "<p><object data='$baseurl/seriestatus.php?Serie=$selserie&amp;Season=$season&amp;Style=$selstyle' type='text/html' width='400px' height='200px'></object></p>\n";

echo "<p class='highlight' ><code>
	&lt;object data='$baseurl/seriescoreboard.php?Serie=$selserie&amp;Season=$season&amp;Style=$selstyle' <br/>
	type='text/html' width='300px' height='200px'&gt;&lt;/object&gt;
	</code></p>\n";
	
echo "<p><object data='$baseurl/seriescoreboard.php?Serie=$selserie&amp;Season=$season&amp;Style=$selstyle' type='text/html' width='400px' height='200px'></object></p>\n";

?>


<h2>Joukkuueen pelatut ja tulevat pelit sek&auml; pistep&ouml;rssi</h2>

<p>Valitse joukkue:
<select class='dropdown' name="teamid">
<?php
$series = Series($season);

while($serie = mysql_fetch_assoc($series))
	{
	$teams = Teams($serie['sarja_id']);

	while($row = mysql_fetch_assoc($teams))
		{
		if(empty($selteam))
			$selteam=$row['Joukkue_ID'];
		
		if($row['Joukkue_ID'] == $selteam)
			echo "<option class='dropdown' selected='selected' value='". $row['Joukkue_ID'] . "'>". htmlentities($row['Nimi']) ." (".htmlentities($serie['nimi']) .")</option>";
		else
			echo "<option class='dropdown' value='". $row['Joukkue_ID'] . "'>". htmlentities($row['Nimi']) ." (".htmlentities($serie['nimi']) .")</option>";
		}
	}
?>
</select>
<input class='button' type='submit' name='update' value='P&auml;ivit&auml;' />
</p>

<?php
echo "<p class='highlight' ><code>
	&lt;object data='$baseurl/teamplayed.php?Team=$selteam&amp;Season=$season&amp;Style=$selstyle' <br/>
	type='text/html' width='300px' height='300px'&gt;&lt;/object&gt;
	</code></p>\n";
	
echo "<p><object data='$baseurl/teamplayed.php?Team=$selteam&amp;Season=$season&amp;Style=$selstyle' type='text/html' width='300px' height='300px'></object></p>\n";

echo "<p class='highlight' ><code>
	&lt;object data='$baseurl/teampcoming.php?Team=$selteam&amp;Season=$season&amp;Style=$selstyle' <br/>
	type='text/html' width='300px' height='300px'&gt;&lt;/object&gt;
	</code></p>\n";
	
echo "<p><object data='$baseurl/teamcoming.php?Team=$selteam&amp;Season=$season&amp;Style=$selstyle' type='text/html' width='300px' height='300px'></object></p>\n";

echo "<p class='highlight' ><code>
	&lt;object data='$baseurl/teamscoreboard.php?Team=$selteam&amp;Season=$season&amp;Style=$selstyle' <br/>
	type='text/html' width='300px' height='200px'&gt;&lt;/object&gt;
	</code></p>\n";
	
echo "<p><object data='$baseurl/teamscoreboard.php?Team=$selteam&amp;Season=$season&amp;Style=$selstyle' type='text/html' width='300px' height='200px'></object></p>\n";
?>
</form>
<?php
CloseConnection();
contentEnd();
pageEnd();
?>