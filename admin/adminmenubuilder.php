<?php 

function adminMenu($id)
	{
	$linklevel = "admin/";
	
	if($id >= 200 && $id<300)
		{
		$linklevel = "../admin/";
		}
	elseif($id >= 300 && $id<400)
		{
		$linklevel = "";
		}	
	elseif($id >= 400 && $id<500)
		{
		$linklevel = "../admin/";
		}
	$curSeason=CurrenSeason();

	echo "<tr><td>"._("Hallinto").":</td></tr>";
	echo "<tr><td style='padding-left:5px'>\n";
	echo "<a href='".$linklevel."serieformats.php'>&raquo; "._("Sarjaformaatit")."</a><br/>\n";
	echo "<a href='".$linklevel."places.php'>&raquo; "._("Pelipaikat")."</a><br/>\n";
	echo "<a href='".$linklevel."seasons.php'>&raquo; "._("Kaudet")."</a><br/>\n";
	echo "</td></tr>\n";
	echo "<tr><td>"._("Nykyinen kausi").":</td></tr>";
	echo "<tr><td style='padding-left:5px'>\n";
	echo "<a href='".$linklevel."seasonseries.php?Season=$curSeason'>&raquo; "._("Sarjat")."</a><br/>\n";
	echo "<a href='".$linklevel."seasonteams.php?Season=$curSeason'>&raquo; "._("Joukkueet")."</a><br/>\n";
	echo "<a href='".$linklevel."seasonplaces.php?Season=$curSeason'>&raquo; "._("Paikat")."</a><br/>\n";
	echo "<a href='".$linklevel."seasongames.php?Season=$curSeason'>&raquo; "._("Pelit")."</a><br/>\n";
	echo "</td></tr>\n";
	echo "<tr><td>"._("Uusi kausi").":</td></tr>";
	echo "<tr><td style='padding-left:5px'>\n";
	echo "<a href='".$linklevel."userinfo.php'>&raquo; "._("Sarjat")."</a><br/>\n";
	echo "<a href='".$linklevel."teamplayers.php'>&raquo; "._("Joukkueet")."</a><br/>\n";
	echo "<a href='".$linklevel."respgames.php'>&raquo; "._("Paikat")."</a><br/>\n";
	echo "<a href='".$linklevel."respgames.php'>&raquo; "._("Pelit")."</a><br/>\n";
	echo "</td></tr>\n";

	}
?>
