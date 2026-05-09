<?php

require_once __DIR__ . '/../include_only.guard.php';
denyDirectCustomizationAccess(__FILE__);

function logo()
{
    return "";
}

function pageHeader()
{
    global $styles_prefix;
    global $include_prefix;
    if (!isset($styles_prefix)) {
        $styles_prefix = $include_prefix;
    }
    return "<a href='http://localhost' class='header_text'><img class='header_logo' src='" . $styles_prefix . "/cust/default/head_logo_fpudd_600_120.png' alt='FPUDD' width=600/></a><br/>\n";
}
