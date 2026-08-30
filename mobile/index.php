<?php

// mobile/index.php has no bootstrap of its own: a direct hit redirects to
// ?view=mobile/index via requireRoutedView() below, and index.php (which
// always defines UO_APP_SOURCE as "user" before routing) includes this file
// again to render it. Guard so that second pass does not redefine the
// constant; mobile mutations are attributed "user" like other routed views.
if (!defined('UO_APP_SOURCE')) {
    define('UO_APP_SOURCE', 'mobile');
}

require_once __DIR__ . '/../lib/view.guard.php';
requireRoutedView('mobile/index', '../index.php');

include_once 'lib/common.functions.php';
$html = "";

if (isset($_POST['login'])) {
    if (!isset($_SESSION['uid']) || $_SESSION['uid'] == "anonymous") {
        $html .= "<p class='warning'>" . _("Check the username and password.") . "</p>\n";
    } else {
        header("location:?view=mobile/respgames");
    }
} elseif (isset($_SESSION['uid']) && $_SESSION['uid'] != "anonymous") {
    header("location:?view=mobile/respgames");
}

mobilePageTop(_("Log in"));

// echo $html;

mobilePageEnd("view=mobile/respgames");
