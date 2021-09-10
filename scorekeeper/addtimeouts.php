<?php
$html = "";
$maxtimeouts = 4;

$gameId = isset($_GET['game']) ? $_GET['game'] : $_SESSION['game'];
$_SESSION['game'] = $gameId;

$game_result = GameResult($gameId);

if(isset($_POST['save'])) {
  $time = "0.0";
  $time_delim = array(",", ";", ":", "#", "*");

  //remove all old timeouts (if any)
  GameRemoveAllTimeouts($gameId);

  //insert home timeouts
  $j=0;
  for($i=0;$i<$maxtimeouts; $i++){
    $timemm = $_POST['htomm'.$i];
    $timess = $_POST['htoss'.$i];
    $time = $timemm.".".$timess;

    if(($timemm+$timess)>0){
      $j++;
      GameAddTimeout($gameId, $j, TimeToSec($time), 1);
    }
  }

  //insert away timeouts
  $j=0;
  for($i=0;$i<$maxtimeouts; $i++){
    $timemm = $_POST['atomm'.$i];
    $timess = $_POST['atoss'.$i];
    $time = $timemm.".".$timess;

    if(($timemm+$timess)>0){
      $j++;
      GameAddTimeout($gameId, $j, TimeToSec($time), 0);
    }
  }

  header("location:?view=addscoresheet&game=".$gameId);
}

$html .= "<div data-role='header'>\n";
$html .= "<h1>"._("Time-outs").": ".utf8entities($game_result['hometeamname'])." - ".utf8entities($game_result['visitorteamname'])."</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";


$html .= "<form action='?view=addtimeouts' method='post' data-ajax='false'>\n";


$html .= "<label for='timemm0' class='select'><b>".utf8entities($game_result['hometeamname'])."</b> "._("time outs").":</label>";
$html .= "<div class='ui-grid-b'>";

//used timeouts
$j=0;
$timemm=0;
$timess=0;

$timeouts = GameTimeouts($gameId);

while($timeout = mysqli_fetch_assoc($timeouts)){
  if (intval($timeout['ishome'])){
    $html .= "<div class='ui-block-a'>\n";

    $time = explode(".", SecToMin($timeout['time']));
    $timemm = $time[0];
    $timess = $time[1];


    $html .= "<select id='htomm$j' name='htomm$j' >";
    for($i=0;$i<=180;$i++){
      if($i==$timemm){
        $html .= "<option value='".$i."' selected='selected'>".$i."</option>";
      }else{
        $html .= "<option value='".$i."'>".$i."</option>";
      }
    }
    $html .= "</select>";
    $html .= "</div>";
    $html .= "<div class='ui-block-b'>\n";
    $html .= "<select id='htoss$j' name='htoss$j' >";
    for($i=0;$i<=60;$i=$i+5){
      if($i==$timess){
        $html .= "<option value='".$i."' selected='selected'>".$i."</option>";
      }else{
        $html .= "<option value='".$i."'>".$i."</option>";
      }
    }
    $html .= "</select>";
    $html .= "</div>";
    $j++;
  }
}

//empty slots
for($j;$j<$maxtimeouts; $j++){

  $html .= "<div class='ui-block-a'>\n";
  $html .= "<select id='htomm$j' name='htomm$j' >";
  $timemm=0;
  $timess=0;
  for($i=0;$i<=180;$i++){
    if($i==$timemm){
      $html .= "<option value='".$i."' selected='selected'>".$i."</option>";
    }else{
      $html .= "<option value='".$i."'>".$i."</option>";
    }
  }
  $html .= "</select>";
  $html .= "</div>";
  $html .= "<div class='ui-block-b'>\n";
  $html .= "<select id='htoss$j' name='htoss$j' >";
  for($i=0;$i<=60;$i=$i+5){
    if($i==$timess){
      $html .= "<option value='".$i."' selected='selected'>".$i."</option>";
    }else{
      $html .= "<option value='".$i."'>".$i."</option>";
    }
  }
  $html .= "</select>";
  $html .= "</div>";

}
$html .= "</div>";

$html .= "<label for='timemm0' class='select'><b>".utf8entities($game_result['visitorteamname'])."</b> "._("time outs").":</label>";
$html .= "<div class='ui-grid-b'>";

//used timeouts
$j=0;

$timeouts = GameTimeouts($gameId);

while($timeout = mysqli_fetch_assoc($timeouts)){
  if(!intval($timeout['ishome'])){
    $html .= "<div class='ui-block-a'>\n";

    $time = explode(".", SecToMin($timeout['time']));
    $timemm = $time[0];
    $timess = $time[1];

    $html .= "<select id='atomm$j' name='atomm$j' >";
    for($i=0;$i<=180;$i++){
      if($i==$timemm){
        $html .= "<option value='".$i."' selected='selected'>".$i."</option>";
      }else{
        $html .= "<option value='".$i."'>".$i."</option>";
      }
    }
    $html .= "</select>";
    $html .= "</div>";
    $html .= "<div class='ui-block-b'>\n";
    $html .= "<select id='atoss$j' name='atoss$j' >";
    for($i=0;$i<=55;$i=$i+5){
      if($i==$timess){
        $html .= "<option value='".$i."' selected='selected'>".$i."</option>";
      }else{
        $html .= "<option value='".$i."'>".$i."</option>";
      }
    }
    $html .= "</select>";
    $html .= "</div>";
    $j++;
  }
}

//empty slots
for($j;$j<$maxtimeouts; $j++){

  $html .= "<div class='ui-block-a'>\n";
  $html .= "<select id='atomm$j' name='atomm$j' >";
  $timemm=0;
  $timess=0;
  for($i=0;$i<=180;$i++){
    if($i==$timemm){
      $html .= "<option value='".$i."' selected='selected'>".$i."</option>";
    }else{
      $html .= "<option value='".$i."'>".$i."</option>";
    }
  }
  $html .= "</select>";
  $html .= "</div>";
  $html .= "<div class='ui-block-b'>\n";
  $html .= "<select id='atoss$j' name='atoss$j' >";
  for($i=0;$i<=55;$i=$i+5){
    if($i==$timess){
      $html .= "<option value='".$i."' selected='selected'>".$i."</option>";
    }else{
      $html .= "<option value='".$i."'>".$i."</option>";
    }
  }
  $html .= "</select>";
  $html .= "</div>";

}
$html .= "</div>";

$html .= "<input type='submit' name='save' data-ajax='false' value='"._("Save")."'/>";
$html .= "<a href='?view=addscoresheet&amp;game=".$gameId."' data-role='button' data-ajax='false'>"._("Back to score sheet")."</a>";


$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;


?>
