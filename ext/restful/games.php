<?php 
class Games extends Restful {
	function __construct() {
		$this->listsql = "SELECT time, home.name As hometeamname, visitor.name As visitorteamname, game.*,scheduling_name.name AS gamename
		FROM uo_game AS game 
		LEFT JOIN uo_team AS home ON (game.hometeam=home.team_id) 
		LEFT JOIN uo_team AS visitor ON (game.visitorteam=visitor.team_id)
		LEFT JOIN uo_scheduling_name AS scheduling_name ON (scheduling_name.scheduling_id=game.name)";
		
		$this->localizename = false;
		$this->tables = array("uo_game" => "game", "uo_played" => "played", "uo_game_pool" => "game_pool", "uo_scheduling_name" => "scheduling_name");
	}
}

?>