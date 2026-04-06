<?php
include_once __DIR__ . '/auth.php';
spiritkeeperRequireAuth(__FILE__, 'logout');

ClearUserSessionData();
header("location:?view=login");
?>
