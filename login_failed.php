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
?>
<h2>Kirjautuminen ep&auml;onnistui</h2>
<p>
Tarkista k&auml;ytt&auml;j&auml;tunnus ja salasana. Ota tarvittaessa yhteytt&auml; liitokiekkoliiton <a href='mailto:sarjavastaava@liitokiekkoliitto.fi'>sarjavastaavaan</a>.
</p>
<?php
contentEnd();
pageEnd();
?>