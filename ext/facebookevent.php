<?php 
while(ob_get_level()) {
	ob_end_clean();
}
header('Connection: close');
ignore_user_abort();
ob_start();
echo('Connection Closed');
$size = ob_get_length();
header("Content-Length: $size");
ob_end_flush();
flush();

include '../lib/database.php';

OpenConnection();

include_once $include_prefix.'localization.php';
include_once $include_prefix.'lib/user.functions.php';
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/facebook.functions.php';
include_once $include_prefix.'lib/game.functions.php';
include_once $include_prefix.'lib/configuration.functions.php';

if (IsFacebookEnabled() && !empty($_GET['game']) && !empty($_GET['event'])) {
	$gameInfo = GameInfo($_GET['game']);
	if ($_GET['event'] == "game" && (($gameInfo['homescore'] > 0) 
		|| ($gameInfo['visitorscore'] > 0))
		&& ($gameInfo['isongoing'] == 0)) {
		if ($gameInfo['homescore'] > $gameInfo['visitorscore']) {
			$wonTeamId = $gameInfo['hometeam'];
			$wonTeamName = $gameInfo['hometeamname'];
			$wonTeamScore = $gameInfo['homescore'];
			$lostTeamId = $gameInfo['visitorteam'];
			$lostTeamName = $gameInfo['visitorteamname'];
			$lostTeamScore = $gameInfo['visitorscore'];
		} else {
			$lostTeamId = $gameInfo['hometeam'];
			$lostTeamName = $gameInfo['hometeamname'];
			$lostTeamScore = $gameInfo['homescore'];
			$wonTeamId = $gameInfo['visitorteam'];
			$wonTeamName = $gameInfo['visitorteamname'];
			$wonTeamScore = $gameInfo['visitorscore'];
		}
		$users = GetGameFacebookUsers($wonTeamId, "won");
		$wonTeamPlayers = TeamPlayerAccreditationArray($wonTeamId);
		foreach ($users as $user) {
			$fb_props = getFacebookUserProperties($user);
			foreach($fb_props['facebookplayer'] as $accrId => $conf) {
				if (isset($wonTeamPlayers[$accrId])) {
					$message = str_replace(array('$teamscore', '$team', '$opponentscore', '$opponent'), 
										   array($wonTeamScore, $wonTeamName, $lostTeamScore, $lostTeamName),
										   $conf['wonmessage']);
					$params = array("link" => GetUrlBase()."?view=gameplay&Game=".$gameInfo['game_id'],
						"message" => $message,
						"name" => $title); 
					FacebookFeedPost($fb_props, $params);
				}
			}
		}
		$users = GetGameFacebookUsers($lostTeamId, "lost");
		$lostTeamPlayers = TeamPlayerAccreditationArray($lostTeamId);
		foreach ($users as $user) {
			$fb_props = getFacebookUserProperties($user);
			foreach($fb_props['facebookplayer'] as $accrId => $conf) {
				if (isset($lostTeamPlayers[$accrId])) {
					$message = str_replace(array('$teamscore', '$team', '$opponentscore', '$opponent'),
										   array($lostTeamScore, $lostTeamName, $wonTeamScore, $wonTeamName),
										   $conf['lostmessage']);
					$params = array("link" => GetUrlBase()."?view=gameplay&Game=".$gameInfo['game_id'],
						"message" => $message,
						"name" => $title); 
					FacebookFeedPost($fb_props, $params);
				}
			}
		}
		// Post to app feed
		global $serverConf;
		$message = str_replace(array('$pool', '$winner', '$loser', '$winnerscore', '$loserscore'), 
							   array($gameInfo['poolname'], $wonTeamName, $lostTeamName, $wonTeamScore, $lostTeamScore),
							   $serverConf['FacebookGameMessage']);
		if (isset($serverConf['FacebookUpdatePage']) && (strlen($serverConf['FacebookUpdatePage']) > 0)
			&& isset($serverConf['FacebookUpdateToken']) && (strlen($serverConf['FacebookUpdateToken']))) { 
			$params = array("link" => GetUrlBase()."?view=gameplay&Game=".$gameInfo['game_id'],
							"message" => $message,
							"name" => $title);
			$app_fb = array("facebooktoken" => $serverConf['FacebookUpdateToken'], "facebookuid" => $serverConf['FacebookUpdatePage']);
			FacebookFeedPost($app_fb, $params);
		}
	} elseif ($_GET['event'] == "goal" && ($gameInfo['isongoing'] == 1) && isset($_GET['num'])) {
		$goalInfo = GoalInfo($gameInfo['game_id'],$_GET['num']);
		if ($goalInfo) {
			if ($goalInfo['ishomegoal'] == 1) {
				$team = $gameInfo['hometeamname'];
				$opponent = $gameInfo['visitorteamname'];
				$teamscore = $goalInfo['homescore'];
				$opponentscore= $goalInfo['visitorscore'];
			} else {
				$opponent = $gameInfo['hometeamname'];
				$team = $gameInfo['visitorteamname'];
				$opponentscore = $goalInfo['homescore'];
				$teamscore = $goalInfo['visitorscore'];	
			}
		}
		$passerName = $goalInfo['assistfirstname']." ".$goalInfo['assistlastname'];
		$scorerName = $goalInfo['scorerfirstname']." ".$goalInfo['scorerlastname'];
		$passer = $goalInfo['assist_accrid'];
		$scorer = $goalInfo['scorer_accrid'];
		$users = GetScoreFacebookUsers($passer, $scorer);
		if (isset($users[$passer])) {
			$fb_props = getFacebookUserProperties($users[$passer]);
			$message = str_replace(array('$teamscore', '$team', '$opponentscore', '$opponent', '$passername', '$scorername'),
			 							array($teamscore, $team, $opponentscore, $opponent, $passerName, $scorerName), $fb_props['facebookplayer'][$passer]['passedmessage']);
			$params = array("link" => GetUrlBase()."?view=gameplay&Game=".$gameInfo['game_id'],
				"message" => $message,
				"name" => $title); 
			FacebookFeedPost($fb_props, $params);
		}
		if (isset($users[$scorer])) {
			$fb_props = getFacebookUserProperties($users[$scorer]);
			$message = str_replace(array('$teamscore', '$team', '$opponentscore', '$opponent', '$passername', '$scorername'),
			 							array($teamscore, $team, $opponentscore, $opponent, $passerName, $scorerName), $fb_props['facebookplayer'][$scorer]['scoredmessage']);
			$params = array("link" => GetUrlBase()."?view=gameplay&Game=".$gameInfo['game_id'],
				"message" => $message,
				"name" => $title); 
			FacebookFeedPost($fb_props, $params);
		}
	}
}
?>
