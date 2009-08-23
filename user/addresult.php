<?php
include_once 'view_ids.inc.php';
include_once 'builder.php';
include_once '../lib/database.php';
include_once '../lib/common.functions.php';
include_once '../lib/game.functions.php';

include_once 'lib/game.functions.php';
include_once 'lib/serie.functions.php';
$LAYOUT_ID = ADDRESULT;

//common page
pageTopHeadOpen();
include_once 'lib/disable_enter.js.inc';
pageTopHeadClose();
leftMenu($LAYOUT_ID);
contentStart();
//content
OpenConnection();
$gameId = intval($_GET["Game"]);

//process itself if remove button was pressed
if(!empty($_POST['save']))
	{
	$home = intval($_POST['home']);
	$away = intval($_POST['away']);
	$ok=GameSetResult($gameId, $home, $away);
	if($ok)
		{
		echo "<p>Tulos tallennettu.</p>";
		SerieResolveStandings(GameSerie($gameId));
		}
	}
	
$result = GameResult($gameId );

echo "<form  method='post' action='addresult.php?Game=".$gameId."'>
<table cellpadding='2'>
<tr><td><b>". htmlentities($result['KNimi']) ."</b></td><td><b> - </b></td><td><b>". htmlentities($result['VNimi']) ."</b></td></tr>
<tr>
<td><input class='input' name='home' value='". $result['kotipisteet'] ."' maxlength='2' size='5'/></td>
<td> - </td>
<td><input class='input' name='away' value='". $result['vieraspisteet'] ."' maxlength='2' size='5'/></td></tr>
</table>";
echo "<p>    
		<input class='button' type='submit' name='save' value='Tallenna'/>
	</p></form>";

echo "<p><a href='addplayerlists.php?Game=$gameId'>Sy&ouml;t&auml; pelin pelaajat</a></p>";
echo "<p><a href='respgames.php'>Takaisin vastuupeleihin</a></p>";

CloseConnection();
//common end
contentEnd();
pageEnd();
?>