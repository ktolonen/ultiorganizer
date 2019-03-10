<?php 
class Pools extends Restful {
	function Pools() {
		
		$this->listsql = "SELECT pool_id, pool.name, pool.type, pool.series
		FROM uo_pool pool LEFT JOIN uo_series series ON (pool.series=series.series_id)
		LEFT JOIN uo_season season ON (series.season=season.season_id)";
		$this->itemsql = "SELECT pool.*, ser.name AS seriesname, ser.season FROM uo_pool pool 
		LEFT JOIN uo_series ser ON(pool.series=ser.series_id)
		WHERE pool.pool_id=%d";
		$this->tables = array("uo_pool" => "pool", "uo_series" => "series", "uo_season" => "season");
		$this->defaultOrdering = array("season.starttime" => "ASC", "series.ordering" => "ASC", "pool.ordering" => "ASC");
		
		$this->children["teams"] = array("field" => "team.team_id", "operator" => "subselect", "value" =>
			array("table" => "team_pool", "field" => "team_pool.team", "join" => "and", "criteria" =>
				array(array("field" => "team_pool.pool", "operator" => "=", "value" => array("variable" => "id")))));
		$this->localizename = true;
		global $active_seasons, $editable_series, $editing_series;
		$this->filters["active"] = $active_seasons;
		$this->filters["my-editable"] = $editable_series;
		$this->filters["my-editing"] = $editing_series;		
		$this->linkfields["series"] = "series";
		$this->linkfields["season"] = "seasons";
	}
	
	function getListData($row) {
		$ret = parent::getListData($row);
		$ret['type'] = $row['type'];
		return $ret;
	}
}
?>