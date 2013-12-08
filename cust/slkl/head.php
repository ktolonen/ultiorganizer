<?php 
function logo() {
	global $include_prefix;
	return "<div><a href='http://www.ultimate.fi/'><img class='logo' src='".$include_prefix."cust/slkl/logo.png' alt='"._("Ultimate.fi")."'/></a></div>";
}

function pageHeader() {
  global $include_prefix;

  //$ret = "<table border='0' cellpadding='0' cellspacing='0'><tr><td class='left'>";
  //$ret .= "<img style='width:30px;height:30px' src='".$include_prefix."cust/slkl/slkl.png' alt='SLKL'/>";
  //$ret .= "</td><td>&nbsp;</td><td class='left'>";
  //$ret .= "<a href='".GetURLBase()."' class='header_text'>"._("Finnish Flying Disc Association")." - "._("Pelikone")."</a>\n";
  //$ret .= "</td></tr></table>";
 
  $ret = "<a href='".GetURLBase()."' class='header_text'>"._("Ultimate Pelikone")."</a>";
  $ret .= "<span style='color: #0bc5e0;font-size: 14pt;'> "._("Finnish Flying Disc Association")."</span>\n";
  
  return $ret;
}

?>