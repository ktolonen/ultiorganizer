<?php
/**
* MySQL Settings - you can get this information from your web hosting company.
*/
define('DB_HOST', 'localhost');
define('DB_USER', 'ultiorganizer');
define('DB_PASSWORD', 'ultiorganizer');
define('DB_DATABASE', 'ultiorganizer');

/**
* Server Defaults.
*/
define('BASEURL', 'http://localhost/ultiorganizer');
define('UPLOAD_DIR', 'images/uploads/');
define('CUSTOMIZATIONS', 'slkl');
define('DATE_FORMAT', _("%d.%m.%Y %H:%M"));
define('WORD_DELIMITER', '/([\;\,\-_\s\/\.])/');

define('ENABLE_ADMIN_DB_ACCESS', 'disabled');
define('ALLOW_INSTALL', false);

/**
* API rate limiting.
* API_RATE_LIMIT is the max number of requests allowed per window.
* API_RATE_WINDOW is the window length in seconds.
* The limit is enforced per token + client IP.
*/
define('API_RATE_LIMIT', 120);
define('API_RATE_WINDOW', 60);

global $locales;
$locales = array("en_GB.utf8" => "English", "fi_FI.utf8" => "Suomi");
?>
