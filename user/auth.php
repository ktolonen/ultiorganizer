<?php

if (!isset($include_prefix)) {
    $include_prefix = __DIR__ . '/../';
}

include_once $include_prefix . 'lib/view.guard.php';
if (!defined('UO_ROUTED_VIEW')) {
    $view = routedViewFromScript($include_prefix);
    if ($view !== '') {
        requireRoutedView($view, '../index.php');
    }
}

include_once $include_prefix . 'lib/auth.guard.php';
