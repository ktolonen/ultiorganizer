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

    public function PrintScoreSheet($seasonname, $gameId, $hometeamname, $visitorteamname, $poolname, $time, $placename)
    {
        $this->game['seasonname'] = $this->pdfText($seasonname);
        $this->game['game_id'] = $gameId . "" . getChkNum($gameId);
        $this->game['hometeamname'] = $this->pdfText($hometeamname);
        $this->game['visitorteamname'] = $this->pdfText($visitorteamname);
        $this->game['poolname'] = $this->pdfText($poolname);
        $this->game['time'] = $time;
        $this->game['placename'] = $this->pdfText($placename);

        $this->AddPage();

        $data = _("Gummis");
        $data .= " - ";
        $data .= _("Game Record");
        $data = $this->pdfText($data); //season name already decoded
        $data .= " " . $this->game['seasonname'];

        $this->HeaderColors(1);
        $this->Cell(0, 9, $data, 1, 1, 'C', true);

        $this->SetY(21);


        $this->OneCellTable($this->pdfText(_("Game #")), $this->game['game_id']);
        $this->OneCellTable($this->pdfText(_("Home team")), $this->game['hometeamname']);
        $this->OneCellTable($this->pdfText(_("Away team")), $this->game['visitorteamname']);
        $this->OneCellTable($this->pdfText(_("Division") . ", " . _("Pool")), $this->game['poolname']);
        $this->OneCellTable($this->pdfText(_("Field")), $this->game['placename']);
        $this->OneCellTable($this->pdfText(_("Scheduled start date and time")), $this->game['time']);
        $this->OneCellTable($this->pdfText(_("Game official")), "");
        $this->SetFont('Arial', '', 10);
        $this->Ln();

        $this->FirstOffence();
        $this->Ln();

        $this->Timeouts();
        $this->Ln();

        $this->SpiritTimeouts();
        $this->Ln();

        $this->OneCellTable($this->pdfText(_("Halftime ends")), "");
        $this->Ln();

        $this->Ln();
        $this->FinalScoreTable();
        $this->Ln();

        $this->Signatures();
        $this->SetXY(95, 21);
        $this->ScoreGrid();

        $this->SetY(-25);
        $data = "";
        $data = $this->pdfText($data);
        $this->SetFont('Arial', '', 10);
        $this->EmptyColors();
        $this->MultiCell(0, 2, $data);
        $this->Image("cust/gummis/gummi_footer.png", 10, 255, 80);

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

        $data = _("Organization");
        $data .= " - ";
        $data .= _("Defence record");
        $data = $this->pdfText($data); //season name already decoded
        $data .= " " . $this->game['seasonname'];

        $this->HeaderColors(1);
        $this->Cell(0, 9, $data, 1, 1, 'C', true);
        $this->Ln();

        $this->SetY(21);
        $this->DefenseGrid();

        $this->SetY(-25);
        $data = "";
        $data = $this->pdfText($data);
        $this->EmptyColors();
        $this->MultiCell(0, 2, $data);
        //$this->WriteHTML($data);
    }

    public function PrintPlayerList($homeplayers, $visitorplayers)
    {
        if (!isset($homeplayers[0]['name']) && !$visitorplayers[0]['name']) {
            return;
        }

        $this->AddPage();

        $data = _("Gummis");
        $data .= " - ";
        $data .= _("Roster");
        $data .= " " . _("for game") . " #" . $this->game['game_id'];
        $data = $this->pdfText($data);
        $this->HeaderColors(1);
        $this->Cell(0, 9, $data, 1, 1, 'C', true);

        $this->SetY(21);

        $this->HeaderColors(2);

        $this->Cell(94, 6, $this->game['hometeamname'], 'LRTB', 0, 'C', true);

        $this->EmptyColors();
        $this->Cell(2, 6, "", 'LR', 0, 'C', true); //separator

        $this->HeaderColors(2);
        $this->Cell(94, 6, $this->game['visitorteamname'], 'LRTB', 0, 'C', true);

        $this->Ln();
        $this->HeaderColors(3);
        //$this->Cell(8,6,"",'LRTB',0,'C',true);
        $this->Cell(56, 6, $this->pdfText(_("Name")), 'LRTB', 0, 'C', true);
        $this->Cell(15, 6, $this->pdfText(_("Jersey#")), 'LRTB', 0, 'C', true);
        $this->Cell(23, 6, $this->pdfText(_("Info")), 'LRTB', 0, 'C', true);
        //$this->Cell(10,6,_("License ok"),'LRTB',0,'C',true);

        $this->EmptyColors();
        $this->Cell(2, 6, "", 'LR', 0, 'C', true); //separator

        $this->HeaderColors();
        //$this->Cell(8,6,"",'LRTB',0,'C',true);
        $this->Cell(56, 6, $this->pdfText(_("Name")), 'LRTB', 0, 'C', true);
        $this->Cell(15, 6, $this->pdfText(_("Jersey#")), 'LRTB', 0, 'C', true);
        $this->Cell(23, 6, $this->pdfText(_("Info")), 'LRTB', 0, 'C', true);
        //$this->Cell(10,6,_("License ok"),'LRTB',0,'C',true);

        $this->Ln();
        $this->EmptyColors();
        for ($i = 1;$i < 31;$i++) {
            $hplayer = "";
            $hnumber = "";
            $vplayer = "";
            $vnumber = "";

            if (isset($homeplayers[$i - 1]['name'])) {
                $hplayer = $this->pdfText($homeplayers[$i - 1]['name']);
                $hnumber = $homeplayers[$i - 1]['num'];
            }
            if (isset($visitorplayers[$i - 1]['name'])) {
                $vplayer = $this->pdfText($visitorplayers[$i - 1]['name']);
                $vnumber = $visitorplayers[$i - 1]['num'];
            }
            $this->EmptyColors();
            //$this->Cell(8,6,$i,'LRTB',0,'C',true);

            if (!empty($hplayer) && !($homeplayers[$i - 1]['accredited'])) {
                $this->EmptyColors(3, null, 'IB');
            }

            $this->Cell(56, 6, $hplayer, 'LRTB', 0, 'L', true);

            $this->EmptyColors();
            $this->Cell(15, 6, $hnumber, 'LRTB', 0, 'C', true);
            $this->Cell(23, 6, "", 'LRTB', 0, 'C', true);
            //$this->Cell(10,6,"",'LRTB',0,'C',true);

            $this->Cell(2, 6, "", 'LR', 0, 'C', true); //separator

            //$this->Cell(8,6,$i,'LRTB',0,'C',true);

            if (!empty($vplayer) && !($visitorplayers[$i - 1]['accredited'])) {
                $this->EmptyColors(3, null, 'IB');
            }
            $this->Cell(56, 6, $vplayer, 'LRTB', 0, 'L', true);

            $this->EmptyColors();
            $this->Cell(15, 6, $vnumber, 'LRTB', 0, 'C', true);
            $this->Cell(23, 6, "", 'LRTB', 0, 'C', true);
            //$this->Cell(10,6,"",'LRTB',0,'C',true);
            $this->Ln();
        }

        $this->EmptyColors(4);
        $data = _("Total number of players:") . " " . count($homeplayers);
        $data = $this->pdfText($data);
        $this->Cell(94, 4, $data, 'T', 0, 'L', true);
        $this->Cell(2, 6, "", '', 0, 'C', true); //separator
        $data = _("Total number of players:") . " " . count($visitorplayers);
        $data = $this->pdfText($data);
        $this->Cell(94, 4, $data, 'T', 0, 'L', true);

        $this->Ln();

        //instructions
        $data = "<br><b>" . _("Scoresheet filling instructions:") . "</b><br>";
        $data .= "1. " . _("Officials fill in their names.") . "<br>";
        $data .= "2. " . _("Captains confirm roster by crossing out injured players, and adjusting jersey numbers if necessary.") . "<br>";
        $data .= "3. " . _("After the toss, officials check the team that will start on offence.") . "<br>";
        $data .= "4. " . _("When halftime starts, fill in the time it ends (the second-half start time).") . "<br>";
        $data .= "5. " . _("During the game, fill in which team scored, the jersey numbers of the player who threw the assist (Assist) and the player who caught the goal (Goal), the time when the goal was scored, and the scoreline after the goal. If a player scores an intercept goal (Callahan), then mark XX as the assist.") . "<br>";
        $data .= "6. " . _("When a team takes a timeout, mark the time in the \"Timeouts\" section.") . "<br>";
        $data .= "7. " . _("After the game, each captain signs the scoresheet to confirm the final score.") . "<br>";
        $data .= "8. " . _("Officials return the completed scoresheet to the results headquarters.");
        $data = $this->pdfText($data);
        $this->EmptyColors(4);
        $this->WriteHTML($data);

    }
    public function PrintRoster($teamname, $seriesname, $poolname, $players)
    {
        $this->AddPage();

        $data = $teamname;
        $data .= " - ";
        $data .= _("Roster");
        $data = $this->pdfText($data);
        $this->HeaderColors(1);
        $this->Cell(0, 9, $data, 1, 1, 'C', true);

        $data = U_($seriesname);
        $data .= ", ";
        $data .= U_($poolname);
        $data .= ", ";
        $data .= _("Game") . " #:";
        $data = $this->pdfText($data);
        $this->EmptyColors(1);
        $this->Cell(0, 6, $data, 1, 1, 'L', true);

        $this->HeaderColors(4);

        $this->Cell(8, 6, "", 'LRTB', 0, 'C', true);
        $this->Cell(100, 6, $this->pdfText(_("Name")), 'LRTB', 0, 'C', true);
        $this->Cell(10, 6, $this->pdfText(_("Play")), 'LRTB', 0, 'C', true);
        $this->Cell(10, 6, $this->pdfText(_("Game #")), 'LRTB', 0, 'C', true);
        $this->Cell(62, 6, $this->pdfText(_("Info")), 'LRTB', 0, 'C', true);
        $this->Ln();
        $this->EmptyColors();
        for ($i = 1;$i < 26;$i++) {
            $player = "";

            if (isset($players[$i - 1]['firstname'])) {
                $player .= $this->pdfText($players[$i - 1]['firstname']);
            }
            $player .= " ";
            if (isset($players[$i - 1]['lastname'])) {
                $player .= $this->pdfText($players[$i - 1]['lastname']);
            }

            $this->EmptyColors();
            $this->Cell(8, 6, $i, 'LRTB', 0, 'C', true);

            if (isset($players[$i - 1]['accredited']) && !($players[$i - 1]['accredited'])) {
                $this->EmptyColors(3, null, 'IB');
            }

            $this->Cell(100, 6, $player, 'LRTB', 0, 'L', true);
            $this->EmptyColors();
            $this->Cell(10, 6, "", 'LRTB', 0, 'C', true);
            if (isset($players[$i - 1]['num']) && $players[$i - 1]['num'] >= 0) {
                $this->Cell(10, 6, $players[$i - 1]['num'], 'LRTB', 0, 'C', true);
            } else {
                $this->Cell(10, 6, "", 'LRTB', 0, 'C', true);
            }
            $this->Cell(62, 6, "", 'LRTB', 0, 'C', true);

            $this->Ln();
        }

        $this->Ln();

        //instructions
        $data = "<b>" . _("NOTICE") . " 1!</b> " . _("For new players added, accreditation id or date of birth must be written down.") . "<BR>";
        $data .= "<b>" . _("NOTICE") . " 2!</b> " . _("The team is responsible for the accreditation of <u>all</u> players on the list.") . "<BR>";
        $data .= "<b>" . _("NOTICE") . " 3! " . _("<b><i>Bold italic</i></b> printed players have problems with their license. They are <u>not</u> allowed to play until the problems are resolved (= payment receipt or note from the organizer shown).") . "";
        $data = $this->pdfText($data);
        $this->EmptyColors(4);
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
    public function DynSetTeamName($longname, $abbrev, $x, $fontsize)
    {
        $this->SetFont('Arial', 'B', $fontsize);
        $text = $this->pdfText($longname);
        if ($this->GetStringWidth($text) > $x - 2 && !empty($abbrev)) {
            $text = $this->pdfText($abbrev);
        }

        while ($this->GetStringWidth($text) > $x - 2) {
            $this->SetFont('Arial', '', --$fontsize);
        }

        return $text;
    }
    public function DynCell($width, $height, $pretext, $longname1, $abbrev1, $longname2, $abbrev2, $posttext, $x, $fontsize, $colors = false, $gamecolor = null)
    {
        $text1 = $this->pdfText($longname1);
        $fs1 = min($fontsize, $height / 3);
        $this->SetFont('Arial', '', $fs1);
        if ($this->GetStringWidth($text1) > $x - 2 && !empty($abbrev1)) {
            $text1 = $this->pdfText($abbrev1);
        }
        while ($this->GetStringWidth($text1) > $x - 2) {
            $this->SetFont('Arial', '', --$fs1);
        }

        $text2 = $this->pdfText($longname2);
        $fs2 = min($fontsize, $height / 3);
        $this->SetFont('Arial', '', $fs2);
        if ($this->GetStringWidth($text2) > $x - 2 && !empty($abbrev)) {
            $text1 = $this->pdfText($abbrev2);
        }
        while ($this->GetStringWidth($text2) > $x - 2) {
            $this->SetFont('Arial', '', --$fs2);
        }

        $text3 = $this->pdfText($pretext . " " . $posttext);
        $fs3 = min($fontsize, $height / 3);
        $this->SetFont('Arial', '', $fs3);
        while ($this->GetStringWidth($text3) > $x - 2) {
            $this->SetFont('Arial', '', --$fs2);
        }

        $fs4 = min(ceil($height), $fontsize);
        $this->SetFont('Arial', '', $fs4);
        $txt = $this->pdfText($pretext) . " ";
        if (!empty($abbrev1)) {
            $txt .= $this->pdfText($abbrev1);
        } else {
            $txt .= $this->pdfText($longname1);
        }
        $txt .= " - ";
        if (!empty($abbrev2)) {
            $txt .= $this->pdfText($abbrev2);
        } else {
            $txt .= $this->pdfText($longname2);
        }
        $txt .=  " (";
        $txt .= $this->pdfText($posttext);
        $txt .= ")";
        if ($this->GetStringWidth($txt) > -2) {
            $this->SetFont('Arial', '', --$fs4);
        }

        if ($colors) {
            $textcolor = $this->TextColor($gamecolor);
            $fillcolor = colorstring2rgb($gamecolor);

            $this->SetDrawColor($textcolor['r'], $textcolor['g'], $textcolor['b']);
            $this->SetFillColor($fillcolor['r'], $fillcolor['g'], $fillcolor['b']);
            $this->SetTextColor($textcolor['r'], $textcolor['g'], $textcolor['b']);
        } else {
            $this->SetTextColor(0);
            $this->SetFillColor(230);
            $this->SetDrawColor(0);
        }

        if ($fs4 > $fs1 || $fs4 > $fs2 || $fs4 > $fs3) {
            $this->Cell($width, $height, $txt, 0, 2, 'L', false);
        } else {
            $this->SetFont('Arial', '', min($fs1, $fs2, $fs3));
            $this->Cell($width, $height / 3, $text1, 0, 2, 'L', $colors);
            $this->Cell($width, $height / 3, $text2, 0, 2, 'L', $colors);
            $this->Cell($width, $height / 3, $this->pdfText($pretext . " " . $posttext), 0, 2, 'L', $colors);
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
            $txt = $this->pdfText($game['phometeamname']);
            $this->Cell(45 - $o, 5, $txt, 'TB', 0, 'L', true);
            $txt = " - ";
            $this->Cell(5, 5, $txt, 'TB', 0, 'L', true);
            $txt = $this->pdfText($game['pvisitorteamname']);
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
            if (GameHasStarted($game) && !intval($game['isongoing'])) {
                $txt = intval($game['homescore']);
                $this->Cell(5, 5, $txt, 'TB', 0, 'L', true);
                $txt = " - ";
                $this->Cell(5, 5, $txt, 'TB', 0, 'L', true);
                $txt = intval($game['visitorscore']);
                $this->Cell(5, 5, $txt, 'TB', 0, 'L', true);
            } else {
                $this->SetTextColor(0);
                $this->SetFillColor(255);
                $this->SetDrawColor(0);
                $this->Cell(8, 5, "", 'TB', 0, 'L', true);
                $this->SetDrawColor(0);
                $this->SetFillColor($fillcolor['r'], $fillcolor['g'], $fillcolor['b']);
                $this->SetTextColor($textcolor['r'], $textcolor['g'], $textcolor['b']);
                $txt = " - ";
                $this->Cell(5, 5, $txt, 'TB', 0, 'L', true);
                $this->SetTextColor(0);
                $this->SetFillColor(255);
                $this->SetDrawColor(0);
                $this->Cell(8, 5, "", 'TB', 0, 'L', true);
                $this->SetDrawColor(0);
                $this->SetFillColor($fillcolor['r'], $fillcolor['g'], $fillcolor['b']);
                $this->SetTextColor($textcolor['r'], $textcolor['g'], $textcolor['b']);

            }
        }

        //fill end of the row
        $this->Cell(0, 5, "", 'TB', 0, 'L', true);
        //$this->Write(6, $txt);

    }
    public function Timeouts()
    {
        //header
        $this->HeaderColors(2);
        $this->Cell(80, 6, $this->pdfText(_("Timeouts")), 'LRTB', 0, 'C', true);
        $this->Ln();

        //home grids
        $this->EmptyColors();
        $this->Cell(20, 6, $this->pdfText(_("Home")), 'LRTB', 0, 'L', true);

        for ($i = 0;$i < 4;$i++) {
            $this->Cell(15, 6, "", 'LRTB', 0, 'L', true);
        }

        $this->Ln();

        //visitor grids
        $this->EmptyColors();
        $this->Cell(20, 6, $this->pdfText(_("Away")), 'LRTB', 0, 'L', true);

        for ($i = 0;$i < 4;$i++) {
            $this->Cell(15, 6, "", 'LRTB', 0, 'L', true);
        }
        $this->Ln();
    }

    public function SpiritTimeouts()
    {
        //header
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255);
        $this->SetFillColor(0, 102, 153);
        $this->Cell(80, 6, $this->pdfText(_("Spirit stoppages")), 'LRTB', 0, 'C', true);
        $this->Ln();

        //home grids
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->Cell(20, 6, $this->pdfText(_("Home")), 'LRTB', 0, 'L', true);

        for ($i = 0;$i < 4;$i++) {
            $this->Cell(15, 6, "", 'LRTB', 0, 'L', true);
        }

        $this->Ln();

        //visitor grids
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->Cell(20, 6, $this->pdfText(_("Away")), 'LRTB', 0, 'L', true);

        for ($i = 0;$i < 4;$i++) {
            $this->Cell(15, 6, "", 'LRTB', 0, 'L', true);
        }
        $this->Ln();
    }
    public function FirstOffence()
    {
        //header
        $this->HeaderColors(2);
        $this->Cell(80, 6, $this->pdfText(_("Starting offensive team")), 'LRTB', 0, 'C', true);
        $this->Ln();

        //home grids
        $this->EmptyColors();
        $this->Cell(10, 6, "", 'LRTB', 0, 'L', true);
        $this->Cell(70, 6, $this->game['hometeamname'], 'LRTB', 0, 'L', true);
        $this->Ln();

        //visitor grids
        $this->EmptyColors();
        $this->Cell(10, 6, "", 'LRTB', 0, 'L', true);
        $this->Cell(70, 6, $this->game['visitorteamname'], 'LRTB', 0, 'L', true);
        $this->Ln();
    }
    public function SpiritPoints()
    {
        //header
        $this->HeaderColors(2);
        $this->Cell(80, 6, $this->pdfText(_("Spirit score")), 'LRTB', 0, 'C', true);
        $this->Ln();
        $this->EmptyColors();
        while ($this->GetStringWidth($this->game['hometeamname']) > 38) {
            $this->EmptyColors(4);
        }
        $this->Cell(40, 6, $this->game['hometeamname'], 'LRT', 0, 'C', true);


        $this->EmptyColors();
        while ($this->GetStringWidth($this->game['visitorteamname']) > 38) {
            $this->EmptyColors(4);
        }
        $this->Cell(40, 6, $this->game['visitorteamname'], 'LRT', 0, 'C', true);

        $this->Ln();
        $this->EmptyColors();
        $this->Cell(40, 6, "", 'LRB', 0, 'C', true);
        $this->Cell(40, 6, "", 'LRB', 0, 'C', true);
        $this->Ln();

    }
    public function Signatures()
    {
        //$this->Ln();
        //header
        $this->HeaderColors(2);
        $this->Cell(80, 6, $this->pdfText(_("Captains' signatures")), 'LRTB', 0, 'C', true);
        $this->Ln();

        //home grids
        $this->EmptyColors();
        $this->Cell(15, 8, $this->pdfText(_("Home")), 'LRTB', 0, 'L', true);
        $this->Cell(65, 8, "", 'LRTB', 0, 'L', true);

        $this->Ln();

        //visitor grids
        $this->EmptyColors();
        $this->Cell(15, 8, $this->pdfText(_("Away")), 'LRTB', 0, 'L', true);
        $this->Cell(65, 8, "", 'LRTB', 0, 'L', true);
        $this->Ln();
    }

    public function ScoreGrid()
    {
        $this->PreFilledColors(4);
        $this->SetX(100);
        $this->Cell(20, 4, $this->pdfText(_("Scoring team")), 'LRT', 0, 'C', true);
        $this->Cell(30, 4, $this->pdfText(_("Jersey numbers")), 'LRT', 0, 'C', true);
        $this->Ln();
        $this->SetX(100);
        $this->PreFilledColors(3);
        $this->Cell(10, 6, $this->pdfText(_("Home")), 'LRB', 0, 'C', true);
        $this->Cell(10, 6, $this->pdfText(_("Away")), 'LRB', 0, 'C', true);
        $this->Cell(15, 6, $this->pdfText(_("Assist")), 'LRB', 0, 'C', true);
        $this->Cell(15, 6, $this->pdfText(_("Goal")), 'LRB', 0, 'C', true);
        $this->Cell(25, 6, $this->pdfText(_("Time")), 'LRTB', 0, 'C', true);
        $this->Cell(25, 6, $this->pdfText(_("Scores")), 'LRTB', 0, 'C', true);
        $this->Ln();
        $this->EmptyColors();
        for ($i = 1;$i < 41;$i++) {
            $this->SetX(95);
            $this->SetFont('Arial', '', 8);
            $this->Cell(5, 6, $i, '', 0, 'C', true);
            $this->SetFont('Arial', '', 10);
            $this->Cell(10, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(10, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(15, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(15, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(25, 6, "", 'LRTB', 0, 'C', true);
            $this->Cell(25, 6, "-", 'LRTB', 0, 'C', true);
            $this->Ln();
        }
    }
    public function DefenseGrid()
    {
        $this->EmptyColors(4);
        //$this->SetX(100);
        //$this->Cell(24,4,$this->pdfText(_("Scoring team")),'LRT',0,'C',true);
        //$this->Cell(30,4,$this->pdfText(_("Jersey numbers")),'LRT',0,'C',true);
        //$this->Ln();
        $this->SetX(50);
        $this->EmptyColors(3);
        $this->Cell(12, 6, $this->pdfText(_("Home")), 'LRTB', 0, 'C', true);
        $this->Cell(12, 6, $this->pdfText(_("Away")), 'LRTB', 0, 'C', true);
        $this->Cell(15, 6, $this->pdfText(_("Player")), 'LRTB', 0, 'C', true);
        $this->Cell(15, 6, $this->pdfText(_("Touched")), 'LRTB', 0, 'C', true);
        $this->Cell(15, 6, $this->pdfText(_("Caught")), 'LRTB', 0, 'C', true);
        $this->Cell(15, 6, $this->pdfText(_("Callahan")), 'LRTB', 0, 'C', true);
        $this->Cell(25, 6, $this->pdfText(_("Time")), 'LRTB', 0, 'C', true);
        $this->Ln();
        $this->EmptyColors();
        for ($i = 1;$i < 31;$i++) {
            $this->SetX(45);
            $this->EmptyColors(4);
            $this->Cell(5, 6, $i, '', 0, 'C', true);
            $this->EmptyColors(3);
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
        $this->HeaderColors(2);
        $this->Cell(80, 6, $this->pdfText(_("Final score")), 'LRTB', 0, 'C', true);
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
        $this->Cell(38, 6, $this->game['visitorteamname'], 'RT', 0, 'C', true);

        $this->EmptyColors();
        $this->Ln();
        $this->Cell(80, 12, "", 'LRB', 0, 'C', true);
        $this->Ln();
    }
    public function HeaderColors($size = 2)
    {
        if ($size == 1) {
            $this->SetFont('Arial', 'B', 16);
        } elseif ($size == 2) {
            $this->SetFont('Arial', 'B', 12);
        } elseif ($size == 3) {
            $this->SetFont('Arial', 'B', 10);
        } else {
            $this->SetFont('Arial', 'B', 8);
        }
        $this->SetTextColor(255);
        $this->SetFillColor(127, 127, 127);
    }
    public function EmptyColors($size = 3, $family = null, $style = null)
    {
        if ($family == null) {
            $family = 'Arial';
        }
        if ($style == null) {
            $style = 'B';
        }
        if ($size == 1) {
            $this->SetFont($family, $style, 14);
        } elseif ($size == 2) {
            $this->SetFont($family, $style, 12);
        } elseif ($size == 3) {
            $this->SetFont($family, $style, 10);
        } else {
            $this->SetFont($family, $style, 8);
        }
        $this->SetTextColor(0);
        $this->SetFillColor(255);
    }

    public function PreFilledColors($size = 3, $family = null, $style = null)
    {
        if ($family == null) {
            $family = 'Arial';
        }
        if ($style == null) {
            $style = 'B';
        }

        if ($size == 1) {
            $this->SetFont($family, $style, 14);
        } elseif ($size == 2) {
            $this->SetFont($family, $style, 12);
        } elseif ($size == 3) {
            $this->SetFont($family, $style, 10);
        } elseif ($size < 1) {
            $this->SetFont($family, $style, 8 + $size);
        } else {
            $this->SetFont($family, $style, 8);
        }
        $this->SetTextColor(0);
        $this->SetFillColor(211, 211, 211);
    }
    public function OneCellTable($header,$data, $mode = null)
    {
        //header
        $this->HeaderColors();
        $this->Cell(80,6,$header,'LRTB',0,'C',true);
        $this->Ln();

        //data
        if ($mode == "fixed") {
            $this->PreFilledColors();
        } elseif ($mode == "empty") {
            $this->EmptyColors();
        } elseif ($data == "") {
            $this->EmptyColors();
        } else {
            $this->PreFilledColors();
        }
        $this->Cell(80, 6, $data, 'LRTB', 0, 'C', true);
        $this->Ln();
    }

    public function DoubleCellTable($header,$data)
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

    public function WriteHTML($html)
    {
        //HTML parser
        $html = str_replace("\n",' ',$html);
        $a = preg_split('/<(.*)>/U',$html,-1,PREG_SPLIT_DELIM_CAPTURE);
        foreach ($a as $i => $e) {
            if ($i % 2 == 0) {
                //Text
                if ($this->HREF) {
                    $this->PutLink($this->HREF,$e);
                } else {
                    $this->Write(4,$e);
                }
            } else {
                //Tag
                if ($e[0] == '/') {
                    $this->CloseTag(strtoupper(substr($e,1)));
                } else {
                    //Extract attributes
                    $a2 = explode(' ',$e);
                    $tag = strtoupper(array_shift($a2));
                    $attr = [];
                    foreach ($a2 as $v) {
                        if (preg_match('/([^=]*)=["\']?([^"\']*)/',$v,$a3)) {
                            $attr[strtoupper($a3[1])] = $a3[2];
                        }
                    }
                    $this->OpenTag($tag,$attr);
                }
            }
        }
    }

    public function OpenTag($tag,$attr)
    {
        //Opening tag
        if ($tag == 'B' || $tag == 'I' || $tag == 'U') {
            $this->SetStyle($tag,true);
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
            $this->SetStyle($tag,false);
        }
        if ($tag == 'A') {
            $this->HREF = '';
        }
    }
    public function SetStyle($tag,$enable)
    {
        //Modify style and select corresponding font
        $this->$tag += ($enable ? 1 : -1);
        $style = '';
        foreach (['B','I','U'] as $s) {
            if ($this->$s > 0) {
                $style .= $s;
            }
        }
        $this->SetFont('',$style);
    }

    public function PutLink($URL,$txt)
    {
        //Put a hyperlink
        $this->SetTextColor(0,0,255);
        $this->SetStyle('U',true);
        $this->Write(4,$txt,$URL);
        $this->SetStyle('U',false);
        $this->SetTextColor(0);
    }
}
