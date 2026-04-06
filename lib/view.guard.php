<?php
require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

function requireRoutedView($view, $indexPath = 'index.php')
{
  if (defined('UO_ROUTED_VIEW')) {
    return;
  }

  $location = $indexPath . '?view=' . urlencode($view);
  if (!empty($_SERVER['QUERY_STRING'])) {
    $location .= '&' . $_SERVER['QUERY_STRING'];
  }
  header('Location: ' . $location);
  exit();
}
