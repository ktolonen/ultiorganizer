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

	echo "<tr><td>Hallinto:</td></tr>";
	echo "<tr><td style='padding-left:5px'>\n";
	echo "<a href='".$linklevel."serieformats.php'>&raquo; Sarjaformaatit</a><br/>\n";
	echo "<a href='".$linklevel."places.php'>&raquo; Pelipaikat</a><br/>\n";
	echo "<a href='".$linklevel."seasons.php'>&raquo; Kaudet</a><br/>\n";
	echo "</td></tr>\n";
	echo "<tr><td>Nykyinen kausi:</td></tr>";
	echo "<tr><td style='padding-left:5px'>\n";
	echo "<a href='".$linklevel."seasonseries.php?Season=$curSeason'>&raquo; Sarjat</a><br/>\n";
	echo "<a href='".$linklevel."seasonteams.php?Season=$curSeason'>&raquo; Joukkueet</a><br/>\n";
	echo "<a href='".$linklevel."seasonplaces.php?Season=$curSeason'>&raquo; Paikat</a><br/>\n";
	echo "<a href='".$linklevel."seasongames.php?Season=$curSeason'>&raquo; Pelit</a><br/>\n";
	echo "</td></tr>\n";
	echo "<tr><td>Uusi kausi:</td></tr>";
	echo "<tr><td style='padding-left:5px'>\n";
	echo "<a href='".$linklevel."userinfo.php'>&raquo; Sarjat</a><br/>\n";
	echo "<a href='".$linklevel."teamplayers.php'>&raquo; Joukkueet</a><br/>\n";
	echo "<a href='".$linklevel."respgames.php'>&raquo; Paikat</a><br/>\n";
	echo "<a href='".$linklevel."respgames.php'>&raquo; Pelit</a><br/>\n";
	echo "</td></tr>\n";

	}
?>