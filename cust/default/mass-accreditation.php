<?php
echo "<h3>"._("Mass accreditation")."</h3>";
echo "<table><tr>";
$seriesResults = SeasonSeries($season);
foreach($seriesResults as $row) {
	echo "<td>\n";
	echo "<h3>".utf8entities($row['name'])."</h3>\n";
	echo "<form method='post' action='?view=admin/accreditation&amp;season=".$season."'>\n";
	echo "<textarea name='accrIds'></textarea><br/>\n";
	echo "<input type='hidden' name='series' value='".$row['series_id']."'/>\n";
	echo "<input type='submit' name='accredit' value='"._("Accredit")."'/>\n";
	echo "</form>\n";
	echo "</td>\n";
}
echo "</tr></table>\n";
?>