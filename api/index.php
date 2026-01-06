<?php
require_once __DIR__ . '/../lib/database.php';
OpenConnection();

require_once __DIR__ . '/../lib/season.functions.php';
require_once __DIR__ . '/../lib/series.functions.php';
require_once __DIR__ . '/../lib/pool.functions.php';
require_once __DIR__ . '/../lib/timetable.functions.php';
require_once __DIR__ . '/../lib/game.functions.php';
require_once __DIR__ . '/../lib/team.functions.php';
require_once __DIR__ . '/../lib/url.functions.php';
require_once __DIR__ . '/../lib/configuration.functions.php';
require_once __DIR__ . '/../lib/api.functions.php';

require_once __DIR__ . '/v1/router.php';

$parts = api_get_path_parts();
if (empty($parts)) {
  api_not_found();
}

$version = array_shift($parts);
switch ($version) {
  case 'v1':
    api_v1_route($parts);
    break;
  default:
    api_not_found();
}

CloseConnection();
