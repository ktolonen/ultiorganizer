<?php 
include_once 'lib/fpdf/fpdf.php';
include_once 'lib/HSVClass.php';

class PDF extends FPDF
	{
	var $B;
	var $I;
	var $U;
	var $HREF;
	
	var $game = array(
			"seasonname"=>"",
			"game_id"=>"",
			"hometeamname"=>"",
			"visitorteamname"=>"",
			"poolname"=>"",
			"time"=>"",
			"placename"=>""
			);
	
	function PrintScoreSheet($seasonname,$gameId,$hometeamname,$visitorteamname,$poolname,$time,$placename)
		{
		$this->game['seasonname'] = utf8_decode($seasonname);
		$this->game['game_id'] = $gameId.-"".getChkNum($gameId);
		$this->game['hometeamname'] = utf8_decode($hometeamname);
		$this->game['visitorteamname'] = utf8_decode($visitorteamname);
		$this->game['poolname'] = utf8_decode($poolname);
		$this->game['time'] = $time;
		$this->game['placename'] = utf8_decode($placename);
		
		$this->AddPage();
		
		$data = _("Gummis");
		$data .= " - ";
		$data .= _("Game Record"); 
		$data = utf8_decode($data); //season name already decoded
		$data .= " " . $this->game['seasonname'];
		
		$this->HeaderColors(1);
		$this->Cell(0,9,$data,1,1,'C',true);
		
		$this->SetY(21);

		
		$this->OneCellTable(utf8_decode(_("Game #")), $this->game['game_id']);
		$this->OneCellTable(utf8_decode(_("Home team")), $this->game['hometeamname']);
		$this->OneCellTable(utf8_decode(_("Away team")), $this->game['visitorteamname']);
		$this->OneCellTable(utf8_decode(_("Division").", "._("Pool")), $this->game['poolname']);
		$this->OneCellTable(utf8_decode(_("Field")), $this->game['placename']);
		$this->OneCellTable(utf8_decode(_("Scheduled start date and time")), $this->game['time']);
		$this->OneCellTable(utf8_decode(_("Game official")), "");
		$this->SetFont('Arial','',10);
		$this->Ln();
		
		$this->FirstOffence();
		$this->Ln();

		$this->Timeouts();
		$this->Ln();

		$this->OneCellTable(utf8_decode(_("Half time ends")), "");
		$this->Ln();
		
		$this->Ln();
		$this->FinalScoreTable();
		$this->Ln();

		$this->Signatures();
		$this->SetXY(95,21);
		$this->ScoreGrid();
		
		$this->SetY(-25);
		$data = "";
		$data = utf8_decode($data);
		$this->SetFont('Arial','',10);
		$this->EmptyColors();
		$this->MultiCell(0,2,$data);
		$this->Image("cust/gummis/gummi_footer.png",10,255, 80);
		
		}
		
	function PrintDefenseSheet($seasonname,$gameId,$hometeamname,$visitorteamname,$poolname,$time,$placename)
		{
		$this->game['seasonname'] = utf8_decode($seasonname);
		$this->game['game_id'] = $gameId."".getChkNum($gameId);
		$this->game['hometeamname'] = utf8_decode($hometeamname);
		$this->game['visitorteamname'] = utf8_decode($visitorteamname);
		$this->game['poolname'] = utf8_decode($poolname);
		$this->game['time'] = $time;
		$this->game['placename'] = utf8_decode($placename);
		
		$this->AddPage();
		
		$data = _("Organization");
		$data .= " - ";
		$data .= _("Defenses record"); 
		$data = utf8_decode($data); //season name already decoded
		$data .= " " . $this->game['seasonname'];
		
		$this->HeaderColors(1);
		$this->Cell(0,9,$data,1,1,'C',true);
		$this->Ln();
		
		$this->SetY(21);
		$this->DefenseGrid();
		
		$this->SetY(-25);
		$data = "";
		$data = utf8_decode($data);
		$this->EmptyColors();
		$this->MultiCell(0,2,$data);
		//$this->WriteHTML($data);
		}

	//Playerlist array("name"=>name, "accredited"=>accredited, "num"=>number)
	function PrintPlayerList($homeplayers, $visitorplayers)
		{
		if (!isset($homeplayers[0]['name']) && !$visitorplayers[0]['name'])
			return;
			
		$this->AddPage();
		
		$data = _("Gummis");
		$data .= " - ";
		$data .= _("Roster"); 
		$data .= " ". _("for game"). " #" . $this->game['game_id'];
		$data = utf8_decode($data);
		$this->HeaderColors(1);
		$this->Cell(0,9,$data,1,1,'C',true);
		
		$this->SetY(21);
		
		$this->HeaderColors(2);

		$this->Cell(94,6,$this->game['hometeamname'],'LRTB',0,'C',true);
		
		$this->EmptyColors();
		$this->Cell(2,6,"",'LR',0,'C',true); //separator
		
		$this->HeaderColors(2);
		$this->Cell(94,6,$this->game['visitorteamname'],'LRTB',0,'C',true);
		
		$this->Ln();
		$this->HeaderColors(3);
		//$this->Cell(8,6,"",'LRTB',0,'C',true);
		$this->Cell(56,6,utf8_decode(_("Name")),'LRTB',0,'C',true);
		$this->Cell(15,6,utf8_decode(_("Jersey#")),'LRTB',0,'C',true);
		$this->Cell(23,6,utf8_decode(_("Info")),'LRTB',0,'C',true);
		//$this->Cell(10,6,_("License ok"),'LRTB',0,'C',true);
		
		$this->EmptyColors();
		$this->Cell(2,6,"",'LR',0,'C',true); //separator
		
		$this->HeaderColors();
		//$this->Cell(8,6,"",'LRTB',0,'C',true);
		$this->Cell(56,6,utf8_decode(_("Name")),'LRTB',0,'C',true);
		$this->Cell(15,6,utf8_decode(_("Jersey#")),'LRTB',0,'C',true);
		$this->Cell(23,6,utf8_decode(_("Info")),'LRTB',0,'C',true);		
		//$this->Cell(10,6,_("License ok"),'LRTB',0,'C',true);

		$this->Ln();
		$this->EmptyColors();
		for($i=1;$i<31;$i++)
			{
			$hplayer = "";
			$hnumber = "";
			$vplayer = "";
			$vnumber = "";
			
			if(isset($homeplayers[$i-1]['name'])){
				$hplayer = utf8_decode($homeplayers[$i-1]['name']);
				$hnumber = $homeplayers[$i-1]['num'];
			}
			if(isset($visitorplayers[$i-1]['name'])){
				$vplayer = utf8_decode($visitorplayers[$i-1]['name']);
				$vnumber = $visitorplayers[$i-1]['num'];
			}
			$this->EmptyColors();
			//$this->Cell(8,6,$i,'LRTB',0,'C',true);
			
			if(!empty($hplayer) && !($homeplayers[$i-1]['accredited'])){
				$this->EmptyColors(3, NULL, 'IB');
			}
			
			$this->Cell(56,6,$hplayer,'LRTB',0,'L',true);
			
			$this->EmptyColors();
			$this->Cell(15,6,$hnumber,'LRTB',0,'C',true);
			$this->Cell(23,6,"",'LRTB',0,'C',true);
			//$this->Cell(10,6,"",'LRTB',0,'C',true);

			$this->Cell(2,6,"",'LR',0,'C',true); //separator
			
			//$this->Cell(8,6,$i,'LRTB',0,'C',true);
			
			if(!empty($vplayer) && !($visitorplayers[$i-1]['accredited'])){
				$this->EmptyColors(3, NULL, 'IB');
			}
			$this->Cell(56,6,$vplayer,'LRTB',0,'L',true);
			
			$this->EmptyColors();
			$this->Cell(15,6,$vnumber,'LRTB',0,'C',true);
			$this->Cell(23,6,"",'LRTB',0,'C',true);
			//$this->Cell(10,6,"",'LRTB',0,'C',true);
			$this->Ln();			
			}
		
		$this->EmptyColors(4);
		$data = _("Total number of players:")." ". count($homeplayers);
		$data = utf8_decode($data);
		$this->Cell(94,4,$data,'T',0,'L',true);
		$this->Cell(2,6,"",'',0,'C',true); //separator
		$data = _("Total number of players:")." ". count($visitorplayers);
		$data = utf8_decode($data);
		$this->Cell(94,4,$data,'T',0,'L',true);
		
		$this->Ln();
		
		//instructions
		$data = "<br><b>"._("Scoresheet filling instructions:")."</b><br>";
		$data .= "1. "._("Officials fill in their names.")."<br>";
		$data .= "2. "._("Captains confirm roster by crossing out injured players, and adjusting jersey numbers if necessary.")."<br>";
		$data .= "3. "._("After the toss, officials check the team that will start on offence.")."<br>";
		$data .= "4. "._("When half time starts, fill in time it ends (the second half start time).")."<br>";
		$data .= "5. "._("During the game, fill in which team has scored, the jersey numbers of the player who threw the goal (Assist) and the player who caught the goal (Goal), the time that the goal was scored, and the scoreline after the goal. If a player scores an intercept goal (Callahan), then mark XX as assist.")."<br>";
		$data .= "6. "._("When a team takes a time-out, mark the time in the \"Time-outs\" section.")."<br>";
		$data .= "7. "._("After the game, each captain signs the scoresheet to confirm the final score.")."<br>";
		$data .= "8. "._("Officials return the completed scoresheet to the results headquarters.");
		$data = utf8_decode($data);
		$this->EmptyColors(4);
		$this->WriteHTML($data);
		
		}		

  function PrintRoster($teamname, $seriesname, $poolname, $players) {
		$this->AddPage();
		
		$data = $teamname;
		$data .= " - ";
		$data .= _("Roster"); 
		$data = utf8_decode($data);
		$this->HeaderColors(1);
		$this->Cell(0,9,$data,1,1,'C',true);
		
		$data = U_($seriesname);
		$data .= ", ";
		$data .= U_($poolname);
		$data .= ", ";
		$data .= _("Game")." #:"; 
		$data = utf8_decode($data);
		$this->EmptyColors(1);
		$this->Cell(0,6,$data,1,1,'L',true);
		
		$this->HeaderColors(4);

		$this->Cell(8,6,"",'LRTB',0,'C',true);
		$this->Cell(100,6,utf8_decode(_("Name")),'LRTB',0,'C',true);
		$this->Cell(10,6,utf8_decode(_("Play")),'LRTB',0,'C',true);
		$this->Cell(10,6,utf8_decode(_("Game#")),'LRTB',0,'C',true);
		$this->Cell(62,6,utf8_decode(_("Info")),'LRTB',0,'C',true);
		$this->Ln();
		$this->EmptyColors();
		for($i=1;$i<26;$i++){
			$player = "";

			if(isset($players[$i-1]['firstname'])){
				$player .= utf8_decode($players[$i-1]['firstname']);
			}
		    $player .= " ";
			if(isset($players[$i-1]['lastname'])){
				$player .= utf8_decode($players[$i-1]['lastname']);
			}
			
			$this->EmptyColors();
			$this->Cell(8,6,$i,'LRTB',0,'C',true);
			
			if(isset($players[$i-1]['accredited']) && !($players[$i-1]['accredited'])){
				$this->EmptyColors(3, NULL,'IB');
			}
			
			$this->Cell(100,6,$player,'LRTB',0,'L',true);
			$this->EmptyColors();
			$this->Cell(10,6,"",'LRTB',0,'C',true);
			if(isset($players[$i-1]['num']) && $players[$i-1]['num']>=0){
			  $this->Cell(10,6,$players[$i-1]['num'],'LRTB',0,'C',true);
			}else{
			  $this->Cell(10,6,"",'LRTB',0,'C',true);
			}
			$this->Cell(62,6,"",'LRTB',0,'C',true);

			$this->Ln();			
			}
			
		$this->Ln();
		
		//instructions
		$data = "<b>"._("NOTICE")." 1!</b> "._("For new players added, accreditation id or date of birth must be written down.")."<BR>";
		$data .= "<b>"._("NOTICE")." 2!</b> "._("The team is responsible for the accreditation of <u>all</u> players on the list.")."<BR>";
		$data .= "<b>"._("NOTICE")." 3! "._("<b><i>Bold italic</i></b> printed players has problems with license. They are <u>not</u> allowed to play until problems are solved (= payment recipe or note from organizer shown).")."";
		$data = utf8_decode($data);
		$this->EmptyColors(4);
		$this->WriteHTML($data);
		
		}		
	function PrintSchedule($scope, $id, $games)
		{
		$left_margin = 10;
		$top_margin = 10;
		//event title
		$this->SetAutoPageBreak(false,$top_margin);
		$this->SetMargins($left_margin,$top_margin); 
		
		$this->AddPage();
		
		switch($scope){
			case "season":
				$this->PrintSeasonPools($id);
				$this->AddPage();
				break;
		
			case "series":
				$this->PrintSeriesPools($id);
				$this->AddPage();
				break;

			case "pool":
			case "team":
				break;
		}
		
		$this->SetAutoPageBreak(true,$top_margin);
		$prevTournament = "";
		$prevPlace = "";
		$prevSeries = "";
		$prevPool = "";
		$prevTeam = "";
		$prevDate = "";
		$prevField = "";
		$isTableOpen = false;
	
		$this->SetTextColor(255);
		$this->SetFillColor(0);
		$this->SetDrawColor(0);
		//print all games in order
		while($game = mysqli_fetch_assoc($games)){
			
			if(!empty($game['place_id']) && $game['reservationgroup'] != $prevTournament) {
				$txt = utf8_decode(U_($game['reservationgroup']));
				$this->SetFont('Arial','B',12);
				$this->SetTextColor(0);
				$this->Ln();
				$this->Write(5, $txt);
				$this->Ln();
				$prevDate="";
			}	

			if(!empty($game['place_id']) && JustDate($game['starttime']) != $prevDate){
				$txt = DefWeekDateFormat($game['starttime']);
				$this->SetFont('Arial','B',10);
				$this->SetTextColor(0);
				$this->Ln();
				$this->Write(5, $txt);
			}
			
			if(!empty($game['place_id']) && ($game['place_id'] != $prevPlace || $game['fieldname'] != $prevField || JustDate($game['starttime']) != $prevDate)){
				$txt = U_($game['placename']);
				$txt .= " "._("Field")." ".U_($game['fieldname']);
				$txt = utf8_decode($txt);
				
				$this->SetFont('Arial','',10);
				$this->SetTextColor(0);
				$this->Ln();
				$this->Cell(0,5,$txt,0,2,'L',false);
			}
			if(!empty($game['reservationgroup']) && !empty($game['place_id'])){
				$this->GameRowWithPool($game, false, true, false);
				$this->Ln();
			}
			
			$prevTournament = $game['reservationgroup'];
			$prevPlace = $game['place_id'];
			$prevField = $game['fieldname'];
			$prevSeries = $game['series_id'];
			$prevPool = $game['pool'];
			$prevDate = JustDate($game['starttime']);
		}
		
		}
		
	function PrintOnePageSchedule($scope, $id, $games, $colors=false){
		$left_margin = 10;
		$top_margin = 10;
		$xarea = 400;
		$yarea = 270;
		$yfieldtitle = 8;
		$xtimetitle = 12;
		$ypagetitle = 5;
		$teamfont = 10;
		
		//event title
		$this->SetAutoPageBreak(false,$top_margin);
		$this->SetMargins($left_margin,$top_margin); 
		
		$timeslots = array();
		$times = array();
		$prevTournament = "";
		$prevPlace = "";
		$prevSeries = "";
		$prevPool = "";
		$prevTeam = "";
		$prevDate = "";
		$prevField = "";
		$fieldstotal = 0;
		
		$isTableOpen = false;
	
		$field = 0;
		$time_offset = $top_margin+$yfieldtitle;
		$field_offset = 0;
		$gridx = 12;
		$gridy = $yarea/60;
		$min_gridy = 5;
		$min_gridx = 40;
		$fieldlimit = 15;
		$num_rows = 1;
			
		$this->SetTextColor(255);
		$this->SetFillColor(0);
		$this->SetDrawColor(0);
		//print all games in order
		while(($gameArray[] = mysqli_fetch_assoc($games)) || array_pop($gameArray));
		
		$g = 0;
		foreach($gameArray as $game){
			$g++;
			
			//one reservation group per page
			if(!empty($game['place_id']) && $game['reservationgroup'] != $prevTournament || $prevDate != JustDate($game['starttime'])) {
				$this->AddPage("L","A3");
				
				$title = utf8_decode(SeasonName($id));
				$title .= " ".utf8_decode($game['reservationgroup']);
				$title .= " (".utf8_decode(ShortDate($game['starttime'])).")";
				$this->SetFont('Arial','BU',12);
				$this->SetTextColor(0);
				
				$times = TimetableTimeslots($game['reservationgroup'],$id);
				$timeslots = array();
				
				$startslot = floor(strtotime($times[0]['time']) / 60 / 30) * 30 * 60;
				$endslot = ceil(strtotime($times[count($times) - 1]['time']) / 60 / 30) * 30 * 60;
				$numslots = ($endslot - $startslot) / 60 / 30 + 2;

				$fieldstotal = TimetableFields($game['reservationgroup'], $id);
				
				if ($numslots * 2 * $min_gridy > $yarea - ($top_margin + $yfieldtitle * 2 + $ypagetitle + 5) || $fieldstotal * $min_gridx * 1.5 < ($xarea - $xtimetitle)) {
					$num_rows = 1;
				} else {
					$num_rows = 2;
				}
				
				$gridy = ($yarea - $top_margin - $yfieldtitle * 2 - $ypagetitle - 10) / $numslots / $num_rows;
				
				$num_pages = floor($fieldstotal / $num_rows / ($xarea - $xtimetitle) * $min_gridx)+1;  
				$fieldlimit = max(3, floor($fieldstotal / $num_rows / $num_pages) +1);
				
				$time_offset = ($yarea / $num_rows) * 0 + $top_margin + $yfieldtitle + $ypagetitle;

				$i = 0;
				
				for ($i = 0, $slot = $startslot; $i < max(3, $numslots); $i++) {
					$timeslots[date("H:i", $slot)] = $i * $gridy;
					$slot = $slot + 60 * 30;
				}
				//foreach($times as $time){
				//	$timeslots[DefHourFormat($time['time'])] = $i*20;
				//	$i++;
				//}
				
				
				$gridx = ($xarea-$xtimetitle)/$fieldlimit;
				$field = 0;
				$prevField = "";
				$row = 1;
				
// 				$this->SetXY($left_margin, $top_margin);
// 				$this->SetFont('Arial','B',10);
// 				$this->SetTextColor(0,200,0);
// 				$this->SetFillColor(0,0,200);
// 				$this->Cell($xarea,$yarea,"*****","LRTB",2,'C',true);
				
				$this->Cell(0,0,$title.".".$fieldstotal.".".$fieldlimit,0,2,'C',false);
			}
			
			//next field
			if(!empty($game['place_id']) && $game['place_id'] != $prevPlace || $game['fieldname'] != $prevField){
				$field++;

				if($field > $fieldlimit){
					$row++;
					if ($row > $num_rows) {
						$row = 1;
						$this->AddPage("L","A3");
					}
						
					$field=1;
					$time_offset = ($yarea / $num_rows) * ($row - 1) + $top_margin + $yfieldtitle + $ypagetitle;
				}
				//write times
				if($field==1){
					$this->SetFont('Arial','B',10);
					$this->SetTextColor(0);
					$this->SetXY($left_margin,$time_offset);
				
					//write times
					foreach($timeslots as $time=>$toffset){
						$this->Cell($xtimetitle,$gridy,$time,0,2,'L',false);
					}
				}
				
				$field_offset = $left_margin+($field-1)*$gridx+$xtimetitle;
				$this->SetXY($field_offset,$time_offset-$yfieldtitle);
								
				$this->SetFont('Arial','B',10);
				$this->SetTextColor(0);
				$this->SetFillColor(190);
				
				$txt = utf8_decode(_("Field")." ".$game['fieldname']);
				$this->Cell($gridx,$yfieldtitle/2,$txt,"LRT",2,'C',true);
				
				$this->SetFont('Arial','',8);
				$this->SetTextColor(0);
				$txt = utf8_decode($game['placename']);
				$this->Cell($gridx,$yfieldtitle/2,$txt,"LR",2,'C',true);
				//write grids
				foreach($timeslots as $time){
					$this->Cell($gridx,$gridy,"",1,2,'L',false);
				}
			}
			
			$starttime = floor(strtotime($times[0]['time'])/60/30)*30*60;
			$startoffset =  (strtotime($game['time']) - $starttime)/60/30*$gridy;
			// $slot = date("H:i",floor(strtotime($game['time'])/30/60)*30*60);
				
			$this->SetXY($field_offset,$time_offset+$startoffset);
			
			$this->SetTextColor(0);
			$this->SetFillColor(230);
			$this->SetDrawColor(0);
			
			$height=($game['timeslot']/30)*$gridy;
			$this->Cell($gridx,$height,"",'LRBT',0,'C',true);
			
			$this->SetXY($field_offset,$time_offset+$startoffset);
			
			$this->SetTextColor(0);
			$this->SetFillColor(255);
			$this->SetDrawColor(0);
			$this->SetFont('Arial','',$teamfont);
			$this->SetTextColor(0);
			// $this->Cell($gridx,1,"",0,2,'',false);
			
			$this->Cell($gridx,1,"",0,2,'L',$colors);
			$pooltxt = utf8_decode($game['seriesname']);
			$pooltxt .= ", ";
			$pooltxt .= utf8_decode($game['poolname']);
			
			if($game['hometeam'] && $game['visitorteam']){
				$this->DynCell($gridx, $height, date("H:i", strtotime($game['time'])), 
						$game['hometeamname'], $game['homeshortname'],
						$game['visitorteamname'], $game['visitorshortname'], 
						$pooltxt, $gridx, $teamfont, $colors, $game['color']);
			}elseif($game['gamename']){
				$this->DynCell($gridx, $height, date("H:i", strtotime($game['time'])), 
						$game['gamename'], NULL,
						NULL, NULL, 
						$pooltxt, $gridx, $teamfont, $colors, $game['color']);
			}else{
				$this->DynCell($gridx, $height, date("H:i", strtotime($game['time'])), 
						$game['phometeamname'], NULL,
						$game['pvisitorteamname'], NULL, 
						$pooltxt, $gridx, $teamfont, $colors, $game['color']);
			}
			$this->SetFont('Arial','',$teamfont);
			
			$this->SetTextColor(0);
			$this->SetFillColor(255);
			$this->SetDrawColor(0);
				
			$this->SetXY($field_offset,$time_offset+$startoffset);
			//$this->Cell($gridx,$gridy,"","LRBT",2,'L',false);			
			
			$prevTournament = $game['reservationgroup'];
			$prevPlace = $game['place_id'];
			$prevField = $game['fieldname'];
			$prevSeries = $game['series_id'];
			$prevPool = $game['pool'];
			$prevDate = JustDate($game['starttime']);
			$prevTime = DefHourFormat($game['starttime']);
		}
		
	}
	
	function Footer(){
		$this->SetXY(-50,-8);
		$this->SetFont('Arial','',6);
		$this->SetTextColor(0);
		$txt = date( 'Y-m-d H:i:s P', time());
		$this->Cell(0,0,$txt,0,2,'R',false);
	
	}


	function TextColor($bgcolor) {
		$hsv = new HSVClass();
		$hsv->setRGBString($bgcolor);
		$hsv->changeHue(180);
		$hsvArr = $hsv->getHSV();
		$hsv->setHSV($hsvArr['h'], 1-$hsvArr['s'],1-$hsvArr['v']);
		return $hsv->getRGB();
	}
	
	function DynSetTeamName($longname, $abbrev, $x, $fontsize){
		$this->SetFont('Arial','B',$fontsize);
		$text = utf8_decode($longname);
		if($this->GetStringWidth($text)>$x-2 && !empty($abbrev)){
			$text = utf8_decode($abbrev);
		}
		
		while($this->GetStringWidth($text)>$x-2){
			$this->SetFont('Arial','',--$fontsize);
		}
		
		return $text;
	}
	
	function DynCell($width, $height, $pretext, $longname1, $abbrev1, $longname2, $abbrev2, $posttext, $x, $fontsize, $colors=false, $gamecolor=NULL) {
		$text1 = utf8_decode($longname1);
		$fs1 = min($fontsize, $height/3);
		$this->SetFont('Arial','',$fs1);
		if($this->GetStringWidth($text1)>$x-2 && !empty($abbrev1)){
			$text1 = utf8_decode($abbrev1);
		}
		while($this->GetStringWidth($text1)>$x-2){
			$this->SetFont('Arial','',--$fs1);
		}
		
		$text2 = utf8_decode($longname2);
		$fs2 = min($fontsize, $height/3);
		$this->SetFont('Arial','',$fs2);
		if($this->GetStringWidth($text2)>$x-2 && !empty($abbrev)){
			$text1 = utf8_decode($abbrev2);
		}
		while($this->GetStringWidth($text2)>$x-2){
			$this->SetFont('Arial','',--$fs2);
		}

		$text3 = utf8_decode($pretext." ".$posttext);
		$fs3 = min($fontsize, $height/3);
		$this->SetFont('Arial','',$fs3);
		while($this->GetStringWidth($text3)>$x-2){
			$this->SetFont('Arial','',--$fs2);
		}
		
		$fs4 = min(ceil($height), $fontsize);
		$this->SetFont('Arial','',$fs4);
		$txt = utf8_decode($pretext) . " ";
		if (!empty($abbrev1))
			$txt .= utf8_decode($abbrev1);
		else
			$txt .= utf8_decode($longname1);
		$txt .= " - "; 
		if (!empty($abbrev2))
			$txt .= utf8_decode($abbrev2);
		else
			$txt .= utf8_decode($longname2); 
		$txt .=  " ("; 
		$txt .= utf8_decode($posttext); 
		$txt.=")";
		if ($this->GetStringWidth($txt)>-2)
			$this->SetFont('Arial','',--$fs4);
			
		if($colors){
			$textcolor = $this->TextColor($gamecolor);
			$fillcolor = colorstring2rgb($gamecolor);
				
			$this->SetDrawColor($textcolor['r'],$textcolor['g'],$textcolor['b']);
			$this->SetFillColor($fillcolor['r'],$fillcolor['g'],$fillcolor['b']);
			$this->SetTextColor($textcolor['r'],$textcolor['g'],$textcolor['b']);
		}else{
			$this->SetTextColor(0);
			$this->SetFillColor(230);
			$this->SetDrawColor(0);
		}
		
		if ($fs4 > $fs1 || $fs4 > $fs2 || $fs4 > $fs3) {
			$this->Cell($width, $height, $txt, 0, 2, 'L', false);
		} else {
			$this->SetFont('Arial', '', min($fs1, $fs2, $fs3));
			$this->Cell($width, $height/3, $text1, 0, 2, 'L', $colors);
			$this->Cell($width, $height/3, $text2, 0, 2, 'L', $colors);
			$this->Cell($width, $height/3, utf8_decode($pretext . " " . $posttext), 0, 2, 'L', $colors);
		}
		
	}
	
	function GameRowWithPool($game, $date=false, $time=true, $field=true, $pool=true, $result=true) {
	
		$this->SetFont('Arial','',8);
		$textcolor = $this->TextColor($game['color']);
		$fillcolor = colorstring2rgb($game['color']);
		$this->SetDrawColor(0);
		$this->SetFillColor($fillcolor['r'],$fillcolor['g'],$fillcolor['b']);
		$this->SetTextColor($textcolor['r'],$textcolor['g'],$textcolor['b']);
		
		if($date){
			$txt = ShortDate($game['time']);
			$this->Cell(10,5,$txt,'TB',0,'L',true);
		}
		
		if($time){
			$txt = DefHourFormat($game['time']);
			$this->Cell(10,5,$txt,'TB',0,'L',true);
		}
		
		if($field){
			$txt = utf8_decode(U_($game['fieldname']));
			$this->Cell(20,5,$txt,'TB',0,'L',true);
		}
		
		$o=0;
		if($game['gamename']){
			$this->SetFont('Arial','B',8);
			$txt = utf8_decode(U_($game['gamename']).":");
			$this->Cell(30,5,$txt,'TB',0,'L',true);
			$o=15;
			$this->SetFont('Arial','',8);
		}
		
		if($game['hometeam'] && $game['visitorteam']){
			$txt = utf8_decode($game['hometeamname']);
			$this->Cell(45-$o,5,$txt,'TB',0,'L',true);
			$txt = " - ";
			$this->Cell(5,5,$txt,'TB',0,'L',true);
			$txt = utf8_decode($game['visitorteamname']);
			$this->Cell(45-$o,5,$txt,'TB',0,'L',true);
		}else{
			$this->SetFont('Arial','I',8);
			$txt = utf8_decode($game['phometeamname']);
			$this->Cell(45-$o,5,$txt,'TB',0,'L',true);
			$txt = " - ";
			$this->Cell(5,5,$txt,'TB',0,'L',true);
			$txt = utf8_decode($game['pvisitorteamname']);
			$this->Cell(45-$o,5,$txt,'TB',0,'L',true);
			$this->SetFont('Arial','',8);
		}
		if($pool){
			$txt = utf8_decode(U_($game['seriesname']));
			$this->Cell(20,5,$txt,'TB',0,'L',true);
			
			$txt = utf8_decode(U_($game['poolname']));
			$this->Cell(40,5,$txt,'TB',0,'L',true);
		}

		if($result){
			if(GameHasStarted($game) && !intval($game['isongoing'])){
				$txt = intval($game['homescore']);
				$this->Cell(5,5,$txt,'TB',0,'L',true);
				$txt = " - ";
				$this->Cell(5,5,$txt,'TB',0,'L',true);
				$txt = intval($game['visitorscore']);
				$this->Cell(5,5,$txt,'TB',0,'L',true);
			}else{
				$this->SetTextColor(0);
				$this->SetFillColor(255);
				$this->SetDrawColor(0);
				$this->Cell(8,5,"",'TB',0,'L',true);
				$this->SetDrawColor(0);
				$this->SetFillColor($fillcolor['r'],$fillcolor['g'],$fillcolor['b']);
				$this->SetTextColor($textcolor['r'],$textcolor['g'],$textcolor['b']);
				$txt = " - ";
				$this->Cell(5,5,$txt,'TB',0,'L',true);
				$this->SetTextColor(0);
				$this->SetFillColor(255);
				$this->SetDrawColor(0);
				$this->Cell(8,5,"",'TB',0,'L',true);
				$this->SetDrawColor(0);
				$this->SetFillColor($fillcolor['r'],$fillcolor['g'],$fillcolor['b']);
				$this->SetTextColor($textcolor['r'],$textcolor['g'],$textcolor['b']);
			
			}
		}
		
		//fill end of the row
		$this->Cell(0,5,"",'TB',0,'L',true);
		//$this->Write(6, $txt);
		
	}
	
	function PrintSeasonPools($id) {
		$left_margin = 10;
		$top_margin = 10;
		$title = utf8_decode(SeasonName($id));
		$series = SeasonSeries($id, true);
		
		$this->SetFont('Arial','B',16);
		$this->SetTextColor(255);
		$this->SetFillColor(0);
		$this->Cell(0,9,$title,1,1,'C',true);
		
		//print all series with color coding
		foreach($series as $row){
			
			if($this->GetY()+97 > 297){
				$this->AddPage();
			}
			$name = utf8_decode(U_($row['name']));
			$this->SetFont('Arial','B',14);
			$this->SetTextColor(0);
			
			$this->Ln();
			$this->Write(6, $name);
			$this->Ln();
			$pools = SeriesPools($row['series_id'], false);
			$max_y = $this->PrintPools($pools);
			$this->SetXY($left_margin,$max_y);
		}
	}
	
	function PrintSeriesPools($id) {
		$left_margin = 10;
		$this->SetFont('Arial','B',16);
		$this->SetTextColor(255);
		$this->SetFillColor(0);
		$this->Cell(0,9,"",1,1,'C',true);
		
		if($this->GetY()+97 > 297){
			$this->AddPage();
		}
		$name = utf8_decode(U_(SeriesName($id)));
		$this->SetFont('Arial','B',14);
		$this->SetTextColor(0);
		
		$this->Ln();
		$this->Write(6, $name);
		$this->Ln();
		$pools = SeriesPools($id, false);
		$max_y = $this->PrintPools($pools);
		$this->SetXY($left_margin,$max_y);
	}
	
	function PrintPools($pools) {
		
		$left_margin = 10;
		$top_margin = 10;
		$pools_x = $left_margin;
		$pools_y = $this->GetY();
		$max_y = $this->GetY();
		$i=0;
		foreach ($pools as $pool) {
			
			$poolinfo = PoolInfo($pool['pool_id']);
			$teams = PoolTeams($pool['pool_id']);
			$scheduling_teams = false;
			
			if(!count($teams)){
				$teams = PoolSchedulingTeams($pool['pool_id']);
				$scheduling_teams = true;
			}
			$name = utf8_decode(U_($poolinfo['name']));
			
			if($i%6==0 && $i <= count($pools)){
				$this->SetXY($left_margin,$max_y);
				$max_y = $this->GetY();
				$pools_y = $this->GetY();
				$pools_x = $left_margin;
			}else{
				$this->SetXY($pools_x,$pools_y);
			}
			
			//pool header
			$fontsize=10;
			$this->SetFont('Arial','B',$fontsize);
			while($this->GetStringWidth($name)>28){
				$this->SetFont('Arial','B',--$fontsize);
			}
			
			$this->SetTextColor(0);
			$this->SetFillColor(255);
			$this->SetDrawColor(0);
			$this->Cell(30,5,$name,1,2,'C',false);
			
			//pool teams
			
			$textcolor = $this->TextColor($poolinfo['color']);
			$fillcolor = colorstring2rgb($poolinfo['color']);
			
			$this->SetDrawColor($textcolor['r'],$textcolor['g'],$textcolor['b']);
			$this->SetFillColor($fillcolor['r'],$fillcolor['g'],$fillcolor['b']);
			$this->SetTextColor($textcolor['r'],$textcolor['g'],$textcolor['b']);
			
			foreach($teams as $team){
				$txt = utf8_decode(U_($team['name']));
				$fontsize=9;
				if($scheduling_teams){
					$this->SetFont('Arial','i',$fontsize);
				}else{
					$this->SetFont('Arial','',$fontsize);
				}
				while($this->GetStringWidth($txt)>28){
					if($scheduling_teams){
						$this->SetFont('Arial','i',--$fontsize);
					}else{
						$this->SetFont('Arial','',--$fontsize);
					}
				}
				$this->Cell(30,4,$txt,'1',2,'L',true);
			}
			
			$pools_x += 31;
			if($this->GetY() > $max_y){$max_y = $this->GetY()+1;}
			$i++;	
		}
	return $max_y;
	}
	
	function PrintError($text)
		{
		$this->AddPage();
		
		$this->SetFont('Arial','',12);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->MultiCell(0,8,$text);
		}
		
	function Timeouts()
		{
		//header
		$this->HeaderColors(2);
		$this->Cell(80,6,utf8_decode(_("Time-outs")),'LRTB',0,'C',true);
		$this->Ln();
		
		//home grids
		$this->EmptyColors();
		$this->Cell(20,6,utf8_decode(_("Home")),'LRTB',0,'L',true);
		
		for($i=0;$i<4;$i++)
			{
			$this->Cell(15,6,"",'LRTB',0,'L',true);
			}
		
		$this->Ln();
		
		//visitor grids
		$this->EmptyColors();
		$this->Cell(20,6,utf8_decode(_("Away")),'LRTB',0,'L',true);
		
		for($i=0;$i<4;$i++)
			{
			$this->Cell(15,6,"",'LRTB',0,'L',true);
			}
		$this->Ln();	
		}

	function FirstOffence()
		{
		//header
		$this->HeaderColors(2);
		$this->Cell(80,6,utf8_decode(_("Starting offensive team")),'LRTB',0,'C',true);
		$this->Ln();
		
		//home grids
		$this->EmptyColors();
		$this->Cell(10,6,"",'LRTB',0,'L',true);
		$this->Cell(70,6,$this->game['hometeamname'],'LRTB',0,'L',true);
		$this->Ln();
		
		//visitor grids
		$this->EmptyColors();
		$this->Cell(10,6,"",'LRTB',0,'L',true);
		$this->Cell(70,6,$this->game['visitorteamname'],'LRTB',0,'L',true);
		$this->Ln();	
		}
		
	function SpiritPoints()
		{
		//header
		$this->HeaderColors(2);
		$this->Cell(80,6,utf8_decode(_("Spirit points")),'LRTB',0,'C',true);
		$this->Ln();
		$this->EmptyColors();
		while($this->GetStringWidth($this->game['hometeamname'])>38){
			$this->EmptyColors(4);
		}
		$this->Cell(40,6,$this->game['hometeamname'],'LRT',0,'C',true);

		
		$this->EmptyColors();
		while($this->GetStringWidth($this->game['visitorteamname'])>38){
			$this->EmptyColors(4);
		}
		$this->Cell(40,6,$this->game['visitorteamname'],'LRT',0,'C',true);

		$this->Ln();
		$this->EmptyColors();
		$this->Cell(40,6,"",'LRB',0,'C',true);
		$this->Cell(40,6,"",'LRB',0,'C',true);
		$this->Ln();
		
		}
		
	function Signatures()
		{
		//$this->Ln();
		//header
		$this->HeaderColors(2);
		$this->Cell(80,6,utf8_decode(_("Captains' signatures")),'LRTB',0,'C',true);
		$this->Ln();
		
		//home grids
		$this->EmptyColors();
		$this->Cell(15,8,utf8_decode(_("Home")),'LRTB',0,'L',true);
		$this->Cell(65,8,"",'LRTB',0,'L',true);
		
		$this->Ln();
		
		//visitor grids
		$this->EmptyColors();
		$this->Cell(15,8,utf8_decode(_("Away")),'LRTB',0,'L',true);
		$this->Cell(65,8,"",'LRTB',0,'L',true);
		$this->Ln();	
		}

	function ScoreGrid()
		{
		$this->PreFilledColors(4);
		$this->SetX(100);
		$this->Cell(20,4,utf8_decode(_("Scoring team")),'LRT',0,'C',true);
		$this->Cell(30,4,utf8_decode(_("Jersey numbers")),'LRT',0,'C',true);
		$this->Ln();
		$this->SetX(100);
		$this->PreFilledColors(3);
		$this->Cell(10,6,utf8_decode(_("Home")),'LRB',0,'C',true);
		$this->Cell(10,6,utf8_decode(_("Away")),'LRB',0,'C',true);
		$this->Cell(15,6,utf8_decode(_("Assist")),'LRB',0,'C',true);
		$this->Cell(15,6,utf8_decode(_("Goal")),'LRB',0,'C',true);
		$this->Cell(25,6,utf8_decode(_("Time")),'LRTB',0,'C',true);
		$this->Cell(25,6,utf8_decode(_("Scores")),'LRTB',0,'C',true);
		$this->Ln();
		$this->EmptyColors();
		for($i=1;$i<41;$i++)
			{
			$this->SetX(95);
			$this->SetFont('Arial','',8);
			$this->Cell(5,6,$i,'',0,'C',true);
			$this->SetFont('Arial','',10);
			$this->Cell(10,6,"",'LRTB',0,'C',true);
			$this->Cell(10,6,"",'LRTB',0,'C',true);
			$this->Cell(15,6,"",'LRTB',0,'C',true);
			$this->Cell(15,6,"",'LRTB',0,'C',true);
			$this->Cell(25,6,"",'LRTB',0,'C',true);
			$this->Cell(25,6,"-",'LRTB',0,'C',true);
			$this->Ln();
			}
		}
	function DefenseGrid()
		{
		$this->EmptyColors(4);
		//$this->SetX(100);
		//$this->Cell(24,4,utf8_decode(_("Scoring team")),'LRT',0,'C',true);
		//$this->Cell(30,4,utf8_decode(_("Jersey numbers")),'LRT',0,'C',true);
		//$this->Ln();
		$this->SetX(50);
		$this->EmptyColors(3);
		$this->Cell(12,6,utf8_decode(_("Home")),'LRTB',0,'C',true);
		$this->Cell(12,6,utf8_decode(_("Away")),'LRTB',0,'C',true);
		$this->Cell(15,6,utf8_decode(_("Player")),'LRTB',0,'C',true);
		$this->Cell(15,6,utf8_decode(_("Touched")),'LRTB',0,'C',true);
		$this->Cell(15,6,utf8_decode(_("Caught")),'LRTB',0,'C',true);
		$this->Cell(15,6,utf8_decode(_("Callahan")),'LRTB',0,'C',true);
		$this->Cell(25,6,utf8_decode(_("Time")),'LRTB',0,'C',true);
		$this->Ln();
		$this->EmptyColors();
		for($i=1;$i<31;$i++)
			{
			$this->SetX(45);
			$this->EmptyColors(4);
			$this->Cell(5,6,$i,'',0,'C',true);
			$this->EmptyColors(3);
			$this->Cell(12,6,"",'LRTB',0,'C',true);
			$this->Cell(12,6,"",'LRTB',0,'C',true);
			$this->Cell(15,6,"",'LRTB',0,'C',true);
			$this->Cell(15,6,"",'LRTB',0,'C',true);
			$this->Cell(15,6,"",'LRTB',0,'C',true);
			$this->Cell(15,6,"",'LRTB',0,'C',true);
			$this->Cell(25,6,"",'LRTB',0,'C',true);
			$this->Ln();
			}
		}
	function FinalScoreTable()
		{
		//header
		$this->HeaderColors(2);
		$this->Cell(80,6,utf8_decode(_("Final score")),'LRTB',0,'C',true);
		$this->Ln();
		
		//data
		$this->PreFilledColors(3);
		$fontsize = 4;
		while ($this->GetStringWidth($this->game['hometeamname']) > 36 && $fontsize > -5) {
			$this->PreFilledColors($fontsize--);
		}
		
		$this->Cell(38, 6, $this->game['hometeamname'], 'LT', 0, 'C', true);
		$this->Cell(4, 6, "-", 'T', 0, 'C', true);
		
		$this->PreFilledColors(3);
		$fontsize = 4;
		while ($this->GetStringWidth($this->game['visitorteamname']) > 36 && $fontsize > -5) {
			$this->PreFilledColors($fontsize--);
		}
		$this->Cell(38,6,$this->game['visitorteamname'],'RT',0,'C',true);

		$this->EmptyColors();
		$this->Ln();
		$this->Cell(80,12,"",'LRB',0,'C',true);
		$this->Ln();
		}
		

	function HeaderColors($size=2)
		{
		if ($size == 1) 
		  $this->SetFont('Arial','B',16);
		elseif ($size == 2) 
		  $this->SetFont('Arial','B',12);
		elseif ($size == 3) 
		  $this->SetFont('Arial','B',10);
		else 
		  $this->SetFont('Arial','B',8);
		$this->SetTextColor(255);
		$this->SetFillColor(127,127,127);
		}
		
	function EmptyColors($size=3, $family=NULL, $style=NULL)
		{
		if ($family == NULL)
			$family = 'Arial';
		if ($style == NULL)
			$style = 'B';
		if ($size == 1) 
		  $this->SetFont($family,$style,14);
		elseif ($size == 2) 
		  $this->SetFont($family,$style,12);
		elseif ($size == 3) 
		  $this->SetFont($family,$style,10);
		else 
		  $this->SetFont($family,$style,8);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		}

	function PreFilledColors($size=3, $family=NULL, $style=NULL)
		{
		if ($family == NULL)
			$family = 'Arial';
		if ($style == NULL)
			$style = 'B';
		
		if ($size == 1) 
		  $this->SetFont($family,$style,14);
		elseif ($size == 2) 
		  $this->SetFont($family,$style,12);
		elseif ($size == 3) 
		  $this->SetFont($family,$style,10);
		elseif ($size < 1) 
		  $this->SetFont($family,$style,8+$size);
		else 
		  $this->SetFont($family,$style,8);
		$this->SetTextColor(0);
		$this->SetFillColor(211,211,211);
		}
			
		
	function OneCellTable($header,$data, $mode=NULL)
		{
		//header
		$this->HeaderColors();
		$this->Cell(80,6,$header,'LRTB',0,'C',true);
		$this->Ln();
		
		//data
		if ($mode == "fixed")
			$this->PreFilledColors();
		elseif ($mode == "empty")
			$this->EmptyColors();
		elseif ($data == "")
			$this->EmptyColors();
		else
			$this->PreFilledColors();			
		$this->Cell ( 80, 6, $data, 'LRTB', 0, 'C', true );
		$this->Ln();
		}

	function DoubleCellTable($header,$data)
		{
		//header
		$this->SetFont('Arial','B',12);
		$this->HeaderColors();
		$this->Cell(80,6,$header,'LRTB',0,'C',true);
		$this->Ln();
		
		//data
		$this->SetFont('Arial','B',12);
		$this->PreFilledColors();
		$this->Cell(80,12,$data,'LRTB',0,'C',true);
		$this->Ln();
		}

		
	function WriteHTML($html)
		{
		//HTML parser
		$html=str_replace("\n",' ',$html);
		$a=preg_split('/<(.*)>/U',$html,-1,PREG_SPLIT_DELIM_CAPTURE);
		foreach($a as $i=>$e)
			{
			if($i%2==0)
				{
				//Text
				if($this->HREF)
					$this->PutLink($this->HREF,$e);
				else
					$this->Write(4,$e);
				}
			else
				{
				//Tag
				if($e[0]=='/')
					$this->CloseTag(strtoupper(substr($e,1)));
				else
					{
					//Extract attributes
					$a2=explode(' ',$e);
					$tag=strtoupper(array_shift($a2));
					$attr=array();
					foreach($a2 as $v)
						{
						if(preg_match('/([^=]*)=["\']?([^"\']*)/',$v,$a3))
							$attr[strtoupper($a3[1])]=$a3[2];
						}
					$this->OpenTag($tag,$attr);
					}
				}
			}
		}

	function OpenTag($tag,$attr)
		{
		//Opening tag
		if($tag=='B' || $tag=='I' || $tag=='U')
			$this->SetStyle($tag,true);
		if($tag=='A')
			$this->HREF=$attr['HREF'];
		if($tag=='BR')
			$this->Ln(5);
		}

	function CloseTag($tag)
		{
		//Closing tag
		if($tag=='B' || $tag=='I' || $tag=='U')
			$this->SetStyle($tag,false);
		if($tag=='A')
			$this->HREF='';
		}
	
	function SetStyle($tag,$enable)
		{
		//Modify style and select corresponding font
		$this->$tag+=($enable ? 1 : -1);
		$style='';
		foreach(array('B','I','U') as $s)
			{
			if($this->$s>0)
				$style.=$s;
			}
		$this->SetFont('',$style);
		}

	function PutLink($URL,$txt)
		{	
		//Put a hyperlink
		$this->SetTextColor(0,0,255);
		$this->SetStyle('U',true);
		$this->Write(4,$txt,$URL);
		$this->SetStyle('U',false);
		$this->SetTextColor(0);
		}
	}
?>