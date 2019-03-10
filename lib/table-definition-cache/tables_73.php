<?php

$tables['uo_accreditationlog'] = array( 'player' => 'int', 'team' => 'int', 'userid' => 'string', 'source' => 'string', 'value' => 'int', 'time' => 'datetime', 'game' => 'int' );
$tables['uo_club'] = array( 'club_id' => 'int', 'name' => 'string', 'contacts' => 'blob', 'city' => 'string', 'country' => 'int', 'story' => 'blob', 'achievements' => 'blob', 'image' => 'int', 'valid' => 'int', 'profile_image' => 'string', 'founded' => 'int' );
$tables['uo_country'] = array( 'country_id' => 'int', 'name' => 'string', 'abbreviation' => 'string', 'flagfile' => 'string', 'valid' => 'int' );
$tables['uo_database'] = array( 'version' => 'int', 'updated' => 'datetime' );
$tables['uo_dbtranslations'] = array( 'translation_key' => 'string', 'en_gb_utf8' => 'string', 'de_de_utf8' => 'string' );
$tables['uo_defense'] = array( 'game' => 'int', 'num' => 'int', 'author' => 'int', 'time' => 'int', 'iscallahan' => 'int', 'iscaught' => 'int', 'ishomedefense' => 'int' );
$tables['uo_enrolledteam'] = array( 'id' => 'int', 'name' => 'string', 'clubname' => 'string', 'series' => 'int', 'userid' => 'string', 'status' => 'int', 'enroll_time' => 'datetime', 'countryname' => 'string' );
$tables['uo_event_log'] = array( 'event_id' => 'int', 'time' => 'timestamp', 'ip' => 'string', 'user_id' => 'string', 'category' => 'string', 'type' => 'string', 'id1' => 'string', 'id2' => 'string', 'source' => 'string', 'description' => 'string' );
$tables['uo_extraemail'] = array( 'userid' => 'string', 'email' => 'string' );
$tables['uo_extraemailrequest'] = array( 'userid' => 'string', 'email' => 'string', 'token' => 'string' );
$tables['uo_game'] = array( 'game_id' => 'int', 'hometeam' => 'int', 'visitorteam' => 'int', 'homescore' => 'int', 'visitorscore' => 'int', 'reservation' => 'int', 'time' => 'datetime', 'pool' => 'int', 'valid' => 'int', 'halftime' => 'int', 'official' => 'string', 'respteam' => 'int', 'resppers' => 'int', 'homesotg' => 'int', 'visitorsotg' => 'int', 'isongoing' => 'int', 'scheduling_name_home' => 'int', 'scheduling_name_visitor' => 'int', 'name' => 'int', 'timeslot' => 'int', 'homedefenses' => 'int', 'visitordefenses' => 'int', 'hasstarted' => 'int' );
$tables['uo_game_pool'] = array( 'game' => 'int', 'pool' => 'int', 'timetable' => 'int' );
$tables['uo_gameevent'] = array( 'game' => 'int', 'num' => 'int', 'time' => 'int', 'type' => 'string', 'ishome' => 'int', 'info' => 'string' );
$tables['uo_goal'] = array( 'game' => 'int', 'num' => 'int', 'assist' => 'int', 'scorer' => 'int', 'time' => 'int', 'homescore' => 'int', 'visitorscore' => 'int', 'ishomegoal' => 'int', 'iscallahan' => 'int' );
$tables['uo_image'] = array( 'image_id' => 'int', 'image_type' => 'string', 'image_height' => 'int', 'image_width' => 'int', 'image_size' => 'int', 'thumb_height' => 'int', 'thumb_width' => 'int', 'thumb' => 'blob', 'image' => 'blob' );
$tables['uo_keys'] = array( 'key_id' => 'int', 'type' => 'string', 'purpose' => 'string', 'id' => 'string', 'keystring' => 'string', 'secrets' => 'string', 'url' => 'string' );
$tables['uo_license'] = array( 'lastname' => 'string', 'firstname' => 'string', 'membership' => 'int', 'birthdate' => 'datetime', 'accreditation_id' => 'string', 'ultimate' => 'int', 'women' => 'int', 'junior' => 'int', 'license' => 'int', 'external_id' => 'int', 'external_type' => 'int', 'external_validity' => 'int' );
$tables['uo_location'] = array( 'id' => 'int', 'name' => 'string', 'fields' => 'int', 'indoor' => 'int', 'address' => 'string', 'info_de_de_utf8' => 'string', 'info_en_gb_utf8' => 'string', 'lat' => 'real', 'lng' => 'real' );
$tables['uo_location_info'] = array( 'location_id' => 'int', 'locale' => 'string', 'info' => 'string' );
$tables['uo_moveteams'] = array( 'frompool' => 'int', 'topool' => 'int', 'fromplacing' => 'int', 'torank' => 'int', 'ismoved' => 'int', 'scheduling_id' => 'int' );
$tables['uo_movingtime'] = array( 'season' => 'string', 'fromlocation' => 'int', 'fromfield' => 'string', 'tolocation' => 'int', 'tofield' => 'string', 'time' => 'int' );
$tables['uo_pageload_counter'] = array( 'id' => 'int', 'page' => 'string', 'loads' => 'int' );
$tables['uo_played'] = array( 'player' => 'int', 'game' => 'int', 'num' => 'int', 'accredited' => 'int', 'acknowledged' => 'int', 'captain' => 'int' );
$tables['uo_player'] = array( 'player_id' => 'int', 'firstname' => 'string', 'lastname' => 'string', 'team' => 'int', 'num' => 'int', 'accreditation_id' => 'string', 'accredited' => 'int', 'profile_id' => 'int' );
$tables['uo_player_profile'] = array( 'profile_id' => 'int', 'email' => 'string', 'firstname' => 'string', 'lastname' => 'string', 'num' => 'int', 'nickname' => 'string', 'birthdate' => 'datetime', 'birthplace' => 'string', 'nationality' => 'string', 'throwing_hand' => 'string', 'height' => 'string', 'story' => 'blob', 'achievements' => 'blob', 'image' => 'int', 'profile_image' => 'string', 'weight' => 'string', 'position' => 'string', 'gender' => 'string', 'info' => 'blob', 'national_id' => 'string', 'accreditation_id' => 'string', 'public' => 'string', 'ffindr_id' => 'int' );
$tables['uo_player_stats'] = array( 'player_id' => 'int', 'profile_id' => 'int', 'team' => 'int', 'season' => 'string', 'series' => 'int', 'games' => 'int', 'wins' => 'int', 'goals' => 'int', 'passes' => 'int', 'callahans' => 'int', 'breaks' => 'int', 'offence_turns' => 'int', 'defence_turns' => 'int', 'offence_time' => 'int', 'defence_time' => 'int', 'defenses' => 'int' );
$tables['uo_pool'] = array( 'pool_id' => 'int', 'name' => 'string', 'ordering' => 'string', 'visible' => 'int', 'continuingpool' => 'int', 'placementpool' => 'int', 'teams' => 'int', 'mvgames' => 'int', 'timeoutlen' => 'int', 'halftime' => 'int', 'winningscore' => 'int', 'timecap' => 'int', 'scorecap' => 'int', 'played' => 'int', 'addscore' => 'int', 'halftimescore' => 'int', 'timeouts' => 'int', 'timeoutsper' => 'string', 'timeoutsovertime' => 'int', 'timeoutstimecap' => 'string', 'betweenpointslen' => 'int', 'series' => 'int', 'type' => 'int', 'timeslot' => 'int', 'color' => 'string', 'forfeitscore' => 'int', 'forfeitagainst' => 'int', 'follower' => 'int', 'drawsallowed' => 'int', 'playoff_template' => 'string' );
$tables['uo_pooltemplate'] = array( 'template_id' => 'int', 'name' => 'string', 'ordering' => 'string', 'continuingpool' => 'int', 'teams' => 'int', 'mvgames' => 'int', 'timeoutlen' => 'int', 'halftime' => 'int', 'winningscore' => 'int', 'timecap' => 'int', 'scorecap' => 'int', 'addscore' => 'int', 'halftimescore' => 'int', 'timeouts' => 'int', 'timeoutsper' => 'string', 'timeoutsovertime' => 'int', 'timeoutstimecap' => 'string', 'betweenpointslen' => 'int', 'type' => 'int', 'timeslot' => 'int', 'forfeitscore' => 'int', 'forfeitagainst' => 'int', 'drawsallowed' => 'int' );
$tables['uo_registerrequest'] = array( 'userid' => 'string', 'password' => 'string', 'name' => 'string', 'email' => 'string', 'token' => 'string', 'last_login' => 'timestamp' );
$tables['uo_reservation'] = array( 'id' => 'int', 'location' => 'int', 'fieldname' => 'string', 'reservationgroup' => 'string', 'starttime' => 'datetime', 'endtime' => 'datetime', 'season' => 'string', 'timeslots' => 'string', 'date' => 'datetime' );
$tables['uo_scheduling_name'] = array( 'scheduling_id' => 'int', 'name' => 'string' );
$tables['uo_season'] = array( 'season_id' => 'string', 'name' => 'string', 'starttime' => 'datetime', 'endtime' => 'datetime', 'iscurrent' => 'int', 'enrollopen' => 'int', 'enroll_deadline' => 'datetime', 'type' => 'string', 'istournament' => 'int', 'isinternational' => 'int', 'isnationalteams' => 'int', 'organizer' => 'string', 'category' => 'string', 'spiritpoints' => 'int', 'showspiritpoints' => 'int', 'timezone' => 'string' );
$tables['uo_season_stats'] = array( 'season' => 'string', 'teams' => 'int', 'games' => 'int', 'players' => 'int', 'goals_total' => 'int', 'home_wins' => 'int', 'defenses_total' => 'int' );
$tables['uo_series'] = array( 'series_id' => 'int', 'name' => 'string', 'ordering' => 'string', 'season' => 'string', 'valid' => 'int', 'type' => 'string', 'color' => 'string', 'pool_template' => 'int' );
$tables['uo_series_stats'] = array( 'series_id' => 'int', 'season' => 'string', 'teams' => 'int', 'games' => 'int', 'players' => 'int', 'goals_total' => 'int', 'home_wins' => 'int', 'defenses_total' => 'int' );
$tables['uo_setting'] = array( 'name' => 'string', 'value' => 'string', 'setting_id' => 'int' );
$tables['uo_sms'] = array( 'sms_id' => 'int', 'to1' => 'int', 'to2' => 'int', 'to3' => 'int', 'to4' => 'int', 'to5' => 'int', 'msg' => 'string', 'created' => 'timestamp', 'click_id' => 'int', 'sent' => 'datetime', 'delivered' => 'datetime' );
$tables['uo_specialranking'] = array( 'frompool' => 'int', 'fromplacing' => 'int', 'torank' => 'int', 'scheduling_id' => 'int' );
$tables['uo_spirit'] = array( 'game_id' => 'int', 'team_id' => 'int', 'cat1' => 'int', 'cat2' => 'int', 'cat3' => 'int', 'cat4' => 'int', 'cat5' => 'int' );
$tables['uo_team'] = array( 'team_id' => 'int', 'name' => 'string', 'pool' => 'int', 'club' => 'int', 'rank' => 'int', 'activerank' => 'int', 'valid' => 'int', 'series' => 'int', 'country' => 'int', 'abbreviation' => 'string' );
$tables['uo_team_pool'] = array( 'team' => 'int', 'pool' => 'int', 'rank' => 'int', 'activerank' => 'int' );
$tables['uo_team_profile'] = array( 'team_id' => 'int', 'coach' => 'string', 'story' => 'blob', 'achievements' => 'blob', 'image' => 'int', 'profile_image' => 'string', 'captain' => 'string', 'ffindr_id' => 'int' );
$tables['uo_team_stats'] = array( 'team_id' => 'int', 'season' => 'string', 'series' => 'int', 'goals_made' => 'int', 'goals_against' => 'int', 'standing' => 'int', 'wins' => 'int', 'losses' => 'int', 'defenses_total' => 'int' );
$tables['uo_timeout'] = array( 'timeout_id' => 'int', 'game' => 'int', 'num' => 'int', 'time' => 'int', 'ishome' => 'int' );
$tables['uo_urls'] = array( 'url_id' => 'int', 'owner' => 'string', 'owner_id' => 'string', 'type' => 'string', 'name' => 'string', 'url' => 'string', 'ordering' => 'string', 'ismedialink' => 'int', 'mediaowner' => 'string', 'publisher_id' => 'int' );
$tables['uo_userproperties'] = array( 'prop_id' => 'int', 'userid' => 'string', 'name' => 'string', 'value' => 'string' );
$tables['uo_users'] = array( 'id' => 'int', 'userid' => 'string', 'password' => 'string', 'name' => 'string', 'email' => 'string', 'last_login' => 'datetime' );
$tables['uo_victorypoints'] = array( 'pointdiff' => 'int', 'victorypoints' => 'int' );
$tables['uo_visitor_counter'] = array( 'id' => 'int', 'ip' => 'string', 'visits' => 'int' );


?>
