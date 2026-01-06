<?php
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/image.functions.php';
include_once $include_prefix . 'lib/logging.functions.php';

function upgrade46()
{
}

function upgrade47()
{
	addColumn('uo_reservation', 'season', 'varchar(10) default NULL');

	$results = runQuery("SELECT DISTINCT pr.id, ser.season
			FROM uo_reservation pr
			LEFT JOIN uo_game pp ON (pp.reservation=pr.id)
			LEFT JOIN uo_pool ps ON (pp.pool=ps.pool_id)
			LEFT JOIN uo_series ser ON (ps.series=ser.series_id)
			LEFT JOIN uo_location pl ON (pr.location=pl.id)");

	while ($row = mysqli_fetch_assoc($results)) {
		runQuery("UPDATE uo_reservation SET season='" . $row['season'] . "'
			WHERE id='" . $row['id'] . "'");
	}

	runQuery('INSERT INTO uo_setting (name, value) VALUES ("GameRSSEnabled", "false")');
}

function upgrade48()
{
}

function upgrade49()
{
	addColumn('uo_season', 'timezone', 'varchar(50) default NULL');
}

function upgrade50()
{
}

function upgrade51()
{
	addColumn('uo_urls', 'ordering', "varchar(2) default ''");
}

function upgrade52()
{
	addColumn('uo_pool', 'forfeitscore', 'int(10) DEFAULT NULL');
	addColumn('uo_pool', 'forfeitagainst', 'int(10) DEFAULT NULL');
	addColumn('uo_pooltemplate', 'forfeitscore', 'int(10) DEFAULT NULL');
	addColumn('uo_pooltemplate', 'forfeitagainst', 'int(10) DEFAULT NULL');
}

function upgrade53()
{
	if (!hasTable("uo_sms")) {
		runQuery("CREATE TABLE `uo_sms` (
		`sms_id` INT(10) NOT NULL AUTO_INCREMENT,
		`to1` INT(15) NOT NULL,
		`to2` INT(15) NULL DEFAULT NULL,
		`to3` INT(15) NULL DEFAULT NULL,
		`to4` INT(15) NULL DEFAULT NULL,
		`to5` INT(15) NULL DEFAULT NULL,
		`msg` VARCHAR(400) NULL DEFAULT NULL,
		`created` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
		`click_id` INT(10) NULL DEFAULT NULL,
		`sent` DATETIME NULL DEFAULT NULL,
		`delivered` DATETIME NULL DEFAULT NULL,
		PRIMARY KEY (`sms_id`)
		)
		COLLATE='latin1_swedish_ci'
		ENGINE=MyISAM
		ROW_FORMAT=DEFAULT
		AUTO_INCREMENT=1000
		");
	}
}
function upgrade54()
{

	if (hasTable("pelik_jasenet") && !hasTable("uo_license")) {
		dropField("pelik_jasenet", "joukkue");
		dropField("pelik_jasenet", "email");
		dropField("pelik_jasenet", "uusi");
		renameTable("pelik_jasenet", "uo_license");
		renameField("uo_license", "sukunimi", "lastname");
		renameField("uo_license", "etunimi", "firstname");
		renameField("uo_license", "jasenmaksu", "membership");
		renameField("uo_license", "ultimate_lisenssi", "license");
		renameField("uo_license", "syntaika", "birthdate");
		renameField("uo_license", "nainen", "women");
		renameField("uo_license", "junnu", "junior");
		renameField("uo_license", "jasennumero", "accreditation_id");
		runQuery("ALTER TABLE uo_license MODIFY accreditation_id varchar(150)");
		runQuery("ALTER TABLE uo_license MODIFY ultimate tinyint(1) DEFAULT NULL");
		runQuery("ALTER TABLE uo_license MODIFY women tinyint(1) DEFAULT NULL");
		runQuery("ALTER TABLE uo_license MODIFY junior tinyint(1) DEFAULT NULL");
		runQuery("ALTER TABLE uo_license MODIFY membership smallint(5) DEFAULT NULL");
		runQuery("ALTER TABLE uo_license MODIFY license smallint(5) DEFAULT NULL");
		addColumn('uo_license', 'external_id', 'int(10) DEFAULT NULL');
		addColumn('uo_license', 'external_type', 'int(10) DEFAULT NULL');
		addColumn('uo_license', 'external_validity', 'int(10) DEFAULT NULL');
	} elseif (!hasTable("uo_license")) {
		runQuery("CREATE TABLE `uo_license` (
		  `lastname` varchar(255) DEFAULT NULL,
		  `firstname` varchar(255) DEFAULT NULL,
		  `membership` smallint(5) DEFAULT NULL,
		  `birthdate` datetime DEFAULT NULL,
		  `accreditation_id` varchar(150) DEFAULT NULL,
		  `ultimate` tinyint(1) DEFAULT NULL,
		  `women` tinyint(1) DEFAULT NULL,
		  `junior` tinyint(1) DEFAULT NULL,
		  `license` smallint(5) DEFAULT NULL,
		  `external_id` int(10) DEFAULT NULL,
		  `external_type` int(10) DEFAULT NULL,
		  `external_validity` int(10) DEFAULT NULL,
		  KEY `etunimi` (`lastname`),
		  KEY `sukunimi` (`firstname`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
	}
}

function upgrade55()
{
	if (!hasColumn('uo_pool', 'follower')) {
		addColumn('uo_pool', 'follower', "int(10) DEFAULT NULL");
	}
}

function upgrade56()
{
	if (!hasColumn('uo_player_profile', 'email')) {
		addColumn('uo_player_profile', 'email', "varchar(100) DEFAULT NULL");

		$results = runQuery("SELECT accreditation_id, email FROM uo_player WHERE email IS NOT NULL");
		while ($row = mysqli_fetch_assoc($results)) {
			$query = sprintf(
				"UPDATE uo_player_profile SET email='%s' WHERE accreditation_id='%s'",
				$row['email'],
				$row['accreditation_id']
			);
			runQuery($query);
		}
		runQuery("alter table uo_player drop column email");
	}
}

function upgrade57()
{
	if (!hasTable("uo_specialranking")) {
		runQuery("CREATE TABLE `uo_specialranking` (
		  `frompool` int(10) NOT NULL,
		  `fromplacing` int(5) NOT NULL,
		  `torank` int(5) NOT NULL,
		  `scheduling_id` int(10) DEFAULT NULL,
		  PRIMARY KEY (`frompool`,`fromplacing`),
		  KEY `idx_scheduling_id` (`scheduling_id`)
		)
		ENGINE=MyISAM
		CHARSET=utf8
		ROW_FORMAT=DEFAULT");
	}
}

function upgrade58()
{
	if (!hasColumn('uo_player_profile', 'firstname')) {
		addColumn('uo_player_profile', 'firstname', "varchar(40) DEFAULT NULL");

		//name from uo_player
		$results = runQuery("SELECT accreditation_id, firstname FROM uo_player WHERE firstname IS NOT NULL");
		while ($row = mysqli_fetch_assoc($results)) {
			$query = sprintf(
				"UPDATE uo_player_profile SET firstname='%s' WHERE accreditation_id='%s'",
				DBEscapeString(trim($row['firstname'])),
				$row['accreditation_id']
			);
			runQuery($query);
		}

		//if uo_license has name use the one from there.
		$results = runQuery("SELECT accreditation_id, firstname FROM uo_license WHERE firstname IS NOT NULL");
		while ($row = mysqli_fetch_assoc($results)) {
			$query = sprintf(
				"UPDATE uo_player_profile SET firstname='%s' WHERE accreditation_id='%s'",
				DBEscapeString(trim($row['firstname'])),
				$row['accreditation_id']
			);
			runQuery($query);
		}
	}
	if (!hasColumn('uo_player_profile', 'lastname')) {
		addColumn('uo_player_profile', 'lastname', "varchar(40) DEFAULT NULL");

		//name from uo_player
		$results = runQuery("SELECT accreditation_id, lastname FROM uo_player WHERE lastname IS NOT NULL");
		while ($row = mysqli_fetch_assoc($results)) {
			$query = sprintf(
				"UPDATE uo_player_profile SET lastname='%s' WHERE accreditation_id='%s'",
				DBEscapeString(trim($row['lastname'])),
				$row['accreditation_id']
			);
			runQuery($query);
		}

		//if uo_license has name use the one from there.
		$results = runQuery("SELECT accreditation_id, lastname FROM uo_license WHERE lastname IS NOT NULL");
		while ($row = mysqli_fetch_assoc($results)) {
			$query = sprintf(
				"UPDATE uo_player_profile SET lastname='%s' WHERE accreditation_id='%s'",
				DBEscapeString(trim($row['lastname'])),
				$row['accreditation_id']
			);
			runQuery($query);
		}
	}
	if (!hasColumn('uo_player_profile', 'num')) {
		addColumn('uo_player_profile', 'num', "tinyint(3) DEFAULT NULL");

		//num from uo_player
		$results = runQuery("SELECT accreditation_id, num FROM uo_player WHERE num IS NOT NULL");
		while ($row = mysqli_fetch_assoc($results)) {
			$query = sprintf(
				"UPDATE uo_player_profile SET num='%s' WHERE accreditation_id='%s'",
				trim($row['num']),
				$row['accreditation_id']
			);
			runQuery($query);
		}
	}
	if (!hasColumn('uo_player_profile', 'profile_id')) {
		addColumn('uo_player_profile', 'profile_id', "int(10) NOT NULL");

		runQuery("UPDATE uo_player_profile SET profile_id=accreditation_id");
		runQuery("ALTER TABLE uo_player_profile DROP PRIMARY KEY");
		runQuery("ALTER TABLE uo_player_profile MODIFY accreditation_id VARCHAR(50)");
		runQuery("ALTER TABLE uo_player_profile AUTO_INCREMENT=100000");
		runQuery("ALTER TABLE uo_player_profile change profile_id profile_id int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY");

		addColumn('uo_player', 'profile_id', "int(10)");
		runQuery("UPDATE uo_player SET profile_id=accreditation_id");

		runQuery("ALTER TABLE uo_player_stats change accreditation_id profile_id int(10) NOT NULL");
		//runQuery("alter table uo_player drop column accreditation_id");
	}
}

function upgrade59()
{
	if (!hasColumn('uo_reservation', 'timeslots')) {
		addColumn('uo_reservation', 'timeslots', "varchar(100) DEFAULT NULL");
	}
	if (!hasColumn('uo_reservation', 'date')) {
		addColumn('uo_reservation', 'date', "datetime DEFAULT NULL");
		$results = runQuery("SELECT * FROM uo_reservation WHERE starttime IS NOT NULL");
		while ($row = mysqli_fetch_assoc($results)) {
			$query = sprintf(
				"UPDATE uo_reservation SET date='%s' WHERE id='%s'",
				ToInternalTimeFormat(ShortDate($row['starttime'])),
				$row['id']
			);
			runQuery($query);
		}
	}
}

function upgrade60()
{

	$dprofiles = runQuery("SELECT * FROM uo_player_profile WHERE accreditation_id!=profile_id");
	while ($profile = mysqli_fetch_assoc($dprofiles)) {
		runQuery("DELETE FROM uo_player_profile WHERE accreditation_id='" . $profile['accreditation_id'] . "'");
	}

	$licenses = runQuery("SELECT * FROM uo_license");
	while ($license = mysqli_fetch_assoc($licenses)) {

		$hasprofile = runQuery("SELECT * FROM uo_player_profile WHERE accreditation_id='" . $license['accreditation_id'] . "'");

		if (mysqli_num_rows($hasprofile) == 0) {
			$query = sprintf(
				"INSERT INTO uo_player_profile (profile_id,firstname,lastname,birthdate,accreditation_id) VALUES
				('%s','%s','%s','%s','%s')",
				DBEscapeString($license['accreditation_id']),
				DBEscapeString($license['firstname']),
				DBEscapeString($license['lastname']),
				DBEscapeString($license['birthdate']),
				DBEscapeString($license['accreditation_id'])
			);
			$profileId = DBQueryInsert($query);
		}
	}

	$players = runQuery("SELECT * FROM uo_player GROUP BY profile_id");
	while ($player = mysqli_fetch_assoc($players)) {

		$hasprofile = runQuery("SELECT * FROM uo_player_profile WHERE profile_id='" . $player['profile_id'] . "'");

		if (mysqli_num_rows($hasprofile) == 0) {
			$query = sprintf(
				"INSERT INTO uo_player_profile (profile_id,firstname,lastname,num) VALUES
				('%s','%s','%s','%s')",
				DBEscapeString($player['profile_id']),
				DBEscapeString($player['firstname']),
				DBEscapeString($player['lastname']),
				DBEscapeString($player['num'])
			);
			$profileId = DBQueryInsert($query);
		}
	}
}

function upgrade61()
{
	if (!hasColumn('uo_player_profile', 'ffindr_id')) {
		addColumn('uo_player_profile', 'ffindr_id', "int(10) DEFAULT NULL");
	}
	if (!hasColumn('uo_team_profile', 'ffindr_id')) {
		addColumn('uo_team_profile', 'ffindr_id', "int(10) DEFAULT NULL");
	}
}

function upgrade62()
{
	runQuery("ALTER TABLE uo_player_profile MODIFY profile_image VARCHAR(30)");
}

function upgrade63()
{
	if (!hasTable("uo_pageload_counter")) {
		runQuery("CREATE TABLE uo_pageload_counter(
  		id int(11) NOT NULL auto_increment,
  		PRIMARY KEY(id),
  		page varchar(100) NOT NULL,
  		loads int(11))");
	}
	if (!hasTable("uo_visitor_counter")) {
		runQuery("CREATE TABLE uo_visitor_counter(
  		id int(11) NOT NULL auto_increment,
  		ip varchar(15) NOT NULL default '',
  		visits int(11),
  		PRIMARY KEY (id))");
	}
}

function upgrade64()
{
	if (!hasRow("uo_setting", "name", "PageTitle")) {
		runQuery('INSERT INTO uo_setting (name, value) VALUES ("PageTitle", "Ultiorganizer - ")');
	}
}

function upgrade65()
{
	if (!hasRow("uo_setting", "name", "DefaultTimezone")) {
		runQuery('INSERT INTO uo_setting (name, value) VALUES ("DefaultTimezone", "Europe/Helsinki")');
	}
	if (!hasRow("uo_setting", "name", "DefaultLocale")) {
		runQuery('INSERT INTO uo_setting (name, value) VALUES ("DefaultLocale", "en_GB.utf8")');
	}
}


function upgrade66()
{
	if (!hasTable("uo_defense")) {
		runQuery("CREATE TABLE `uo_defense` (
	`game` int(10) NOT NULL,
	`num` smallint(5) NOT NULL,
	`author` int(10) DEFAULT NULL,
	`time` smallint(5) DEFAULT NULL,
	`iscallahan` tinyint(1) NOT NULL,
	`iscaught` tinyint(1) NOT NULL,
	`ishomedefense` tinyint(1) NOT NULL,
	PRIMARY KEY (`game`,`num`),
	INDEX `idx_game` (`game`),
	INDEX `idx_player` (`author`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci");
	}
	if (!hasColumn('uo_player_stats', 'defenses')) {
		addColumn('uo_player_stats', 'defenses', "int(5) DEFAULT 0");
	}
	if (!hasColumn('uo_season_stats', 'defenses_total')) {
		addColumn('uo_season_stats', 'defenses_total', "int(5) DEFAULT 0");
	}
	if (!hasColumn('uo_series_stats', 'defenses_total')) {
		addColumn('uo_series_stats', 'defenses_total', "int(5) DEFAULT 0");
	}
	if (!hasColumn('uo_team_stats', 'defenses_total')) {
		addColumn('uo_team_stats', 'defenses_total', "int(5) DEFAULT 0");
	}
	if (!hasColumn('uo_game', 'homedefenses')) {
		addColumn('uo_game', 'homedefenses', "smallint(5) DEFAULT 0");
	}
	if (!hasColumn('uo_game', 'defenses_total')) {
		addColumn('uo_game', 'visitordefenses', "smallint(5) DEFAULT 0");
	}
}

function upgrade67()
{
	if (!hasColumn("uo_series", "color")) {
		addColumn('uo_series', 'color', "varchar(6) DEFAULT NULL");
	}
	if (!hasColumn("uo_series", "pool_template")) {
		addColumn('uo_series', 'pool_template', "int(10) DEFAULT NULL");
	}

	if (!hasRow("uo_setting", "name", "ShowDefenseStats")) {
		runQuery('INSERT INTO uo_setting (name, value) VALUES ("ShowDefenseStats", "false")');
	}
}

function upgrade68()
{
	if (!hasTable("uo_spirit")) {
		runQuery("CREATE TABLE `uo_spirit` (
		`game_id` INT(10) NOT NULL,
		`team_id` INT(10) NOT NULL,
		`cat1` TINYINT(2) NOT NULL DEFAULT 0,
		`cat2` TINYINT(2) NOT NULL DEFAULT 0,
		`cat3` TINYINT(2) NOT NULL DEFAULT 0,
		`cat4` TINYINT(2) NOT NULL DEFAULT 0,
		`cat5` TINYINT(2) NOT NULL DEFAULT 0,
		PRIMARY KEY (game_id,team_id)
		)
		COLLATE='latin1_swedish_ci'
		ENGINE=MyISAM
		ROW_FORMAT=DEFAULT
		");
	}
}

function upgrade69()
{
	if (!hasColumn("uo_pool", "drawsallowed")) {
		addColumn("uo_pool", "drawsallowed", "smallint(5) DEFAULT 0");
	}
	if (!hasColumn("uo_pooltemplate", "drawsallowed")) {
		addColumn("uo_pooltemplate", "drawsallowed", "smallint(5) DEFAULT 0");
	}
	if (!hasColumn("uo_game", "hasstarted")) {
		runQuery("UPDATE uo_game SET time=NULL WHERE time < '0000-01-01 00:00:00';");
		addColumn("uo_game", "hasstarted", "tinyint(1) DEFAULT 0");
		runQuery("UPDATE uo_game SET hasstarted='1' WHERE isongoing>0 OR homescore>0 OR visitorscore>0");
	}
}

function upgrade70()
{
	if (!hasTable("uo_movingtime")) {
		runQuery("CREATE TABLE `uo_movingtime` (
	`season` varchar(10) NOT NULL,
    `fromlocation` int(10) NOT NULL,
    `fromfield` varchar(50) NOT NULL,
	`tolocation` int(10) NOT NULL,
    `tofield` varchar(50) NOT NULL,
    `time` int(10) DEFAULT 0,
	PRIMARY KEY (`season`,`fromlocation`,`fromfield`,`tolocation`,`tofield`),
	INDEX `idx_season` (`season`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci");
	}
}

function upgrade71()
{
	if (!hasTable("uo_location_info")) {
		runQuery(
			"CREATE TABLE `uo_location_info` (
	`location_id` INT(10) NOT NULL,
    `locale` varchar(20) NOT NULL,
    `info` varchar(255) DEFAULT NULL,
	PRIMARY KEY (`location_id`,`locale`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci"
		);
	}

	$results = runQuery("SELECT * FROM uo_location");
	while ($row = mysqli_fetch_assoc($results)) {
		foreach ($row as $key => $value) {
			if (substr($key, 0, 5) === "info_") {
				if (!empty($value)) {
					$locale = substr($key, 5);
					runQuery(
						sprintf(
							'INSERT INTO `uo_location_info` (`location_id`, `locale`, `info`)
            VALUES ("%d", "%s", "%s")',
							$row['id'],
							DBEscapeString($locale),
							DBEscapeString($value)
						)
					);
				}
			}
		}
	}
}

function upgrade72()
{
	renameField('uo_team_stats', 'loses', 'losses');
}

function upgrade73()
{
	addColumn("uo_pool", "playoff_template", "varchar(30) default NULL");
}

function upgrade74()
{
	if (!hasTable("uo_comment")) {
		runQuery(
			"CREATE TABLE `uo_comment` (
    `type` tinyint(3) NOT NULL,
    `id` varchar(10) NOT NULL,
    `comment` text NOT NULL,
	PRIMARY KEY (`type`,`id`),
    INDEX `idx_id` (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci"
		);
	}
}

function upgrade75()
{
	if (!hasTable("uo_spirit_category")) {
		runQuery("CREATE TABLE `uo_spirit_category` (
        `category_id` INT(10) NOT NULL AUTO_INCREMENT,
        `mode` INT(10) NOT NULL,
        `group` INT(5) NOT NULL DEFAULT 1,
        `index` INT(5) NOT NULL,
        `min` INT(5) NOT NULL DEFAULT 0,
        `max` INT(5) NOT NULL DEFAULT 4,
        `factor` INT(5) NOT NULL DEFAULT 1,
        `text` text NOT NULL,
        PRIMARY KEY (`category_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci AUTO_INCREMENT=1000");

		// the gettext strings have no function here, but are needed so gettext replaces things like _($category) later ...
		runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1001", 0, "One simple score")');
		_("One simple score");
		runQuery(
			'INSERT INTO uo_spirit_category (`mode`, `index`, `min`, `max`, `text`) VALUES ("1001", 1, 0, 20, "Spirit score")'
		);
		_("Spirit score");

		runQuery(
			'INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1002", 0, "WFDF (four categories plus comparison)")'
		);
		_("WFDF (four categories plus comparison)");
		runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1002", 1, "Rules Knowledge and Use")');
		_("Rules Knowledge and Use");
		runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1002", 2, "Fouls and Body Contact")');
		_("Fouls and Body Contact");
		runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1002", 3, "Fair-Mindedness")');
		_("Fair-Mindedness");
		runQuery(
			'INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1002", 4, "Positive Attitude and Self-Control")'
		);
		_("Positive Attitude and Self-Control");
		runQuery(
			'INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1002", 5, "Our Spirit compared to theirs")'
		);
		_("Our Spirit compared to theirs");

		runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1003", 0, "WFDF (five categories)")');
		_("WFDF (five categories)");
		runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1003", 1, "Rules Knowledge and Use")');
		_("Rules Knowledge and Use");
		runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1003", 2, "Fouls and Body Contact")');
		_("Fouls and Body Contact");
		runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1003", 3, "Fair-Mindedness")');
		_("Fair-Mindedness");
		runQuery(
			'INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1003", 4, "Positive Attitude and Self-Control")'
		);
		_("Positive Attitude and Self-Control");
		runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1003", 5, "Communication")');
		_("Communication");

		runQuery(
			'INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1004", 0, "WFDF (five categories, theirs and ours)")'
		);
		_("WFDF (five categories, theirs and ours)");
		runQuery(
			'INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1004", 1, "Rules Knowledge and Use (theirs)")'
		);
		_("Rules Knowledge and Use (theirs)");
		runQuery(
			'INSERT INTO uo_spirit_category (`mode`, `group`, `index`, `factor`, `text`) VALUES ("1004", 1, 2, 0, "Rules Knowledge and Use (ours)")'
		);
		_("Rules Knowledge and Use (ours)");
		runQuery(
			'INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1004", 3, "Fouls and Body Contact (theirs)")'
		);
		_("Fouls and Body Contact (theirs)");
		runQuery(
			'INSERT INTO uo_spirit_category (`mode`, `group`, `index`, `factor`, `text`) VALUES ("1004", 1, 4, 0, "Fouls and Body Contact (ours)")'
		);
		_("Fouls and Body Contact (ours)");
		runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1004", 5, "Fair-Mindedness (theirs)")');
		_("Fair-Mindedness (theirs)");
		runQuery(
			'INSERT INTO uo_spirit_category (`mode`, `group`, `index`, `factor`, `text`) VALUES ("1004", 1, 6, 0, "Fair-Mindedness (ours)")'
		);
		_("Fair-Mindedness (ours)");
		runQuery(
			'INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1004", 7, "Positive Attitude and Self-Control (theirs)")'
		);
		_("Positive Attitude and Self-Control (theirs)");
		runQuery(
			'INSERT INTO uo_spirit_category (`mode`, `group`, `index`, `factor`, `text`) VALUES ("1004", 1, 8, 0, "Positive Attitude and Self-Control (ours)")'
		);
		_("Positive Attitude and Self-Control (ours)");
		runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1004", 9, "Communication (theirs)")');
		_("Communication (theirs)");
		runQuery(
			'INSERT INTO uo_spirit_category (`mode`, `group`, `index`, `factor`, `text`) VALUES ("1004", 1, 10, 0, "Communication (ours)")'
		);
		_("Communication (ours)");

		runQuery("CREATE TABLE `uo_spirit_score` (
        `game_id` INT(10) NOT NULL,
        `team_id` INT(10) NOT NULL,
		`category_id` INT(10) NOT NULL,
        `value` INT (3) DEFAULT NULL,
        PRIMARY KEY (`game_id`, `team_id`, `category_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci AUTO_INCREMENT=1000");

		addColumn('uo_season', 'spiritmode', 'INT(10) DEFAULT NULL');
		if (CUSTOMIZATIONS == "slkl") {
			runQuery("UPDATE uo_season SET `spiritmode` = 1003 WHERE `spiritpoints`=1");
			$categoriesResult = runQuery("SELECT * FROM `uo_spirit_category` WHERE mode=1003");
		} else {
			// set all to 1001
			runQuery("UPDATE uo_season SET `spiritmode` = 1001 WHERE `spiritpoints`=1");

			// update WFDF scores
			$categoriesResult = runQuery("SELECT * FROM `uo_spirit_category` WHERE mode=1002");
		}

		$categories = array();
		while ($cat = mysqli_fetch_assoc($categoriesResult)) {
			$categories[$cat['index']] = $cat['category_id'];
		}

		$lastSeason = null;

		$query =
			"SELECT st.*, sn.season_id
       FROM uo_spirit st
       LEFT JOIN uo_game g on (g.game_id = st.game_id)
       LEFT JOIN uo_pool p on (g.pool = p.pool_id)
       LEFT JOIN uo_series ss on (p.series = ss.series_id)
       LEFT JOIN uo_season sn on (ss.season = sn.season_id)";
		$results = runQuery($query);

		while ($row = mysqli_fetch_assoc($results)) {
			for ($i = 1; $i <= 5; ++$i) {
				runQuery(
					sprintf(
						"INSERT INTO `uo_spirit_score` (`game_id`, `team_id`, `category_id`, `value`)
               VALUES (%d, %d, %d, %d)",
						$row['game_id'],
						$row['team_id'],
						$categories[$i],
						$row['cat' . $i]
					)
				);
			}
			if ($lastSeason != $row['season_id']) {
				$lastSeason = $row['season_id'];
				runQuery(sprintf(
					"UPDATE uo_season SET `spiritmode` = 1002 WHERE `spiritpoints`=1 AND season_id=%d",
					(int)$lastSeason
				));
			}
		}

		// update remaining, simple scores
		$categoriesResult = runQuery("SELECT * FROM `uo_spirit_category` WHERE mode=1001");
		$categories = array();
		while ($cat = mysqli_fetch_assoc($categoriesResult)) {
			$categories[$cat['index']] = $cat['category_id'];
		}

		$query =
			"SELECT g.game_id, g.hometeam, g.visitorteam, g.homesotg, g.visitorsotg
    FROM uo_game g
    LEFT JOIN uo_pool p on (g.pool = p.pool_id)
    LEFT JOIN uo_series ss on (p.series = ss.series_id)
    LEFT JOIN uo_season sn on (ss.season = sn.season_id)
    WHERE
    (g.homesotg IS NOT NULL OR g.visitorsotg IS NOT NULL)
    AND sn.spiritmode = 1001";
		$results = runQuery($query);
		while ($row = mysqli_fetch_assoc($results)) {
			runQuery(sprintf(
				"INSERT INTO `uo_spirit_score` (game_id, team_id, category_id, value)
             VALUES (%d, %d, %d, %d)",
				$row['game_id'],
				$row['hometeam'],
				$categories[1],
				$row['homesotg']
			));
		}

		/* 
     // undo:
     DROP TABLE uo_spirit_category;
     DROP TABLE uo_spirit_score;
     ALTER TABLE uo_season DROP spiritmode;
     DELETE FROM uo_database WHERE version=76;
    */

		// clean up
		runQuery('DROP TABLE uo_spirit');
		runQuery("UPDATE uo_game SET time=NULL WHERE time < '0000-01-01 00:00:00';");
		dropField("uo_game", "homesotg");
		dropField("uo_game", "visitorsotg");
		dropField("uo_season", "spiritpoints");
	}
}

function upgrade76()
{
	global $locales;
	if (!hasTable("uo_translation")) {
		runQuery("CREATE TABLE `uo_translation` (
      `translation_key` varchar(50) NOT NULL,
      `locale` varchar(15) NOT NULL,
      `translation` varchar(100) NOT NULL,
      PRIMARY KEY (`translation_key`, `locale`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci");

		// Ensure we have locales even if config did not set them (e.g., during CLI upgrades).
		if (!is_array($locales) || empty($locales)) {
			$locales = array();
			$cols = runQuery("SHOW COLUMNS FROM uo_dbtranslations");
			while ($col = mysqli_fetch_assoc($cols)) {
				if ($col['Field'] === 'translation_key') {
					continue;
				}
				// Use column name as both key and label if nothing better is available.
				$locales[$col['Field']] = $col['Field'];
			}
		}

		foreach ($locales as $localestr => $localename) {
			$loc = DBEscapeString(str_replace(".", "_", $localestr));
			runQuery(sprintf(
				"INSERT INTO uo_translation 
          (SELECT translation_key, '%s' AS locale, `%s` AS translation 
           FROM uo_dbtranslations
           WHERE `%s` IS NOT NULL)",
				$loc,
				$loc,
				$loc
			));
		}
		runQuery("DROP TABLE uo_dbtranslations");
	}
}

function upgrade77()
{
	runQuery("ALTER TABLE uo_users MODIFY password varchar(255) DEFAULT NULL");
	runQuery("ALTER TABLE uo_registerrequest MODIFY password varchar(255) DEFAULT NULL");
}

function upgrade78()
{
	addIndex("uo_game_pool", "idx_pool_timetable_game", "(pool, timetable, game)");
	addIndex("uo_goal", "idx_goal_game_scorer", "(game, scorer)");
	addIndex("uo_goal", "idx_goal_game_assist", "(game, assist)");
	addIndex("uo_goal", "idx_goal_game_callahan_scorer", "(game, iscallahan, scorer)");
	addIndex("uo_game", "idx_game_valid_time", "(valid, time)");
	addIndex("uo_game", "idx_game_valid_pool_time", "(valid, pool, time)");
	addIndex("uo_game", "idx_game_valid_hometeam_time", "(valid, hometeam, time)");
	addIndex("uo_game", "idx_game_valid_visitorteam_time", "(valid, visitorteam, time)");
	addIndex("uo_reservation", "idx_reservation_group_time_loc_field", "(reservationgroup, starttime, location, fieldname)");
	addIndex("uo_pool", "idx_pool_series_ordering", "(series, ordering)");
	addIndex("uo_series", "idx_series_season_ordering", "(season, ordering)");
}

function upgrade79()
{
	$created = false;
	if (!hasTable("uo_team_spirit_stats")) {
		runQuery("CREATE TABLE `uo_team_spirit_stats` (
      `team_id` int(10) NOT NULL,
      `season` varchar(10) DEFAULT NULL,
      `series` int(10) DEFAULT NULL,
      `category_id` int(10) NOT NULL,
      `games` int(5) DEFAULT 0,
      `average` decimal(6,2) DEFAULT 0,
      PRIMARY KEY (`team_id`,`category_id`),
      KEY `fk_team_spirit_stats_team` (`team_id`),
      KEY `fk_team_spirit_stats_category` (`category_id`),
      KEY `fk_team_spirit_stats_series` (`series`),
      KEY `fk_team_spirit_stats_season` (`season`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$created = true;
	}

	if ($created || hasTable("uo_team_spirit_stats")) {
		$seasons = runQuery(
			"SELECT se.season_id, se.spiritmode
				FROM uo_season se
				INNER JOIN uo_season_stats ss ON (ss.season = se.season_id)
				WHERE se.spiritmode IS NOT NULL AND se.spiritmode > 0"
		);
		while ($row = mysqli_fetch_assoc($seasons)) {
			$season_safe = DBEscapeString($row['season_id']);
			$mode = (int)$row['spiritmode'];
			$query = sprintf(
				"INSERT INTO uo_team_spirit_stats (team_id, season, series, category_id, games, average)
				SELECT ssc.team_id, '%s', ser.series_id, ssc.category_id,
					COUNT(*) AS games, AVG(ssc.value) AS average
				FROM uo_spirit_score ssc
				LEFT JOIN uo_spirit_category sct ON (sct.category_id = ssc.category_id)
				LEFT JOIN uo_game g ON (g.game_id = ssc.game_id)
				LEFT JOIN uo_game_pool gp ON (gp.game = g.game_id)
				LEFT JOIN uo_pool p ON (p.pool_id = gp.pool)
				LEFT JOIN uo_series ser ON (ser.series_id = p.series)
				WHERE ser.season='%s'
					AND sct.mode=%d
					AND gp.timetable=1
					AND g.isongoing=0
					AND g.hasstarted>0
				GROUP BY ssc.team_id, ssc.category_id, ser.series_id
				ON DUPLICATE KEY UPDATE
					season=VALUES(season),
					series=VALUES(series),
					games=VALUES(games),
					average=VALUES(average)",
				$season_safe,
				$season_safe,
				$mode
			);
			runQuery($query);
		}
	}
}

function upgrade80()
{
	if (!hasColumn('uo_season', 'use_season_points')) {
		runQuery("ALTER TABLE uo_season ADD use_season_points tinyint(1) DEFAULT 0");
	}

	if (!hasTable("uo_season_round")) {
		runQuery("CREATE TABLE `uo_season_round` (
			`round_id` int(10) NOT NULL AUTO_INCREMENT,
			`season` varchar(10) NOT NULL,
			`series` int(10) NOT NULL,
			`round_no` int(10) NOT NULL,
			`name` varchar(100) NOT NULL,
			PRIMARY KEY (`round_id`),
			UNIQUE KEY `uq_season_round_season_series_no` (`season`,`series`,`round_no`),
			KEY `idx_season_round_season` (`season`),
			KEY `idx_season_round_series` (`series`),
			CONSTRAINT `fk_season_round_season` FOREIGN KEY (`season`) REFERENCES `uo_season` (`season_id`) ON DELETE CASCADE ON UPDATE CASCADE,
			CONSTRAINT `fk_season_round_series` FOREIGN KEY (`series`) REFERENCES `uo_series` (`series_id`) ON DELETE CASCADE ON UPDATE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}

	if (!hasTable("uo_season_points")) {
		runQuery("CREATE TABLE `uo_season_points` (
			`round_id` int(10) NOT NULL,
			`team_id` int(10) NOT NULL,
			`points` int(10) NOT NULL DEFAULT 0,
			PRIMARY KEY (`round_id`,`team_id`),
			KEY `idx_season_points_team` (`team_id`),
			CONSTRAINT `fk_season_points_round` FOREIGN KEY (`round_id`) REFERENCES `uo_season_round` (`round_id`) ON DELETE CASCADE ON UPDATE CASCADE,
			CONSTRAINT `fk_season_points_team` FOREIGN KEY (`team_id`) REFERENCES `uo_team` (`team_id`) ON DELETE CASCADE ON UPDATE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}
}

function upgrade81()
{
	if (!hasTable("uo_api_token")) {
		runQuery("CREATE TABLE `uo_api_token` (
			`token_id` int(10) NOT NULL AUTO_INCREMENT,
			`token_hash` char(64) NOT NULL,
			`token_value` varchar(100) DEFAULT NULL,
			`label` varchar(100) DEFAULT NULL,
			`scope_type` varchar(20) NOT NULL,
			`scope_id` varchar(50) DEFAULT NULL,
			`revoked` tinyint(1) NOT NULL DEFAULT 0,
			`created_at` timestamp NOT NULL DEFAULT current_timestamp(),
			`last_used` timestamp NULL DEFAULT NULL,
			PRIMARY KEY (`token_id`),
			UNIQUE KEY `uq_api_token_hash` (`token_hash`),
			KEY `idx_api_token_scope` (`scope_type`,`scope_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}

	if (!hasTable("uo_api_rate_limit")) {
		runQuery("CREATE TABLE `uo_api_rate_limit` (
			`rate_key` varchar(128) NOT NULL,
			`window_start` int(10) NOT NULL,
			`request_count` int(10) NOT NULL,
			`updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
			PRIMARY KEY (`rate_key`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}

	if (!hasColumn('uo_season', 'api_public')) {
		addColumn('uo_season', 'api_public', "tinyint(1) DEFAULT 0");
	}
}

function upgradeEngineToInnoDb() {
    $charset = 'utf8mb4';
    $collation = 'utf8mb4_unicode_ci';

    // Clean nullable references and ensure no orphans before conversion.
    cleanupNullableOrphans();
    $errors = findOrphanErrors();
    if (count($errors)) {
        $instructions = "Cannot add foreign keys:\n" . implode("\n", $errors) . "\n";
        throw new Exception($instructions);
    }

    runQuery(sprintf(
        "ALTER DATABASE `%s` CHARACTER SET %s COLLATE %s",
        DB_DATABASE,
        $charset,
        $collation
    ));

    $tables = runQuery(sprintf("SHOW TABLES FROM `%s`", DB_DATABASE));
    while ($row = mysqli_fetch_row($tables)) {
        $table = $row[0];
        runQuery(sprintf(
            "ALTER TABLE `%s` CONVERT TO CHARACTER SET %s COLLATE %s, ENGINE=InnoDB",
            $table,
            $charset,
            $collation
        ));
    }

	// Add foreign keys now that all tables use InnoDB.
	addInnoDbForeignKeys();
}


function runQuery($query)
{
	global $mysqlconnectionref;
	$result = mysqli_query($mysqlconnectionref, $query);
	return $result;
}

function addColumn($table, $column, $type)
{
	if (hasColumn($table, $column)) {
		runQuery("alter table " . $table . " drop column " . $column);
	}
	runQuery("alter table " . $table . " add " . $column . " " . $type);
}
function hasColumn($table, $column)
{
	global $mysqlconnectionref;
	$tableQuery = sprintf("SHOW COLUMNS FROM `%s` LIKE '%s'", $table, DBEscapeString($column));
	$result = mysqli_query($mysqlconnectionref, $tableQuery);
	if (!$result) {
		return false;
	}
	return mysqli_num_rows($result) > 0;
}

function hasIndex($table, $index)
{
	global $mysqlconnectionref;
	$indexQuery = sprintf("SHOW INDEX FROM `%s` WHERE Key_name = '%s'", $table, DBEscapeString($index));
	$result = mysqli_query($mysqlconnectionref, $indexQuery);
	if (!$result) {
		return false;
	}
	return mysqli_num_rows($result) > 0;
}

function addIndex($table, $index, $definition)
{
	if (hasIndex($table, $index)) {
		return;
	}
	runQuery(sprintf("ALTER TABLE `%s` ADD INDEX `%s` %s", $table, $index, $definition));
}

/**
 * Add a foreign key if it does not already exist.
 */
function addForeignKey($table, $constraint, $definition)
{
	$existsQuery = sprintf(
		"SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = '%s' AND TABLE_NAME = '%s' AND CONSTRAINT_NAME = '%s' AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
		DBEscapeString(DB_DATABASE),
		DBEscapeString($table),
		DBEscapeString($constraint)
	);
	$exists = runQuery($existsQuery);
	if ($exists && mysqli_num_rows($exists) > 0) {
		return;
	}

	runQuery(sprintf("ALTER TABLE `%s` ADD CONSTRAINT `%s` %s", $table, $constraint, $definition));
}

/**
 * Null out nullable references that point to missing parents.
 */
function cleanupNullableOrphans()
{
	$cleanup = array(
		"UPDATE uo_club c LEFT JOIN uo_country k ON k.country_id = c.country SET c.country = NULL WHERE c.country IS NOT NULL AND (c.country = 0 OR k.country_id IS NULL)",
		"UPDATE uo_team t LEFT JOIN uo_club c ON c.club_id = t.club SET t.club = NULL WHERE t.club IS NOT NULL AND (t.club = 0 OR c.club_id IS NULL)",
		"UPDATE uo_team t LEFT JOIN uo_country c ON c.country_id = t.country SET t.country = NULL WHERE t.country IS NOT NULL AND (t.country = 0 OR c.country_id IS NULL)",
		"UPDATE uo_team t LEFT JOIN uo_pool p ON p.pool_id = t.pool SET t.pool = NULL WHERE t.pool IS NOT NULL AND (t.pool = 0 OR p.pool_id IS NULL)",
		"UPDATE uo_team t LEFT JOIN uo_series s ON s.series_id = t.series SET t.series = NULL WHERE t.series IS NOT NULL AND (t.series = 0 OR s.series_id IS NULL)",
		"UPDATE uo_player p LEFT JOIN uo_team t ON t.team_id = p.team SET p.team = NULL WHERE p.team IS NOT NULL AND (p.team = 0 OR t.team_id IS NULL)",
		"UPDATE uo_player p LEFT JOIN uo_player_profile pr ON pr.profile_id = p.profile_id SET p.profile_id = NULL WHERE p.profile_id IS NOT NULL AND (p.profile_id = 0 OR pr.profile_id IS NULL)",
		"UPDATE uo_game g LEFT JOIN uo_team t ON t.team_id = g.hometeam SET g.hometeam = NULL WHERE g.hometeam IS NOT NULL AND (g.hometeam = 0 OR t.team_id IS NULL)",
		"UPDATE uo_game g LEFT JOIN uo_team t ON t.team_id = g.visitorteam SET g.visitorteam = NULL WHERE g.visitorteam IS NOT NULL AND (g.visitorteam = 0 OR t.team_id IS NULL)",
		"UPDATE uo_game g LEFT JOIN uo_pool p ON p.pool_id = g.pool SET g.pool = NULL WHERE g.pool IS NOT NULL AND (g.pool = 0 OR p.pool_id IS NULL)",
		"UPDATE uo_game g LEFT JOIN uo_reservation r ON r.id = g.reservation SET g.reservation = NULL WHERE g.reservation IS NOT NULL AND (g.reservation = 0 OR r.id IS NULL)",
		"UPDATE uo_goal go LEFT JOIN uo_player p ON p.player_id = go.assist SET go.assist = NULL WHERE go.assist IS NOT NULL AND (go.assist = 0 OR p.player_id IS NULL)",
		"UPDATE uo_goal go LEFT JOIN uo_player p ON p.player_id = go.scorer SET go.scorer = NULL WHERE go.scorer IS NOT NULL AND (go.scorer = 0 OR p.player_id IS NULL)",
		"UPDATE uo_moveteams m LEFT JOIN uo_scheduling_name s ON s.scheduling_id = m.scheduling_id SET m.scheduling_id = NULL WHERE m.scheduling_id IS NOT NULL AND (m.scheduling_id = 0 OR s.scheduling_id IS NULL)",
		"UPDATE uo_series s LEFT JOIN uo_pooltemplate pt ON pt.template_id = s.pool_template SET s.pool_template = NULL WHERE s.pool_template IS NOT NULL AND (s.pool_template = 0 OR pt.template_id IS NULL)",
		"UPDATE uo_defense d LEFT JOIN uo_player p ON p.player_id = d.author SET d.author = NULL WHERE d.author IS NOT NULL AND (d.author = 0 OR p.player_id IS NULL)"
	);
	foreach ($cleanup as $q) {
		runQuery($q);
	}
}

/**
 * Return a list of orphan error strings blocking FK creation.
 */
function findOrphanErrors()
{
	$errors = array();
	$orphanChecks = array(
		"uo_club.country" => "SELECT 1 FROM uo_club c LEFT JOIN uo_country k ON k.country_id = c.country WHERE c.country IS NOT NULL AND k.country_id IS NULL LIMIT 1",
		"uo_team.club" => "SELECT 1 FROM uo_team t LEFT JOIN uo_club c ON c.club_id = t.club WHERE t.club IS NOT NULL AND c.club_id IS NULL LIMIT 1",
		"uo_team.country" => "SELECT 1 FROM uo_team t LEFT JOIN uo_country c ON c.country_id = t.country WHERE t.country IS NOT NULL AND c.country_id IS NULL LIMIT 1",
		"uo_team.pool" => "SELECT 1 FROM uo_team t LEFT JOIN uo_pool p ON p.pool_id = t.pool WHERE t.pool IS NOT NULL AND p.pool_id IS NULL LIMIT 1",
		"uo_team.series" => "SELECT 1 FROM uo_team t LEFT JOIN uo_series s ON s.series_id = t.series WHERE t.series IS NOT NULL AND s.series_id IS NULL LIMIT 1",
		"uo_player.team" => "SELECT 1 FROM uo_player p LEFT JOIN uo_team t ON t.team_id = p.team WHERE p.team IS NOT NULL AND t.team_id IS NULL LIMIT 1",
		"uo_player.profile_id" => "SELECT 1 FROM uo_player p LEFT JOIN uo_player_profile pr ON pr.profile_id = p.profile_id WHERE p.profile_id IS NOT NULL AND pr.profile_id IS NULL LIMIT 1",
		"uo_team_profile.team_id" => "SELECT 1 FROM uo_team_profile tp LEFT JOIN uo_team t ON t.team_id = tp.team_id WHERE t.team_id IS NULL LIMIT 1",
		"uo_player_stats.player_id" => "SELECT 1 FROM uo_player_stats ps LEFT JOIN uo_player p ON p.player_id = ps.player_id WHERE p.player_id IS NULL LIMIT 1",
		"uo_player_stats.profile_id" => "SELECT 1 FROM uo_player_stats ps LEFT JOIN uo_player_profile pr ON pr.profile_id = ps.profile_id WHERE pr.profile_id IS NULL LIMIT 1",
		"uo_player_stats.team" => "SELECT 1 FROM uo_player_stats ps LEFT JOIN uo_team t ON t.team_id = ps.team WHERE ps.team IS NOT NULL AND t.team_id IS NULL LIMIT 1",
		"uo_player_stats.series" => "SELECT 1 FROM uo_player_stats ps LEFT JOIN uo_series s ON s.series_id = ps.series WHERE ps.series IS NOT NULL AND s.series_id IS NULL LIMIT 1",
		"uo_player_stats.season" => "SELECT 1 FROM uo_player_stats ps LEFT JOIN uo_season se ON se.season_id = ps.season WHERE ps.season IS NOT NULL AND se.season_id IS NULL LIMIT 1",
		"uo_game.hometeam/visitorteam" => "SELECT 1 FROM uo_game g LEFT JOIN uo_team t1 ON t1.team_id = g.hometeam LEFT JOIN uo_team t2 ON t2.team_id = g.visitorteam WHERE (g.hometeam IS NOT NULL AND t1.team_id IS NULL) OR (g.visitorteam IS NOT NULL AND t2.team_id IS NULL) LIMIT 1",
		"uo_game.pool" => "SELECT 1 FROM uo_game g LEFT JOIN uo_pool p ON p.pool_id = g.pool WHERE g.pool IS NOT NULL AND p.pool_id IS NULL LIMIT 1",
		"uo_game.reservation" => "SELECT 1 FROM uo_game g LEFT JOIN uo_reservation r ON r.id = g.reservation WHERE g.reservation IS NOT NULL AND r.id IS NULL LIMIT 1",
		"uo_goal.game" => "SELECT 1 FROM uo_goal go LEFT JOIN uo_game g ON g.game_id = go.game WHERE go.game IS NOT NULL AND g.game_id IS NULL LIMIT 1",
		"uo_goal.assist/scorer" => "SELECT 1 FROM uo_goal go LEFT JOIN uo_player p1 ON p1.player_id = go.assist LEFT JOIN uo_player p2 ON p2.player_id = go.scorer WHERE (go.assist IS NOT NULL AND p1.player_id IS NULL) OR (go.scorer IS NOT NULL AND p2.player_id IS NULL) LIMIT 1",
		"uo_played.player/game" => "SELECT 1 FROM uo_played pl LEFT JOIN uo_player p ON p.player_id = pl.player LEFT JOIN uo_game g ON g.game_id = pl.game WHERE p.player_id IS NULL OR g.game_id IS NULL LIMIT 1",
		"uo_timeout.game" => "SELECT 1 FROM uo_timeout ti LEFT JOIN uo_game g ON g.game_id = ti.game WHERE ti.game IS NOT NULL AND g.game_id IS NULL LIMIT 1",
		"uo_gameevent.game" => "SELECT 1 FROM uo_gameevent ge LEFT JOIN uo_game g ON g.game_id = ge.game WHERE ge.game IS NOT NULL AND g.game_id IS NULL LIMIT 1",
		"uo_game_pool.game/pool" => "SELECT 1 FROM uo_game_pool gp LEFT JOIN uo_game g ON g.game_id = gp.game LEFT JOIN uo_pool p ON p.pool_id = gp.pool WHERE (gp.game IS NOT NULL AND g.game_id IS NULL) OR (gp.pool IS NOT NULL AND p.pool_id IS NULL) LIMIT 1",
		"uo_reservation.location" => "SELECT 1 FROM uo_reservation r LEFT JOIN uo_location l ON l.id = r.location WHERE r.location IS NOT NULL AND l.id IS NULL LIMIT 1",
		"uo_location_info.location_id" => "SELECT 1 FROM uo_location_info li LEFT JOIN uo_location l ON l.id = li.location_id WHERE li.location_id IS NOT NULL AND l.id IS NULL LIMIT 1",
		"uo_moveteams.frompool/topool" => "SELECT 1 FROM uo_moveteams m LEFT JOIN uo_pool p1 ON p1.pool_id = m.frompool LEFT JOIN uo_pool p2 ON p2.pool_id = m.topool WHERE p1.pool_id IS NULL OR p2.pool_id IS NULL LIMIT 1",
		"uo_moveteams.scheduling_id" => "SELECT 1 FROM uo_moveteams m LEFT JOIN uo_scheduling_name s ON s.scheduling_id = m.scheduling_id WHERE m.scheduling_id IS NOT NULL AND s.scheduling_id IS NULL LIMIT 1",
		"uo_movingtime.season/fromlocation/tolocation" => "SELECT 1 FROM uo_movingtime mt LEFT JOIN uo_season se ON se.season_id = mt.season LEFT JOIN uo_location l1 ON l1.id = mt.fromlocation LEFT JOIN uo_location l2 ON l2.id = mt.tolocation WHERE se.season_id IS NULL OR l1.id IS NULL OR l2.id IS NULL LIMIT 1",
		"uo_series.pool_template" => "SELECT 1 FROM uo_series s LEFT JOIN uo_pooltemplate pt ON pt.template_id = s.pool_template WHERE s.pool_template IS NOT NULL AND pt.template_id IS NULL LIMIT 1",
		"uo_enrolledteam.series/userid" => "SELECT 1 FROM uo_enrolledteam e LEFT JOIN uo_series s ON s.series_id = e.series LEFT JOIN uo_users u ON u.userid = e.userid WHERE s.series_id IS NULL OR u.userid IS NULL LIMIT 1",
		"uo_extraemail.userid" => "SELECT 1 FROM uo_extraemail ex LEFT JOIN uo_users u ON u.userid = ex.userid WHERE ex.userid IS NOT NULL AND u.userid IS NULL LIMIT 1",
		"uo_extraemailrequest.userid" => "SELECT 1 FROM uo_extraemailrequest ex LEFT JOIN uo_users u ON u.userid = ex.userid WHERE ex.userid IS NOT NULL AND u.userid IS NULL LIMIT 1",
		"uo_spirit_score.game/team/category" => "SELECT 1 FROM uo_spirit_score ss LEFT JOIN uo_game g ON g.game_id = ss.game_id LEFT JOIN uo_team t ON t.team_id = ss.team_id LEFT JOIN uo_spirit_category c ON c.category_id = ss.category_id WHERE g.game_id IS NULL OR t.team_id IS NULL OR c.category_id IS NULL LIMIT 1",
		"uo_defense.game/author" => "SELECT 1 FROM uo_defense d LEFT JOIN uo_game g ON g.game_id = d.game LEFT JOIN uo_player p ON p.player_id = d.author WHERE g.game_id IS NULL OR (d.author IS NOT NULL AND p.player_id IS NULL) LIMIT 1",
	);
	foreach ($orphanChecks as $label => $query) {
		$res = runQuery($query);
		if ($res && mysqli_num_rows($res) > 0) {
			$errors[] = "Orphaned rows for " . $label . ".\n";
		}
	}
	return $errors;
}

/**
 * Define the InnoDB foreign keys used by the schema.
 * This is applied after converting engines/charset to avoid MyISAM errors.
 */
function addInnoDbForeignKeys()
{
	addForeignKey('uo_club', 'fk_club_country', "FOREIGN KEY (`country`) REFERENCES `uo_country` (`country_id`) ON DELETE SET NULL ON UPDATE CASCADE");

	addForeignKey('uo_team', 'fk_team_club', "FOREIGN KEY (`club`) REFERENCES `uo_club` (`club_id`) ON DELETE SET NULL ON UPDATE CASCADE");
	addForeignKey('uo_team', 'fk_team_country', "FOREIGN KEY (`country`) REFERENCES `uo_country` (`country_id`) ON DELETE SET NULL ON UPDATE CASCADE");
	addForeignKey('uo_team', 'fk_team_pool', "FOREIGN KEY (`pool`) REFERENCES `uo_pool` (`pool_id`) ON DELETE SET NULL ON UPDATE CASCADE");
	addForeignKey('uo_team', 'fk_team_series', "FOREIGN KEY (`series`) REFERENCES `uo_series` (`series_id`) ON DELETE SET NULL ON UPDATE CASCADE");

	addForeignKey('uo_team_profile', 'fk_team_profile_team', "FOREIGN KEY (`team_id`) REFERENCES `uo_team` (`team_id`) ON DELETE CASCADE ON UPDATE CASCADE");

	addForeignKey('uo_team_stats', 'fk_team_stats_team', "FOREIGN KEY (`team_id`) REFERENCES `uo_team` (`team_id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_team_stats', 'fk_team_stats_series', "FOREIGN KEY (`series`) REFERENCES `uo_series` (`series_id`) ON DELETE SET NULL ON UPDATE CASCADE");
	addForeignKey('uo_team_stats', 'fk_team_stats_season', "FOREIGN KEY (`season`) REFERENCES `uo_season` (`season_id`) ON DELETE SET NULL ON UPDATE CASCADE");

	addForeignKey('uo_team_spirit_stats', 'fk_team_spirit_stats_team', "FOREIGN KEY (`team_id`) REFERENCES `uo_team` (`team_id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_team_spirit_stats', 'fk_team_spirit_stats_series', "FOREIGN KEY (`series`) REFERENCES `uo_series` (`series_id`) ON DELETE SET NULL ON UPDATE CASCADE");
	addForeignKey('uo_team_spirit_stats', 'fk_team_spirit_stats_season', "FOREIGN KEY (`season`) REFERENCES `uo_season` (`season_id`) ON DELETE SET NULL ON UPDATE CASCADE");
	addForeignKey('uo_team_spirit_stats', 'fk_team_spirit_stats_category', "FOREIGN KEY (`category_id`) REFERENCES `uo_spirit_category` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE");

	addForeignKey('uo_player', 'fk_player_team', "FOREIGN KEY (`team`) REFERENCES `uo_team` (`team_id`) ON DELETE SET NULL ON UPDATE CASCADE");
	addForeignKey('uo_player', 'fk_player_profile', "FOREIGN KEY (`profile_id`) REFERENCES `uo_player_profile` (`profile_id`) ON DELETE SET NULL ON UPDATE CASCADE");

	addForeignKey('uo_player_stats', 'fk_player_stats_player', "FOREIGN KEY (`player_id`) REFERENCES `uo_player` (`player_id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_player_stats', 'fk_player_stats_profile', "FOREIGN KEY (`profile_id`) REFERENCES `uo_player_profile` (`profile_id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_player_stats', 'fk_player_stats_team', "FOREIGN KEY (`team`) REFERENCES `uo_team` (`team_id`) ON DELETE SET NULL ON UPDATE CASCADE");
	addForeignKey('uo_player_stats', 'fk_player_stats_series', "FOREIGN KEY (`series`) REFERENCES `uo_series` (`series_id`) ON DELETE SET NULL ON UPDATE CASCADE");
	addForeignKey('uo_player_stats', 'fk_player_stats_season', "FOREIGN KEY (`season`) REFERENCES `uo_season` (`season_id`) ON DELETE SET NULL ON UPDATE CASCADE");

	addForeignKey('uo_game', 'fk_game_hometeam', "FOREIGN KEY (`hometeam`) REFERENCES `uo_team` (`team_id`) ON DELETE SET NULL ON UPDATE CASCADE");
	addForeignKey('uo_game', 'fk_game_visitorteam', "FOREIGN KEY (`visitorteam`) REFERENCES `uo_team` (`team_id`) ON DELETE SET NULL ON UPDATE CASCADE");
	addForeignKey('uo_game', 'fk_game_reservation', "FOREIGN KEY (`reservation`) REFERENCES `uo_reservation` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
	addForeignKey('uo_game', 'fk_game_pool', "FOREIGN KEY (`pool`) REFERENCES `uo_pool` (`pool_id`) ON DELETE SET NULL ON UPDATE CASCADE");

	addForeignKey('uo_goal', 'fk_goal_game', "FOREIGN KEY (`game`) REFERENCES `uo_game` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_goal', 'fk_goal_assist', "FOREIGN KEY (`assist`) REFERENCES `uo_player` (`player_id`) ON DELETE SET NULL ON UPDATE CASCADE");
	addForeignKey('uo_goal', 'fk_goal_scorer', "FOREIGN KEY (`scorer`) REFERENCES `uo_player` (`player_id`) ON DELETE SET NULL ON UPDATE CASCADE");

	addForeignKey('uo_played', 'fk_played_player', "FOREIGN KEY (`player`) REFERENCES `uo_player` (`player_id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_played', 'fk_played_game', "FOREIGN KEY (`game`) REFERENCES `uo_game` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE");

	addForeignKey('uo_timeout', 'fk_timeout_game', "FOREIGN KEY (`game`) REFERENCES `uo_game` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_gameevent', 'fk_gameevent_game', "FOREIGN KEY (`game`) REFERENCES `uo_game` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE");

	addForeignKey('uo_game_pool', 'fk_game_pool_game', "FOREIGN KEY (`game`) REFERENCES `uo_game` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_game_pool', 'fk_game_pool_pool', "FOREIGN KEY (`pool`) REFERENCES `uo_pool` (`pool_id`) ON DELETE CASCADE ON UPDATE CASCADE");

	addForeignKey('uo_reservation', 'fk_reservation_location', "FOREIGN KEY (`location`) REFERENCES `uo_location` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_location_info', 'fk_location_info_location', "FOREIGN KEY (`location_id`) REFERENCES `uo_location` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");

	addForeignKey('uo_moveteams', 'fk_moveteams_frompool', "FOREIGN KEY (`frompool`) REFERENCES `uo_pool` (`pool_id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_moveteams', 'fk_moveteams_topool', "FOREIGN KEY (`topool`) REFERENCES `uo_pool` (`pool_id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_moveteams', 'fk_moveteams_scheduling', "FOREIGN KEY (`scheduling_id`) REFERENCES `uo_scheduling_name` (`scheduling_id`) ON DELETE SET NULL ON UPDATE CASCADE");

	addForeignKey('uo_movingtime', 'fk_movingtime_season', "FOREIGN KEY (`season`) REFERENCES `uo_season` (`season_id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_movingtime', 'fk_movingtime_fromlocation', "FOREIGN KEY (`fromlocation`) REFERENCES `uo_location` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_movingtime', 'fk_movingtime_tolocation', "FOREIGN KEY (`tolocation`) REFERENCES `uo_location` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");

	addForeignKey('uo_series', 'fk_series_pooltemplate', "FOREIGN KEY (`pool_template`) REFERENCES `uo_pooltemplate` (`template_id`) ON DELETE SET NULL ON UPDATE CASCADE");

	addForeignKey('uo_enrolledteam', 'fk_enrolledteam_series', "FOREIGN KEY (`series`) REFERENCES `uo_series` (`series_id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_enrolledteam', 'fk_enrolledteam_user', "FOREIGN KEY (`userid`) REFERENCES `uo_users` (`userid`) ON DELETE CASCADE ON UPDATE CASCADE");

	addForeignKey('uo_extraemail', 'fk_extraemail_user', "FOREIGN KEY (`userid`) REFERENCES `uo_users` (`userid`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_extraemailrequest', 'fk_extraemailrequest_user', "FOREIGN KEY (`userid`) REFERENCES `uo_users` (`userid`) ON DELETE CASCADE ON UPDATE CASCADE");

	addForeignKey('uo_spirit_score', 'fk_spirit_score_game', "FOREIGN KEY (`game_id`) REFERENCES `uo_game` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_spirit_score', 'fk_spirit_score_team', "FOREIGN KEY (`team_id`) REFERENCES `uo_team` (`team_id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_spirit_score', 'fk_spirit_score_category', "FOREIGN KEY (`category_id`) REFERENCES `uo_spirit_category` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE");

	addForeignKey('uo_defense', 'fk_defense_game', "FOREIGN KEY (`game`) REFERENCES `uo_game` (`game_id`) ON DELETE CASCADE ON UPDATE CASCADE");
	addForeignKey('uo_defense', 'fk_defense_author', "FOREIGN KEY (`author`) REFERENCES `uo_player` (`player_id`) ON DELETE SET NULL ON UPDATE CASCADE");
}

function hasRow($table, $column, $value)
{
	global $mysqlconnectionref;
	$query = "SELECT * FROM $table WHERE $column='" . $value . "'";
	$result = mysqli_query($mysqlconnectionref, $query);
	return mysqli_num_rows($result);
}

function hasTable($table)
{
	global $mysqlconnectionref;
	$query = sprintf("SHOW TABLES FROM `%s`", DB_DATABASE);
	$tables = mysqli_query($mysqlconnectionref, $query);
	while (list($temp) = mysqli_fetch_array($tables)) {
		if ($temp == $table) {
			return TRUE;
		}
	}
	return FALSE;
}
function getPositions($pos)
{
	$startingpos = explode("-", $pos);
	if (count($startingpos) == 2) {
		$temp = array();
		for ($j = (int)$startingpos[0]; $j <= (int)$startingpos[1]; $j++) {
			$temp[] = $j;
		}
		return $temp;
	} else {
		return explode(",", $pos);
	}
}

function renameTable($oldtable, $newtable)
{
	global $mysqlconnectionref;
	$query = "SHOW COLUMNS FROM $newtable";
	$result = mysqli_query($mysqlconnectionref, $query);
	if ($result) return true;
	$query = "RENAME TABLE $oldtable TO $newtable";
	runQuery($query);
	return true;
}

function renameField($table, $oldfield, $newfield)
{
	if (hasColumn($table, $newfield)) {
		return true;
	}
	$query = "SHOW COLUMNS FROM $table WHERE FIELD='" . $oldfield . "'";
	$result = DBQuery($query);
	if ($row = mysqli_fetch_assoc($result)) {
		$query = "ALTER TABLE $table CHANGE $oldfield $newfield " . $row['Type'];
		if ($row['Null'] == "YES") {
			$query .= " NULL ";
		} else {
			$query .= " NOT NULL ";
		}
		runQuery($query);
	}
	return true;
}

function changeToAutoIncrementField($table, $field)
{
	$query = "SHOW COLUMNS FROM $table WHERE FIELD='" . $field . "'";
	$result = DBQuery($query);
	if ($row = mysqli_fetch_assoc($result)) {
		$query = "ALTER TABLE $table CHANGE $field $field " . $row['Type'] . " NOT NULL auto_increment";
		runQuery($query);
	}
	return true;
}

function dropField($table, $field)
{
	if (hasColumn($table, $field)) {
		$query = "ALTER TABLE $table DROP $field";
		$result = DBQuery($query);
		if ($result) return true;
		else return false;
	}
	return true;
}

function copyProfileImages()
{

	//club images
	$results = runQuery("SELECT * FROM uo_club WHERE image IS NOT NULL");
	while ($row = mysqli_fetch_assoc($results)) {
		$image = GetImage($row['image']);
		if ($image) {
			$type = $image['image_type'];
			$data = $image['image'];
			$org = imagecreatefromstring($data);
			$target = "" . UPLOAD_DIR . "";
			if (!is_dir($target)) {
				recur_mkdirs($target, 0775);
			}
			switch ($type) {
				case "image/jpeg":
				case "image/pjpeg":
					$target .= "tmp.jpg";
					imagejpeg($org, $target);
					break;
				case "image/png":
					$target .= "tmp.png";
					imagepng($org, $target);
					break;
				case "image/gif":
					$target .= "tmp.gif";
					imagegif($org, $target);
					break;
			}
			$imgname = time() . $row['club_id'] . ".jpg";
			$basedir = "" . UPLOAD_DIR . "clubs/" . $row['club_id'] . "/";
			if (!is_dir($basedir)) {
				recur_mkdirs($basedir, 0775);
				recur_mkdirs($basedir . "thumbs/", 0775);
			}

			ConvertToJpeg($target, $basedir . $imgname);
			CreateThumb($basedir . $imgname, $basedir . "thumbs/" . $imgname, 160, 120);
			$query = sprintf(
				"UPDATE uo_club SET profile_image='%s' WHERE club_id='%s'",
				DBEscapeString($imgname),
				DBEscapeString($row['club_id'])
			);
			runQuery($query);
			unlink($target);
		}
	}

	//team images
	$results = runQuery("SELECT * FROM uo_team_profile WHERE image IS NOT NULL");
	while ($row = mysqli_fetch_assoc($results)) {
		$image = GetImage($row['image']);
		if ($image) {
			$type = $image['image_type'];
			$data = $image['image'];
			$org = imagecreatefromstring($data);
			$target = "" . UPLOAD_DIR . "";
			if (!is_dir($target)) {
				recur_mkdirs($target, 0775);
			}
			switch ($type) {
				case "image/jpeg":
				case "image/pjpeg":
					$target .= "tmp.jpg";
					imagejpeg($org, $target);
					break;
				case "image/png":
					$target .= "tmp.png";
					imagepng($org, $target);
					break;
				case "image/gif":
					$target .= "tmp.gif";
					imagegif($org, $target);
					break;
			}
			$imgname = time() . $row['team_id'] . ".jpg";
			$basedir = "" . UPLOAD_DIR . "teams/" . $row['team_id'] . "/";
			if (!is_dir($basedir)) {
				recur_mkdirs($basedir, 0775);
				recur_mkdirs($basedir . "thumbs/", 0775);
			}

			ConvertToJpeg($target, $basedir . $imgname);
			CreateThumb($basedir . $imgname, $basedir . "thumbs/" . $imgname, 320, 240);
			$query = sprintf(
				"UPDATE uo_team_profile SET profile_image='%s' WHERE team_id='%s'",
				DBEscapeString($imgname),
				DBEscapeString($row['team_id'])
			);
			runQuery($query);
			unlink($target);
		}
	}

	//player images
	$results = runQuery("SELECT * FROM uo_player_profile WHERE image IS NOT NULL");
	while ($row = mysqli_fetch_assoc($results)) {
		$image = GetImage($row['image']);
		if ($image) {
			$type = $image['image_type'];
			$data = $image['image'];
			$org = imagecreatefromstring($data);
			$target = "" . UPLOAD_DIR . "";
			if (!is_dir($target)) {
				recur_mkdirs($target, 0775);
			}
			switch ($type) {
				case "image/jpeg":
				case "image/pjpeg":
					$target .= "tmp.jpg";
					imagejpeg($org, $target);
					break;
				case "image/png":
					$target .= "tmp.png";
					imagepng($org, $target);
					break;
				case "image/gif":
					$target .= "tmp.gif";
					imagegif($org, $target);
					break;
			}
			$imgname = time() . $row['accreditation_id'] . ".jpg";
			$basedir = "" . UPLOAD_DIR . "players/" . $row['accreditation_id'] . "/";
			if (!is_dir($basedir)) {
				recur_mkdirs($basedir, 0775);
				recur_mkdirs($basedir . "thumbs/", 0775);
			}

			ConvertToJpeg($target, $basedir . $imgname);
			CreateThumb($basedir . $imgname, $basedir . "thumbs/" . $imgname, 120, 160);
			$query = sprintf(
				"UPDATE uo_player_profile SET profile_image='%s' WHERE accreditation_id='%s'",
				DBEscapeString($imgname),
				DBEscapeString($row['accreditation_id'])
			);
			runQuery($query);
			unlink($target);
		}
	}
}
