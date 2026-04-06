<?php

function requireRoutedView($view)
{
  if (defined('UO_ROUTED_VIEW')) {
    return;
  }

  $location = 'index.php?view=' . urlencode($view);
  if (!empty($_SERVER['QUERY_STRING'])) {
    $location .= '&' . $_SERVER['QUERY_STRING'];
  }
  header('Location: ' . $location);
  exit();
}
