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

echo "<h1>"._("Pelikoneen linkitys muille sivuille")."</h1>
	<p>"._("Voit linkitt&auml;&auml; seuraavia tilastoja pelikoneesta suoraan omille sivuillesi").".</p>
	<h2>"._("Tyyli")."</h2>
	<form method='post' action='index.php'>
	<p>"._("Valitse tyyli").":
	<select class='dropdown' name='style'>\n";

if(empty($selstyle))
	$selstyle=$styles[0];
		
for($i=0;$i<count($styles);$i++) 
	{
	if($selstyle == $styles[$i])
		echo "<option class='dropdown' selected='selected' value='$styles[$i]'>$stylenames[$i]</option>";
	else
		echo "<option class='dropdown' value='$styles[$i]'>$stylenames[$i]</option>";
	}	
echo "</select>
	<br/>"._("tai linkki omaan tyylim&auml;&auml;rittelyyn").":\n";"
$lastown='http://';
if(!empty($_POST['ownstyle']))
	$lastown = $_POST['ownstyle'];
echo "<input class='input' size='50' name='ownstyle' value='$lastown'/>";
?>
<br/>
<input class='button' type='submit' name='update' value='"._("P&auml;ivit&auml;")."' />
</p>

<h2>"._("Kausi")."</h2>
<p>"._("Valitse kausi").":
<select class='dropdown' name='season'>\n";

if(empty($season))
	$season = CurrenSeason();

$seasons = Seasons();
		
while($row = mysql_fetch_assoc($seasons))
	{
	$aYear = strtok($row['kausi'], "."); 
	$aSeason = strtok(".");
	$name = $row['kausi'];
	
	if ($aSeason == "1")
		$name = _("Kes&auml;")." $aYear";
	elseif ($aSeason == "2")
		$name = _("Talvi")." $aYear";
		
	if($row['kausi'] == $season)
		echo "<option class='dropdown' selected='selected' value='". $row['kausi'] . "'>". $name ."</option>";
	else
		echo "<option class='dropdown' value='". $row['kausi'] . "'>". $name ."</option>";
	}

echo "</select><br/>
	<input class='button' type='submit' name='update' value='"._("P&auml;ivit&auml;")."' />
	</p>
	<h2>"._("Turnauksen kaikki pelit")."</h2>
	<p>"._("Valitse turnaus").":
	<select class='dropdown' name='tournamentname'>\n";

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
	
echo "</select>
	<input class='button' type='submit' name='update' value='"._("P&auml;ivit&auml;")."' />
	</p>\n";

echo "<p class='highlight' ><code>
	&lt;object data='$baseurl/tournament.php?Tournament=$seltournament&amp;Season=$season&amp;Style=$selstyle' <br/>
	type='text/html' width='300px' height='300px'&gt;&lt;/object&gt;
	</code></p>\n";
	
echo "<p><object data='$baseurl/tournament.php?Tournament=$seltournament&amp;Season=$season&amp;Style=$selstyle' type='text/html' width='300px' height='300px'></object></p>\n";


echo "<h2>"._("Sarjatilanne ja pistep&ouml;rssi")."</h2>
	<p>"._("Valitse sarja").":
	<select class='dropdown' name='serieid'>\n";
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

echo "</select>
	<input class='button' type='submit' name='update' value='"._("P&auml;ivit&auml;")."' />
	</p>\n";

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

echo "<h2>"._("Joukkuueen pelatut ja tulevat pelit sek&auml; pistep&ouml;rssi")."</h2>\n";

echo "<p>"._("Valitse joukkue").":
	<select class='dropdown' name='teamid'>\n";

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
echo "</select>
	<input class='button' type='submit' name='update' value='"._("P&auml;ivit&auml;")."' />
	</p>\n";

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
echo "</form>\n";

CloseConnection();
contentEnd();
pageEnd();
?>
