<?php
ob_start();
?>
<!--
[CLASSIFICATION]
category=database
type=import
format=xml
security=superadmin
customization=WFDF

[DESCRIPTION]
title = "Import FFindr registration data from XML file"
description = "XML file format"
-->
<?php
ob_end_clean();
if (!isSuperAdmin()){die('Insufficient user rights');}

include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
if (is_file('cust/'.CUSTOMIZATIONS.'/teamplayers.functions.php')) {
  include_once 'cust/'.CUSTOMIZATIONS.'/teamplayers.functions.php';
}
$imported = false;
$html = "";
$title = ("Import FFindr data from XML-file");
$seasonId = "";

if (isset($_POST['add']) && isSuperAdmin()){
  if(is_uploaded_file($_FILES['restorefile']['tmp_name'])) {

    $templine = '';
    set_time_limit(300);
    $eventdatahandler = new XMLHandler();
    $eventdatahandler->XMLToEvent($_FILES['restorefile']['tmp_name'], $seasonId, "new");
    unlink($_FILES['restorefile']['tmp_name']);
    $imported = true;
  }
}elseif (isset($_POST['replace'])){
  if(is_uploaded_file($_FILES['restorefile']['tmp_name'])) {

    $templine = '';
    set_time_limit(300);
    $eventdatahandler = new XMLHandler();
    $eventdatahandler->XMLToEvent($_FILES['restorefile']['tmp_name'], $seasonId, "new");
    unlink($_FILES['restorefile']['tmp_name']);
    $imported = true;
  }
}

//common page
ini_set("post_max_size", "30M");
ini_set("upload_max_filesize", "30M");
ini_set("memory_limit", -1 );

if($imported){
  $html .= "<p>"._("Data imported!")."</p>";
  unset($_POST['restore']);
  unset($_POST['replace']);
}

$html .= "<form method='post' enctype='multipart/form-data' action='?view=plugins/import_xml_ffindr'>\n";

$html .= "<p><span class='profileheader'>"._("Select file to import").": </span></p>\n";

$html .= "<p><input class='input' type='file' size='80' name='restorefile'/>";
$html .= "<input type='hidden' name='MAX_FILE_SIZE' value='100000000'/></p>";

if(empty($seasonId)){
  $html .= "<p><input class='button' type='submit' name='add' value='"._("Import")."'/></p>";
}else{
  $html .= "<p>"._("This operation updates and adds event data in database with one from file. It will not delete any data or change user rights.")."</p>";
  $html .= "<p><input class='button' type='submit' name='replace' value='"._("Update")."'/></p>";
}

$html .= "</form>";

showPage($title, $html);

class XMLHandler{
  var $eventId; //event id under processing
  var $divisionId; //division id under processing
  var $teamId; //team id under processing
  var $playerId; //player id under processing
  var $profileId; //profile id under processing
  var $clubId; //club id under processing
  var $tag; //tag under processing

  var $mode; //import mode: 'add' or 'replace'

  /**
   * Default construction
   */
  function XMLHandler(){}

  /**
   * Updates event from given xml-file.
   * @param string $filename Name of XML-file uploaded
   * @param string $eventId Event to Update or empty if new event created
   * @param string $mode new - for new event, replace - to update existing event
   */
  function XMLToEvent($filename, $eventId="", $mode="new"){
    $this->mode = $mode;
    $this->eventId = $eventId;

    if((empty($this->eventId) && isSuperAdmin()) || isSeasonAdmin($eventId)){
      //create parser and set callback functions
      $xmlparser = xml_parser_create();
      xml_set_element_handler($xmlparser, array($this, "start_tag"), array($this, "end_tag"));
      xml_set_default_handler($xmlparser, array($this, "content"));

      if (!($fp = fopen($filename, "r"))) { die("cannot open ".$filename); }

      //remove extra spaces
      while ($data = fread($fp, 4096)){
        $data=preg_replace('/\s\s+/', ' ', $data);
        if (!xml_parse($xmlparser, $data, feof($fp))) {
          $reason = xml_error_string(xml_get_error_code($xmlparser));
          $reason .= xml_get_current_line_number($xmlparser);
          die($reason);
        }
      }
       
      xml_parser_free($xmlparser);
    } else { die('Insufficient rights to import data'); }
  }

  /**
   * Callback function for element start.
   * @param xmlparser $parser a reference to the XML parser calling the handler.
   * @param string $name a element name
   * @param array $attribs element's attributes
   */
  function start_tag($parser, $name, $attribs) {

    if (is_array($attribs)) {
      $row = array();
      while(list($key,$val) = each($attribs)) {
        if($val=="NULL"){
          $row[$key]="NULL";
        }elseif(is_int($val) || $val==-1){
          $row[$key]=(int)($val);
        }else{
          $row[$key]=mysql_real_escape_string($val);
        }
      }
      switch($this->mode){
        case "new":
          $this->InsertToDatabase($name, $row);
          break;
           
        case "replace":
          $this->ReplaceInDatabase($name, $row);
          break;
      }
    }
  }
  /**
   * Callback function for element end.
   * @param xmlparser $parser a reference to the XML parser calling the handler.
   * @param string $name a element name
   */
  function end_tag($parser, $name) {

    switch($name){
      case "EVENT":
        $this->eventId = 0;
        break;

      case "DIVISION":
        $this->divisionId = 0;
        break;

      case "TEAM":
        $this->teamId = 0;
        break;
         
      case "PLAYER":
        $this->playerId = 0;
        break;

      case "CLUB":
        $this->clubId = 0;
        break;
    }
    $this->tag = "";
  }

  /**
   * Callback function for element content.
   * @param xmlparser $parser a reference to the XML parser calling the handler.
   * @param string $content a element data
   */
  function content($parser, $content) {

    $data = array();

    switch($this->tag){
      case "STORY":
        $data['story']=$content;
        if($this->playerId){
          $info = PlayerInfo($this->playerId);
          $cond = "profile_id=".$info['profile_id'];
          $name = "uo_player_profile";
        }elseif($this->teamId){
          $cond = "team_id=".$this->teamId;
          $name = "uo_team_profile";
        }elseif($this->clubId){
          $cond = "club_id=".$this->clubId;
          $name = "uo_club";
        }
        $this->SetRow($name,$data,$cond);
        break;

      case "ACHIEVEMENTS":
        $data['achievements']=$content;
        if($this->playerId){
          $info = PlayerInfo($this->playerId);
          $cond = "profile_id=".$info['profile_id'];
          $name = "uo_player_profile";
        }elseif($this->teamId){
          $cond = "team_id=".$this->teamId;
          $name = "uo_team_profile";
        }elseif($this->clubId){
          $cond = "club_id=".$this->clubId;
          $name = "uo_club";
        }
        $this->SetRow($name,$data,$cond);
        break;

      case "INFO":
        $data['info']=$content;
        if($this->playerId){
          $info = PlayerInfo($this->playerId);
          $cond = "profile_id=".$info['profile_id'];
          $name = "uo_player_profile";
        }elseif($this->teamId){
          $cond = "team_id=".$this->teamId;
          $name = "uo_team_profile";
        }elseif($this->clubId){
          $cond = "club_id=".$this->clubId;
          $name = "uo_club";
        }
        $this->SetRow($name,$data,$cond);
        break;

      case "CONTACTS":
        $data['contacts']=$content;
        if($this->playerId){
          $info = PlayerInfo($this->playerId);
          $cond = "profile_id=".$info['profile_id'];
          $name = "uo_player_profile";
        }elseif($this->teamId){
          $cond = "team_id=".$this->teamId;
          $name = "uo_team_profile";
        }elseif($this->clubId){
          $cond = "club_id=".$this->clubId;
          $name = "uo_club";
        }
        $this->SetRow($name,$data,$cond);
        break;
    }
  }
   
  /**
   * Does id mappings before inserts data into database as new data.
   * @param string $name Name of the table to insert
   * @param array $row Data to insert: key=>field, value=>data
   *
   * @see EventDataXMLHandler::InsertRow()
   */
  function InsertToDatabase($name, $row){

    $data = array();

    switch($name){
      case "EVENT":

        $id = $row['ID'];
        $season = SeasonInfo($id);

        //if event doesn't exist
        if(!isset($season['season_id'])){
          $data['season_id']=$id;
          $data['name']=$row['NAME'];

          $this->InsertRow("uo_season", $data);

          AddEditSeason($_SESSION['uid'],$id);
          AddUserRole($_SESSION['uid'], 'seasonadmin:'.$id);
        }
        
        $this->eventId=$id;
        break;

      case "DIVISION":
        
        $cond = "name='".mysql_real_escape_string($row['NAME'])."' AND season='".$this->eventId."'";
        $query = "SELECT * FROM uo_series WHERE ".$cond;
        $exist = DBQueryRowCount($query);
        
        if(!$exist){
          $data['name']=$row['NAME'];
          $data['season']=$this->eventId;
          $data['valid']=1;
          $this->divisionId = $this->InsertRow("uo_series", $data);
        }else{
          $division_info=DBQueryToRow($query);
          $this->divisionId = $division_info['series_id'];
        }
        break;

      case "TEAM":
        $cond = "name='".mysql_real_escape_string($row['NAME'])."' AND series='".$this->divisionId."'";
        $query = "SELECT * FROM uo_team WHERE ".$cond;
        $exist = DBQueryRowCount($query);
        
        if(!$exist){
          $clubname=isset($row['CLUB'])?$row['CLUB']:"";
          $countryname=isset($row['COUNTRY'])?$row['COUNTRY']:"";
          
                //team adming
        $email=isset($row['EMAIL'])?$row['EMAIL']:"";
        if(!empty($email)){
          $query = sprintf("SELECT * FROM uo_users WHERE email= '%s'",
              mysql_real_escape_string($email));
          $user = DBQueryToRow($query);
          if(!$user){
            $query = sprintf("INSERT INTO uo_users (name, userid, password, email) VALUES ('%s', '%s', MD5('%s'), '%s')",
			  mysql_real_escape_string($email),
			  mysql_real_escape_string($email),
			  mysql_real_escape_string(CreateRandomPassword()),
			  mysql_real_escape_string($email));
			DBQuery($query);
			
			$query = sprintf("INSERT INTO uo_userproperties (userid, name, value) VALUES ('%s', 'poolselector', 'currentseason')",
			  mysql_real_escape_string($email));
            DBQuery($query);
            $userid=mysql_real_escape_string($email);	  
          }else{
            $userid=$user['userid'];
          }
        }else{
          $userid = $_SESSION['uid'];
        }

          $id = AddSeriesEnrolledTeam($this->divisionId, $userid, $row['NAME'], $clubname, $countryname);
          $this->teamId = ConfirmEnrolledTeam($this->divisionId, $id);
        }else{
          $team_info=DBQueryToRow($query);
          $this->teamId = $team_info['team_id'];
        }
        $data['abbreviation']=isset($row['ABBREVIATION'])?$row['ABBREVIATION']:"";
        $data['rank']=isset($row['SEED'])?$row['SEED']:"";
        $cond = "team_id=".$this->teamId;
        $this->SetRow("uo_team",$data,$cond);
                
        //profile
        $cond = "team_id=".$this->teamId;
        $query = "SELECT * FROM uo_team_profile WHERE ".$cond;
        $exist = DBQueryRowCount($query);
        
        $teamprofile['coach']=isset($row['COACH'])?$row['COACH']:"";
        $teamprofile['captain']=isset($row['CAPTAIN'])?$row['CAPTAIN']:"";
        $teamprofile['ffindr_id']=isset($row['FFINDR_ID'])?$row['FFINDR_ID']:"";
         
        if(!$exist){
          $teamprofile['team_id']=$this->teamId;
          $this->InsertRow("uo_team_profile", $teamprofile);
        }else{
          $this->SetRow("uo_team_profile",$teamprofile,$cond);
        }
        break;
         
      case "PLAYER":
        
        if(isset($row['FIRSTNAME'])){ 
          $row['FIRSTNAME']=stripslashes($row['FIRSTNAME']);
          $row['LASTNAME']=stripslashes($row['LASTNAME']);
          $cond = "firstname='".mysql_real_escape_string($row['FIRSTNAME'])."' AND lastname='".mysql_real_escape_string($row['LASTNAME'])."' AND team='".$this->teamId."'";
        }else{
          $row['LASTNAME']=stripslashes($row['LASTNAME']);
          $cond = "lastname='".mysql_real_escape_string($row['LASTNAME'])."' AND team='".$this->teamId."'";
        }
        $query = "SELECT * FROM uo_player WHERE ".$cond;
        $exist1 = DBQueryRowCount($query);
        
        $profileId=isset($row['ULTIORGANIZER_ID'])?$row['ULTIORGANIZER_ID']:0;
        $player_profile = false;
        $exist2 = 0;
        if($profileId>0){
          $player_profile = PlayerProfile($profileId);
          $cond = "profile_id='".$profileId."' AND team='".$this->teamId."'";
          $query = "SELECT * FROM uo_player WHERE ".$cond;
          $exist2 = DBQueryRowCount($query);
        }else{
          $query = sprintf("SELECT pp.* FROM uo_player_profile pp
								WHERE UPPER(CONCAT(pp.firstname,' ',pp.lastname)) like '%%%s%%'",
								mysql_real_escape_string(strtoupper($row['LASTNAME'])));
		  if(DBQueryRowCount($query)==1){
		    $player=DBQueryToRow($query);
		    $profileId=$player['profile_id'];
		    $player_profile = PlayerProfile($player['profile_id']);
		    $cond = "profile_id='".$profileId."' AND team='".$this->teamId."'";
            $query = "SELECT * FROM uo_player WHERE ".$cond;
            $exist2 = DBQueryRowCount($query);
		  }
        }
        
        if(!$exist1 && !$exist2){
          
          if($player_profile){
            $firstname=$player_profile['firstname'];
            $lastname=$player_profile['lastname'];
            $jersey=isset($row['JERSEY'])?$row['JERSEY']:$player_profile['num'];
          }else{
            $firstname=isset($row['FIRSTNAME'])?$row['FIRSTNAME']:"";
            $lastname=isset($row['LASTNAME'])?$row['LASTNAME']:"";
            $jersey=isset($row['JERSEY'])?$row['JERSEY']:"-1";
            $profileId=0;
          }
          
          $this->playerId = AddPlayer($this->teamId,$firstname,$lastname,$profileId,$jersey);
          //if(empty($accId)){
          //  CreatePlayerProfile($this->playerId);
          //}
          AccreditPlayer($this->playerId, "FFinder dataimporter");
        }else{
          $player_info=DBQueryToRow($query);
          $this->playerId = $player_info['player_id'];          
          $data['num']=isset($row['JERSEY'])?$row['JERSEY']:"";
          $cond = "player_id=".$this->playerId;
          $this->SetRow("uo_player",$data,$cond);
        }
        
        break;

      case "PROFILE":

        $data['email']=isset($row['EMAIL'])?$row['EMAIL']:"";
        $data['nickname']=isset($row['NICKNAME'])?$row['NICKNAME']:"";
        $data['birthdate']=isset($row['BIRTHDATE'])?$row['BIRTHDATE']:"";
        $data['birthdate']=ToInternalTimeFormat($data['birthdate']);
        $data['birthplace']=isset($row['BIRTHPLACE'])?$row['BIRTHPLACE']:"";
        $data['nationality']=isset($row['NATIONALITY'])?$row['NATIONALITY']:"";
        $data['throwing_hand']=isset($row['THROWING_HAND'])?$row['THROWING_HAND']:"";
        $data['height']=isset($row['HEIGHT'])?$row['HEIGHT']:"";
        $data['weight']=isset($row['WEIGHT'])?$row['WEIGHT']:"";
        $data['position']=isset($row['POSITION'])?$row['POSITION']:"";
        $data['gender']=isset($row['GENDER'])?$row['GENDER']:"";
        $data['ffindr_id']=isset($row['FFINDR_ID'])?$row['FFINDR_ID']:"";
        
        $player_info = PlayerInfo($this->playerId);
        //$data['accreditation_id']=$player_info['profile_id'];
        $data['firstname']=isset($player_info['firstname'])?$player_info['firstname']:"";
        $data['lastname']=isset($player_info['lastname'])?$player_info['lastname']:"";
        $data['num']=isset($player_info['num'])?$player_info['num']:"-1";
        //echo "<p>".$this->playerId." ".$data['lastname']." ".$player_info['profile_id']."</p>";
        $cond = "profile_id=".$player_info['profile_id'];
        $this->SetRow("uo_player_profile",$data,$cond);

        break;
      case "CLUB":
         
        $data['city']=isset($row['CITY'])?$row['CITY']:"";
        $data['founded']=isset($row['FOUNDED'])?$row['FOUNDED']:"";

        $countryname=isset($row['COUNTRY'])?$row['COUNTRY']:"";

        $data['country'] = CountryId($countryname);

        $this->clubId=ClubId($row['NAME']);

        $cond = "club_id=".$this->clubId;
        $this->SetRow("uo_club",$data,$cond);

        break;
    }
    $this->tag=$name;
  }

  /**
   * Inserts data into database as new data.
   * @param string $name Name of the table to insert
   * @param array $row Data to insert: key=>field, value=>data
   */
  function InsertRow($name, $data){
     
    $values = "'".implode("','",array_values($data))."'";
    $fields = implode(",",array_keys($data));

    $query = "INSERT INTO ".mysql_real_escape_string($name)." (";
    $query .= mysql_real_escape_string($fields);
    $query .= ") VALUES (";
    $query .= $values;
    $query .= ")";
    return DBQueryInsert($query);

  }

  /**
   * Set data into database by updating existing row.
   * @param string $name Name of the table to update
   * @param array $row Data to insert: key=>field, value=>data
   */
  function SetRow($name, $row, $cond){

    $values = array_values($row);
    $fields = array_keys($row);

    $query = "UPDATE ".mysql_real_escape_string($name)." SET ";

    for($i=0;$i<count($fields);$i++){
      $query .= mysql_real_escape_string($fields[$i]) ."='".mysql_real_escape_string($values[$i])."', ";
    }
    $query = rtrim($query,', ');
    $query .= " WHERE ";
    $query .= mysql_real_escape_string($cond);
    return DBQueryInsert($query);
  }
}

?>
