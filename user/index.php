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
<h2>Sis&auml;&auml;nkirjautuminen onnistui!</h2>
<p>Valitse haluttu toiminto:</p>
<a href='userinfo.php'>&raquo; Omat tiedot</a><br/>
<a href='teamplayers.php'>&raquo; Pelaajalista</a><br/>
<a href='respgames.php'>&raquo; Vastuupelit</a><br/>
<?php
contentEnd();
pageEnd();
?>