<?php

$LAYOUT_ID = HOME;
$title = _("Home");

//common page
pageTop($title, false);
leftMenu($LAYOUT_ID);
contentStart();

//content
echo "<h1>". _("Welcome to the Ultiorganizer") ."</h1>";

$htmlfile = 'locale/'.getSessionLocale().'/LC_MESSAGES/welcome.html';

if (is_file('cust/'.CUSTOMIZATIONS.'/'.$htmlfile)) {
  echo file_get_contents('cust/'.CUSTOMIZATIONS.'/'. $htmlfile);
}else{
  echo file_get_contents($htmlfile);
}

echo "<p>";
echo "<a href='?view=user_guide'>"._("User Guide")."</a>\n";
echo "</p>";

echo "<p>";
echo _("In case of feedback, improvement ideas or any other questions contact to:");
$urls = GetUrlListByTypeArray(array("admin"),0);
  foreach($urls as $url){
      echo "<br/><a href='mailto:".$url['url']."'>".U_($url['name'])."</a>\n";
  }
echo "</p>";

contentEnd();
pageEnd();
?>
