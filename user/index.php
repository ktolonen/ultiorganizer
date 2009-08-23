<?php
include_once 'view_ids.inc.php';
include_once 'builder.php';
$LAYOUT_ID = HOME;

//common page
pageTop(false);
leftMenu($LAYOUT_ID);
contentStart();

//content
echo "
     <h2>"._("Sis&auml;&auml;nkirjautuminen onnistui")."!</h2>
     <p>"._("Valitse haluttu toiminto").":</p>
     <a href='userinfo.php'>&raquo; "._("Omat tiedot")."</a><br/>
     <a href='teamplayers.php'>&raquo; "._("Pelaajalista")."</a><br/>
     <a href='respgames.php'>&raquo; "._("Vastuupelit")."</a><br/>";

contentEnd();
pageEnd();
?>