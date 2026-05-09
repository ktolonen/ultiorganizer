<?php

require_once __DIR__ . '/../include_only.guard.php';
denyDirectCustomizationAccess(__FILE__);

include_once 'lib/tfpdf/tfpdf.php';
include_once 'lib/hsvclass/HSVClass.php';
include_once 'lib/phpqrcode/qrlib.php';

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

    private function onePageScheduleTimeslotKey($time)
    {
        return DefHourFormat($time);
    }

    private function onePageSchedulePageTitle($id, $game)
    {
        $seasonId = !empty($game['season']) ? $game['season'] : $id;
        $title = U_(SeasonName($seasonId));
        if (!empty($game['reservationgroup'])) {
            $title .= " - " . U_($game['reservationgroup']);
        }
        if (!empty($game['starttime'])) {
            $title .= " - " . DefWeekDateFormat($game['starttime']);
        }

        return $this->pdfText($title);
    }

    private function onePageScheduleFieldKey($game)
    {
        $field = trim((string) ($game['fieldname'] ?? ''));
        $location = "";
        if (!empty($game['place_id'])) {
            $location = "id:" . $game['place_id'];
        } elseif (trim((string) ($game['placename'] ?? '')) !== '') {
            $location = "name:" . trim((string) $game['placename']);
        }

        return $location . "|" . $field;
    }

    private function onePageScheduleFitCell($width, $height, $text, $border, $ln, $align, $style, $maxSize, $minSize = 6)
    {
        $text = $this->pdfText($text);
        $fontsize = $maxSize;
        $this->SetFont('Arial', $style, $fontsize);
        while ($this->GetStringWidth($text) > $width - 2 && $fontsize > $minSize) {
            $this->SetFont('Arial', $style, --$fontsize);
        }
        $this->Cell($width, $height, $text, $border, $ln, $align, false);
    }

    private function onePageScheduleFieldTitle($game)
    {
        return $this->pdfText(_("Field") . " " . $game['fieldname']);
    }

    private function onePageScheduleLocationTitle($game)
    {
        return $this->pdfText(U_($game['placename'] ?? ''));
    }

    private function onePageScheduleTimeslotsForDate($times, $currentGame, $games)
    {
        $timeslots = [];
        $dayTimes = [];
        $timeRows = [];
        $currentDate = JustDate($currentGame['starttime']);

        foreach ($times as $time) {
            if (strtotime(JustDate($time['time'])) > strtotime($currentDate)) {
                break;
            }
            if (strtotime(JustDate($time['time'])) < strtotime($currentDate)) {
                continue;
            }

            $timestamp = strtotime($time['time']);
            if ($timestamp !== false) {
                $timeRows[$timestamp] = $time;
            }
        }

        foreach ($games as $game) {
            if (trim((string) ($game['fieldname'] ?? '')) === '' || empty($game['time'])) {
                continue;
            }
            if ($game['reservationgroup'] != $currentGame['reservationgroup'] || JustDate($game['starttime']) != $currentDate) {
                continue;
            }

            $timestamp = strtotime($game['time']);
            if ($timestamp !== false && !isset($timeRows[$timestamp])) {
                $timeRows[$timestamp] = ['time' => $game['time']];
            }
        }

        ksort($timeRows);

        $i = 0;
        foreach ($timeRows as $time) {
            $offset = $i * 20;
            $timeslots[$time['time']] = $offset;
            $timeslots[$this->onePageScheduleTimeslotKey($time['time'])] = $offset;
            $dayTimes[] = $time;
            $i++;
        }

        return [$timeslots, $dayTimes];
    }

    public function PrintSchedule($scope, $id, $games)
    {
        $left_margin = 10;
        $top_margin = 10;
        //event title
        $this->SetAutoPageBreak(false, $top_margin);
        $this->SetMargins($left_margin, $top_margin);

        $this->AddPage();

        switch ($scope) {
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

        $this->SetAutoPageBreak(true, $top_margin);
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
        foreach ($games as $game) {
            $reservationGroup = isset($game['reservationgroup']) ? $game['reservationgroup'] : "";
            $placeId = isset($game['place_id']) ? $game['place_id'] : "";
            $placeName = trim((string) ($game['placename'] ?? ''));
            $fieldName = trim((string) ($game['fieldname'] ?? ''));
            $gameDate = !empty($game['starttime']) ? JustDate($game['starttime']) : "";

            if ($reservationGroup !== "" && $reservationGroup != $prevTournament) {
                $txt = $this->pdfText(U_($reservationGroup));
                $this->SetFont('Arial', 'B', 12);
                $this->SetTextColor(0);
                $this->Ln();
                $this->Write(5, $txt);
                $this->Ln();
                $prevDate = "";
            }

            if ($gameDate !== "" && $gameDate != $prevDate) {
                $txt = DefWeekDateFormat($game['starttime']);
                $this->SetFont('Arial', 'B', 10);
                $this->SetTextColor(0);
                $this->Ln();
                $this->Write(5, $txt);
            }

            if (($placeName !== "" || $fieldName !== "") && ($placeId != $prevPlace || $fieldName != $prevField || $gameDate != $prevDate)) {
                $txt = "";
                if ($placeName !== "") {
                    $txt = U_($placeName);
                }
                if ($fieldName !== "") {
                    $txt .= ($txt !== "" ? " " : "") . _("Field") . " " . U_($fieldName);
                }
                $txt = $this->pdfText($txt);

                $this->SetFont('Arial', '', 10);
                $this->SetTextColor(0);
                $this->Ln();
                $this->Cell(0, 5, $txt, 0, 2, 'L', false);
            }
            if ($placeName !== "" || $fieldName !== "") {
                $this->GameRowWithPool($game, false, true, false);
            } else {
                $this->GameRowWithPool($game, false, true, true);
            }
            if ($reservationGroup !== "" || $gameDate !== "") {
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

    public function PrintOnePageSchedule($scope, $id, $games, $colors = false)
    {
        $left_margin = 10;
        $top_margin = 15;
        $xarea = 400;
        $yarea = 270;
        $yfieldtitle = 8;
        $xtimetitle = 20;
        $ypagetitle = 8;

        //event title
        $this->SetAutoPageBreak(false, $top_margin);
        $this->SetMargins($left_margin, $top_margin);

        $timeslots = [];
        $times = [];
        $prevTournament = "";
        $prevPlace = "";
        $prevSeries = "";
        $prevPool = "";
        $prevTeam = "";
        $prevDate = "";
        $prevField = "";
        $fieldstotal = 0;

        $pageAdded = false;
        $gamePrinted = false;

        $field = 0;
        $time_offset = $top_margin + $ypagetitle + $yfieldtitle;
        $field_offset = 0;
        $gridx = 12;
        $gridy = 20;
        $fieldlimit = 15;

        $this->SetTextColor(255);
        $this->SetFillColor(0);
        $this->SetDrawColor(0);
        //print all games in order
        foreach ($games as $game) {
            // skip incomplete rows (no field/time) to avoid writing outside a page
            if (trim((string) ($game['fieldname'] ?? '')) === '' || empty($game['time'])) {
                continue;
            }

            //one reservation group per page
            if (!$pageAdded || $game['reservationgroup'] != $prevTournament
                || (!empty($prevDate) && JustDate($game['starttime']) != $prevDate)
            ) {
                $this->AddPage("L", "A3");
                $pageAdded = true;
                $this->SetXY($left_margin, $top_margin);
                $this->SetFont('Arial', 'B', 12);
                $this->SetTextColor(0);
                $this->Cell($xarea, $ypagetitle, $this->onePageSchedulePageTitle($id, $game), 0, 1, 'C', false);

                $times = TimetableTimeslots($game['reservationgroup'], $id);
                list($timeslots, $times) = $this->onePageScheduleTimeslotsForDate($times, $game, $games);
                $fieldstotal = TimetableFields($game['reservationgroup'], $id);
                $fieldlimit = max($fieldstotal / 2 + 1, 10);
                $gridx = $xarea / $fieldlimit;
                $field = 0;
                $prevField = "";
                $time_offset = $top_margin + $ypagetitle + $yfieldtitle;
            }

            //next field
            $fieldKey = $this->onePageScheduleFieldKey($game);
            if ($fieldKey != $prevField) {
                $field++;

                if ($field >= $fieldlimit) {
                    $field = 1;
                    $time_offset = $yarea / 2 + $top_margin + $ypagetitle + 2 * $yfieldtitle;
                }
                //write times
                if ($field == 1) {
                    $this->SetFont('Arial', 'B', 10);
                    $this->SetTextColor(0);
                    $this->SetXY($left_margin, $time_offset);

                    //write times
                    foreach ($times as $time) {
                        $txt = $this->pdfText(DefHourFormat($time['time']));
                        $this->Cell($xtimetitle, $gridy, $txt, 0, 2, 'L', false);
                    }
                }

                $field_offset = $left_margin + ($field - 1) * $gridx + $xtimetitle;
                $this->SetXY($field_offset, $time_offset - $yfieldtitle);

                $this->SetTextColor(0);
                $locationTitle = $this->onePageScheduleLocationTitle($game);
                if ($locationTitle !== '') {
                    $this->onePageScheduleFitCell($gridx, $yfieldtitle / 2, $locationTitle, "LRT", 2, 'C', '', 8);
                    $this->onePageScheduleFitCell($gridx, $yfieldtitle / 2, $this->onePageScheduleFieldTitle($game), "LR", 2, 'C', 'B', 10);
                } else {
                    $this->onePageScheduleFitCell($gridx, $yfieldtitle, $this->onePageScheduleFieldTitle($game), "LR", 2, 'C', 'B', 12);
                }
                //write grids
                foreach ($times as $time) {
                    $this->Cell($gridx, $gridy, "", 1, 2, 'L', false);
                }
            }

            $slot = $game['time'];
            if (!isset($timeslots[$slot])) {
                $slot = $this->onePageScheduleTimeslotKey($game['time']);
                if (!isset($timeslots[$slot])) {
                    continue;
                }
            }
            $gamePrinted = true;
            $this->SetXY($field_offset, $time_offset + $timeslots[$slot]);

            $this->SetTextColor(0);
            $this->SetFillColor(255);
            $this->SetDrawColor(0);
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(0);
            $this->Cell($gridx, 1, "", 0, 2, 'L', false);
            if ($game['hometeam'] && $game['visitorteam']) {
                $txt = $this->pdfText($game['hometeamname']);
                $this->DynSetTeamFont($txt, $gridx, 8);
                $this->Cell($gridx, 4, $txt, 0, 2, 'L', false);
                $txt = $this->pdfText($game['visitorteamname']);
                $this->DynSetTeamFont($txt, $gridx, 8);
                $this->Cell($gridx, 4, $txt, 0, 2, 'L', false);
            } else {
                $txt = $this->pdfText(U_($game['phometeamname']));
                $this->DynSetTeamFont($txt, $gridx, 8);
                $this->Cell($gridx, 4, $txt, 0, 2, 'L', false);
                $txt = $this->pdfText(U_($game['pvisitorteamname']));
                $this->DynSetTeamFont($txt, $gridx, 8);
                $this->Cell($gridx, 4, $txt, 0, 2, 'L', false);
            }
            $this->SetFont('Arial', '', 8);

            if ($colors) {
                $textcolor = $this->TextColor($game['color']);
                $fillcolor = colorstring2rgb($game['color']);

                $this->SetDrawColor($textcolor['r'], $textcolor['g'], $textcolor['b']);
                $this->SetFillColor($fillcolor['r'], $fillcolor['g'], $fillcolor['b']);
                $this->SetTextColor($textcolor['r'], $textcolor['g'], $textcolor['b']);
            } else {
                $this->SetTextColor(0);
                $this->SetFillColor(255);
                $this->SetDrawColor(0);
            }

            $this->Cell($gridx, 1, "", 0, 2, 'L', $colors);
            $txt = $this->pdfText($game['seriesname']);
            if (strlen($game['poolname']) < 15) {
                $txt .= ", \n";
            } else {
                $txt .= ", ";
            }
            $txt .= $this->pdfText($game['poolname']);
            //$this->DynSetFont($txt,$gridx,8);
            $this->MultiCell($gridx, 3, $txt, "LR", 2, 'L', $colors);

            $this->SetXY($field_offset, $time_offset + $timeslots[$slot]);
            $this->Cell($gridx, $gridy, "", "LRBT", 2, 'L', false);

            $prevTournament = $game['reservationgroup'];
            $prevPlace = $game['place_id'];
            $prevField = $fieldKey;
            $prevSeries = $game['series_id'];
            $prevPool = $game['pool'];
            $prevDate = JustDate($game['starttime']);
            $prevTime = DefHourFormat($game['starttime']);
        }

        if (!$gamePrinted) {
            if ($pageAdded) {
                $this->SetXY($left_margin, $top_margin);
                $this->SetFont('Arial', '', 12);
                $this->SetTextColor(0);
                $this->SetFillColor(255);
                $this->Cell(0, 8, $this->pdfText(_("No games") . "."), 0, 1, 'L', false);
            } else {
                $this->PrintError($this->pdfText(_("No games") . "."));
            }
        }
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
            $txt = $this->pdfText(U_($game['fieldname'] ?? ''));
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

    public function PrintSeasonPools($id)
    {
        $left_margin = 10;
        $top_margin = 10;
        $title = $this->pdfText(SeasonName($id));
        $series = SeasonSeries($id, true);

        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(255);
        $this->SetFillColor(0);
        $this->Cell(0, 9, $title, 1, 1, 'C', true);

        //print all series with color coding
        foreach ($series as $row) {

            if ($this->GetY() + 97 > 297) {
                $this->AddPage();
            }
            $name = $this->pdfText(U_($row['name']));
            $this->SetFont('Arial', 'B', 14);
            $this->SetTextColor(0);

            $this->Ln();
            $this->Write(6, $name);
            $this->Ln();
            $pools = SeriesPools($row['series_id'], false);
            $max_y = $this->PrintPools($pools);
            $this->SetXY($left_margin, $max_y);
        }
    }

    public function PrintSeriesPools($id)
    {

        $left_margin = 10;
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(255);
        $this->SetFillColor(0);
        $this->Cell(0, 9, "", 1, 1, 'C', true);

        if ($this->GetY() + 97 > 297) {
            $this->AddPage();
        }
        $name = $this->pdfText(U_(SeriesName($id)));
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0);

        $this->Ln();
        $this->Write(6, $name);
        $this->Ln();
        $pools = SeriesPools($id, false);
        $max_y = $this->PrintPools($pools);
        $this->SetXY($left_margin, $max_y);
    }

    public function PrintPools($pools)
    {

        $left_margin = 10;
        $top_margin = 10;
        $pools_x = $left_margin;
        $pools_y = $this->GetY();
        $max_y = $this->GetY();
        $i = 0;
        foreach ($pools as $pool) {

            $poolinfo = PoolInfo($pool['pool_id']);
            $teams = PoolTeams($pool['pool_id']);
            $scheduling_teams = false;

            if (!count($teams)) {
                $teams = PoolSchedulingTeams($pool['pool_id']);
                $scheduling_teams = true;
            }
            $name = $this->pdfText(U_($poolinfo['name']));

            if ($i % 6 == 0 && $i <= count($pools)) {
                $this->SetXY($left_margin, $max_y);
                $max_y = $this->GetY();
                $pools_y = $this->GetY();
                $pools_x = $left_margin;
            } else {
                $this->SetXY($pools_x, $pools_y);
            }

            //pool header
            $fontsize = 10;
            $this->SetFont('Arial', 'B', $fontsize);
            while ($this->GetStringWidth($name) > 28) {
                $this->SetFont('Arial', 'B', --$fontsize);
            }

            $this->SetTextColor(0);
            $this->SetFillColor(255);
            $this->SetDrawColor(0);
            $this->Cell(30, 5, $name, 1, 2, 'C', false);

            //pool teams

            $textcolor = $this->TextColor($poolinfo['color']);
            $fillcolor = colorstring2rgb($poolinfo['color']);

            $this->SetDrawColor($textcolor['r'], $textcolor['g'], $textcolor['b']);
            $this->SetFillColor($fillcolor['r'], $fillcolor['g'], $fillcolor['b']);
            $this->SetTextColor($textcolor['r'], $textcolor['g'], $textcolor['b']);

            foreach ($teams as $team) {
                $txt = $this->pdfText(U_($team['name']));
                $fontsize = 10;
                if ($scheduling_teams) {
                    $this->SetFont('Arial', 'i', $fontsize);
                } else {
                    $this->SetFont('Arial', '', $fontsize);
                }
                while ($this->GetStringWidth($txt) > 28) {
                    if ($scheduling_teams) {
                        $this->SetFont('Arial', 'i', --$fontsize);
                    } else {
                        $this->SetFont('Arial', '', --$fontsize);
                    }
                }
                $this->Cell(30, 5, $txt, '1', 2, 'L', true);
            }

            $pools_x += 31;
            if ($this->GetY() > $max_y) {
                $max_y = $this->GetY() + 1;
            }
            $i++;
        }
        return $max_y;
    }

    public function PrintError($text)
    {
        $this->AddPage();

        $this->SetFont('Arial', '', 12);
        $this->SetTextColor(0);
        $this->SetFillColor(255);
        $this->MultiCell(0, 8, $text);
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
