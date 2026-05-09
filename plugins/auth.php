<?php

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit();
}

function pluginRedirectToIndex($file)
{
    $view = 'plugins/' . basename($file, '.php');
    $location = '../index.php?view=' . urlencode($view);
    if (!empty($_SERVER['QUERY_STRING'])) {
        $location .= '&' . $_SERVER['QUERY_STRING'];
    }
    header('Location: ' . $location);
    exit();
}

function pluginRequireAdmin($file)
{
    if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath($file)) {
        pluginRedirectToIndex($file);
    }

    include_once __DIR__ . '/../admin/auth.php';
}
