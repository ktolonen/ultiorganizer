<?php 
class Series extends Restful {
	function Series() {
		$this->listsql = "SELECT series_id, series.name as name, season.name as seasonname, series.season 
		FROM uo_series series LEFT JOIN uo_season season ON (series.season=season.season_id)";
		$this->itemsql = "SELECT * FROM uo_series WHERE series_id=%d";
		$this->tables = array("uo_series" => "series", "uo_season" => "season");
		$this->defaultOrdering = array("season.starttime" => "DESC", "series.ordering" => "ASC", "series.name" => "ASC");
		
		$this->children["pools"] = array("field" => "pool.series", "operator" => "=", "value" => array("variable" => "id"));
		$this->children["teams"] = array("field" => "team.series", "operator" => "=", "value" => array("variable" => "id"));
		$this->localizename = true;
		global $active_seasons, $editable_series, $editing_series;
		$this->filters["active"] = $active_seasons;
		$this->filters["my-editable"] = $editable_series;
		$this->filters["my-editing"] = $editing_series;
		$this->linkfields["season"] = "seasons";
	}
	
	function getItemName() {
		return "Series";
	}	
}
?>