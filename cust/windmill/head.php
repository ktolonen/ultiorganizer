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
	return "<a href='http://www.windmillwindup.com/' class='header_text'><img class='header_logo' src='".$styles_prefix."cust/windmill/bg_header.jpg' alt='Windmill Windup' width=600/></a><br/>\n";
}

?>