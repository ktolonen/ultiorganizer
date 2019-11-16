<?php
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/image.functions.php';
include_once $include_prefix.'lib/logging.functions.php';

function upgrade46() {
	runQuery('INSERT INTO uo_setting (name, value) VALUES ("FacebookEnabled", "false")');
	runQuery('INSERT INTO uo_setting (name, value) VALUES ("FacebookAppId", "")');
	runQuery('INSERT INTO uo_setting (name, value) VALUES ("FacebookAppKey", "")');
	runQuery('INSERT INTO uo_setting (name, value) VALUES ("FacebookAppSecret", "")');
}

function upgrade47() {
	addColumn('uo_reservation', 'season', 'varchar(10) default NULL');
		
	$results = runQuery("SELECT DISTINCT pr.id, ser.season
			FROM uo_reservation pr
			LEFT JOIN uo_game pp ON (pp.reservation=pr.id)
			LEFT JOIN uo_pool ps ON (pp.pool=ps.pool_id)
			LEFT JOIN uo_series ser ON (ps.series=ser.series_id)
			LEFT JOIN uo_location pl ON (pr.location=pl.id)");

	while($row = mysqli_fetch_assoc($results)){
		runQuery("UPDATE uo_reservation SET season='".$row['season']."'
			WHERE id='".$row['id']."'");
	}
	
	runQuery('INSERT INTO uo_setting (name, value) VALUES ("GameRSSEnabled", "false")');
}

function upgrade48() {
	runQuery('INSERT INTO uo_setting (name, value) VALUES ("FacebookGameMessage", "Game finished in pool $pool")');
}

function upgrade49() {
	addColumn('uo_season', 'timezone', 'varchar(50) default NULL');
}

function upgrade50() {
	runQuery('INSERT INTO uo_setting (name, value) VALUES ("FacebookUpdatePage", "")');
}

function upgrade51() {
	addColumn('uo_urls', 'ordering', "varchar(2) default ''");
}

function upgrade52() {
	addColumn('uo_pool', 'forfeitscore', 'int(10) DEFAULT NULL');
	addColumn('uo_pool', 'forfeitagainst', 'int(10) DEFAULT NULL');
	addColumn('uo_pooltemplate', 'forfeitscore', 'int(10) DEFAULT NULL');
	addColumn('uo_pooltemplate', 'forfeitagainst', 'int(10) DEFAULT NULL');
}

function upgrade53() {
	if(!hasTable("uo_sms")){
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
function upgrade54() {

	if(hasTable("pelik_jasenet") && !hasTable("uo_license")){
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
	}elseif(!hasTable("uo_license")){
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

function upgrade55() {
	if(!hasColumn('uo_pool', 'follower')){
		addColumn('uo_pool', 'follower', "int(10) DEFAULT NULL");
	}
}

function upgrade56() {
	if(!hasColumn('uo_player_profile', 'email')){
		addColumn('uo_player_profile', 'email', "varchar(100) DEFAULT NULL");
		
		$results = runQuery("SELECT accreditation_id, email FROM uo_player WHERE email IS NOT NULL");
	    while($row = mysqli_fetch_assoc($results)){
	        $query = sprintf("UPDATE uo_player_profile SET email='%s' WHERE accreditation_id='%s'",
			  $row['email'],
			  $row['accreditation_id']);
            runQuery($query);			  
	    }
	    runQuery("alter table uo_player drop column email");
	}
}

function upgrade57() {
	if(!hasTable("uo_specialranking")){
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

function upgrade58() {
	if(!hasColumn('uo_player_profile', 'firstname')){
		addColumn('uo_player_profile', 'firstname', "varchar(40) DEFAULT NULL");
		
		//name from uo_player
		$results = runQuery("SELECT accreditation_id, firstname FROM uo_player WHERE firstname IS NOT NULL");
	    while($row = mysqli_fetch_assoc($results)){
	        $query = sprintf("UPDATE uo_player_profile SET firstname='%s' WHERE accreditation_id='%s'",
			  mysql_real_escape_string(trim($row['firstname'])),
			  $row['accreditation_id']);
            runQuery($query);			  
	    }
	    
	    //if uo_license has name use the one from there.
		$results = runQuery("SELECT accreditation_id, firstname FROM uo_license WHERE firstname IS NOT NULL");
	    while($row = mysqli_fetch_assoc($results)){
	        $query = sprintf("UPDATE uo_player_profile SET firstname='%s' WHERE accreditation_id='%s'",
			  mysql_real_escape_string(trim($row['firstname'])),
			  $row['accreditation_id']);
            runQuery($query);			  
	    }
	}
	if(!hasColumn('uo_player_profile', 'lastname')){
		addColumn('uo_player_profile', 'lastname', "varchar(40) DEFAULT NULL");
		
		//name from uo_player
		$results = runQuery("SELECT accreditation_id, lastname FROM uo_player WHERE lastname IS NOT NULL");
	    while($row = mysqli_fetch_assoc($results)){
	        $query = sprintf("UPDATE uo_player_profile SET lastname='%s' WHERE accreditation_id='%s'",
			  mysql_real_escape_string(trim($row['lastname'])),
			  $row['accreditation_id']);
            runQuery($query);			  
	    }
	    
	    //if uo_license has name use the one from there.
		$results = runQuery("SELECT accreditation_id, lastname FROM uo_license WHERE lastname IS NOT NULL");
	    while($row = mysqli_fetch_assoc($results)){
	        $query = sprintf("UPDATE uo_player_profile SET lastname='%s' WHERE accreditation_id='%s'",
			  mysql_real_escape_string(trim($row['lastname'])),
			  $row['accreditation_id']);
            runQuery($query);			  
	    }
	}
	if(!hasColumn('uo_player_profile', 'num')){
		addColumn('uo_player_profile', 'num', "tinyint(3) DEFAULT NULL");
		
		//num from uo_player
		$results = runQuery("SELECT accreditation_id, num FROM uo_player WHERE num IS NOT NULL");
	    while($row = mysqli_fetch_assoc($results)){
	        $query = sprintf("UPDATE uo_player_profile SET num='%s' WHERE accreditation_id='%s'",
			  trim($row['num']),
			  $row['accreditation_id']);
            runQuery($query);			  
	    }
	}	
	if(!hasColumn('uo_player_profile', 'profile_id')){
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

function upgrade59() {
	if(!hasColumn('uo_reservation', 'timeslots')){
		addColumn('uo_reservation', 'timeslots', "varchar(100) DEFAULT NULL");
	}
    if(!hasColumn('uo_reservation', 'date')){
		addColumn('uo_reservation', 'date', "datetime DEFAULT NULL");
        $results = runQuery("SELECT * FROM uo_reservation WHERE starttime IS NOT NULL");
	    while($row = mysqli_fetch_assoc($results)){
	        $query = sprintf("UPDATE uo_reservation SET date='%s' WHERE id='%s'",
			  ToInternalTimeFormat(ShortDate($row['starttime'])),
			  $row['id']);
            runQuery($query);			  
	    }
	}
}

function upgrade60() {
  
  $dprofiles = runQuery("SELECT * FROM uo_player_profile WHERE accreditation_id!=profile_id");
  while($profile = mysqli_fetch_assoc($dprofiles)){
    runQuery("DELETE FROM uo_player_profile WHERE accreditation_id='".$profile['accreditation_id']."'");
  }
  
  $licenses = runQuery("SELECT * FROM uo_license");
  while($license = mysqli_fetch_assoc($licenses)){
    
    $hasprofile = runQuery("SELECT * FROM uo_player_profile WHERE accreditation_id='".$license['accreditation_id']."'");
    
    if(mysql_num_rows($hasprofile)==0){
        $query = sprintf("INSERT INTO uo_player_profile (profile_id,firstname,lastname,birthdate,accreditation_id) VALUES
				('%s','%s','%s','%s','%s')",
        mysql_real_escape_string($license['accreditation_id']),
        mysql_real_escape_string($license['firstname']),
        mysql_real_escape_string($license['lastname']),
        mysql_real_escape_string($license['birthdate']),
        mysql_real_escape_string($license['accreditation_id']));
      $profileId = DBQueryInsert($query);
    }
  }
  
  $players = runQuery("SELECT * FROM uo_player GROUP BY profile_id");
  while($player = mysqli_fetch_assoc($players)){
    
    $hasprofile = runQuery("SELECT * FROM uo_player_profile WHERE profile_id='".$player['profile_id']."'");
   
    if(mysql_num_rows($hasprofile)==0){
        $query = sprintf("INSERT INTO uo_player_profile (profile_id,firstname,lastname,num) VALUES
				('%s','%s','%s','%s')",
        mysql_real_escape_string($player['profile_id']),
        mysql_real_escape_string($player['firstname']),
        mysql_real_escape_string($player['lastname']),
        mysql_real_escape_string($player['num']));
      $profileId = DBQueryInsert($query);
    }
  }
  
}

function upgrade61() {
  if(!hasColumn('uo_player_profile', 'ffindr_id')){
		addColumn('uo_player_profile', 'ffindr_id', "int(10) DEFAULT NULL");
  }
 if(!hasColumn('uo_team_profile', 'ffindr_id')){
		addColumn('uo_team_profile', 'ffindr_id', "int(10) DEFAULT NULL");
  }
}

function upgrade62() {
  runQuery("ALTER TABLE uo_player_profile MODIFY profile_image VARCHAR(30)");
}

function upgrade63() {
  if(!hasTable("uo_pageload_counter")){
    runQuery("CREATE TABLE uo_pageload_counter(
  		id int(11) NOT NULL auto_increment,
  		PRIMARY KEY(id),
  		page varchar(100) NOT NULL,
  		loads int(11))");
  }
  if(!hasTable("uo_visitor_counter")){
    runQuery("CREATE TABLE uo_visitor_counter(
  		id int(11) NOT NULL auto_increment,
  		ip varchar(15) NOT NULL default '',
  		visits int(11),
  		PRIMARY KEY (id))");
  }
}

function upgrade64() {
   if(!hasRow("uo_setting","name","PageTitle")){
	runQuery('INSERT INTO uo_setting (name, value) VALUES ("PageTitle", "Ultiorganizer - ")');
   }
}

function upgrade65() {
  if(!hasRow("uo_setting","name","DefaultTimezone")){
	runQuery('INSERT INTO uo_setting (name, value) VALUES ("DefaultTimezone", "Europe/Helsinki")');
  }
  if(!hasRow("uo_setting","name","DefaultLocale")){
	runQuery('INSERT INTO uo_setting (name, value) VALUES ("DefaultLocale", "en_GB.utf8")');
  }
}


function upgrade66(){
if(!hasTable("uo_defense")){
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
	if(!hasColumn('uo_player_stats', 'defenses')){
		addColumn('uo_player_stats', 'defenses', "int(5) DEFAULT 0");
	}
	if(!hasColumn('uo_season_stats', 'defenses_total')){
		addColumn('uo_season_stats', 'defenses_total', "int(5) DEFAULT 0");
	}
	if(!hasColumn('uo_series_stats', 'defenses_total')){
		addColumn('uo_series_stats', 'defenses_total', "int(5) DEFAULT 0");
	}
	if(!hasColumn('uo_team_stats', 'defenses_total')){
		addColumn('uo_team_stats', 'defenses_total', "int(5) DEFAULT 0");
	}
	if(!hasColumn('uo_game', 'homedefenses')){
		addColumn('uo_game', 'homedefenses', "smallint(5) DEFAULT 0");
	}
	if(!hasColumn('uo_game', 'defenses_total')){
		addColumn('uo_game', 'visitordefenses', "smallint(5) DEFAULT 0");
	}
	
}

function upgrade67() {
  if(!hasColumn("uo_series","color")){
	addColumn('uo_series', 'color', "varchar(6) DEFAULT NULL");
  }
  if(!hasColumn("uo_series","pool_template")){
	addColumn('uo_series', 'pool_template', "int(10) DEFAULT NULL");
  }  
  
 if(!hasRow("uo_setting","name","ShowDefenseStats")){
	runQuery('INSERT INTO uo_setting (name, value) VALUES ("ShowDefenseStats", "false")');
   }
}

function upgrade68() {
	if(!hasTable("uo_spirit")){
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

function upgrade69() {
	if (!hasColumn("uo_pool", "drawsallowed")) {
		addColumn("uo_pool", "drawsallowed", "smallint(5) DEFAULT 0");
	}
	if (!hasColumn("uo_pooltemplate", "drawsallowed")) {
		addColumn("uo_pooltemplate", "drawsallowed", "smallint(5) DEFAULT 0");
	}
	if (!hasColumn("uo_game", "hasstarted")) {
		addColumn("uo_game", "hasstarted", "tinyint(1) DEFAULT 0");
		runQuery("UPDATE uo_game SET hasstarted='1' WHERE isongoing>0 OR homescore>0 OR visitorscore>0");
	}
}

function upgrade70() {
  if(!hasTable("uo_movingtime")){
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

function upgrade71() {
  if (!hasTable("uo_location_info")) {
    runQuery(
        "CREATE TABLE `uo_location_info` (
	`location_id` INT(10) NOT NULL,
    `locale` varchar(20) NOT NULL,
    `info` varchar(255) DEFAULT NULL,
	PRIMARY KEY (`location_id`,`locale`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci");
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
                  $row['id'], mysql_real_escape_string($locale), mysql_real_escape_string($value)));
        }
      }
    }
  }
}

function upgrade72() {
  renameField('uo_team_stats', 'loses', 'losses');
}

function upgrade73() {
  addColumn("uo_pool", "playoff_template", "varchar(30) default NULL");
}

function upgrade74() {
  if (!hasTable("uo_comment")) {
    runQuery(
        "CREATE TABLE `uo_comment` (
    `type` tinyint(3) NOT NULL,
    `id` varchar(10) NOT NULL,
    `comment` text NOT NULL,
	PRIMARY KEY (`type`,`id`),
    INDEX `idx_id` (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci");
  }
}

function upgrade75() {
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
        'INSERT INTO uo_spirit_category (`mode`, `index`, `min`, `max`, `text`) VALUES ("1001", 1, 0, 20, "Spirit score")');
    _("Spirit score");
    
    runQuery(
        'INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1002", 0, "WFDF (four categories plus comparison)")');
    _("WFDF (four categories plus comparison)");
    runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1002", 1, "Rules Knowledge and Use")');
    _("Rules Knowledge and Use");
    runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1002", 2, "Fouls and Body Contact")');
    _("Fouls and Body Contact");
    runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1002", 3, "Fair-Mindedness")');
    _("Fair-Mindedness");
    runQuery(
        'INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1002", 4, "Positive Attitude and Self-Control")');
    _("Positive Attitude and Self-Control");
    runQuery(
        'INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1002", 5, "Our Spirit compared to theirs")');
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
        'INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1003", 4, "Positive Attitude and Self-Control")');
    _("Positive Attitude and Self-Control");
    runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1003", 5, "Communication")');
    _("Communication");
    
    runQuery(
        'INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1004", 0, "WFDF (five categories, theirs and ours)")');
    _("WFDF (five categories, theirs and ours)");
    runQuery(
        'INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1004", 1, "Rules Knowledge and Use (theirs)")');
    _("Rules Knowledge and Use (theirs)");
    runQuery(
        'INSERT INTO uo_spirit_category (`mode`, `group`, `index`, `factor`, `text`) VALUES ("1004", 1, 2, 0, "Rules Knowledge and Use (ours)")');
    _("Rules Knowledge and Use (ours)");
    runQuery(
        'INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1004", 3, "Fouls and Body Contact (theirs)")');
    _("Fouls and Body Contact (theirs)");
    runQuery(
        'INSERT INTO uo_spirit_category (`mode`, `group`, `index`, `factor`, `text`) VALUES ("1004", 1, 4, 0, "Fouls and Body Contact (ours)")');
    _("Fouls and Body Contact (ours)");
    runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1004", 5, "Fair-Mindedness (theirs)")');
    _("Fair-Mindedness (theirs)");
    runQuery(
        'INSERT INTO uo_spirit_category (`mode`, `group`, `index`, `factor`, `text`) VALUES ("1004", 1, 6, 0, "Fair-Mindedness (ours)")');
    _("Fair-Mindedness (ours)");
    runQuery(
        'INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1004", 7, "Positive Attitude and Self-Control (theirs)")');
    _("Positive Attitude and Self-Control (theirs)");
    runQuery(
        'INSERT INTO uo_spirit_category (`mode`, `group`, `index`, `factor`, `text`) VALUES ("1004", 1, 8, 0, "Positive Attitude and Self-Control (ours)")');
    _("Positive Attitude and Self-Control (ours)");
    runQuery('INSERT INTO uo_spirit_category (`mode`, `index`, `text`) VALUES ("1004", 9, "Communication (theirs)")');
    _("Communication (theirs)");
    runQuery(
        'INSERT INTO uo_spirit_category (`mode`, `group`, `index`, `factor`, `text`) VALUES ("1004", 1, 10, 0, "Communication (ours)")');
    _("Communication (ours)");
    
    runQuery("CREATE TABLE `uo_spirit_score` (
        `game_id` INT(10) NOT NULL,
        `team_id` INT(10) NOT NULL,
		`category_id` INT(10) NOT NULL,
        `value` INT (3) DEFAULT NULL,
        PRIMARY KEY (`game_id`, `team_id`, `category_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci AUTO_INCREMENT=1000");

    addColumn('uo_season', 'spiritmode', 'INT(10) DEFAULT NULL');
    // set all to 1001
    runQuery("UPDATE uo_season SET `spiritmode` = 1001 WHERE `spiritpoints`=1");
    
    // update WFDF scores
    $categoriesResult = runQuery("SELECT * FROM `uo_spirit_category` WHERE mode=1002");
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
      for ($i=1; $i<=5; ++$i) {
        runQuery(sprintf(
            "INSERT INTO `uo_spirit_score` (`game_id`, `team_id`, `category_id`, `value`)
               VALUES (%d, %d, %d, %d)", 
               $row['game_id'], $row['team_id'], $categories[$i], $row['cat'.$i])
            );
      }
      if ($lastSeason != $row['season_id']) {
        $lastSeason = $row['season_id'];
        runQuery(sprintf(
            "UPDATE uo_season SET `spiritmode` = 1002 WHERE `spiritpoints`=1 AND season_id=%d",
            (int)$lastSeason));
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
             $row['game_id'], $row['hometeam'], $categories[1], $row['homesotg']));
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
    dropField("uo_game", "homesotg");
    dropField("uo_game", "visitorsotg");
    dropField("uo_season", "spiritpoints");
  }
}

function upgrade76() {
  global $locales;
  if (!hasTable("uo_translation")) {
    runQuery("CREATE TABLE `uo_translation` (
      `translation_key` varchar(50) NOT NULL,
      `locale` varchar(15) NOT NULL,
      `translation` varchar(100) NOT NULL,
      PRIMARY KEY (`translation_key`, `locale`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci");
    
    foreach ($locales as $localestr => $localename) {
      $loc = mysql_real_escape_string(str_replace(".", "_", $localestr));
      runQuery(sprintf("INSERT INTO uo_translation 
          (SELECT translation_key, '%s' AS locale, `%s` AS translation 
           FROM uo_dbtranslations
           WHERE `%s` IS NOT NULL)",
          $loc, $loc, $loc));
    }
    runQuery("DROP TABLE uo_dbtranslations");
  }
}

function runQuery($query) {
	global $mysqlconnectionref;
	$result = mysqli_query($mysqlconnectionref, $query);
	if (!$result) { die('Invalid query: ("'.$query.'")'."<br/>\n" . mysql_error()); }
	return $result;
}

function addColumn($table, $column, $type) {
	if (hasColumn($table, $column)) {
		runQuery("alter table ".$table." drop column ".$column);
	}
	runQuery("alter table ".$table." add ".$column." ".$type);
		
}
function hasColumn($table, $column) {
	global $mysqlconnectionref;
	$query = "SELECT max(".$column.") FROM ".$table;
	$result = mysqli_query($mysqlconnectionref, $query);
	if (!$result) { return false; } else return true;
}

function hasRow($table, $column, $value) {
	global $mysqlconnectionref;
	$query = "SELECT * FROM $table WHERE $column='".$value."'";
	$result = mysqli_query($mysqlconnectionref, $query);
	return mysqli_num_rows($result);
}

function hasTable($table) { 
	global $mysqlconnectionref;
	$query = "SHOW TABLES FROM ".DB_DATABASE;
	$tables = mysqli_query($mysqlconnectionref, $query); 
	while (list ($temp) = mysqli_fetch_array ($tables)) {
		if ($temp == $table) {
			return TRUE;
		}
	}
	return FALSE;
}
function getPositions($pos) {
	$startingpos=explode("-", $pos);
	if (count($startingpos) == 2) {
		$temp = array();
		for ($j=(int)$startingpos[0]; $j<=(int)$startingpos[1]; $j++) {
			$temp[] = $j;
		}
		return $temp;
	} else {
		return explode(",", $pos);
	}
}

function renameTable($oldtable, $newtable) {
	$query = "SHOW COLUMNS FROM $newtable";
	$result = mysql_query($query);
	if ($result) return true;
	$query = "RENAME TABLE $oldtable TO $newtable";
	runQuery($query);
	return true;
}

function renameField($table, $oldfield, $newfield) {
	if (hasColumn($table, $newfield)) {
		return true;
	}
	$query = "SHOW COLUMNS FROM $table WHERE FIELD='".$oldfield."'";
	$result = DBQuery($query);
	if ($row = mysqli_fetch_assoc($result)) {
		$query = "ALTER TABLE $table CHANGE $oldfield $newfield ".$row['Type'];
		if ($row['Null'] == "YES") {
			$query .= " NULL ";		
		} else {
			$query .= " NOT NULL ";
		}
		runQuery($query);
	}
	return true;
}

function changeToAutoIncrementField($table, $field) {
	$query = "SHOW COLUMNS FROM $table WHERE FIELD='".$field."'";
	$result = DBQuery($query);
	if ($row = mysqli_fetch_assoc($result)) {
		$query = "ALTER TABLE $table CHANGE $field $field ".$row['Type']." NOT NULL auto_increment";
		runQuery($query);
	}
	return true;	
}

function dropField($table, $field) {
	if (hasColumn($table, $field)) {
		$query = "ALTER TABLE $table DROP $field";
		$result = DBQuery($query);
		if ($result) return true;
		else return false;
	}
	return true;
}

function copyProfileImages() {
	
	//club images
	$results = runQuery("SELECT * FROM uo_club WHERE image IS NOT NULL");
	while($row = mysqli_fetch_assoc($results)){
		$image = GetImage($row['image']);
		if($image){
			$type = $image['image_type'];
			$data = $image['image'];
			$org = imagecreatefromstring($data);
			$target = "".UPLOAD_DIR."";
			if(!is_dir($target)){
				recur_mkdirs($target,0775);
			}
			 switch ($type){
				case "image/jpeg":
				case "image/pjpeg":
				$target .= "tmp.jpg";
				imagejpeg($org,$target);
				break;
				case "image/png":
				$target .= "tmp.png";
				imagepng($org,$target);
				break;
				case "image/gif":
				$target .= "tmp.gif";
				imagegif($org,$target);
				break;
			}
			$imgname = time().$row['club_id'].".jpg";
			$basedir = "".UPLOAD_DIR."clubs/".$row['club_id']."/";
			if(!is_dir($basedir)){
				recur_mkdirs($basedir,0775);
				recur_mkdirs($basedir."thumbs/",0775);
			}
		
			ConvertToJpeg($target, $basedir.$imgname);
			CreateThumb($basedir.$imgname, $basedir."thumbs/".$imgname, 160, 120);
			$query = sprintf("UPDATE uo_club SET profile_image='%s' WHERE club_id='%s'",
					mysql_real_escape_string($imgname),
					mysql_real_escape_string($row['club_id']));
			runQuery($query);	
			unlink($target);
		}
	}
	
	//team images
	$results = runQuery("SELECT * FROM uo_team_profile WHERE image IS NOT NULL");
	while($row = mysqli_fetch_assoc($results)){
		$image = GetImage($row['image']);
		if($image){
			$type = $image['image_type'];
			$data = $image['image'];
			$org = imagecreatefromstring($data);
			$target = "".UPLOAD_DIR."";
			if(!is_dir($target)){
				recur_mkdirs($target,0775);
			}
			 switch ($type){
				case "image/jpeg":
				case "image/pjpeg":
				$target .= "tmp.jpg";
				imagejpeg($org,$target);
				break;
				case "image/png":
				$target .= "tmp.png";
				imagepng($org,$target);
				break;
				case "image/gif":
				$target .= "tmp.gif";
				imagegif($org,$target);
				break;
			}
			$imgname = time().$row['team_id'].".jpg";
			$basedir = "".UPLOAD_DIR."teams/".$row['team_id']."/";
			if(!is_dir($basedir)){
				recur_mkdirs($basedir,0775);
				recur_mkdirs($basedir."thumbs/",0775);
			}
		
			ConvertToJpeg($target, $basedir.$imgname);
			CreateThumb($basedir.$imgname, $basedir."thumbs/".$imgname, 320, 240);
			$query = sprintf("UPDATE uo_team_profile SET profile_image='%s' WHERE team_id='%s'",
					mysql_real_escape_string($imgname),
					mysql_real_escape_string($row['team_id']));
			runQuery($query);	
			unlink($target);
		}
	}
	
	//player images
	$results = runQuery("SELECT * FROM uo_player_profile WHERE image IS NOT NULL");
	while($row = mysqli_fetch_assoc($results)){
		$image = GetImage($row['image']);
		if($image){
			$type = $image['image_type'];
			$data = $image['image'];
			$org = imagecreatefromstring($data);
			$target = "".UPLOAD_DIR."";
			if(!is_dir($target)){
				recur_mkdirs($target,0775);
			}
			 switch ($type){
				case "image/jpeg":
				case "image/pjpeg":
				$target .= "tmp.jpg";
				imagejpeg($org,$target);
				break;
				case "image/png":
				$target .= "tmp.png";
				imagepng($org,$target);
				break;
				case "image/gif":
				$target .= "tmp.gif";
				imagegif($org,$target);
				break;
			}
			$imgname = time().$row['accreditation_id'].".jpg";
			$basedir = "".UPLOAD_DIR."players/".$row['accreditation_id']."/";
			if(!is_dir($basedir)){
				recur_mkdirs($basedir,0775);
				recur_mkdirs($basedir."thumbs/",0775);
			}
		
			ConvertToJpeg($target, $basedir.$imgname);
			CreateThumb($basedir.$imgname, $basedir."thumbs/".$imgname, 120, 160);
			$query = sprintf("UPDATE uo_player_profile SET profile_image='%s' WHERE accreditation_id='%s'",
					mysql_real_escape_string($imgname),
					mysql_real_escape_string($row['accreditation_id']));
			runQuery($query);	
			unlink($target);
		}
	}
}
?>
