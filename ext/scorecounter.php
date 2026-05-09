<?php
include_once __DIR__ . '/localization.php';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='fi' lang='fi'>

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta http-equiv="Pragma" content="no-cache" />
	<meta http-equiv="Expires" content="-1" />
	<?php
    include_once __DIR__ . '/../lib/common.functions.php';
include_once __DIR__ . '/../lib/game.functions.php';
include_once __DIR__ . '/../lib/season.functions.php';
include_once __DIR__ . '/../lib/series.functions.php';
include_once __DIR__ . '/../lib/team.functions.php';
include_once __DIR__ . '/../lib/timetable.functions.php';

echo "<title>" . _("Ultiorganizer Score counter") . "</title>";
?>
</head>

<body>
	<?php

$season = iget("season");

$lenght = 6;
if (iget("numbers")) {
    $lenght = intval(iget("numbers"));
}
echo "<table><tr>";
$scores = (string) SeasonScoreCounter($season);

$chars = str_split($scores);
for ($i = count($chars); $i < $lenght; $i++) {
    echo "<td>0</td>";
}
foreach ($chars as $char) {
    echo "<td class='center' style='width:10px'>$char</td>";
}

echo "</tr></table>";


CloseConnection();
?>
</body>

</html>
