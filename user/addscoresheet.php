<?php
include_once 'view_ids.inc.php';
include_once 'builder.php';
include_once '../lib/database.php';
include_once '../lib/common.functions.php';
include_once '../lib/game.functions.php';
include_once '../lib/team.functions.php';
include_once '../lib/player.functions.php';
include_once '../lib/place.functions.php';

include_once 'lib/game.functions.php';
$LAYOUT_ID = ADDSCORESHEET;

$maxtimeouts = 6;
$maxscores = 41;

//common page
pageTopHeadOpen();
include_once 'lib/disable_enter.js.inc';
?>
<script type="text/javascript">
<!--
function validTime(field) 
	{
	field.value=field.value.replace(/[^0-9]/g, '.')
	}

function validNumber(field) 
	{
	field.value=field.value.replace(/[^0-9]/g, '')
	}

function validNumberX(field) 
	{
	field.value=field.value.replace(/[^0-9|^xX]/g, '')
	}
	
function updateScores(index) 
	{
	var i=0;
	var h=0;
	var a=0;
	
	for (i=0;i<<?php echo $maxscores;?>;i++)
		{
		var hradio = document.getElementById("hteam"+i);
		var aradio = document.getElementById("ateam"+i);
		
		if(hradio.checked)
			{
			h++;
			}
		else if(aradio.checked)
			{
			a++;
			}
		else
			{
			break;
			}
			
		var input = document.getElementById("sit"+i);
		input.value = h+" - "+a;
		}
	}
function eraseLast() 
	{
	var i=(<?php echo $maxscores;?>-1);
	
	for (i;i>=0;i=i-1)
		{
		var hradio = document.getElementById("hteam"+i);
		var aradio = document.getElementById("ateam"+i);
		
		if(aradio.checked || hradio.checked)
			{
			var input = document.getElementById("sit"+i);
			input.value = "";
			var input = document.getElementById("pass"+i);
			input.value = "";
			var input = document.getElementById("goal"+i);
			input.value = "";
			var input = document.getElementById("time"+i);
			input.value = "";
			aradio.checked=false;
			hradio.checked=false;
			break;
			}
		}
	}
//-->
</script>
<?php
pageTopHeadClose();
leftMenu($LAYOUT_ID);
contentStart();
//content
OpenConnection();
$gameId = intval($_GET["Game"]);

$game_result = GameResult($gameId);

//process itself if submit was pressed
if(!empty($_POST['save']))
	{
	$time_delim = array(",", ";", ":");
	//set score sheet keeper
	GameSetScoreSheetKeeper($gameId, $_POST['secretary']);
	
	//set halftime
	$htime = $_POST['halftime'];
	$htime = str_replace($time_delim,".",$htime);
	$htime = TimeToSec($htime);
	GameSetHalftime($gameId, $htime);
	
	//remove all old timeouts (if any)
	GameRemoveAllTimeouts($gameId);
	
	//insert home timeouts
	$j=0;
	for($i=0;$i<$maxtimeouts; $i++)
		{
		$time = $_POST['hto'.$i];
		$time = str_replace($time_delim,".",$time);
		
		if(!empty($time))
			{
			$j++;
			GameAddTimeout($gameId, $j, TimeToSec($time), 1);
			}
		}
		
	//insert away timeouts
	$j=0;
	for($i=0;$i<$maxtimeouts; $i++)
		{
		$time = $_POST['ato'.$i];
		$time = str_replace($time_delim,".",$time);
		
		if(!empty($time))
			{
			$j++;
			GameAddTimeout($gameId, $j, TimeToSec($time), 0);
			}
		}
	
	//remove all old scores (if any)
	GameRemoveAllScores($gameId);

	//insert scores
	$h=0;
	$a=0;
	$prevtime=0;
	for($i=0;$i<$maxscores; $i++)
		{
		$callahan = 0;
		$team="";
		$pass="";
		$goal="";
		$time="";
		if(!empty($_POST['team'.$i]))
			$team = $_POST['team'.$i];
		if(!empty($_POST['pass'.$i]))
			$pass = $_POST['pass'.$i];
		if(!empty($_POST['goal'.$i]))
			$goal = $_POST['goal'.$i];
		if(!empty($_POST['time'.$i]))
			$time = $_POST['time'.$i];
			
		$time = str_replace($time_delim,".",$time);
		$time = TimeToSec($time);
		if(!empty($team) && $time == $htime)
			echo "<p class='warning'>"._("Piste")." ",$i+1,": "._("aika ei voi olla sama kuin puoliajan p&auml;&auml;ttymisell&auml;")."!</p>";
			
		if(!empty($team) && $time <= $prevtime)
			echo "<p class='warning'>"._("Piste")." ",$i+1,": "._("aika ei voi olla sama tai aikaisempi kuin edellisess&auml; pisteess&auml;")."!</p>";
		
		if(strcasecmp($pass,'xx')==0 || strcasecmp($pass,'x')==0)
			$callahan = 1;
			
		$prevtime = $time;
			
		if(!empty($team) && $team=='H')
			{
			$h++;
			if(!$callahan)
				{
				$pass = GamePlayerFromNumber($gameId, $game_result['kotijoukkue'], $pass);
				if($pass==-1)
					echo "<p class='warning'>"._("Piste")." ",$i+1,": "._("sy&ouml;tt&auml;j&auml;n numeroa")." '".$_POST['pass'.$i]."' "._("ei pelaajalistalla")"!</p>";
				}
			else
				$pass=-1;
				
			$goal = GamePlayerFromNumber($gameId, $game_result['kotijoukkue'], $goal);
			if($goal==-1)
				echo "<p class='warning'>"._("Piste")." ",$i+1,": "._("maalintekij&auml;n numeroa")." '".$_POST['goal'.$i]."' "._("ei pelaajalistalla")."!</p>";
			
			if($pass==$goal)
				echo "<p class='warning'>"._("Piste")." ",$i+1,": "._("maalintekij&auml;ll&auml; ja sy&ouml;tt&auml;j&auml;ll&auml; sama numero")." '".$_POST['goal'.$i]."'!</p>";
				
			GameAddScore($gameId,$pass,$goal,$time,$i+1,$h,$a,1,$callahan);
			}
		elseif(!empty($team) && $team=='A')
			{
			$a++;
			if(!$callahan)
				{
				$pass = GamePlayerFromNumber($gameId, $game_result['vierasjoukkue'], $pass);
				if($pass==-1)
					echo "<p class='warning'>"._("Piste")." ",$i+1,": "._("sy&ouml;tt&auml;j&auml;n numeroa")." '".$_POST['pass'.$i]."' "._("ei pelaajalistalla")."!</p>";
				}
			else
				$pass=-1;
				
			$goal = GamePlayerFromNumber($gameId, $game_result['vierasjoukkue'], $goal);
			if($goal==-1)
				echo "<p class='warning'>"._("Piste")." ",$i+1,": "._("maalintekij&auml;n numeroa")." '".$_POST['goal'.$i]."' "._("ei pelaajalistalla")."!</p>";

			GameAddScore($gameId,$pass,$goal,$time,$i+1,$h,$a,0,$callahan);
			}
		}
	echo "<p>P&ouml;yt&auml;kirja tallennettu (klo. ".DefTimestamp().")!</p>";
	echo "<a href='../gameplay.php?Game=$gameId'>"._("Pelin kulku")."</a>";
	}
$game_result = GameResult($gameId);
$place = PlaceInfo($game_result['paikka']);


echo "<form action='addscoresheet.php?Game=$gameId' method='post'>";
echo "<table cellspacing='5' cellpadding='5'>";

echo "<tr><td colspan='2'><h1>"._("Suomen Liitokiekkoliitto")." - "._("Pelin p&ouml;yt&auml;kirja")."</h1></td></tr>";
echo "<tr><td valign='top'>\n";

//team, place, time info and scoresheet keeper's name
echo "<table cellspacing='0' width='100%' border='1'>";
echo "<tr><th>"._("Kotijoukkue")."</th></tr>";
echo "<tr><td>". htmlentities($game_result['KNimi']) ."</td></tr>";
echo "<tr><th>"._("Vierasjoukkue")."</th></tr>";
echo "<tr><td>". htmlentities($game_result['VNimi']) ."</td></tr>";
echo "<tr><th>"._("Paikka")."</th></tr>";
echo "<tr><td>". htmlentities($place['paikka']) ."</td></tr>";
echo "<tr><th>"._("Aika")."</th></tr>";
echo "<tr><td>". ShortDate($game_result['aika']) ." ". DefHourFormat($game_result['aika']) ."</td></tr>";
echo "<tr><th>"._("Toimitsija")."</th></tr>";
echo "<tr><td><input class='input' style='WIDTH: 90%' type='text' name='secretary' id='secretary' value='". $game_result['toim'] ."'/></td></tr>";
echo "</table>\n";

//timeouts
echo "<table cellspacing='0' width='100%' border='1'>";
echo "<tr><th colspan='",$maxtimeouts+1,"'>"._("Aikalis&auml;t")."</th></tr>\n";

echo "<tr><th>"._("Koti")."</th>\n";

//home team used timeouts
$i=0;
$timeouts = GameTimeouts($gameId);
while($timeout = mysql_fetch_assoc($timeouts))
	{
	if (intval($timeout['koti']))
		{
		echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='5' maxlength='8' id='hto$i' name='hto$i' value='". SecToMin($timeout['aika']) ."' /></td>\n";
		$i++;
		}
	}

//empty slots
for($i;$i<$maxtimeouts; $i++)
	{
	//two last slot are smaller for visual reasons
	if($i>($maxtimeouts-3))
		echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='1' maxlength='8' id='hto$i' name='hto$i' value='' /></td>\n";	
	else
		echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='5' maxlength='8' id='hto$i' name='hto$i' value='' /></td>\n";		
	}
echo "</tr>\n";

echo "<tr><th>"._("Vieras")."</th>\n";

//away team used timeouts
$i=0;
$timeouts = GameTimeouts($gameId);
while($timeout = mysql_fetch_assoc($timeouts))
	{
	if (!intval($timeout['koti']))
		{
		echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='5' maxlength='8' id='ato$i' name='ato$i' value='". SecToMin($timeout['aika']) ."' /></td>\n";
		$i++;
		}
	}

//empty slots
for($i;$i<$maxtimeouts; $i++)
	{
	//two last slot are smaller for visual reasons
	if($i>($maxtimeouts-3))
		echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='1' maxlength='8' id='ato$i' name='ato$i' value='' /></td>\n";	
	else
		echo "<td><input class='input' onkeyup=\"validTime(this);\" type='text' size='5' maxlength='8' id='ato$i' name='ato$i' value='' /></td>\n";	
	}
	
echo "</tr>";
echo "</table>";

//halftime
echo "<table cellspacing='0' width='100%' border='1'>\n";
echo "<tr><th>"._("Puoliaika p&auml;&auml;ttyi")."</th></tr>";
echo "<tr><td><input class='input' onkeyup=\"validTime(this);\"
	maxlength='8' type='text' name='halftime' id='halftime' value='". SecToMin($game_result['puoliaika']) ."'/></td></tr>";
echo "</table>\n";

//result		
echo "<table cellspacing='0' width='100%' border='1'>\n";
echo "<tr><th>"._("Lopputulos")."</th></tr>";
echo "<tr><td>". $game_result['kotipisteet'] ." - ". $game_result['vieraspisteet'] ."</td></tr>";
echo "</table>\n";
		
//buttons
echo "<table cellspacing='0' cellpadding='10px' width='100%'>\n";
echo "<tr><td colspan='2'>
		<a href='javascript://' onclick=\"eraseLast()\">"._("Poista viimeinen maali")."</a></td></tr>";
echo "<tr>";
echo "<td><input class='button' type='submit' value='"._("Tallenna")."' name='save'/></td>";
echo "<td><input class='button' type='reset' value='"._("Peruuta")."' name='reset'/></td>";
echo "</tr>";
echo "<tr><td colspan='2'>
<p>"._("Sy&ouml;t&auml; pelin p&ouml;yt&auml;kirja").":
<ul>
<li>"._("Ajan erottimena voit k&auml;ytt&auml;&auml;")." .,:; "._("-merkkej&auml;").".</li>
<li>"._("Merkitse Callahan-maaliin sy&ouml;tt&auml;j&auml;ksi XX").".</li>
<li>"._("P&ouml;yt&auml;kirjan voi tallentaa v&auml;lill&auml;")."<li></ul></p></td></tr>";
echo "<tr><td colspan='2'><p><a href='respgames.php'>"._("Takaisin vastuupeleihin")."</a></p></td></tr>";
echo "</table>\n";

//scores		
echo "</td><td>";
echo "<table cellspacing='0' border='1'>\n";
echo "<tr><th>#</th><th>"._("Koti")</th><th>"._("Vieras")."</th><th>"._("Sy&ouml;tt&auml;j&auml;")."</th><th>"._("Tekij&auml;")."</th><th>"._("Aika")."</th>";
echo "<th>"._("Tilanne")."</th></tr>\n";

$scores = GameGoals($gameId);

$i=0;
while($row = mysql_fetch_assoc($scores))
	{
	
	echo "<tr>"; 
	echo "<td class='center' style='WIDTH: 25px'>",$i+1,"</td>\n";
	
	if (intval($row['kotimaali']))
		{
		echo "<td style='WIDTH: 40px' class='center'><input onclick=\"updateScores($i);\" id='hteam$i' name='team$i' type='radio' checked='checked' value='H' /></td>";
		echo "<td style='WIDTH: 40px' class='center'><input onclick=\"updateScores($i);\" id='ateam$i' name='team$i' type='radio' value='A' /></td>";			
		}
	else
		{
		echo "<td style='WIDTH: 40px' class='center'><input onclick=\"updateScores($i);\" id='hteam$i' name='team$i' type='radio' value='H' /></td>";
		echo "<td style='WIDTH: 40px' class='center'><input onclick=\"updateScores($i);\" id='ateam$i' name='team$i' type='radio' checked='checked' value='A' /></td>";			
		}
	
	if (intval($row['callahan']))
		{
		echo "<td class='center' style='WIDTH: 50px'><input class='input' onkeyup=\"validNumberX(this);\" id='pass$i' name='pass$i' maxlength='2' size='3' value='XX'/></td>";
		}
	else
		{
		$n = PlayerNumber($row['syottaja'],$gameId);
		if($n < 0)
			$n="";
			
		echo "<td class='center' style='WIDTH: 50px'><input class='input' onkeyup=\"validNumberX(this);\" id='pass$i' name='pass$i' maxlength='2' size='3' value='$n'/></td>";
		}
		
	$n = PlayerNumber($row['tekija'],$gameId);
	if($n < 0)
		$n="";
		
	echo "<td class='center' style='WIDTH: 50px'><input class='input' onkeyup=\"validNumber(this);\" id='goal$i' name='goal$i' maxlength='2' size='3' value='$n'/></td>";
	echo "<td style='WIDTH: 60px'><input class='input' onkeyup=\"validTime(this);\" id='time$i' name='time$i' maxlength='8' size='8' value='". SecToMin($row['aika']) ."'/></td>";
	echo "<td class='center' style='WIDTH: 60px'><input class='fakeinput center' id='sit$i' name='sit$i' size='7' disabled='disabled'
	value='". $row['ktilanne'] ." - ". $row['vtilanne'] ."'/></td>";
	
	echo "</tr>\n";
	$i++;	
	}

for($i;$i<$maxscores; $i++)
	{
	echo "<tr>"; 
	echo "<td class='center' style='WIDTH: 25px'>",$i+1,"</td>\n";
	echo "<td class='center' style='WIDTH: 40px'><input onclick=\"updateScores($i);\" id='hteam$i' name='team$i' type='radio' value='H' /></td>";
	echo "<td class='center' style='WIDTH: 40px'><input onclick=\"updateScores($i);\" id='ateam$i' name='team$i' type='radio' value='A' /></td>";			
	echo "<td class='center' style='WIDTH: 50px'><input class='input' onkeyup=\"validNumberX(this);\" id='pass$i' name='pass$i' size='3' maxlength='2'/></td>";
	echo "<td  class='center' style='WIDTH: 50px'><input class='input' onkeyup=\"validNumber(this);\" id='goal$i' name='goal$i' size='3' maxlength='2'/></td>";
	echo "<td style='WIDTH: 60px'><input class='input' onkeyup=\"validTime(this);\" id='time$i' name='time$i' maxlength='8' size='8'/></td>";
	echo "<td class='center' style='WIDTH: 60px'><input class='fakeinput center' id='sit$i' name='sit$i' size='7' disabled='disabled'/></td>";
	echo "</tr>\n";
	}
echo "</table>\n";		
echo "</td></tr></table></form>\n";		


CloseConnection();
//common end
contentEnd();
pageEnd();
?>
