<?php 
require_once __DIR__ . '/../include_only.guard.php';
denyDirectCustomizationAccess(__FILE__);

include_once 'lib/tfpdf/tfpdf.php';
include_once 'lib/tfpdf/cellfit.php';
include_once 'lib/hsvclass/HSVClass.php';

class PDF extends tFPDF_CellFit
	{
	var $B;
	var $I;
	var $U;
	var $HREF;
	var $eventLogoLg = "";
	var $eventLogoSm = "";
	var $show_note = false;
	var $gender_rule = "A";
	var $isMixed = true;
	var $homefullname = "";
	var $visitorfullname = "";
	

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
		
	function PrintScoreSheet($seasonname,$gameId,$hometeamname,$visitorteamname,$poolname,$time,$placename,$homeplayers,$visitorplayers) {
		
    // event logo file name
    $this->eventLogoLg = "cust/".CUSTOMIZATIONS."/logo-big.png";
    $this->eventLogoSm = "cust/".CUSTOMIZATIONS."/logo-small.png";

		// 
		$this->SetAutoPageBreak(false);

		$this->game['seasonname'] = $this->pdfText($seasonname);
		$this->game['game_id'] = $gameId; //."".getChkNum($gameId);
		$this->game['hometeamname'] = $this->pdfText($hometeamname);
		$this->game['visitorteamname'] = $this->pdfText($visitorteamname);
		$this->game['poolname'] = $this->pdfText($poolname);
		$this->game['time'] = (string)$time;
		$this->game['placename'] = $this->pdfText($placename);

		$this->show_note = false;

    $this->gender_rule = "A";

		// get team id's for this game
		$gameinfo = array();
		if (!empty($gameId)) {
			$gameinfo = GameInfo($gameId);
			if (!is_array($gameinfo)) {
				$gameinfo = array();
			}
		}
		$hometeamid = $gameinfo['hometeam'] ?? 0;
		$visitorteamid = $gameinfo['visitorteam'] ?? 0;

		// get team abbreviations
		$hometeaminfo = !empty($hometeamid) ? TeamInfo($hometeamid) : array();
		$visitorteaminfo = !empty($visitorteamid) ? TeamInfo($visitorteamid) : array();
		if (!is_array($hometeaminfo)) {
			$hometeaminfo = array();
		}
		if (!is_array($visitorteaminfo)) {
			$visitorteaminfo = array();
		}
		if (!($this->game['homeshortname'] = ($hometeaminfo['abbreviation'] ?? ''))) {
			$this->game['homeshortname'] = _("Home");
		}
		if (!($this->game['visitorshortname'] = ($visitorteaminfo['abbreviation'] ?? ''))) {
			$this->game['visitorshortname'] = _("Away");
		}

    // get coaches names
    /*
    $hometeamprofile = TeamProfile($hometeamid);
    $visitorteamprofile = TeamProfile($visitorteamid);
    $this->game['homecoach'] = $hometeamprofile['coach'];
    $this->game['visitorcoach'] = $visitorteamprofile['coach'];
    */

		// get reservation group
		$this->game['reservation_group'] = "";
		if (!empty($gameId)) {
			$resId = GameReservation($gameId);
			if (!empty($resId)) {
				$resinfo = ReservationInfo($resId);
				if (is_array($resinfo)) {
					$this->game['reservation_group'] = (string)($resinfo['reservationgroup'] ?? "");
				}
			}
		}

		// get time without date and remove seconds
		$this->game['timeonly'] = "";
		$timeParts = preg_split('/\s+/', trim((string)$time));
		$clock = $timeParts[1] ?? ($timeParts[0] ?? "");
		$clockParts = explode(':', $clock);
		if (isset($clockParts[1])) {
			$this->game['timeonly'] = $clockParts[0] . ":" . $clockParts[1];
		}

		// get field number
		$this->game['fieldnum'] = (string)($gameinfo['fieldname'] ?? '');

		// get division only
		$division_array = explode(',', $this->game['poolname'], 2);
		$this->game['division'] = trim($division_array[0] ?? '');
    $this->game['pool'] = trim($division_array[1] ?? '');
    
    // determine if division is mixed gender (based on division name containing the word mixed)
    $this->isMixed = stripos($this->game['division'], "mixed") !== false;
    if (empty($this->game['division'])) {
      $this->isMixed = true;
    }

    // team names
    //$this->homefullname = $hometeaminfo['abbreviation'] . " (" . $hometeaminfo['countryname'] . ")";
    //$this->visitorfullname = $visitorteaminfo['abbreviation'] . " (" . $visitorteaminfo['countryname'] . ")";
    $this->homefullname = !empty($hometeamname) ? $hometeamname : $this->game['homeshortname'];
    $this->visitorfullname = !empty($visitorteamname) ? $visitorteamname : $this->game['visitorshortname'];

    // ================
    // PAGE STARTS HERE
    // ================
		$this->AddPage();

    // print top page header
    $this->TopHeader();
    $this->Ln(3);

    // print game headers
    $this->GameHeaderA();
    $this->Ln(3);

    // print players lists on front page
		$this->PrintPlayerList($homeplayers,$visitorplayers);
    
    if ($this->show_note) {
      $this->SetFont('Arial','IB',10);
      $this->SetTextColor(0);
      $this->SetFillColor(255);
      $this->CellFitScale(100,6,_("(*) player not allowed to play (for medical or other reasons)"),0,1,'L',true);
    }
		$this->Ln();

    // scoregrid
		$this->SetXY(120,25);
		$this->ScoreGrid();
    $this->Ln(2);
    
    // second half start time
		$this->SetX(132);
		$this->HalfTime();
    $this->Ln(4);

    // timeouts
    $this->SetX(120);
		$this->Timeouts2();

    // final score and captain's signatures
    $this->SetXY(10,258);
		$this->SigFinalScore();
	
    // event logo
    if (is_file($this->eventLogoLg)) {
      $this->Image($this->eventLogoLg,140,265,50,0);
    }


    // ============
    // START PAGE 2
    // ============
    if (defined('PRINT_SPIRIT_SHEETS') && PRINT_SPIRIT_SHEETS) {
      $this->AddPage('L');

      // cutting lines
      $this->Line(149,10,149,200);

      // team names = CCC (Country name)
      //$spirithome = array($hometeaminfo['abbreviation'],$hometeaminfo['countryname']);
      //$spiritvisitor = array($visitorteaminfo['abbreviation'],$visitorteaminfo['countryname']);
      $spirithome = $hometeamname;
      $spiritvisitor = $visitorteamname;

      // print SOTG sheet for home team
      $this->SetXY(10,10);
      $this->SpiritHeader($spirithome,$spiritvisitor);
      $this->Ln(3);
      $this->SetX(10);
      $this->SpiritTable();
      $this->Ln(8);
      $this->SetX(10);
      $this->FeedbackGA();

      // print SOTG sheet for visitor team
      $this->SetXY(158,10);
      $this->SpiritHeader($spiritvisitor,$spirithome);
      $this->Ln(3);
      $this->SetX(158);
      $this->SpiritTable();
      $this->Ln(8);
      $this->SetX(158);
      $this->FeedbackGA();
    }

  }

	function PrintDefenseSheet($seasonname,$gameId,$hometeamname,$visitorteamname,$poolname,$time,$placename)
		{
		$this->game['seasonname'] = $this->pdfText($seasonname);
		$this->game['game_id'] = $gameId."".getChkNum($gameId);
		$this->game['hometeamname'] = $this->pdfText($hometeamname);
		$this->game['visitorteamname'] = $this->pdfText($visitorteamname);
		$this->game['poolname'] = $this->pdfText($poolname);
		$this->game['time'] = $time;
		$this->game['placename'] = $this->pdfText($placename);
		
		$this->SetAutoPageBreak(false);
		
		$data = _("Defences") . " " . _("for game") . " #" . $this->game['game_id']; 
		$data = $this->pdfText($data); //season name already decoded
		//$data .= " " . $this->game['seasonname'];
		
		$this->Ln();

		$this->SetFont('Arial','B',16);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(0,9,$data,1,1,'C',true);
		//$this->Ln();
		
		$this->SetY($this->GetY() + 1);
		$this->DefenseGrid();
		
		}

  // Print top header of the scoresheet
  function TopHeader() {

    // first row

		$this->SetFont('Arial','B',11);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->CellFitScale(30,6,$this->game['seasonname'],'LRT',0,'C',true);
		$this->CellFitScale(20,6,_("Day"),1,0,'C',true);
		$this->CellFitScale(16,6,_("Time"),1,0,'C',true);
		$this->CellFitScale(16,6,_("Field"),1,0,'C',true);
		$this->CellFitScale(35,6,_("Division"),1,0,'C',true);
		$this->CellFitScale(55,6,_("Pool / Bracket"),1,0,'C',true);
		$this->CellFitScale(18,6,_("Game #"),1,1,'C',true);

    // second row
    //$this->SetX($this->GetX()+30);
		$this->CellFitScale(30,6,_("Scoresheet"),'LRB',0,'C',true);
		
    $this->SetFillColor(255);
		$this->SetFont('Arial','',10);
    
    // day
		$this->CellFitScale(20,6,$this->game['reservation_group'],1,0,'C',true);
    
    // time
		$this->CellFitScale(16,6,$this->game['timeonly'],1,0,'C',true);
    
    // field number
		$this->CellFitScale(16,6,$this->game['fieldnum'],1,0,'C',true);
    
    // division
		$this->CellFitScale(35,6,$this->game['division'],1,0,'C',true);
    
    // pool
		$this->CellFitScale(55,6,$this->game['pool'],1,0,'C',true);
    
    // game ref number
		$this->SetFont('Arial','B',12);
		$this->CellFitScale(18,6,$this->game['game_id'],1,1,'C',true);
  }

  function GameHeaderB() { // gender ratio rule B (endzones decides)
    
    $topY = $this->GetY();
    $this->SetTextColor(0);
    
		// home team
		$this->SetFont('Arial','B',10);
		$this->SetFillColor(230);
    $data = $this->pdfText(_("Home"));
		$this->Cell(12,5,$data,'LRTB',0,'C',true);
		
    $this->SetFont('Arial','',10);
		$this->SetFillColor(255);
    $data = $this->homefullname . " (" . $this->game['homeshortname'] . ")";
		$this->CellFitScale(88,5,$data,'LRTB',1,'L',true);

		// away team
		$this->SetFont('Arial','B',10);
		$this->SetFillColor(230);
    $data = $this->pdfText(_("Away"));
		$this->Cell(12,5,$data,'LRTB',0,'C',true);
		
    $this->SetFont('Arial','',10);
		$this->SetFillColor(255);
    $data = $this->visitorfullname . " (" . $this->game['visitorshortname'] . ")";
		$this->CellFitScale(88,5,$data,'LRTB',1,'L',true);
    
    $offsetX = $this->GetX() + 59;
    
    // Endzones
    $this->Ln(3);
    $this->EndzonesB();
	
    $bottomY = $this->GetY();

    // scorekeepers names
    $this->SetXY($offsetX,$topY+13);
		$this->SetFont('Arial','B',10);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(41,5,$this->pdfText(_("Scorekeeper(s)")),'LRTB',1,'C',true);
    $this->SetX($offsetX);
		$this->SetFillColor(255);
		$this->Cell(41,18,'','LRTB',1,'C',true);

    // notes
    $this->Ln(4);
    $this->SetX($offsetX);
    $this->SetFont('Arial','I',10);
		$this->CellFitScale(41,5,_("* mixed only"),0,1,'L',true);

    if ($bottomY > $this->GetY())
      $this->SetY($bottomY);

  }

  // print a field for writing game starting settings (rule B)
  function EndzonesB() {
    $offset = $this->GetX();

    // header
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->SetFont('Arial','',10);
		$this->CellFitScale(56,5,_("Game starting settings").":",'B',1,'C',true);

    // first row
		$this->SetFont('Arial','',10);
		$this->Cell(11,6,"__",'LRT',0,'C',true);
		$this->SetFillColor(230);
		$this->Cell(34,6,_("Endzone")." (A/B)*",'LRT',0,'C',true);
		$this->SetFillColor(255);
		$this->Cell(11,6,"__",'LRT',1,'C',true);

    // second row
    $this->SetX($offset);
		$this->Cell(11,6,"____",'LR',0,'C',true);
		$this->SetFillColor(230);
		$this->Cell(34,6,_("Teams (abbrev.)"),'LR',0,'C',true);
		$this->SetFillColor(255);
		$this->Cell(11,6,"____",'LR',1,'C',true);

    // third row
    $this->SetX($offset);
		$this->SetFont('ZapfDingbats','',16);
		$this->Cell(11,6,"q",'LRB',0,'C',true);
		$this->SetFont('Arial','',10);
		$this->SetFillColor(230);
		$this->Cell(34,6,_("First Offence"),'LRB',0,'C',true);
		$this->SetFillColor(255);
	  $this->SetFont('ZapfDingbats','',16);
		$this->Cell(11,6,"q",'LRB',1,'C',true);

    // forth row
    $this->Ln(1); // vertical spacer
    $this->SetX($offset);
		$this->SetFont('Arial','',8);
		$this->CellFitScale(11,4,_("Endzone"),0,0,'C',true);
		$this->Cell(9,4,"",'R',0,'C',true);
    $this->SetFillColor(230);
		$this->CellFitScale(16,4,_("Scorekeepers"),1,0,'C',true);
    $this->SetFillColor(255);
		$this->Cell(9,4,"",'L',0,'C',true);
		$this->CellFitScale(11,4,_("Endzone"),0,1,'C',true);
  }


  function GameHeaderA() { // gender ratio rule A (Prescribed)
		
    $topY = $this->GetY();
    $this->SetTextColor(0);
    
		// home team
		$this->SetFont('Arial','B',10);
		$this->SetFillColor(230);
    $data = $this->pdfText(_("Home"));
		$this->Cell(12,5,$data,'LRTB',0,'C',true);
		
    $this->SetFont('Arial','',10);
		$this->SetFillColor(255);
    $data = empty($this->homefullname) ? "" : $this->homefullname . " (" . $this->game['homeshortname'] . ")";
		$this->CellFitScale(88,5,$data,'LRTB',1,'L',true);

		// away team
		$this->SetFont('Arial','B',10);
		$this->SetFillColor(230);
    $data = $this->pdfText(_("Away"));
		$this->Cell(12,5,$data,'LRTB',0,'C',true);
		
    $this->SetFont('Arial','',10);
		$this->SetFillColor(255);
    $data = empty($this->visitorfullname) ? "" : $this->visitorfullname . " (" . $this->game['visitorshortname'] . ")";
		$this->CellFitScale(88,5,$data,'LRTB',1,'L',true);
    
    // scorekeepers names
    $this->Ln(3);
		$this->SetFont('Arial','B',10);
		$this->SetFillColor(230);
		$this->CellFitScale(25,10,_("Scorekeeper(s)"),'LRTB',0,'C',true);	
		$this->SetFillColor(255);
		$this->Cell(75,10,"",'LRTB',1,'L',true);

    // Endzones
    $this->Ln(3);
    $this->EndzonesA();

    // gender ratio 1st point
    if ($this->isMixed) {
      switch (Seasontype(GetString('season'))) {
        case "indoor":
        case "beach":
          $gender1 = "3M/2F";
          $gender2 = "2M/3F";
          break;
        case "outdoor":
        default:
          $gender1 = "4M/3F";
          $gender2 = "3M/4F";
          break;
      }
      $this->Ln(5);
      $this->SetFont('Arial','',10);
      $this->CellFitScale(48,5,"* "._("Gender Ratio on 1st point").":",'R',0,'L',true);
      $this->SetFont('Arial','B',10);
      $this->CellFitScale(14,5,$gender1,1,0,'C',true);
      $this->CellFitScale(2,5,"",'LR',0,'L',true);
      $this->CellFitScale(14,5,$gender2,1,1,'C',true);
      $this->Ln(1);
      $this->SetFont('Arial','I',8);
      $this->CellFitScale(100,5,_("Points with an asterisk (*) must have the same gender ratio as the first point."),'',1,'L',true);
      //$this->CellFitScale(1,5,"",'L',0,'L',true);
      //$this->SetFont('Arial','I',10);
      //$this->CellFitScale(21,5,"("._("mixed only").")",0,1,'L',true);
    } else {
      $this->Ln(10);
    }

    $bottomY = $this->GetY();

    if ($bottomY > $this->GetY())
      $this->SetY($bottomY);

  }

  // print a field for writing game starting settings (rule A)
  function EndzonesA() {
    //$offset = $this->GetX();
    $offset = $this->GetX()+11;

    // header
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->SetFont('Arial','B',10);
		$this->CellFitScale(100,5,_("Game starting conditions"),'',1,'C',true);
    $this->Ln(3);

    // first row
    $this->SetX($offset);
		$this->SetFont('Arial','',10);
		$this->Cell(12,2,"",'LRT',0,'C',true);
		$this->SetFillColor(230);
		$this->Cell(54,2,"",'LRT',0,'C',true);
		$this->SetFillColor(255);
		$this->Cell(12,2,"",'LRT',1,'C',true);

    // second row
    $this->SetX($offset);
		$this->Cell(12,10,"_____",'LR',0,'C',true);
		$this->SetFillColor(230);
		$this->CellFitScale(54,10,_("Which team started on which side?"),'LR',0,'C',true);
		$this->SetFillColor(255);
		$this->Cell(12,10,"_____",'LR',1,'C',true);

    // third row
    $this->SetX($offset);
		$this->SetFont('ZapfDingbats','',16);
		$this->Cell(12,10,"q",'LRB',0,'C',true);
		$this->SetFont('Arial','',10);
		$this->SetFillColor(230);
		$this->CellFitScale(54,10,_("Who started on offence?"),'LRB',0,'C',true);
		$this->SetFillColor(255);
	  $this->SetFont('ZapfDingbats','',16);
		$this->Cell(12,10,"q",'LRB',1,'C',true);

    // forth row
    $this->Ln(1); // vertical spacer
    $this->SetX($offset);
		$this->SetFont('Arial','',8);
		$this->CellFitScale(12,4,_("Endzone"),0,0,'C',true);
		$this->Cell(19,4,"",'R',0,'C',true);
    $this->SetFillColor(230);
		$this->CellFitScale(16,4,_("Scorekeepers"),1,0,'C',true);
    $this->SetFillColor(255);
		$this->Cell(19,4,"",'L',0,'C',true);
		$this->CellFitScale(12,4,_("Endzone"),0,1,'C',true);
  }

	//Playerlist array("name"=>name, "accredited"=>accredited, "num"=>number)
	function PrintPlayerList($homeplayers, $visitorplayers)
		{
		$offset = $this->GetX();

		$this->SetFont('Arial','B',10);
		$this->SetTextColor(0);
		$this->SetFillColor(230);

		$this->CellFitScale(49,5,$this->homefullname,'LRTB',0,'C',true);
		
		$this->SetFillColor(255);
		$this->Cell(2,5,"",'LR',0,'C',true); //separator
		
		$this->SetFillColor(230);
		$this->CellFitScale(49,5,$this->visitorfullname,'LRTB',0,'C',true);
		
		$this->Ln();
		$this->SetX($offset);
		$this->SetFont('Arial','B',10);
		
		$this->SetFillColor(230);

		$this->SetX($offset);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		for($i=1;$i<=28;$i++) {
			$hplayer = "";
			$hnumber = "";
			$vplayer = "";
			$vnumber = "";
			
			if(isset($homeplayers[$i-1]['name'])){
				$hplayer = $homeplayers[$i-1]['name'];
				$hnumber = $homeplayers[$i-1]['num'];
			}
			if(isset($visitorplayers[$i-1]['name'])){
				$vplayer = $visitorplayers[$i-1]['name'];
				$vnumber = $visitorplayers[$i-1]['num'];
			}
			$this->SetFont('Arial','',10);
			
      //if ($i==29) { // replace this for the last one to display the coach instead
      if (false) { // not in use
		    $this->SetFillColor(230);
        $this->Cell(7,5,'C','LRTB',0,'C',true);
        $this->CellFitScale(42,5,$this->game['homecoach'],'LRTB',0,'L',true);
		    $this->SetFillColor(255);
      } else {
        
        if(!empty($hplayer) && !($homeplayers[$i-1]['accredited'])){
          $this->SetFont('Arial','IB',10);
          $hnumber = "(*)";
          $this->show_note = true;
        }
        $this->Cell(7,5,$hnumber,'LRTB',0,'C',true);
        $this->CellFitScale(42,5,$hplayer,'LRTB',0,'L',true);
			}

			$this->SetFont('Arial','',10);
			$this->Cell(2,5,"",'LR',0,'C',true); //separator
			
      //if ($i==29) { // replace this for the last one to display the coach instead
      if (false) {
		    $this->SetFillColor(230);
        $this->Cell(7,5,'C','LRTB',0,'C',true);
        $this->CellFitScale(42,5,$this->game['visitorcoach'],'LRTB',0,'L',true);
		    $this->SetFillColor(255);
      } else {
        
        if(!empty($vplayer) && !($visitorplayers[$i-1]['accredited'])){
          $this->SetFont('Arial','IB',10);
          $vnumber = "(*)";
          $this->show_note = true;
        }
        $this->Cell(7,5,$vnumber,'LRTB',0,'C',true);
        $this->CellFitScale(42,5,$vplayer,'LRTB',0,'L',true);
		  }

			$this->SetFont('Arial','',10);
			$this->Ln();			
			$this->SetX($offset);
		}
		
		$this->SetFont('Arial','',8);
		$data = _("Total number of players:")." ". count($homeplayers);
		$this->Cell(49,4,$data,'T',0,'L',true);
		$this->Cell(2,4,"",'',0,'C',true); //separator
		$data = _("Total number of players:")." ". count($visitorplayers);
		$this->Cell(49,4,$data,'T',0,'L',true);
		
		$this->Ln();

		}		

  function PrintRoster($teamname, $seriesname, $poolname, $players) {
		$this->AddPage();
		
		$data = $teamname;
		$data .= " - ";
		$data .= _("Roster"); 
		$data = $this->pdfText($data);
		$this->SetFont('Arial','B',16);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(0,9,$data,1,1,'C',true);
		
		$data = U_($seriesname);
		$data .= ", ";
		$data .= U_($poolname);
		$data .= ", ";
		$data .= _("Game")." #:"; 
		$data = $this->pdfText($data);
		$this->SetFont('Arial','',14);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(0,6,$data,1,1,'L',true);
		
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(230);

		$this->SetFont('Arial','',10);
		$this->Cell(8,6,"",'LRTB',0,'C',true);
		$this->Cell(100,6,$this->pdfText(_("Name")),'LRTB',0,'C',true);
		$this->Cell(10,6,$this->pdfText(_("Play")),'LRTB',0,'C',true);
		$this->Cell(10,6,$this->pdfText(_("Game #")),'LRTB',0,'C',true);
		$this->Cell(62,6,$this->pdfText(_("Info")),'LRTB',0,'C',true);
		$this->Ln();
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		for($i=1;$i<26;$i++){
			$player = "";

			if(isset($players[$i-1]['firstname'])){
				$player .= $this->pdfText($players[$i-1]['firstname']);
			}
		    $player .= " ";
			if(isset($players[$i-1]['lastname'])){
				$player .= $this->pdfText($players[$i-1]['lastname']);
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
		$data .= "<b>"._("NOTICE")." 3! "._("<b><i>Bold italic</i></b> printed players have problems with their license. They are <u>not</u> allowed to play until the problems are resolved (= payment receipt or note from the organizer shown).")."";
		$data = $this->pdfText($data);
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
		foreach($games as $game){
			
			if(!empty($game['place_id']) && $game['reservationgroup'] != $prevTournament) {
				$txt = $this->pdfText(U_($game['reservationgroup']));
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
				$txt = $this->pdfText($txt);
				
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
		
	function PrintOnePageSchedule($scope, $id, $games, $colors=true){
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
		$gridy = 4;
		$fieldlimit = 15;
			
		$this->SetTextColor(255);
		$this->SetFillColor(0);
		$this->SetDrawColor(0);
		//print all games in order
		foreach($games as $game){
			
			//one reservation group per page
			if(!empty($game['place_id']) && $game['reservationgroup'] != $prevTournament || $prevDate != JustDate($game['starttime'])) {
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
				$hour=9;
				$min=0;
				$slots=13*60+1;
				$timegridy=0;
				for($i=1;$i<=$slots;$i++){
				  
				  $timeslots[DefHourFormat("$hour:$min")] = $timegridy;
				  /*echo "<p>".DefHourFormat("$hour:$min")."</p>";*/
				  
				  if($i%60){
				    $min++;
				  }else{
				    $hour++;
				    $min=0;
				  }
				  if($min%8==0){
				    $timegridy++;
				  }
				  
				}
				//foreach($times as $time){
				//	$timeslots[DefHourFormat($time['time'])] = $i*20;
				//	$i++;
				//}
				
				$fieldstotal = TimetableFields($game['reservationgroup'],$id);
				$fieldlimit = max($fieldstotal/2+1,8);
				$gridx = $xarea/$fieldlimit;
				$field = 0;
				$prevField = "";
				$time_offset = $top_margin+$yfieldtitle+$ypagetitle+(($yarea/2-(count($timeslots)/30)*$gridy)/2);
			}
			
			//next field
			if(!empty($game['place_id']) && $game['fieldname'] != $prevField){
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
					foreach($timeslots as $time=>$toffset){
					  if(strEndsWith($time, ":00")||strEndsWith($time, ":30")){
						$this->Cell($xtimetitle,$gridy,$time,0,2,'L',false);
					  }
					}
				}
				
				$field_offset = $left_margin+($field-1)*$gridx+$xtimetitle;
				$this->SetXY($field_offset,$time_offset-$yfieldtitle);
								
				$this->SetFont('Arial','B',10);
				$this->SetTextColor(0);
				$this->SetFillColor(190);
				
				$txt = $this->pdfText(_("Field")." ".$game['fieldname']);
				$this->Cell($gridx,$yfieldtitle/2,$txt,"LRT",2,'C',true);
				
				$this->SetFont('Arial','',8);
				$this->SetTextColor(0);
				$txt = $this->pdfText($game['placename']);
				$this->Cell($gridx,$yfieldtitle/2,$txt,"LR",2,'C',true);
				//write grids
				foreach($timeslots as $time=>$toffset){
				  if(strEndsWith($time, ":00")||strEndsWith($time, ":30")){
					$this->Cell($gridx,$gridy,"",1,2,'L',false);
				  }
				}
			}
			
			$slot = DefHourFormat($game['time']);
			$this->SetXY($field_offset,$time_offset+$timeslots[$slot]);
			
			$this->SetTextColor(0);
			$this->SetFillColor(230);
			$this->SetDrawColor(0);
			
			$height=($game['timeslot']/30)*4;
			$this->Cell($gridx,$height,"",'LRBT',0,'C',true);
			
			$this->SetXY($field_offset,$time_offset+$timeslots[$slot]);
			
			$this->SetTextColor(0);
			$this->SetFillColor(255);
			$this->SetDrawColor(0);
			$this->SetFont('Arial','',$teamfont);
			$this->SetTextColor(0);
			$this->Cell($gridx,1,"",0,2,'',false);
			if($game['hometeam'] && $game['visitorteam']){
				//$txt = $this->DynSetTeamName($game['hometeamname'],$game['homeshortname'],$gridx,$teamfont);
				//$this->Cell($gridx,4,$txt,0,2,'L',false);
				$txt = $game['hometeamname']." - ".$game['visitorteamname'];
				//$stxt = $this->pdfText($game['homeshortname']." - ".$game['visitorshortname']);
				$txt = $this->DynSetTeamName($txt,"",$gridx,$teamfont);
				$this->Cell($gridx,$gridy-1,$txt,0,2,'L',false);
			}elseif($game['gamename']){
				$txt = $this->DynSetTeamName($game['gamename'],"",$gridx,$teamfont);
				$this->Cell($gridx,$gridy-1,$txt,0,2,'L',false);
			}else{
			    $txt = $this->pdfText($game['phometeamname']." - ".$game['pvisitorteamname']);
				//$txt = $this->DynSetTeamName($game['phometeamname'],"",$gridx,$teamfont);
				//$this->Cell($gridx,4,$txt,0,2,'L',false);
				$txt = $this->DynSetTeamName($txt,"",$gridx,$teamfont);
				$this->Cell($gridx,$gridy-1,$txt,0,2,'L',false);
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
				$this->SetFillColor(230);
				$this->SetDrawColor(0);
			}
			
			$this->Cell($gridx,1,"",0,2,'L',$colors);
			$txt = $this->pdfText($game['seriesname']);
			if(strlen($game['poolname'])<15){
				$txt .= ", \n";
			}else{
				$txt .= ", ";
			}
			$txt .= $this->pdfText($game['poolname']);
			//$this->DynSetFont($txt,$gridx,8);
			//$this->MultiCell($gridx,4,$txt,"LR",2,'L',$colors);
			$fontsize=10;
		    while($this->GetStringWidth($txt)>$gridx-2){
			  $this->SetFont('Arial','',--$fontsize);
		    }
			$this->Cell($gridx,$gridy-2,$txt,0,2,'LR',false);
			
			$this->SetTextColor(0);
			$this->SetFillColor(255);
			$this->SetDrawColor(0);
				
			$this->SetXY($field_offset,$time_offset+$timeslots[$slot]);
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
	/*	$this->SetXY(-50,-8);
		$this->SetFont('Arial','',6);
		$this->SetTextColor(0);
		$txt = date( 'Y-m-d H:i:s P', time());
		$this->Cell(0,0,$txt,0,2,'R',false);
	*/
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
			$txt = $this->pdfText(U_($info['fieldname']));
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
			$goals = intval($game['homescore'])+intval($game['visitorscore']);
	
			if($goals && !intval($game['isongoing'])){
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
		
	function Timeouts()
		{
    $curX = $this->GetX();
		//header
		$this->SetFont('Arial','B',10);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(52,5,_("Timeouts (min:sec)"),'LRTB',1,'C',true);
    $this->SetX($curX);
		
		//home grids
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(12,12,$this->game['homeshortname'],'LRTB',0,'C',true);
		
		$this->Cell(20,6,":",'LRTB',0,'C',true);
		$this->Cell(20,6,":",'LRTB',1,'C',true);
    $this->SetX($curX+12);
		$this->Cell(20,6,":",'LRTB',0,'C',true);
		$this->Cell(20,6,":",'LRTB',1,'C',true);
		
		//visitor grids
		$this->SetTextColor(0);
		$this->SetFillColor(255);
    $this->SetX($curX);
		$this->Cell(12,12,$this->game['visitorshortname'],'LRTB',0,'C',true);
		
		$this->Cell(20,6,":",'LRTB',0,'C',true);
		$this->Cell(20,6,":",'LRTB',1,'C',true);
    $this->SetX($curX+12);
		$this->Cell(20,6,":",'LRTB',0,'C',true);
		$this->Cell(20,6,":",'LRTB',1,'C',true);
	
		}

	function SpiritTimeouts()
		{
    $curX = $this->GetX();

		//header
		$this->SetFont('Arial','B',10);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(80,5,_("Spirit stoppages (min:sec)"),'LRTB',0,'C',true);
		$this->Ln();
		$this->SetX($curX);

		//home grids
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(20,5,$this->game['homeshortname'],'LRTB',0,'C',true);
		
		for($i=0;$i<3;$i++)
			{
			$this->Cell(20,5,":",'LRTB',0,'C',true);
			}
		
		$this->Ln();
		$this->SetX($curX);
		
		//visitor grids
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(20,5,$this->game['visitorshortname'],'LRTB',0,'C',true);
		
		for($i=0;$i<3;$i++)
			{
			$this->Cell(20,5,":",'LRTB',0,'C',true);
			}
	
		$this->Ln();	
		}

	function Timeouts2()
		{
    $curX = $this->GetX();
		//header row 1
		$this->SetFont('Arial','B',10);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(12,5,"",'R',0,'C',true);
		$this->SetFillColor(230);
		$this->CellFitScale(40,5,_("Timeouts"),'LRT',0,'C',true);
		$this->SetFillColor(255);
		//$this->Cell(1,5,"",'LR',0,'C',true);
		$this->SetFillColor(230);
		$this->CellFitScale(20,5,_("Spirit"),'LRT',1,'C',true);

    //header row 2
    $this->SetX($curX);
		$this->SetFont('Arial','B',10);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(12,5,"",'R',0,'C',true);
		$this->SetFillColor(230);
		$this->SetFont('Arial','',10);
		$this->CellFitScale(20,5,_("1st half"),'LRB',0,'C',true);
		$this->CellFitScale(20,5,_("2nd half"),'LRB',0,'C',true);
		$this->SetFont('Arial','B',10);
		$this->SetFillColor(255);
		//$this->Cell(1,5,"",'LR',0,'C',true);
		$this->SetFillColor(230);
		$this->CellFitScale(20,5,_("Timeouts"),'LRB',1,'C',true);
		//$this->Ln(1);

		//home grids
    $this->SetX($curX);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->CellFitScale(12,12,$this->game['homeshortname'],'LRTB',0,'C',true);
    //$this->Cell(1,12,"",0,'C',true);
		
		$this->Cell(20,6,":",'LRTB',0,'C',true);
		$this->Cell(20,6,":",'LRTB',0,'C',true);
		//$this->Cell(1,6,"",'LR',0,'C',true);
		$this->Cell(20,6,":",'LRTB',1,'C',true);
    $this->SetX($curX+12);
		$this->Cell(20,6,":",'LRTB',0,'C',true);
		$this->Cell(20,6,":",'LRTB',0,'C',true);
		//$this->Cell(1,6,"",'LR',0,'C',true);
		$this->Cell(20,6,":",'LRTB',1,'C',true);
		
		//$this->Ln(1);

		//visitor grids
		$this->SetTextColor(0);
		$this->SetFillColor(255);
    $this->SetX($curX);
		$this->CellFitScale(12,12,$this->game['visitorshortname'],'LRTB',0,'C',true);
    //$this->Cell(1,12,"",0,'C',true);
		
		$this->Cell(20,6,":",'LRTB',0,'C',true);
		$this->Cell(20,6,":",'LRTB',0,'C',true);
		//$this->Cell(1,6,"",'LR',0,'C',true);
		$this->Cell(20,6,":",'LRTB',1,'C',true);
    $this->SetX($curX+12);
		$this->Cell(20,6,":",'LRTB',0,'C',true);
		$this->Cell(20,6,":",'LRTB',0,'C',true);
		//$this->Cell(1,6,"",'LR',0,'C',true);
		$this->Cell(20,6,":",'LRTB',1,'C',true);
	
		}

	function TimeoutsBeach()
		{
    $curX = $this->GetX();
		//header row 1
		$this->SetFont('Arial','B',10);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(12,6,"",'R',0,'C',true);
		$this->SetFillColor(230);
		$this->CellFitScale(20,6,_("Timeouts"),'LRTB',0,'C',true);
		$this->SetFillColor(0);
    $this->Cell(0.5,6,"",'LRTB',0,'C',true); // separator
		$this->SetFillColor(230);
		$this->CellFitScale(40,6,_("Spirit stoppages"),'LRTB',1,'C',true);

		//home grids
    $this->SetX($curX);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->CellFitScale(12,8,$this->game['homeshortname'],'LRTB',0,'C',true);
		
		$this->Cell(20,8,":",'LRTB',0,'C',true);
		$this->SetFillColor(0);
    $this->Cell(0.5,8,"",'LRTB',0,'C',true); // separator
		$this->SetFillColor(255);
		$this->Cell(20,8,":",'LRTB',0,'C',true);
		$this->Cell(20,8,":",'LRTB',1,'C',true);
		
		//visitor grids
		$this->SetTextColor(0);
		$this->SetFillColor(255);
    $this->SetX($curX);
		$this->CellFitScale(12,8,$this->game['visitorshortname'],'LRTB',0,'C',true);
		
		$this->Cell(20,8,":",'LRTB',0,'C',true);
		$this->SetFillColor(0);
    $this->Cell(0.5,8,"",'LRTB',0,'C',true); // separator
		$this->SetFillColor(255);
		$this->Cell(20,8,":",'LRTB',0,'C',true);
		$this->Cell(20,8,":",'LRTB',1,'C',true);
	
		}

	function HalfTime()
		{
    $curX = $this->GetX();

		$this->SetFont('Arial','B',10);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(45,5,_("Second half started at"),'LRTB',0,'C',true);
		$this->Ln();

    $this->SetX($curX);
		
		$this->SetFillColor(255);
		$this->Cell(27,10,":",'LTB',0,'C',true);
		$this->SetFont('Arial','',10);
		$this->Cell(18,10,"(min:sec)",'RTB',0,'L',true);
		$this->Ln();

		}

	function HalfTimeScore()
		{
		$this->SetFont('Arial','B',10);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(50,5,_("Halftime score"),'LRTB',0,'C',true);
		$this->Ln();

		$this->SetFillColor(255);
		$this->Cell(14,5,$this->game['homeshortname'],'LRTB',0,'C',true);
		$this->Cell(22,5,"-",'LRTB',0,'C',true);
		$this->Cell(14,5,$this->game['visitorshortname'],'LRTB',0,'C',true);
		$this->Ln();

		}

	function FinalScore()
		{
    $curX = $this->GetX();

		$this->SetFont('Arial','B',10);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(40,5,_("Final score"),'LRTB',0,'C',true);
		$this->Ln();

    $this->SetX($curX);

		$this->SetFillColor(255);
		$this->Cell(10,10,$this->pdfText($this->game['homeshortname']),'LRTB',0,'C',true);
		$this->Cell(20,10,"-",'LRTB',0,'C',true);
		$this->Cell(10,10,$this->pdfText($this->game['visitorshortname']),'LRTB',0,'C',true);
		$this->Ln();

		}

	function FirstOffence()
		{
		//header
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(80,6,$this->pdfText(_("Starting offensive team")),'LRTB',0,'C',true);
		$this->Ln();
		
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
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(80,6,$this->pdfText(_("Spirit score")),'LRTB',0,'C',true);
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
		$curX = $this->GetX();

		//header
		$this->SetFont('Arial','B',10);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(80,5,$this->pdfText(_("Captains' signatures")),'LRTB',0,'C',true);
		$this->Ln();
		
    $this->SetX($curX);

		//home grids
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(15,10,$this->pdfText($this->game['homeshortname']),'LRTB',0,'C',true);
		$this->Cell(65,10,"",'LRTB',0,'L',true);
		
		$this->Ln();
    $this->SetX($curX);
		
		//visitor grids
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(15,10,$this->pdfText($this->game['visitorshortname']),'LRTB',0,'C',true);
		$this->Cell(65,10,"",'LRTB',0,'L',true);
		$this->Ln();	
		}

	function SigFinalScore()
		{
		$curX = $this->GetX();

		//header
		$this->SetFont('Arial','',10);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(30,5,_("Team"),'LRTB',0,'C',true);
		$this->CellFitScale(18,5,_("Halftime score"),'LRTB',0,'C',true);
		$this->SetFont('Arial','B',10);
		$this->CellFitScale(18,5,_("Final score"),'LRTB',0,'C',true);
		$this->CellFitScale(40,5,_("Captains' signatures"),'LRTB',0,'C',true);
		$this->Cell(12,5,"#",'LRTB',0,'C',true);
		$this->Ln();
		
    $this->SetX($curX);

		//home grids
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->SetFont('Arial','',10);
		$this->CellFitScale(30,12,$this->homefullname,'LRTB',0,'L',true);
		$this->Cell(18,12,"",'LRTB',0,'L',true);
		$this->Cell(18,12,"",'LRTB',0,'L',true);
		$this->Cell(40,12,"",'LRTB',0,'L',true);
		$this->Cell(12,12,"",'LRTB',0,'L',true);
		$this->Ln();

    $this->SetX($curX);
		
		//visitor grids
		$this->CellFitScale(30,12,$this->visitorfullname,'LRTB',0,'L',true);
		$this->Cell(18,12,"",'LRTB',0,'L',true);
		$this->Cell(18,12,"",'LRTB',0,'L',true);
		$this->Cell(40,12,"",'LRTB',0,'L',true);
		$this->Cell(12,12,"",'LRTB',0,'L',true);
		$this->Ln();	
		
    }

	function ScoreGrid()
		{ // narrower 100 -> 80 width
		$this->SetFont('Arial','',8);
		
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->SetX(120);
		$this->CellFitScale(16,4,$this->pdfText(_("Scoring team")),'LRT',0,'C',true);
		$this->CellFitScale(20,4,$this->pdfText(_("Shirt numbers")),'LRT',0,'C',true);
		$this->CellFitScale(20,4,$this->pdfText(_("Time")),'LRT',0,'C',true);
		$this->CellFitScale(20,4,$this->pdfText(_("Score")),'LRT',0,'C',true);
		$this->Ln();
		$this->SetX(120);
		$this->SetFont('Arial','',9);
		$this->CellFitScale(8,5,$this->pdfText($this->game['homeshortname']),'LRB',0,'C',true);
		$this->CellFitScale(8,5,$this->pdfText($this->game['visitorshortname']),'LRB',0,'C',true);
		$this->CellFitScale(10,5,$this->pdfText(_("Assist")),'LRB',0,'C',true);
		$this->CellFitScale(10,5,$this->pdfText(_("Goal")),'LRB',0,'C',true);
		$this->CellFitScale(20,5,$this->pdfText(_("min : sec")),'LRB',0,'C',true);
		$this->CellFitScale(20,5,$this->pdfText($this->game['homeshortname'] . " - " . $this->game['visitorshortname']),'LRB',0,'C',true);
		$this->Ln();
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		for($i=1;$i<31;$i++)
			{
			$this->SetX(115);
			$this->SetFont('Arial','',8);
			$this->Cell(5,5,$i,'',0,'C',true);
			$this->SetFont('Arial','',10);
		  $this->SetFont('ZapfDingbats','',14);
			$this->Cell(8,5,"q",'LRTB',0,'C',true);
			$this->Cell(8,5,"q",'LRTB',0,'C',true);
		  $this->SetFont('Arial','',10);
			$this->Cell(10,5,"",'LRTB',0,'C',true);
			$this->Cell(10,5,"",'LRTB',0,'C',true);
			$this->Cell(20,5,":",'LRTB',0,'C',true);
			$this->Cell(20,5,"-",'LRTB',0,'C',true);
			if (($this->gender_rule == "A") && $this->isMixed && (($i%4==0) || (($i-1)%4==0))) {
        $this->Cell(4,5,"*",'L',0,'C',true);
      }
			$this->Ln();
			}
    $this->SetX(120);
		$this->SetFont('Arial','I',9);
		$this->CellFitScale(76,6,$this->pdfText(_("Note: For Callahan goals write 'XX' as the assist.")),'T',1,'L',true);
		}

	function DefenseGridHeader()
		{
		$this->SetFont('Arial','',10);
		$this->Cell(12,6,$this->pdfText($this->game['homeshortname']),'LRTB',0,'C',true);
		$this->Cell(12,6,$this->pdfText($this->game['visitorshortname']),'LRTB',0,'C',true);
		$this->SetFont('Arial','',8);
		$this->Cell(12,6,$this->pdfText(_("Player")),'LRTB',0,'C',true);
		$this->Cell(12,6,$this->pdfText(_("Caught")),'LRTB',0,'C',true);
		$this->Cell(12,6,$this->pdfText(_("Rejected")),'LRTB',0,'C',true);
		$this->Cell(12,6,$this->pdfText(_("Callahan")),'LRTB',0,'C',true);
		$this->SetFont('Arial','',10);
		$this->Cell(20,6,$this->pdfText(_("Time")),'LRTB',0,'C',true);
		}

	function DefenseGrid()
		{

		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->SetX(10);
		$this->DefenseGridHeader();
		$this->SetX(108);
		$this->DefenseGridHeader();
		$this->Ln();
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$topY = $this->GetY();
		for($i=1;$i<21;$i++)
			{
			if ($i == 11) {
				$this->SetY($topY);
			}
			if ($i < 11) {
				$this->SetX(5);
			} else {
				$this->SetX(103);
			}
			$this->SetFont('Arial','',8);
			$this->Cell(5,6,$i,'',0,'R',true);
			$this->SetFont('Arial','',10);
			$this->Cell(12,6,"",'LRTB',0,'C',true);
			$this->Cell(12,6,"",'LRTB',0,'C',true);
			$this->Cell(12,6,"",'LRTB',0,'C',true);
			$this->Cell(12,6,"",'LRTB',0,'C',true);
			$this->Cell(12,6,"",'LRTB',0,'C',true);
			$this->Cell(12,6,"",'LRTB',0,'C',true);
			$this->Cell(20,6,":",'LRTB',0,'C',true);
			$this->Ln();
			}
		}
	function Instructions()
		{
		$this->Ln(5);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		
		$this->SetFont('Arial','B',9);
		
		$data = _("Scoresheet filling instructions:");
		$this->SetX(100);
		$this->MultiCell(100,5,$this->pdfText($data),0,'C',true);
		$this->Ln(3);
		
		$this->SetFont('Arial','',9);
		
		$data = "1. "._("Scorekeepers fill in their names.");
		$this->SetX(100);
		$this->MultiCell(100,4,$this->pdfText($data),0,'L',true);
		$this->Ln(3);
		
		$data = "2. "._("Check rosters with team captains. Cross out players not playing this game and correct any wrong shirt numbers.");
		$this->SetX(100);
		$this->MultiCell(100,4,$this->pdfText($data),0,'L',true);
		$this->Ln(3);
		
		$data = "3. "._("Mark which team is starting on offence.");
		$this->SetX(100);
		$this->MultiCell(100,4,$this->pdfText($data),0,'L',true);
		$this->Ln(3);

		$data = "4. "._("Game start. Use the timeline guide at the bottom of the next page to aid you with timekeeping, and remember: it is the timekeeper's responsibility to blow the whistle, not to enforce what happens!");
		$this->SetX(100);
		$this->MultiCell(100,4,$this->pdfText($data),0,'L',true);
		$this->Ln(3);
			
		$data = "5. "._("At halftime, write the game time when it happened as well as the current game score. Game time does not stop at halftime.");
		$this->SetX(100);
		$this->MultiCell(100,4,$this->pdfText($data),0,'L',true);
		$this->Ln(3);
		
		$data = "6. "._("Enter goals: mark which team scored, write players' numbers for the assist and goal (XX for Callahan), and write the game time when the goal was scored and the current game score.");
		$this->SetX(100);
		$this->MultiCell(100,4,$this->pdfText($data),0,'L',true);
		$this->Ln(3);
		
		$data = "7. "._("Timeouts: write the game time when it was called. Game time does not stop during timeouts.");
		$this->SetX(100);
		$this->MultiCell(100,4,$this->pdfText($data),0,'L',true);
		$this->Ln(3);
		
		$data = "8. "._("Game finished: write final score and get captain's signature.");
		$this->SetX(100);
		$this->MultiCell(100,4,$this->pdfText($data),0,'L',true);
		$this->Ln(1);

		}

	function FinalScoreTable()
		{
		//header
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(80,6,$this->pdfText(_("Final score")),'LRTB',0,'C',true);
		$this->Ln();
		
		//data
		$this->SetTextColor(0);
		$this->SetFillColor(255);

		$fontsize=12;
		$this->SetFont('Arial','B',$fontsize);
		while($this->GetStringWidth($this->game['hometeamname'])>36){
			$this->SetFont('Arial','B',--$fontsize);
		}
		
		$this->Cell(40,6,$this->game['hometeamname'],'LRT',0,'C',true);
		//$this->Cell(4,6,"-",'T',0,'C',true);
		
		$fontsize=12;
		$this->SetFont('Arial','B',$fontsize);
		while($this->GetStringWidth($this->game['visitorteamname'])>36){
			$this->SetFont('Arial','B',--$fontsize);
		}
		$this->Cell(40,6,$this->game['visitorteamname'],'LRT',0,'C',true);

		$this->SetFont('Arial','B',12);
		$this->Ln();
		$this->Cell(40,12,"",'LRTB',0,'C',true);
		$this->Cell(40,12,"",'LRTB',0,'C',true);
		$this->Ln(6);
		}
		

	function Scorekeepers()
		{
		//header
    $this->SetX(71);
		$this->SetFont('Arial','B',10);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(39,5,$this->pdfText(_("Scorekeeper(s)")),'LRTB',0,'C',true);
		$this->Ln();
		
		//blank cell
    $this->SetX(71);
		$this->SetFillColor(255);
		$this->Cell(39,10,'','LRTB',0,'C',true);
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
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(80,6,$data,'LRTB',0,'C',true);
		$this->Ln();
		}

	function SplitOneCellTable($header,$data)
		{
		//header
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(40,6,$header,'LRTB',0,'R',true);
		
		//data
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(40,6,$data,'LRTB',0,'L',true);
		$this->Ln();
		}

	function SplitOneCellTable2($header,$data)
		{
		//header
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(20,6,$header,'LRTB',0,'C',true);
		
		//data
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(60,6,$data,'LRTB',0,'C',true);
		$this->Ln();
		}

	function SplitOneCellTable3($header,$data,$w1,$w2)
		{
		//header
		$this->SetFont('Arial','B',10);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell($w1,5,$header,'LRTB',0,'C',true);
		
		//data
		$this->SetFont('Arial','',10);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell($w2,5,$data,'LRTB',0,'L',true);
		$this->Ln();
		}

	function ThreeCellTable($header1,$header2,$header3,$data1,$data2,$data3)
		{
		//header
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->Cell(30,6,$header1,'LRTB',0,'C',true);
		$this->Cell(20,6,$header2,'LRTB',0,'C',true);
		$this->Cell(30,6,$header3,'LRTB',0,'C',true);
		$this->Ln();
		
		//data
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(30,6,$data1,'LRTB',0,'C',true);
		$this->Cell(20,6,$data2,'LRTB',0,'C',true);
		$this->Cell(30,6,$data3,'LRTB',0,'C',true);
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
		$this->SetFont('Arial','B',12);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->Cell(80,12,$data,'LRTB',0,'C',true);
		$this->Ln();
		}

	function SpiritHeader($yourteam,$opponent)
		{
		//get current X,Y position
		$curX = $this->GetX();
		$curY = $this->GetY();
    $logoW = 18;
    $logoH = 0; // if one of W or H is 0, it will automatically calculate the other to respect ratio

		// event logo
    if (is_file($this->eventLogoSm)) {
      $this->Image($this->eventLogoSm,$curX+1,$curY+11,$logoW,$logoH);
    }
    
    $this->setXY($curX,$curY);
		
    // page title
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->SetFont('Arial','B',12);
		$this->CellFitScale(80,6,"SPIRIT OF THE GAME SCORE SHEET",0,0,'C',true);
		
    $this->SetXY($curX+90,$curY);
    $this->SetFont('Arial','B',9);
		$this->CellFitScale(40,4,"Dear Scorekeeper,",0,1,'R',true);
    $this->SetX($curX+90);
    $this->SetFont('Arial','',9);
		$this->CellFitScale(40,4,"Please give this sheet to",0,1,'R',true);
    
    $this->Ln(1);

    $curX += $logoW+4;

		// 1st row
		$this->SetX($curX);
		$this->SetFont('Arial','B',10);
		$this->SetTextColor(0);
		$this->SetFillColor(230);
		$this->SetFont('Arial','B',10);
		$this->CellFitScale(10,6,_("Day"),'LRTB',0,'C',true);
		$this->SetFillColor(255);
		$this->SetFont('Arial','',10);
		$this->CellFitScale(20,6,$this->game['reservation_group'],'LRTB',0,'C',true);
		$this->Cell(2,6,"",'LR',0,'C',true);
		$this->SetFillColor(230);
		$this->SetFont('Arial','B',10);
		$this->CellFitScale(18,6,"Your Team",'LRTB',0,'L',true);
		$this->SetFillColor(255);
		$this->SetFont('Arial','',10);
		$this->CellFitScale(36,6,$yourteam,'LRTB',0,'L',true);
		//$this->SetFont('Arial','B',14);
		//$this->CellFitScale(6,6,"←",'L',0,'L',true);
		$this->SetFont('Arial','B',10);
		$this->CellFitScale(20,6,"← this team",'L',1,'L',true);


    $this->Ln(2);
   
		// 2nd row
		$this->SetX($curX);
		$this->SetFont('Arial','B',10);
    $this->SetFillColor(230);
		$this->CellFitScale(14,6,_("Game")." #",'LRTB',0,'L',true);
		$this->SetFillColor(255);
		$this->SetFont('Arial','',10);
		$this->CellFitScale(16,6,$this->game['game_id'],'LRTB',0,'C',true);
		$this->Cell(2,6,"",'LR',0,'C',true);
		$this->SetFillColor(230);
		$this->SetFont('Arial','B',10);
		$this->CellFitScale(18,6,"Opponent",'LRTB',0,'L',true);
		$this->SetFillColor(255);
		$this->SetFont('Arial','',10);
		$this->CellFitScale(36,6,$opponent,'LRTB',1,'L',true);

    $this->Ln(2);

    // 3rd row
		$this->SetX($curX);
		$this->SetFillColor(230);
		$this->SetFont('Arial','B',10);
		$this->CellFitScale(14,6,_("Division"),'LRTB',0,'L',true);
		$this->SetFillColor(255);
		$this->SetFont('Arial','',10);
    $this->CellFitScale(42,6,$this->game['division'],'LRTB',0,'C',true);
		$this->Cell(2,6,"",'LR',0,'C',true);
		$this->SetFillColor(230);
		$this->SetFont('Arial','B',10);
		$this->CellFitScale(8,6,_("Pool"),'LRTB',0,'L',true);
		$this->SetFillColor(255);
		$this->SetFont('Arial','',10);
    $this->CellFitScale(40,6,$this->game['pool'],'LRTB',1,'C',true);

		}

	function SpiritTable()
		{
		//get current X,Y position
		$curX = $this->GetX();
		$curY = $this->GetY();

    

		//header
    $this->SetXY($curX,$curY);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->SetFont('Arial','B',8);
    $this->Write(4,"Involve your whole team when rating the other team.\n");
		$this->SetFont('Arial','',8);
    $this->SetX($curX);
    $this->Write(4,"Discuss each of the categories and CIRCLE a score\n");
    $this->SetX($curX);
    $this->Write(4,"from 0 to 4.");
    $curY += 15;
    $this->RotatedText($curX+86,$curY,"Poor",90);
    $this->RotatedText($curX+96,$curY,"Not Good",90);
		$this->SetFont('Arial','B',8);
    $this->RotatedText($curX+106,$curY,"Good",90);
		$this->SetFont('Arial','',8);
    $this->RotatedText($curX+116,$curY,"Very Good",90);
    $this->RotatedText($curX+126,$curY,"Excellent",90);
		$this->SetXY($curX,$curY+3);

		//scores
		$this->SetTextColor(0);
		$this->SetFillColor(255);
    $i = 1;
		foreach(array(
      array("Rules Knowledge and Use", "They did not purposefully misinterpret the rules. They kept to time limits. When they didn't know the rules, they showed a real willingness to learn."),
      array("Fouls and Body Contact","They avoided fouling, contact, and dangerous plays. They played safely. The game flowed smoothly."),
      array("Fair-Mindedness","They apologized in situations where it was appropriate, informed teammates about wrong/unnecessary calls. Only called significant breaches."),
      array("Attitude and Self-Control","They were polite. They played with appropriate intensity irrespective of the score. They left an overall positive impression during and after the game."),
      array("Communication","They communicated respectfully. They listened. They kept discussion to reasonable limits. They got to know us. They used hand signals.")
      ) as $s ) {
		  $curX = $this->GetX();
  		$curY = $this->GetY();

			$this->SetFont('Arial','B',10);
			$this->CellFitScale(79,5,$i.". ".$s[0],0,1,'L',true);
      $this->SetX($curX);
			$this->SetFont('Arial','',6);
			$this->MultiCell(79,3,"Examples: ".$s[1],0,'L',true);
		  $this->SetXY($curX+80,$curY);
			$this->SetFont('Arial','',12);
			$this->Cell(10,10," 0*",'LRTB',0,'C',true);
			$this->Cell(10,10,"1",'LRTB',0,'C',true);
  		$this->SetFillColor(230);
			$this->Cell(10,10,"2",'LRTB',0,'C',true);
		  $this->SetFillColor(255);
			$this->Cell(10,10,"3",'LRTB',0,'C',true);
			$this->Cell(10,10," 4*",'LRTB',0,'C',true);
			$this->SetXY($curX,$curY+12);
      $i++;
			}
  	
    $curY = $this->GetY();

    // total sum
    $this->SetFont('Arial','B',10);
    $this->CellFitScale(79,5,"You Do the Math",0,1,'L',true);
		$this->SetX($curX);
    $this->SetFont('Arial','',6);
    $this->MultiCell(79,3,"Add up the points to give a total Spirit score between 0 and 20.\nMost games will be between 8-13 pts.",0,'L',true);
		$this->SetX($curX);
    $this->SetFont('Arial','B',6);
    $this->MultiCell(79,3,"A '10' is a common score.",0,'L',true);

    $this->SetXY($curX+85,$curY);
    $this->SetFont('Arial','B',12);
    $this->Cell(10,10,"=",'R',0,'C',true);
    $this->Cell(20,10,"",'LRTB',1,'C',true);

		$this->Ln(6);
		$this->SetX($curX);

    // Comments
		$this->SetFont('Arial','B',10);
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->CellFitScale(130,6,"*Comments",0,1,'L',true);
		$this->SetX($curX);
    $this->SetFont('Arial','',6);
    $this->MultiCell(130,3,"Write additional details about the other team's spirit. REQUIRED if you pick a '0' or '4' in any category.\nComments will not be shared publicly, but will be shared with the other team.",0,'L',true);
    $this->Ln(6);

    // Comments' lines
    $this->SetX($curX);
    $this->Cell(130,6,"",'TB',1,'C',true);
    $this->Ln(6);
    $this->SetX($curX);
    $this->Cell(130,6,"",'T',1,'C',true);
    $bottomY = $this->getY();

    $this->SetY($bottomY-6);

    // Comments' lines
    /*
    $this->SetX($curX);
		$this->Line($curX+27,$curY+7,$curX+100,$curY+7);
		$this->Line($curX,$curY+12,$curX+54,$curY+12);
		$this->Line($curX,$curY+18,$curX+90,$curY+18);
		$this->Line($curX,$curY+24,$curX+90,$curY+24);
    */

		}

	function FeedbackGA()
		{
		//get current X position
		$curX = $this->GetX();

		//header
		$this->SetTextColor(0);
		$this->SetFillColor(255);
		$this->SetFont('Arial','B',10);
    $this->CellFitScale(130,6,"If you had Game Advisors for this game, please provide any feedback about them",0,1,'C',true);
    $this->SetX($curX);
		$this->Cell(130,6,"",'B',1,'C',true);
    $this->SetX($curX);
		$this->Cell(130,6,"",'TB',1,'C',true);
    $this->SetX($curX);
		$this->Cell(130,6,"",'TB',1,'C',true);
/*
    $this->SetX($curX);
		$this->Cell(130,6,"",'TB',1,'C',true);
    $this->SetX($curX);
		$this->Cell(130,6,"",'TB',1,'C',true);
*/
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
