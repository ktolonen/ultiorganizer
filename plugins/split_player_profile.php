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
customization=all

[DESCRIPTION]
title = "Split player profile"
description = "Move selected roster records from a merged player profile to a new or existing destination profile."
-->
<?php
ob_end_clean();

if (!isSuperAdmin()) {
    die('Insufficient user rights');
}

/**
 * Resolve the profile identified by an explicit player or profile ID.
 *
 * @param string $id
 * @param string $idType
 * @return int
 */
function SplitPlayerProfilePluginResolveId($id, $idType)
{
    $id = trim((string) $id);
    $idType = $idType === 'profile' ? 'profile' : 'player';
    if (!ctype_digit($id)) {
        return 0;
    }

    $id = (int) $id;
    if ($id <= 0) {
        return 0;
    }

    if ($idType === 'profile') {
        $query = sprintf(
            "SELECT profile_id FROM uo_player_profile WHERE profile_id=%d",
            $id,
        );
    } else {
        $query = sprintf(
            "SELECT profile_id FROM uo_player WHERE player_id=%d",
            $id,
        );
    }

    return (int) DBQueryToValue($query);
}

/**
 * Get the source profile.
 *
 * @param int $profileId
 * @return array|null
 */
function SplitPlayerProfilePluginProfile($profileId)
{
    $query = sprintf(
        "SELECT profile_id, firstname, lastname
		FROM uo_player_profile
		WHERE profile_id=%d",
        (int) $profileId,
    );

    return DBQueryToRow($query);
}

/**
 * Get the roster records that belong to a player profile.
 *
 * @param int $profileId
 * @return array
 */
function SplitPlayerProfilePluginRows($profileId)
{
    $query = sprintf(
        "SELECT p.player_id, p.firstname, p.lastname, p.num,
			t.team_id, t.name AS teamname,
			ser.series_id, ser.name AS seriesname,
			sea.season_id, sea.name AS seasonname, sea.starttime,
			(SELECT COUNT(*) FROM uo_played played WHERE played.player=p.player_id) AS games
		FROM uo_player p
		LEFT JOIN uo_team t ON t.team_id=p.team
		LEFT JOIN uo_series ser ON ser.series_id=t.series
		LEFT JOIN uo_season sea ON sea.season_id=ser.season
		WHERE p.profile_id=%d
		ORDER BY sea.starttime, sea.season_id, ser.ordering, ser.series_id, t.name, p.player_id",
        (int) $profileId,
    );

    return DBQueryToArray($query);
}

/**
 * Move selected roster records from one player profile to another.
 *
 * @param int $sourceProfileId
 * @param array $playerIds
 * @param string $destinationType
 * @param mixed $existingProfileId
 * @param string $firstname
 * @param string $lastname
 * @return array{profile_id: int, profile_created: bool, players_moved: int, stats_moved: int}
 */
function SplitPlayerProfilePluginMove(
    $sourceProfileId,
    $playerIds,
    $destinationType,
    $existingProfileId,
    $firstname,
    $lastname,
) {
    if (!isSuperAdmin()) {
        die("Insufficient rights to split player profile");
    }

    $sourceProfileId = (int) $sourceProfileId;
    $destinationType = $destinationType === "existing" ? "existing" : "new";
    $existingProfileId = trim((string) $existingProfileId);
    $firstname = trim((string) $firstname);
    $lastname = trim((string) $lastname);

    if ($sourceProfileId <= 0) {
        throw new InvalidArgumentException("Select a valid source profile.");
    }
    if ($destinationType === "new") {
        if ($firstname === "" || $lastname === "") {
            throw new InvalidArgumentException("Enter the first and last name for the new destination profile.");
        }
        if (mb_strlen($firstname) > 40 || mb_strlen($lastname) > 40) {
            throw new InvalidArgumentException("Player names can contain at most 40 characters.");
        }
        $destinationProfileId = 0;
    } else {
        if (!ctype_digit($existingProfileId) || (int) $existingProfileId <= 0) {
            throw new InvalidArgumentException("Enter a valid destination profile ID.");
        }
        $destinationProfileId = (int) $existingProfileId;
        if ($destinationProfileId === $sourceProfileId) {
            throw new InvalidArgumentException("Select a destination profile other than the source profile.");
        }
    }

    $selectedPlayerIds = [];
    foreach ((array) $playerIds as $playerId) {
        if (!is_scalar($playerId)) {
            continue;
        }
        $playerId = (int) $playerId;
        if ($playerId > 0) {
            $selectedPlayerIds[$playerId] = $playerId;
        }
    }
    if (empty($selectedPlayerIds)) {
        throw new InvalidArgumentException("Select at least one roster record to move.");
    }

    $selectedIdList = implode(",", $selectedPlayerIds);
    $previousExceptionMode = DBShouldThrowExceptions();
    DBSetExceptionMode(true);
    $transactionStarted = false;

    try {
        DBExecute("START TRANSACTION");
        $transactionStarted = true;

        $profileIds = [$sourceProfileId];
        if ($destinationType === "existing") {
            $profileIds[] = $destinationProfileId;
        }
        sort($profileIds);
        $profileIdList = implode(",", $profileIds);
        $query = "SELECT profile_id FROM uo_player_profile
			WHERE profile_id IN ($profileIdList)
			ORDER BY profile_id
			FOR UPDATE";
        $lockedProfiles = DBQueryToArray($query);
        $lockedProfileIds = [];
        foreach ($lockedProfiles as $profile) {
            $lockedProfileIds[(int) $profile['profile_id']] = true;
        }
        if (!isset($lockedProfileIds[$sourceProfileId])) {
            throw new RuntimeException("The source profile no longer exists.");
        }
        if ($destinationType === "existing" && !isset($lockedProfileIds[$destinationProfileId])) {
            throw new RuntimeException("The destination profile no longer exists.");
        }

        $query = sprintf(
            "SELECT player_id FROM uo_player WHERE profile_id=%d FOR UPDATE",
            $sourceProfileId,
        );
        $currentPlayers = DBQueryToArray($query);
        $currentPlayerIds = [];
        foreach ($currentPlayers as $player) {
            $currentPlayerIds[(int) $player['player_id']] = true;
        }

        foreach ($selectedPlayerIds as $playerId) {
            if (!isset($currentPlayerIds[$playerId])) {
                throw new RuntimeException("The profile changed while it was being edited. Review the selections and try again.");
            }
        }
        if (count($currentPlayerIds) < 2 || count($selectedPlayerIds) >= count($currentPlayerIds)) {
            throw new InvalidArgumentException("Leave at least one roster record with the source profile.");
        }

        $statsMoved = (int) DBQueryToValue(
            "SELECT COUNT(*) FROM uo_player_stats WHERE player_id IN ($selectedIdList)",
        );

        $profileCreated = $destinationType === "new";
        if ($profileCreated) {
            $query = sprintf(
                "INSERT INTO uo_player_profile (firstname, lastname)
				VALUES ('%s', '%s')",
                DBEscapeString($firstname),
                DBEscapeString($lastname),
            );
            $destinationProfileId = DBQueryInsert($query);
        }

        $query = sprintf(
            "UPDATE uo_player SET profile_id=%d
			WHERE profile_id=%d AND player_id IN (%s)",
            $destinationProfileId,
            $sourceProfileId,
            $selectedIdList,
        );
        DBExecute($query);

        $query = sprintf(
            "UPDATE uo_player_stats SET profile_id=%d
			WHERE player_id IN (%s)",
            $destinationProfileId,
            $selectedIdList,
        );
        DBExecute($query);

        Log1(
            "player",
            "split",
            array_key_first($selectedPlayerIds),
            "",
            sprintf("profile %d split to %d", $sourceProfileId, $destinationProfileId),
            "split profile",
        );

        DBExecute("COMMIT");
        $transactionStarted = false;

        return [
            "profile_id" => $destinationProfileId,
            "profile_created" => $profileCreated,
            "players_moved" => count($selectedPlayerIds),
            "stats_moved" => $statsMoved,
        ];
    } catch (Throwable $e) {
        if ($transactionStarted) {
            DBExecute("ROLLBACK");
        }
        throw $e;
    } finally {
        DBSetExceptionMode($previousExceptionMode);
    }
}

$title = "Split player profile";
$html = "";
$sourceProfileId = 0;
$lookupId = isset($_POST['lookup_id']) ? trim((string) $_POST['lookup_id']) : "";
$idType = isset($_POST['id_type']) ? (string) $_POST['id_type'] : 'player';
$destinationType = isset($_POST['destination_type']) && $_POST['destination_type'] === 'existing'
    ? 'existing'
    : 'new';
$existingProfileId = isset($_POST['existing_profile_id'])
    ? trim((string) $_POST['existing_profile_id'])
    : "";
$destinationLookupId = isset($_POST['destination_lookup_id'])
    ? trim((string) $_POST['destination_lookup_id'])
    : "";
$destinationIdType = isset($_POST['destination_id_type']) && $_POST['destination_id_type'] === 'profile'
    ? 'profile'
    : 'player';
$selectedPlayerIds = [];
if (isset($_POST['player_ids']) && is_array($_POST['player_ids'])) {
    foreach ($_POST['player_ids'] as $playerId) {
        if (is_scalar($playerId) && (int) $playerId > 0) {
            $selectedPlayerIds[(int) $playerId] = (int) $playerId;
        }
    }
}
$firstname = isset($_POST['new_firstname']) ? (string) $_POST['new_firstname'] : "";
$lastname = isset($_POST['new_lastname']) ? (string) $_POST['new_lastname'] : "";
$message = "";

if (!empty($_POST['split_profile'])) {
    $sourceProfileId = isset($_POST['source_profile_id']) ? (int) $_POST['source_profile_id'] : 0;

    try {
        if ($destinationType === "existing") {
            $resolvedDestinationProfileId = SplitPlayerProfilePluginResolveId(
                $destinationLookupId,
                $destinationIdType,
            );
            if (
                $resolvedDestinationProfileId <= 0
                || $resolvedDestinationProfileId !== (int) $existingProfileId
            ) {
                throw new InvalidArgumentException("Find and review the destination profile before moving roster records.");
            }
        }
        $split = SplitPlayerProfilePluginMove(
            $sourceProfileId,
            $selectedPlayerIds,
            $destinationType,
            $existingProfileId,
            $firstname,
            $lastname,
        );
        $destinationProfileId = (int) $split['profile_id'];
        $message .= "<div class='success'>";
        if ($split['profile_created']) {
            $message .= "<p>" . sprintf(
                "Created destination profile %1\$d and moved %2\$d roster records and %3\$d player statistics records.",
                $destinationProfileId,
                $split['players_moved'],
                $split['stats_moved'],
            ) . "</p>";
        } else {
            $message .= "<p>" . sprintf(
                "Moved %1\$d roster records and %2\$d player statistics records to destination profile %3\$d.",
                $split['players_moved'],
                $split['stats_moved'],
                $destinationProfileId,
            ) . "</p>";
        }
        $message .= "<p><a href='?view=playercard&amp;profile=" . $destinationProfileId . "'>";
        $message .= "View the destination player card</a> | ";
        $message .= "<a href='?view=playercard&amp;profile=" . $sourceProfileId . "'>";
        $message .= "View the source player card</a></p>";
        $message .= "<p>Source profile details, images, links, and profile administrator rights were not copied to or changed on the destination profile.</p>";
        $message .= "</div>";
        $sourceProfileId = 0;
        $lookupId = "";
        $destinationType = "new";
        $existingProfileId = "";
        $destinationLookupId = "";
        $destinationIdType = "player";
        $selectedPlayerIds = [];
    } catch (Throwable $exception) {
        $message .= "<div class='warning'>" . utf8entities($exception->getMessage()) . "</div>";
    }
} elseif (!empty($_POST['find_destination'])) {
    $sourceProfileId = isset($_POST['source_profile_id']) ? (int) $_POST['source_profile_id'] : 0;
    $destinationType = "existing";
    $existingProfileId = (string) SplitPlayerProfilePluginResolveId(
        $destinationLookupId,
        $destinationIdType,
    );
    if ((int) $existingProfileId <= 0) {
        $existingProfileId = "";
        $message .= "<div class='warning'>No destination profile was found for that ID.</div>";
    } elseif ((int) $existingProfileId === $sourceProfileId) {
        $existingProfileId = "";
        $message .= "<div class='warning'>Select a destination profile other than the source profile.</div>";
    }
} elseif (!empty($_POST['open_profile'])) {
    $sourceProfileId = SplitPlayerProfilePluginResolveId($lookupId, $idType);
    if ($sourceProfileId <= 0) {
        $message .= "<div class='warning'>No source profile was found for that ID.</div>";
    }
} elseif (!empty($_GET['profile'])) {
    $sourceProfileId = SplitPlayerProfilePluginResolveId((string) $_GET['profile'], 'profile');
}

$html .= $message;
$html .= "<form method='post' action='?view=plugins/split_player_profile'>\n";
$html .= "<p>Open the source profile by entering a player ID or profile ID.</p>\n";
$html .= "<p><label for='profile-id'>Source ID:</label> ";
$html .= "<input class='input' type='text' inputmode='numeric' pattern='[0-9]*' id='profile-id' name='lookup_id' value='" . utf8entities($lookupId) . "'/> ";
$html .= "<select class='dropdown' name='id_type'>";
$html .= "<option value='player'" . ($idType !== 'profile' ? " selected='selected'" : "") . ">Player ID</option>";
$html .= "<option value='profile'" . ($idType === 'profile' ? " selected='selected'" : "") . ">Profile ID</option>";
$html .= "</select> ";
$html .= "<input class='button' type='submit' name='open_profile' value='Open source profile'/></p>\n";
$html .= "</form>\n";

if ($sourceProfileId > 0) {
    $profile = SplitPlayerProfilePluginProfile($sourceProfileId);
    $rows = SplitPlayerProfilePluginRows($sourceProfileId);
    $defaultFirstname = trim((string) ($profile['firstname'] ?? ""));
    $defaultLastname = trim((string) ($profile['lastname'] ?? ""));
    if (!empty($rows)) {
        if ($defaultFirstname === '') {
            $defaultFirstname = trim((string) $rows[0]['firstname']);
        }
        if ($defaultLastname === '') {
            $defaultLastname = trim((string) $rows[0]['lastname']);
        }
    }
    $formFirstname = array_key_exists('new_firstname', $_POST)
        ? trim($firstname)
        : $defaultFirstname;
    $formLastname = array_key_exists('new_lastname', $_POST)
        ? trim($lastname)
        : $defaultLastname;
    $destinationProfile = null;
    $destinationRows = [];
    if ($destinationType === 'existing' && (int) $existingProfileId > 0) {
        $destinationProfile = SplitPlayerProfilePluginProfile((int) $existingProfileId);
        if ($destinationProfile) {
            $destinationRows = SplitPlayerProfilePluginRows((int) $existingProfileId);
        }
    }

    $html .= "<h2>Source profile: " . utf8entities(trim($defaultFirstname . " " . $defaultLastname));
    $html .= " (Profile ID " . $sourceProfileId . ")</h2>\n";
    $html .= "<p>Select the roster records that belong to the other person. The selected records will move from the source profile to the destination profile.</p>\n";
    $html .= "<p><strong>The source profile must retain at least one roster record.</strong> ";
    $html .= "Source profile details, images, links, and profile administrator rights are not copied to or changed on the destination profile.</p>\n";

    if (!empty($rows)) {
        $html .= "<form method='post' id='split-profile-records' action='?view=plugins/split_player_profile'>\n";
        $html .= "<table class='admintable'>\n";
        $html .= "<tr><th><input type='checkbox' aria-label='Select all roster records' onclick='checkAll(\"split-profile-records\");'/></th>";
        $html .= "<th>Event</th><th>Division</th>";
        $html .= "<th>Team</th><th>Roster name</th><th>Number</th>";
        $html .= "<th>Games</th><th>Player ID</th></tr>\n";
        foreach ($rows as $row) {
            $playerId = (int) $row['player_id'];
            $html .= "<tr>";
            $html .= "<td class='center'><input type='checkbox' name='player_ids[]' value='" . $playerId . "'";
            $html .= isset($selectedPlayerIds[$playerId]) ? " checked='checked'" : "";
            $html .= "/></td>";
            $html .= "<td>" . utf8entities($row['seasonname'] ?: $row['season_id']) . "</td>";
            $html .= "<td>" . utf8entities($row['seriesname']) . "</td>";
            $html .= "<td>" . utf8entities($row['teamname']) . "</td>";
            $html .= "<td>" . utf8entities(trim($row['firstname'] . " " . $row['lastname'])) . "</td>";
            $html .= "<td class='center'>" . utf8entities($row['num']) . "</td>";
            $html .= "<td class='center'>" . (int) $row['games'] . "</td>";
            $html .= "<td><a href='?view=playercard&amp;player=" . $playerId . "'>" . $playerId . "</a></td>";
            $html .= "</tr>\n";
        }
        $html .= "</table>\n";
    }

    if (count($rows) < 2) {
        $html .= "<div class='warning'>This source profile does not contain enough roster records to split.</div>";
        if (!empty($rows)) {
            $html .= "</form>\n";
        }
    } else {
        $html .= "<h3>Destination profile</h3>\n";
        $html .= "<p><label for='destination-new'><input type='radio' id='destination-new' name='destination_type' value='new'";
        $html .= $destinationType === 'new' ? " checked='checked'" : "";
        $html .= "/> Create a new destination profile</label></p>\n";
        $html .= "<p>For a new destination profile, enter the person's name.</p>\n";
        $html .= "<p><label for='new-firstname'>First name:</label> ";
        $html .= "<input class='input' id='new-firstname' name='new_firstname' maxlength='40' value='" . utf8entities($formFirstname) . "'/> ";
        $html .= "<label for='new-lastname'>Last name:</label> ";
        $html .= "<input class='input' id='new-lastname' name='new_lastname' maxlength='40' value='" . utf8entities($formLastname) . "'/></p>\n";
        $html .= "<p><label for='destination-existing'><input type='radio' id='destination-existing' name='destination_type' value='existing'";
        $html .= $destinationType === 'existing' ? " checked='checked'" : "";
        $html .= "/> Use an existing destination profile</label></p>\n";
        $html .= "<p>Find the destination profile by entering a player ID or profile ID.</p>\n";
        $html .= "<p><label for='destination-lookup-id'>Destination ID:</label> ";
        $html .= "<input class='input' type='text' inputmode='numeric' pattern='[0-9]*' id='destination-lookup-id' name='destination_lookup_id' value='" . utf8entities($destinationLookupId) . "'/> ";
        $html .= "<select class='dropdown' name='destination_id_type'>";
        $html .= "<option value='player'" . ($destinationIdType !== 'profile' ? " selected='selected'" : "") . ">Player ID</option>";
        $html .= "<option value='profile'" . ($destinationIdType === 'profile' ? " selected='selected'" : "") . ">Profile ID</option>";
        $html .= "</select> ";
        $html .= "<input class='button' type='submit' name='find_destination' value='Find destination profile'/></p>\n";
        $html .= "<input type='hidden' name='existing_profile_id' value='" . utf8entities($existingProfileId) . "'/>\n";

        if ($destinationProfile) {
            $destinationName = trim(
                (string) $destinationProfile['firstname'] . " " . (string) $destinationProfile['lastname'],
            );
            $html .= "<h4>Existing destination profile: " . utf8entities($destinationName);
            $html .= " (Profile ID " . (int) $existingProfileId . ")</h4>\n";
            $html .= "<p>Review the destination profile and its roster records before moving selected records. This preview is read-only.</p>\n";
            if (!empty($destinationRows)) {
                $html .= "<table class='admintable'>\n";
                $html .= "<tr><th>Event</th><th>Division</th><th>Team</th>";
                $html .= "<th>Roster name</th><th>Number</th><th>Games</th><th>Player ID</th></tr>\n";
                foreach ($destinationRows as $destinationRow) {
                    $destinationPlayerId = (int) $destinationRow['player_id'];
                    $html .= "<tr>";
                    $html .= "<td>" . utf8entities($destinationRow['seasonname'] ?: $destinationRow['season_id']) . "</td>";
                    $html .= "<td>" . utf8entities($destinationRow['seriesname']) . "</td>";
                    $html .= "<td>" . utf8entities($destinationRow['teamname']) . "</td>";
                    $html .= "<td>" . utf8entities(trim($destinationRow['firstname'] . " " . $destinationRow['lastname'])) . "</td>";
                    $html .= "<td class='center'>" . utf8entities($destinationRow['num']) . "</td>";
                    $html .= "<td class='center'>" . (int) $destinationRow['games'] . "</td>";
                    $html .= "<td><a href='?view=playercard&amp;player=" . $destinationPlayerId . "'>";
                    $html .= $destinationPlayerId . "</a></td>";
                    $html .= "</tr>\n";
                }
                $html .= "</table>\n";
            } else {
                $html .= "<div class='warning'>The destination profile has no roster records.</div>\n";
            }
        }
        $html .= "<input type='hidden' name='source_profile_id' value='" . $sourceProfileId . "'/>\n";
        $confirm = "Move the selected roster records to the destination profile?";
        $html .= "<p><input class='button' type='submit' name='split_profile' value='Move selected roster records'";
        $html .= " onclick=\"return confirm(" . utf8entities(json_encode($confirm)) . ");\"/></p>\n";
        $html .= "</form>\n";
    }
}

showPage($title, $html);
