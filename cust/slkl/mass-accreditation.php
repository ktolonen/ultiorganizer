<?php
$new_players="";
$updated = false;

if (isset($_POST['upload'])) {

  if($_FILES['uploadedfile']['type'] == "application/octet-stream" || $_FILES['uploadedfile']['type'] == "application/msaccess"){
    slklUpdateLicensesFromAccess();
    $updated = true;
  }elseif($_FILES['uploadedfile']['type']=="text/x-csv" || $_FILES['uploadedfile']['type']=="text/csv"){
    if(is_uploaded_file($_FILES['uploadedfile']['tmp_name'])) {
      if (($handle = fopen($_FILES['uploadedfile']['tmp_name'], "r")) !== FALSE) {
        $new_players=slklUpdateLicensesFromCSV($handle,$season);
        fclose($handle);
        $updated = true;
      }
    }
  }else{

  }


} else if (isset($_POST['check'])) {

  if(empty($_POST['series'])){
    echo "<p class='warning'>"._("No divisions selected!")."</p>";
  }else{
    $req_validity=empty($_POST['validity'])?array():$_POST['validity'];
    $req_type=empty($_POST['type'])?array():$_POST['type'];

    foreach($_POST['series'] as $division){
      $players = SeriesAllPlayers($division);
      $players_total = count($players);
      $newacc = 0;
      $oldacc = 0;


      //loop all palyers in division
      foreach($players as $player){
        if(!empty($player['accreditation_id'])){
          $license = LicenseData($player['accreditation_id']);
          $playerInfo = PlayerInfo($player['player_id']);
          //echo "<p>". $playerInfo['lastname']." ".$playerInfo['firstname']." :".$license['membership']."/".$license['license']." ".$playerInfo['accredited'];
          if($playerInfo['accredited']){
            $oldacc++;
          }

          //new type of accreditation
          if( !$playerInfo['accredited'] && in_array($license['external_validity'], $req_validity)
          &&  (in_array($license['external_type'], $req_type) || isset($_POST['allTypes']))){
            AccreditPlayer($playerInfo['player_id'], "automatic accreditation");
            $newacc++;
          }

          //old year based accreditation
          if(!$playerInfo['accredited'] && isset($_POST['isValidityYear']) && !empty($_POST['validityYear'])
          && $license['membership']==$_POST['validityYear'] && $license['license']==$_POST['validityYear']){
            AccreditPlayer($playerInfo['player_id'], "automatic accreditation");
            $newacc++;
            //echo "accredited";
          }
          //echo "</p>";
        }
      }

      echo "<p>".SeriesName($division).": ".$newacc ." ". _("new accreditations.");
      echo " "._("Total")." ".($newacc+$oldacc)."/".$players_total." ". _("players accredited.")."</p>";
    }
  }
}

if($view=="acc"){
  echo "<h3>"._("Update ultiorganizer license database")."</h3>";
  echo "<form enctype='multipart/form-data' method='post' action='$url'>\n";
  echo "<div><input type='hidden' name='MAX_FILE_SIZE' value='50000000' /></div>\n";
  echo "<table>";
  echo "<tr><td>"._("Membership database (a file with .mdb/.csv suffix)").":</td><td><input name='uploadedfile' type='file' />\n";
  echo "<input type='submit' name='upload' value='"._("Upload")."' /></td></tr>\n";
  echo "<tr><td colspan='2' align='center'>";
  echo "</table>\n";
  echo "</form>\n";
  if($updated){
    echo "<p>". _("License file imported.")."</p>";
  }
  if(!empty($new_players)){
    echo "<p><b>". _("New licensed players added:")."</b></p>";
    echo $new_players;
  }
}

if($view=="autoacc"){
  $seasonInfo = SeasonInfo($_GET['season']);
  echo "<p>"._("Accredit players against license database with selected conditions.")."</p>";
  echo "<form method='post' action='$url'>\n";
  //echo "<h4>"._("Series")."</h4>\n";

  echo "<table>";
  echo "<tr><td><b>"._("Select divisions to check:")."</b></td></tr>\n";
  $series = SeasonSeries($season);
  foreach($series as $row) {
    echo "<tr><td>\n";
    echo "<input type='checkbox' name='series[]' value='".utf8entities($row['series_id'])."' /> ";
    echo U_($row['name'])."</td></tr>\n";
  }
  echo "</table>\n";

  echo "<table>";
  echo "<tr><td><b>"._("Select licenses validity required:")."</b></td></tr>\n";
  $validity = ExternalLicenseValidityList();
  foreach($validity as $row) {
    echo "<tr><td>\n";
    echo "<input type='checkbox' name='validity[]' value='".utf8entities($row['external_validity'])."' /> ";
    echo U_($row['external_validity'])."</td></tr>\n";
  }
  $year = date('Y', strtotime($seasonInfo['starttime']));
  echo "<tr><td>\n";
  echo "<input type='checkbox' name='isValidityYear'/>";
  echo _("License for year:");
  echo "<input class='input' size='5' maxlength='4' name='validityYear' value='".utf8entities($year)."'/> ";
  echo "</td></tr>\n";
  echo "</table>\n";

  echo "<table>";
  echo "<tr><td><b>"._("Select license type(s) accepted:")."</b></td></tr>\n";
  $types = ExternalLicenseTypes();
  foreach($types as $row) {
    echo "<tr><td>\n";
    echo "<input type='checkbox' name='type[]' value='".utf8entities($row['external_type'])."' /> ";
    echo U_($row['external_type'])."</td></tr>\n";
  }
  echo "<tr><td>\n";
  echo "<input type='checkbox' name='allTypes'/>"._("Accept any");
  echo "</td></tr>\n";
  echo "</table>\n";
  echo "<p>"._("Accredit all players in selected divisions:")." <input type='submit' name='check' value='"._("Accredit")."'/></p>";
  echo "</form>\n";

}

function slklUpdateLicensesFromAccess(){

  $errors=0;
  $message="";
  $currentdir=getcwd();
  $target_path = $currentdir . "/../db/";

  $target_path = realpath($target_path) . "\\pelikone_members.mdb";

  if (!move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $target_path)) {
    die("<p>There was an error uploading the file, please try again!</p>");
  }
  //mysql_query("insert into uo_license ( lastname, firstname, membership, birthdate, accreditation_id, women, junior, license) VALUES ('Aalto', 'Anne', 1997, '', '',1473, '', 0, 1, 0, 0)");
  $result = mysql_query("set autocommit=0");
  if (!$result) {
    die("Can't set autocommit to 0: " . mysql_error() ."<br>\n");
  }
  $result = mysql_query("BEGIN");
  if (!$result) {
    die("Can't BEGIN: " . mysql_error() ."<br>\n");
  }
  $connstr = "DRIVER={Microsoft Access Driver (*.mdb)}; DBQ=$target_path";
  //~ echo "Connstring: $connstr<br>\n";
  $connAccessODBC = odbc_connect($connstr, "", "");
   
  if (!$connAccessODBC) {
    die("<p>Couldn't connect to access. " . odbc_error() . "</p>");
  }
  $truncresult = mysql_query("truncate table uo_license");
  if (!$truncresult) {
    $errors++;
    $message .= 'Invalid query: ' . mysql_error() . "\n";
    $message .= "Whole query: truncate table uo_license\n";
  }
   
  $result = odbc_exec($connAccessODBC, "select
    		sukunimi, 
    		etunimi, 
    		".utf8_decode("jäsenmaksu").",	
    		syntaika, 
    		".utf8_decode("jäsennumero").", 
    		joukkue, email, ultimate, nainen, junnu, uusi, ultimate_lisenssi from jasenet");
   
  $i=0;
  while($row = odbc_fetch_row($result)) {
    $i++;
    $fSukunimi = utf8_encode(odbc_result($result, "sukunimi"));
    $fEtunimi = utf8_encode(odbc_result($result, "etunimi"));
    $fJasenmaksu = utf8_encode(odbc_result($result, utf8_decode("jäsenmaksu")));
    $fSyntaika = utf8_encode(odbc_result($result, "syntaika"));
    if(empty($fSyntaika)) {
      $fSyntaika = "1970-01-01 00:00:00";
    }
    $fJasennumero = utf8_encode(odbc_result($result, utf8_decode("jäsennumero")));
    $fJoukkue = utf8_encode(odbc_result($result, "joukkue"));
    $fEmail = utf8_encode(odbc_result($result, "email"));
    $fUltimate = utf8_encode(odbc_result($result, "ultimate"));
    $fNainen = utf8_encode(odbc_result($result, "nainen"));
    $fJunnu = utf8_encode(odbc_result($result, "junnu"));
    $fUusi = utf8_encode(odbc_result($result, "uusi"));
    $fUltimateLisenssi = utf8_encode(odbc_result($result, "ultimate_lisenssi"));
     
    $query = sprintf("
    		insert into uo_license (
    			lastname,
    			firstname,
    			membership,
    			birthdate,
    			accreditation_id,
    			ultimate,
    			women,
    			junior,
    			license)
    		VALUES ('%s', '%s', %d, '%s', '%s', %d, %d, %d, %d)",
    mysql_real_escape_string($fSukunimi),
    mysql_real_escape_string($fEtunimi),
    $fJasenmaksu,
    mysql_real_escape_string($fSyntaika),
    $fJasennumero,
    $fUltimate,
    $fNainen,
    $fJunnu,
    $fUltimateLisenssi);
    //~ echo "<p>$i: $query</p>\n";
    $insResult = mysql_query($query);
    if (!$insResult) {
      $errors++;
      $message .= 'Invalid query: ' . mysql_error() . "\n";
      $message .= 'Whole query: ' . $query . "<br>\n";
    }
    //checkAccreditation($fJasennumero, $fNainen, $fJunnu, $fUltimateLisenssi, "member_upload");

  }
  odbc_close($connAccessODBC);
  if ($errors == 0) {
    echo "<p>"._("License database update ok").".</p>";
    mysql_query("COMMIT");
  } else {
    echo "<p>"._("License database update failed")."</p>";
    mysql_query("ROLLBACK");
    echo $message;
  }
  mysql_query("set autocommit=1");
}


function slklUpdateLicensesFromCSV($handle, $season){
  $html = "";
  $utf8 = false;
  $length = 1000;//row length in file
  $delimiter = ';';
  //$enclosure = '';

  while (($data = fgetcsv($handle, $length, $delimiter)) !== FALSE) {
     
    $id = trim($utf8 ? $data[0] : utf8_encode($data[0]));

    if(!is_numeric($id)){
      continue;
    }

    $lastname = trim($utf8 ? $data[1] : utf8_encode($data[1]));
    $firstname = trim($utf8 ? $data[2] : utf8_encode($data[2]));
    $birthdate = trim($utf8 ? $data[3] : utf8_encode($data[3]));
    $gender = trim($utf8 ? $data[4] : utf8_encode($data[4]));
    $email = trim($utf8 ? $data[5] : utf8_encode($data[5]));
    //$license_string = trim($utf8 ? $data[6] : utf8_encode($data[6]));
    $license_id = trim($utf8 ? $data[6] : utf8_encode($data[6]));
    //$cond = trim($utf8 ? $data[7] : utf8_encode($data[7]));
    $year = trim($utf8 ? $data[7] : utf8_encode($data[7]));
    
    //if($cond=="avoin"){
    //  continue;
   // }
        
    $year = substr($year,0,4);

    if($year==""){
      continue;
    }
    
    if($gender=="nainen"){
      $women=1;
    }else{
      $women=0;
    }
    
    $firstname = mb_strtolower($firstname,"UTF-8");
    $firstname[0] = mb_strtoupper($firstname[0]); 
    $lastname = ucfirst(mb_strtolower($lastname,"UTF-8"));
    $lastname[0] = mb_strtoupper($lastname[0]); 
        
    //in license dump, some players has given both names
    $pos = strpos($firstname," ");
    if(!$pos){
      $pos = strpos($firstname,"-");
    }
    if($pos){
      $shortername = substr($firstname,0,$pos);
    }else{
      $shortername = $firstname;
    }
    
    //653 Jäsenmaksu ja ultimaten kilpailulisenssi, aikuiset
    //654 Jäsenmaksu ja ultimaten kilpailulisenssi, juniorit
    //655 Jäsenmaksu, ultimaten kilpailulisenssi ja vakuutus, aikuiset
    //656 Jäsenmaksu, ultimaten kilpailulisenssi ja vakuutus, juniorit
    //4001 Jäsenmaksu ja ultimaten kilpailulisenssi, aikuiset
    //4002 Jäsenmaksu ja ultimaten kilpailulisenssi, juniorit
    //4003 Jäsenmaksu ja vakuutus, aikuiset
    //4004 Jäsenmaksu ja vakuutus, junioirit alle 16 vuotta
    //4008 Jäsenmaksu, ultimaten kilpailulisenssi ja vakuutus, aikuiset
    //4009 Jäsenmaksu, ultimaten kilpailulisenssi ja vakuutus, junioirit yli 16 vuotta
    //4010 Jäsenmaksu, ultimaten kilpailulisenssi ja vakuutus, junioirit alle 16 vuotta
    //4011 Vakuutusmaksu, aikuiset (yli 16 vuotta)
    //4012
    //4013 Jäsenmaksu, juniorit
    //4014 Jäsenmaksu, aikuiset
    //4015 Ultimaten kilpailulisenssi, aikuiset
    //4016 Ultimaten kilpailulisenssi, juniorit
   
    //2012-2013
    //4644 A-lisenssi (sis jäsenmaksun ja lisenssin)
	//4645 B-lisenssi (sis jäsenmaksun ja lisenssin)
	//4653 C-lisenssi (sis jäsenmaksun ja lisenssin)
	//4649 Jäsenmaksu 2012 ja lisenssi (ei vakuutusta)
	//4650 Jäsenmaksu 2012 ja lisenssi alle 18-vuotiaat (ei vakuutusta)
	//4651 Jäsenmaksu, aikuiset 2012
	//4652 Jäsenmaksu, juniorit 2012

    
    $valid_membership=array(653,654,655,656,4001,4002,4003,4004,4008,4009,4010,4013,4014,4644,4645,4653,4649,4650,4651,4652);
    $valid_license=array(653,654,655,656,4001,4002,4008,4009,4010,4015,4016,4644,4645,4653,4649,4650);
    $valid_juniors=array(654,656,4002,4004,4009,4010,4013,4016,4652,4650,4645);
    $ignore = array(4011);
    
    if(in_array($license_id,$ignore)){
      continue;  
    }
    
    $membership="";
    //if(stristr($license_string,"senmaksu")){
    if(in_array($license_id,$valid_membership)){
      $membership=$year;
    }
    $license="";
    //if(stristr($license_string,"ultimaten kilpailulisenssi")){
    if(in_array($license_id,$valid_license)){
      $license=$year;
    }
    $junior=0;
    //if(stristr($license_string,"juniorit")||strstr($license_string,"junioirit")){
    if(in_array($license_id,$valid_juniors)){
      $junior=1;
    }
     
    if(!empty($birthdate)){
      $birthdate = substr($birthdate,0,4)."-".substr($birthdate,4,2)."-".substr($birthdate,6,2);
      $birthdate .= " 00:00:00";
    }else{
      $birthdate = "1971-01-01 00:00:00";
    }

    //echo "<p>$id $firstname $lastname</p>";
    $exist = DBQueryRowCount("SELECT accreditation_id FROM uo_license WHERE external_id='".mysql_real_escape_string($id)."'");
    if($exist==1){
      $query = "UPDATE uo_license SET junior=$junior ";
      if(!empty($membership)){
        $query .= ",membership='".$membership."'";
      }
      if(!empty($license)){
        $query .= ",license='".$license."'";
      }
      if(!empty($gender)){
        $query .= ",women=$women";
      }
      if(!empty($birthdate)){
        $query .= ",birthdate='".$birthdate."'";
      }
      $query .= sprintf(" WHERE external_id='%s'", mysql_real_escape_string($id));
      DBQuery($query);
    }else{
      
      //echo "<p>$lastname $firstname ($shortername)</p>";
      $check1 = "UPPER(lastname) LIKE '".mysql_real_escape_string($lastname)."'";
      $check2 = "UPPER(firstname) LIKE '".mysql_real_escape_string($firstname)."'";
      $check3 = "UPPER(firstname) LIKE '".mysql_real_escape_string($shortername)."'";
      $check4 = "birthdate='".mysql_real_escape_string($birthdate)."' AND birthdate!='1971-01-01 00:00:00'";

      //$count1 = DBQueryRowCount("SELECT accreditation_id FROM uo_license WHERE ".$check1);
      $count1 = DBQueryRowCount("SELECT accreditation_id FROM uo_license WHERE ".$check1." AND ".$check2." AND external_id IS NULL");
      $count2 = DBQueryRowCount("SELECT accreditation_id FROM uo_license WHERE ".$check1." AND ".$check3." AND external_id IS NULL");
      $count3 = DBQueryRowCount("SELECT accreditation_id FROM uo_license WHERE ".$check1." AND ".$check4." AND external_id IS NULL");

      $query = "UPDATE uo_license SET junior=$junior ";
      //$query = "UPDATE uo_license SET external_id=accreditation_id ";
      //$query .= sprintf(",accreditation_id='%s' ", mysql_real_escape_string($id));
      $query .= sprintf(",external_id='%s' ", mysql_real_escape_string($id));
      if(!empty($membership)){
        $query .= ",membership='".$membership."'";
      }
      if(!empty($license)){
        $query .= ",license='".$license."'";
      }
      $query .= ",external_type='".$license_id."'";
      $query .= ",birthdate='".$birthdate."'";
      $query .= ",women='".$women."'";
      if($count1==1){
        $query .= " WHERE $check1 AND $check2";
        DBQuery($query);
      }elseif($count2==1){
        $query .= " WHERE $check1 AND $check3";
        DBQuery($query);
      }elseif($count3==1){
        $query .= " WHERE $check1 AND $check4";
        DBQuery($query);
      }
      
      //echo "<p>$lastname $firstname ($shortername): $count1 $count2 $count3</p>";
      if($count1!=1 && $count2!=1 && $count3!=1){
        //$birthdate = "1971-01-01 00:00:00";
        if(empty($membership)){
          $membership = 0;
        }
        if(empty($license)){
          $license = 0;
        }

        $query = sprintf("INSERT INTO uo_license (lastname, firstname, birthdate, membership, license, junior, women, external_id, external_type,accreditation_id, ultimate)
    				  		VALUES ('%s','%s','%s','%s','%s','%s',%d,'%s','%s','%s',1)",
        mysql_real_escape_string($lastname),
        mysql_real_escape_string($firstname),
        mysql_real_escape_string($birthdate),
        mysql_real_escape_string($membership),
        mysql_real_escape_string($license),
        mysql_real_escape_string($junior),
        (int) $women,
        mysql_real_escape_string($id),
        mysql_real_escape_string($license_id),
        mysql_real_escape_string($id),
        1
        );
        DBQuery($query);
        $html .= "<p>".utf8entities($id)." ".utf8entities($firstname)." ".utf8entities($lastname)."</p>";

        //check if player already have profile
        $players = SeasonAllPlayers($season);
        $found=false;
        foreach($players as $player) {
          $playerinfo = PlayerInfo($player['player_id']);
          if(empty($playerinfo['accreditation_id'])){
            if($playerinfo['firstname']==$firstname && $playerinfo['lastname']==$lastname){
              	if(empty($player['profile_id'])){
              	  CreatePlayerProfile($player['player_id']);
              	  $playerinfo = PlayerInfo($player['player_id']);
              	}
              	$query = "UPDATE uo_player SET accreditation_id='".mysql_real_escape_string($id)."' ";
              	$query .= "WHERE player_id=". $player['player_id'];
              	DBQuery($query);              	
              	$query = "UPDATE uo_player_profile SET accreditation_id='".mysql_real_escape_string($id)."' ";
              	$query .= "WHERE profile_id=". $playerinfo['profile_id'];
              	DBQuery($query);
              	$found=true;
            }
          }
        }
      }
    }
    
    $accreditation_id = DBQueryToValue("SELECT accreditation_id FROM uo_license WHERE external_id='".mysql_real_escape_string($id)."'");
    $profile = DBQueryToRow("SELECT * FROM uo_player_profile WHERE accreditation_id='".$accreditation_id."'");

    if($profile){
      $query = "UPDATE uo_player_profile SET accreditation_id='".$accreditation_id."' ";
      $query .= ",birthdate='".$birthdate."'";

      if(empty($profile['gender'])){
        if($women){
          $query .= ",gender='F'";
        }else{
          $query .= ",gender='M'";
        }
      }
      if(empty($profile['email'])){
        $query .= ",email='".$email."'";
      }

      $query .= sprintf(" WHERE profile_id='%s'", $profile['profile_id']);
      DBQuery($query);


    }else{
      if($women){
          $gender = 'F';
        }else{
          $gender = 'M';
        }
    
       $query = sprintf("INSERT INTO uo_player_profile (firstname,lastname,accreditation_id, gender, email, birthdate) VALUES
				('%s','%s','%s','%s','%s','%s')",
            mysql_real_escape_string($firstname),
            mysql_real_escape_string($lastname),
            mysql_real_escape_string($id),
            mysql_real_escape_string($gender),
            mysql_real_escape_string($email),
            mysql_real_escape_string($birthdate));
            
            $profileId = DBQueryInsert($query);
    }
  }
  return $html;
}

?>