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
class EventDataXMLHandler
{
  var $eventId; //event id under processing
  var $uo_season = array(); //event id mapping array
  var $uo_series = array(); //series id mapping array
  var $uo_team = array(); //team id mapping array
  var $uo_scheduling_name = array(); //scheduling id mapping array
  var $uo_pool = array(); //pool id mapping array
  var $uo_player = array(); //player id mapping array
  var $uo_game = array(); //game id mapping array
  var $uo_reservation = array(); //reservation id mapping array
  var $followers = array(); // pools with unresolved followers
  var $mode; //import mode: 'add' or 'replace'

  /**
   * Default construction
   */
  function __construct()
  {
  }

  // FIXME include defense, gameevent, played (?)
  /**
   * Converts element data into xml-format.
   *
   * xml-structure
   * <uo_season>
   *  <uo_reservation></uo_reservation>
   *  <uo_movingtime></uo_movingtime>
   *  <uo_series>
   *    <uo_team>
   *      <uo_player></uo_player>
   *    </uo_team>
   *    <uo_scheduling_name></uo_scheduling_name>
   *    <uo_pool>
   *	    <uo_team_pool></uo_team_pool>
   *      <uo_game>
   *        <uo_goal></uo_goal>
   *        <uo_gameevent></uo_gameevent>
   *        <uo_played></uo_played>
   *      </uo_game>
   *    </uo_pool>
   *    <uo_game_pool></uo_game_pool>
   *    <uo_moveteams></uo_moveteams>
   *  </uo_series>
   *</uo_season>
   *
   * @param string $eventId Event to conver into xml-format.
   * @return string event data in xml
   */

  function EventToXML($eventId)
  {

    if (isSeasonAdmin($eventId)) {
      $ret = "";
      $ret .= "<?xml version='1.0' encoding='UTF-8'?>\n";
      //uo_season
      $seasons = DBQuery("SELECT * FROM uo_season WHERE season_id='" . DBEscapeString($eventId) . "'");
      $row = mysqli_fetch_assoc($seasons);
      $ret .= $this->RowToXML("uo_season", $row, false);

      //uo_reservation
      $reservations = DBQuery("SELECT * FROM uo_reservation WHERE season='" . DBEscapeString($eventId) . "'");
      while ($reservation = mysqli_fetch_assoc($reservations)) {
        $ret .= $this->RowToXML("uo_reservation", $reservation);
      }

      //uo_movingtime
      $times = DBQuery("SELECT * FROM uo_movingtime WHERE season='" . DBEscapeString($eventId) . "'");
      while ($time = mysqli_fetch_assoc($times)) {
        $ret .= $this->RowToXML("uo_movingtime", $time);
      }

      //uo_series
      $series = DBQuery("SELECT * FROM uo_series WHERE season='" . DBEscapeString($eventId) . "'");
      while ($ser = mysqli_fetch_assoc($series)) {
        $ret .= $this->RowToXML("uo_series", $ser, false);

        $seriesId = (int)$ser['series_id'];
        //uo_team
        $teams = DBQuery("SELECT * FROM uo_team WHERE series='$seriesId'");
        while ($team = mysqli_fetch_assoc($teams)) {
          $ret .= $this->RowToXML("uo_team", $team, false);
          //uo_player
          $players = DBQuery("SELECT * FROM uo_player WHERE team='" . DBEscapeString($team['team_id']) . "'");
          while ($player = mysqli_fetch_assoc($players)) {
            $ret .= $this->RowToXML("uo_player", $player);
          }
          $ret .= "</uo_team>\n";
        }

        //uo_scheduling_name, referenced by either games or moves 
        $schedulings = DBQuery("SELECT sched.* FROM uo_scheduling_name sched 
            LEFT JOIN uo_game game ON (sched.scheduling_id = game.scheduling_name_home OR sched.scheduling_id = game.scheduling_name_visitor)
            LEFT JOIN uo_pool pool ON (game.pool = pool.pool_id)
            LEFT JOIN uo_moveteams mv ON (sched.scheduling_id = mv.scheduling_id)
            LEFT JOIN uo_pool pool2 ON (mv.frompool = pool2.pool_id OR mv.topool = pool2.pool_id)
            WHERE pool2.series = $seriesId  OR pool.series = $seriesId 
            GROUP BY scheduling_id");
        while ($row = mysqli_fetch_assoc($schedulings)) {
          $ret .= $this->RowToXML("uo_scheduling_name", $row);
        }

        //uo_pool
        $pools = DBQuery("SELECT * FROM uo_pool WHERE series='$seriesId'");
        while ($row = mysqli_fetch_assoc($pools)) {
          $ret .= $this->RowToXML("uo_pool", $row, false);

          //uo_team_pool
          $teampools = DBQuery("SELECT * FROM uo_team_pool WHERE pool='" . DBEscapeString($row['pool_id']) . "'");
          while ($teampool = mysqli_fetch_assoc($teampools)) {
            $ret .= $this->RowToXML("uo_team_pool", $teampool);
          }

          //uo_game
          $games = DBQuery("SELECT * FROM uo_game WHERE pool='" . DBEscapeString($row['pool_id']) . "'");
          while ($row = mysqli_fetch_assoc($games)) {
            $ret .= $this->RowToXML("uo_game", $row, false);

            //uo_goal
            $goals = DBQuery("SELECT * FROM uo_goal WHERE game='" . DBEscapeString($row['game_id']) . "'");
            while ($goal = mysqli_fetch_assoc($goals)) {
              $ret .= $this->RowToXML("uo_goal", $goal);
            }
            //uo_gameevent
            $gameevents = DBQuery("SELECT * FROM uo_gameevent WHERE game='" . DBEscapeString($row['game_id']) . "'");
            while ($gameevent = mysqli_fetch_assoc($gameevents)) {
              $ret .= $this->RowToXML("uo_gameevent", $gameevent);
            }
            //uo_played
            $playedplayers = DBQuery("SELECT * FROM uo_played WHERE game='" . DBEscapeString($row['game_id']) . "'");
            while ($playedplayer = mysqli_fetch_assoc($playedplayers)) {
              $ret .= $this->RowToXML("uo_played", $playedplayer);
            }
            $ret .= "</uo_game>\n";
          }
          $ret .= "</uo_pool>\n";
        }

        //uo_moveteams
        $moveteams = DBQuery("SELECT m.* FROM uo_moveteams m
				LEFT JOIN uo_pool p ON(m.frompool=p.pool_id) 
				WHERE p.series='$seriesId'");
        while ($moveteam = mysqli_fetch_assoc($moveteams)) {
          $ret .= $this->RowToXML("uo_moveteams", $moveteam);
        }

        //uo_game_pool
        $gamepools = DBQuery("SELECT g.* FROM uo_game_pool g
				LEFT JOIN uo_pool p ON(g.pool=p.pool_id)
				WHERE p.series='$seriesId'");
        while ($gamepool = mysqli_fetch_assoc($gamepools)) {
          $ret .= $this->RowToXML("uo_game_pool", $gamepool);
        }
        $ret .= "</uo_series>\n";
      }

      $ret .= "</uo_season>\n";

      return $ret;
    } else {
      die('Insufficient rights to export data');
    }
  }

  /**
   * Converts database row to xml.
   * @param string $elementName - name of xml-element (table name)
   * @param array $row - element attributes (table row)
   * @param boolean $endtag - true if element closed
   *
   * @return string XML-data
   */
  function RowToXML($elementName, $row, $endtag = true)
  {

    $columns = array_keys($row);
    $values = array_values($row);
    $total = count($row);
    $ret = "<" . $elementName . " ";

    for ($i = 0; $i < $total; $i++) {
      if ($values[$i] == '') {
        $ret .=  $columns[$i] . "='NULL' ";
      } else {
        $ret .=  $columns[$i] . "='" . htmlspecialchars($values[$i], ENT_QUOTES, "UTF-8") . "' ";
      }
    }

    if ($endtag) {
      $ret .= "/>\n";
    } else {
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
  function XMLToEvent($filename, $eventId = "", $mode = "new")
  {
    $this->mode = $mode;
    $this->eventId = $eventId;

    if ((empty($this->eventId) && isSuperAdmin()) || isSeasonAdmin($eventId)) {
      //create parser and set callback functions
      $xmlparser = xml_parser_create();
      xml_set_element_handler($xmlparser, array($this, "start_tag"), array($this, "end_tag"));

      if (!($fp = fopen($filename, "r"))) {
        die("cannot open " . $filename);
      }

      //remove extra spaces
      while ($data = fread($fp, 4096)) {
        $data = eregi_replace(">" . "[[:space:]]+" . "< ", ">< ", $data);
        if (!xml_parse($xmlparser, $data, feof($fp))) {
          $reason = xml_error_string(xml_get_error_code($xmlparser));
          $reason .= xml_get_current_line_number($xmlparser);
          die($reason);
        }
      }

      foreach ($this->followers as $pool => $follow) {
        $query = "UPDATE uo_pool SET follower='" . ((int) $this->uo_pool[$follow]) . "' WHERE pool_id='$pool'";
        DBQuery($query);
      }

      xml_parser_free($xmlparser);
    } else {
      die('Insufficient rights to import data');
    }
  }

  /**
   * Callback function for element start.
   * @param xmlparser $parser a reference to the XML parser calling the handler.
   * @param string $name a element name
   * @param array $attribs element's attributes
   */
  function start_tag($parser, $name, $attribs)
  {
    if (is_array($attribs)) {
      $row = array();
      while (list($key, $val) = each($attribs)) {
        if ($val == "NULL") {
          $row[$key] = "NULL";
        } else {
          $row[$key] = $val;
        }
      }
      switch ($this->mode) {
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
  function end_tag($parser, $name)
  {
  }

  /**
   * Does id mappings before inserts data into database as new data.
   * @param string $name Name of the table to insert
   * @param array $row Data to insert: key=>field, value=>data
   *
   * @see EventDataXMLHandler::InsertRow()
   */
  function InsertToDatabase($name, $row)
  {

    switch ($name) {
      case "uo_season":

        $seasonId = $row["SEASON_ID"];
        $newId = $seasonId;
        $newName = $row["NAME"];

        $max = 1;
        while (SeasonExists($newId) || SeasonNameExists($newName)) {
          $modifier = rand(1, ++$max);
          $newId = substr($seasonId, 0, 7) . "_$modifier";
          $newName = $row["NAME"] . " ($modifier)";
        }
        $row["NAME"] = $newName;
        $this->uo_season[$row["SEASON_ID"]] = $newId;
        unset($row["SEASON_ID"]);

        $values = "'" . implode("','", array_values($row)) . "'";
        $fields = implode(",", array_keys($row));

        $query = "INSERT INTO " . DBEscapeString($name) . " (";
        $query .= "SEASON_ID,";
        $query .= DBEscapeString($fields);
        $query .= ") VALUES (";
        $query .= "'" . DBEscapeString($newId) . "',";
        $query .= $values;
        $query .= ")";
        DBQueryInsert($query);

        AddEditSeason($_SESSION['uid'], $newId);
        AddUserRole($_SESSION['uid'], 'seasonadmin:' . $newId);
        break;

      case "uo_series":
        $key = $row["SERIES_ID"];
        unset($row["SERIES_ID"]);
        $row["SEASON"] = $this->uo_season[$row["SEASON"]];

        $newId = $this->InsertRow($name, $row);
        $this->uo_series[$key] = $newId;
        break;

      case "uo_scheduling_name":
        $key = $row["SCHEDULING_ID"];
        unset($row["SCHEDULING_ID"]);

        $newId = $this->InsertRow($name, $row);
        $this->uo_scheduling_name[$key] = $newId;
        break;

      case "uo_team":
        $key = $row["TEAM_ID"];
        unset($row["TEAM_ID"]);
        $row["SERIES"] = $this->uo_series[$row["SERIES"]];

        $newId = $this->InsertRow($name, $row);
        $this->uo_team[$key] = $newId;
        break;

      case "uo_player":
        $key = $row["PLAYER_ID"];
        unset($row["PLAYER_ID"]);
        $row["TEAM"] = $this->uo_team[$row["TEAM"]];

        $newId = $this->InsertRow($name, $row);
        $this->uo_player[$key] = $newId;
        break;

      case "uo_pool":
        $key = $row["POOL_ID"];
        unset($row["POOL_ID"]);
        $row["SERIES"] = $this->uo_series[$row["SERIES"]];

        $newId = $this->InsertRow($name, $row);
        $this->uo_pool[$key] = $newId;

        if (!empty($row["FOLLOWER"]) && $row["FOLLOWER"] != "NULL") {
          $this->followers[$newId] = (int)$row["FOLLOWER"];
        }

        break;

      case "uo_reservation":
        $key = $row["ID"];
        unset($row["ID"]);
        $row["SEASON"] = $this->uo_season[$row["SEASON"]];

        $newId = $this->InsertRow($name, $row);
        $this->uo_reservation[$key] = $newId;
        break;

      case "uo_movingtime":
        $row["SEASON"] = $this->uo_season[$row["SEASON"]];

        $newId = $this->InsertRow($name, $row);
        break;

      case "uo_game":
        $key = $row["GAME_ID"];
        unset($row["GAME_ID"]);

        if (!empty($row["HOMETEAM"]) && $row["HOMETEAM"] != "NULL" && $row["HOMETEAM"] > 0) {
          $row["HOMETEAM"] = $this->uo_team[$row["HOMETEAM"]];
        }
        if (!empty($row["VISITORTEAM"]) && $row["VISITORTEAM"] != "NULL" && $row["VISITORTEAM"] > 0) {
          $row["VISITORTEAM"] = $this->uo_team[$row["VISITORTEAM"]];
        }
        if (!empty($row["RESPTEAM"]) && $row["RESPTEAM"] != "NULL" && $row["RESPTEAM"] > 0) {
          $oldresp = $row["RESPTEAM"];
          if ($row["HOMETEAM"] == "NULL") {
            $row["RESPTEAM"] = $this->uo_scheduling_name[$row["RESPTEAM"]];
          } else {
            $row["RESPTEAM"] = $this->uo_team[$row["RESPTEAM"]];
          }
          if (is_null($row["RESPTEAM"])) {
            $row["RESPTEAM"] = "NULL";
          }
        }
        if (!empty($row["RESERVATION"]) && isset($this->uo_reservation[$row["RESERVATION"]])) {
          $row["RESERVATION"] = $this->uo_reservation[$row["RESERVATION"]];
        }
        if (!empty($row["POOL"])) {
          $row["POOL"] = $this->uo_pool[$row["POOL"]];
        }
        if (!empty($row["SCHEDULING_NAME_HOME"]) && isset($this->uo_scheduling_name[$row["SCHEDULING_NAME_HOME"]])) {
          $row["SCHEDULING_NAME_HOME"] = $this->uo_scheduling_name[$row["SCHEDULING_NAME_HOME"]];
        }
        if (!empty($row["SCHEDULING_NAME_VISITOR"] && isset($this->uo_scheduling_name[$row["SCHEDULING_NAME_VISITOR"]]))) {
          $row["SCHEDULING_NAME_VISITOR"] = $this->uo_scheduling_name[$row["SCHEDULING_NAME_VISITOR"]];
        }

        $newId = $this->InsertRow($name, $row);

        $this->uo_game[$key] = $newId;
        break;

      case "uo_goal":
        $row["GAME"] = $this->uo_game[$row["GAME"]];
        if ($row["ASSIST"] >= 0) {
          $row["ASSIST"] = $this->uo_player[$row["ASSIST"]];
        }
        if ($row["SCORER"] >= 0) {
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
        $row["SCHEDULING_ID"] = $this->uo_scheduling_name[$row["SCHEDULING_ID"]];
        $this->InsertRow($name, $row);
        break;
    }
  }

  /**
   * Inserts data into database as new data.
   * @param string $name Name of the table to insert
   * @param array $row Data to insert: key=>field, value=>data
   */
  function InsertRow($name, $row)
  {
    $columns = GetTableColumns($name);
    $fields = implode(",", array_keys($row));


    $values = "";
    foreach ($row as $key => $value) {
      if ($columns[strtolower($key)] === 'int') {
        if ($value === "NULL") {
          $values .= "NULL,";
        } elseif (is_numeric($value))
          $values .= "'" . DBEscapeString($value) . "',";
        else
          die("Invalid column value '$value' for column $key of table $name. (" . json_encode($row) . ").");
      } else {
        $values .= "'" . DBEscapeString($value) . "',";
      }
    }

    $values = substr($values, 0, -1);

    $query = "INSERT INTO " . DBEscapeString($name) . " (";
    $query .= DBEscapeString($fields);
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
  function ReplaceInDatabase($name, $row)
  {

    switch ($name) {
      case "uo_season":
        $cond = "season_id='" . $row["SEASON_ID"] . "'";
        $query = "SELECT season_id FROM uo_season WHERE " . $cond;
        $exist = DBQueryRowCount($query);
        if ($exist) {
          if ("'$this->eventId'" == $row["SEASON_ID"]) {
            $this->SetRow($name, $row, $cond);
            $this->uo_season[$row["SEASON_ID"]] = $row["SEASON_ID"];
          } else {
            die(_("Target event is not the same as in a file."));
          }
        } else {
          die(_("Event to replace doesn't exist"));
        }
        break;

      case "uo_series":
        $key = $row["SERIES_ID"];
        unset($row["SERIES_ID"]);

        $cond = "series_id='" . $key . "'";
        $query = "SELECT * FROM " . $name . " WHERE " . $cond;
        $exist = DBQueryRowCount($query);

        if ($exist) {
          $this->SetRow($name, $row, $cond);
          $this->uo_series[$key] = $key;
        } else {
          $row["SEASON"] = $this->uo_season[$row["SEASON"]];
          $newId = $this->InsertRow($name, $row);
          $this->uo_series[$key] = $newId;
        }
        break;

      case "uo_scheduling_name":
        $key = $row["SCHEDULING_ID"];
        unset($row["SCHEDULING_ID"]);

        $cond = "scheduling_id='$key'";
        $query = "SELECT * FROM " . $name . " WHERE " . $cond;
        $exist = DBQueryRowCount($query);

        if ($exist) {
          $this->SetRow($name, $row, $cond);
        } else {
          $newId = $this->InsertRow($name, $row);
          $this->uo_scheduling_name[$key] = $newId;
        }
        break;


      case "uo_team":
        $key = $row["TEAM_ID"];
        unset($row["TEAM_ID"]);

        $cond = "team_id='" . $key . "'";
        $query = "SELECT * FROM " . $name . " WHERE " . $cond;
        $exist = DBQueryRowCount($query);

        if ($exist) {
          $this->SetRow($name, $row, $cond);
          $this->uo_team[$key] = $key;
        } else {
          $row["SERIES"] = $this->uo_series[$row["SERIES"]];
          $newId = $this->InsertRow($name, $row);
          $this->uo_team[$key] = $newId;
        }
        break;

      case "uo_player":
        $key = $row["PLAYER_ID"];
        unset($row["PLAYER_ID"]);
        $row["TEAM"] = $this->uo_team[$row["TEAM"]];

        $cond = "player_id='" . $key . "'";
        $query = "SELECT * FROM " . $name . " WHERE " . $cond;
        $exist = DBQueryRowCount($query);

        if ($exist) {
          $this->SetRow($name, $row, $cond);
          $this->uo_player[$key] = $key;
        } else {
          $newId = $this->InsertRow($name, $row);
          $this->uo_player[$key] = $newId;
        }
        break;

      case "uo_pool":
        $key = $row["POOL_ID"];
        unset($row["POOL_ID"]);
        $row["SERIES"] = $this->uo_series[$row["SERIES"]];

        $cond = "pool_id='" . $key . "'";
        $query = "SELECT * FROM " . $name . " WHERE " . $cond;
        $exist = DBQueryRowCount($query);

        if ($exist) {
          $this->SetRow($name, $row, $cond);
          $this->uo_pool[$key] = $key;
        } else {
          $newId = $this->InsertRow($name, $row);
          $this->uo_pool[$key] = $newId;
        }
        break;

      case "uo_reservation":
        $key = $row["ID"];
        unset($row["ID"]);
        $row["SEASON"] = $this->uo_season[$row["SEASON"]];

        $cond = "id='" . $key . "'";
        $query = "SELECT * FROM " . $name . " WHERE " . $cond;
        $exist = DBQueryRowCount($query);

        if ($exist) {
          $this->SetRow($name, $row, $cond);
          $this->uo_reservation[$key] = $key;
        } else {
          $newId = $this->InsertRow($name, $row);
          $this->uo_reservation[$key] = $newId;
        }
        break;

      case "uo_movingtime":
        $row["SEASON"] = $this->uo_season[$row["SEASON"]];

        $season = $row["SEASON"];
        $from = $row["FROMLOCATION"];
        $fromfield = $row["FROMFIELD"];
        $to = $row["TOLOCATION"];
        $tofield = $row["TOFIELD"];
        $cond = "season='$season' AND fromlocation='$from' AND fromfield='$fromfield' AND tolocation='$to' AND tofield='$tofield'";
        $query = "SELECT * FROM " . $name . " WHERE " . $cond;
        $exist = DBQueryRowCount($query);

        if ($exist) {
          $this->SetRow($name, $row, $cond);
        } else {
          $newId = $this->InsertRow($name, $row);
        }
        break;

      case "uo_game":
        $key = $row["GAME_ID"];
        unset($row["GAME_ID"]);

        if (!empty($row["HOMETEAM"]) && $row["HOMETEAM"] != "NULL" && $row["HOMETEAM"] > 0) {
          $row["HOMETEAM"] = $this->uo_team[$row["HOMETEAM"]];
        }
        if (!empty($row["VISITORTEAM"]) && $row["VISITORTEAM"] != "NULL" && $row["VISITORTEAM"] > 0) {
          $row["VISITORTEAM"] = $this->uo_team[$row["VISITORTEAM"]];
        }
        if (!empty($row["RESPTEAM"]) && $row["RESPTEAM"] != "NULL" && $row["RESPTEAM"] > 0) {
          $row["RESPTEAM"] = $this->uo_team[$row["RESPTEAM"]];
        }
        if (!empty($row["RESERVATION"]) && isset($this->uo_reservation[$row["RESERVATION"]])) {
          $row["RESERVATION"] = $this->uo_reservation[$row["RESERVATION"]];
        }
        if (!empty($row["POOL"])) {
          $row["POOL"] = $this->uo_pool[$row["POOL"]];
        }
        if (!empty($row["SCHEDULING_NAME_HOME"]) && isset($this->uo_scheduling_name[$row["SCHEDULING_NAME_HOME"]])) {
          $row["SCHEDULING_NAME_HOME"] = $this->uo_scheduling_name[$row["SCHEDULING_NAME_HOME"]];
        }
        if (!empty($row["SCHEDULING_NAME_VISITOR"] && isset($this->uo_scheduling_name[$row["SCHEDULING_NAME_VISITOR"]]))) {
          $row["SCHEDULING_NAME_VISITOR"] = $this->uo_scheduling_name[$row["SCHEDULING_NAME_VISITOR"]];
        }

        $newId = $this->InsertRow($name, $row);

        $this->uo_game[$key] = $newId;

        $cond = "game_id='" . $key . "'";
        $query = "SELECT * FROM " . $name . " WHERE " . $cond;
        $exist = DBQueryRowCount($query);

        if ($exist) {
          $this->SetRow($name, $row, $cond);
          $this->uo_game[$key] = $key;
        } else {
          $newId = $this->InsertRow($name, $row);
          $this->uo_game[$key] = $newId;
        }
        break;

      case "uo_goal":
        $row["GAME"] = $this->uo_game[$row["GAME"]];
        if ($row["ASSIST"] >= 0) {
          $row["ASSIST"] = $this->uo_player[$row["ASSIST"]];
        }
        if ($row["SCORER"] >= 0) {
          $row["SCORER"] = $this->uo_player[$row["SCORER"]];
        }

        $cond = "game='" . $row["GAME"] . "' AND num='" . $row["NUM"] . "'";
        $query = "SELECT * FROM " . $name . " WHERE " . $cond;
        $exist = DBQueryRowCount($query);

        if ($exist) {
          $this->SetRow($name, $row, $cond);
        } else {
          $this->InsertRow($name, $row);
        }
        break;

      case "uo_gameevent":
        $row["GAME"] = $this->uo_game[$row["GAME"]];

        $cond = "game='" . $row["GAME"] . "' AND num='" . $row["NUM"] . "'";
        $query = "SELECT * FROM " . $name . " WHERE " . $cond;
        $exist = DBQueryRowCount($query);

        if ($exist) {
          $this->SetRow($name, $row, $cond);
        } else {
          $this->InsertRow($name, $row);
        }

        break;

      case "uo_played":
        $row["GAME"] = $this->uo_game[$row["GAME"]];
        $row["PLAYER"] = $this->uo_player[$row["PLAYER"]];

        $cond = "game='" . $row["GAME"] . "' AND player='" . $row["PLAYER"] . "'";
        $query = "SELECT * FROM " . $name . " WHERE " . $cond;
        $exist = DBQueryRowCount($query);

        if ($exist) {
          $this->SetRow($name, $row, $cond);
        } else {
          $this->InsertRow($name, $row);
        }
        break;

      case "uo_team_pool":
        $row["TEAM"] = $this->uo_team[$row["TEAM"]];
        $row["POOL"] = $this->uo_pool[$row["POOL"]];

        $cond = "team='" . $row["TEAM"] . "' AND pool='" . $row["POOL"] . "'";
        $query = "SELECT * FROM " . $name . " WHERE " . $cond;
        $exist = DBQueryRowCount($query);

        if ($exist) {
          $this->SetRow($name, $row, $cond);
        } else {
          $this->InsertRow($name, $row);
        }
        break;

      case "uo_game_pool":
        $row["GAME"] = $this->uo_game[$row["GAME"]];
        $row["POOL"] = $this->uo_pool[$row["POOL"]];

        $cond = "game='" . $row["GAME"] . "' AND pool='" . $row["POOL"] . "'";
        $query = "SELECT * FROM " . $name . " WHERE " . $cond;
        $exist = DBQueryRowCount($query);

        if ($exist) {
          $this->SetRow($name, $row, $cond);
        } else {
          $this->InsertRow($name, $row);
        }

        break;

      case "uo_moveteams":
        $row["TOPOOL"] = $this->uo_pool[$row["TOPOOL"]];
        $row["FROMPOOL"] = $this->uo_pool[$row["FROMPOOL"]];

        $cond = "topool='" . $row["TOPOOL"] . "' AND fromplacing='" . $row["FROMPLACING"] . "'";
        $query = "SELECT * FROM " . $name . " WHERE " . $cond;
        $exist = DBQueryRowCount($query);

        if ($exist) {
          $this->SetRow($name, $row, $cond);
        } else {
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
  function SetRow($name, $row, $cond)
  {

    $values = array_values($row);
    $fields = array_keys($row);

    $query = "UPDATE " . DBEscapeString($name) . " SET ";

    for ($i = 0; $i < count($fields); $i++) {
      $query .= DBEscapeString($fields[$i]) . "='" . DBEscapeString($values[$i]) . "', ";
    }
    $query = rtrim($query, ', ');
    $query .= " WHERE ";
    $query .= $cond;
    return DBQueryInsert($query);
  }
}
