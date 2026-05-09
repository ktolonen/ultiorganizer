<?php
include_once __DIR__ . '/auth.php';
pluginRequireAdmin(__FILE__);

ob_start();
?>
<!--
[CLASSIFICATION]
category=database
type=updater
format=any
security=superadmin

[DESCRIPTION]
title = "Licenses"
description = "Update details in uo_license"
-->
<?php
ob_end_clean();
if (!isSuperAdmin()) {
    die('Insufficient user rights');
}

$html = "";
$title = ("Licenses");
$accId = isset($_GET["accid"]) ? intval($_GET["accid"]) : 0;
$cleanupMessage = "";

$cleanupCandidatesQuery = "
  SELECT COUNT(*)
  FROM uo_license l
  LEFT JOIN uo_player_profile pp ON pp.accreditation_id = l.accreditation_id
  LEFT JOIN (
    SELECT DISTINCT p.accreditation_id
    FROM uo_player p
    INNER JOIN uo_played up ON up.player = p.player_id
    WHERE p.accreditation_id IS NOT NULL AND p.accreditation_id <> '' AND p.accreditation_id <> '0'
  ) played ON played.accreditation_id = l.accreditation_id
  LEFT JOIN (
    SELECT DISTINCT p.accreditation_id
    FROM uo_player p
    WHERE p.accreditation_id IS NOT NULL AND p.accreditation_id <> '' AND p.accreditation_id <> '0'
  ) players ON players.accreditation_id = l.accreditation_id
  WHERE l.accreditation_id IS NOT NULL AND l.accreditation_id <> '' AND l.accreditation_id <> '0'
    AND pp.profile_id IS NULL
    AND played.accreditation_id IS NULL
    AND players.accreditation_id IS NULL";

$cleanupDeleteQuery = "
  DELETE l
  FROM uo_license l
  LEFT JOIN uo_player_profile pp ON pp.accreditation_id = l.accreditation_id
  LEFT JOIN (
    SELECT DISTINCT p.accreditation_id
    FROM uo_player p
    INNER JOIN uo_played up ON up.player = p.player_id
    WHERE p.accreditation_id IS NOT NULL AND p.accreditation_id <> '' AND p.accreditation_id <> '0'
  ) played ON played.accreditation_id = l.accreditation_id
  LEFT JOIN (
    SELECT DISTINCT p.accreditation_id
    FROM uo_player p
    WHERE p.accreditation_id IS NOT NULL AND p.accreditation_id <> '' AND p.accreditation_id <> '0'
  ) players ON players.accreditation_id = l.accreditation_id
  WHERE l.accreditation_id IS NOT NULL AND l.accreditation_id <> '' AND l.accreditation_id <> '0'
    AND pp.profile_id IS NULL
    AND played.accreditation_id IS NULL
    AND players.accreditation_id IS NULL";

if (isset($_POST['save'])) {
    $query = sprintf(
        "UPDATE uo_license SET lastname='%s', firstname='%s', membership='%s',
			birthdate='%s', accreditation_id='%s', ultimate='%s', women='%s', junior='%s', license='%s', external_id='%s', external_type='%s', 
			external_validity='%s' WHERE accreditation_id='%s'",
        DBEscapeString($_POST['lastname']),
        DBEscapeString($_POST['firstname']),
        DBEscapeString($_POST['membership']),
        DBEscapeString($_POST['birthdate']),
        DBEscapeString($_POST['accreditation_id']),
        DBEscapeString($_POST['ultimate']),
        DBEscapeString($_POST['women']),
        DBEscapeString($_POST['junior']),
        DBEscapeString($_POST['license']),
        DBEscapeString($_POST['external_id']),
        DBEscapeString($_POST['external_type']),
        DBEscapeString($_POST['external_validity']),
        $accId,
    );
    DBQuery($query);
    $accId = $_POST['accreditation_id'];
} elseif (isset($_POST['remove_x'])) {
    $id = $_POST['hiddenDeleteId'];
    DBQuery("DELETE FROM uo_license WHERE accreditation_id='" . $id . "'");
} elseif (isset($_POST['cleanup_unused'])) {
    $cleanupRemoved = (int) DBQueryToValue($cleanupCandidatesQuery);
    if ($cleanupRemoved > 0) {
        DBQuery($cleanupDeleteQuery);
    }
    $cleanupMessage = $cleanupRemoved . " license row(s) deleted by safe cleanup.";
}


//common page

if ($accId > 0) {
    $html .= "<form method='post' id='tables' action='?view=plugins/lisence_modifier&amp;accid=" . $accId . "''>\n";
    $licenses = DBQuery("SELECT * FROM uo_license WHERE accreditation_id='" . $accId . "'");
    $html .= "<table>";
    $lis = mysqli_fetch_assoc($licenses);
    $columns = array_keys($lis);
    $values = array_values($lis);
    $total = count($lis);
    for ($i = 0; $i < $total; $i++) {
        $html .= "<tr>";
        $html .= "<td>" . utf8entities($columns[$i]) . "</td>";
        $html .= "<td><input class='input' name='" . $columns[$i] . "' value='" . utf8entities($values[$i]) . "'/></td>";
        $html .= "</tr>";
    }
    $html .= "</table>";

    $html .= "<h3>Matching player profiles</h3>";

    $matches = [];
    if (!empty($lis['accreditation_id'])) {
        $query = sprintf(
            "SELECT profile_id, firstname, lastname
      FROM uo_player_profile
      WHERE accreditation_id='%s'
      ORDER BY profile_id",
            DBEscapeString($lis['accreditation_id']),
        );
        $result = DBQuery($query);
        while ($row = mysqli_fetch_assoc($result)) {
            $profileId = intval($row['profile_id']);
            if (!isset($matches[$profileId])) {
                $matches[$profileId] = [
                    "profile_id" => $profileId,
                    "firstname" => $row['firstname'],
                    "lastname" => $row['lastname'],
                    "reasons" => [],
                ];
            }
            $matches[$profileId]['reasons'][] = "Same accreditation_id";
        }
    }

    if (!empty($lis['firstname']) && !empty($lis['lastname'])) {
        $query = sprintf(
            "SELECT profile_id, firstname, lastname
      FROM uo_player_profile
      WHERE LOWER(TRIM(firstname))='%s' AND LOWER(TRIM(lastname))='%s'
      ORDER BY profile_id",
            DBEscapeString(strtolower(trim($lis['firstname']))),
            DBEscapeString(strtolower(trim($lis['lastname']))),
        );
        $result = DBQuery($query);
        while ($row = mysqli_fetch_assoc($result)) {
            $profileId = intval($row['profile_id']);
            if (!isset($matches[$profileId])) {
                $matches[$profileId] = [
                    "profile_id" => $profileId,
                    "firstname" => $row['firstname'],
                    "lastname" => $row['lastname'],
                    "reasons" => [],
                ];
            }
            if (!in_array("Identical first + last name", $matches[$profileId]['reasons'])) {
                $matches[$profileId]['reasons'][] = "Identical first + last name";
            }
        }
    }

    $html .= "<p> ";
    if (count($matches) > 0) {
        $links = [];
        foreach ($matches as $row) {
            $links[] = "<a href='index.php?view=user/playerprofile&amp;profile=" . $row['profile_id'] . "'>"
              . $row['profile_id'] . " - " . utf8entities($row['firstname'] . " " . $row['lastname']) . "</a> : "
              . utf8entities(implode(", ", $row['reasons']));
        }
        $html .= implode("<br/>", $links);
    } else {
        $html .= "-";
    }
    $html .= "</p>";

    $html .= "<input class='button' type='submit' name='save' value='" . _("Save") . "' />";
    $html .= "<input class='button' type='button' name='takaisin'  value='" . _("Return") . "' onclick=\"window.location.href='?view=plugins/lisence_modifier'\"/>";
} else {
    $html .= "<form method='post' id='tables' action='?view=plugins/lisence_modifier'>\n";
    if (!empty($cleanupMessage)) {
        $html .= "<p><strong>" . utf8entities($cleanupMessage) . "</strong></p>";
    }
    $cleanupCandidates = (int) DBQueryToValue($cleanupCandidatesQuery);
    $html .= "<p>Safe cleanup candidates: " . $cleanupCandidates
      . " (no profile, no played games, and no player row in uo_player). ";
    $html .= "<input class='button' type='submit' name='cleanup_unused' value='Delete safe cleanup candidates'"
      . " onclick=\"return confirm('Delete safe cleanup candidates from uo_license? This cannot be undone.');\"/></p>";

    $licenses = DBQuery(
        "SELECT l.*,
      COALESCE(pp.profile_ids, '') AS matching_profile_ids
    FROM uo_license l
    LEFT JOIN (
      SELECT accreditation_id, GROUP_CONCAT(profile_id ORDER BY profile_id SEPARATOR ',') AS profile_ids
      FROM uo_player_profile
      WHERE accreditation_id IS NOT NULL AND accreditation_id <> ''
      GROUP BY accreditation_id
    ) pp ON pp.accreditation_id = l.accreditation_id
    ORDER BY l.lastname, l.firstname, l.accreditation_id",
    );
    $html .= "<table style='width:100%'>";
    $previousNameKey = "";
    while ($lis = mysqli_fetch_assoc($licenses)) {
        $firstname = trim((string) ($lis['firstname'] ?? ""));
        $lastname = trim((string) ($lis['lastname'] ?? ""));
        $currentNameKey = strtolower($firstname) . "|" . strtolower($lastname);
        $isPotentialDuplicate = (!empty($firstname) && !empty($lastname) && $currentNameKey == $previousNameKey);
        $rowStyle = $isPotentialDuplicate ? " style='background-color:#ffe7a8'" : "";
        $html .= "<tr" . $rowStyle . ">";
        $html .= "<td>" . utf8entities($lis['accreditation_id']) . "</td>";
        $html .= "<td>" . utf8entities($lis['lastname']) . "</td>";
        $html .= "<td>" . utf8entities($lis['firstname']) . "</td>";
        $html .= "<td>";
        if (!empty($lis['matching_profile_ids'])) {
            $profileLinks = [];
            $profileIds = explode(",", $lis['matching_profile_ids']);
            foreach ($profileIds as $profileId) {
                $profileId = intval($profileId);
                if ($profileId > 0) {
                    $profileLinks[] = "<a href='index.php?view=user/playerprofile&amp;profile=" . $profileId . "'>" . $profileId . "</a>";
                }
            }
            $html .= implode(", ", $profileLinks);
        } else {
            $html .= "-";
        }
        $html .= "</td>";
        $html .= "<td>" . utf8entities($lis['membership']) . "</td>";
        $html .= "<td>" . utf8entities($lis['license']) . "</td>";
        $html .= "<td><a href='?view=plugins/lisence_modifier&amp;accid=" . $lis['accreditation_id'] . "'>" . _("edit") . "</a></td>";
        $html .= "<td><input class='deletebutton' type='image' src='images/remove.png' name='remove' value='X' alt='X' onclick='setId(" . $lis['accreditation_id'] . ");'/></td>";
        //$html .="<td>".utf8entities($lis['accreditation_id'])."</td>";
        $html .= "</tr>";
        $previousNameKey = $currentNameKey;
    }
    $html .= "</table>";
}
$html .= "<div><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></div>";
$html .= "</form>";
showPage($title, $html);
?>
