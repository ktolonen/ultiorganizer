<?php
$locale = "en_GB.utf8";
if (isSet($_GET["locale"])) $locale = $_GET["locale"];
putenv("LC_ALL=$locale");
setlocale(LC_ALL, $locale);
bindtextdomain("messages", "./locale");
textdomain("messages");
?>
