<?php
/**
 * @file
 * This file contains Event Data XML Handler data handling functions for xml<->sql.
 *
 */

/**
 * @class EventDataXMLHandler
 *
 */
class EventDataXMLHandler{
  var $eventId; //event id under processing
  var $uo_season=array(); //event id mapping array
  var $uo_series=array(); //series id mapping array
  var $uo_team=array(); //team id mapping array
  var $uo_pool=array(); //pool id mapping array
  var $uo_player=array(); //player id mapping array
  var $uo_game=array(); //game id mapping array
  var $uo_reservation=array(); //reservation id mapping array
  var $mode; //import mode: 'add' or 'replace'

  /**
   * Default construction
   */
  function EventDataXMLHandler(){}

  /**
   * Converts element data into xml-format.
   *
   * xml-structure
   * <uo_season>
   *  <uo_reservation></uo_reservation>
   *  <uo_series>
   *    <uo_team>
   *      <uo_player></uo_player>
   *    </uo_team>
   *    <uo_pool>
   *	    <uo_team_pool></uo_team_pool>
   *      <uo_game>
   *        <uo_goal></uo_goal>
   *        <uo_gameevent></uo_gameevent>
   *        <uo_played></uo_played>
   *      </uo_game>
   *    </uo_pool>
   *  <uo_game_pool></uo_game_pool>
   *  <uo_moveteams></uo_moveteams>
   *  </uo_series>
   *</uo_season>
   *
   * @param string $eventId Event to conver into xml-format.
   * @return string event data in xml
   */

  function EventToXML($eventId){
     
    if (isSeasonAdmin($eventId)) {
      $ret = "";
      $ret .= "<?xml version='1.0' encoding='UTF-8'?>\n";
      //uo_season
      $seasons = DBQuery("SELECT * FROM uo_season WHERE season_id='".mysql_real_escape_string($eventId)."'");
      $row = mysql_fetch_assoc($seasons);
      $ret .= $this->RowToXML("uo_season", $row, false);

      //uo_reservation
      $reservations = DBQuery("SELECT * FROM uo_reservation WHERE season='".mysql_real_escape_string($eventId)."'");
      while($reservation = mysql_fetch_assoc($reservations)){
        $ret .= $this->RowToXML("uo_reservation", $reservation);
      }

      //uo_series
      $series = DBQuery("SELECT * FROM uo_series WHERE season='".mysql_real_escape_string($eventId)."'");
      while($ser = mysql_fetch_assoc($series)){
        $ret .= $this->RowToXML("uo_series", $ser, false);
         
        //uo_team
        $teams = DBQuery("SELECT * FROM uo_team WHERE series='".mysql_real_escape_string($ser['series_id'])."'");
        while($team = mysql_fetch_assoc($teams)){
          $ret .= $this->RowToXML("uo_team", $team, false);
          //uo_player
          $players = DBQuery("SELECT * FROM uo_player WHERE team='".mysql_real_escape_string($team['team_id'])."'");
          while($player = mysql_fetch_assoc($players)){
            $ret .= $this->RowToXML("uo_player", $player);
          }
          $ret .= "</uo_team>\n";
        }
         
        //uo_pool
        $pools = DBQuery("SELECT * FROM uo_pool WHERE series='".mysql_real_escape_string($ser['series_id'])."'");
        while($row = mysql_fetch_assoc($pools)){
          $ret .= $this->RowToXML("uo_pool", $row, false);

          //uo_team_pool
          $teampools = DBQuery("SELECT * FROM uo_team_pool WHERE pool='".mysql_real_escape_string($row['pool_id'])."'");
          while($teampool = mysql_fetch_assoc($teampools)){
            $ret .= $this->RowToXML("uo_team_pool", $teampool);
          }

          //uo_game
          $games = DBQuery("SELECT * FROM uo_game WHERE pool='".mysql_real_escape_string($row['pool_id'])."'");
          while($row = mysql_fetch_assoc($games)){
            $ret .= $this->RowToXML("uo_game", $row, false);
             
            //uo_goal
            $goals = DBQuery("SELECT * FROM uo_goal WHERE game='".mysql_real_escape_string($row['game_id'])."'");
            while($goal = mysql_fetch_assoc($goals)){
              $ret .= $this->RowToXML("uo_goal", $goal);
            }
            //uo_gameevent
            $gameevents = DBQuery("SELECT * FROM uo_gameevent WHERE game='".mysql_real_escape_string($row['game_id'])."'");
            while($gameevent = mysql_fetch_assoc($gameevents)){
              $ret .= $this->RowToXML("uo_gameevent", $gameevent);
            }
            //uo_played
            $playedplayers = DBQuery("SELECT * FROM uo_played WHERE game='".mysql_real_escape_string($row['game_id'])."'");
            while($playedplayer = mysql_fetch_assoc($playedplayers)){
              $ret .= $this->RowToXML("uo_played", $playedplayer);
            }
            $ret .= "</uo_game>\n";
          }
          $ret .= "</uo_pool>\n";
        }
         
        //uo_moveteams
        $moveteams = DBQuery("SELECT m.* FROM uo_moveteams m
				LEFT JOIN uo_pool p ON(m.frompool=p.pool_id) 
				WHERE p.series='".mysql_real_escape_string($ser['series_id'])."'");
        while($moveteam = mysql_fetch_assoc($moveteams)){
          $ret .= $this->RowToXML("uo_moveteams", $moveteam);
        }
         
        //uo_game_pool
        $gamepools = DBQuery("SELECT g.* FROM uo_game_pool g
				LEFT JOIN uo_pool p ON(g.pool=p.pool_id)
				WHERE p.series='".mysql_real_escape_string($ser['series_id'])."'");
        while($gamepool = mysql_fetch_assoc($gamepools)){
          $ret .= $this->RowToXML("uo_game_pool", $gamepool);
        }
        $ret .= "</uo_series>\n";
      }

      $ret .= "</uo_season>\n";

      return $ret;
    } else { die('Insufficient rights to export data'); }
  }

  /**
   * Converts database row to xml.
   * @param string $elementName - name of xml-element (table name)
   * @param assoc_array $row - element attributes (table row)
   * @param boolean $endtag - true if element closed
   *
   * @return string XML-data
   */
  function RowToXML($elementName, $row, $endtag=true){

    $columns = array_keys($row);
    $values = array_values($row);
    $total = count($row);
    $ret = "<".$elementName." ";
     
    for ($i=0; $i < $total; $i++) {
      if($values[$i]==''){
        $ret .=  $columns[$i]."='NULL' ";
      }else{
        $ret .=  $columns[$i]."='".htmlspecialchars($values[$i],ENT_QUOTES,"UTF-8")."' ";
      }
    }

    if($endtag){
      $ret .= "/>\n";
    }else{
      $ret .= ">\n";
    }

    return $ret;
  }

  /**
   * Creates or updates event from given xml-file.
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

      if (!($fp = fopen($filename, "r"))) { die("cannot open ".$filename); }

      //remove extra spaces
      while ($data = fread($fp, 4096)){
        $data=eregi_replace(">"."[[:space:]]+"."< ",">< ",$data);
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
   * @param array $attribs element's attriibutes
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
          $row[$key]="'".mysql_real_escape_string($val)."'";
        }
      }
      switch($this->mode){
        case "new":
          $this->InsertToDatabase(strtolower($name), $row);
          break;
           
        case "replace":
          $this->ReplaceInDatabase(strtolower($name), $row);
          break;
      }
    }
  }
  /**
   * Callback function for element end.
   * @param xmlparser $parser a reference to the XML parser calling the handler.
   * @param string $name a element name
   */
  function end_tag($parser, $name) {}
   
  /**
   * Does id mappings before inserts data into database as new data.
   * @param string $name Name of the table to insert
   * @param array $row Data to insert: key=>field, value=>data
   *
   * @see EventDataXMLHandler::InsertRow()
   */
  function InsertToDatabase($name, $row){

    switch($name){
      case "uo_season":

        $key = str_replace("'",'',$row["SEASON_ID"]);

        $season = SeasonInfo($key);
        //if season exist
        if(count($season)){
          $newId = mysql_real_escape_string(substr($key,0,7)). rand(1,100);
          $row["NAME"] = "'".str_replace("'",'',$row["NAME"])." (1)'";
        }else{
          $newId = mysql_real_escape_string($key);
        }
        $this->uo_season[$row["SEASON_ID"]]="'".$newId."'";
        unset($row["SEASON_ID"]);

        $values = implode(",",array_values($row));
        $fields = implode(",",array_keys($row));

        $query = "INSERT INTO ".mysql_real_escape_string($name)." (";
        $query .= "SEASON_ID,";
        $query .= mysql_real_escape_string($fields);
        $query .= ") VALUES (";
        $query .= "'".$newId."',";
        $query .= $values;
        $query .= ")";
        DBQueryInsert($query);

        AddEditSeason($_SESSION['uid'],$newId);
        AddUserRole($_SESSION['uid'], 'seasonadmin:'.$newId);
        break;

      case "uo_series":
        $key = $row["SERIES_ID"];
        unset($row["SERIES_ID"]);
        $row["SEASON"] = $this->uo_season[$row["SEASON"]];

        $newId = $this->InsertRow($name, $row);
        $this->uo_series[$key]=$newId;
        break;

      case "uo_team":
        $key = $row["TEAM_ID"];
        unset($row["TEAM_ID"]);
        $row["SERIES"] = $this->uo_series[$row["SERIES"]];
         
        $newId = $this->InsertRow($name, $row);
        $this->uo_team[$key]=$newId;
        break;
         
      case "uo_player":
        $key = $row["PLAYER_ID"];
        unset($row["PLAYER_ID"]);
        $row["TEAM"] = $this->uo_team[$row["TEAM"]];
         
        $newId = $this->InsertRow($name, $row);
        $this->uo_player[$key]=$newId;
        break;

      case "uo_pool":
        $key = $row["POOL_ID"];
        unset($row["POOL_ID"]);
        $row["SERIES"] = $this->uo_series[$row["SERIES"]];
         
        $newId = $this->InsertRow($name, $row);
        $this->uo_pool[$key]=$newId;
        break;
         
      case "uo_reservation":
        $key = $row["ID"];
        unset($row["ID"]);
        $row["SEASON"] = $this->uo_season[$row["SEASON"]];
         
        $newId = $this->InsertRow($name, $row);
        $this->uo_reservation[$key]=$newId;
        break;
         
      case "uo_game":
        $key = $row["GAME_ID"];
        unset($row["GAME_ID"]);
        if(!empty($row["HOMETEAM"]) && $row["HOMETEAM"]!="NULL" && $row["HOMETEAM"]>0){
          $row["HOMETEAM"] = $this->uo_team[$row["HOMETEAM"]];
        }
        if(!empty($row["VISITORTEAM"]) && $row["VISITORTEAM"]!="NULL" && $row["VISITORTEAM"]>0){
          $row["VISITORTEAM"] = $this->uo_team[$row["VISITORTEAM"]];
        }
        if(!empty($row["RESPTEAM"]) && $row["RESPTEAM"]!="NULL" && $row["RESPTEAM"]>0){
          $row["RESPTEAM"] = $this->uo_team[$row["RESPTEAM"]];
        }
        if(!empty($row["RESERVATION"]) && isset($this->uo_reservation[$row["RESERVATION"]])){
          $row["RESERVATION"] = $this->uo_reservation[$row["RESERVATION"]];
        }
        if(!empty($row["POOL"])){
          $row["POOL"] = $this->uo_pool[$row["POOL"]];
        }
         
        $newId = $this->InsertRow($name, $row);
        $this->uo_game[$key]=$newId;
        break;

      case "uo_goal":
        $row["GAME"] = $this->uo_game[$row["GAME"]];
        if($row["ASSIST"]>=0){
          $row["ASSIST"] = $this->uo_player[$row["ASSIST"]];
        }
        if($row["SCORER"]>=0){
          $row["SCORER"] = $this->uo_player[$row["SCORER"]];
        }
        $this->InsertRow($name, $row);
        break;

      case "uo_gameevent":
        $row["GAME"] = $this->uo_game[$row["GAME"]];
        $this->InsertRow($name, $row);
        break;
         
      case "uo_played":
        $row["GAME"] = $this->uo_game[$row["GAME"]];
        $row["PLAYER"] = $this->uo_player[$row["PLAYER"]];
        $this->InsertRow($name, $row);
        break;

      case "uo_team_pool":
        $row["TEAM"] = $this->uo_team[$row["TEAM"]];
        $row["POOL"] = $this->uo_pool[$row["POOL"]];
        $this->InsertRow($name, $row);
        break;

      case "uo_game_pool":
        $row["GAME"] = $this->uo_game[$row["GAME"]];
        $row["POOL"] = $this->uo_pool[$row["POOL"]];
        $this->InsertRow($name, $row);
        break;

      case "uo_moveteams":
        $row["TOPOOL"] = $this->uo_pool[$row["TOPOOL"]];
        $row["FROMPOOL"] = $this->uo_pool[$row["FROMPOOL"]];
        $this->InsertRow($name, $row);
        break;
    }
  }

  /**
   * Inserts data into database as new data.
   * @param string $name Name of the table to insert
   * @param array $row Data to insert: key=>field, value=>data
   */
  function InsertRow($name, $row){
     
    $values = implode(",",array_values($row));
    $fields = implode(",",array_keys($row));

    $query = "INSERT INTO ".mysql_real_escape_string($name)." (";
    $query .= mysql_real_escape_string($fields);
    $query .= ") VALUES (";
    $query .= $values;
    $query .= ")";
    return DBQueryInsert($query);

  }

  /**
   * Does id mappings before updating data into database.
   * If primary key doesn't exist in database, then data is inserted into database.
   * @param string $name Name of the table to update
   * @param array $row Data to insert: key=>field, value=>data
   *
   * @see EventDataXMLHandler::InsertRow()
   * @see EventDataXMLHandler::SetRow()
   */
  function ReplaceInDatabase($name, $row){

    switch($name){
      case "uo_season":
        $cond = "season_id=".$row["SEASON_ID"];
        $query = "SELECT season_id FROM uo_season WHERE ". $cond;
        $exist = DBQueryRowCount($query);
        if($exist){
          if("'$this->eventId'" == $row["SEASON_ID"]){
            $this->SetRow($name,$row,$cond);
            $this->uo_season[$row["SEASON_ID"]]=$row["SEASON_ID"];
          }else{
            die(_("Target event is not the same as in a file."));
          }
        }else{
          die(_("Event to replace doesn't exist"));
        }
        break;

      case "uo_series":
        $key = $row["SERIES_ID"];
        unset($row["SERIES_ID"]);

        $cond = "series_id=".$key;
        $query = "SELECT * FROM ".$name." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($name,$row,$cond);
          $this->uo_series[$key]=$key;
        }else{
          $row["SEASON"] = $this->uo_season[$row["SEASON"]];
          $newId = $this->InsertRow($name, $row);
          $this->uo_series[$key]=$newId;
        }
        break;

      case "uo_team":
        $key = $row["TEAM_ID"];
        unset($row["TEAM_ID"]);

        $cond = "team_id=".$key;
        $query = "SELECT * FROM ".$name." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($name,$row,$cond);
          $this->uo_team[$key]=$key;
        }else{
          $row["SERIES"] = $this->uo_series[$row["SERIES"]];
          $newId = $this->InsertRow($name, $row);
          $this->uo_team[$key]=$newId;
        }
        break;
         
      case "uo_player":
        $key = $row["PLAYER_ID"];
        unset($row["PLAYER_ID"]);
        $row["TEAM"] = $this->uo_team[$row["TEAM"]];

        $cond = "player_id=".$key;
        $query = "SELECT * FROM ".$name." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($name,$row,$cond);
          $this->uo_player[$key]=$key;
        }else{
          $newId = $this->InsertRow($name, $row);
          $this->uo_player[$key]=$newId;
        }
        break;

      case "uo_pool":
        $key = $row["POOL_ID"];
        unset($row["POOL_ID"]);
        $row["SERIES"] = $this->uo_series[$row["SERIES"]];

        $cond = "pool_id=".$key;
        $query = "SELECT * FROM ".$name." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($name,$row,$cond);
          $this->uo_pool[$key]=$key;
        }else{
          $newId = $this->InsertRow($name, $row);
          $this->uo_pool[$key]=$newId;
        }
        break;
         
      case "uo_reservation":
        $key = $row["ID"];
        unset($row["ID"]);
        $row["SEASON"] = $this->uo_season[$row["SEASON"]];
         
        $cond = "id=".$key;
        $query = "SELECT * FROM ".$name." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($name,$row,$cond);
          $this->uo_reservation[$key]=$key;
        }else{
          $newId = $this->InsertRow($name, $row);
          $this->uo_reservation[$key]=$newId;
        }
        break;
         
      case "uo_game":
        $key = $row["GAME_ID"];
        unset($row["GAME_ID"]);
        $row["HOMETEAM"] = $this->uo_team[$row["HOMETEAM"]];
        $row["VISITORTEAM"] = $this->uo_team[$row["VISITORTEAM"]];
        if(!empty($row["RESPTEAM"]) && $row["RESPTEAM"]!="NULL" && $row["RESPTEAM"]>0){
          $row["RESPTEAM"] = $this->uo_team[$row["RESPTEAM"]];
        }
        if(!empty($row["RESERVATION"]) && isset($this->uo_reservation[$row["RESERVATION"]])){
          $row["RESERVATION"] = $this->uo_reservation[$row["RESERVATION"]];
        }
        if(!empty($row["POOL"])){
          $row["POOL"] = $this->uo_pool[$row["POOL"]];
        }
         
        $cond = "game_id=".$key;
        $query = "SELECT * FROM ".$name." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($name,$row,$cond);
          $this->uo_game[$key]=$key;
        }else{
          $newId = $this->InsertRow($name, $row);
          $this->uo_game[$key]=$newId;
        }
        break;

      case "uo_goal":
        $row["GAME"] = $this->uo_game[$row["GAME"]];
        if($row["ASSIST"]>=0){
          $row["ASSIST"] = $this->uo_player[$row["ASSIST"]];
        }
        if($row["SCORER"]>=0){
          $row["SCORER"] = $this->uo_player[$row["SCORER"]];
        }

        $cond = "game=".$row["GAME"]." AND num=".$row["NUM"];
        $query = "SELECT * FROM ".$name." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($name,$row,$cond);
        }else{
          $this->InsertRow($name, $row);
        }
        break;

      case "uo_gameevent":
        $row["GAME"] = $this->uo_game[$row["GAME"]];

        $cond = "game=".$row["GAME"]." AND num=".$row["NUM"];
        $query = "SELECT * FROM ".$name." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($name,$row,$cond);
        }else{
          $this->InsertRow($name, $row);
        }

        break;
         
      case "uo_played":
        $row["GAME"] = $this->uo_game[$row["GAME"]];
        $row["PLAYER"] = $this->uo_player[$row["PLAYER"]];

        $cond = "game=".$row["GAME"]." AND player=".$row["PLAYER"];
        $query = "SELECT * FROM ".$name." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($name,$row,$cond);
        }else{
          $this->InsertRow($name, $row);
        }
        break;

      case "uo_team_pool":
        $row["TEAM"] = $this->uo_team[$row["TEAM"]];
        $row["POOL"] = $this->uo_pool[$row["POOL"]];

        $cond = "team=".$row["TEAM"]." AND pool=".$row["POOL"];
        $query = "SELECT * FROM ".$name." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($name,$row,$cond);
        }else{
          $this->InsertRow($name, $row);
        }
        break;

      case "uo_game_pool":
        $row["GAME"] = $this->uo_game[$row["GAME"]];
        $row["POOL"] = $this->uo_pool[$row["POOL"]];

        $cond = "game=".$row["GAME"]." AND pool=".$row["POOL"];
        $query = "SELECT * FROM ".$name." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($name,$row,$cond);
        }else{
          $this->InsertRow($name, $row);
        }

        break;

      case "uo_moveteams":
        $row["TOPOOL"] = $this->uo_pool[$row["TOPOOL"]];
        $row["FROMPOOL"] = $this->uo_pool[$row["FROMPOOL"]];

        $cond = "topool=".$row["TOPOOL"]." AND fromplacing=".$row["FROMPLACING"];
        $query = "SELECT * FROM ".$name." WHERE ".$cond;
        $exist = DBQueryRowCount($query);

        if($exist){
          $this->SetRow($name,$row,$cond);
        }else{
          $this->InsertRow($name, $row);
        }
        break;
    }
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
      $query .= mysql_real_escape_string($fields[$i]) ."=".$values[$i].", ";
    }
    $query = rtrim($query,', ');
    $query .= " WHERE ";
    $query .= $cond;
    return DBQueryInsert($query);
  }
}
?>