<?php 
function logo() {
	global $styles_prefix;
	global $include_prefix;
	if (!isset($styles_prefix)) {
		$styles_prefix = $include_prefix;
	}
	return "<img class='header_logo' src='".$styles_prefix."cust/default/logo.gif' alt='"._("EXAMPLE")."'/>";
}

function pageHeader() {
	return "<a href='http://sourceforge.net/apps/trac/ultiorganizer/wiki' class='header_text'>"._("Ultiorganizer")."</a><br/>\n";
}

?>