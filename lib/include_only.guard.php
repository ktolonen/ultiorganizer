<?php

function denyDirectFileAccess($file)
{
    $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
    if ($scriptFilename === '') {
        return;
    }

    if (realpath($scriptFilename) !== realpath($file)) {
        return;
    }

    http_response_code(404);
    exit();
}

function denyDirectLibAccess($file)
{
    denyDirectFileAccess($file);
}

denyDirectFileAccess(__FILE__);
