<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';

$LAYOUT_ID = SEASONSERIES;

$season = $_GET["season"];
$html = "";

$title = utf8entities(U_(SeasonName($season))).": "._("Divisions");

//process itself on submit
if(!empty($_POST['remove_x'])){
  $id = $_POST['hiddenDeleteId'];
  if(CanDeleteSeries($id)){
    DeleteSeries($id);
  }
}elseif(!empty($_POST['add'])){
  $sp['name'] = !empty($_POST['name0']) ? $_POST['name0'] : "no name";
  $sp['type'] = $_POST['type0'];
  $sp['ordering'] = !empty($_POST['ordering0']) ? $_POST['ordering0'] : "A";
  $sp['season'] = $season;
  $sp['valid']=isset($_POST['valid0']) ? 1 : 0;
  $sp['pool_template'] = $_POST['template0'];
  AddSeries($sp);

}else if(!empty($_POST['save'])){

  //Save all
  $series = SeasonSeries($season);
  foreach($series as $row){
    $id = $row['series_id'];
    $sp['series_id'] = $id;
    $sp['name'] = !empty($_POST["name$id"]) ? $_POST["name$id"] : "no name";
    $sp['type'] = $_POST["type$id"];
    $sp['ordering'] = $_POST["ordering$id"];
    $sp['season'] = $season;
    $sp['valid']=isset($_POST["valid$id"]) ? 1 : 0;
    $sp['pool_template'] = $_POST["template$id"];
    SetSeries($sp);
  }
}

//common page
pageTopHeadOpen($title);
$setFocus = "onload=\"document.getElementById('name0').focus();\"";
pageTopHeadClose($title, false, $setFocus);
leftMenu($LAYOUT_ID);
contentStart();

$html .= "<form method='post' action='?view=admin/seasonseries&amp;season=$season'>";
$html .= "<h2>"._("Divisions")."</h2>\n";

$series = SeasonSeries($season);
$types = SeriesTypes();

$html .= "<table class='admintable'>\n";
$html .= "<tr><th>"._("Name")."</th><th>"._("Type")."</th><th>"._("Rules")."</th><th>"._("Ordering")."</th><th class='center' title='"._("Visible")."'>"._("V")."</th>";
$html .= "<th>"._("Operations")."</th><th></th></tr>\n";

$last_ordering = 0;
$last_rule_template = 0;

foreach($series as $row){
  $id = $row['series_id'];
  $html .= "<tr  class='admintablerow'>";
  $html .= "<td><input class='input' size='15' maxlength='50' name='name$id' value='".utf8entities($row['name'])."'/></td>";
  $html .= "<td><select class='dropdown' name='type$id'>\n";

  foreach($types as $type){
    if($row['type']==$type){
      $html .= "<option class='dropdown' selected='selected' value='$type'>".U_($type)."</option>\n";
    }else{
      $html .= "<option class='dropdown' value='$type'>".U_($type)."</option>\n";
    }
  }

  $html .= "</select></td>";

  $html .=  "<td><select class='dropdown' name='template$id'>\n";

$templates = PoolTemplates();

foreach($templates as $template) {
  
  if($row['pool_template']==$template['template_id']){
    $html .=   "<option class='dropdown' selected='selected' value='".utf8entities($template['template_id'])."'>". utf8entities($template['name']) ."</option>";
    $last_rule_template = $template['template_id'];
  }else{
    $html .=   "<option class='dropdown' value='".utf8entities($template['template_id'])."'>". utf8entities($template['name']) ."</option>";
  }
}

$html .=  "</select></td>";

  $html .= "<td><input class='input' size='3' maxlength='1' name='ordering$id' value='".utf8entities($row['ordering'])."'/></td>";
  
  if(intval($row['valid'])){
    $html .= "<td class='center'><input class='input' type='checkbox' name='valid$id' checked='checked'/></td>";
  }else{
    $html .= "<td class='center'><input class='input' type='checkbox' name='valid$id'/></td>";
  }

  $html .= "<td style='white-space: nowrap;'>\n";
  $html .= "<a href='?view=admin/seasonteams&amp;season=".$season."&amp;series=".$id."'>"._("Teams")."</a> | ";
  $html .= "<a href='?view=admin/seasonpools&amp;season=".$season."&amp;series=".$id."'>"._("Pools")."</a> | ";
  $html .= "<a href='?view=admin/seasongames&amp;season=".$season."&amp;series=".$id."'>"._("Games")."</a>";
  
  $html .= "</td>";

  $html .= "<td class='center'>";
  if(CanDeleteSeries($id)){
    $html .= "<input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setId(".$id.");\"/>";
  }
  $html .= "</td>";
  $html .= "</tr>\n";
  $last_ordering = $row['ordering'];
}
if(!$last_ordering){
  $last_ordering='A';
}else{
  $last_ordering++;
}
$html .= "<tr>";
$html .= "<td style='padding-top:15px'><input class='input' size='15' maxlength='50' id='name0' name='name0'/></td>";
$html .= "<td style='padding-top:15px'><select class='dropdown' name='type0'>\n";

foreach($types as $type){
  $html .= "<option class='dropdown' value='$type'>".U_($type)."</option>\n";
}

$html .= "</select></td>";
$html .=  "<td style='padding-top:15px'><select class='dropdown' name='template0'>\n";

$templates = PoolTemplates();

foreach($templates as $template) {
  if($last_rule_template==$template['template_id']){
      $html .=   "<option class='dropdown' selected='selected' value='".utf8entities($template['template_id'])."'>". utf8entities($template['name']) ."</option>";
  }else{ 
     $html .=   "<option class='dropdown' value='".utf8entities($template['template_id'])."'>". utf8entities($template['name']) ."</option>";
  }
}

$html .=  "</select></td>";

$html .= "<td style='padding-top:15px'><input class='input' size='3' maxlength='1' name='ordering0' value='$last_ordering'/></td>";
$html .= "<td style='padding-top:15px'><input class='input' type='checkbox' name='valid0' checked='checked'/></td>";

$html .= "<td style='padding-top:15px'><input id='add' class='button' name='add' type='submit' value='"._("Add")."'/></td>";
$html .= "<td style='padding-top:15px'></td>";
$html .= "</tr>\n";

$html .= "</table>";

$html .= "<p>";
$html .= "<input id='save' class='button' name='save' type='submit' value='"._("Save")."'/> ";
$html .= "<input id='cancel' class='button' name='cancel' type='submit' value='"._("Cancel")."'/>";
$html .= "</p>";

//stores id to delete
$html .= "<div><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></div>";
$html .= "</form>\n";
$html .= "<hr/>\n";
$html .= "<p>";
$html .= "<a href='?view=admin/serieformats'>"._("Edit rule templates")."</a> ";
$html .= "</p>";
echo $html;

contentEnd();
pageEnd();

?>