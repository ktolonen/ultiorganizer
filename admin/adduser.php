<?php
include_once $include_prefix.'lib/common.functions.php';

if((!empty($_GET["season"]) && !isSeasonAdmin($_GET["season"])) && !isSuperAdmin()){
    die("Insufficient user rights");
}

$html = "";
$mailsent = false;
if(!empty($_POST['save'])) {
  $newUsername=$_POST['UserName'];
  $newPassword=$_POST['Password'];
  $newName=$_POST['Name'];
  $newEmail=$_POST['Email'];
  $error = 0;
  $message = "";
  if(empty($newUsername)|| strlen($newUsername) < 3 || strlen($newUsername) > 50)
  {
    $html .= "<p>"._("Username is too short (min. 3 letters)").".</p>";
    $error = 1;
  }
  if (IsRegistered($newUsername))
  {
    $html .=  "<p>"._("The username is already in use").".</p>";
    $error = 1;
  }
  if(empty($newPassword) || strlen($newPassword) <5 || strlen($newPassword) > 20)
  {
    $html .=  "<p>"._("Password is too short (min. 5 letters).").".</p>";
    $error = 1;
  }
  if(empty($newName))
  {
    $html .= "<p>"._("Name can not be empty").".</p>";
    $error = 1;
  }

  if(empty($newEmail)) {
    $html .= "<p>"._("Email can not be empty").".</p>";
    $error = 1;
  }

  if (!validEmail($newEmail)) {
    $html .= "<p>"._("Invalid email address").".</p>";
    $error = 1;
  }

  $uidcheck = mysql_real_escape_string($newUsername);

  if($uidcheck != $newUsername || preg_match('/[ ]/', $newUsername) /*|| preg_match('/[^a-z0-9._]/i', $newUsername)*/)
  {
    $html .= "<p>"._("User id may not have spaces or special characters").".</p>";
    $error = 1;
  }

  $pswcheck = mysql_real_escape_string($newPassword);

  if($pswcheck != $newPassword)
  {
    $html .= "<p>"._("Illegal characters in the password").".</p>";
    $error = 1;
  }

  if ($error == 0) {
    if (AddRegisterRequest($newUsername, $newPassword, $newName, $newEmail)) {
      ConfirmRegisterUID($newUsername);
      AddEditSeason($newUsername, CurrentSeason());
      AddSeasonUserRole($newUsername, "teamadmin:".$_POST["team"], CurrentSeason());
      $html .= "<p>"._("Added new user") ."<br/>\n";
      $html .= _("Username").": ". $newUsername ."<br/>\n";
      $html .= _("Password").": ". $newPassword ."<br/>\n";
    }
  } else {
    $html .= "<p>"._("Correct the errors and try again").".</p>\n";
  }
}

$LAYOUT_ID = REGISTER;
$title = _("Add new user");
//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();


$html .= "<form method='post' action='?view=admin/adduser";
$html .= "'>\n";
$html .= "<table cellpadding='8'>
		<tr><td class='infocell'>"._("Name").":</td>
			<td><input type='text' class='input' maxlength='256' id='Name' name='Name' value='";
if (isset($_POST['Name'])) $html .= $_POST['Name'];
$html .= "'/></td></tr>
		<tr><td class='infocell'>"._("Username").":</td>
			<td><input type='text' class='input' maxlength='50' id='UserName' name='UserName' value='";
if (isset($_POST['UserName'])) $html .= $_POST['UserName'];
$html .= "'/></td></tr>
		<tr><td class='infocell'>"._("Password").":</td>
			<td><input type='text' class='input' maxlength='20' id='Password' name='Password' value='";
if (isset($_POST['Password'])) $html .= $_POST['Password'];
else $html .= UserCreateRandomPassword();
$html .= "'/></td></tr>
		<tr><td class='infocell'>"._("Email").":</td>
			<td><input type='text' class='input' maxlength='512' id='Email' name='Email' size='40' value='";
if (isset($_POST['Email'])) $html .= $_POST['Email'];
$html .= "'/></td></tr>";

$html .= "<tr><td class='infocell'>"._("Responsible team").":</td>";
$teams = SeasonTeams(CurrentSeason());
$html .= "<td><select class='dropdown' name='team'>";
if(isset($_POST['team']))
$html .= "<option class='dropdown' value='0'></option>";
else
$html .= "<option class='dropdown' selected='selected' value='0'></option>";

foreach($teams as $team){
  if(isset($_POST['team']) && $team['team_id']==$_POST['team'])
  $html .= "<option class='dropdown' selected='selected' value='".utf8entities($team['team_id'])."'>". utf8entities(U_($team['seriesname']))." ". utf8entities($team['name']) ."</option>";
  else
  $html .= "<option class='dropdown' value='".utf8entities($team['team_id'])."'>". utf8entities(U_($team['seriesname']))." ". utf8entities($team['name']) ."</option>";
}

$html .= "</select></td></tr>";

$html .= "<tr><td colspan = '2' align='right'><br/>
	      <input class='button' type='submit' name='save' value='"._("Add")."' />
	      </td></tr>\n";

$html .= "</table>\n";
$html .= "</form>";

echo $html;

//common end
contentEnd();
pageEnd();
?>
