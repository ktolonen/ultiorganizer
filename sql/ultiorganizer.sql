SET NAMES 'utf8';

CREATE TABLE `uo_accreditationlog` (
  `player` int(10) DEFAULT NULL,
  `team` int(10) DEFAULT NULL,
  `userid` varchar(50) DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  `value` tinyint(1) DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `game` int(10) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_club` (
  `club_id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT NULL,
  `contacts` text,
  `city` varchar(100) DEFAULT NULL,
  `country` int(10) DEFAULT NULL,
  `story` text,
  `achievements` text,
  `image` int(10) DEFAULT NULL,
  `valid` tinyint(4) NOT NULL DEFAULT '1',
  `profile_image` varchar(20) DEFAULT NULL,
  `founded` int(4) DEFAULT NULL,
  PRIMARY KEY (`club_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;


CREATE TABLE `uo_country` (
  `country_id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `abbreviation` char(3) DEFAULT NULL,
  `flagfile` varchar(50) DEFAULT NULL,
  `valid` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`country_id`)
) ENGINE=MyISAM AUTO_INCREMENT=1205 DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

INSERT INTO uo_country VALUES(1000,"Afghanistan","AFG","Afghanistan.png",1);
INSERT INTO uo_country VALUES(1001,"Netherlands Antilles","AHO","Netherlands_Antilles.png",1);
INSERT INTO uo_country VALUES(1002,"Albania","ALB","Albania.png",1);
INSERT INTO uo_country VALUES(1003,"Algeria","ALG","Algeria.png",1);
INSERT INTO uo_country VALUES(1004,"Andorra","AND","Andorra.png",1);
INSERT INTO uo_country VALUES(1005,"Angola","ANG","Angola.png",1);
INSERT INTO uo_country VALUES(1006,"Antigua and Barbuda","ANT","Antigua_and_Barbuda.png",1);
INSERT INTO uo_country VALUES(1007,"Argentina","ARG","Argentina.png",1);
INSERT INTO uo_country VALUES(1008,"Armenia","ARM","Armenia.png",1);
INSERT INTO uo_country VALUES(1009,"Aruba","ARU","Aruba.png",1);
INSERT INTO uo_country VALUES(1010,"American Samoa","ASA","American_Samoa.png",1);
INSERT INTO uo_country VALUES(1011,"Australia","AUS","Australia.png",1);
INSERT INTO uo_country VALUES(1012,"Austria","AUT","Austria.png",1);
INSERT INTO uo_country VALUES(1013,"Azerbaijan","AZE","Azerbaijan.png",1);
INSERT INTO uo_country VALUES(1014,"Bahamas","BAH","Bahamas.png",1);
INSERT INTO uo_country VALUES(1015,"Bangladesh","BAN","Bangladesh.png",1);
INSERT INTO uo_country VALUES(1016,"Barbados","BAR","Barbados.png",1);
INSERT INTO uo_country VALUES(1017,"Burundi","BDI","Burundi.png",1);
INSERT INTO uo_country VALUES(1018,"Belgium","BEL","Belgium.png",1);
INSERT INTO uo_country VALUES(1019,"Benin","BEN","Benin.png",1);
INSERT INTO uo_country VALUES(1020,"Bermuda","BER","Bermuda.png",1);
INSERT INTO uo_country VALUES(1021,"Bhutan","BHU","Bhutan.png",1);
INSERT INTO uo_country VALUES(1022,"Bosnia and Herzegovina","BIH",NULL,1);
INSERT INTO uo_country VALUES(1023,"Belize","BIZ","Belize.png",1);
INSERT INTO uo_country VALUES(1024,"Belarus","BLR","Belarus.png",1);
INSERT INTO uo_country VALUES(1025,"Bolivia","BOL","Bolivia.png",1);
INSERT INTO uo_country VALUES(1026,"Botswana","BOT","Botswana.png",1);
INSERT INTO uo_country VALUES(1027,"Brazil","BRA","Brazil.png",1);
INSERT INTO uo_country VALUES(1028,"Bahrain","BRN","Bahrain.png",1);
INSERT INTO uo_country VALUES(1029,"Brunei","BRU","Brunei.png",1);
INSERT INTO uo_country VALUES(1030,"Bulgaria","BUL","Bulgaria.png",1);
INSERT INTO uo_country VALUES(1031,"Burkina Faso","BUR","Burkina_Faso.png",1);
INSERT INTO uo_country VALUES(1032,"Central African Republic","CAF","Central_African_Republic.png",1);
INSERT INTO uo_country VALUES(1033,"Cambodia","CAM","Cambodia.png",1);
INSERT INTO uo_country VALUES(1034,"Canada","CAN","Canada.png",1);
INSERT INTO uo_country VALUES(1035,"Cayman Islands","CAY","Cayman_Islands.png",1);
INSERT INTO uo_country VALUES(1036,"Congo","CGO",NULL,1);
INSERT INTO uo_country VALUES(1037,"Chad","CHA","Chad.png",1);
INSERT INTO uo_country VALUES(1038,"Chile","CHI","Chile.png",1);
INSERT INTO uo_country VALUES(1039,"China","CHN","China.png",1);
INSERT INTO uo_country VALUES(1040,"Cóte d\'Ivoire","CIV",NULL,1);
INSERT INTO uo_country VALUES(1041,"Cameroon","CMR","Cameroon.png",1);
INSERT INTO uo_country VALUES(1042,"DR Congo","COD",NULL,1);
INSERT INTO uo_country VALUES(1043,"Cook Islands","COK","Cook_Islands.png",1);
INSERT INTO uo_country VALUES(1044,"Colombia","COL","Colombia.png",1);
INSERT INTO uo_country VALUES(1045,"Comoros","COM","Comoros.png",1);
INSERT INTO uo_country VALUES(1046,"Cape Verde","CPV","Cape_Verde.png",1);
INSERT INTO uo_country VALUES(1047,"Costa Rica","CRC","Costa_Rica.png",1);
INSERT INTO uo_country VALUES(1048,"Croatia","CRO","Croatia.png",1);
INSERT INTO uo_country VALUES(1049,"Cuba","CUB","Cuba.png",1);
INSERT INTO uo_country VALUES(1050,"Cyprus","CYP","Cyprus.png",1);
INSERT INTO uo_country VALUES(1051,"Czech Republic","CZE","Czech_Republic.png",1);
INSERT INTO uo_country VALUES(1052,"Denmark","DEN","Denmark.png",1);
INSERT INTO uo_country VALUES(1053,"Djibouti","DJI","Djibouti.png",1);
INSERT INTO uo_country VALUES(1054,"Dominica","DMA","Dominica.png",1);
INSERT INTO uo_country VALUES(1055,"Dominican Republic","DOM","Dominican_Republic.png",1);
INSERT INTO uo_country VALUES(1056,"Ecuador","ECU","Ecuador.png",1);
INSERT INTO uo_country VALUES(1057,"Egypt","EGY","Egypt.png",1);
INSERT INTO uo_country VALUES(1058,"Eritrea","ERI","Eritrea.png",1);
INSERT INTO uo_country VALUES(1059,"El Salvador","ESA","El_Salvador.png",1);
INSERT INTO uo_country VALUES(1060,"Spain","ESP","Spain.png",1);
INSERT INTO uo_country VALUES(1061,"Estonia","EST","Estonia.png",1);
INSERT INTO uo_country VALUES(1062,"Ethiopia","ETH","Ethiopia.png",1);
INSERT INTO uo_country VALUES(1063,"Fiji","FIJ","Fiji.png",1);
INSERT INTO uo_country VALUES(1064,"Finland","FIN","Finland.png",1);
INSERT INTO uo_country VALUES(1065,"France","FRA","France.png",1);
INSERT INTO uo_country VALUES(1066,"Micronesia","FSM","Micronesia.png",1);
INSERT INTO uo_country VALUES(1067,"Gabon","GAB","Gabon.png",1);
INSERT INTO uo_country VALUES(1068,"Gambia","GAM","Gambia.png",1);
INSERT INTO uo_country VALUES(1069,"Great Britain","GBR","United_Kingdom.png",1);
INSERT INTO uo_country VALUES(1070,"Guinea-Bissau","GBS","Guinea_Bissau.png",1);
INSERT INTO uo_country VALUES(1071,"Georgia","GEO","Georgia.png",1);
INSERT INTO uo_country VALUES(1072,"Equatorial Guinea","GEQ","Equatorial_Guinea.png",1);
INSERT INTO uo_country VALUES(1073,"Germany","GER","Germany.png",1);
INSERT INTO uo_country VALUES(1074,"Ghana","GHA","Ghana.png",1);
INSERT INTO uo_country VALUES(1075,"Greece","GRE","Greece.png",1);
INSERT INTO uo_country VALUES(1076,"Grenada","GRN","Grenada.png",1);
INSERT INTO uo_country VALUES(1077,"Guatemala","GUA","Guatemala.png",1);
INSERT INTO uo_country VALUES(1078,"Guinea","GUI","Guinea.png",1);
INSERT INTO uo_country VALUES(1079,"Guam","GUM","Guam.png",1);
INSERT INTO uo_country VALUES(1080,"Guyana","GUY","Guyana.png",1);
INSERT INTO uo_country VALUES(1081,"Haiti","HAI","Haiti.png",1);
INSERT INTO uo_country VALUES(1082,"Hong Kong","HKG","Hong_Kong.png",1);
INSERT INTO uo_country VALUES(1083,"Honduras","HON","Honduras.png",1);
INSERT INTO uo_country VALUES(1084,"Hungary","HUN","Hungary.png",1);
INSERT INTO uo_country VALUES(1085,"Indonesia","INA","Indonesia.png",1);
INSERT INTO uo_country VALUES(1086,"India","IND","India.png",1);
INSERT INTO uo_country VALUES(1087,"Iran","IRI","Iran.png",1);
INSERT INTO uo_country VALUES(1088,"Ireland","IRL","Ireland.png",1);
INSERT INTO uo_country VALUES(1089,"Iraq","IRQ","Iraq.png",1);
INSERT INTO uo_country VALUES(1090,"Iceland","ISL","Iceland.png",1);
INSERT INTO uo_country VALUES(1091,"Israel","ISR","Israel.png",1);
INSERT INTO uo_country VALUES(1092,"Virgin Islands","ISV",NULL,1);
INSERT INTO uo_country VALUES(1093,"Italy","ITA","Italy.png",1);
INSERT INTO uo_country VALUES(1094,"British Virgin Islands","IVB",NULL,1);
INSERT INTO uo_country VALUES(1095,"Jamaica","JAM","Jamaica.png",1);
INSERT INTO uo_country VALUES(1096,"Jordan","JOR","Jordan.png",1);
INSERT INTO uo_country VALUES(1097,"Japan","JPN","Japan.png",1);
INSERT INTO uo_country VALUES(1098,"Kazakhstan","KAZ","Kazakhstan.png",1);
INSERT INTO uo_country VALUES(1099,"Kenya","KEN","Kenya.png",1);
INSERT INTO uo_country VALUES(1100,"Kyrgyzstan","KGZ","Kyrgyzstan.png",1);
INSERT INTO uo_country VALUES(1101,"Kiribati","KIR","Kiribati.png",1);
INSERT INTO uo_country VALUES(1102,"South Korea","KOR","South_Korea.png",1);
INSERT INTO uo_country VALUES(1103,"Saudi Arabia","KSA","Saudi_Arabia.png",1);
INSERT INTO uo_country VALUES(1104,"Kuwait","KUW","Kuwait.png",1);
INSERT INTO uo_country VALUES(1105,"Laos","LAO","Laos.png",1);
INSERT INTO uo_country VALUES(1106,"Latvia","LAT","Latvia.png",1);
INSERT INTO uo_country VALUES(1107,"Libya","LBA","Libya.png",1);
INSERT INTO uo_country VALUES(1108,"Liberia","LBR","Liberia.png",1);
INSERT INTO uo_country VALUES(1109,"Saint Lucia","LCA","Saint_Lucia.png",1);
INSERT INTO uo_country VALUES(1110,"Lesotho","LES","Lesotho.png",1);
INSERT INTO uo_country VALUES(1111,"Lebanon","LIB","Lebanon.png",1);
INSERT INTO uo_country VALUES(1112,"Liechtenstein","LIE","Liechtenstein.png",1);
INSERT INTO uo_country VALUES(1113,"Lithuania","LTU","Lithuania.png",1);
INSERT INTO uo_country VALUES(1114,"Luxembourg","LUX","Luxembourg.png",1);
INSERT INTO uo_country VALUES(1115,"Madagascar","MAD","Madagascar.png",1);
INSERT INTO uo_country VALUES(1116,"Morocco","MAR","Morocco.png",1);
INSERT INTO uo_country VALUES(1117,"Malaysia","MAS","Malaysia.png",1);
INSERT INTO uo_country VALUES(1118,"Malawi","MAW","Malawi.png",1);
INSERT INTO uo_country VALUES(1119,"Moldova","MDA","Moldova.png",1);
INSERT INTO uo_country VALUES(1120,"Maldives","MDV","Maldives.png",1);
INSERT INTO uo_country VALUES(1121,"Mexico","MEX","Mexico.png",1);
INSERT INTO uo_country VALUES(1122,"Mongolia","MGL","Mongolia.png",1);
INSERT INTO uo_country VALUES(1123,"Marshall Islands","MHL","Marshall_Islands.png",1);
INSERT INTO uo_country VALUES(1124,"Macedonia","MKD","Macedonia.png",1);
INSERT INTO uo_country VALUES(1125,"Mali","MLI","Mali.png",1);
INSERT INTO uo_country VALUES(1126,"Malta","MLT","Malta.png",1);
INSERT INTO uo_country VALUES(1127,"Montenegro","MNE",NULL,1);
INSERT INTO uo_country VALUES(1128,"Monaco","MON","Monaco.png",1);
INSERT INTO uo_country VALUES(1129,"Mozambique","MOZ","Mozambique.png",1);
INSERT INTO uo_country VALUES(1130,"Mauritius","MRI","Mauritius.png",1);
INSERT INTO uo_country VALUES(1131,"Mauritania","MTN","Mauritania.png",1);
INSERT INTO uo_country VALUES(1132,"Myanmar","MYA","Myanmar.png",1);
INSERT INTO uo_country VALUES(1133,"Namibia","NAM","Namibia.png",1);
INSERT INTO uo_country VALUES(1134,"Nicaragua","NCA","Nicaragua.png",1);
INSERT INTO uo_country VALUES(1135,"Netherlands","NED","Netherlands.png",1);
INSERT INTO uo_country VALUES(1136,"Nepal","NEP","Nepal.png",1);
INSERT INTO uo_country VALUES(1137,"Nigeria","NGR","Nigeria.png",1);
INSERT INTO uo_country VALUES(1138,"Niger","NIG","Niger.png",1);
INSERT INTO uo_country VALUES(1139,"Norway","NOR","Norway.png",1);
INSERT INTO uo_country VALUES(1140,"Nauru","NRU","Nauru.png",1);
INSERT INTO uo_country VALUES(1141,"New Zealand","NZL","New_Zealand.png",1);
INSERT INTO uo_country VALUES(1142,"Oman","OMA","Oman.png",1);
INSERT INTO uo_country VALUES(1143,"Pakistan","PAK","Pakistan.png",1);
INSERT INTO uo_country VALUES(1144,"Panama","PAN","Panama.png",1);
INSERT INTO uo_country VALUES(1145,"Paraguay","PAR","Paraguay.png",1);
INSERT INTO uo_country VALUES(1146,"Peru","PER","Peru.png",1);
INSERT INTO uo_country VALUES(1147,"Philippines","PHI","Philippines.png",1);
INSERT INTO uo_country VALUES(1148,"Palestine","PLE",NULL,1);
INSERT INTO uo_country VALUES(1149,"Palau","PLW","Palau.png",1);
INSERT INTO uo_country VALUES(1150,"Papua New Guinea","PNG","Papua_New_Guinea.png",1);
INSERT INTO uo_country VALUES(1151,"Poland","POL","Poland.png",1);
INSERT INTO uo_country VALUES(1152,"Portugal","POR","Portugal.png",1);
INSERT INTO uo_country VALUES(1153,"North Korea","PRK","North_Korea.png",1);
INSERT INTO uo_country VALUES(1154,"Puerto Rico","PUR","Puerto_Rico.png",1);
INSERT INTO uo_country VALUES(1155,"Qatar","QAT","Qatar.png",1);
INSERT INTO uo_country VALUES(1156,"Romania","ROU","Romania.png",1);
INSERT INTO uo_country VALUES(1157,"South Africa","RSA","South_Africa.png",1);
INSERT INTO uo_country VALUES(1158,"Russia","RUS","Russian_Federation.png",1);
INSERT INTO uo_country VALUES(1159,"Rwanda","RWA","Rwanda.png",1);
INSERT INTO uo_country VALUES(1160,"Samoa","SAM","Samoa.png",1);
INSERT INTO uo_country VALUES(1161,"Senegal","SEN","Senegal.png",1);
INSERT INTO uo_country VALUES(1162,"Seychelles","SEY",NULL,1);
INSERT INTO uo_country VALUES(1163,"Singapore","SIN","Singapore.png",1);
INSERT INTO uo_country VALUES(1164,"Saint Kitts and Nevis","SKN","Saint_Kitts_and_Nevis.png",1);
INSERT INTO uo_country VALUES(1165,"Sierra Leone","SLE","Sierra_Leone.png",1);
INSERT INTO uo_country VALUES(1166,"Slovenia","SLO","Slovenia.png",1);
INSERT INTO uo_country VALUES(1167,"San Marino","SMR","San_Marino.png",1);
INSERT INTO uo_country VALUES(1168,"Solomon Islands","SOL","Soloman_Islands.png",1);
INSERT INTO uo_country VALUES(1169,"Somalia","SOM","Somalia.png",1);
INSERT INTO uo_country VALUES(1170,"Serbia","SRB",NULL,1);
INSERT INTO uo_country VALUES(1171,"Sri Lanka","SRI","Sri_Lanka.png",1);
INSERT INTO uo_country VALUES(1172,"Sao Tomé and Príncipe","STP","Sao_Tomé_and_Príncipe.png",1);
INSERT INTO uo_country VALUES(1173,"Sudan","SUD","Sudan.png",1);
INSERT INTO uo_country VALUES(1174,"Switzerland","SUI","Switzerland.png",1);
INSERT INTO uo_country VALUES(1175,"Suriname","SUR","Suriname.png",1);
INSERT INTO uo_country VALUES(1176,"Slovakia","SVK","Slovakia.png",1);
INSERT INTO uo_country VALUES(1177,"Sweden","SWE","Sweden.png",1);
INSERT INTO uo_country VALUES(1178,"Swaziland","SWZ","Swaziland.png",1);
INSERT INTO uo_country VALUES(1179,"Syria","SYR","Syria.png",1);
INSERT INTO uo_country VALUES(1180,"Tanzania","TAN","Tanzania.png",1);
INSERT INTO uo_country VALUES(1181,"Tonga","TGA","Tonga.png",1);
INSERT INTO uo_country VALUES(1182,"Thailand","THA","Thailand.png",1);
INSERT INTO uo_country VALUES(1183,"Tajikistan","TJK","Tajikistan.png",1);
INSERT INTO uo_country VALUES(1184,"Turkmenistan","TKM","Turkmenistan.png",1);
INSERT INTO uo_country VALUES(1185,"Timor-Leste","TLS",NULL,1);
INSERT INTO uo_country VALUES(1186,"Togo","TOG","Togo.png",1);
INSERT INTO uo_country VALUES(1187,"Chinese Taipei","TPE","Taiwan.png",1);
INSERT INTO uo_country VALUES(1188,"Trinidad and Tobago","TRI","Trinidad_and_Tobago.png",1);
INSERT INTO uo_country VALUES(1189,"Tunisia","TUN","Tunisia.png",1);
INSERT INTO uo_country VALUES(1190,"Turkey","TUR","Turkey.png",1);
INSERT INTO uo_country VALUES(1191,"Tuvalu","TUV","Tuvalu.png",1);
INSERT INTO uo_country VALUES(1192,"United Arab Emirates","UAE",NULL,1);
INSERT INTO uo_country VALUES(1193,"Uganda","UGA","Uganda.png",1);
INSERT INTO uo_country VALUES(1194,"Ukraine","UKR","Ukraine.png",1);
INSERT INTO uo_country VALUES(1195,"Uruguay","URU","Uruguay.png",1);
INSERT INTO uo_country VALUES(1196,"United States","USA","United_States_of_America.png",1);
INSERT INTO uo_country VALUES(1197,"Uzbekistan","UZB","Uzbekistan.png",1);
INSERT INTO uo_country VALUES(1198,"Vanuatu","VAN","Vanuatu.png",1);
INSERT INTO uo_country VALUES(1199,"Venezuela","VEN","Venezuela.png",1);
INSERT INTO uo_country VALUES(1200,"Vietnam","VIE","Vietnam.png",1);
INSERT INTO uo_country VALUES(1201,"Saint Vincent and the Grenadines","VIN","Saint_Vicent_and_the_Grenadines.png",1);
INSERT INTO uo_country VALUES(1202,"Yemen","YEM","Yemen.png",1);
INSERT INTO uo_country VALUES(1203,"Zambia","ZAM","Zambia.png",1);
INSERT INTO uo_country VALUES(1204,"Zimbabwe","ZIM","Zimbabwe.png",1);


CREATE TABLE `uo_database` (
  `version` int(10) DEFAULT NULL,
  `updated` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

INSERT INTO uo_database VALUES(61,"2011-06-07 23:39:13");

INSERT INTO uo_database VALUES(62,"2011-12-06 20:21:14");
INSERT INTO uo_database VALUES(63,"2011-12-06 20:21:14");
INSERT INTO uo_database VALUES(64,"2011-12-06 20:21:14");
INSERT INTO uo_database VALUES(65,"2011-12-06 20:21:14");

CREATE TABLE `uo_dbtranslations` (
  `translation_key` varchar(50) NOT NULL,
  `fi_FI_utf8` varchar(50) DEFAULT NULL,
  `en_GB_utf8` varchar(50) DEFAULT NULL,
  `de_DE_utf8` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`translation_key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;


CREATE TABLE `uo_enrolledteam` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `clubname` varchar(50) DEFAULT NULL,
  `series` int(10) NOT NULL,
  `userid` varchar(50) NOT NULL,
  `status` int(10) DEFAULT '0',
  `enroll_time` datetime DEFAULT NULL,
  `countryname` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_series` (`series`)
) ENGINE=MyISAM AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_event_log` (
  `event_id` int(15) NOT NULL AUTO_INCREMENT,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(15) DEFAULT NULL,
  `user_id` varchar(50) NOT NULL,
  `category` varchar(15) NOT NULL,
  `type` varchar(15) NOT NULL,
  `id1` varchar(20) DEFAULT NULL,
  `id2` varchar(20) DEFAULT NULL,
  `source` varchar(20) DEFAULT NULL,
  `description` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`event_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_game` (
  `game_id` int(10) NOT NULL AUTO_INCREMENT,
  `hometeam` int(10) DEFAULT NULL,
  `visitorteam` int(10) DEFAULT NULL,
  `homescore` smallint(5) DEFAULT NULL,
  `visitorscore` smallint(5) DEFAULT NULL,
  `reservation` int(10) DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `pool` int(10) DEFAULT NULL,
  `valid` tinyint(1) NOT NULL,
  `halftime` int(10) DEFAULT NULL,
  `official` varchar(50) DEFAULT NULL,
  `respteam` int(10) DEFAULT NULL,
  `resppers` int(10) DEFAULT NULL,
  `homesotg` int(10) DEFAULT NULL,
  `visitorsotg` int(10) DEFAULT NULL,
  `isongoing` tinyint(1) DEFAULT '0',
  `scheduling_name_home` int(10) DEFAULT NULL,
  `scheduling_name_visitor` int(10) DEFAULT NULL,
  `name` int(10) DEFAULT NULL,
  `timeslot` int(10) DEFAULT NULL,
  PRIMARY KEY (`game_id`),
  INDEX `idx_hometeam` (`hometeam`),
  INDEX `idx_visitorteam` (`visitorteam`),
  INDEX `idx_reservation` (`reservation`),
  INDEX `idx_game_id` (`game_id`),
  INDEX `idx_name` (`name`),
  INDEX `idx_pool` (`pool`)
) ENGINE=MyISAM AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_game_pool` (
  `game` int(10) NOT NULL,
  `pool` int(10) NOT NULL,
  `timetable` tinyint(1) NOT NULL,
  PRIMARY KEY (`game`,`pool`),
  INDEX `idx_game` (`game`),
  INDEX `idx_pool` (`pool`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_gameevent` (
  `game` int(10) NOT NULL,
  `num` smallint(5) NOT NULL,
  `time` smallint(5) DEFAULT NULL,
  `type` varchar(10) DEFAULT NULL,
  `ishome` tinyint(1) NOT NULL,
  `info` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`game`,`num`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_goal` (
  `game` int(10) NOT NULL,
  `num` smallint(5) NOT NULL,
  `assist` int(10) DEFAULT NULL,
  `scorer` int(10) DEFAULT NULL,
  `time` smallint(5) DEFAULT NULL,
  `homescore` tinyint(3) unsigned DEFAULT NULL,
  `visitorscore` tinyint(3) unsigned DEFAULT NULL,
  `ishomegoal` tinyint(1) NOT NULL,
  `iscallahan` tinyint(1) NOT NULL,
  PRIMARY KEY (`game`,`num`),
  INDEX `idx_game` (`game`),
  INDEX `idx_assist` (`assist`),
  INDEX `idx_scorer` (`scorer`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_image` (
  `image_id` int(10) NOT NULL AUTO_INCREMENT,
  `image_type` varchar(30) DEFAULT NULL,
  `image_height` int(5) DEFAULT NULL,
  `image_width` int(5) DEFAULT NULL,
  `image_size` int(10) DEFAULT NULL,
  `thumb_height` int(5) DEFAULT NULL,
  `thumb_width` int(5) DEFAULT NULL,
  `thumb` mediumblob,
  `image` longblob,
  PRIMARY KEY (`image_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;


CREATE TABLE `uo_keys` (
  `key_id` int(10) NOT NULL AUTO_INCREMENT,
  `type` varchar(15) NOT NULL,
  `purpose` varchar(15) DEFAULT NULL,
  `id` varchar(15) DEFAULT NULL,
  `keystring` varchar(50) DEFAULT NULL,
  `secrets` varchar(50) DEFAULT NULL,
  `url` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`key_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_license` (
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
  INDEX `idx_firstname` (`firstname`),
  INDEX `idx_lastname` (`lastname`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;


CREATE TABLE `uo_location` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `fields` int(5) NOT NULL DEFAULT '1',
  `indoor` tinyint(1) NOT NULL DEFAULT '0',
  `address` varchar(255) DEFAULT NULL,
  `info_fi_FI_utf8` varchar(255) DEFAULT NULL,
  `info_en_GB_utf8` varchar(255) DEFAULT NULL,
  `lat` float(17,13) DEFAULT NULL,
  `lng` float(17,13) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_moveteams` (
  `frompool` int(10) NOT NULL,
  `topool` int(10) NOT NULL,
  `fromplacing` int(5) NOT NULL,
  `torank` int(5) NOT NULL,
  `ismoved` tinyint(4) NOT NULL DEFAULT '0',
  `scheduling_id` int(10) DEFAULT NULL,
  PRIMARY KEY (`frompool`,`fromplacing`),
  INDEX `idx_scheduling_id` (`scheduling_id`),
  INDEX `idx_topool` (`topool`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_pageload_counter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page` varchar(100) NOT NULL,
  `loads` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;


CREATE TABLE `uo_played` (
  `player` int(10) NOT NULL,
  `game` int(10) NOT NULL,
  `num` smallint(5) DEFAULT NULL,
  `accredited` tinyint(1) NOT NULL DEFAULT '0',
  `acknowledged` tinyint(1) NOT NULL DEFAULT '0',
  `captain` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`player`,`game`),
  INDEX `idx_player` (`player`),
  INDEX `idx_game` (`game`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_player` (
  `player_id` int(10) NOT NULL AUTO_INCREMENT,
  `firstname` varchar(40) DEFAULT '',
  `lastname` varchar(40) DEFAULT '',
  `team` int(10) DEFAULT NULL,
  `num` tinyint(3) unsigned DEFAULT NULL,
  `accreditation_id` varchar(150) DEFAULT NULL,
  `accredited` tinyint(1) NOT NULL DEFAULT '0',
  `profile_id` int(10) DEFAULT NULL,
  PRIMARY KEY (`player_id`),
  INDEX `idx_accreditation_id` (`profile_id`),
  INDEX `idx_team` (`team`)
) ENGINE=MyISAM AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_player_profile` (
  `profile_id` int(10) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) DEFAULT NULL,
  `firstname` varchar(40) DEFAULT NULL,
  `lastname` varchar(40) DEFAULT NULL,
  `num` tinyint(3) DEFAULT NULL,  
  
  `nickname` varchar(20) DEFAULT NULL,
  `birthdate` datetime DEFAULT NULL,
  `birthplace` varchar(30) DEFAULT NULL,
  `nationality` varchar(30) DEFAULT NULL,
  `throwing_hand` varchar(15) DEFAULT NULL,
  `height` varchar(10) DEFAULT NULL,
  `story` text,
  `achievements` text,
  `image` int(10) DEFAULT NULL,
  `profile_image` varchar(30) DEFAULT NULL,
  `weight` varchar(10) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `gender` CHAR(1) DEFAULT NULL,
  `info` text DEFAULT NULL,
  `national_id` VARCHAR(100) DEFAULT NULL,
  `accreditation_id` varchar(50) DEFAULT NULL,  
  `public` VARCHAR(200) DEFAULT 'nickname|birthplace|nationality|throwing_hand|height|weight|position|story|achievements|profile_image',
  `ffindr_id` int(10) DEFAULT NULL,


  PRIMARY KEY (`profile_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_player_stats` (
  `player_id` int(10) NOT NULL,
  `profile_id` int(10) NOT NULL,
  `team` int(10) DEFAULT NULL,
  `season` varchar(10) DEFAULT NULL,
  `series` int(10) DEFAULT NULL,
  `games` int(5) DEFAULT '0',
  `wins` int(5) DEFAULT '0',
  `goals` int(5) DEFAULT '0',
  `passes` int(5) DEFAULT '0',
  `callahans` int(5) DEFAULT '0',
  `breaks` int(5) DEFAULT '0',
  `offence_turns` int(5) DEFAULT '0',
  `defence_turns` int(5) DEFAULT '0',
  `offence_time` int(5) DEFAULT '0',
  `defence_time` int(5) DEFAULT '0',
  PRIMARY KEY (`player_id`),
  INDEX `idx_profile_id` (`profile_id`),
  INDEX `idx_team` (`team`),
  INDEX `idx_season` (`season`),
  INDEX `idx_series` (`series`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;



CREATE TABLE `uo_pool` (
  `pool_id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT NULL,
  `ordering` varchar(20) DEFAULT NULL,
  `visible` tinyint(1) NOT NULL,
  `continuingpool` tinyint(1) NOT NULL,
  `placementpool` tinyint(1) DEFAULT '0',
  `teams` int(10) DEFAULT NULL,
  `mvgames` int(10) DEFAULT NULL,
  `timeoutlen` int(10) DEFAULT NULL,
  `halftime` int(10) DEFAULT NULL,
  `winningscore` smallint(5) DEFAULT NULL,
  `timecap` int(10) DEFAULT NULL,
  `scorecap` smallint(5) DEFAULT NULL,
  `played` tinyint(1) NOT NULL,
  `addscore` int(10) DEFAULT NULL,
  `halftimescore` int(10) DEFAULT NULL,
  `timeouts` int(10) DEFAULT NULL,
  `timeoutsper` varchar(5) DEFAULT NULL,
  `timeoutsovertime` int(10) DEFAULT NULL,
  `timeoutstimecap` varchar(5) DEFAULT NULL,
  `betweenpointslen` int(10) DEFAULT NULL,
  `series` int(10) DEFAULT NULL,
  `type` int(10) NOT NULL DEFAULT '1',
  `timeslot` int(10) DEFAULT NULL,
  `color` varchar(6) DEFAULT NULL,
  `forfeitscore` int(10) DEFAULT NULL,
  `forfeitagainst` int(10) DEFAULT NULL,
  `follower` int(10) DEFAULT NULL,
  PRIMARY KEY (`pool_id`),
  INDEX `idx_series` (`series`)
) ENGINE=MyISAM AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_pooltemplate` (
  `template_id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT NULL,
  `ordering` varchar(20) DEFAULT NULL,
  `continuingpool` tinyint(1) NOT NULL,
  `teams` int(10) DEFAULT NULL,
  `mvgames` int(10) DEFAULT NULL,
  `timeoutlen` int(10) DEFAULT NULL,
  `halftime` int(10) DEFAULT NULL,
  `winningscore` smallint(5) DEFAULT NULL,
  `timecap` int(10) DEFAULT NULL,
  `scorecap` smallint(5) DEFAULT NULL,
  `addscore` int(10) DEFAULT NULL,
  `halftimescore` int(10) DEFAULT NULL,
  `timeouts` int(10) DEFAULT NULL,
  `timeoutsper` varchar(5) DEFAULT NULL,
  `timeoutsovertime` int(10) DEFAULT NULL,
  `timeoutstimecap` varchar(5) DEFAULT NULL,
  `betweenpointslen` int(10) DEFAULT NULL,
  `type` int(10) NOT NULL DEFAULT '1',
  `timeslot` int(10) DEFAULT NULL,
  `forfeitscore` int(10) DEFAULT NULL,
  `forfeitagainst` int(10) DEFAULT NULL,
  PRIMARY KEY (`template_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_registerrequest` (
  `userid` varchar(50) NOT NULL,
  `password` char(32) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(100) DEFAULT NULL,
  `last_login` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`userid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_reservation` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `location` int(10) NOT NULL,
  `fieldname` varchar(50) DEFAULT NULL,
  `reservationgroup` varchar(50) DEFAULT NULL,
  `starttime` datetime DEFAULT NULL,
  `endtime` datetime DEFAULT NULL,
  `season` varchar(10) DEFAULT NULL,
  `timeslots` varchar(100) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_location` (`location`)
) ENGINE=MyISAM AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;


CREATE TABLE `uo_scheduling_name` (
  `scheduling_id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`scheduling_id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_season` (
  `season_id` varchar(10) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `starttime` datetime DEFAULT NULL,
  `endtime` datetime DEFAULT NULL,
  `iscurrent` tinyint(4) NOT NULL DEFAULT '0',
  `enrollopen` tinyint(1) NOT NULL DEFAULT '0',
  `enroll_deadline` datetime DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `istournament` tinyint(1) DEFAULT '0',
  `isinternational` tinyint(1) DEFAULT '0',
  `isnationalteams` tinyint(1) DEFAULT '0',
  `organizer` varchar(50) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `spiritpoints` tinyint(1) DEFAULT NULL,
  `showspiritpoints` tinyint(1) DEFAULT 0,
  `timezone` varchar(50) default NULL,
  PRIMARY KEY (`season_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_season_stats` (
  `season` varchar(10) NOT NULL,
  `teams` int(5) DEFAULT '0',
  `games` int(5) DEFAULT '0',
  `players` int(5) DEFAULT '0',
  `goals_total` int(5) DEFAULT '0',
  `home_wins` int(5) DEFAULT '0',
  PRIMARY KEY (`season`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;



CREATE TABLE `uo_series` (
  `series_id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT NULL,
  `ordering` varchar(1) DEFAULT NULL,
  `season` varchar(50) DEFAULT NULL,
  `valid` tinyint(4) NOT NULL DEFAULT '0',
  `type` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`series_id`),
  INDEX `idx_season` (`season`)
) ENGINE=MyISAM AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;


CREATE TABLE `uo_series_stats` (
  `series_id` int(10) NOT NULL,
  `season` varchar(10) DEFAULT NULL,
  `teams` int(5) DEFAULT '0',
  `games` int(5) DEFAULT '0',
  `players` int(5) DEFAULT '0',
  `goals_total` int(5) DEFAULT '0',
  `home_wins` int(5) DEFAULT '0',
  PRIMARY KEY (`series_id`),
  INDEX `idx_season` (`season`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;


CREATE TABLE `uo_setting` (
  `name` varchar(50) DEFAULT NULL,
  `value` varchar(200) DEFAULT '',
  `setting_id` int(10) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`setting_id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

INSERT INTO uo_setting VALUES("CurrentSeason",NULL,"1");
INSERT INTO uo_setting VALUES("HomeTeamResponsible","yes","2");
INSERT INTO uo_setting VALUES("GoogleMapsAPIKey",NULL,"3");
INSERT INTO uo_setting VALUES("TwitterEnabled","false","4");
INSERT INTO uo_setting VALUES("TwitterConsumerKey",NULL,"5");
INSERT INTO uo_setting VALUES("TwitterConsumerSecret",NULL,"6");
INSERT INTO uo_setting VALUES("TwitterOAuthCallback","http://127.0.0.1:80/pelikone3/ext/twittercallback.php","7");
INSERT INTO uo_setting VALUES ("EmailSource", "ultiorganizer@diibadaaba.net", "8");
INSERT INTO uo_setting VALUES ("FacebookEnabled", "false", "9");
INSERT INTO uo_setting VALUES ("FacebookAppId", NULL, "10");
INSERT INTO uo_setting VALUES ("FacebookAppKey", NULL, "11");
INSERT INTO uo_setting VALUES ("FacebookAppSecret", NULL, "12");
INSERT INTO uo_setting VALUES ("FacebookGameMessage", "Game finished in pool $pool", "13");
INSERT INTO uo_setting VALUES ("FacebookUpdatePage", "", "14");
INSERT INTO uo_setting VALUES ("GameRSSEnabled", "false", "15");
INSERT INTO uo_setting VALUES("PageTitle","Ultiorganizer - ",16);


CREATE TABLE `uo_sms` (
  `sms_id` int(10) NOT NULL AUTO_INCREMENT,
  `to1` int(15) NOT NULL,
  `to2` int(15) DEFAULT NULL,
  `to3` int(15) DEFAULT NULL,
  `to4` int(15) DEFAULT NULL,
  `to5` int(15) DEFAULT NULL,
  `msg` varchar(400) DEFAULT NULL,
  `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `click_id` int(10) DEFAULT NULL,
  `sent` datetime DEFAULT NULL,
  `delivered` datetime DEFAULT NULL,
  PRIMARY KEY (`sms_id`)
) ENGINE=MyISAM AUTO_INCREMENT=1000 DEFAULT CHARSET=latin1;


CREATE TABLE IF NOT EXISTS `uo_specialranking` (
  `frompool` int(10) NOT NULL,
  `fromplacing` int(5) NOT NULL,
  `torank` int(5) NOT NULL,
  `scheduling_id` int(10) DEFAULT NULL,
  PRIMARY KEY (`frompool`,`fromplacing`),
  KEY `idx_scheduling_id` (`scheduling_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;


CREATE TABLE `uo_team` (
  `team_id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT NULL,
  `pool` int(10) DEFAULT NULL,
  `club` int(10) DEFAULT NULL,
  `rank` smallint(5) DEFAULT NULL,
  `activerank` int(10) DEFAULT NULL,
  `valid` tinyint(1) NOT NULL,
  `series` int(10) DEFAULT NULL,
  `country` int(10) DEFAULT NULL,
  `abbreviation` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`team_id`),
  INDEX `idx_club` (`club`),
  INDEX `idx_country` (`country`),
  INDEX `idx_series` (`series`),
  INDEX `idx_pool` (`pool`)
) ENGINE=MyISAM AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_team_pool` (
  `team` int(10) NOT NULL,
  `pool` int(10) NOT NULL,
  `rank` smallint(5) DEFAULT NULL,
  `activerank` int(10) DEFAULT NULL,
  PRIMARY KEY (`team`,`pool`),
  INDEX `idx_team` (`team`),
  INDEX `idx_pool` (`pool`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_team_profile` (
  `team_id` int(11) NOT NULL,
  `coach` varchar(100) DEFAULT NULL,
  `story` text,
  `achievements` text,
  `image` int(10) DEFAULT NULL,
  `profile_image` varchar(20) DEFAULT NULL,
  `captain` varchar(100) DEFAULT '',
  `ffindr_id` int(10) DEFAULT NULL,
  PRIMARY KEY (`team_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_team_stats` (
  `team_id` int(10) NOT NULL,
  `season` varchar(10) DEFAULT NULL,
  `series` int(10) DEFAULT NULL,
  `goals_made` int(5) DEFAULT '0',
  `goals_against` int(5) DEFAULT '0',
  `standing` int(5) DEFAULT '0',
  `wins` int(5) DEFAULT '0',
  `loses` int(5) DEFAULT '0',
  PRIMARY KEY (`team_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;


CREATE TABLE `uo_timeout` (
  `timeout_id` int(10) NOT NULL AUTO_INCREMENT,
  `game` int(10) DEFAULT NULL,
  `num` tinyint(3) unsigned DEFAULT NULL,
  `time` int(10) DEFAULT NULL,
  `ishome` tinyint(1) NOT NULL,
  PRIMARY KEY (`timeout_id`),
  INDEX `idx_game` (`game`)
) ENGINE=MyISAM AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

CREATE TABLE `uo_urls` (
  `url_id` int(10) NOT NULL AUTO_INCREMENT,
  `owner` varchar(15) NOT NULL,
  `owner_id` varchar(15) DEFAULT NULL,
  `type` varchar(15) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `ordering` varchar(2) default '',
  `ismedialink` tinyint(1) DEFAULT '0',
  `mediaowner` varchar(100) DEFAULT NULL,
  `publisher_id` int(10) DEFAULT NULL,
  PRIMARY KEY (`url_id`),
  INDEX `idx_owner_id` (`owner_id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

INSERT INTO uo_urls VALUES(1,"ultiorganizer",0,"menulink","Powered by Ultiorganizer","https://sourceforge.net/apps/trac/ultiorganizer/","XA",0,NULL,NULL);
INSERT INTO uo_urls VALUES(2,"ultiorganizer",0,"menumail","Administration","admin@example.com","YA",0,NULL,NULL);

CREATE TABLE `uo_userproperties` (
  `prop_id` int(10) NOT NULL AUTO_INCREMENT,
  `userid` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `value` varchar(256) DEFAULT NULL,
  PRIMARY KEY (`prop_id`),
  INDEX `idx_userid` (`userid`)
) ENGINE=MyISAM AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

INSERT INTO uo_userproperties VALUES("1","anonymous","poolselector","currentseason");
INSERT INTO uo_userproperties VALUES("2","admin","userrole","superadmin");

CREATE TABLE `uo_users` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `userid` varchar(50) NOT NULL,
  `password` char(32) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userid` (`userid`)
) ENGINE=MyISAM AUTO_INCREMENT=1001 DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;

INSERT INTO uo_users VALUES("1","anonymous",NULL,NULL,NULL,NULL);
INSERT INTO uo_users VALUES("2","admin","21232f297a57a5a743894a0e4a801fc3","Administrator",NULL,"2010-04-01 15:40:46");

create table uo_extraemailrequest (
		userid varchar(50) not null, 
		email varchar(100) not null, 
		token varchar(100),
		primary key (email) 
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

create table uo_extraemail (
		userid varchar(50) not null, 
		email varchar(100) not null, 
		primary key (email) 
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

	
CREATE TABLE IF NOT EXISTS `uo_victorypoints` (
  `pointdiff` tinyint(10) NOT NULL,
  `victorypoints` tinyint(10) NOT NULL,
  PRIMARY KEY (`pointdiff`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='defines how many victory points you get for a point diff';

INSERT INTO `uo_victorypoints` (`pointdiff`, `victorypoints`) VALUES (-15, 0), (-14, 1), (-13, 2), (-12, 3), (-11, 4), (-10, 5), (-9, 6), (-8, 7), (-7, 8), (-6, 9), (-5, 10), (-4, 11), (-3, 12), (-2, 13), (-1, 14), (0, 15), (1, 16), (2, 17), (3, 18), (4, 19), (5, 20), (6, 21), (7, 22), (8, 23), (9, 24), (10, 25), (11, 25), (12, 25), (13, 25), (14, 25), (15, 25);


CREATE TABLE `uo_visitor_counter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(15) NOT NULL DEFAULT '',
  `visits` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;
