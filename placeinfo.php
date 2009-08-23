<?php
include_once 'view_ids.inc.php';
include_once 'lib/database.php';
include_once 'lib/place.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/serie.functions.php';
include_once 'builder.php';
$LAYOUT_ID = PLACEINFO;

//common page
pageTop();
leftMenu($LAYOUT_ID);
contentStart();
OpenConnection();
$placeId = intval($_GET["Place"]);
$place = PlaceInfo($placeId);

echo "<h1>". htmlentities($place['paikka']) ."</h1>\n";
echo "<p>". htmlentities($place['info']) ."</p>\n";
echo "<p></p>";
echo "<p>"._("Suhtaudu karttaan varauksella, sill&auml; karttalinkki on paras arvaus eik&auml; perustu j&auml;rjest&auml;jien toimittamiin koordinaatteihin.")."</p><p></p>\n";
$maplink = $place['info'];

echo "<iframe width='425' height='350' frameborder='0' scrolling='no' marginheight='0' marginwidth='0' 
	src='http://maps.google.fi/maps?f=q&amp;source=s_q&amp;hl=fi&amp;geocode=&amp;q=".$maplink."&amp;ie=UTF8&amp;output=embed'>
	</iframe><br/><small>
	<a href='http://maps.google.fi/maps?f=q&amp;source=embed&amp;hl=fi&amp;geocode=&amp;q=".$maplink."&amp;ie=UTF8' 
	>"._("N&auml;yt&auml; suurempi kartta")."</a></small>";

CloseConnection();

echo "<hr/><p><a href="javascript:history.go(-1);">"._("Palaa")."</a></p>";

contentEnd();
pageEnd();
?>
