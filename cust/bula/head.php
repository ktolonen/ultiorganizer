<?php 
function logo() {
	global $include_prefix;
	$html = "";
	$html .= "<table style='width:95%;' cellspacing='5' cellpadding='2'>\n";
    $html .=  "<tr><td class='center'>";
    $html .=  "<a href='http://beachultimate.org/home.html'><img class='logo' src='".$include_prefix."cust/bula/bula_logo.gif' alt='"._("BULA")."'/></a>";
    $html .=  "</td></tr>";
    $html .=  "</table>";
  
	return "";
}

function pageHeader() {
	global $styles_prefix;
	global $include_prefix;
	if (!isset($styles_prefix)) {
		$styles_prefix = $include_prefix;
	}
	//return "<a href='http://beachultimate.org/home.html' class='header_text'><img class='header_logo' style='border: none;'  src='".$styles_prefix."cust/bula/header_logo.jpg' alt='BULA'/></a><img class='header_logo' src='".$styles_prefix."cust/bula/header_layout.jpg' alt='BULA'/>\n";
	return "";
}

?>