<?php
include_once 'view_ids.inc.php';
include_once 'builder.php';
$LAYOUT_ID = HOME;

//common page
pageTop(false);
leftMenu($LAYOUT_ID);
contentStart();

//content
?>
<h2>Sis&auml;&auml;nkirjautuminen onnistui!</h2>
<p>Valitse haluttu toiminto:</p>
<a href='../user/userinfo.php'>&raquo; Omat tiedot</a><br/>
<a href='../user/teamplayers.php'>&raquo; Pelaajalista</a><br/>
<a href='../user/respgames.php'>&raquo; Vastuupelit</a><br/>
<?php
contentEnd();
pageEnd();
?>