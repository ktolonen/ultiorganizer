<?php
include_once 'view_ids.inc.php';
include_once 'builder.php';
include_once '../lib/database.php';
include_once '../lib/team.functions.php';
include_once '../lib/player.functions.php';
include_once '../lib/common.functions.php';

include_once 'lib/user.functions.php';
include_once 'lib/team.functions.php';
$LAYOUT_ID = TEAMPLAYERS;

//common page
pageTopHeadOpen();
include_once 'lib/disable_enter.js.inc';
pageTopHeadClose();

leftMenu($LAYOUT_ID);
contentStart();
//content
OpenConnection();
$teamId=0;
$gameId=0;

if(!empty($_GET["Team"]))
	$teamId = intval($_GET["Team"]);

//set when called from addplayerlist view
if(!empty($_GET["Game"]))
	$gameId = intval($_GET["Game"]);

if(empty($teamId))
	{
	$userinfo = UserInfo($_SESSION['id']);
	$teamId = $userinfo['team'];
	}
	
$teaminfo = TeamInfo($teamId);

//process itself if remove button was pressed
if(!empty($_POST['remove']))
	{
	
	foreach($_POST["delcheck"] as $delId) 
		{
		$games = PlayerPlayedGames($delId, $teaminfo['sarja']);
		if($games)
			{
			$memberinfo = MembershipInfo($delId);
			echo "<div style='width:500px'>
				<p class='warning'><i>". htmlentities($memberinfo['enimi'] ." ". $memberinfo['snimi']) ."</i> ei voida poistaa pelaajalistalta. 
				Pelannut joukkueessa $games peli&auml;.</p></div>";
			}
		else
			{
			RemovePlayer($delId);
			}
		}
	}

//process itself if add button was pressed
if(!empty($_POST['add']))
	{
	
	foreach($_POST["addcheck"] as $addId) 
		{
		$found=false;
		
		//make sure that player is not already added on team's playerlist
		$team_players = TeamPlayerList($teamId);
		while($player = mysql_fetch_assoc($team_players))
			{
			$memberinfo = MembershipInfo($player['pelaaja_id']);
			if($memberinfo['jnro'] == $addId)
				{
				echo "<div style='width:500px'>
				<p><i>". htmlentities($memberinfo['enimi'] ." ". $memberinfo['snimi']) ."</i> oli jo pelaajalistalla.</p></div>";
				$found=true;
				}
			}
			
		if(!$found)
			{
			AddPlayer($teamId, $addId);
			}
		}
	}

echo "<h2>Pelaajalista: ". htmlentities($teaminfo['nimi']) ."</h2>\n";

echo "<form method='post' action='teamplayers.php?Team=$teamId&amp;Game=$gameId'>\n";
echo "<table border='0' cellpadding='2' width='500px'>\n";

echo "<tr><th>Jnro</th><th>Syntym&auml;aika</th><th>Nimi</th><th>J&auml;senmaksu</th><th>Lisenssi</th><th>
<input class='button' name='remove' type='submit' value='Poista'/></th></tr>\n";

$team_players = TeamPlayerList($teamId);

while($player = mysql_fetch_assoc($team_players))
	{
	$memberinfo = MembershipInfo($player['pelaaja_id']);
	
	echo "<tr>
		<td>". $memberinfo['jnro'] ."</td> 
		<td>". DefBirthdayFormat($memberinfo['saika']) ."</td>
		<td>". htmlentities($memberinfo['enimi'] ." ". $memberinfo['snimi']) ."</td>
		<td>". $memberinfo['JMaksu'] ."</td>
		<td>". $memberinfo['Lisenssi'] ."</td>
		<td style='text-align: center;'><input type='checkbox' name='delcheck[]' value='".$player['pelaaja_id']."'/></td>
		</tr>\n";
	}

echo "</table></form>\n";

if(!empty($gameId))
		{
		echo "<p><a href='addplayerlists.php?Game=$gameId'>Takaisin sy&ouml;tt&auml;m&auml;&auml;n pelinumeroita</a></p>";
		}
		
echo "<hr/>\n";

//if NOT search pressed
if(empty($_POST['search']))
	{

	echo "
	<h2>Lis&auml;&auml; pelaajia</h2>
	<div style='width:500px'>
	<p>Voit lis&auml;t&auml; pelaajia joko j&auml;sennumeron, syntym&auml;ajan (pp.kk.vvvv) tai nimen perusteella.
	 Voit lis&auml;t&auml; useamman kuin yhden pelaajaa kerrallaan.</p>
	</div>

	<form method='post' action='teamplayers.php?Team=$teamId&amp;Game=$gameId'>

	<table border='0' cellpadding='2px' cellspacing='0' width='100%'>
	  <tr>
		<th>Jnro</th>
		<th>Syntym&auml;aika</th>
		<th>Etunimi</th>
		<th>Sukunimi</th>
	  </tr>
	  <tr>
		<td><input class='input' name='member1' style='WIDTH: 50px' size='5'/></td>
		<td><input class='input' name='birth1' style='WIDTH: 80px' size='10'/></td>
		<td><input class='input' name='fname1' style='WIDTH: 170px' size='10'/></td>
		<td><input class='input' name='lname1' style='WIDTH: 170px'/></td>
	  </tr>
	  <tr>
		<td><input class='input' name='member2' style='WIDTH: 50px' size='5'/></td>
		<td><input class='input' name='birth2' style='WIDTH: 80px' size='10'/></td>
		<td><input class='input' name='fname2' style='WIDTH: 170px' size='10'/></td>
		<td><input class='input' name='lname2' style='WIDTH: 170px'/></td>
	  </tr>
	  <tr>
		<td><input class='input' name='member3' style='WIDTH: 50px' size='5'/></td>
		<td><input class='input' name='birth3' style='WIDTH: 80px' size='10'/></td>
		<td><input class='input' name='fname3' style='WIDTH: 170px' size='10'/></td>
		<td><input class='input' name='lname3' style='WIDTH: 170px'/></td>
	  </tr>
	  <tr>
		<td><input class='input' name='member4' style='WIDTH: 50px' size='5'/></td>
		<td><input class='input' name='birth4' style='WIDTH: 80px' size='10'/></td>
		<td><input class='input' name='fname4' style='WIDTH: 170px' size='10'/></td>
		<td><input class='input' name='lname4' style='WIDTH: 170px'/></td>
	  </tr>
	<tr>
		<td><input class='input' name='member5' style='WIDTH: 50px' size='5'/></td>
		<td><input class='input' name='birth5' style='WIDTH: 80px' size='10'/></td>
		<td><input class='input' name='fname5' style='WIDTH: 170px' size='10'/></td>
		<td><input class='input' name='lname5' style='WIDTH: 170px'/></td>
	  </tr>  
	 </table>
	<p>    
		<input class='button' type='submit' name='search' value='Hae pelaajat'/>
		<input class='button' type='submit' name='clear' value='Tyhjenn&auml;'/>
	</p>
	</form>";
		
	}
else
	{
	echo 
	"<h2>Valitse lis&auml;tt&auml;v&auml;t pelaajat</h2>

	<form method='post' action='teamplayers.php?Team=$teamId&amp;Game=$gameId'>

	<table border='0' cellpadding='2px' cellspacing='0' width='100%'>	
	<tr><th>Lis&auml;&auml;</th><th>Jnro</th><th>Syntym&auml;aika</th><th>Nimi</th><th>J&auml;senmaksu</th><th>Lisenssi</th></tr>\n";
	
	//get given values, search from database and prints
	$players = PlayerSearch($_POST['member1'],$_POST['birth1'],$_POST['fname1'],$_POST['lname1']);
	PrintPlayers($players);
	$players = PlayerSearch($_POST['member2'],$_POST['birth2'],$_POST['fname2'],$_POST['lname2']);
	PrintPlayers($players);
	$players = PlayerSearch($_POST['member3'],$_POST['birth3'],$_POST['fname3'],$_POST['lname3']);
	PrintPlayers($players);
	$players = PlayerSearch($_POST['member4'],$_POST['birth4'],$_POST['fname4'],$_POST['lname4']);
	PrintPlayers($players);
	$players = PlayerSearch($_POST['member5'],$_POST['birth5'],$_POST['fname5'],$_POST['lname5']);
	PrintPlayers($players);
	echo "</table>";
	echo "<p>    
		<input class='button' type='submit' name='add' value='Lis&auml;&auml;'/>
		<input class='button' type='submit' name='clear' value='Peruuta'/>
	</p></form>";
	}	
CloseConnection();
//common end
contentEnd();
pageEnd();

//prints players from given array
function PrintPlayers($players)
	{
	if($players)
		{
		while($player = mysql_fetch_assoc($players))
			{
			echo "<tr>
				<td style='text-align: center;'><input type='checkbox' name='addcheck[]' value='".$player['Jasennumero']."'/></td>
				<td>". $player['Jasennumero'] ."</td> 
				<td>". DefBirthdayFormat($player['SyntAika']) ."</td>
				<td>". htmlentities($player['Etunimi'] ." ". $player['Sukunimi']) ."</td>
				<td>". $player['Jasenmaksu'] ."</td>
				<td>". $player['Lisenssi'] ."</td>
				</tr>\n";
			}
		}
	}
?>