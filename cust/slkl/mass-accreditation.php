<?php
if (!isset($include_prefix)) {
  $include_prefix = __DIR__ . '/../../';
}

include_once $include_prefix . 'lib/auth.guard.php';

$new_players = "";
$updated = false;

if (isset($_POST['upload'])) {

  if ($_FILES['uploadedfile']['type'] == "text/x-csv" || $_FILES['uploadedfile']['type'] == "text/csv") {
    if (is_uploaded_file($_FILES['uploadedfile']['tmp_name'])) {
      if (($handle = fopen($_FILES['uploadedfile']['tmp_name'], "r")) !== FALSE) {
        $new_players = slklUpdateLicensesFromCSV($handle, $season);
        fclose($handle);
        $updated = true;
      }
    }
  } else {
  }
} else if (isset($_POST['check'])) {

  if (empty($_POST['series'])) {
    echo "<p class='warning'>" . _("No divisions selected!") . "</p>";
  } else {
    $req_validity = empty($_POST['validity']) ? array() : $_POST['validity'];
    $req_type = empty($_POST['type']) ? array() : $_POST['type'];

    foreach ($_POST['series'] as $division) {
      $players = SeriesAllPlayers($division);
      $players_total = count($players);
      $newacc = 0;
      $oldacc = 0;


      //loop all palyers in division
      foreach ($players as $player) {
        if (!empty($player['accreditation_id'])) {
          $license = LicenseData($player['accreditation_id']);
          $playerInfo = PlayerInfo($player['player_id']);
          if ($playerInfo['accredited']) {
            $oldacc++;
            continue;
          }

          $isValidMembership = false;
          // check membership
          if (
            isset($_POST['isValidityYear']) && !empty($_POST['validityYear'])
            && $license['membership'] == $_POST['validityYear']
          ) {
            $isValidMembership = true;
          }

          $isValidLicense = false;
          // check license
          if (
            in_array($license['external_type'], $req_type) || isset($_POST['allTypes'])
          ) {
            $isValidLicense = true;
          }

          // accrediate
          if($isValidMembership && $isValidLicense) {
            AccreditPlayer($playerInfo['player_id'], "automatic accreditation");
            $newacc++;
          }

          echo "</p>";
        }
      }

      echo "<p>" . SeriesName($division) . ": " . $newacc . " " . _("new accreditations.");
      echo " " . _("Total") . " " . ($newacc + $oldacc) . "/" . $players_total . " " . _("players accredited.") . "</p>";
    }
  }
}

if ($view == "acc") {
  echo "<h3>" . _("Update ultiorganizer license database") . "</h3>";
  echo "<form enctype='multipart/form-data' method='post' action='$url'>\n";
  echo "<div><input type='hidden' name='MAX_FILE_SIZE' value='50000000' /></div>\n";
  echo "<table>";
  echo "<tr><td>" . _("Membership database (a file with .csv suffix)") . ":</td><td><input name='uploadedfile' type='file' />\n";
  echo "<input type='submit' name='upload' value='" . _("Upload") . "' /></td></tr>\n";
  echo "<tr><td colspan='2' align='center'>";
  echo "</table>\n";
  echo "</form>\n";
  if ($updated) {
    echo "<p>" . _("License file imported.") . "</p>";
  }
  if (!empty($new_players)) {
    echo "<p><b>" . _("New licensed players added:") . "</b></p>";
    echo $new_players;
  }
}

if ($view == "autoacc") {
  $seasonInfo = SeasonInfo($_GET['season']);
  echo "<p>" . _("Accredit players against license database with selected conditions.") . "</p>";
  echo "<form method='post' action='$url'>\n";
  //echo "<h4>"._("Series")."</h4>\n";

  echo "<table>";
  echo "<tr><td><b>" . _("Select divisions to check:") . "</b></td></tr>\n";
  $series = SeasonSeries($season);
  foreach ($series as $row) {
    echo "<tr><td>\n";
    echo "<input type='checkbox' name='series[]' value='" . utf8entities($row['series_id']) . "' /> ";
    echo U_($row['name']) . "</td></tr>\n";
  }
  echo "</table>\n";

  echo "<table>";
  echo "<tr><td><b>" . _("Select licenses validity required:") . "</b></td></tr>\n";
  $validity = ExternalLicenseValidityList();
  foreach ($validity as $row) {
    echo "<tr><td>\n";
    echo "<input type='checkbox' name='validity[]' value='" . utf8entities($row['external_validity']) . "' /> ";
    echo U_($row['external_validity']) . "</td></tr>\n";
  }
  $year = date('Y', strtotime($seasonInfo['starttime']));
  echo "<tr><td>\n";
  echo "<input type='checkbox' name='isValidityYear'/>";
  echo _("License for year:");
  echo "<input class='input' size='5' maxlength='4' name='validityYear' value='" . utf8entities($year) . "'/> ";
  echo "</td></tr>\n";
  echo "</table>\n";

  echo "<table>";
  echo "<tr><td><b>" . _("Select license type(s) accepted:") . "</b></td></tr>\n";
  $types = ExternalLicenseTypes();
  foreach ($types as $row) {
    echo "<tr><td>\n";
    echo "<input type='checkbox' name='type[]' value='" . utf8entities($row['external_type']) . "' /> ";
    echo U_($row['external_type']) . "</td></tr>\n";
  }
  echo "<tr><td>\n";
  echo "<input type='checkbox' name='allTypes'/>" . _("Accept any");
  echo "</td></tr>\n";
  echo "</table>\n";
  echo "<p>" . _("Accredit all players in selected divisions:") . " <input type='submit' name='check' value='" . _("Accredit") . "'/></p>";
  echo "</form>\n";
}

function slklUpdateLicensesFromCSV($handle, $season)
{
  $html = "";
  $utf8 = false;
  $length = 1000; //row length in file
  $delimiter = ';';
  //$enclosure = '';

  while (($data = fgetcsv($handle, $length, $delimiter)) !== FALSE) {

    $id = trim($utf8 ? $data[0] : convertToUtf8($data[0]));

    if (!is_numeric($id)) {
      continue;
    }

    $lastname = trim($utf8 ? $data[1] : convertToUtf8($data[1]));
    $firstname = trim($utf8 ? $data[2] : convertToUtf8($data[2]));
    $birthdate = trim($utf8 ? $data[3] : convertToUtf8($data[3]));
    $gender = trim($utf8 ? $data[4] : convertToUtf8($data[4]));

    //$license_string = trim($utf8 ? $data[6] : convertToUtf8($data[6]));
    $license_id = trim($utf8 ? $data[5] : convertToUtf8($data[5]));
    //$cond = trim($utf8 ? $data[7] : convertToUtf8($data[7]));
    $year = trim($utf8 ? $data[6] : convertToUtf8($data[6]));
    $email = trim($utf8 ? $data[7] : convertToUtf8($data[7]));

    //if($cond=="avoin"){
    //  continue;
    // }
    $dates = explode(".", $year);
    $year = isset($dates[2]) ? $dates[2] : "";

    if ($year == "") {
      continue;
    }

    if (strcasecmp($gender, "nainen") == 0) {
      $women = 1;
    } else {
      $women = 0;
    }

    $firstname = mb_strtolower($firstname, "UTF-8");
    $firstname[0] = mb_strtoupper($firstname[0]);
    $lastname = ucfirst(mb_strtolower($lastname, "UTF-8"));
    $lastname[0] = mb_strtoupper($lastname[0]);

    //in license dump, some players has given both names
    $pos = strpos($firstname, " ");
    if (!$pos) {
      $pos = strpos($firstname, "-");
    }
    if ($pos) {
      $shortername = substr($firstname, 0, $pos);
    } else {
      $shortername = $firstname;
    }

    //2023
    //3271	Aikuisten jäsenyys 2023-2024/Adult membership 2023-2024
    //3272	Nuorten jäsenyys 2023-2024/Junior membership 2023-2024
    //3274	Kertalisenssi aikuiset kesäkausi 2023 (vaaditaan SM-sarjaan/-turnauksiin)
    //3276	Kilpailulisenssi aikuiset kesäkausi 2023 (vaaditaan SM-sarjaan/-turnauksiin)
    //3278	Kilpailulisenssi aikuiset koko kausi 2022-2023  (vaaditaan SM-sarjaan ja divareihin)
    //3279	Kilpailulisenssi juniorit koko kausi 2023-2024
    //3282	Kilpailulisenssi aikuiset talvikausi 2023-2024 (vaaditaan SM-sarjaan ja divareihin)
    //3283	Kilpailulisenssi juniorit talvikausi 2023-2024
    //3280	Kertalisenssi aikuiset talvikausi 2023-2024

    //2024
    //3968	Aikuisten jäsenyys 2024-2025/Adult membership 2024-2025
    //3972	Nuorten jäsenyys 2024-2025/Junior membership
    //3970	Kilpailulisenssi aikuiset kesäkausi 2024
    //3971	Kilpailulisenssi aikuiset koko kausi 2024-2025
    //4303	Kilpailulisenssi aikuiset talvikausi 2024-2025

    //3974	Kilpailulisenssi juniorit kesäkausi 2024
    //3975	Kilpailulisenssi juniorit koko kausi 2024-2025
    //4304	Kilpailulisenssi juniorit talvikausi 2024-2025

    //4076	Kertalisenssi aikuiset kesä 2024
	  //4077	Kertalisenssi juniorit kesä 2024
  	//4078	Harrastelisenssi kesä 2024
    //4302	Kertalisenssi aikuiset talvi 2024
	  //4305	Kertalisenssi juniorit talvi 2024

    // moved
    //4676	Aikuisten jäsenyys 2024-2025/Adult membership 2024-2025. 
    //4678	Nuorten jäsenyys 2024-2025/Junior membership
    //4677	Kilpailulisenssi aikuiset talvikausi 2024-2025
    //4679	Kertalisenssi aikuiset talvikausi 2024-2025
    //4680	Kertalisenssi juniorit talvikausi 2024-2025
    //4681	Kilpailulisenssi juniorit talvikausi 2024-2025
    //4593	Siirretty beach SM lisenssi

    //2025
    //4653	Aikuisten jäsenyys 2025-2026/Adult membership 2025-2026
    //4664	Nuorten jäsenyys 2025-2026/Junior membership
    
    //4659	Kilpailulisenssi aikuiset kesäkausi 2025
    //4660	Kilpailulisenssi aikuiset koko kausi 2025-2026
    //4663	U17 sarjan kilpailulisenssi koko kausi 2025-2026
    //4662	U17 sarjan kilpailulisenssi kesäkausi 2025
    //4655	Kertalisenssi aikuiset kesä 2025
    //4654	Harrastelisenssi kesä 2025
    //4656	Kertalisenssi aikuiset talvikausi 2025-2026
    //4661	Kilpailulisenssi aikuiset talvikausi 2025-2026
	  //4665	U17 sarjan kilpailulisenssi talvikausi 2025-2026
    
    $valid_membership = array(3968, 3972,4676,4678,4653,4664);
    $valid_license = array(3970, 3971, 3974, 3975, 4076, 4077, 4078, 4303, 4304, 4302, 4305, 4677, 4679, 4680, 4681, 4593,4659,4660,4663,4662,4655,4654,4656,4661,4665);
    $valid_juniors = array(3974, 3975, 4077, 4304, 4305, 4680, 4681,4663,4662,4665);
    $ignore = array();


    if (in_array($license_id, $ignore)) {
      continue;
    }

    $membership = "";
    //if(stristr($license_string,"senmaksu")){
    if (in_array($license_id, $valid_membership)) {
      $membership = $year;
    }
    $license = "";
    $external_type = "";
    //if(stristr($license_string,"ultimaten kilpailulisenssi")){
    if (in_array($license_id, $valid_license)) {
      $license = $year;
      $external_type = $license_id;
    }
    $junior = 0;
    //if(stristr($license_string,"juniorit")||strstr($license_string,"junioirit")){
    if (in_array($license_id, $valid_juniors)) {
      $junior = 1;
    }

    if (!empty($birthdate)) {
      //$birthdate = substr($birthdate,0,4)."-".substr($birthdate,4,2)."-".substr($birthdate,6,2);
      $dates = explode(".", $birthdate);
      $birthdate = $dates[2] . "-" . $dates[1] . "-" . $dates[0];
      $birthdate .= " 00:00:00";
    } else {
      $birthdate = "1971-01-01 00:00:00";
    }

    //echo "<p>$id $firstname $lastname</p>";
    $exist = DBQueryRowCount("SELECT accreditation_id FROM uo_license WHERE external_id='" . DBEscapeString($id) . "'");
    if ($exist == 1) {
      $query = "UPDATE uo_license SET junior=$junior ";
      if (!empty($membership)) {
        $query .= ",membership='" . $membership . "'";
      }
      if (!empty($license)) {
        $query .= ",license='" . $license . "'";
      }
      if (!empty($gender)) {
        $query .= ",women=$women";
      }
      if (!empty($birthdate)) {
        $query .= ",birthdate='" . $birthdate . "'";
      }
      if (!empty($external_type)) {
        $query .= ",external_type='" . $external_type . "'";
      }

      $query .= sprintf(" WHERE external_id='%s'", DBEscapeString($id));
      DBQuery($query);
    } else {

      //echo "<p>$lastname $firstname ($shortername)</p>";
      $check1 = "UPPER(lastname) LIKE '" . DBEscapeString($lastname) . "'";
      $check2 = "UPPER(firstname) LIKE '" . DBEscapeString($firstname) . "'";
      $check3 = "UPPER(firstname) LIKE '" . DBEscapeString($shortername) . "'";
      $check4 = "birthdate='" . DBEscapeString($birthdate) . "' AND birthdate!='1971-01-01 00:00:00'";

      //$count1 = DBQueryRowCount("SELECT accreditation_id FROM uo_license WHERE ".$check1);
      $count1 = DBQueryRowCount("SELECT accreditation_id FROM uo_license WHERE " . $check1 . " AND " . $check2 . " AND external_id IS NULL");
      $count2 = DBQueryRowCount("SELECT accreditation_id FROM uo_license WHERE " . $check1 . " AND " . $check3 . " AND external_id IS NULL");
      $count3 = DBQueryRowCount("SELECT accreditation_id FROM uo_license WHERE " . $check1 . " AND " . $check4 . " AND external_id IS NULL");

      $query = "UPDATE uo_license SET junior=$junior ";
      //$query = "UPDATE uo_license SET external_id=accreditation_id ";
      //$query .= sprintf(",accreditation_id='%s' ", DBEscapeString($id));
      $query .= sprintf(",external_id='%s' ", DBEscapeString($id));
      if (!empty($membership)) {
        $query .= ",membership='" . $membership . "'";
      }
      if (!empty($license)) {
        $query .= ",license='" . $license . "'";
      }
      if (!empty($external_type)) {
        $query .= ",external_type='" . $external_type . "'";
      }
      $query .= ",birthdate='" . $birthdate . "'";
      $query .= ",women='" . $women . "'";
      if ($count1 == 1) {
        $query .= " WHERE $check1 AND $check2";
        DBQuery($query);
      } elseif ($count2 == 1) {
        $query .= " WHERE $check1 AND $check3";
        DBQuery($query);
      } elseif ($count3 == 1) {
        $query .= " WHERE $check1 AND $check4";
        DBQuery($query);
      }

      //echo "<p>$lastname $firstname ($shortername): $count1 $count2 $count3</p>";
      if ($count1 != 1 && $count2 != 1 && $count3 != 1) {
        //$birthdate = "1971-01-01 00:00:00";
        if (empty($membership)) {
          $membership = 0;
        }
        if (empty($license)) {
          $license = 0;
        }
        if (empty($external_type)) {
          $external_type = 0;
        }

        $query = sprintf(
          "INSERT INTO uo_license (lastname, firstname, birthdate, membership, license, junior, women, external_id, external_type,accreditation_id, ultimate)
    				  		VALUES ('%s','%s','%s','%s','%s','%s',%d,'%s','%s','%s',1)",
          DBEscapeString($lastname),
          DBEscapeString($firstname),
          DBEscapeString($birthdate),
          DBEscapeString($membership),
          DBEscapeString($license),
          DBEscapeString($junior),
          (int) $women,
          DBEscapeString($id),
          DBEscapeString($external_type),
          DBEscapeString($id),
          1
        );
        DBQuery($query);
        $html .= "<p>" . utf8entities($id) . " " . utf8entities($firstname) . " " . utf8entities($lastname) . "</p>";

        //check if player already have profile
        $players = SeasonAllPlayers($season);
        $found = false;
        foreach ($players as $player) {
          $playerinfo = PlayerInfo($player['player_id']);
          if (empty($playerinfo['accreditation_id'])) {
            if ($playerinfo['firstname'] == $firstname && $playerinfo['lastname'] == $lastname) {
              if (empty($player['profile_id'])) {
                CreatePlayerProfile($player['player_id']);
                $playerinfo = PlayerInfo($player['player_id']);
              }
              $query = "UPDATE uo_player SET accreditation_id='" . DBEscapeString($id) . "' ";
              $query .= "WHERE player_id=" . $player['player_id'];
              DBQuery($query);
              $query = "UPDATE uo_player_profile SET accreditation_id='" . DBEscapeString($id) . "' ";
              $query .= "WHERE profile_id=" . $playerinfo['profile_id'];
              DBQuery($query);
              $found = true;
            }
          }
        }
      }
    }

    $accreditation_id = DBQueryToValue("SELECT accreditation_id FROM uo_license WHERE external_id='" . DBEscapeString($id) . "'");
    $profile = DBQueryToRow("SELECT * FROM uo_player_profile WHERE accreditation_id='" . $accreditation_id . "'");

    if ($profile) {
      $query = "UPDATE uo_player_profile SET accreditation_id='" . $accreditation_id . "' ";
      $query .= ",birthdate='" . $birthdate . "'";

      if (empty($profile['gender'])) {
        if ($women) {
          $query .= ",gender='F'";
        } else {
          $query .= ",gender='M'";
        }
      }
      if (empty($profile['email'])) {
        $query .= ",email='" . $email . "'";
      }

      $query .= sprintf(" WHERE profile_id='%s'", $profile['profile_id']);
      DBQuery($query);
    } else {
      if ($women) {
        $gender = 'F';
      } else {
        $gender = 'M';
      }

      $query = sprintf(
        "INSERT INTO uo_player_profile (firstname,lastname,accreditation_id, gender, email, birthdate) VALUES
				('%s','%s','%s','%s','%s','%s')",
        DBEscapeString($firstname),
        DBEscapeString($lastname),
        DBEscapeString($id),
        DBEscapeString($gender),
        DBEscapeString($email),
        DBEscapeString($birthdate)
      );

      $profileId = DBQueryInsert($query);
    }
  }
  return $html;
}
