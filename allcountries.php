<?php
include_once 'lib/country.functions.php';

$title = _("Countries");
$html = "";
$counter = 0;
$maxcols = 5;
$countries = CountryList(true,true);
$html .= "<h1>"._("Countries")."</h1>\n";
$html .= "<table width='100%' border='0' cellspacing='0' cellpadding='2'>\n";
foreach($countries as $country) {

  if($counter==0){
    $html .= "<tr>\n";
  }

  $html .= "<td style='width:20%'>";
  $html .= "<a href='?view=countrycard&amp;country=". $country['country_id']."'>";
  $html .= "<img src='images/flags/small/".$country['flagfile']."' alt=''/><br/>";
  $html .= utf8entities($country['name'])."</a></td>";

  $counter++;
  if($counter>=$maxcols){
    $html .= "</tr>\n";
    $counter = 0;
  }
}
if($counter>0 && $counter<=$maxcols){$html .= "</tr>\n";};
$html .= "</table>\n";

showPage($title, $html);

?>
