<?php 
function logo() {
	return "";
}

function pageHeader() {
	global $styles_prefix;
	global $include_prefix;
	if (!isset($styles_prefix)) {
		$styles_prefix = $include_prefix;
	}

  $html = "";

  // button to show menu in responsive mode on narrow screens
  $html .= "<img class='menu_button' onclick='menu_toggle()' src='images/menu-bars.svg' alt='menu'>";

  $html .= "<a href='https://www.wfdf.sport/' class='header_text' target='_blank'><img class='header_logo' style='width:auto;height:30px;margin: 10px 10px;' src='".$styles_prefix."cust/wfdf/wfdf-logo.jpg' alt='WFDF'/></a>\n";
  
  //$html .= "<a href='https://www.wu24.sport/' class='header_text' target='_blank'><img class='header_logo' style='width:auto;height:50px;margin: 0px 10px;' src='".$styles_prefix."cust/wfdf/logo-small.png' alt='WU24 2025'/></a>\n";

if (defined('UO_SESSION_NAME')) {
  $sessionName = (string) constant('UO_SESSION_NAME');

  if (stripos($sessionName, "test") !== false) {
    $html .= "<div style='position: fixed; top: 10px; left: 50%; width: 50%; margin-left: -25%; text-align: center; font-size: 50px; font-weight: bold; color: red;'>FOR TESTING ONLY!</div>\n";
  }

  if (stripos($sessionName, "demo") !== false) {
    $html .= "<div style='position: fixed; top: 10px; left: 50%; width: 50%; margin-left: -25%; text-align: center; font-size: 50px; font-weight: bold; color: orange;'>DEMO!</div>\n";
  }
}


  return $html;
}

?>
