<?php
include_once __DIR__ . '/auth.php';
ClearUserSessionData();
header("location:?view=login");
