<?php

require_once __DIR__ . '/../include_only.guard.php';
denyDirectCustomizationAccess(__FILE__);

function logo()
{
    global $include_prefix;
    return "<div><a href='https://github.com/ktolonen/ultiorganizer/'><img class='logo' src='" . $include_prefix . "cust/default/logo-big.png'/></a></div>";
}

function pageHeader()
{
    global $include_prefix;
    return "<a href='https://github.com/ktolonen/ultiorganizer' class='header_text'><img class='header_logo' style='width:auto;height:40px;' src='" . $include_prefix . "cust/default/logo.png' alt=''/>" . _("Ultiorganizer") . "</a><br/>\n";
}
