<?php
$new_players="";
$updated = false;

if (isset($_POST['upload'])) {

if($_FILES['uploadedfile']['type']=="text/x-csv" || $_FILES['uploadedfile']['type']=="text/csv"){
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
         // echo "<p>". $playerInfo['lastname']." ".$playerInfo['firstname']." :".$license['membership']."/".$license['license']." ".$playerInfo['accredited'] ."</p>";
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
          //if(!$playerInfo['accredited'] && isset($_POST['isValidityYear']) && !empty($_POST['validityYear'])
          //&& $license['membership']==$_POST['validityYear'] && $license['license']==$_POST['validityYear']){
          //  AccreditPlayer($playerInfo['player_id'], "automatic accreditation");
          //  $newacc++;
            //echo "accredited";
          //}
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

    //$license_string = trim($utf8 ? $data[6] : utf8_encode($data[6]));
    $license_id = trim($utf8 ? $data[5] : utf8_encode($data[5]));
    //$cond = trim($utf8 ? $data[7] : utf8_encode($data[7]));
    $year = trim($utf8 ? $data[6] : utf8_encode($data[6]));
    $email = trim($utf8 ? $data[7] : utf8_encode($data[7]));  

	//if($cond=="avoin"){
    //  continue;
   // }
    $dates = explode(".", $year);
    $year = $dates[2];

    if($year==""){
      continue;
    }
    
    if(strcasecmp($gender,"nainen")==0){
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

    //2013-2014
    //5202 A-lisenssi (sis jäsenmaksun ja lisenssin)
	//5203 B-lisenssi (sis jäsenmaksun ja lisenssin)
	//5204 C-lisenssi (sis jäsenmaksun ja lisenssin)
	//5200 Jäsenmaksu 2012 ja lisenssi (ei vakuutusta)
	//5201 Jäsenmaksu 2012 ja lisenssi alle 18-vuotiaat (ei vakuutusta)
	//5198 Jäsenmaksu, aikuiset 2012
	//5199 Jäsenmaksu, juniorit 2012
	
	//2014-2015
//5820 A-lisenssi (sisältää jäsenmaksun 2014 ja vakuutuksen)
//5800 A-lisenssi, ei jäsenmaksua
//5801 A-lisenssi, ei vakuutusta, ei jäsenmaksua
//5821 Aikuisten jäsenmaksu 2014 ja lisenssi (ei vakuutusta)
//5804 B-ja C-lisenssi (alle 18v), ei vakuutusta, ei jäsenmaksua
//5822 B-lisenssi (sisältää jäsenmaksun 2014 ja vakuutuksen)
//5802 B-lisenssi, ei jäsenmaksua
//5823 C-lisenssi (sisältää jäsenmaksun 2014 ja vakuutuksen)
//5803 C-lisenssi, ei jäsenmaksua
//5799 Jäsenmaksu aikuiset
//5798 Jäsenmaksu nuoret alle 18 v
//Kertalisenssi, ei vakuutusta, ei jäsenmaksua
//5824 Nuorten jäsenmaksu 2014 ja lisenssi (ei vakuutusta)

//2015-2016
//6431 Aikuisten vakuutus
//6432 Alle 16-vuotiaan vakuutus
//6421 Jäsenyys
//6420 Kertalisenssi (sis jäsenyys ja kertalisenssi)
//6414 Kesälisenssi aikuiset voimassa 1.4.15 – 30.9.15 (sis jäsenyys ja lisenssi)
//6422 Kesälisenssi aikuiset voimassa 1.4.15 – 30.9.15, (sis jäsenyys, lisenssi ja vakuutus)
//6425 Kesälisenssi juniorit 16-20v, voimassa 1.4.15 – 30.9.15 (sis jäsenyys, lisenssi ja vakuutus)
//6428 Kesälisenssi juniorit alle 16v, voimassa 1.4.15 – 30.9.15 (sis jäsenyys, lisenssi ja vakuutus)
//6417 Kesälisenssi juniorit, voimassa 1.4.15 – 30.9.15 (sis jäsenyys ja lisenssi)
//6415 Talvilisenssi aikuiset, voimassa 1.10.2015-31.3.2016 (sis jäsenyys ja lisenssi)
//6423 Talvilisenssi aikuiset, voimassa 1.10.2015-31.3.2016 (sis jäsenyys, lisenssi ja vakuutus)
//6426 Talvilisenssi juniorit 16-20v, voimassa 1.10.2015-31.3.2016 (sis jäsenyys, lisenssi ja vakuutus )
//6429 Talvilisenssi juniorit alle16v, voimassa 1.10.2015-31.3.2016 (sis jäsenyys, lisenssi ja vakuutus)
//6418 Talvilisenssi juniorit, voimassa 1.10.2015-31.3.2016 (sis jäsenyys ja lisenssi)
//6416 Vuosilisenssi aikuiset (sis jäsenyys ja lisenssi)
//6424 Vuosilisenssi aikuiset (sis jäsenyys, lisenssi ja vakuutus)
//6419 Vuosilisenssi juniorit (sis jäsenyys ja lisenssi)
//6427 Vuosilisenssi juniorit 16-20, (sis jäsenyys, lisenssi ja vakuutus)
//6430 Vuosilisenssi juniorit alle 16, (sis jäsenyys , lisenssi ja vakuutus)

//2016-2017
//	16-20 vuotiaiden vakuutus
//	Aikuisten vakuutus
//	Alle 12-vuotiaiden vakuutus
//7151	Jäsenyys
//7152	Jäsenyys alle 20-vuotiaalle
//7155	Kertalisenssi
//7157	Kesälisenssi aikuiset (jäsenyys ja lisenssi)
//7160	Kesälisenssi juniorit alle 20v (jäsenyys ja lisenssi)
//7158	Talvilisenssi aikuiset (jäsenyys ja lisenssi)
//7161	Talvilisenssi juniorit alle 20v (jäsenyys ja lisenssi)
//7159	Vuosilisenssi aikuiset (jäsenyys ja lisenssi)
//7162	Vuosilisenssi juniorit alle 20v (jäsenyys ja lisenssi)
//27	Jäsenyys, aikuiset
//28	Jäsenyys, alle 20-vuotiaat
//29	Kesälisenssi, aikuiset
//31	Vuosilisenssi, aikuiset
//32	Kesälisenssi, alle 20-vuotiaat
//34	Vuosilisenssi, alle 20-vuotiaat
//35	kertalisenssi, sis. jäsenmaksun
//30	Talvilisenssi, aikuiset
//33	Talvilisenssi, alle 20-vuotiaat
//385	Talvilisenssi, kertalisenssin ostaneille
//36 	kertalisenssi, sis. jäsenmaksu alle 20-vuotiaat

//2018
//404	Vuosilisenssi aikuiset
//405	Vuosilisenssi alle 20-vuotiaat (1.4.1999 tai sen jälkeen syntyneet)
//406	Kesälisenssi aikuiset
//407	Kesälisenssi alle 20-vuotiaat (1.4.1999 tai sen jälkeen syntyneet)
//413	Kertalisenssi aikuiset
//414	Kertalisenssi juniorit
//432	B-tour pelaajamaksu
//408	Talvilisenssi aikuiset
//412	Talvilisenssi alle 20-vuotiaat (1.4.1999 tai sen jälkeen syntyneet)

//2019
//807	Vuosilisenssi aikuiset
//810	Vuosilisenssi alle 20-vuotiaat (1.4.1999 tai sen jälkeen syntyneet)
//806	Kesälisenssi aikuiset
//812	Kesälisenssi alle 20-vuotiaat (1.4.1999 tai sen jälkeen syntyneet)
//805	Kertalisenssi aikuiset
//809 Nuorten jäsenyys
//	Kertalisenssi juniorit
//	B-tour pelaajamaksu
//808	Talvilisenssi aikuiset
//813	Talvilisenssi alle 20-vuotiaat (1.4.1999 tai sen jälkeen syntyneet)

//2020
//1294	Kilpailulisenssi kesä 2020 (vaaditaan SM-sarjaan/-turnauksiin)
//1295	Kilpailulisenssi koko kausi 2020-2021 (vaaditaan SM-sarjaan/-turnauksiin)
//1296	Junioreiden kilpailulisenssi kesä 2020 (vaaditaan SM-sarjaan/-turnauksiin)
//1297	Junioreiden kilpailulisenssi koko kausi
//1759	Kilpailulisenssi aikuiset talvikausi 2020-2021


//2021
//2020	Kilpailulisenssi juniorit koko kausi 2021-2022
//2021  Kertalisenssi aikuiset kesäkausi 2021(vaaditaan SM-sarjaan/-turnauksiin)
//2023	Kilpailulisenssi aikuiset koko kausi 2021-2022  (vaaditaan SM-sarjaan ja divareihin)
//2024	Kilpailulisenssi aikuiset kesäkausi 2021 (vaaditaan SM-sarjaan/-turnauksiin)

    $valid_membership=array(2020,2023,2024);
    $valid_license=array(2020,2021,2023,2024);
    $valid_juniors=array(2020);
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
	$external_type="";
    //if(stristr($license_string,"ultimaten kilpailulisenssi")){
    if(in_array($license_id,$valid_license)){
      $license=$year;
      $external_type=$license_id;
    }
    $junior=0;
    //if(stristr($license_string,"juniorit")||strstr($license_string,"junioirit")){
    if(in_array($license_id,$valid_juniors)){
      $junior=1;
    }
     
    if(!empty($birthdate)){
      //$birthdate = substr($birthdate,0,4)."-".substr($birthdate,4,2)."-".substr($birthdate,6,2);
	  $dates = explode(".", $birthdate);
      $birthdate = $dates[2]."-".$dates[1]."-".$dates[0];
      $birthdate .= " 00:00:00";
    }else{
      $birthdate = "1971-01-01 00:00:00";
    }

    //echo "<p>$id $firstname $lastname</p>";
    $exist = DBQueryRowCount("SELECT accreditation_id FROM uo_license WHERE external_id='".DBEscapeString($id)."'");
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
	  if(!empty($external_type)){
		$query .= ",external_type='".$external_type."'";
	  }
	   
      $query .= sprintf(" WHERE external_id='%s'", mysql_real_escape_string($id));
      DBQuery($query);
    }else{
      
      //echo "<p>$lastname $firstname ($shortername)</p>";
      $check1 = "UPPER(lastname) LIKE '".DBEscapeString($lastname)."'";
      $check2 = "UPPER(firstname) LIKE '".DBEscapeString($firstname)."'";
      $check3 = "UPPER(firstname) LIKE '".DBEscapeString($shortername)."'";
      $check4 = "birthdate='".DBEscapeString($birthdate)."' AND birthdate!='1971-01-01 00:00:00'";

      //$count1 = DBQueryRowCount("SELECT accreditation_id FROM uo_license WHERE ".$check1);
      $count1 = DBQueryRowCount("SELECT accreditation_id FROM uo_license WHERE ".$check1." AND ".$check2." AND external_id IS NULL");
      $count2 = DBQueryRowCount("SELECT accreditation_id FROM uo_license WHERE ".$check1." AND ".$check3." AND external_id IS NULL");
      $count3 = DBQueryRowCount("SELECT accreditation_id FROM uo_license WHERE ".$check1." AND ".$check4." AND external_id IS NULL");

      $query = "UPDATE uo_license SET junior=$junior ";
      //$query = "UPDATE uo_license SET external_id=accreditation_id ";
      //$query .= sprintf(",accreditation_id='%s' ", DBEscapeString($id));
      $query .= sprintf(",external_id='%s' ", DBEscapeString($id));
      if(!empty($membership)){
        $query .= ",membership='".$membership."'";
      }
      if(!empty($license)){
        $query .= ",license='".$license."'";
      }
	  if(!empty($external_type)){
		$query .= ",external_type='".$external_type."'";
	  }
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
              	$query = "UPDATE uo_player SET accreditation_id='".DBEscapeString($id)."' ";
              	$query .= "WHERE player_id=". $player['player_id'];
              	DBQuery($query);              	
              	$query = "UPDATE uo_player_profile SET accreditation_id='".DBEscapeString($id)."' ";
              	$query .= "WHERE profile_id=". $playerinfo['profile_id'];
              	DBQuery($query);
              	$found=true;
            }
          }
        }
      }
    }
    
    $accreditation_id = DBQueryToValue("SELECT accreditation_id FROM uo_license WHERE external_id='".DBEscapeString($id)."'");
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
            DBEscapeString($firstname),
            DBEscapeString($lastname),
            DBEscapeString($id),
            DBEscapeString($gender),
            DBEscapeString($email),
            DBEscapeString($birthdate));
            
            $profileId = DBQueryInsert($query);
    }
  }
  return $html;
}

?>