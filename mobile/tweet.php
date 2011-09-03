<?php
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/player.functions.php';
include_once 'lib/twitter.functions.php';
$html = "";

$gameId = intval($_GET["Game"]);
	
if(isset($_POST['tweettext'])) {
	
	if(!empty($_POST['textbox'])){
		TweetText($gameId, $_POST['textbox']);
	}
	header("location:?view=mobile/addscoresheet&Game=".$gameId);
	}
elseif(isset($_POST['tweetresult'])) {
	TweetGameScores($gameId);
	header("location:?view=mobile/addscoresheet&Game=".$gameId);
}
mobilePageTop(_("Score&nbsp;sheet"));
?>
<script type="text/javascript">
<!--
function update() {
   
   if(document.form.textbox.value.length > 140) {
		document.form.textbox.value = document.form.textbox.value.substring(0, 140);
    }
	document.form.counter.value=document.form.textbox.value.length;
}
//-->
</script>
<?php
$html .= "<form action='?".utf8entities($_SERVER['QUERY_STRING'])."' method='post' name='form'>\n"; 
$html .= "<table cellpadding='2'>\n";
$html .= "<tr><td>\n";
$html .= _("Game end result is sent automatically to Twitter.");
$html .= "</td></tr><tr><td>\n";
$html .= "<input class='button' type='submit' name='tweetresult' value='"._("Tweet last score")."'/>";
$html .= "</td></tr><tr><td>\n";
$html .= " <input class='input' disabled='disabled' name='counter' value='0' size='3'/> / 140";
$html .= "</td></tr><tr><td>\n";
$html .= "<textarea class='input' rows='4' cols='40' name='textbox' onkeyup=\"update();\"></textarea>";
$html .= "</td></tr><tr><td>\n";

$html .= "<input class='button' type='submit' name='tweettext' value='"._("Tweet text")."'/>";

$html .= "</td></tr><tr><td>\n";
$html .= "<a href='?view=mobile/addscoresheet&amp;Game=".$gameId."'>"._("Back to score sheet")."</a>";
$html .= "</td></tr>\n";
$html .= "</table>\n";
$html .= "</form>"; 

echo $html;
		
pageEnd();
?>
