<?php
class Teams extends Restful {
	function Teams() {
		$this->listsql = "SELECT team_id, team.name, series.name as seriesname, pool.name as poolname, season.name as seasonname
		FROM uo_team team LEFT JOIN uo_pool pool ON (team.pool=pool.pool_id)
		LEFT JOIN uo_series series ON (team.series=series.series_id)
		LEFT JOIN uo_season season ON (series.season=season.season_id)";
		$this->itemsql = "SELECT team.name, team.club, club.name AS clubname, team.pool, pool.name AS poolname, ser.name AS seriesname, 
		team.series, ser.type, ser.season, team.abbreviation, team.country, c.name AS countryname, c.flagfile
		FROM uo_team team LEFT JOIN uo_pool pool ON (team.pool=pool.pool_id) 
		LEFT JOIN uo_series series ON (team.series=series.series_id)
		LEFT JOIN uo_club club ON (team.club=club.club_id)
		LEFT JOIN uo_country country ON (team.country=country.country_id)
		WHERE team.team_id = '%s'";
		$this->tables = array("uo_team" => "team", "uo_pool" => "pool", "uo_series" => "series", "uo_season" => "season", "uo_country" => "country");
		$this->defaultOrdering = array("season.starttime" => "ASC", "series.ordering" => "ASC", "pool.ordering" => "ASC", "team.rank" => "ASC", "team.name" => "ASC");

		$this->children["players"] = array("field" => "player.team", "operator" => "=", "value" => array("variable" => "id"));
		$this->localizename = false;
		global $active_seasons, $editable_teams, $editing_teams;
		$this->filters["active"] = $active_seasons;
		$this->filters["my-editable"] = $editable_teams;
		$this->filters["my-editing"] = $editing_teams;
		$this->linkfields["pool"] = "pools";
		$this->linkfields["series"] = "series";
		$this->linkfields["season"] = "seasons";
	}
}
?>