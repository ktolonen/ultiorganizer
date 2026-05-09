<?php
include_once __DIR__ . '/auth.php';
include_once 'menufunctions.php';
include_once 'lib/privacy.functions.php';

$LAYOUT_ID = DBADMIN;
$title = _("Registered user privacy tools");
$html = "";
$message = "";
$search = isset($_POST['user_search']) ? trim((string) $_POST['user_search']) : "";
$matches = [];
$selectedUserId = isset($_POST['selected_user_id']) ? trim((string) $_POST['selected_user_id']) : "";
$selectedSubject = null;

if (!isSuperAdmin()) {
    Forbidden(isset($_SESSION['uid']) ? $_SESSION['uid'] : 'anonymous');
}

if ($selectedUserId !== '') {
    $selectedSubject = PrivacyGetUserSubject($selectedUserId);
}

if (!empty($_POST['search_users'])) {
    $matches = PrivacyUserMatches($search);
} elseif ($search !== '') {
    $matches = PrivacyUserMatches($search);
}

if (!empty($_POST['download_report'])) {
    if (!empty($selectedSubject)) {
        try {
            PrivacyLogUserReportExport($selectedUserId, $_SESSION['uid']);
            $report = PrivacyRenderUserReportText($selectedUserId, $_SESSION['uid']);
            PrivacyDownloadTextFile(PrivacyUserReportFilename($selectedUserId), $report);
        } catch (Exception $e) {
            $message = "<p class='warning'>" . _("Registered user privacy report could not be generated.") . "</p>";
        }
    } else {
        $message = "<p class='warning'>" . _("Select one registered user before downloading the privacy report.") . "</p>";
    }
} elseif (!empty($_POST['delete_user_data'])) {
    if (!empty($selectedSubject)) {
        try {
            PrivacyDeleteUserData($selectedUserId, $_SESSION['uid']);
            $message = "<p class='success'>" . _("Registered user data was deleted.") . "</p>";
            $selectedUserId = "";
            $selectedSubject = null;
            $matches = [];
        } catch (Exception $e) {
            $message = "<p class='warning'>" . _("Registered user data could not be deleted.") . "</p>";
        }
    } else {
        $message = "<p class='warning'>" . _("Select one registered user before deleting data.") . "</p>";
    }
}

pageTopHeadOpen($title);
?>
<script type="text/javascript">
	function confirmDeleteUserData() {
		return confirm('<?php echo addslashes(_("Are you sure you want to delete the selected registered user and all related privacy data, including matching logs? This action cannot be undone.")); ?>');
	}
</script>
<?php
pageTopHeadClose($title, false);
leftMenu($LAYOUT_ID);
contentStart();

$html .= "<p><a href='?view=admin/dbadmin'>&laquo; " . _("Return to Database administration") . "</a></p>";
$html .= "<h2>" . $title . "</h2>";
$html .= $message;

$html .= "<form method='post' action='?view=admin/privacyuser'>";
$html .= "<table>";
$html .= "<tr><td>" . _("User") . ":</td><td><input class='input' type='text' name='user_search' value='" . utf8entities($search) . "'/></td></tr>";
$html .= "<tr><td colspan='2'><input class='button' type='submit' name='search_users' value='" . _("Search") . "'/></td></tr>";
$html .= "</table>";
$html .= "</form>";

if ($search !== '') {
    $html .= "<h3>" . _("Search results") . "</h3>";
    if (empty($matches)) {
        $html .= "<p>" . _("No registered users matched the search.") . "</p>";
    } else {
        $html .= "<form method='post' action='?view=admin/privacyuser'>";
        $html .= "<input type='hidden' name='user_search' value='" . utf8entities($search) . "'/>";
        $html .= "<table>";
        $html .= "<tr><th></th><th>" . _("Name") . "</th><th>" . _("Username") . "</th><th>" . _("Email") . "</th><th>" . _("Last login") . "</th></tr>";
        foreach ($matches as $match) {
            $checked = $selectedUserId === $match['userid'] ? " checked='checked'" : "";
            $html .= "<tr>";
            $html .= "<td><input type='radio' name='selected_user_id' value='" . utf8entities($match['userid']) . "'" . $checked . "/></td>";
            $html .= "<td>" . utf8entities((string) $match['name']) . "</td>";
            $html .= "<td>" . utf8entities((string) $match['userid']) . "</td>";
            $html .= "<td>" . utf8entities((string) $match['email']) . "</td>";
            $html .= "<td>" . utf8entities(LongTimeFormat($match['last_login'])) . "</td>";
            $html .= "</tr>";
        }
        $html .= "</table>";
        $html .= "<p><input class='button' type='submit' name='select_user' value='" . _("Select") . "'/></p>";
        $html .= "</form>";
    }
}

if (!empty($selectedSubject)) {
    $user = $selectedSubject['user'];
    $html .= "<h3>" . _("Selected user") . "</h3>";
    $html .= "<p>" . utf8entities($user['name']) . " (" . utf8entities($user['userid']) . ")</p>";
    $html .= "<form method='post' action='?view=admin/privacyuser'>";
    $html .= "<input type='hidden' name='user_search' value='" . utf8entities($search) . "'/>";
    $html .= "<input type='hidden' name='selected_user_id' value='" . utf8entities($selectedUserId) . "'/>";
    $html .= "<input class='button' type='submit' name='download_report' value='" . _("Download registered user privacy report") . "'/> ";
    $html .= "<input class='button' type='submit' name='delete_user_data' value='" . _("Delete registered user data") . "' onclick='return confirmDeleteUserData();'/>";
    $html .= "</form>";
}

echo $html;
contentEnd();
pageEnd();
?>
