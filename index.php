<?php
include_once 'view_ids.inc.php';
include_once 'builder.php';
$LAYOUT_ID = HOME;

//common page
pageTop();
leftMenu($LAYOUT_ID);
contentStart();

//content
?>
<h1>Tervetuloa liitokiekkoliiton pelikoneeseen</h1>
<p>
Mik&auml;li olet joukkueen yhteyshenkil&ouml;, niin anna viereisess&auml; oleviin kenttiin k&auml;ytt&auml;j&auml;tunnuksesi
ja salasanasi, jotta tied&auml;mme mink&auml; joukkueen edustaja olet.
</p>
<?php
contentEnd();
pageEnd();
?>