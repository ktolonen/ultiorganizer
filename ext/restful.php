<?php 
include '../lib/database.php';

OpenConnection();

include_once $include_prefix.'localization.php';
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/user.functions.php';
include_once $include_prefix.'lib/restful.functions.php';
include_once $include_prefix.'ext/restful/classes.php';

session_start();

if (!isset($_SESSION['uid'])) {
	$_SESSION['uid'] = "anonymous";
	SetUserSessionData("anonymous");
}

setSessionLocale();

if (isset($_SERVER['PATH_INFO'])) {
	$path = array_filter(explode("/", $_SERVER['PATH_INFO']));
	global $isDir;
	$isDir = true; 
	if (substr($_SERVER['PATH_INFO'], -1) != "/") {
		$isDir = false;
	}
} else {
	$path = array();
	$isDir = false;
}

if (isset($_GET['authenticate'])) {
	if ($_GET['authenticate'] == "logout") {
		ClearUserSessionData();
	} elseif ($_GET['authenticate'] == "login") {
		if (isset($_SERVER["PHP_AUTH_USER"]) && isset($_SERVER["PHP_AUTH_PW"])) {
			UserAuthenticate($_SERVER["PHP_AUTH_USER"], $_SERVER["PHP_AUTH_PW"], "FailUnauthorized");
		} else {
			ClearUserSessionData();
			header('WWW-Authenticate: Basic realm="ultiorganizer"');
   			if (strpos("Microsoft", $_SERVER["SERVER_SOFTWARE"])) {
   				header("Status: 401 Unauthorized");
   			} else {
   				header("HTTP/1.0 401 Unauthorized");				
   			}
   			echo "Unauthorized";
   			exit();
		}
	}
	paramHandled('authenticate');
}

if (isset($_GET['locale'])) {
	paramHandled('locale');
}

if (isset($_GET['jsonp'])) {
	$jsonp = urldecode($_GET['jsonp']);
	paramHandled('jsonp');
}

header("content-type: text/javascript");
if (isset($jsonp)) {
	echo $jsonp."(";
}

if (count($path) == 0) {
	$objects = array("seasons", "series", "pools", "teams", "teamprofiles", "countries", "players", "playerprofiles",
		"games", "locations");
	if (hasEditUsersRight()) {
		$objects[] = "users";
	}
	if (isset($_SESSION['userproperties']['userrole']['superadmin']) ||
	isset($_SESSION['userproperties']['userrole']['seasonadmin'])) {
		$objects[] = "reservations";
	}
	$output = array();
	if (isset($_SESSION) && isset($_SESSION['uid']) && $_SESSION['uid'] != "anonymous") {
		$output["user"]	= UserInfo($_SESSION['uid']);
		unset($output['user']['password']);
		$output["user"]["link"] = urlencode(GetURLBase()."/ext/restful.php/users/".$_SESSION['uid']);
	}
	foreach ($objects as $obj) {
		$output[$obj] = array ("name" => _(ucwords($obj)), "link" => urlencode(GetURLBase()."/ext/restful.php/".$obj."/"));
	}
	echo "{\n \"objects\": ".json_encode($output)."\n}";
} else {
	$object = $path[1];
	$object = ucwords($object);
	$restful = new $object();
	if (count($path) == 1) {
		$filter = null;
		if (isset($_GET['filter'])) {
			$filter = $restful->getFilter(getJSONGetParamater('filter'));
		} elseif (isset($_GET['andfilter']) || isset($_GET['orfilter'])) {
			if (isset($_GET['andfilter'])) {
				$filter = array("join" => "and");
				$filters = getJSONGetParamater('andfilter');
			} else {
				$filter = array("join" => "or");
				$filters = getJSONGetParamater('orfilter');
			}
			
			$criteria = array();
			$unsafe = false;
			foreach ($filters as $nextfilter) {
				$unsafe = $unsafe || is_array($nextfilter);
				$criteria[] = $restful->getFilter($nextfilter);
			}
			$filter['criteria'] = $criteria;
		}
		
		$ordering = null;
		if (isset($_GET['ordering'])) {
			$ordering = json_decode($_GET['ordering'], true);
			switch(json_last_error())
		    {
		        case JSON_ERROR_DEPTH:
		            die('Ordering parsing error: Maximum stack depth exceeded');
		        break;
		        case JSON_ERROR_CTRL_CHAR:
		            die('Ordering parsing error: Unexpected control character found');
		        break;
		        case JSON_ERROR_SYNTAX:
		            die('Ordering parsing error: Malformed JSON');
		        break;
		        case JSON_ERROR_NONE:
		        break;
		    }
		}
		$list = $restful->getList($filter, $ordering);
		echo json_encode($list);
	} elseif ($path[2] == "filters") {
		echo json_encode($restful->getFilters());
	} else {
		$item = $restful->getItem($path[2]);
		echo json_encode($item);
	}
}

if (isset($jsonp)) {
	echo ")";
}

function getJSONGetParamater($parameter) {
	if (isset($_GET[$parameter])) {
		$ret = json_decode($_GET[$parameter], true);
		switch(json_last_error())
	    {
	        case JSON_ERROR_DEPTH:
	            die('Filter parsing error: Maximum stack depth exceeded');
	        break;
	        case JSON_ERROR_CTRL_CHAR:
	            die('Filter parsing error: Unexpected control character found');
	        break;
	        case JSON_ERROR_SYNTAX:
	            die('Filter parsing error: Malformed JSON');
	        break;
	        case JSON_ERROR_NONE:
	        break;
	    }
	    return $ret;
	} else {
		die("JSON parameter $parameter not set");
	}
}

?>