<?php
include_once $include_prefix.'lib/configuration.functions.php';
include_once $include_prefix.'lib/game.functions.php';

function TournamentView($games, $grouping=true){

  $ret = "";
  $prevTournament = "";
  $prevPlace = "";
  $prevSeries = "";
  $prevPool = "";
  $prevTeam = "";
  $prevDate = "";
  $prevTimezone = "";
  $isTableOpen = false;
  $rss = IsGameRSSEnabled();

  while($game = mysqli_fetch_assoc($games)){
    $ret .= "\n<!-- res:". $game['reservationgroup'] ." pool:". $game['pool']." date:".JustDate($game['starttime'])."-->\n";
    if($game['reservationgroup'] != $prevTournament
    || (empty($game['reservationgroup']) && !$isTableOpen)) {
      if($isTableOpen){
        $ret .= "</table>\n";
        $ret .= "<hr/>\n";
        $isTableOpen = false;
      }
      if($grouping){
        $ret .= "<h1>". utf8entities(U_($game['reservationgroup'])) ."</h1>\n";
      }
      $prevPlace="";
      	
    }

    if(JustDate($game['starttime']) != $prevDate || $game['place_id'] != $prevPlace){
      if($isTableOpen){
        $ret .= "</table>\n";
        $isTableOpen = false;
      }
      $ret .= "<h3>";
      $ret .= DefWeekDateFormat($game['starttime']);
      $ret .= " ";
      $ret .= "<a href='?view=reservationinfo&amp;reservation=".$game['reservation_id']."'>";
      $ret .= utf8entities(U_($game['placename']));
      $ret .= "</a>";
      $ret .= "</h3>\n";
      $prevPool="";
    }

    if($game['pool'] != $prevPool){
      if($isTableOpen){
        $ret .= "</table>\n";
        $isTableOpen = false;
      }
      $ret .= "<table cellpadding='2' border='0' cellspacing='0'>\n";
      $isTableOpen = true;
      $ret .= SeriesAndPoolHeaders($game);
    }

    if($isTableOpen){
      //function GameRow($game, $date=false, $time=true, $field=true, $series=false,$pool=false,$info=true)
      $ret .= GameRow($game, false,true,true,false,false,true,$rss);
    }

    $prevTournament = $game['reservationgroup'];
    $prevPlace = $game['place_id'];
    $prevSeries = $game['series_id'];
    $prevPool = $game['pool'];
    $prevDate = JustDate($game['starttime']);
    $prevTimezone = $game['timezone'];
  }

  if($isTableOpen){
    $ret .= "</table>\n";
  }
  $ret .= PrintTimeZone($prevTimezone);
  return $ret;
}

function SeriesView($games, $date=true, $time=false){
  $ret = "";
  $prevTournament = "";
  $prevPlace = "";
  $prevSeries = "";
  $prevPool = "";
  $prevTeam = "";
  $prevDate = "";
  $prevTimezone = "";
  $isTableOpen = false;
  $rss = IsGameRSSEnabled();

  while($game = mysqli_fetch_assoc($games)){
    if($game['series_id'] != $prevSeries
    || (empty($game['series_id']) && !$isTableOpen)) {
      if($isTableOpen){
        $ret .= "</table>\n";
        $ret .= "<hr/>\n";
        $isTableOpen = false;
      }
      $ret .= "<h1>". utf8entities(U_($game['seriesname'])) ."</h1>\n";
    }

    if($game['pool'] != $prevPool){
      if($isTableOpen){
        $ret .= "</table>\n";
        $isTableOpen = false;
      }
      $ret .= "<table cellpadding='2' border='0' cellspacing='0'>\n";
      $isTableOpen = true;
      $ret .= PoolHeaders($game);
    }

    //function GameRow($game, $date=false, $time=true, $field=true, $series=false,$pool=false,$info=true)
    $ret .= GameRow($game, true, true, true, false, false, true, $rss);

    $prevTournament = $game['reservationgroup'];
    $prevPlace = $game['place_id'];
    $prevSeries = $game['series_id'];
    $prevPool = $game['pool'];
    $prevDate = JustDate($game['time']);
    $prevTimezone = $game['timezone'];
  }

  if($isTableOpen){
    $ret .= "</table>\n";
  }
  $ret .= PrintTimeZone($prevTimezone);
  return $ret;
}

function PlaceView($games, $grouping=true){
  $ret = "";
  $prevTournament = "";
  $prevPlace = "";
  $prevSeries = "";
  $prevPool = "";
  $prevTeam = "";
  $prevDate = "";
  $prevField = "";
  $prevTimezone = "";
  $isTableOpen = false;
  $rss = IsGameRSSEnabled();

  while($game = mysqli_fetch_assoc($games)){
    if($game['reservationgroup'] != $prevTournament
    || (empty($game['reservationgroup']) && !$isTableOpen)) {
      if($isTableOpen){
        $ret .= "</table>\n";
        $ret .= "<hr/>\n";
        $isTableOpen = false;
      }
      if($grouping){
        $ret .= "<h1>". utf8entities(U_($game['reservationgroup'])) ."</h1>\n";
      }
      $prevDate = "";
    }

    if(JustDate($game['starttime']) != $prevDate){
      if($isTableOpen){
        $ret .= "</table>\n";
        $isTableOpen = false;
      }
      $ret .= "<h3>";
      $ret .= DefWeekDateFormat($game['starttime']);
      $ret .= "</h3>\n";
    }

    if($game['place_id'] != $prevPlace || $game['fieldname'] != $prevField || JustDate($game['starttime']) != $prevDate){
      if($isTableOpen){
        $ret .= "</table>\n";
        $isTableOpen = false;
      }
      $ret .= "<table cellpadding='2' border='0' cellspacing='0'>\n";
      $isTableOpen = true;
      $ret .= PlaceHeaders($game, true);
    }

    if($isTableOpen){
      //function GameRow($game, $date=false, $time=true, $field=true, $series=false,$pool=false,$info=true)
      $ret .= GameRow($game, false, true, false, true, true, true,$rss);
    }

    $prevTournament = $game['reservationgroup'];
    $prevPlace = $game['place_id'];
    $prevField = $game['fieldname'];
    $prevSeries = $game['series_id'];
    $prevPool = $game['pool'];
    $prevDate = JustDate($game['starttime']);
    $prevTimezone = $game['timezone'];
  }

  if($isTableOpen){
    $ret .= "</table>\n";
  }
  $ret .= PrintTimeZone($prevTimezone);
  return $ret;
}

function TimeView($games, $grouping=true){
  $ret = "";
  $prevTournament = "";
  $prevTime = "";
  $isTableOpen = false;
  $rss = IsGameRSSEnabled();

  while($game = mysqli_fetch_assoc($games)){
    if($game['time'] != $prevTime) {
      if($isTableOpen){
        $ret .= "</table>\n";
        //$ret .= "<hr/>\n";
        $isTableOpen = false;
      }
      $ret .= "<h3>". DefWeekDateFormat($game['time']) ." ". DefHourFormat($game['time']) ."</h3>\n";
      $ret .= "<table cellpadding='2' border='0' cellspacing='0'>\n";
      $isTableOpen = true;
    }

    if($isTableOpen){
      //function GameRow($game, $date=false, $time=true, $field=true, $series=false,$pool=false,$info=true)
      $ret .= GameRow($game, false, false, true, true, true, true,$rss);
    }

    $prevTime = $game['time'];
    $prevTimezone = $game['timezone'];

  }

  if($isTableOpen){
    $ret .= "</table>\n";
  }
  $ret .= PrintTimeZone($prevTimezone);
  return $ret;
}

function ExtTournamentView($games){
  $ret = "";
  $prevTournament = "";
  $prevPlace = "";
  $prevSeries = "";
  $prevPool = "";
  $prevTeam = "";
  $prevDate = "";
  $prevField = "";
  $prevTimezone = "";
  $isTableOpen = false;
  $ret .= "<table width='95%'>";
  
  while($game = mysqli_fetch_assoc($games)){
    if($game['reservationgroup'] != $prevTournament
    || (empty($game['reservationgroup']) && !$isTableOpen)) {
      if($isTableOpen){
        $ret .= "</table></td></tr>\n";
        $isTableOpen = false;
      }
      $ret .= "<tr><td><h1 class='pk_h1'>". utf8entities(U_($game['reservationgroup'])) ."</h1></td></tr>\n";
    }

    if($game['place_id'] != $prevPlace || $game['fieldname'] != $prevField ||JustDate($game['starttime']) != $prevDate){
      if($isTableOpen){
        $ret .= "</table></td></tr>\n";
        $isTableOpen = false;
      }
      $ret .= "<tr><td style='width:100%'><table width='100%' class='pk_table'><tr><td class='pk_tournament_td1'>";
      $ret .= utf8entities(U_($game['placename'])) ." "._("Field")." ".utf8entities($game['fieldname'])."</td></tr></table></td></tr>\n";
      $ret .= "<tr><td><table width='100%' class='pk_table'>\n";
      $isTableOpen = true;
    }

    $ret .= "<tr><td style='width:10px' class='pk_tournament_td2'>". DefHourFormat($game['time']) ."</td>";
    if($game['hometeam'] && $game['visitorteam']){
      $ret .= "<td style='width:100px' class='pk_tournament_td2'>". utf8entities($game['hometeamname']) ."</td>
			<td style='width:5px' class='pk_tournament_td2'>-</td>
			<td style='width:100px' class='pk_tournament_td2'>". utf8entities($game['visitorteamname']) ."</td>";
      	
      if(GameHasStarted($game))
        $ret .= "<td style='text-align: center;width:8px' class='pk_tournament_td2'>?</td>
					<td style='text-align: center;width:5px' class='pk_tournament_td2'>-</td>
					<td style='text-align: center;width:8px' class='pk_tournament_td2'>?</td>";
      else
       $ret .= "<td style='text-align: center;width:8px' class='pk_tournament_td2'>". intval($game['homescore']) ."</td>
					<td style='text-align: center;width:5px' class='pk_tournament_td2'>-</td>
					<td style='text-align: center;width:8px' class='pk_tournament_td2'>". intval($game['visitorscore']) ."</td>";
    }else{
      $ret .= "<td style='width:100px' class='pk_tournament_td2'>". utf8entities($game['phometeamname']) ."</td>
			<td style='width:5px' class='pk_tournament_td2'>-</td>
			<td style='width:100px' class='pk_tournament_td2'>". utf8entities($game['pvisitorteamname']) ."</td>";
      $ret .= "<td style='text-align: center;width:8px' class='pk_tournament_td2'>?</td>
					<td style='text-align: center;width:5px' class='pk_tournament_td2'>-</td>
					<td style='text-align: center;width:8px' class='pk_tournament_td2'>?</td>";
    }
    $ret .= "<td style='width:5px' class='pk_tournament_td2'></td>";
    $ret .= "<td style='width:50px' class='pk_tournament_td2'>". utf8entities($game['seriesname']) ."</td>";
    $ret .= "<td style='width:100px' class='pk_tournament_td2'>". utf8entities($game['poolname']) ."</td>";
    $ret .= "</tr>\n";
    	

    $prevTournament = $game['reservationgroup'];
    $prevPlace = $game['place_id'];
    $prevField = $game['fieldname'];
    $prevSeries = $game['series_id'];
    $prevPool = $game['pool'];
    $prevDate = JustDate($game['starttime']);
    $prevTimezone = $game['timezone'];
  }

  if($isTableOpen){
    $ret .= "</table></td></tr>\n";
  }
  $ret .= "</table>\n";
  $ret .= PrintTimeZone($prevTimezone);
  return $ret;
}

function ExtGameView($games){
  $ret = "";
  $prevTournament = "";
  $prevPlace = "";
  $prevSeries = "";
  $prevPool = "";
  $prevTeam = "";
  $prevDate = "";
  $prevField = "";
  $prevTimezone = "";
  $isTableOpen = false;
  $ret .= "<table style='white-space: nowrap' width='95%'>";

  while($game = mysqli_fetch_assoc($games)){
    if($game['reservationgroup'] != $prevTournament
    || (empty($game['reservationgroup']) && !$isTableOpen)) {
      if($isTableOpen){
        $ret .= "</table></td></tr>\n";
        $isTableOpen = false;
      }
      $ret .= "<tr><td><h1 class='pk_h1'>". utf8entities(U_($game['reservationgroup'])) ."</h1></td></tr>\n";
    }

    if($game['place_id'] != $prevPlace || $game['fieldname'] != $prevField || JustDate($game['starttime']) != $prevDate){
      if($isTableOpen){
        $ret .= "</table></td></tr>\n";
        $isTableOpen = false;
      }
      $ret .= "<tr><td><table width='100%' class='pk_table'>";
      $ret .= "<tr><th class='pk_teamgames_th' colspan='12'>";
      $ret .= DefWeekDateFormat($game['starttime']) ." ". utf8entities(U_($game['placename']))." "._("Field")." ".utf8entities($game['fieldname']);
      $ret .= "</th></tr>\n";
      $isTableOpen = true;
    }

    $ret .= "<tr><td style='width:15%' class='pk_teamgames_td'>". DefHourFormat($game['time']) ."</td>";
    if($game['hometeam'] && $game['visitorteam']){
      $ret .= "<td style='width:36%' class='pk_teamgames_td'>". utf8entities($game['hometeamname']) ."</td>
			<td style='width:3%' class='pk_teamgames_td'>-</td>
			<td style='width:36%' class='pk_teamgames_td'>". utf8entities($game['visitorteamname']) ."</td>";
      if(GameHasStarted($game)){
        $ret .= "<td style='text-align: center;width:4%' class='pk_teamgames_td'>?</td>
					<td style='text-align: center;width:2%' class='pk_teamgames_td'>-</td>
					<td style='text-align: center;width:4%' class='pk_teamgames_td'>?</td>";
      }else{
        $ret .= "<td style='text-align: center;width:4%' class='pk_teamgames_td'>". intval($game['homescore']) ."</td>
					<td style='text-align: center;width:2%' class='pk_teamgames_td'>-</td>
					<td style='text-align: center;width:4%' class='pk_teamgames_td'>". intval($game['visitorscore']) ."</td>";
      }
    }else{
      $ret .= "<td style='width:36%' class='pk_teamgames_td'>". utf8entities($game['phometeamname']) ."</td>
			<td style='width:3%' class='pk_teamgames_td'>-</td>
			<td style='width:36%' class='pk_teamgames_td'>". utf8entities($game['pvisitorteamname']) ."</td>";
    }
    $ret .= "</tr>\n";
    	

    $prevTournament = $game['reservationgroup'];
    $prevPlace = $game['place_id'];
    $prevSeries = $game['series_id'];
    $prevField = $game['fieldname'];
    $prevPool = $game['pool'];
    $prevDate = JustDate($game['starttime']);
    $prevTimezone = $game['timezone'];
  }

  if($isTableOpen){
    $ret .= "</table></td></tr>\n";
  }
  $ret .= "</table>\n";
  $ret .= PrintTimeZone($prevTimezone);
  return $ret;
}

function PlaceHeaders($info, $field=false){
  $ret = "<tr>\n";
  $ret .= "<th align='left' colspan='13'>";
  $ret .= "<a class='thlink' href='?view=reservationinfo&amp;reservation=".$info['reservation_id']."'>";
  $ret .= utf8entities($info['placename']);
  $ret .= "</a>";
  if($field){
    $ret .= " "._("Field")." ".utf8entities($info['fieldname']);
  }

  $ret .= "</th>\n";
  $ret .= "</tr>\n";

  return $ret;
}

function PoolHeaders($info){
  $ret = "<tr style='width:100%'>\n";
  $ret .= "<th align='left' colspan='13'>";
  $ret .= utf8entities(U_($info['poolname']));
  $ret .= "</th>\n";
  $ret .= "</tr>\n";
  return $ret;
}

function SeriesAndPoolHeaders($info){
  $ret = "<tr style='width:100%'>\n";
  $ret .= "<th align='left' colspan='12'>";
  $ret .= utf8entities(U_($info['seriesname']));
  $ret .= " ";
  $ret .= utf8entities(U_($info['poolname']));
  $ret .= "</th>\n";
  $ret .= "</tr>\n";
  return $ret;
}

function GameRow($game, $date=false, $time=true, $field=true, $series=false,$pool=false,$info=true,$rss=false,$media=true){
  $datew = 'width:60px';
  $timew = 'width:40px';
  $fieldw = 'width:60px';
  $teamw = 'width:120px';
  $againstmarkw = 'width:5px';
  $seriesw = 'width:80px';
  $poolw = 'width:130px';
  $scoresw = 'width:15px';
  $infow = 'width:80px';
  $gamenamew = 'width:50px';
  $mediaw='width:40px';

  $ret = "<tr style='width:100%'>\n";

  if($date){
    $ret .= "<td style='$datew'><span>". ShortDate($game['time']) ."</span></td>\n";
  }

  if($time){
    $ret .= "<td style='$timew'><span>". DefHourFormat($game['time']) ."</span></td>\n";
  }

  if($field){
    if (!empty($game['fieldname']))
      $ret .= "<td style='$fieldw'><span>" . _("Field") . " " . utf8entities($game['fieldname']) . "</span></td>\n";
    else
      $ret .= "<td style='$fieldw'></td>\n";
  }

  if($game['hometeam']){
    $ret .= "<td style='$teamw'><span>". utf8entities($game['hometeamname']) ."</span></td>\n";
  }else{
    $ret .= "<td style='$teamw'><span class='schedulingname'>". utf8entities(U_($game['phometeamname'])) ."</span></td>\n";
  }

  $ret .= "<td style='$againstmarkw'>-</td>\n";

  if($game['visitorteam']){
    $ret .= "<td style='$teamw'><span>". utf8entities($game['visitorteamname']) ."</span></td>\n";
  }else{
    $ret .= "<td style='$teamw'><span class='schedulingname'>". utf8entities(U_($game['pvisitorteamname'])) ."</span></td>\n";
  }

  if($series){
    $ret .= "<td style='$seriesw'><span>". utf8entities(U_($game['seriesname'])) ."</span></td>\n";
  }

  if($pool){
    $ret .= "<td style='$poolw'><span>". utf8entities(U_($game['poolname'])) ."</span></td>\n";
  }

  if(!GameHasStarted($game))	{
    $ret .= "<td style='$scoresw'><span>?</span></td>\n";
    $ret .= "<td style='$againstmarkw'><span>-</span></td>\n";
    $ret .= "<td style='$scoresw'><span>?</span></td>\n";
  }else{
    if ($game ['isongoing']) {
      $ret .= "<td style='$scoresw'><span><em>" . intval ( $game ['homescore'] ) . "</em></span></td>\n";
      $ret .= "<td style='$againstmarkw'><span>-</span></td>\n";
      $ret .= "<td style='$scoresw'><span><em>" . intval ( $game ['visitorscore'] ) . "</em></span></td>\n";
    } else {
      $ret .= "<td style='$scoresw'><span>" . intval ( $game ['homescore'] ) . "</span></td>\n";
      $ret .= "<td style='$againstmarkw'><span>-</span></td>\n";
      $ret .= "<td style='$scoresw'><span>" . intval ( $game ['visitorscore'] ) . "</span></td>\n";
    }
  }

  if($game['gamename']){
    $ret .= "<td style='$gamenamew'><span>". utf8entities(U_($game['gamename'])) ."</span></td>\n";
  }else{
    $ret .= "<td style='$gamenamew'></td>\n";
  }

  if($media){
    $urls = GetMediaUrlList("game", $game['game_id'], "live");
    $ret .= "<td style='$mediaw;white-space: nowrap;'>";
    if(count($urls) && (intval($game['isongoing']) || !GameHasStarted($game))){
      foreach($urls as $url){
        $title=$url['name'];
        if(empty($title)){
          $title = _("Live Broadcasting");
        }
        $ret .= "<a href='". $url['url']."'>"."<img border='0' width='16' height='16' title='".utf8entities($title)."' src='images/linkicons/".$url['type'].".png' alt='".$url['type']."'/></a>";
      }
    }
    $ret .= "</td>\n";
  }

  if($info){
    if(!GameHasStarted($game)){
      if($game['hometeam'] && $game['visitorteam']){
        $t1 = preg_replace('/\s*/m','',$game['hometeamname']);
        $t2 = preg_replace('/\s*/m','',$game['visitorteamname']);

        $xgames = GetAllPlayedGames($t1,$t2, $game['type'], "");
        if(mysqli_num_rows($xgames)>0){
          $ret .= "<td class='right' style='$infow'><span style='white-space: nowrap'>";
          $ret .= "<a href='?view=gamecard&amp;team1=". utf8entities($game['hometeam']) ."&amp;team2=". utf8entities($game['visitorteam']) . "'>";
          $ret .=  _("Game history")."</a></span></td>\n";
        }else{
          $ret .= "<td class='left' style='$infow'></td>\n";
        }
      }else{
        $ret .= "<td class='left' style='$infow'></td>\n";
      }
    }else{
      if(!intval($game['isongoing'])){
        if(intval($game['scoresheet'])){
          $ret .= "<td class='right' style='$infow'><span>&nbsp;<a href='?view=gameplay&amp;game=". $game['game_id'] ."'>";
          $ret .= _("Game play") ."</a></span></td>\n";
        }else{
          $ret .= "<td class='left' style='$infow'></td>\n";
        }
      }else{
        if(intval($game['scoresheet'])){
          $ret .= "<td class='right' style='$infow'><span>&nbsp;&nbsp;<a href='?view=gameplay&amp;game=". $game['game_id'] ."'>";
          $ret .= _("Ongoing") ."</a></span></td>\n";
        }else{
          $ret .= "<td class='right' style='$infow'>&nbsp;&nbsp;"._("Ongoing")."</td>\n";
        }
        	
      }
    }
    if($rss){
      $ret .= "<td class='feed-list'><a style='color: #ffffff;' href='ext/rss.php?feed=game&amp;id1=".$game['game_id']."'>";
      $ret .= "<img src='images/feed-icon-14x14.png' width='10' height='10' alt='RSS'/></a></td>";
    }
  }
  $ret .=  "</tr>\n";
  return $ret;
}

function PrintTimeZone($timezone){
  $ret = "<p class='timezone'>"._("Timezone").": ".utf8entities($timezone).". ";
  if(class_exists("DateTime") && !empty($timezone)){
    $dateTime = new DateTime("now", new DateTimeZone($timezone));
    $ret .= _("Local time").": ".DefTimeFormat($dateTime->format("Y-m-d H:i:s"));
  }
  $ret .= "</p>";
  return $ret;
}

function NextGameDay($id, $gamefilter, $order){
  $games = TimetableGames($id, $gamefilter, "coming", "time");
  $game = mysqli_fetch_assoc($games);
  $next = ShortEnDate($game['time']);
  $games = TimetableGames($id, $gamefilter, $next, $order);
  return $games;
}

function PrevGameDay($id, $gamefilter, $order){
  $games = TimetableGames($id, $gamefilter, "past", "timedesc");
  $game = mysqli_fetch_assoc($games);
  $prev = ShortEnDate($game['time']);
  $games = TimetableGames($id, $gamefilter, $prev, $order);
  return $games;
}


function TimetableGames($id, $gamefilter, $timefilter, $order, $groupfilter=""){
  //common game query
  $query = "SELECT pp.game_id, pp.time, pp.hometeam, pp.visitorteam, pp.homescore,
			pp.visitorscore, pp.pool AS pool, pool.name AS poolname, pool.timeslot,
			ps.series_id, ps.name AS seriesname, ps.season, ps.type, pr.fieldname, pr.reservationgroup,
			pr.id AS reservation_id, pr.starttime, pr.endtime, pl.id AS place_id, COALESCE(pm.goals,0) AS scoresheet,
			pl.name AS placename, pl.address, pp.isongoing, pp.hasstarted, home.name AS hometeamname, visitor.name AS visitorteamname,
			phome.name AS phometeamname, pvisitor.name AS pvisitorteamname, pool.color, pgame.name AS gamename,
			home.abbreviation AS homeshortname, visitor.abbreviation AS visitorshortname, homec.country_id AS homecountryid, 
			homec.name AS homecountry, visitorc.country_id AS visitorcountryid, visitorc.name AS visitorcountry, 
			homec.flagfile AS homeflag, visitorc.flagfile AS visitorflag, s.timezone
			FROM uo_game pp 
			LEFT JOIN (SELECT COUNT(*) AS goals, game FROM uo_goal GROUP BY game) AS pm ON (pp.game_id=pm.game)
			LEFT JOIN uo_pool pool ON (pool.pool_id=pp.pool) 
			LEFT JOIN uo_series ps ON (pool.series=ps.series_id)
			LEFT JOIN uo_season s ON (s.season_id=ps.season)
			LEFT JOIN uo_reservation pr ON (pp.reservation=pr.id)
			LEFT JOIN uo_location pl ON (pr.location=pl.id)
			LEFT JOIN uo_team AS home ON (pp.hometeam=home.team_id)
			LEFT JOIN uo_team_pool AS homepool ON (pp.hometeam=homepool.team AND pp.pool=homepool.pool)
			LEFT JOIN uo_team AS visitor ON (pp.visitorteam=visitor.team_id)
			LEFT JOIN uo_country AS homec ON (homec.country_id=home.country)
			LEFT JOIN uo_country AS visitorc ON (visitorc.country_id=visitor.country)
			LEFT JOIN uo_scheduling_name AS pgame ON (pp.name=pgame.scheduling_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)";

  switch($gamefilter)
  {
    case "season":
      $query .= " WHERE pp.valid=true AND ps.season='".DBEscapeString($id)."'";
      break;

    case "series":
      $query .= " WHERE pp.valid=true AND ps.series_id='".(int)$id."'";
      break;

    case "pool":
      $query .= " WHERE pp.valid=true AND pp.pool='".(int)$id."'";
      break;

    case "poolgroup":
      //keep pool filter as it is to give better performance for single pool query
      //extra explode needed to make parameters safe
      $pools = explode(",", DBEscapeString($id));
      $query .= " WHERE pp.valid=true AND pp.pool IN(".implode(",",$pools).")";
      break;
      	
    case "team":
      $query .= " WHERE pp.valid=true AND (pp.visitorteam='".(int)$id."' OR pp.hometeam='".(int)$id."')";
      break;

    case "game":
      $query .= " WHERE pp.game_id=".(int)$id;
      break;
  }

  switch($timefilter)
  {
    case "coming":
      $query .= " AND pp.time IS NOT NULL AND ((pp.homescore IS NULL AND pp.visitorscore IS NULL) OR (pp.hasstarted=0) OR pp.isongoing=1)";
      break;

    case "past":
      $query .= " AND ((pp.hasstarted > 0) )";
      break;
      	
    case "played":
      $query .= " AND ((pp.hasstarted > 0) )";
      break;
      	
    case "ongoing":
      $query .= " AND pp.isongoing=1";
      break;
      	
    case "comingNotToday":
      $query .= " AND pp.time >= Now()";
      break;

    case "pastNotToday":
      $query .= " AND pp.time <= Now()";
      break;
      	
    case "today":
      $query .= " AND DATE_FORMAT(pp.time,'%Y-%m-%d') = DATE_SUB(CURRENT_DATE(), INTERVAL 0 DAY)";
      break;

    case "tomorrow":
      $query .= " AND DATE_FORMAT(pp.time,'%Y-%m-%d') = DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)";
      break;
      	
    case "yesterday":
      $query .= " AND DATE_FORMAT(pp.time,'%Y-%m-%d') = DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY)";
      break;

    case "all":
      break;
      	
    default:
      $query .= " AND DATE_FORMAT(pp.time,'%Y-%m-%d') = '".DBEscapeString($timefilter)."'";
      break;
  }

  if(!empty($groupfilter) && $groupfilter!="all"){
    $query .= "AND pr.reservationgroup='".DBEscapeString($groupfilter)."'";
  }

  switch($order)
  {
    case "tournaments":
      $query .= " ORDER BY pr.starttime, pr.reservationgroup, pl.id, ps.ordering, pool.ordering, pp.time ASC, pr.fieldname + 0, pp.game_id ASC";
      break;

    case "series":
      $query .= " ORDER BY ps.ordering, pool.ordering, pp.time ASC, pr.starttime, pr.fieldname + 0, pp.game_id ASC";
      break;

    case "places":
      $query .= " ORDER BY pr.starttime, pr.reservationgroup, pl.id, pr.fieldname +0,  pp.time ASC, pp.game_id ASC";
      break;

    case "tournamentsdesc":
      $query .= " ORDER BY pr.starttime DESC, pr.reservationgroup, pl.id, ps.ordering, pool.ordering, pp.time ASC, pp.game_id ASC";
      break;

    case "placesdesc":
      $query .= " ORDER BY pr.starttime DESC, pr.reservationgroup, pl.id, pr.fieldname + 0, pp.time ASC, pp.game_id ASC";
      break;
      	
    case "onepage":
      $query .= " ORDER BY pr.reservationgroup, pr.starttime, pl.id, pr.fieldname +0, pp.time ASC, pp.game_id ASC";
      break;

    case "time":
      $query .= " ORDER BY pp.time ASC, pr.fieldname +0, game_id ASC";
      break;
      	
    case "timedesc":
      $query .= " ORDER BY pp.time DESC, game_id ASC";
      break;
      	
    case "crossmatch":
      $query .= " ORDER BY homepool.rank ASC, game_id ASC";
      break;
  }

  $result = DBQuery($query);

  return $result;
}

function TimetableGrouping($id, $gamefilter, $timefilter)
{
  //common game query
  $query = "SELECT pool.name AS poolname, ps.name AS seriesname, pr.fieldname, pr.reservationgroup,
			pl.name AS placename
			FROM uo_game pp 
			LEFT JOIN (SELECT COUNT(*) AS goals, game FROM uo_goal GROUP BY game) AS pm ON (pp.game_id=pm.game)
			LEFT JOIN uo_pool pool ON (pool.pool_id=pp.pool) 
			LEFT JOIN uo_series ps ON (pool.series=ps.series_id)
			LEFT JOIN uo_reservation pr ON (pp.reservation=pr.id)
			LEFT JOIN uo_location pl ON (pr.location=pl.id)
			LEFT JOIN uo_team AS home ON (pp.hometeam=home.team_id)
			LEFT JOIN uo_team AS visitor ON (pp.visitorteam=visitor.team_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)";

  switch($gamefilter)
  {
    case "season":
      $query .= " WHERE pp.valid=true AND ps.season='".DBEscapeString($id)."'";
      break;

    case "series":
      $query .= " WHERE pp.valid=true AND ps.series_id='".(int)$id."'";
      break;

    case "pool":
      $query .= " WHERE pp.valid=true AND pp.pool='".(int)$id."'";
      break;

    case "poolgroup":
      //keep pool filter as it is to give better performance for single pool query
      //extra explode needed to make parameters safe
      $pools = explode(",", DBEscapeString($id));
      $query .= " WHERE pp.valid=true AND pp.pool IN(".implode(",",$pools).")";
      break;
      	
    case "team":
      $query .= " WHERE pp.valid=true AND (pp.visitorteam='".(int)$id."' OR pp.hometeam='".(int)$id."')";
      break;
  }

  switch($timefilter)
  {
    case "coming":
      $query .= " AND pp.time IS NOT NULL AND ((pp.homescore IS NULL AND pp.visitorscore IS NULL) OR (pp.hasstarted = 0) OR pp.isongoing=1)";
      break;

    case "past":
      $query .= " AND ((pp.hasstarted >0))";
      break;
      	
    case "played":
      $query .= " AND ((pp.hasstarted >0))";
      break;
      	
    case "ongoing":
      $query .= " AND pp.isongoing=1";
      break;
      	
    case "comingNotToday":
      $query .= " AND pp.time >= Now()";
      break;

    case "pastNotToday":
      $query .= " AND pp.time <= Now()";
      break;
      	
    case "today":
      $query .= " AND DATE_FORMAT(pp.time,'%Y-%m-%d') = DATE_SUB(CURRENT_DATE(), INTERVAL 0 DAY)";
      break;

    case "tomorrow":
      $query .= " AND DATE_FORMAT(pp.time,'%Y-%m-%d') = DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)";
      break;
      	
    case "yesterday":
      $query .= " AND DATE_FORMAT(pp.time,'%Y-%m-%d') = DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY)";
      break;

    case "all":
      break;
      	
    default:
      $query .= " AND DATE_FORMAT(pp.time,'%Y-%m-%d') = '".DBEscapeString($timefilter)."'";
      break;
  }
  $query .= " GROUP BY pr.reservationgroup ORDER BY pp.time ASC, ps.ordering, pr.reservationgroup";

  return DBQueryToArray($query);
}

function TimetableFields($reservationgroup, $season){
  $query = "SELECT COUNT(*) as games
			FROM uo_game pp 
			LEFT JOIN (SELECT COUNT(*) AS goals, game FROM uo_goal GROUP BY game) AS pm ON (pp.game_id=pm.game)
			LEFT JOIN uo_pool pool ON (pool.pool_id=pp.pool) 
			LEFT JOIN uo_series ps ON (pool.series=ps.series_id)
			LEFT JOIN uo_reservation pr ON (pp.reservation=pr.id)";

  $query .= " WHERE pp.valid=true AND ps.season='".DBEscapeString($season)."' AND pr.reservationgroup='".DBEscapeString($reservationgroup)."'";
  $query .= " GROUP BY pr.location, pr.fieldname";
  $result = DBQuery($query);
  return mysqli_num_rows($result);
}

function TimetableTimeslots($reservationgroup, $season){
  $query = "SELECT pp.time
			FROM uo_game pp 
			LEFT JOIN uo_pool pool ON (pool.pool_id=pp.pool) 
			LEFT JOIN uo_series ps ON (pool.series=ps.series_id)
			LEFT JOIN uo_reservation pr ON (pp.reservation=pr.id)";

  $query .= " WHERE pp.valid=true AND ps.season='".DBEscapeString($season)."' AND pr.reservationgroup='".DBEscapeString($reservationgroup)."'";
  $query .= " GROUP BY pp.time";
  return DBQueryToArray($query);
}

function TimetableIntraPoolConflicts($season) {
  $query = "SELECT g1.game_id as game1, g2.game_id as game2, g1.pool as pool1, g2.pool as pool2,  
      g1.hometeam as home1, g1.visitorteam as visitor1, g2.hometeam as home2, g2.visitorteam as visitor2, 
      g1.scheduling_name_home as scheduling_home1, g1.scheduling_name_visitor as scheduling_visitor1, 
      g2.scheduling_name_home as scheduling_home2, g2.scheduling_name_visitor as scheduling_visitor2, 
      g1.reservation as reservation1, g2.reservation as reservation2, g1.time as time1, g2.time as time2, 
      p1.timeslot as slot1, p2.timeslot as slot2, 
      res1.location location1, res1.fieldname as field1, res2.location as location2, res2.fieldname as field2  
      FROM uo_game as g1
      LEFT JOIN uo_game as g2 ON ((g1.hometeam=g2.hometeam OR g1.visitorteam = g2.visitorteam OR g1.hometeam=g2.visitorteam OR g1.visitorteam = g2.hometeam) AND g1.game_id != g2.game_id )
      LEFT JOIN uo_pool as p1 ON (p1.pool_id = g1.pool)
      LEFT JOIN uo_pool as p2 ON (p2.pool_id = g2.pool)
      LEFT JOIN uo_reservation as res1 ON (res1.id = g1.reservation)
      LEFT JOIN uo_reservation as res2 ON (res2.id = g2.reservation)
      LEFT JOIN uo_series as ser1 ON (ser1.series_id = p1.series)
      LEFT JOIN uo_series as ser2 ON (ser2.series_id = p2.series)
      WHERE g1.reservation IS NOT NULL AND g2.reservation IS NOT NULL AND ser1.season = '".$season ."' AND ser2.season = '".$season."' AND g1.time <= g2.time
      ORDER BY time2 ASC, time1 ASC";
  return DBQueryToArray($query);
}

function TimetableInterPoolConflicts($season) {
  $query = "SELECT  g1.game_id as game1, g2.game_id as game2, g1.pool as pool1, g2.pool as pool2,  
      g1.hometeam as home1, g1.visitorteam as visitor1, g2.hometeam as home2, g2.visitorteam as visitor2, 
      g1.scheduling_name_home as scheduling_home1, g1.scheduling_name_visitor as scheduling_visitor1, 
      g2.scheduling_name_home as scheduling_home2, g2.scheduling_name_visitor as scheduling_visitor2, 
      g1.reservation as reservation1, g2.reservation as reservation2, g1.time as time1, g2.time as time2, 
      p1.timeslot as slot1, p2.timeslot as slot2, 
      res1.location location1, res1.fieldname as field1, res2.location as location2, res2.fieldname as field2
      FROM uo_moveteams as mv
      LEFT JOIN uo_game as g1 ON (g1.pool = mv.frompool)
      LEFT JOIN uo_game as g2 ON (g2.pool = mv.topool AND g1.game_id != g2.game_id )
      LEFT JOIN uo_pool as p1 ON (p1.pool_id = g1.pool)
      LEFT JOIN uo_pool as p2 ON (p2.pool_id = g2.pool)
      LEFT JOIN uo_reservation as res1 ON (res1.id = g1.reservation)
      LEFT JOIN uo_reservation as res2 ON (res2.id = g2.reservation)
      LEFT JOIN uo_series as ser1 ON (ser1.series_id = p1.series)
      LEFT JOIN uo_series as ser2 ON (ser2.series_id = p2.series)
      WHERE ser1.season = '".$season."' AND ser2.season = '". $season."'
        AND (g1.hometeam IS NULL OR g1.visitorteam IS NULL OR g2.hometeam IS NULL OR g2.visitorteam IS NULL OR
          (g1.hometeam=g2.hometeam OR g1.visitorteam = g2.visitorteam OR g1.hometeam=g2.visitorteam OR g1.visitorteam = g2.hometeam))
      ORDER BY time2 ASC, time1 ASC";
  return DBQueryToArray($query);
}

function TimeTableMoveTimes($season) {
  $query = sprintf("SELECT * FROM uo_movingtime
            WHERE season = '%s'
            ORDER BY fromlocation, fromfield+0, tolocation, tofield+0", $season);
  
	$result = DBQuery($query);

	$ret = array();
	while ($row = mysqli_fetch_assoc($result)){
		$ret[$row['fromlocation']][$row['fromfield']][$row['tolocation']][$row['tofield']] = $row['time'];
	}
	return $ret;
  }

function TimeTableMoveTime($movetimes, $location1, $field1, $location2, $field2) {
  if (!isset($movetimes[$location1][$field1][$location2][$field2]))
    return 0;
  $time = $movetimes[$location1][$field1][$location2][$field2];
  if (empty($time))
    return 0;
  else
    return $time * 60;
}

function TimeTableSetMoveTimes($season, $times) {
  if (isSuperAdmin() || isSeasonAdmin($season)) {
    for ($from = 0; $from < count($times); $from++) {
      for ($to = 0; $to < count($times); $to++) {
        $query = sprintf(" 
          INSERT INTO uo_movingtime
          (season, fromlocation, fromfield, tolocation, tofield, time) 
          VALUES ('%s', '%d', '%d', '%d', '%d', '%d') ON DUPLICATE KEY UPDATE time='%d'", 
            DBEscapeString($season), 
            (int) $times[$from]['location'], 
            (int) $times[$from]['field'],
            (int) $times[$to]['location'], 
            (int) $times[$to]['field'],
            (int) $times[$from][$to],
            (int) $times[$from][$to]) ;
    DBQuery($query);
      }
    }
  }else {
    die('Insufficient rights to edit moving times');
  }
}

function IsGamesScheduled($id, $gamefilter, $timefilter)
{
  $result = TimetableGames($id, $gamefilter, $timefilter, "");

  return (mysqli_num_rows($result)>0);
}

function TimetableToCsv($season,$separator){

  $query = sprintf("SELECT pp.time AS Time, phome.name AS HomeSchedulingName, pvisitor.name AS AwaySchedulingName,
			home.name AS HomeTeam, visitor.name AS AwayTeam, pp.homescore AS HomeScores, 
			pp.visitorscore AS VisitorScores, pool.name AS Pool, ps.name AS Division, 
			pr.fieldname AS Field, pr.reservationgroup AS ReservationGroup,
			pl.name AS Place, pp.name AS GameName
			FROM uo_game pp 
			LEFT JOIN (SELECT COUNT(*) AS goals, game FROM uo_goal GROUP BY game) AS pm ON (pp.game_id=pm.game)
			LEFT JOIN uo_pool pool ON (pool.pool_id=pp.pool) 
			LEFT JOIN uo_series ps ON (pool.series=ps.series_id)
			LEFT JOIN uo_reservation pr ON (pp.reservation=pr.id)
			LEFT JOIN uo_location pl ON (pr.location=pl.id)
			LEFT JOIN uo_team AS home ON (pp.hometeam=home.team_id)
			LEFT JOIN uo_team AS visitor ON (pp.visitorteam=visitor.team_id)
			LEFT JOIN uo_scheduling_name AS pgame ON (pp.name=pgame.scheduling_id)
			LEFT JOIN uo_scheduling_name AS phome ON (pp.scheduling_name_home=phome.scheduling_id)
			LEFT JOIN uo_scheduling_name AS pvisitor ON (pp.scheduling_name_visitor=pvisitor.scheduling_id)
			WHERE pp.valid=true AND ps.season='%s'
			ORDER BY pr.starttime, pr.reservationgroup, pl.id, pr.fieldname +0, pp.time ASC, pp.game_id ASC",
  DBEscapeString($season));

  // Gets the data from the database
  $result = DBQuery($query);
  return ResultsetToCsv($result, $separator);
}
