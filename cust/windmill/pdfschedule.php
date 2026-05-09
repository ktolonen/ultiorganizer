<?php 
require_once __DIR__ . '/../include_only.guard.php';
denyDirectCustomizationAccess(__FILE__);

if (!isset($include_prefix)) {
  $include_prefix = __DIR__ . '/../../';
}

include_once $include_prefix . 'lib/tfpdf/tfpdf.php';
include_once $include_prefix . 'lib/hsvclass/HSVClass.php';

class PDF extends tFPDF
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

		function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
			{
			parent::__construct($orientation, $unit, $size);
			$this->AddFont('Arial', '', 'DejaVuSansCondensed.ttf', true);
			$this->AddFont('Arial', 'B', 'DejaVuSansCondensed-Bold.ttf', true);
			$this->AddFont('Arial', 'I', 'DejaVuSansCondensed-Oblique.ttf', true);
			$this->AddFont('Arial', 'BI', 'DejaVuSansCondensed-BoldOblique.ttf', true);
			}
		private function pdfText($text)
			{
			return (string)$text;
			}

		private function onePageScheduleFieldKey($game)
			{
			$field = trim((string)($game['fieldname'] ?? ''));
			$location = "";
			if (!empty($game['place_id'])) {
				$location = "id:" . $game['place_id'];
			} elseif (trim((string)($game['placename'] ?? '')) !== '') {
				$location = "name:" . trim((string)$game['placename']);
			}
			return $location . "|" . $field;
			}

		private function onePageScheduleFitCell($width, $height, $text, $border, $ln, $align, $style, $maxSize, $fill = true, $minSize = 6)
			{
			$text = $this->pdfText($text);
			$fontsize = $maxSize;
			$this->SetFont('Arial', $style, $fontsize);
			while ($this->GetStringWidth($text) > $width - 2 && $fontsize > $minSize) {
				$this->SetFont('Arial', $style, --$fontsize);
			}
			$this->Cell($width, $height, $text, $border, $ln, $align, $fill);
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
		foreach($games as $game){
			$reservationGroup = isset($game['reservationgroup']) ? $game['reservationgroup'] : "";
			$placeId = isset($game['place_id']) ? $game['place_id'] : "";
			$placeName = trim((string)($game['placename'] ?? ''));
			$fieldName = trim((string)($game['fieldname'] ?? ''));
			$gameDate = !empty($game['starttime']) ? JustDate($game['starttime']) : "";
			
			if($reservationGroup !== "" && $reservationGroup != $prevTournament) {
				$txt = $this->pdfText(U_($reservationGroup));
				$this->SetFont('Arial','B',12);
				$this->SetTextColor(0);
				$this->Ln();
				$this->Write(5, $txt);
				$this->Ln();
				$prevDate="";
			}	

			if($gameDate !== "" && $gameDate != $prevDate){
				$txt = DefWeekDateFormat($game['starttime']);
				$this->SetFont('Arial','B',10);
				$this->SetTextColor(0);
				$this->Ln();
				$this->Write(5, $txt);
			}
			
			if(($placeName !== "" || $fieldName !== "") && ($placeId != $prevPlace || $fieldName != $prevField || $gameDate != $prevDate)){
				$txt = "";
				if ($placeName !== "") {
					$txt = U_($placeName);
				}
				if ($fieldName !== "") {
					$txt .= ($txt !== "" ? " " : "") . _("Field") . " " . U_($fieldName);
				}
				$txt = $this->pdfText($txt);
				
				$this->SetFont('Arial','',10);
				$this->SetTextColor(0);
				$this->Ln();
				$this->Cell(0,5,$txt,0,2,'L',false);
			}
			if($placeName !== "" || $fieldName !== ""){
				$this->GameRowWithPool($game, false, true, false);
			}else{
				$this->GameRowWithPool($game, false, true, true);
			}
			if($reservationGroup !== "" || $gameDate !== ""){
				$this->Ln();				
			}
			
			$prevTournament = $reservationGroup;
			$prevPlace = $placeId;
			$prevField = $fieldName;
			$prevSeries = isset($game['series_id']) ? $game['series_id'] : "";
			$prevPool = isset($game['pool']) ? $game['pool'] : "";
			$prevDate = $gameDate;
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
		$gridy = 20;
		$fieldlimit = 15;
			
		$this->SetTextColor(255);
		$this->SetFillColor(0);
		$this->SetDrawColor(0);
		//print all games in order
		foreach($games as $game){
			if (trim((string)($game['fieldname'] ?? '')) === '' || empty($game['time'])) {
				continue;
			}
			
			//one reservation group per page
			if($game['reservationgroup'] != $prevTournament || $prevDate != JustDate($game['starttime'])) {
				$this->AddPage("L","A3");
				
				$title = $this->pdfText(SeasonName($id));
				$title .= " ".$this->pdfText($game['reservationgroup']);
				$title .= " (".$this->pdfText(ShortDate($game['starttime'])).")";
				$this->SetFont('Arial','BU',12);
				$this->SetTextColor(0);
				$this->Cell(0,0,$title,0,2,'C',false);
				
				$times = TimetableTimeslots($game['reservationgroup'],$id);
				$timeslots = array();
				$i=0;
				foreach($times as $time){
					$timeslots[DefHourFormat($time['time'])] = $i*20;
					$i++;
				}
				
				$fieldstotal = TimetableFields($game['reservationgroup'],$id);
				$fieldlimit = max($fieldstotal/2+1,10);
				$gridx = $xarea/$fieldlimit;
				$field = 0;
				$prevField = "";
				$time_offset = $top_margin+$yfieldtitle+$ypagetitle+(($yarea/2-count($timeslots)*$gridy)/2);
			}
			
			//next field
			$fieldKey = $this->onePageScheduleFieldKey($game);
			if($fieldKey != $prevField){
				$field++;

				if($field >= $fieldlimit){
					$field=1;
					$time_offset = $yarea/2+$top_margin+2*$yfieldtitle+$ypagetitle;
				}
				//write times
				if($field==1){
					$this->SetFont('Arial','B',10);
					$this->SetTextColor(0);
					$this->SetXY($left_margin,$time_offset);
				
					//write times
					foreach($times as $time){
						$txt = $this->pdfText(DefHourFormat($time['time']));
						$this->Cell($xtimetitle,$gridy,$txt,0,2,'L',false);
					}
				}
				
				$field_offset = $left_margin+($field-1)*$gridx+$xtimetitle;
				$this->SetXY($field_offset,$time_offset-$yfieldtitle);
								
				$this->SetFont('Arial','B',10);
				$this->SetTextColor(0);
				$this->SetFillColor(230);
				$fieldTitle = $this->pdfText(_("Field")." ".$game['fieldname']);
				$placeTitle = $this->pdfText($game['placename'] ?? '');
				if ($placeTitle !== '') {
					$this->onePageScheduleFitCell($gridx,$yfieldtitle/2,$fieldTitle,"LRT",2,'C','B',10,true);
					$this->SetFont('Arial','',8);
					$this->SetTextColor(0);
					$this->onePageScheduleFitCell($gridx,$yfieldtitle/2,$placeTitle,"LR",2,'C','',8,true);
				} else {
					$this->onePageScheduleFitCell($gridx,$yfieldtitle,$fieldTitle,"LRT",2,'C','B',10,true);
				}
				//write grids
				foreach($times as $time){
					$this->Cell($gridx,$gridy,"",1,2,'L',false);
				}
			}
			
			$slot = DefHourFormat($game['time']);
			if (!isset($timeslots[$slot])) {
				continue;
			}
			$this->SetXY($field_offset,$time_offset+$timeslots[$slot]);
			
			$this->SetTextColor(0);
			$this->SetFillColor(255);
			$this->SetDrawColor(0);
			$this->SetFont('Arial','',$teamfont);
			$this->SetTextColor(0);
			$this->Cell($gridx,1,"",0,2,'L',false);
			if($game['hometeam'] && $game['visitorteam']){
				$txt = $this->DynSetTeamName($game['hometeamname'],$game['homeshortname'],$gridx,$teamfont);
				$this->Cell($gridx,4,$txt,0,2,'L',false);
				$txt = $this->pdfText($game['visitorteamname']);
				$txt = $this->DynSetTeamName($game['visitorteamname'],$game['visitorshortname'],$gridx,$teamfont);
				$this->Cell($gridx,4,$txt,0,2,'L',false);
			}elseif($game['gamename']){
				$txt = $this->DynSetTeamName($game['gamename'],"",$gridx,$teamfont);
				$this->Cell($gridx,8,$txt,0,2,'L',false);
			}else{
				$txt = $this->DynSetTeamName($game['phometeamname'],"",$gridx,$teamfont);
				$this->Cell($gridx,4,$txt,0,2,'L',false);
				$txt = $this->DynSetTeamName($game['pvisitorteamname'],"",$gridx,$teamfont);
				$this->Cell($gridx,4,$txt,0,2,'L',false);
			}
			$this->SetFont('Arial','',$teamfont);
			
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
			$txt = $this->pdfText($game['seriesname']);
			$txt .= ", ";
			$txt .= $this->pdfText($game['poolname']);
			//$this->DynSetFont($txt,$gridx,8);
			$this->MultiCell($gridx,4,$txt,"LR",2,'L',$colors);
			
			$this->SetTextColor(0);
			$this->SetFillColor(255);
			$this->SetDrawColor(0);
				
			$this->SetXY($field_offset,$time_offset+$timeslots[$slot]);
			$this->Cell($gridx,$gridy,"","LRBT",2,'L',false);			
			
			$prevTournament = $game['reservationgroup'];
			$prevPlace = $game['place_id'];
			$prevField = $fieldKey;
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
		$text = $this->pdfText($longname);
		if($this->GetStringWidth($text)>$x-2 && !empty($abbrev)){
			$text = $this->pdfText($abbrev);
		}
		
		while($this->GetStringWidth($text)>$x-2){
			$this->SetFont('Arial','',--$fontsize);
		}
		
		return $text;
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
			$txt = $this->pdfText(U_($game['fieldname'] ?? ''));
			$this->Cell(20,5,$txt,'TB',0,'L',true);
		}
		
		$o=0;
		if($game['gamename']){
			$this->SetFont('Arial','B',8);
			$txt = $this->pdfText(U_($game['gamename']).":");
			$this->Cell(30,5,$txt,'TB',0,'L',true);
			$o=15;
			$this->SetFont('Arial','',8);
		}
		
		if($game['hometeam'] && $game['visitorteam']){
			$txt = $this->pdfText($game['hometeamname']);
			$this->Cell(45-$o,5,$txt,'TB',0,'L',true);
			$txt = " - ";
			$this->Cell(5,5,$txt,'TB',0,'L',true);
			$txt = $this->pdfText($game['visitorteamname']);
			$this->Cell(45-$o,5,$txt,'TB',0,'L',true);
		}else{
			$this->SetFont('Arial','I',8);
			$txt = $this->pdfText($game['phometeamname']);
			$this->Cell(45-$o,5,$txt,'TB',0,'L',true);
			$txt = " - ";
			$this->Cell(5,5,$txt,'TB',0,'L',true);
			$txt = $this->pdfText($game['pvisitorteamname']);
			$this->Cell(45-$o,5,$txt,'TB',0,'L',true);
			$this->SetFont('Arial','',8);
		}
		if($pool){
			$txt = $this->pdfText(U_($game['seriesname']));
			$this->Cell(20,5,$txt,'TB',0,'L',true);
			
			$txt = $this->pdfText(U_($game['poolname']));
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
		$title = $this->pdfText(SeasonName($id));
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
			$name = $this->pdfText(U_($row['name']));
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
		$name = $this->pdfText(U_(SeriesName($id)));
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
			$name = $this->pdfText(U_($poolinfo['name']));
			
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
				$txt = $this->pdfText(U_($team['name']));
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
