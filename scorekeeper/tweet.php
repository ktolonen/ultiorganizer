<?php
$html = "";

$gameId = isset($_GET['game']) ? $_GET['game'] : $_SESSION['game'];
$_SESSION['game'] = $gameId;

$game_result = GameResult($gameId);
	
if(isset($_POST['tweettext'])) {
	
	if(!empty($_POST['textbox'])){
		TweetText($gameId, $_POST['textbox']);
	}
	header("location:?view=addscoresheet&game=".$gameId);
	}
elseif(isset($_POST['tweetresult'])) {
	TweetGameScores($gameId);
	header("location:?view=addscoresheet&game=".$gameId);
}
$html .= "<div data-role='header'>\n";
$html .= "<h1>"._("Tweet").": ".utf8entities($game_result['hometeamname'])." - ".utf8entities($game_result['visitorteamname'])."</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";
$html .= "<form action='?view=tweet' method='post' data-ajax='false'>\n";

$html .= _("Game end result is sent automatically to Twitter.");

$html .= "<input type='submit' name='tweetresult' data-ajax='false' value='"._("Tweet last score")."'/>";

$html .= "<label for='textbox'>"._("Tweet Text").":</label>";
$html .= "<textarea name='textbox' id='textbox' maxlength='140'></textarea>";
$html .= "<input disabled='disabled' name='counter' id='counter' value='0/140' size='5'/>";


$html .= "<input type='submit' name='tweettext' data-ajax='false' value='"._("Tweet text")."'/>";
$html .= "<a href='?view=addscoresheet&amp;game=".$gameId."' data-role='button' data-ajax='false'>"._("Back to score sheet")."</a>";
$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
?>
<script type="text/javascript">
<!--

$("#textbox").keyup(function(){
	var len = $(this).val().length;
	$('input[id=counter]').val(len+"/140");
	});

//-->
</script>

