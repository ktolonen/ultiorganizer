<?php

require_once __DIR__ . '/../include_only.guard.php';
denyDirectCustomizationAccess(__FILE__);

include_once 'lib/pdf.interfaces.php';
include_once 'lib/tfpdf/tfpdf.php';
include_once 'lib/hsvclass/HSVClass.php';
include_once 'lib/phpqrcode/qrlib.php';

class PDF extends tFPDF implements ScoreSheetPdf
{
    public $B;
    public $I;
    public $U;
    public $HREF;

    public $game = [
        "seasonname" => "",
        "game_id" => "",
        "hometeamname" => "",
        "visitorteamname" => "",
        "poolname" => "",
        "time" => "",
        "placename" => "",
    ];

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);
        $this->AddFont('Arial', '', 'DejaVuSansCondensed.ttf', true);
        $this->AddFont('Arial', 'B', 'DejaVuSansCondensed-Bold.ttf', true);
        $this->AddFont('Arial', 'I', 'DejaVuSansCondensed-Oblique.ttf', true);
        $this->AddFont('Arial', 'BI', 'DejaVuSansCondensed-BoldOblique.ttf', true);
    }

    private function pdfText($text)
    {
        return (string) $text;
    }

    public function PrintScoreSheet($seasonname, $gameId, $hometeamname, $visitorteamname, $poolname, $time, $placename, $homeplayers = [], $visitorplayers = [])
    {
        $this->game['seasonname'] = $this->pdfText($seasonname);
        $this->game['game_id'] = $gameId . "" . getChkNum($gameId);
        $this->game['hometeamname'] = $this->pdfText($hometeamname);
        $this->game['visitorteamname'] = $this->pdfText($visitorteamname);
        $this->game['poolname'] = $this->pdfText($poolname);
        $this->game['time'] = $time;
        $this->game['placename'] = $this->pdfText($placename);

        $this->AddPage();

        $data = _("Ultiorganizer");
        $data .= " - ";
        $data .= _("Scoresheet");
        $data = $this->pdfText($data); //season name already decoded
        $data .= " " . $this->game['seasonname'];

        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(255);
        $this->SetFillColor(0);
        $this->Cell(0, 9, $data, 1, 1, 'C', true);

        $this->SetY(21);

        $this->OneCellTable($this->pdfText(_("Game #")), $this->game['game_id']);
        $this->OneCellTable($this->pdfText(_("Home team")), $this->game['hometeamname']);
        $this->OneCellTable($this->pdfText(_("Away team")), $this->game['visitorteamname']);
        $this->OneCellTable($this->pdfText(_("Division") . ", " . _("Pool")), $this->game['poolname']);
        $this->OneCellTable($this->pdfText(_("Field")), $this->game['placename']);
        $this->OneCellTable($this->pdfText(_("Scheduled start date and time")), $this->game['time']);
        $this->OneCellTable($this->pdfText(_("Game official")), "");
        $this->Ln();

        $this->FirstOffence();
        $this->Ln();

        $this->Timeouts();
        $this->Ln();

        $this->OneCellTable($this->pdfText(_("Halftime ends (time)")), "");
        $this->Ln();

        $this->FinalScoreTable();
        $this->Ln();

        $this->SpiritTimeouts();

        $this->Signatures();
        $this->Ln();
        $this->SetXY(95, 21);
        $this->ScoreGrid();

        //print QR-code for result URL
        $filename = UPLOAD_DIR . $this->game['game_id'] . ".png";
        $url = BASEURL . "/scorekeeper/?view=result&g=" . $this->game['game_id'];
        QRcode::png($url, $filename, 'h', 2, 2);
        $this->Image($filename, 20, 246);
        unlink($filename);

        $this->SetY(-22);

        $data = _("After the match has ended, update result:") . " " . BASEURL . "/scorekeeper/?view=result";
        $data = $this->pdfText($data);
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->MultiCell(0, 1, $data);
    }
    public function PrintDefenseSheet($seasonname, $gameId, $hometeamname, $visitorteamname, $poolname, $time, $placename)
    {
        $this->game['seasonname'] = $this->pdfText($seasonname);
        $this->game['game_id'] = $gameId . "" . getChkNum($gameId);
        $this->game['hometeamname'] = $this->pdfText($hometeamname);
        $this->game['visitorteamname'] = $this->pdfText($visitorteamname);
        $this->game['poolname'] = $this->pdfText($poolname);
        $this->game['time'] = $time;
        $this->game['placename'] = $this->pdfText($placename);

        $this->AddPage();

        $data = _("Ultiorganizer");
        $data .= " - ";
        $data .= _("Defence record");
        $data = $this->pdfText($data); //season name already decoded
        $data .= " " . $this->game['seasonname'];

        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(255);
        $this->SetFillColor(0);
        $this->Cell(0, 9, $data, 1, 1, 'C', true);
        $this->Ln();

        $this->SetY(21);
        $this->DefenseGrid();

        $this->SetY(-25);
        $data = "";
        $data = $this->pdfText($data);
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->MultiCell(0, 2, $data);
        //$this->WriteHTML($data);
    }
    public function PrintPlayerList($homeplayers, $visitorplayers)
    {
        $this->AddPage();

        $data = _("Ultiorganizer");
        $data .= " - ";
        $data .= _("Roster");
        $data .= " " . $this->game['game_id'];
        $data = $this->pdfText($data);
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(255);
        $this->SetFillColor(0);
        $this->Cell(0, 9, $data, 1, 1, 'C', true);

        $this->SetY(21);

        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255);
        $this->SetFillColor(0);

        $this->Cell(94, 8, $this->game['hometeamname'], 'LRTB', 0, 'C', true);

        $this->SetFillColor(255);
        $this->Cell(2, 8, "", 'LR', 0, 'C', true); //separator

        $this->SetFillColor(0);
        $this->Cell(94, 8, $this->game['visitorteamname'], 'LRTB', 0, 'C', true);

        $this->Ln();
        $this->SetFont('Arial', '', 10);
        $this->Cell(8, 6, "", 'LRTB', 0, 'C', true);
        $this->Cell(56, 6, $this->pdfText(_("Name")), 'LRTB', 0, 'C', true);
        $this->Cell(10, 6, $this->pdfText(_("Play")), 'LRTB', 0, 'C', true);
        $this->Cell(10, 6, $this->pdfText(_("#")), 'LRTB', 0, 'C', true);
        $this->Cell(10, 6, $this->pdfText(_("Info")), 'LRTB', 0, 'C', true);

        $this->SetFillColor(255);
        $this->Cell(2, 6, "", 'LR', 0, 'C', true); //separator

        $this->SetFillColor(0);
        $this->Cell(8, 6, "", 'LRTB', 0, 'C', true);
        $this->Cell(56, 6, $this->pdfText(_("Name")), 'LRTB', 0, 'C', true);
        $this->Cell(10, 6, $this->pdfText(_("Play")), 'LRTB', 0, 'C', true);
        $this->Cell(10, 6, $this->pdfText(_("#")), 'LRTB', 0, 'C', true);
        $this->Cell(10, 6, $this->pdfText(_("Info")), 'LRTB', 0, 'C', true);

        $this->Ln();
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        for ($i = 1; $i < 26; $i++) {
            $hplayer = "";
            $vplayer = "";
            if (isset($homeplayers[$i - 1]['name'])) {
                $hplayer = $this->pdfText($homeplayers[$i - 1]['name']);
            }
            if (isset($visitorplayers[$i - 1]['name'])) {
                $vplayer = $this->pdfText($visitorplayers[$i - 1]['name']);
            }
            $this->SetFont('Arial', '', 10);
            $this->Cell(8, 6, $i, 'LRTB', 0, 'C', true);

            if (!empty($hplayer) && !($homeplayers[$i - 1]['accredited'])) {
                $this->SetFont('Arial', 'IB', 10);
            }

            $this->Cell(56, 6, $hplayer, 'LRTB', 0, 'L', true);

            $this->SetFont('Arial', '', 10);
            $this->Cell(10, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(10, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(10, 6, "", 'LRTB', 0, 'C', true);

            $this->Cell(2, 6, "", 'LR', 0, 'C', true); //separator

            $this->Cell(8, 6, $i, 'LRTB', 0, 'C', true);

            if (!empty($vplayer) && !($visitorplayers[$i - 1]['accredited'])) {
                $this->SetFont('Arial', 'IB', 10);
            }
            $this->Cell(56, 6, $vplayer, 'LRTB', 0, 'L', true);

            $this->SetFont('Arial', '', 10);
            $this->Cell(10, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(10, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(10, 6, "", 'LRTB', 0, 'C', true);
            $this->Ln();
        }

        $this->Ln();

        //instructions
        $data = "";
        $data .= "<b>" . _("Filling instructions:") . "</b><BR>";
        $data .= _("1. Mark the team captain with the letter C into the info grid.") . "<BR>";
        $data .= _("2. Mark the spirit captain with the letters SC into the info grid.") . "<BR>";
        $data .= _("3. Mark players who will play with an X in the Play column.") . "<BR>";
        $data .= _("4. Add jersey numbers for players in the # column.") . "<BR>";
        $data .= "<BR><BR>";

        //$data .= "<b>"._("NOTICE")." 1!</b> "._("For new players added, accreditation id or date of birth must be written down.")."<BR>";
        //$data .= "<b>"._("NOTICE")." 2!</b> "._("The team is responsible for the accreditation of <u>all</u> players on the list.")."<BR>";
        //$data .= "<b>"._("NOTICE")." 3! "._("<b><i>Bold italic</i></b> printed players has problems with license. They are <u>not</u> allowed to play until problems are solved (= payment recipe or note from organizer shown).")."";
        $data = $this->pdfText($data);
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->WriteHTML($data);
    }

    public function PrintRoster($teamname, $seriesname, $poolname, $players)
    {
        $this->AddPage();

        $data = $teamname;
        $data .= " - ";
        $data .= _("Roster");
        $data = $this->pdfText($data);
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(255);
        $this->SetFillColor(0, 0, 0);
        $this->Cell(0, 9, $data, 1, 1, 'C', true);

        $data = U_($seriesname);
        $data .= ", ";
        $data .= U_($poolname);
        $data .= ", ";
        $data .= _("Game") . " #:";
        $data = $this->pdfText($data);
        $this->SetFont('Arial', '', 14);
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->Cell(0, 6, $data, 1, 1, 'L', true);

        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255);
        $this->SetFillColor(0, 0, 0);

        $this->SetFont('Arial', '', 10);
        $this->Cell(8, 6, "", 'LRTB', 0, 'C', true);
        $this->Cell(100, 6, $this->pdfText(_("Name")), 'LRTB', 0, 'C', true);
        $this->Cell(10, 6, $this->pdfText(_("Play")), 'LRTB', 0, 'C', true);
        $this->Cell(12, 6, $this->pdfText(_("Jersey")), 'LRTB', 0, 'C', true);
        $this->Cell(60, 6, $this->pdfText(_("Info")), 'LRTB', 0, 'C', true);
        $this->Ln();
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        for ($i = 1; $i < 26; $i++) {
            $player = "";

            if (isset($players[$i - 1]['firstname'])) {
                $player .= $this->pdfText($players[$i - 1]['firstname']);
            }
            $player .= " ";
            if (isset($players[$i - 1]['lastname'])) {
                $player .= $this->pdfText($players[$i - 1]['lastname']);
            }

            $this->SetFont('Arial', '', 10);
            $this->Cell(8, 6, $i, 'LRTB', 0, 'C', true);

            if (isset($players[$i - 1]['accredited']) && !($players[$i - 1]['accredited'])) {
                $this->SetFont('Arial', 'IB', 10);
            }

            $this->Cell(100, 6, $player, 'LRTB', 0, 'L', true);
            $this->SetFont('Arial', '', 10);
            $this->Cell(10, 6, "", 'LRTB', 0, 'C', true);
            if (isset($players[$i - 1]['num']) && $players[$i - 1]['num'] >= 0) {
                $this->Cell(12, 6, $players[$i - 1]['num'], 'LRTB', 0, 'C', true);
            } else {
                $this->Cell(12, 6, "", 'LRTB', 0, 'C', true);
            }
            $this->Cell(60, 6, "", 'LRTB', 0, 'C', true);

            $this->Ln();
        }

        $this->Ln();

        //instructions
        $data = "";
        //$data = "<b>"._("NOTICE")." 1!</b> "._("For new players added, accreditation id or date of birth must be written down.")."<BR>";
        //$data .= "<b>"._("NOTICE")." 2!</b> "._("The team is responsible for the accreditation of <u>all</u> players on the list.")."<BR>";
        //$data .= "<b>"._("NOTICE")." 3! "._("<b><i>Bold italic</i></b> printed players has problems with license. They are <u>not</u> allowed to play until problems are solved (= payment recipe or note from organizer shown).")."";
        $data = $this->pdfText($data);
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->WriteHTML($data);
    }

    public function Footer()
    {
        $this->SetXY(-50, -8);
        $this->SetFont('Arial', '', 6);
        $this->SetTextColor(0);
        $txt = date('Y-m-d H:i:s P', time());
        $this->Cell(0, 0, $txt, 0, 2, 'R', false);
    }


    public function TextColor($bgcolor)
    {
        $hsv = new HSVClass();
        $hsv->setRGBString($bgcolor);
        $hsv->changeHue(180);
        $hsvArr = $hsv->getHSV();
        $hsv->setHSV($hsvArr['h'], 1 - $hsvArr['s'], 1 - $hsvArr['v']);
        return $hsv->getRGB();
    }

    public function DynSetTeamFont($text, $x, $fontsize)
    {
        $this->SetFont('Arial', 'B', $fontsize);
        while ($this->GetStringWidth($text) > $x - 2) {
            $this->SetFont('Arial', '', --$fontsize);
        }
    }

    public function GameRowWithPool($game, $date = false, $time = true, $field = true, $pool = true, $result = true)
    {

        $this->SetFont('Arial', '', 8);
        $textcolor = $this->TextColor($game['color']);
        $fillcolor = colorstring2rgb($game['color']);
        $this->SetDrawColor(0);
        $this->SetFillColor($fillcolor['r'], $fillcolor['g'], $fillcolor['b']);
        $this->SetTextColor($textcolor['r'], $textcolor['g'], $textcolor['b']);

        if ($date) {
            $txt = ShortDate($game['time']);
            $this->Cell(10, 5, $txt, 'TB', 0, 'L', true);
        }

        if ($time) {
            $txt = DefHourFormat($game['time']);
            $this->Cell(10, 5, $txt, 'TB', 0, 'L', true);
        }

        if ($field) {
            $txt = $this->pdfText(U_($game['fieldname']));
            $this->Cell(20, 5, $txt, 'TB', 0, 'L', true);
        }

        $o = 0;
        if ($game['gamename']) {
            $this->SetFont('Arial', 'B', 8);
            $txt = $this->pdfText(U_($game['gamename']) . ":");
            $this->Cell(30, 5, $txt, 'TB', 0, 'L', true);
            $o = 15;
            $this->SetFont('Arial', '', 8);
        }

        if ($game['hometeam'] && $game['visitorteam']) {
            $txt = $this->pdfText($game['hometeamname']);
            $this->Cell(45 - $o, 5, $txt, 'TB', 0, 'L', true);
            $txt = " - ";
            $this->Cell(5, 5, $txt, 'TB', 0, 'L', true);
            $txt = $this->pdfText($game['visitorteamname']);
            $this->Cell(45 - $o, 5, $txt, 'TB', 0, 'L', true);
        } else {
            $this->SetFont('Arial', 'I', 8);
            $txt = $this->pdfText(U_($game['phometeamname']));
            $this->Cell(45 - $o, 5, $txt, 'TB', 0, 'L', true);
            $txt = " - ";
            $this->Cell(5, 5, $txt, 'TB', 0, 'L', true);
            $txt = $this->pdfText(U_($game['pvisitorteamname']));
            $this->Cell(45 - $o, 5, $txt, 'TB', 0, 'L', true);
            $this->SetFont('Arial', '', 8);
        }
        if ($pool) {
            $txt = $this->pdfText(U_($game['seriesname']));
            $this->Cell(20, 5, $txt, 'TB', 0, 'L', true);

            $txt = $this->pdfText(U_($game['poolname']));
            $this->Cell(40, 5, $txt, 'TB', 0, 'L', true);
        }

        if ($result) {
            if (GameHasStarted($game) &&  !intval($game['isongoing'])) {
                $txt = intval($game['homescore']);
                $this->Cell(5, 5, $txt, 'TB', 0, 'L', true);
                $txt = " - ";
                $this->Cell(5, 5, $txt, 'TB', 0, 'L', true);
                $txt = intval($game['visitorscore']);
                $this->Cell(5, 5, $txt, 'TB', 0, 'L', true);
            }
        }

        //fill end of the row
        $this->Cell(0, 5, "", 'TB', 0, 'L', true);
        //$this->Write(6, $txt);

    }

    public function Timeouts()
    {
        //header
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255);
        $this->SetFillColor(0, 0, 0);
        $this->Cell(80, 6, $this->pdfText(_("Timeouts")), 'LRTB', 0, 'C', true);
        $this->Ln();

        //home grids
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->Cell(20, 6, $this->pdfText(_("Home")), 'LRTB', 0, 'L', true);

        for ($i = 0; $i < 4; $i++) {
            $this->Cell(15, 6, "", 'LRTB', 0, 'L', true);
        }

        $this->Ln();

        //visitor grids
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->Cell(20, 6, $this->pdfText(_("Away")), 'LRTB', 0, 'L', true);

        for ($i = 0; $i < 4; $i++) {
            $this->Cell(15, 6, "", 'LRTB', 0, 'L', true);
        }
        $this->Ln();
    }

    public function SpiritTimeouts()
    {
        //header
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255);
        $this->SetFillColor(0, 0, 0);
        $this->Cell(80, 6, $this->pdfText(_("Spirit stoppages")), 'LRTB', 0, 'C', true);
        $this->Ln();

        //home grids
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->Cell(20, 6, $this->pdfText(_("Home")), 'LRTB', 0, 'L', true);

        for ($i = 0; $i < 2; $i++) {
            $this->Cell(30, 6, "", 'LRTB', 0, 'L', true);
        }

        $this->Ln();

        //visitor grids
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->Cell(20, 6, $this->pdfText(_("Away")), 'LRTB', 0, 'L', true);

        for ($i = 0; $i < 2; $i++) {
            $this->Cell(30, 6, "", 'LRTB', 0, 'L', true);
        }
        $this->Ln();
    }

    public function FirstOffence()
    {
        //header
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255);
        $this->SetFillColor(0, 0, 0);
        $this->Cell(80, 6, $this->pdfText(_("First Offence")), 'LRTB', 0, 'C', true);
        $this->Ln();

        $this->SetFont('Arial', '', 12);

        //home grids
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->Cell(10, 6, "", 'LRTB', 0, 'L', true);
        $this->Cell(70, 6, $this->game['hometeamname'], 'LRTB', 0, 'L', true);
        $this->Ln();

        //visitor grids
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->Cell(10, 6, "", 'LRTB', 0, 'L', true);
        $this->Cell(70, 6, $this->game['visitorteamname'], 'LRTB', 0, 'L', true);
        $this->Ln();
    }

    public function SpiritPoints()
    {
        //header
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255);
        $this->SetFillColor(0, 102, 153);
        $this->Cell(80, 6, $this->pdfText(_("Spirit score")), 'LRTB', 0, 'C', true);
        $this->Ln();
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $fontsize = 10;
        $this->SetFont('Arial', 'B', $fontsize);
        while ($this->GetStringWidth($this->game['hometeamname']) > 38) {
            $this->SetFont('Arial', 'B', --$fontsize);
        }
        $this->Cell(40, 6, $this->game['hometeamname'], 'LRT', 0, 'C', true);


        $fontsize = 10;
        $this->SetFont('Arial', 'B', $fontsize);
        while ($this->GetStringWidth($this->game['visitorteamname']) > 38) {
            $this->SetFont('Arial', 'B', --$fontsize);
        }
        $this->Cell(40, 6, $this->game['visitorteamname'], 'LRT', 0, 'C', true);

        $this->Ln();
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(40, 6, "", 'LRB', 0, 'C', true);
        $this->Cell(40, 6, "", 'LRB', 0, 'C', true);
        $this->Ln();
    }
    public function Signatures()
    {
        $this->Ln();
        $this->Ln();
        //header
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255);
        $this->SetFillColor(0, 0, 0);
        $this->Cell(80, 6, $this->pdfText(_("Captains' signatures")), 'LRTB', 0, 'C', true);
        $this->Ln();

        //home grids
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->Cell(20, 6, $this->pdfText(_("Home")), 'LRTB', 0, 'L', true);
        $this->Cell(60, 6, "", 'LRTB', 0, 'L', true);

        $this->Ln();

        //visitor grids
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->Cell(20, 6, $this->pdfText(_("Away")), 'LRTB', 0, 'L', true);
        $this->Cell(60, 6, "", 'LRTB', 0, 'L', true);
        $this->Ln();
    }

    public function ScoreGrid()
    {
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->SetX(100);
        $this->Cell(24, 4, $this->pdfText(_("Scoring team")), 'LRT', 0, 'C', true);
        $this->Cell(30, 4, $this->pdfText(_("Jersey numbers")), 'LRT', 0, 'C', true);
        $this->Ln();
        $this->SetX(100);
        $this->SetFont('Arial', '', 10);
        $this->Cell(12, 6, $this->pdfText(_("Home")), 'LRB', 0, 'C', true);
        $this->Cell(12, 6, $this->pdfText(_("Away")), 'LRB', 0, 'C', true);
        $this->Cell(15, 6, $this->pdfText(_("Assist")), 'LRB', 0, 'C', true);
        $this->Cell(15, 6, $this->pdfText(_("Goal")), 'LRB', 0, 'C', true);
        $this->Cell(25, 6, $this->pdfText(_("Time")), 'LRTB', 0, 'C', true);
        $this->Cell(21, 6, $this->pdfText(_("Scores")), 'LRTB', 0, 'C', true);
        $this->Ln();
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        for ($i = 1; $i < 41; $i++) {
            $this->SetX(95);
            $this->SetFont('Arial', '', 8);
            $this->Cell(5, 6, $i, '', 0, 'C', true);
            $this->SetFont('Arial', '', 10);
            $this->Cell(12, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(12, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(15, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(15, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(25, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(21, 6, "-", 'LRTB', 0, 'C', true);
            $this->Ln();
        }
    }
    public function DefenseGrid()
    {
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        //$this->SetX(100);
        //$this->Cell(24,4,$this->pdfText(_("Scoring team")),'LRT',0,'C',true);
        //$this->Cell(30,4,$this->pdfText(_("Jersey numbers")),'LRT',0,'C',true);
        //$this->Ln();
        $this->SetX(50);
        $this->SetFont('Arial', '', 10);
        $this->Cell(12, 6, $this->pdfText(_("Home")), 'LRTB', 0, 'C', true);
        $this->Cell(12, 6, $this->pdfText(_("Away")), 'LRTB', 0, 'C', true);
        $this->Cell(15, 6, $this->pdfText(_("Player")), 'LRTB', 0, 'C', true);
        $this->Cell(15, 6, $this->pdfText(_("Touched")), 'LRTB', 0, 'C', true);
        $this->Cell(15, 6, $this->pdfText(_("Caught")), 'LRTB', 0, 'C', true);
        $this->Cell(15, 6, $this->pdfText(_("Callahan")), 'LRTB', 0, 'C', true);
        $this->Cell(25, 6, $this->pdfText(_("Time")), 'LRTB', 0, 'C', true);
        $this->Ln();
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        for ($i = 1; $i < 31; $i++) {
            $this->SetX(45);
            $this->SetFont('Arial', '', 8);
            $this->Cell(5, 6, $i, '', 0, 'C', true);
            $this->SetFont('Arial', '', 10);
            $this->Cell(12, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(12, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(15, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(15, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(15, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(15, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(25, 6, "", 'LRTB', 0, 'C', true);
            $this->Ln();
        }
    }
    public function FinalScoreTable()
    {
        //header
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255);
        $this->SetFillColor(0, 0, 0);
        $this->Cell(80, 6, $this->pdfText(_("Final score")), 'LRTB', 0, 'C', true);
        $this->Ln();

        //data
        $this->SetTextColor(0);
        $this->SetFillColor(255);

        $fontsize = 12;
        $this->SetFont('Arial', 'B', $fontsize);
        while ($this->GetStringWidth($this->game['hometeamname']) > 36) {
            $this->SetFont('Arial', 'B', --$fontsize);
        }

        $this->Cell(38, 6, $this->game['hometeamname'], 'LTB', 0, 'C', true);
        $this->Cell(4, 6, "-", 'TB', 0, 'C', true);

        $fontsize = 12;
        $this->SetFont('Arial', 'B', $fontsize);
        while ($this->GetStringWidth($this->game['visitorteamname']) > 36) {
            $this->SetFont('Arial', 'B', --$fontsize);
        }
        $this->Cell(38, 6, $this->game['visitorteamname'], 'RTB', 0, 'C', true);

        $this->SetFont('Arial', 'B', 12);
        $this->Ln();
    }

    public function OneCellTable($header, $data)
    {
        //header
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255);
        $this->SetFillColor(0, 0, 0);
        $this->Cell(80, 6, $header, 'LRTB', 0, 'C', true);
        $this->Ln();

        //data
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->Cell(80, 6, $data, 'LRTB', 0, 'C', true);
        $this->Ln();
    }

    public function DoubleCellTable($header, $data)
    {
        //header
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255);
        $this->SetFillColor(0, 0, 0);
        $this->Cell(80, 6, $header, 'LRTB', 0, 'C', true);
        $this->Ln();

        //data
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->Cell(80, 12, $data, 'LRTB', 0, 'C', true);
        $this->Ln();
    }


    public function WriteHTML($html)
    {
        //HTML parser
        $html = str_replace("\n", ' ', $html);
        $a = preg_split('/<(.*)>/U', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($a as $i => $e) {
            if ($i % 2 == 0) {
                //Text
                if ($this->HREF) {
                    $this->PutLink($this->HREF, $e);
                } else {
                    $this->Write(5, $e);
                }
            } else {
                //Tag
                if ($e[0] == '/') {
                    $this->CloseTag(strtoupper(substr($e, 1)));
                } else {
                    //Extract attributes
                    $a2 = explode(' ', $e);
                    $tag = strtoupper(array_shift($a2));
                    $attr = [];
                    foreach ($a2 as $v) {
                        if (preg_match('/([^=]*)=["\']?([^"\']*)/', $v, $a3)) {
                            $attr[strtoupper($a3[1])] = $a3[2];
                        }
                    }
                    $this->OpenTag($tag, $attr);
                }
            }
        }
    }

    public function OpenTag($tag, $attr)
    {
        //Opening tag
        if ($tag == 'B' || $tag == 'I' || $tag == 'U') {
            $this->SetStyle($tag, true);
        }
        if ($tag == 'A') {
            $this->HREF = $attr['HREF'];
        }
        if ($tag == 'BR') {
            $this->Ln(5);
        }
    }

    public function CloseTag($tag)
    {
        //Closing tag
        if ($tag == 'B' || $tag == 'I' || $tag == 'U') {
            $this->SetStyle($tag, false);
        }
        if ($tag == 'A') {
            $this->HREF = '';
        }
    }

    public function SetStyle($tag, $enable)
    {
        //Modify style and select corresponding font
        $this->$tag += ($enable ? 1 : -1);
        $style = '';
        foreach (['B', 'I', 'U'] as $s) {
            if ($this->$s > 0) {
                $style .= $s;
            }
        }
        $this->SetFont('', $style);
    }

    public function PutLink($URL, $txt)
    {
        //Put a hyperlink
        $this->SetTextColor(0, 0, 255);
        $this->SetStyle('U', true);
        $this->Write(5, $txt, $URL);
        $this->SetStyle('U', false);
        $this->SetTextColor(0);
    }
}
