<?php
include_once 'localization.php';
include_once '../lib/feed_generator/FeedWriter.php';
include_once '../lib/feed_generator/FeedItem.php';
include_once '../lib/player.functions.php';
include_once '../lib/accreditation.functions.php';

include_once '../lib/season.functions.php';
include_once '../lib/timetable.functions.php';
include_once '../lib/team.functions.php';
include_once '../lib/series.functions.php';
include_once '../lib/common.functions.php';
include_once '../lib/game.functions.php';

$type=RSS2;
$max_items = 25;

$feedtype="all";
$baseurl = GetURLBase();

if(iget("feed")) {
  $feedtype = iget("feed");
}
if(iget("id1")) {
  $id1 = iget("id1");
} else {
  $id1 = CurrentSeason();
}

$id2 = iget("id2");


//Creating an instance of FeedWriter class.
//The constant RSS2 is passed to mention the version
$feed = new FeedWriter(RSS2);
$feed->setChannelElement('language', GetW3CLocale());
$feed->setChannelElement('pubDate', date(DATE_RSS, time()));

switch($feedtype){
  case "gameresults":
    //$cutpos = strrpos($baseurl, "/");
    //$path = substr($baseurl,0,$cutpos); //remove ext

    //series
    if(!empty($id2)){
      $feed->setTitle(_("Ultimate results").": ".SeasonName($id1) ." ".SeriesName($id2));
      $feed->setLink($baseurl ."/?view=played");
      $feed->setDescription(SeasonName($id1) ." ".SeriesName($id2));
      $games = TimetableGames($id2, "series", "past", "timedesc");
      //season
    }else{
      $feed->setTitle(_("Ultimate results").": ".SeasonName($id1));
      $feed->setLink($baseurl ."/?view=played");
      $feed->setDescription(SeasonName($id1));
      $games = TimetableGames($id1, "season", "past", "timedesc");
    }
    $i=0;

    while(($game = mysqli_fetch_assoc($games)) && $i<$max_items){
      	
      if(GameHasStarted($game)){
        $newItem = $feed->createNewItem();
        $newItem->setGuid($game['game_id']);
        $title = TeamName($game['hometeam']);
        $title .= " - ";
        $title .= TeamName($game['visitorteam']);
        $title .= " ";
        $title .= intval($game['homescore']);
        $title .= " - ";
        $title .= intval($game['visitorscore']);
        	
        $newItem->setTitle($title);
        $newItem->setLink($baseurl ."/?view=gameplay&game=". $game['game_id']);

        $desc = U_($game['seriesname']);
        $desc .= " ";
        $desc .= $game['poolname'];
        $newItem->setDescription($desc);

        //Now add the feed item
        $feed->addItem($newItem);
        $i++;
      }
    }
    break;

  case "game":
    //$cutpos = strrpos($baseurl, "/");
    //$path = substr($baseurl,0,$cutpos); //remove ext

    $game = GameInfo($id1);
    $goals = GameGoals($id1);
    $gameevents = GameEvents($id1);
    $mediaevents = GameMediaEvents($id1);

    $feed->setTitle(_("Ultimate game").": ". $game['hometeamname'] ." - ".$game['visitorteamname']);
    $feed->setLink($baseurl ."/?view=gameplay&game=$id1");
    $feed->setDescription($game['seriesname'] .", ".$game['poolname']);

    $prevgoal = 0;
    $items = array();

    while($goal = mysqli_fetch_assoc($goals)){
      $newItem = $feed->createNewItem();
      $newItem->setGuid($goal['time']);

      $title = $game['hometeamname'];
      $title .= " - ";
      $title .= $game['visitorteamname'];
      $title .= " ";
      $title .= intval($goal['homescore']);
      $title .= " - ";
      $title .= intval($goal['visitorscore']);

      if (intval($goal['iscallahan'])){
        $pass = "xx";
      }else{
        $pass = $goal['assistfirstname'] ." ". $goal['assistlastname'];
      }
      	
      $scorer = $goal['scorerfirstname'] ." ". $goal['scorerlastname'];
      	
      $desc = "[".SecToMin($goal['time'])."] ";
      if(!empty($pass) || !empty($scorer)){
        $desc .= $pass ." --> ". $scorer;
      }

      //gameevents
      foreach($gameevents as $event){
        if((intval($event['time']) >= $prevgoal) &&
        (intval($event['time']) < intval($goal['time'])))
        {
          if($event['type'] == "timeout")
          $gameevent = _("Time-out");
          elseif($event['type'] == "turnover")
          $gameevent = _("Turnover");
          elseif($event['type'] == "offence")
          $gameevent = _("Offence");

          $desc .= "<br/>[".SecToMin($event['time'])."] ";
          	
          if(intval($event['ishome'])>0)
          $desc .=  $gameevent ." ".$game['hometeamname'];
          else
          $desc .= $gameevent ." ".$game['visitorteamname'];
        }
      }
      	
      $newItem->setTitle($title);
      $newItem->setLink($baseurl ."/?view=gameplay&game=$id1");
      $newItem->setDescription($desc);

      $items[] = $newItem;
      //$feed->addItem($newItem);

      $prevgoal = intval($goal['time']);
    }


    //gameevents after last goal
    $desc = "";
    foreach($gameevents as $event){
      if((intval($event['time']) >= $prevgoal))
      {
        if($event['type'] == "timeout")
        $gameevent = _("Time-out");
        elseif($event['type'] == "turnover")
        $gameevent = _("Turnover");
        elseif($event['type'] == "offence")
        $gameevent = _("Offence");

        if(!empty($desc)){$desc .= "<br/>";}
        $desc .= "[".SecToMin($event['time'])."] ";

        if(intval($event['ishome'])>0)
        $desc .=  $gameevent ." ".$game['hometeamname'];
        else
        $desc .= $gameevent ." ".$game['visitorteamname'];
      }
    }
    if(!empty($desc)){
      $newItem = $feed->createNewItem();
      $newItem->setTitle(_("Latest events"));
      $newItem->setLink($baseurl ."/?view=gameplay&game=$id1");
      $newItem->setDescription($desc);
      $items[] = $newItem;
    }

    $items = array_reverse($items);
    foreach($items as $item){
      $feed->addItem($item);
    }

    break;

  case "all":
    //$cutpos = strrpos($baseurl, "/");
    //$path = substr($baseurl,0,$cutpos); //remove ext

    $feed->setTitle(_("Ultimate results"));
    $feed->setLink($baseurl ."/?view=played");
    $feed->setDescription(_("Ultimate results"));
    $games = GameAll(20);

    while(($game = mysqli_fetch_assoc($games))){
      	
      if(GameHasStarted($game)){
        $newItem = $feed->createNewItem();
        $newItem->setGuid($game['game_id']);
        $title = TeamName($game['hometeam']);
        $title .= " - ";
        $title .= TeamName($game['visitorteam']);
        $title .= " ";
        $title .= intval($game['homescore']);
        $title .= " - ";
        $title .= intval($game['visitorscore']);
        	
        $newItem->setTitle($title);
        $newItem->setLink($baseurl ."/?view=gameplay&game=". $game['game_id']);

        $desc = U_($game['seasonname']);
        $desc .= ": ";
        $desc .= U_($game['seriesname']);
        if(!empty($game['gamename'])){
          $desc .= " - ";
          $desc .= U_($game['gamename']);
        }else{
          $desc .= " - ";
          $desc .= U_($game['poolname']);
        }
        $newItem->setDescription($desc);

        //Now add the feed item
        $feed->addItem($newItem);
      }
    }
    break;
}

CloseConnection();

//OK. Everything is done. Now genarate the feed.
$feed->genarateFeed();

?>
