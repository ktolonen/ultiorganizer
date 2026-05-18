<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

function GetUrlById($urlId)
{
    $query = sprintf(
        "SELECT * FROM uo_urls WHERE url_id=%d",
        (int) $urlId,
    );
    return DBQueryToRow($query);
}

function GetUrl($owner, $ownerId, $type)
{
    $query = sprintf(
        "SELECT * FROM uo_urls WHERE owner='%s' AND owner_id='%s' AND type='%s'",
        DBEscapeString($owner),
        DBEscapeString($ownerId),
        DBEscapeString($type),
    );
    return DBQueryToRow($query);
}

function GetUrlList($owner, $ownerId, $medialinks = false)
{
    if ($medialinks) {
        $query = sprintf(
            "SELECT * FROM uo_urls WHERE owner='%s' AND owner_id='%s' AND ismedialink=1",
            DBEscapeString($owner),
            DBEscapeString($ownerId),
        );
    } else {
        $query = sprintf(
            "SELECT * FROM uo_urls WHERE owner='%s' AND owner_id='%s' AND ismedialink=0",
            DBEscapeString($owner),
            DBEscapeString($ownerId),
        );
    }
    $query .= " ORDER BY ordering, type, name";
    return DBQueryToArray($query);
}

function GetUrlListByTypeArray($typearray, $ownerId)
{
    $list = [];
    foreach ($typearray as $type) {
        $list[] = "'" . DBEscapeString($type) . "'";
    }
    if (empty($list)) {
        return [];
    }
    $liststring = implode(",", $list);
    $query = "SELECT * FROM uo_urls WHERE owner='ultiorganizer' AND type IN($liststring) AND owner_id='" . DBEscapeString($ownerId) . "' ORDER BY ordering,type, name";
    return DBQueryToArray($query);
}

function GetMediaUrlList($owner, $ownerId, $type = "")
{

    if ($owner == "game") {
        $query = sprintf(
            "SELECT urls.*, u.name AS publisher, e.time
			FROM uo_urls urls 
			LEFT JOIN uo_users u ON (u.id=urls.publisher_id)
			LEFT JOIN uo_gameevent e ON(e.info=urls.url_id)
			WHERE urls.owner='%s' AND urls.owner_id='%s' AND urls.ismedialink=1",
            DBEscapeString($owner),
            DBEscapeString($ownerId),
        );
    } else {
        $query = sprintf(
            "SELECT urls.*, u.name AS publisher FROM uo_urls urls 
			LEFT JOIN uo_users u ON (u.id=urls.publisher_id)
			WHERE urls.owner='%s' AND urls.owner_id='%s' AND urls.ismedialink=1",
            DBEscapeString($owner),
            DBEscapeString($ownerId),
        );
    }
    if (!empty($type)) {
        $query .= sprintf(" AND urls.type='%s'", DBEscapeString($type));
    }

    return DBQueryToArray($query);
}

function GetMediaUrlListForGames($gameIds, $type = "")
{
    $ids = [];
    foreach ($gameIds as $gameId) {
        $gameId = (int) $gameId;
        if ($gameId > 0) {
            $ids[] = $gameId;
        }
    }
    if (empty($ids)) {
        return [];
    }

    $query = "SELECT urls.*, u.name AS publisher, e.time
		FROM uo_urls urls 
		LEFT JOIN uo_users u ON (u.id=urls.publisher_id)
		LEFT JOIN uo_gameevent e ON (e.info=urls.url_id)
		WHERE urls.owner='game' AND urls.owner_id IN (" . implode(",", $ids) . ") AND urls.ismedialink=1";
    if (!empty($type)) {
        $query .= sprintf(" AND urls.type='%s'", DBEscapeString($type));
    }

    $rows = DBQueryToArray($query);
    $byGame = [];
    foreach ($rows as $row) {
        $ownerId = $row['owner_id'];
        if (!isset($byGame[$ownerId])) {
            $byGame[$ownerId] = [];
        }
        $byGame[$ownerId][] = $row;
    }

    return $byGame;
}

function GetUrlTypes()
{
    $types = [];
    $dbtype = ["homepage", "forum", "twitter", "blogger", "facebook", "flickr", "picasa", "other"];
    $translation = [_("Homepage"), _("Forum"), _("Twitter"), _("Blogger"), _("Facebook"), _("Flickr"), _("Picasa"), _("Other")];
    $icon = ["homepage.png", "forum.png", "twitter.png", "blogger.png", "facebook.png", "flickr.png", "picasa.png", "other.png"];

    for ($i = 0; $i < count($dbtype); $i++) {
        $types[] = ['type' => $dbtype[$i], 'name' => $translation[$i], 'icon' => $icon[$i]];
    }
    return $types;
}

function GetMediaUrlTypes()
{
    $types = [];
    $dbtype = ["image", "video", "live"];
    $translation = [_("Image"), _("Video"), _("Live video")];
    $icon = ["image.png", "video.png", "live.png"];

    for ($i = 0; $i < count($dbtype); $i++) {
        $types[] = ['type' => $dbtype[$i], 'name' => $translation[$i], 'icon' => $icon[$i]];
    }
    return $types;
}

function AddUrl($urlparams)
{
    if (isSuperAdmin()) {
        $url = SafeUrl($urlparams['url']);

        $query = sprintf(
            "INSERT INTO uo_urls (owner,owner_id,type,name,url,ordering)
				VALUES('%s','%s','%s','%s','%s','%s')",
            DBEscapeString($urlparams['owner']),
            DBEscapeString($urlparams['owner_id']),
            DBEscapeString($urlparams['type']),
            DBEscapeString($urlparams['name']),
            DBEscapeString($url),
            DBEscapeString($urlparams['ordering']),
        );

        return DBQuery($query);
    } else {
        die('Insufficient rights to add url');
    }
}

function AddMail($urlparams)
{
    if (isSuperAdmin()) {
        $query = sprintf(
            "INSERT INTO uo_urls (owner,owner_id,type,name,url,ordering)
				VALUES('%s','%s','%s','%s','%s','%s')",
            DBEscapeString($urlparams['owner']),
            DBEscapeString($urlparams['owner_id']),
            DBEscapeString($urlparams['type']),
            DBEscapeString($urlparams['name']),
            DBEscapeString($urlparams['url']),
            DBEscapeString($urlparams['ordering']),
        );
        return DBQuery($query);
    } else {
        die('Insufficient rights to add url');
    }
}

function SetUrl($urlparams)
{
    if (isSuperAdmin()) {
        $url = SafeUrl($urlparams['url']);

        $query = sprintf(
            "UPDATE uo_urls SET owner='%s',owner_id='%s',type='%s',name='%s',url='%s', ordering='%s'
			WHERE url_id=%d",
            DBEscapeString($urlparams['owner']),
            DBEscapeString($urlparams['owner_id']),
            DBEscapeString($urlparams['type']),
            DBEscapeString($urlparams['name']),
            DBEscapeString($url),
            DBEscapeString($urlparams['ordering']),
            (int) $urlparams['url_id'],
        );
        return DBQuery($query);
    } else {
        die('Insufficient rights to add url');
    }
}

function SetMail($urlparams)
{
    if (isSuperAdmin()) {
        $query = sprintf(
            "UPDATE uo_urls SET owner='%s',owner_id='%s',type='%s',name='%s',url='%s', ordering='%s'
			WHERE url_id=%d",
            DBEscapeString($urlparams['owner']),
            DBEscapeString($urlparams['owner_id']),
            DBEscapeString($urlparams['type']),
            DBEscapeString($urlparams['name']),
            DBEscapeString($urlparams['url']),
            DBEscapeString($urlparams['ordering']),
            (int) $urlparams['url_id'],
        );
        return DBQuery($query);
    } else {
        die('Insufficient rights to add url');
    }
}

function RemoveUrl($urlId)
{
    if (isSuperAdmin()) {
        $query = sprintf(
            "DELETE FROM uo_urls WHERE url_id=%d",
            (int) $urlId,
        );
        return DBQuery($query);
    } else {
        die('Insufficient rights to remove url');
    }
}

function AddMediaUrl($urlparams)
{
    if (CanEditMediaTarget($urlparams['owner'], $urlparams['owner_id'])) {

        $url = SafeUrl($urlparams['url']);
        $publisherId = CurrentUserDatabaseId();

        $query = sprintf(
            "INSERT INTO uo_urls (owner,owner_id,type,name,url,ismedialink,mediaowner,publisher_id)
				VALUES('%s','%s','%s','%s','%s',1,'%s','%s')",
            DBEscapeString($urlparams['owner']),
            DBEscapeString($urlparams['owner_id']),
            DBEscapeString($urlparams['type']),
            DBEscapeString($urlparams['name']),
            DBEscapeString($url),
            DBEscapeString($urlparams['mediaowner']),
            DBEscapeString($publisherId),
        );
        Log2("Media", "Add", $urlparams['url']);

        return DBQueryInsert($query);
    } else {
        die('Insufficient rights to add media');
    }
}

function RemoveMediaUrl($urlId)
{
    $url = GetUrlById($urlId);
    if (!$url || (int) $url['ismedialink'] !== 1) {
        return false;
    }

    if (!CanRemoveMediaUrl($url)) {
        die('Insufficient rights to remove url');
    }

    DBQuery(sprintf(
        "DELETE FROM uo_gameevent WHERE type='media' AND info=%d",
        (int) $urlId,
    ));

    $query = sprintf(
        "DELETE FROM uo_urls WHERE url_id=%d AND ismedialink=1",
        (int) $urlId,
    );
    Log2("Media", "Remove", $urlId);
    return DBQuery($query);
}

function CurrentUserDatabaseId()
{
    $query = sprintf(
        "SELECT id FROM uo_users WHERE userid='%s'",
        DBEscapeString($_SESSION['uid']),
    );
    return (int) DBQueryToValue($query);
}

function CanRemoveMediaUrl($url)
{
    if (isSuperAdmin()) {
        return true;
    }

    return hasAddMediaRight() && (int) $url['publisher_id'] === CurrentUserDatabaseId();
}

function CanEditMediaTarget($owner, $ownerId)
{
    if (isSuperAdmin()) {
        return true;
    }
    if (!hasAddMediaRight()) {
        return false;
    }

    $ownerId = (int) $ownerId;
    if ($ownerId <= 0) {
        return false;
    }

    switch ($owner) {
        case 'game':
            return hasEditGameEventsRight($ownerId);
        case 'team':
            return hasEditPlayersRight($ownerId);
        case 'player':
            return CanEditPlayerMediaTarget($ownerId);
        case 'club':
            return CanEditClubMediaTarget($ownerId);
        case 'series':
            return hasEditGamesRight($ownerId);
        case 'pool':
            $poolInfo = PoolInfo($ownerId);
            return !empty($poolInfo['series']) && hasEditGamesRight($poolInfo['series']);
        case 'country':
            return false;
    }

    return false;
}

function CanEditClubMediaTarget($clubId)
{
    $query = sprintf(
        "SELECT team_id FROM uo_team WHERE club=%d",
        (int) $clubId,
    );
    foreach (DBQueryToArray($query) as $team) {
        if (hasEditPlayersRight((int) $team['team_id'])) {
            return true;
        }
    }

    return false;
}

function CanEditPlayerMediaTarget($profileId)
{
    if (isPlayerAdmin($profileId)) {
        return true;
    }

    $query = sprintf(
        "SELECT MAX(player_id) FROM uo_player WHERE profile_id=%d",
        (int) $profileId,
    );
    $playerId = (int) DBQueryToValue($query);
    if ($playerId <= 0) {
        return false;
    }

    return hasEditPlayerProfileRight($playerId);
}
