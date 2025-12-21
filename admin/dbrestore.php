<?php
include_once __DIR__ . '/auth.php';
include_once 'menufunctions.php';
include_once 'lib/club.functions.php';
include_once 'lib/reservation.functions.php';
$html = "";
if (!isSuperAdmin()) {
	Forbidden(isset($_SESSION['uid']) ? $_SESSION['uid'] : 'anonymous');
}

if (!defined('ENABLE_ADMIN_DB_ACCESS') || constant('ENABLE_ADMIN_DB_ACCESS') != "enabled") {
	$html = "<p>" . _("Direct database access is disabled. To enable it, define(ENABLE_ADMIN_DB_ACCESS,'enabled') in the config.inc.php file") . "</p>";
} else {
	if (isset($_POST['restore']) && isSuperAdmin()) {
			if (is_uploaded_file($_FILES['restorefile']['tmp_name'])) {
				$filenameParts = explode('.', $_FILES['restorefile']['name']);
				$extension = strtolower(end($filenameParts));

				if ("gz" == $extension) {
					$lines = gzfile($_FILES['restorefile']['tmp_name']);
				} elseif ("sql" == $extension) {
					$lines = file($_FILES['restorefile']['tmp_name']);
				} else {
					$lines = null;
					$html .= "<p>" . _("Unsupported backup format. Please upload a .gz or .sql file.") . "</p>";
				}

				if (is_array($lines)) {
					$templine = '';
					set_time_limit(300);

					foreach ($lines as $line) {
						// Skip it if it's a comment
						if (substr($line, 0, 2) == '--' || $line == '')
							continue;

						$templine .= $line;
						if (substr(trim($line), -1, 1) == ';') {
							DBQuery($templine);
							$templine = '';
						}
					}
					unlink($_FILES['restorefile']['tmp_name']);
					unset($_SESSION['dbversion']);
					$html .= "<p>" . _("Restore") . "</p>";
				}
			}

	}

	if (isSuperAdmin()) {
		ini_set("post_max_size", "30M");
		ini_set("upload_max_filesize", "30M");
		ini_set("memory_limit", -1);

		$html .= "<form method='post' enctype='multipart/form-data' action='?view=admin/dbrestore'>\n";

		$html .= "<p><span class='profileheader'>" . _("Select backup to restore") . ": </span></p>\n";

		$html .= "<p><input class='input' type='file' size='80' name='restorefile'/>";
		$html .= "<input type='hidden' name='MAX_FILE_SIZE' value='100000000'/></p>";
		$html .= "<p><input class='button' type='submit' name='restore' value='" . _("Restore") . "'/>";
		$html .= "<input class='button' type='button' name='takaisin'  value='" . _("Return") . "' onclick=\"window.location.href='?view=admin/dbadmin'\"/></p>";
		$html .= "</form>";
	} else {
		$html .= "<p>" . _("User credentials does not match") . "</p>\n";
	}
}
//common page
$title = _("Database backup");
$LAYOUT_ID = DBRESTORE;
pageTopHeadOpen($title);
include 'script/common.js.inc';
pageTopHeadClose($title, false);
leftMenu($LAYOUT_ID);
contentStart();
echo $html;

contentEnd();
pageEnd();
