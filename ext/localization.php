<?php

include_once __DIR__ . '/../lib/database.php';

OpenConnection();
global $include_prefix;
$include_prefix = "../";
include_once __DIR__ . '/../lib/translation.functions.php';
//include_once '../lib/configuration.functions.php';
include_once __DIR__ . '/../localization.php';

//session_start();
setSessionLocale();
