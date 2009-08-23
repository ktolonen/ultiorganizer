<?php
include_once 'view_ids.inc.php';
include_once 'lib/database.php';
include_once 'lib/season.functions.php';
include_once 'lib/serie.functions.php';
include_once 'builder.php';
$LAYOUT_ID = HOME;

//common page
pageTop();
leftMenu($LAYOUT_ID);
contentStart();

//content
echo "<h2>"._("Kirjautuminen ep&auml;onnistui")."</h2>
     <p>"._("Tarkista k&auml;ytt&auml;j&auml;tunnus ja salasana.")." "._("Ota tarvittaessa yhteytt&auml; liitokiekkoliiton")."
     <a href='mailto:"._("sarjavastaava@liitokiekkoliitto.fi")."'>"._("sarjavastaavaan")."</a>.
     </p>";

contentEnd();
pageEnd();
?>