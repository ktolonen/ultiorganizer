<?php 
include_once $include_prefix.'lib/configuration.functions.php';
include_once $include_prefix.'lib/twitteroauth/twitteroauth.php';
include_once $include_prefix.'lib/game.functions.php';
include_once $include_prefix.'lib/player.functions.php';

function TweetGameResult($gameId){

	if(!IsTwitterEnabled()){return;}
	
	if(!isset($_SESSION['TwitterConsumerKey'])){
		$twitterconf = GetTwitterConf();
		$_SESSION['TwitterConsumerKey'] = $twitterconf['TwitterConsumerKey'];
		$_SESSION['TwitterConsumerSecret'] = $twitterconf['TwitterConsumerSecret'];
		$_SESSION['TwitterOAuthCallback'] = $twitterconf['TwitterOAuthCallback'];
	}
	
	$gameinfo = GameInfo($gameId);
	
	$text = $gameinfo['seriesname'] .", ". $gameinfo['poolname'] .": ";
	$text .= $gameinfo['hometeamname'] ." - ". $gameinfo['visitorteamname'];
	$text .= " ". intval($gameinfo['homescore']) ." - ". intval($gameinfo['visitorscore']);
	$text = TweetTextCheck($text);
	
	$purpose = "season results";
	$key = GetTwitterKey($gameinfo['season'], $purpose);

	if($key){
		$twitter = new TwitterOAuth($_SESSION['TwitterConsumerKey'], $_SESSION['TwitterConsumerSecret'], $key['keystring'], $key['secrets']);
		$twitter->post('statuses/update', array('status' => $text,
			'in_reply_to_status_id' => $gameId));
	}
	
	$purpose = "series results";
	$key = GetTwitterKey($gameinfo['series'], $purpose);
	if($key){
		$twitter = new TwitterOAuth($_SESSION['TwitterConsumerKey'], $_SESSION['TwitterConsumerSecret'], $key['keystring'], $key['secrets']);
		$twitter->post('statuses/update', array('status' => $text,'in_reply_to_status_id' => $gameId));
	}
}

function TweetText($gameId, $text){
	
	if(!IsTwitterEnabled()){return;}
	
	if(!isset($_SESSION['TwitterConsumerKey'])){
		$twitterconf = GetTwitterConf();
		$_SESSION['TwitterConsumerKey'] = $twitterconf['TwitterConsumerKey'];
		$_SESSION['TwitterConsumerSecret'] = $twitterconf['TwitterConsumerSecret'];
		$_SESSION['TwitterOAuthCallback'] = $twitterconf['TwitterOAuthCallback'];
	}
	
	$gameinfo = GameInfo($gameId);
	
	$text = TweetTextCheck($text);
		
	$purpose = "series results";
	$key = GetTwitterKey($gameinfo['series'], $purpose);
	if($key){
		$twitter = new TwitterOAuth($_SESSION['TwitterConsumerKey'], $_SESSION['TwitterConsumerSecret'], $key['keystring'], $key['secrets']);
		$twitter->post('statuses/update', array('status' => $text,'in_reply_to_status_id' => $gameId));
	}
}

function TweetGameScores($gameId){

	if(!IsTwitterEnabled()){return;}
	
	if(!isset($_SESSION['TwitterConsumerKey'])){
		$twitterconf = GetTwitterConf();
		$_SESSION['TwitterConsumerKey'] = $twitterconf['TwitterConsumerKey'];
		$_SESSION['TwitterConsumerSecret'] = $twitterconf['TwitterConsumerSecret'];
		$_SESSION['TwitterOAuthCallback'] = $twitterconf['TwitterOAuthCallback'];
	}
	
	$gameinfo = GameInfo($gameId);
	$lastscore = GameLastGoal($gameId);
	$text = $gameinfo['seriesname'] .", ". $gameinfo['poolname'] .": ";
	$text .= $gameinfo['hometeamname'] ." - ". $gameinfo['visitorteamname'];
	$text .= ". ". _("Last score").": ";
	
	if(!empty($lastscore['time'])){
		$text .= $lastscore['homescore']." - ". $lastscore['visitorscore'];
		$text .= " [".SecToMin($lastscore['time'])."]";
		if (intval($lastscore['iscallahan'])){
			$lastpass = "xx";
		}else{
			$lastpass = $lastscore['assistfirstname'] ." ". $lastscore['assistlastname'];
		}
		$lastgoal = $lastscore['scorerfirstname'] ." ". $lastscore['scorerlastname'];
		if(!empty($lastpass) || !empty($lastgoal)){
			$text .= " ".$lastpass." --> ".$lastgoal;
		}
	}
	
	$text = TweetTextCheck($text);
	
	$purpose = "series results";
	$key = GetTwitterKey($gameinfo['series'], $purpose);
	if($key){
		$twitter = new TwitterOAuth($_SESSION['TwitterConsumerKey'], $_SESSION['TwitterConsumerSecret'], $key['keystring'], $key['secrets']);
		$twitter->post('statuses/update', array('status' => $text,'in_reply_to_status_id' => $gameId));
	}	
}

function TweetTextCheck($text){
	//$text = utf8_encode($text);
	return substr($text,0,140);
}

?>
