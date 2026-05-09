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

function routedViewFromScript($baseDir)
{
    $baseDirReal = realpath($baseDir);
    $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';

    if ($baseDirReal !== false && $scriptFilename !== '') {
        $scriptReal = realpath($scriptFilename);
        $basePrefix = rtrim($baseDirReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if ($scriptReal !== false && strpos($scriptReal, $basePrefix) === 0) {
            $view = substr($scriptReal, strlen($basePrefix));
            $view = str_replace(DIRECTORY_SEPARATOR, '/', $view);
            return preg_replace('/\.php$/i', '', $view);
        }
    }

    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
    $view = ltrim(preg_replace('/\.php$/i', '', $scriptName), '/');
    $parts = explode('/', $view);
    foreach (['admin', 'user'] as $root) {
        $offset = array_search($root, $parts, true);
        if ($offset !== false) {
            return implode('/', array_slice($parts, $offset));
        }
    }

    return $view;
}
