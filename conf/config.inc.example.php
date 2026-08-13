<?php

/**
 * Required database connection.
 *
 * Get these values from your hosting provider or local database setup. They
 * are used before the application can read any server settings from the
 * database.
 */
define('DB_HOST', 'localhost');
define('DB_USER', 'ultiorganizer');
define('DB_PASSWORD', 'ultiorganizer');
define('DB_DATABASE', 'ultiorganizer');

/**
 * Required public URL and uploaded-file location.
 *
 * BASEURL must be the browser-visible root URL without a trailing slash.
 * UPLOAD_DIR is a web-readable directory, relative to the application root,
 * used for generated and uploaded images.
 */
define('BASEURL', 'http://localhost/ultiorganizer');
define('UPLOAD_DIR', 'images/uploads/');

/**
 * Writable directory for automatic database-upgrade state and locks.
 *
 * Left undefined on purpose: the path is then derived from this installation's
 * own directory. Installations sharing the directory share one maintenance flag
 * and one upgrade lock, so define it only if you need to, and give each
 * installation on the server a unique path.
 */
// define('MAINTENANCE_RUNTIME_DIR', '/tmp/ultiorganizer-maintenance');

/**
 * Writable directory for short-lived cross-request cache files.
 *
 * Installations may share it: cache files go into a per-install subdirectory
 * keyed on the database connection. Set to '' to disable the filesystem cache
 * even when the admin cache toggle is enabled.
 */
define('PERSISTENT_CACHE_DIR', '/tmp/ultiorganizer-cache');

/**
 * Session cookie name.
 *
 * Left undefined on purpose: the name is then derived from this installation's
 * own directory. Installations sharing a domain must not share a cookie name, or
 * a login on one is picked up by the other, so define it only if you need to, and
 * give each installation on the domain a unique name. Only letters, digits, '-'
 * and '_' are accepted, and the name must not be all digits.
 */
// define('UO_SESSION_NAME', 'UO_SESSID');

/**
 * Site customization and localization defaults.
 *
 * CUSTOMIZATIONS selects the cust/<id>/ skin and override files. DATE_FORMAT
 * is the accepted user-facing date/time input format. WORD_DELIMITER controls
 * how translation search/autocomplete splits words.
 */
define('CUSTOMIZATIONS', 'slkl');
define('DATE_FORMAT', _("%d.%m.%Y %H:%M"));
define('WORD_DELIMITER', '/([\;\,\-_\s\/\.])/');

/**
 * Operational safety flags.
 *
 * Keep ENABLE_ADMIN_DB_ACCESS disabled in production; enabling it gives
 * superadmins direct SQL access. ALLOW_INSTALL should be true only while
 * running install.php. ANONYMOUS_RESULT_INPUT allows unauthenticated result
 * entry through the scorekeeper result view.
 */
define('ENABLE_ADMIN_DB_ACCESS', 'disabled');
define('ALLOW_INSTALL', false);
define('ANONYMOUS_RESULT_INPUT', false);

/**
 * Public account and email policy.
 *
 * DISABLE_SELF_REGISTRATION means only admins can add registered users.
 * NO_EMAIL disables outbound email and also disables public self-registration.
 */
define('DISABLE_SELF_REGISTRATION', false);
define('NO_EMAIL', false);

/**
 * API rate limiting.
 *
 * API_RATE_LIMIT is the maximum number of requests allowed per token and client
 * IP in each API_RATE_WINDOW, measured in seconds.
 */
define('API_RATE_LIMIT', 120);
define('API_RATE_WINDOW', 60);

/**
 * Available interface languages.
 *
 * The array key is the locale directory under locale/, and the value is the
 * human-readable language name shown to admins and users.
 */
global $locales;
$locales = [
    "de_DE.utf8" => "Deutsch",
    "en_GB.utf8" => "English",
    "es_ES.utf8" => "Español",
    "fi_FI.utf8" => "Suomi",
];
