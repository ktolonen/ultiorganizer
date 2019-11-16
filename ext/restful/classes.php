<?php 

class Restful {
	
	protected $children = array();
	protected $localizename = false;
	protected $filters = array();
	protected $linkfields = array();
	protected $listsql;
	protected $itemsql;
	protected $tables;
	protected $defaultOrdering;
	
	function getNameField() {
		return "name";
	}
	
	function getFilters() {
		return $this->filters;
	}
	
	function getItemName() {
		return substr(get_class($this), 0, -1);
	}
	function getIdField()  {
		return strtolower($this->getItemName())."_id";
	}
	
	function getRestClassName() {
		return strtolower(get_class($this));
	}
	
	function getChildFilter($child) {
		return $this->children($child);	
	}
	
	function getItem($id) {
		$query = $this->getItemSQL($id);
		$ret = DBQueryToRow($query, true);
		$merged = array_merge($ret, $this->getChildren($id));
		$this->convertLinkFields($merged);
		return $merged;
	}
	
	function getFilter($filter) {
		if (!isset($filter)) {
			return null;
		}
		if (!is_array($filter)) {
			if (isset($this->filters[$filter])) {
				return $this->filters[$filter];
			}
		} else {
			return $filter;
		}
		return null;
	}
	
	function getListSQL($filter=null, $ordering=null) {
		if (!isset($ordering)) {
			$ordering = $this->getDefaultOrdering();
		}
		$tables = $this->getTables();
		$orderby = CreateOrdering($tables, $ordering);
		$where = CreateFilter($tables, $filter);
		$query = $this->getListSQL()." ".$where." ".$orderby;
		return $query;
	}
	
	function getItemSQL($id) {
		return sprintf($this->itemsql, DBEscapeString($id));
	}
	
	function getDefaultOrdering() {
		return $this->defaultOrdering;
	}
	
	function getTables() {
		return $this->tables;
	}
	
	function getList($filter=null, $ordering=null) {
		if (!isset($ordering)) {
			$ordering = $this->getDefaultOrdering();
		}
		$tables = $this->getTables();
		$orderby = CreateOrdering($tables, $ordering);
		$where = CreateFilter($tables, $filter);
		$query = $this->listsql." ".$where." ".$orderby;
		$items = DBQuery(trim($query));
		
		$retArray = array();
		$className = $this->getRestClassName();
		while ($next = mysqli_fetch_assoc($items)) {
			$toadd = $this->getListData($next);
			$retArray[] = $toadd;
		}
		return array("data" => $retArray);
	}
		
	function getListData($row) {
		$ret = array();
		
		$id = $row[$this->getIdField()];
		$ret['id'] = $id;
		if ($this->localizename) {
			$ret['name'] = U_($row[$this->getNameField()]);
		} else {
			$ret['name'] = $row[$this->getNameField()];
		}
		$ret['link'] = urlencode(GetURLBase()."/ext/restful.php/".$this->getRestClassName()."/".$id);
		return $ret;
	}
	
	function getChildren($id) {
		$GLOBALS['id'] = $id;
		$ret = array();
		foreach ($this->children as $child=>$filter) {
			$childObjectName = ucwords($child);
			$childObject = new $childObjectName(); 
			$nextchildren = $childObject->getList($filter);
			$ret[$child] = $nextchildren["data"];
		}
		return $ret;
	}
	
	function convertLinkFields(&$row) {
		foreach ($this->linkfields as $field => $objectName) {
			$objectName = ucwords($objectName);
			$object = new $objectName();
			$table = strtolower($object->getItemName());
			$filter = array("field" => $table.".".$object->getIdField(), "operator" => "=", "value" => $row[$field]);
			$nextLink = $object->getList($filter);
			$row[$field] = $nextLink['data'];
		}
	}
}

// Filters
$active_seasons = array("field" => "season.iscurrent", "operator" => "=", "value" => 1);
$editable_seasons = array("join" => "or", "criteria" => array(
			array("field" => array("constanttype" => "int", "value" => array("variable" => "userproperties", "key1" => "userrole", "key2" => "superadmin")),
				"operator" => ">",
				"value" => 0),
			array("field" => "season.season_id", "operator" => "in", "value" => 
				array("variable" => "userproperties", "key1" => "userrole", "key2" => "seasonadmin", "implode-keys" => ","))));
$edit_seasons = array("field" => "season.season_id", "operator" => "in", "value" =>
					array("variable" => "userproperties", "key1" => "editseason", "implode-keys" => ","));
$editing_seasons = array("join" => "and", "criteria" =>
			array($editable_seasons, $edit_seasons));

$editable_series = array_copy($editable_seasons);
$editable_series["criteria"][] = array("field" => "series.series_id", "operator" => "in", "value" => 
				array("variable" => "userproperties", "key1" => "userrole", "key2" => "seriesadmin", "implode-keys" => ","));
$editing_series = array("join" => "and", "criteria" =>
			array($editable_series, $edit_seasons));

$editable_teams = array_copy($editable_seasons);
$editable_teams["criteria"][] = array("field" => "team.team_id", "operator" => "in", "value" => 
				array("variable" => "userproperties", "key1" => "userrole", "key2" => "teamadmin", "implode-keys" => ","));
$editing_teams = array("join" => "and", "criteria" =>
			array($editable_teams, $edit_seasons));
				

include_once $include_prefix.'ext/restful/countries.php';
include_once $include_prefix.'ext/restful/games.php';
include_once $include_prefix.'ext/restful/goals.php';
include_once $include_prefix.'ext/restful/playerprofiles.php';
include_once $include_prefix.'ext/restful/players.php';
include_once $include_prefix.'ext/restful/pools.php';
include_once $include_prefix.'ext/restful/seasons.php';
include_once $include_prefix.'ext/restful/series.php';
include_once $include_prefix.'ext/restful/teamprofiles.php';
include_once $include_prefix.'ext/restful/teams.php';
include_once $include_prefix.'ext/restful/users.php';
?>