<?php 
include_once 'lib/fpdf/fpdf.php';
include_once 'lib/HSVClass.php';
include_once 'lib/phpqrcode/qrlib.php';

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
		$this->game['game_id'] = $gameId."".getChkNum($gameId);
		$this->game['hometeamname'] = utf8_decode($hometeamname);
		$this->game['visitorteamname'] = utf8_decode($visitorteamname);
		$this->game['poolname'] = utf8_decode($poolname);
		$this->game['time'] = $time;
		$this->game['placename'] = utf8_decode($placename);
		
		$this->AddPage();
		
		$data = _("Finnish Flying Disc Association");
		$data .= " - ";
		$data .= _("Scoresheet"); 
		$data = utf8_decode($data); //season name already decoded
		$data .= " " . $this->game['seasonname'];
		
		$this->SetFont('Arial','B',16);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(0,9,$data,1,1,'C',true);
		
		$this->SetY(21);
		
		$this->OneCellTable(utf8_decode(_("Game #")), $this->game['game_id']);
		$this->OneCellTable(utf8_decode(_("Home team")), $this->game['hometeamname']);
		$this->OneCellTable(utf8_decode(_("Away team")), $this->game['visitorteamname']);
		$this->OneCellTable(utf8_decode(_("Division").", "._("Pool")), $this->game['poolname']);
		$this->OneCellTable(utf8_decode(_("Field")), $this->game['placename']);
		$this->OneCellTable(utf8_decode(_("Scheduled start date and time")), $this->game['time']);
		$this->OneCellTable(utf8_decode(_("Game official")), "");
		$this->Ln();

		$this->FirstOffence();
		$this->Ln();

		$this->Timeouts();
		$this->Ln();

		$this->OneCellTable(utf8_decode(_("Half time ends (time)")), "");
		$this->Ln();
		
		$this->Ln();
		$this->FinalScoreTable();
		$this->Ln();

		$this->Signatures();
		$this->Ln();
		/* Not printed, since now it is too easy to make mistakes in pool configuration (e.g. wrong scorecap).*/
		//$gameinfo = GameInfo($gameId);
		//$data = _("Time cap").": ".$gameinfo['timecap']." "._("min")."<BR>";
		//$data .= _("Game points").": ".$gameinfo['winningscore']." "._("points")."<BR>";
		//$data .= _("Point cap").": ".$gameinfo['scorecap']." "._("points")."<BR>";
		//$data = utf8_decode($data);
		//$this->SetFont('Arial','',8);
		//$this->SetTextColor(0);
		//$this->SetFillColor(255);
		//$this->WriteHTML($data);
		
		$this->SetXY(95,21);
		$this->ScoreGrid();

		//print QR-code for result URL
		$filename = UPLOAD_DIR.$this->game['game_id'] .".png";
		$url = BASEURL."/scorekeeper/?view=result&g=".$this->game['game_id'];
		QRcode::png($url, $filename, 'h', 1, 2);
		$this->Image($filename,10,252);
		unlink($filename);
		
		$this->SetY(-22);
		
		$data = _("After the match has ended, update result:") . " ". BASEURL."/scorekeeper/?view=result";
		$data = utf8_decode($data);
		$this->SetFont('Arial','',10);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->MultiCell(0,1,$data);
		}

	//Playerlist array("name"=>name, "accredited"=>accredited, "num"=>number)
	function PrintPlayerList($homeplayers, $visitorplayers)
		{
		$this->AddPage();
		
		$data = _("Finnish Flying Disc Association");
		$data .= " - ";
		$data .= _("Roster"); 
		$data .= " " . $this->game['game_id'];
		$data = utf8_decode($data);
		$this->SetFont('Arial','B',16);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(0,9,$data,1,1,'C',true);
		
		$this->SetY(21);
		
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(230);

		$this->Cell(94,8,$this->game['hometeamname'],'LRTB',0,'C',true);
		
		$this->SetFillColor(255);
		$this->Cell(2,8,"",'LR',0,'C',true); //separator
		
		$this->SetFillColor(230);
		$this->Cell(94,8,$this->game['visitorteamname'],'LRTB',0,'C',true);
		
		$this->Ln();
		$this->SetFont('Arial','',10);
		$this->Cell(8,6,"",'LRTB',0,'C',true);
		$this->Cell(56,6,utf8_decode(_("Name")),'LRTB',0,'C',true);
		$this->Cell(10,6,utf8_decode(_("Play")),'LRTB',0,'C',true);
		$this->Cell(10,6,utf8_decode(_("#")),'LRTB',0,'C',true);
		$this->Cell(10,6,utf8_decode(_("Info")),'LRTB',0,'C',true);
		
		$this->SetFillColor(255);
		$this->Cell(2,6,"",'LR',0,'C',true); //separator
		
		$this->SetFillColor(230);
		$this->Cell(8,6,"",'LRTB',0,'C',true);
		$this->Cell(56,6,utf8_decode(_("Name")),'LRTB',0,'C',true);
		$this->Cell(10,6,utf8_decode(_("Play")),'LRTB',0,'C',true);
		$this->Cell(10,6,utf8_decode(_("#")),'LRTB',0,'C',true);
		$this->Cell(10,6,utf8_decode(_("Info")),'LRTB',0,'C',true);

		$this->Ln();
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		for($i=1;$i<26;$i++)
			{
			$hplayer = "";
			$vplayer = "";
			if(isset($homeplayers[$i-1]['name'])){
				$hplayer = utf8_decode($homeplayers[$i-1]['name']);
			}
			if(isset($visitorplayers[$i-1]['name'])){
				$vplayer = utf8_decode($visitorplayers[$i-1]['name']);
			}
			$this->SetFont('Arial','',10);
			$this->Cell(8,6,$i,'LRTB',0,'C',true);
			
			if(!empty($hplayer) && !($homeplayers[$i-1]['accredited'])){
				$this->SetFont('Arial','IB',10);
			}
			
			$this->Cell(56,6,$hplayer,'LRTB',0,'L',true);
			
			$this->SetFont('Arial','',10);
			$this->Cell(10,6,"",'LRTB',0,'C',true);
			$this->Cell(10,6,"",'LRTB',0,'C',true);
			$this->Cell(10,6,"",'LRTB',0,'C',true);

			$this->Cell(2,6,"",'LR',0,'C',true); //separator
			
			$this->Cell(8,6,$i,'LRTB',0,'C',true);
			
			if(!empty($vplayer) && !($visitorplayers[$i-1]['accredited'])){
				$this->SetFont('Arial','IB',10);
			}
			$this->Cell(56,6,$vplayer,'LRTB',0,'L',true);
			
			$this->SetFont('Arial','',10);
			$this->Cell(10,6,"",'LRTB',0,'C',true);
			$this->Cell(10,6,"",'LRTB',0,'C',true);
			$this->Cell(10,6,"",'LRTB',0,'C',true);
			$this->Ln();			
			}
			
		$this->Ln();
		
		//instructions
		$data = "";
		$data .= "<b>"._("Filling instructions:")."</b><BR>";
		$data .= _("1. Mark team's captain with C letter after name.")."<BR>";
		$data .= _("2. Mark players playing with X on Play -column.")."<BR>";
		$data .= _("3. Add jersey numbers for players on # -column.")."<BR>";
		$data .= "<BR><BR>";
		//$data .= "<b>"._("NOTICE")." 1!</b> "._("For new players added, accreditation id or date of birth must be written down.")."<BR>";
		//$data .= "<b>"._("NOTICE")." 2!</b> "._("The team is responsible for the accreditation of <u>all</u> players on the list.")."<BR>";
		//$data .= "<b>"._("NOTICE")." 3! "._("<b><i>Bold italic</i></b> printed players has problems with license. They are <u>not</u> allowed to play until problems are solved (= payment recipe or note from organizer shown).")."";
		$data = utf8_decode($data);
		$this->SetFont('Arial','',10);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->WriteHTML($data);
		
		}		

  function PrintRoster($teamname, $seriesname, $poolname, $players) {
		$this->AddPage();
		
		$data = $teamname;
		$data .= " - ";
		$data .= _("Roster"); 
		$data = utf8_decode($data);
		$this->SetFont('Arial','B',16);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(0,9,$data,1,1,'C',true);
		
		$data = U_($seriesname);
		$data .= ", ";
		$data .= U_($poolname);
		$data .= ", ";
		$data .= _("Game")." #:"; 
		$data = utf8_decode($data);
		$this->SetFont('Arial','',14);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(0,6,$data,1,1,'L',true);
		
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(230);

		$this->SetFont('Arial','',10);
		$this->Cell(8,6,"",'LRTB',0,'C',true);
		$this->Cell(100,6,utf8_decode(_("Name")),'LRTB',0,'C',true);
		$this->Cell(10,6,utf8_decode(_("Play")),'LRTB',0,'C',true);
		$this->Cell(12,6,utf8_decode(_("Jersey")),'LRTB',0,'C',true);
		$this->Cell(60,6,utf8_decode(_("Info")),'LRTB',0,'C',true);
		$this->Ln();
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		for($i=1;$i<26;$i++){
			$player = "";

			if(isset($players[$i-1]['firstname'])){
				$player .= utf8_decode($players[$i-1]['firstname']);
			}
		    $player .= " ";
			if(isset($players[$i-1]['lastname'])){
				$player .= utf8_decode($players[$i-1]['lastname']);
			}
			
			$this->SetFont('Arial','',10);
			$this->Cell(8,6,$i,'LRTB',0,'C',true);
			
			if(isset($players[$i-1]['accredited']) && !($players[$i-1]['accredited'])){
				$this->SetFont('Arial','IB',10);
			}
			
			$this->Cell(100,6,$player,'LRTB',0,'L',true);
			$this->SetFont('Arial','',10);
			$this->Cell(10,6,"",'LRTB',0,'C',true);
			if(isset($players[$i-1]['num']) && $players[$i-1]['num']>=0){
			  $this->Cell(12,6,$players[$i-1]['num'],'LRTB',0,'C',true);
			}else{
			  $this->Cell(12,6,"",'LRTB',0,'C',true);
			}
			$this->Cell(60,6,"",'LRTB',0,'C',true);

			$this->Ln();			
			}
			
		$this->Ln();
		
		//instructions
		$data="";
		//$data = "<b>"._("NOTICE")." 1!</b> "._("For new players added, accreditation id or date of birth must be written down.")."<BR>";
		//$data .= "<b>"._("NOTICE")." 2!</b> "._("The team is responsible for the accreditation of <u>all</u> players on the list.")."<BR>";
		//$data .= "<b>"._("NOTICE")." 3! "._("<b><i>Bold italic</i></b> printed players has problems with license. They are <u>not</u> allowed to play until problems are solved (= payment recipe or note from organizer shown).")."";
		$data = utf8_decode($data);
		$this->SetFont('Arial','',10);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
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
		$top_margin = 15;
		$xarea = 400;
		$yarea = 270;
		$yfieldtitle = 5;
		$xtimetitle = 20;
		
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
		$gridy = 20;
		$fieldlimit = 15;
			
		$this->SetTextColor(255);
		$this->SetFillColor(0);
		$this->SetDrawColor(0);
		//print all games in order
		while($game = mysqli_fetch_assoc($games)){
			
			//one reservation group per page
			if((!empty($game['place_id']) && $game['reservationgroup'] != $prevTournament) 
				|| (!empty($prevDate) && JustDate($game['starttime']) != $prevDate)) {
				$this->AddPage("L","A3");
				$times = TimetableTimeslots($game['reservationgroup'],$id);
				$timeslots = array();
				$tmptimes = array();
				$i=0;
 
				foreach($times as $time){

					if(strtotime(JustDate($time['time'])) > strtotime(JustDate($game['starttime']))){
						break;
					}
					if(strtotime(JustDate($time['time'])) < strtotime(JustDate($game['starttime']))){
						continue;
					}
					
					$timeslots[$time['time']] = $i*20;
					$tmptimes[]=$time;
					$i++;
				}
				$times=$tmptimes;
				$fieldstotal = TimetableFields($game['reservationgroup'],$id);
				$fieldlimit = max($fieldstotal/2+1,10);
				$gridx = $xarea/$fieldlimit;
				$field = 0;
				$prevField = "";
				$time_offset = $top_margin+$yfieldtitle;
			}
			
			//next field
			if(!empty($game['place_id']) && $game['fieldname'] != $prevField){
				$field++;

				if($field >= $fieldlimit){
					$field=1;
					$time_offset = $yarea/2+$top_margin+2*$yfieldtitle;
				}
				//write times
				if($field==1){
					$this->SetFont('Arial','B',10);
					$this->SetTextColor(0);
					$this->SetXY($left_margin,$time_offset);
				
					//write times
					foreach($times as $time){
						$this->Cell($xtimetitle,$gridy/4,"",0,2,'L',false);
						$txt = utf8_decode(ShortDate($time['time']));
						$this->Cell($xtimetitle,$gridy/4,$txt,0,2,'L',false);
						$txt = utf8_decode(DefHourFormat($time['time']));
						$this->Cell($xtimetitle,$gridy/4,$txt,0,2,'L',false);
						$this->Cell($xtimetitle,$gridy/4,"",0,2,'L',false);
					}
				}
				
				$field_offset = $left_margin+($field-1)*$gridx+$xtimetitle;
				$this->SetXY($field_offset,$time_offset-$yfieldtitle);
								
				$this->SetFont('Arial','B',12);
				$this->SetTextColor(0);
				$txt = utf8_decode(_("Field")." ".$game['fieldname']);
				$this->Cell($gridx,$yfieldtitle,$txt,"LR",2,'C',false);
				//write grids
				foreach($times as $time){
					$this->Cell($gridx,$gridy,"",1,2,'L',false);
				}
			}
			
			$slot = $game['time'];
			$this->SetXY($field_offset,$time_offset+$timeslots[$slot]);
			
			$this->SetTextColor(0);
			$this->SetFillColor(255);
			$this->SetDrawColor(0);
			$this->SetFont('Arial','',8);
			$this->SetTextColor(0);
			$this->Cell($gridx,1,"",0,2,'L',false);
			if($game['hometeam'] && $game['visitorteam']){
				$txt = utf8_decode($game['hometeamname']);
				$this->DynSetTeamFont($txt,$gridx,8);
				$this->Cell($gridx,4,$txt,0,2,'L',false);
				$txt = utf8_decode($game['visitorteamname']);
				$this->DynSetTeamFont($txt,$gridx,8);
				$this->Cell($gridx,4,$txt,0,2,'L',false);
			}else{
				$txt = utf8_decode(U_($game['phometeamname']));
				$this->DynSetTeamFont($txt,$gridx,8);
				$this->Cell($gridx,4,$txt,0,2,'L',false);
				$txt = utf8_decode(U_($game['pvisitorteamname']));
				$this->DynSetTeamFont($txt,$gridx,8);
				$this->Cell($gridx,4,$txt,0,2,'L',false);
			}
			$this->SetFont('Arial','',8);
			
			if($colors){
				$textcolor = $this->TextColor($game['color']);
				$fillcolor = colorstring2rgb($game['color']);
			
				$this->SetDrawColor($textcolor['r'],$textcolor['g'],$textcolor['b']);
				$this->SetFillColor($fillcolor['r'],$fillcolor['g'],$fillcolor['b']);
				$this->SetTextColor($textcolor['r'],$textcolor['g'],$textcolor['b']);
			}else{
				$this->SetTextColor(0);
				$this->SetFillColor(255);
				$this->SetDrawColor(0);
			}
			
			$this->Cell($gridx,1,"",0,2,'L',$colors);
			$txt = utf8_decode($game['seriesname']);
			if(strlen($game['poolname'])<15){
				$txt .= ", \n";
			}else{
				$txt .= ", ";
			}
			$txt .= utf8_decode($game['poolname']);
			//$this->DynSetFont($txt,$gridx,8);
			$this->MultiCell($gridx,3,$txt,"LR",2,'L',$colors);
			
			$this->SetXY($field_offset,$time_offset+$timeslots[$slot]);
			$this->Cell($gridx,$gridy,"","LRBT",2,'L',false);			
			
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
	
	function DynSetTeamFont($text, $x, $fontsize){
		$this->SetFont('Arial','B',$fontsize);
		while($this->GetStringWidth($text)>$x-2){
			$this->SetFont('Arial','',--$fontsize);
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
		
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		
		if($field){
			$txt = utf8_decode(U_($info['fieldname']));
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
			$txt = utf8_decode(U_($game['phometeamname']));
			$this->Cell(45-$o,5,$txt,'TB',0,'L',true);
			$txt = " - ";
			$this->Cell(5,5,$txt,'TB',0,'L',true);
			$txt = utf8_decode(U_($game['pvisitorteamname']));
			$this->Cell(45-$o,5,$txt,'TB',0,'L',true);
			$this->SetFont('Arial','',8);
		}
		if($pool){
		  	$this->SetFillColor($fillcolor['r'],$fillcolor['g'],$fillcolor['b']);
		    $this->SetTextColor($textcolor['r'],$textcolor['g'],$textcolor['b']);
			$txt = utf8_decode(U_($game['seriesname']));
			$this->Cell(20,5,$txt,'TB',0,'L',true);

			$txt = utf8_decode(U_($game['poolname']));
			$this->Cell(40,5,$txt,'TB',0,'L',true);
			$this->SetTextColor(0);
		    $this->SetFillColor(255);
		}

		if($result){
			if(GameHasStarted($game) &&  !intval($game['isongoing'])){
				$txt = intval($game['homescore']);
				$this->Cell(5,5,$txt,'TB',0,'L',true);
				$txt = " - ";
				$this->Cell(5,5,$txt,'TB',0,'L',true);
				$txt = intval($game['visitorscore']);
				$this->Cell(5,5,$txt,'TB',0,'L',true);
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
		
		$this->SetFont('Arial','B',16);
		$this->SetTextColor(255);
		$this->SetFillColor(0);
		$this->Cell(0,9,$title,1,1,'C',true);
		
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
				$fontsize=10;
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
				$this->Cell(30,5,$txt,'1',2,'L',true);
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
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(80,6,utf8_decode(_("Time-outs (time)")),'LRTB',0,'C',true);
		$this->Ln();
		
		$this->SetFont('Arial','',12);
		
		//home grids
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(20,6,utf8_decode(_("Home")),'LRTB',0,'L',true);
		
		for($i=0;$i<4;$i++)
			{
			$this->Cell(15,6,"",'LRTB',0,'L',true);
			}
		
		$this->Ln();
		
		//visitor grids
		$this->SetTextColor(0);
		$this->SetFillColor(255);
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
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(80,6,utf8_decode(_("First Offence")),'LRTB',0,'C',true);
		$this->Ln();
		
		$this->SetFont('Arial','',12);
		
		//home grids
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(10,6,"",'LRTB',0,'L',true);
		$this->Cell(70,6,$this->game['hometeamname'],'LRTB',0,'L',true);
		$this->Ln();
		
		//visitor grids
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(10,6,"",'LRTB',0,'L',true);
		$this->Cell(70,6,$this->game['visitorteamname'],'LRTB',0,'L',true);
		$this->Ln();	
		}
		
	function SpiritPoints()
		{
		//header
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(255);
		$this->SetFillColor(0,102,153);
		$this->Cell(80,6,utf8_decode(_("Spirit points")),'LRTB',0,'C',true);
		$this->Ln();
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$fontsize=10;
		$this->SetFont('Arial','B',$fontsize);
		while($this->GetStringWidth($this->game['hometeamname'])>38){
			$this->SetFont('Arial','B',--$fontsize);
		}
		$this->Cell(40,6,$this->game['hometeamname'],'LRT',0,'C',true);

		
		$fontsize=10;
		$this->SetFont('Arial','B',$fontsize);
		while($this->GetStringWidth($this->game['visitorteamname'])>38){
			$this->SetFont('Arial','B',--$fontsize);
		}
		$this->Cell(40,6,$this->game['visitorteamname'],'LRT',0,'C',true);

		$this->Ln();
		$this->SetFont('Arial','B',12);
		$this->Cell(40,6,"",'LRB',0,'C',true);
		$this->Cell(40,6,"",'LRB',0,'C',true);
		$this->Ln();
		
		}
	function Signatures()
		{
		$this->Ln();
		$this->Ln();
		//header
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(80,6,utf8_decode(_("Captains' signatures")),'LRTB',0,'C',true);
		$this->Ln();
		
		$this->SetFont('Arial','',12);
		
		//home grids
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(20,6,utf8_decode(_("Home")),'LRTB',0,'L',true);
		$this->Cell(60,6,"",'LRTB',0,'L',true);
		
		$this->Ln();
		
		//visitor grids
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(20,6,utf8_decode(_("Away")),'LRTB',0,'L',true);
		$this->Cell(60,6,"",'LRTB',0,'L',true);
		$this->Ln();	
		}

	function ScoreGrid()
		{
		$this->SetFont('Arial','',8);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->SetX(100);
		$this->Cell(24,4,utf8_decode(_("Scoring team")),'LRT',0,'C',true);
		$this->Cell(30,4,utf8_decode(_("Jersey numbers")),'LRT',0,'C',true);
		$this->Ln();
		$this->SetX(100);
		$this->SetFont('Arial','',10);
		$this->Cell(12,6,utf8_decode(_("Home")),'LRB',0,'C',true);
		$this->Cell(12,6,utf8_decode(_("Away")),'LRB',0,'C',true);
		$this->Cell(15,6,utf8_decode(_("Assist")),'LRB',0,'C',true);
		$this->Cell(15,6,utf8_decode(_("Goal")),'LRB',0,'C',true);
		$this->Cell(25,6,utf8_decode(_("Time")),'LRTB',0,'C',true);
		$this->Cell(21,6,utf8_decode(_("Scores")),'LRTB',0,'C',true);
		$this->Ln();
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		for($i=1;$i<41;$i++)
			{
			$this->SetX(95);
			$this->SetFont('Arial','',8);
			$this->Cell(5,6,$i,'',0,'C',true);
			$this->SetFont('Arial','',10);
			$this->Cell(12,6,"",'LRTB',0,'C',true);
			$this->Cell(12,6,"",'LRTB',0,'C',true);
			$this->Cell(15,6,"",'LRTB',0,'C',true);
			$this->Cell(15,6,"",'LRTB',0,'C',true);
			$this->Cell(25,6,"",'LRTB',0,'C',true);
			$this->Cell(21,6,"-",'LRTB',0,'C',true);
			$this->Ln();
			}
		}
	
	function FinalScoreTable()
		{
		//header
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(80,6,utf8_decode(_("Final score")),'LRTB',0,'C',true);
		$this->Ln();
		
		//data
		$this->SetTextColor(0);
		$this->SetFillColor(255);

		$fontsize=12;
		$this->SetFont('Arial','B',$fontsize);
		while($this->GetStringWidth($this->game['hometeamname'])>36){
			$this->SetFont('Arial','B',--$fontsize);
		}
		
		$this->Cell(38,6,$this->game['hometeamname'],'LTB',0,'C',true);
		$this->Cell(4,6,"-",'TB',0,'C',true);
		
		$fontsize=12;
		$this->SetFont('Arial','B',$fontsize);
		while($this->GetStringWidth($this->game['visitorteamname'])>36){
			$this->SetFont('Arial','B',--$fontsize);
		}
		$this->Cell(38,6,$this->game['visitorteamname'],'RTB',0,'C',true);

		$this->SetFont('Arial','B',12);
		$this->Ln();
		$this->Cell(80,6,"-",'LRTB',0,'C',true);
		$this->Ln();
		}
		
	function OneCellTable($header,$data)
		{
		//header
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(80,6,$header,'LRTB',0,'C',true);
		$this->Ln();
		
		//data
		$this->SetFont('Arial','',12);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(80,6,$data,'LRTB',0,'C',true);
		$this->Ln();
		}

	function DoubleCellTable($header,$data)
		{
		//header
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(80,6,$header,'LRTB',0,'C',true);
		$this->Ln();
		
		//data
		$this->SetFont('Arial','',12);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
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
