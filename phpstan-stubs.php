<?php

// PHPStan stubs for runtime-defined project constants.
//
// These are normally defined by conf/config.inc.php (per-installation, gitignored)
// or by third-party libraries we exclude from analysis. Listing them here lets
// PHPStan resolve references without polluting the rest of the analysis.
//
// Loaded via bootstrapFiles in phpstan.neon.dist.

if (!defined('CUSTOMIZATIONS')) {
    define('CUSTOMIZATIONS', 'default');
}

if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', '');
}

if (!defined('BASEURL')) {
    define('BASEURL', '');
}

if (!defined('DB_HOST')) {
    define('DB_HOST', '');
}

if (!defined('DB_USER')) {
    define('DB_USER', '');
}

if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', '');
}

if (!defined('DB_DATABASE')) {
    define('DB_DATABASE', '');
}

if (!defined('DATE_FORMAT')) {
    define('DATE_FORMAT', 'd.m.Y');
}

if (!defined('WORD_DELIMITER')) {
    define('WORD_DELIMITER', '/([\;\,\-_\s\/\.])/');
}

// FeedWriter format constant.
if (!defined('RSS2')) {
    define('RSS2', 'RSS2');
}
