<?php 
class Seasons extends Restful {
	function Seasons() {
		$this->listsql = "SELECT season_id, name FROM uo_season season";
		$this->itemsql = "SELECT * FROM uo_season WHERE season_id='%s'";
		$this->tables = array("uo_season" => "season");
		$this->defaultOrdering = array("season.starttime" => "DESC");
		
		$this->children["series"] = array("field" => "series.season", "operator" => "=", "value" => array("variable" => "id"));
		$this->localizename = true;
		global $active_seasons, $editable_seasons, $editing_seasons;
		$this->filters["active"] = $active_seasons; 
		$this->filters["my-editable"] = $editable_seasons;
		$this->filters["my-editing"] = $editing_seasons;
	}
}
?>