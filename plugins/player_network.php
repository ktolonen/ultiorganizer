<?php

include_once __DIR__ . '/auth.php';
pluginRequireAdmin(__FILE__);

ob_start();
?>
<!--
[CLASSIFICATION]
category=database
type=generator
format=any
security=superadmin
customization=all

[DESCRIPTION]
title = "Player network export"
description = "Build a standalone interactive teammate network page from all roster history."
-->
<?php
ob_end_clean();

if (!isSuperAdmin()) {
    die('Insufficient user rights');
}

define('PLAYER_NETWORK_DEFAULT_MIN_SHARED', 1);
define('PLAYER_NETWORK_THRESHOLDS', '1,2,3,4,5,6,8,10');
define('PLAYER_NETWORK_EXPORT_FILE', 'player-network.html');

/**
 * Database-wide counts shown on the analyze screen.
 *
 * @return array
 */
function PlayerNetworkPluginSummary()
{
    $query = "SELECT
		(SELECT COUNT(*) FROM uo_season) AS events,
		(SELECT COUNT(*) FROM uo_team) AS teams,
		(SELECT COUNT(*) FROM uo_team WHERE valid=1) AS teams_valid,
		(SELECT COUNT(*) FROM uo_player_profile) AS profiles,
		(SELECT COUNT(*) FROM uo_player p
			INNER JOIN uo_team t ON (t.team_id=p.team AND t.valid=1)
			WHERE p.profile_id>0) AS roster_rows";

    $row = DBQueryToRow($query);
    return $row ? $row : [];
}

/**
 * All teammate pairs with the number of distinct teams they shared.
 *
 * Two roster rows on the same valid team make a pair. Comparing profile ids with
 * ">" yields every pair exactly once and drops self-pairs, which matter because
 * one profile can hold several roster rows on the same team.
 *
 * Deliberately uncached: this returns tens of thousands of rows and has exactly
 * one consumer, so letting DBQueryToArray() put it through the persistent cache
 * would serialise the whole result to disk on every GET of the analyze page for
 * no reuse.
 *
 * @param int $minShared only return pairs sharing at least this many teams
 * @return array
 */
function PlayerNetworkPluginPairs($minShared = 1)
{
    $query = sprintf(
        "SELECT a.profile_id AS p1, b.profile_id AS p2, COUNT(DISTINCT a.team) AS w
		FROM uo_player a
		INNER JOIN uo_player b ON (b.team=a.team AND b.profile_id>a.profile_id)
		INNER JOIN uo_team t ON (t.team_id=a.team AND t.valid=1)
		WHERE a.profile_id>0 AND b.profile_id>0
		GROUP BY a.profile_id, b.profile_id
		HAVING w>=%d",
        (int) $minShared,
    );

    return DBQueryToArrayUncached($query);
}

/**
 * Display names for the given profile ids.
 *
 * A profile row can carry empty names, so fall back to the latest roster row and
 * finally to the profile id itself.
 *
 * @param array $profileIds
 * @return array profile id => name
 */
function PlayerNetworkPluginNames($profileIds)
{
    if (empty($profileIds)) {
        return [];
    }

    $ids = [];
    foreach ($profileIds as $profileId) {
        $profileId = (int) $profileId;
        if ($profileId > 0) {
            $ids[$profileId] = $profileId;
        }
    }
    if (empty($ids)) {
        return [];
    }

    $names = [];
    foreach (array_chunk(array_values($ids), 5000) as $chunk) {
        $query = sprintf(
            "SELECT pr.profile_id,
				TRIM(CONCAT(COALESCE(pr.firstname,''), ' ', COALESCE(pr.lastname,''))) AS profilename,
				TRIM(CONCAT(COALESCE(p.firstname,''), ' ', COALESCE(p.lastname,''))) AS rostername
			FROM uo_player_profile pr
			LEFT JOIN uo_player p ON (p.player_id=(
				SELECT MAX(player_id) FROM uo_player WHERE profile_id=pr.profile_id))
			WHERE pr.profile_id IN (%s)",
            implode(",", $chunk),
        );

        foreach (DBQueryToArray($query) as $row) {
            $name = trim((string) $row['profilename']);
            if ($name === "") {
                $name = trim((string) $row['rostername']);
            }
            if ($name === "") {
                $name = "#" . $row['profile_id'];
            }
            $names[(int) $row['profile_id']] = $name;
        }
    }

    return $names;
}

/**
 * Every profile that holds at least one roster row on a valid team.
 *
 * @return array
 */
function PlayerNetworkPluginRosteredProfileIds()
{
    $query = "SELECT DISTINCT p.profile_id
		FROM uo_player p
		INNER JOIN uo_team t ON (t.team_id=p.team AND t.valid=1)
		WHERE p.profile_id>0";

    $ids = [];
    foreach (DBQueryToArray($query) as $row) {
        $ids[] = (int) $row['profile_id'];
    }

    return $ids;
}

/**
 * Edge and node counts for each candidate threshold.
 *
 * Counted from a single pass over all pairs rather than one query per threshold.
 *
 * @param array $pairs
 * @param array $thresholds
 * @return array
 */
function PlayerNetworkPluginThresholdStats($pairs, $thresholds)
{
    $stats = [];
    foreach ($thresholds as $threshold) {
        $stats[$threshold] = ["edges" => 0, "nodes" => []];
    }

    foreach ($pairs as $pair) {
        $weight = (int) $pair['w'];
        foreach ($thresholds as $threshold) {
            if ($weight < $threshold) {
                continue;
            }
            $stats[$threshold]['edges']++;
            $stats[$threshold]['nodes'][(int) $pair['p1']] = true;
            $stats[$threshold]['nodes'][(int) $pair['p2']] = true;
        }
    }

    $rows = [];
    foreach ($thresholds as $threshold) {
        $nodes = count($stats[$threshold]['nodes']);
        $edges = $stats[$threshold]['edges'];
        $rows[] = [
            "threshold" => $threshold,
            "edges" => $edges,
            "nodes" => $nodes,
            "degree" => $nodes > 0 ? (2 * $edges / $nodes) : 0,
        ];
    }

    return $rows;
}

/**
 * The most connected players at a given threshold.
 *
 * Counts distinct teammates, not shared teams, so this answers "who has played
 * with the most different people" rather than "who has the longest career".
 *
 * @param array $pairs output of PlayerNetworkPluginPairs()
 * @param int $minShared
 * @param int $limit
 * @return array list of ["profile_id" => int, "connections" => int, "name" => string]
 */
function PlayerNetworkPluginTopConnected($pairs, $minShared, $limit = 20)
{
    $degree = [];
    foreach ($pairs as $pair) {
        if ((int) $pair['w'] < $minShared) {
            continue;
        }
        foreach ([(int) $pair['p1'], (int) $pair['p2']] as $profileId) {
            if (!isset($degree[$profileId])) {
                $degree[$profileId] = 0;
            }
            $degree[$profileId]++;
        }
    }

    arsort($degree);
    $top = array_slice($degree, 0, $limit, true);
    $names = PlayerNetworkPluginNames(array_keys($top));

    $rows = [];
    foreach ($top as $profileId => $connections) {
        $rows[] = [
            "profile_id" => $profileId,
            "connections" => $connections,
            "name" => isset($names[$profileId]) ? $names[$profileId] : ("#" . $profileId),
        ];
    }

    return $rows;
}

/**
 * Optional colour grouping for the exported map.
 *
 * "gender" reads uo_player_profile.gender, which is frequently unset - those
 * players land in an explicit unknown group rather than being silently folded
 * into one of the real ones.
 *
 * "division" derives the group from the divisions a player's teams played in,
 * which is recorded for every team and so has full coverage. Junior and master
 * variants merge into their parent division: the palette only supports three
 * categories that stay distinguishable under colour-vision deficiency, and the
 * open/women/mixed split is the meaningful one.
 *
 * @param string $mode "gender", "division" or "" for no grouping
 * @param array $nodeIds profile ids in node order
 * @return array|null ["labels" => [...], "colors" => [...], "of" => [...]]
 */
function PlayerNetworkPluginGroups($mode, $nodeIds)
{
    if ($mode !== "gender" && $mode !== "division" || empty($nodeIds)) {
        return null;
    }

    $assigned = [];

    if ($mode === "gender") {
        $labels = ["Men", "Women", "Not recorded"];
        $colors = ["#3987e5", "#d95926", "#5c6a80"];
        foreach (array_chunk($nodeIds, 5000) as $chunk) {
            $query = sprintf(
                "SELECT profile_id, gender FROM uo_player_profile WHERE profile_id IN (%s)",
                implode(",", array_map('intval', $chunk)),
            );
            foreach (DBQueryToArray($query) as $row) {
                $gender = strtoupper(trim((string) $row['gender']));
                $assigned[(int) $row['profile_id']] = $gender === "M" ? 0 : ($gender === "F" ? 1 : 2);
            }
        }
    } else {
        $labels = ["Open", "Women", "Mixed"];
        $colors = ["#3987e5", "#d95926", "#199e70"];
        $tally = [];
        $query = "SELECT p.profile_id, ser.type, COUNT(*) AS teams
			FROM uo_player p
			INNER JOIN uo_team t ON (t.team_id=p.team AND t.valid=1)
			INNER JOIN uo_series ser ON (ser.series_id=t.series)
			WHERE p.profile_id>0
			GROUP BY p.profile_id, ser.type";
        foreach (DBQueryToArrayUncached($query) as $row) {
            $type = strtolower((string) $row['type']);
            $group = 0;
            if (strpos($type, "women") !== false) {
                $group = 1;
            } elseif (strpos($type, "mixed") !== false) {
                $group = 2;
            }
            $profileId = (int) $row['profile_id'];
            if (!isset($tally[$profileId])) {
                $tally[$profileId] = [0, 0, 0];
            }
            $tally[$profileId][$group] += (int) $row['teams'];
        }
        foreach ($tally as $profileId => $counts) {
            $best = 0;
            for ($i = 1; $i < 3; $i++) {
                if ($counts[$i] > $counts[$best]) {
                    $best = $i;
                }
            }
            $assigned[$profileId] = $best;
        }
    }

    $of = [];
    foreach ($nodeIds as $profileId) {
        $of[] = isset($assigned[$profileId]) ? $assigned[$profileId] : (count($labels) - 1);
    }

    return ["labels" => $labels, "colors" => $colors, "of" => $of];
}

/**
 * Assemble the graph payload handed to the exported page.
 *
 * @param int $minShared
 * @param bool $includeIsolated
 * @param string $groupMode "", "gender" or "division"
 * @return array
 */
function PlayerNetworkPluginBuildGraph($minShared, $includeIsolated, $groupMode = "")
{
    $pairs = PlayerNetworkPluginPairs($minShared);

    $index = [];
    $nodeIds = [];
    foreach ($pairs as $pair) {
        foreach ([(int) $pair['p1'], (int) $pair['p2']] as $profileId) {
            if (!isset($index[$profileId])) {
                $index[$profileId] = count($nodeIds);
                $nodeIds[] = $profileId;
            }
        }
    }

    $nodeCount = count($nodeIds);
    $degree = array_fill(0, max($nodeCount, 1), 0);
    $edges = [];
    foreach ($pairs as $pair) {
        $a = $index[(int) $pair['p1']];
        $b = $index[(int) $pair['p2']];
        $edges[] = $a;
        $edges[] = $b;
        $edges[] = (int) $pair['w'];
        $degree[$a]++;
        $degree[$b]++;
    }
    if ($nodeCount === 0) {
        $degree = [];
    }

    $names = PlayerNetworkPluginNames($nodeIds);
    $nodeNames = [];
    foreach ($nodeIds as $profileId) {
        $nodeNames[] = isset($names[$profileId]) ? $names[$profileId] : ("#" . $profileId);
    }

    $isolated = [];
    if ($includeIsolated) {
        $missing = [];
        foreach (PlayerNetworkPluginRosteredProfileIds() as $profileId) {
            if (!isset($index[$profileId])) {
                $missing[] = $profileId;
            }
        }
        foreach (PlayerNetworkPluginNames($missing) as $name) {
            $isolated[] = $name;
        }
        sort($isolated);
    }

    return [
        "nodeIds" => $nodeIds,
        "names" => $nodeNames,
        "degree" => array_values($degree),
        "edges" => $edges,
        "isolated" => $isolated,
        "groups" => PlayerNetworkPluginGroups($groupMode, $nodeIds),
    ];
}

/**
 * Stylesheet for the exported page.
 *
 * The export is a standalone artifact rather than an application page, so it
 * deliberately carries its own styles instead of the skin colour tokens.
 *
 * @return string
 */
function PlayerNetworkPluginExportCss()
{
    return <<<'CSS'
* { box-sizing: border-box; }
html, body { margin: 0; height: 100%; }
body {
  background: #10151f;
  color: #e6ecf5;
  font: 13px/1.45 "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  overflow: hidden;
}
#stage { position: absolute; inset: 0; }
#net { display: block; width: 100%; height: 100%; cursor: grab; }
#net.dragging { cursor: grabbing; }
#panel {
  position: absolute; top: 14px; left: 14px; width: 290px; max-width: calc(100vw - 28px);
  max-height: calc(100vh - 28px); overflow-y: auto;
  background: rgba(19, 26, 38, 0.94); border: 1px solid #2c3a52; border-radius: 8px;
  padding: 12px 13px; box-shadow: 0 8px 26px rgba(0, 0, 0, 0.45);
}
#panel h1 { font-size: 14px; margin: 0 0 2px; letter-spacing: 0.02em; }
#meta { font-size: 11px; color: #8b9ab3; margin: 0 0 10px; }
#q {
  width: 100%; padding: 7px 9px; border-radius: 5px; border: 1px solid #33415c;
  background: #0c1119; color: #e6ecf5; font-size: 13px;
}
#q:focus { outline: none; border-color: #5b8fd6; }
#hits { list-style: none; margin: 6px 0 0; padding: 0; max-height: 232px; overflow-y: auto; }
#hits li {
  padding: 5px 8px; border-radius: 4px; cursor: pointer;
  display: flex; justify-content: space-between; gap: 8px;
}
#hits li:hover { background: #223047; }
#hits li.off { color: #77859c; cursor: default; }
#hits li.off:hover { background: transparent; }
#hits .n { color: #7f9dc9; font-variant-numeric: tabular-nums; }
#note { font-size: 11px; color: #8b9ab3; margin: 9px 0 0; }
#detail { margin: 10px 0 0; font-size: 12px; display: none; }
#detail b { color: #cfe0f7; }
#panel h2 {
  font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em;
  color: #7f8fa8; margin: 12px 0 5px; font-weight: 600;
}
#top, #key { list-style: none; margin: 0; padding: 0; }
#keywrap { display: none; }
#key li { display: flex; align-items: center; gap: 8px; padding: 2px 0; color: #9aa9c0; }
#key .sw { width: 11px; height: 11px; border-radius: 50%; flex: 0 0 11px; }
#key .c { color: #66748c; margin-left: auto; font-variant-numeric: tabular-nums; }
#top li {
  display: flex; justify-content: space-between; gap: 8px;
  padding: 3px 8px; border-radius: 4px; cursor: pointer;
}
#top li:hover { background: #223047; }
#top .n { color: #7f9dc9; font-variant-numeric: tabular-nums; }
#top .r { color: #66748c; width: 17px; display: inline-block; }
#zoom { position: absolute; right: 14px; bottom: 14px; display: flex; gap: 6px; }
#zoom button {
  width: 32px; height: 32px; border-radius: 5px; border: 1px solid #33415c;
  background: rgba(19, 26, 38, 0.94); color: #e6ecf5; font-size: 15px; cursor: pointer;
}
#zoom button:hover { border-color: #5b8fd6; }
#zoom button.wide { width: auto; padding: 0 11px; font-size: 12px; }
#tip {
  position: absolute; pointer-events: none; display: none; z-index: 5;
  background: rgba(10, 14, 22, 0.95); border: 1px solid #33415c; border-radius: 5px;
  padding: 5px 8px; font-size: 12px; white-space: nowrap;
}
#boot {
  position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
  flex-direction: column; gap: 12px; background: #10151f; z-index: 10;
}
#bar { width: 260px; height: 4px; background: #222c3f; border-radius: 2px; overflow: hidden; }
#bar i { display: block; height: 100%; width: 0; background: #5b8fd6; }
@media (max-width: 620px) {
  /* The panel is tall enough to bury the map on a phone, so cap it and let it
     scroll rather than letting it own the whole screen. */
  #panel { width: auto; right: 14px; max-height: 48vh; }
  #hits { max-height: 150px; }
}
CSS;
}

/**
 * Renderer for the exported page.
 *
 * @return string
 */
function PlayerNetworkPluginExportJs()
{
    return <<<'JS'
(function () {
  "use strict";

  var D = window.UO_NETWORK;
  var TAU = Math.PI * 2;
  var GOLDEN = 2.399963229728653;

  var names = D.names;
  var deg = D.degree;
  var E = D.edges;
  var n = names.length;
  var m = E.length / 3;

  var px = new Float64Array(n);
  var py = new Float64Array(n);
  var comp = new Int32Array(n);
  var compCount = 0;
  var mainComp = 0;
  var anchorX = null, anchorY = null;

  var canvas = document.getElementById("net");
  var ctx = canvas.getContext("2d");
  var tip = document.getElementById("tip");
  var boot = document.getElementById("boot");
  var bar = document.getElementById("bar").firstChild;
  var bootText = document.getElementById("boottext");
  var detail = document.getElementById("detail");

  var W = 0, H = 0, dpr = 1;
  var cam = { x: 0, y: 0, s: 1 };
  var fly = null;
  var sel = -1, hov = -1, selAt = 0;
  var dragging = false, dragMoved = false, lastX = 0, lastY = 0;
  var pinch = 0;

  /* ---- adjacency (compressed rows, built once) ---- */

  var adjStart = new Int32Array(n + 1);
  var adjNode = new Int32Array(2 * m);
  var adjW = new Int32Array(2 * m);
  (function buildAdjacency() {
    var i, a, b;
    for (i = 0; i < m; i++) {
      adjStart[E[i * 3] + 1]++;
      adjStart[E[i * 3 + 1] + 1]++;
    }
    for (i = 0; i < n; i++) { adjStart[i + 1] += adjStart[i]; }
    var fill = new Int32Array(n);
    for (i = 0; i < m; i++) {
      a = E[i * 3]; b = E[i * 3 + 1];
      adjNode[adjStart[a] + fill[a]] = b;
      adjW[adjStart[a] + fill[a]] = E[i * 3 + 2];
      fill[a]++;
      adjNode[adjStart[b] + fill[b]] = a;
      adjW[adjStart[b] + fill[b]] = E[i * 3 + 2];
      fill[b]++;
    }
  })();

  /* ---- connected components ---- */

  function findComponents() {
    var i, j, head, node, sizes = [];
    for (i = 0; i < n; i++) { comp[i] = -1; }
    var queue = new Int32Array(n);
    for (i = 0; i < n; i++) {
      if (comp[i] !== -1) { continue; }
      var size = 0;
      head = 0;
      queue[0] = i;
      var tail = 1;
      comp[i] = compCount;
      while (head < tail) {
        node = queue[head++];
        size++;
        for (j = adjStart[node]; j < adjStart[node + 1]; j++) {
          if (comp[adjNode[j]] === -1) {
            comp[adjNode[j]] = compCount;
            queue[tail++] = adjNode[j];
          }
        }
      }
      if (sizes.length === 0 || size > sizes[mainComp]) { mainComp = compCount; }
      sizes.push(size);
      compCount++;
    }
    return sizes;
  }

  /* Components repel each other to infinity without their own anchor, so give
     each one a spot on a golden-angle spiral. The spiral radius follows the
     square root of the accumulated area, so satellites tile snugly around the
     main mass; spacing by each component's own radius instead pushes the
     outermost ones kilometres away and collapses the fitted view to a dot. */
  function placeAnchors(sizes, k) {
    var order = [];
    var i;
    for (i = 0; i < sizes.length; i++) { order.push(i); }
    order.sort(function (a, b) { return sizes[b] - sizes[a]; });
    anchorX = new Float64Array(sizes.length);
    anchorY = new Float64Array(sizes.length);
    var area = sizes[order[0]] * k * k * 2.2;
    for (i = 1; i < order.length; i++) {
      var c = order[i];
      area += sizes[c] * k * k * 9;
      var r = Math.sqrt(area / Math.PI);
      anchorX[c] = r * Math.cos(i * GOLDEN);
      anchorY[c] = r * Math.sin(i * GOLDEN);
    }
  }

  /* ---- layout: Fruchterman-Reingold, uniform grid for repulsion ---- */

  var K = 52;
  var ITER = 380;
  var temp = 0;
  var iter = 0;
  var seenIdx = null;

  function initLayout() {
    var sizes = findComponents();
    placeAnchors(sizes, K);
    seenIdx = new Int32Array(compCount);
    var i;
    for (i = 0; i < n; i++) {
      var c = comp[i];
      var idx = seenIdx[c]++;
      /* Golden-angle spiral, not a circle: symmetric starts under symmetric
         forces park the layout in a symmetric local minimum. */
      var ang = idx * GOLDEN;
      var rad = K * 0.62 * Math.sqrt(idx + 0.5);
      px[i] = anchorX[c] + rad * Math.cos(ang);
      py[i] = anchorY[c] + rad * Math.sin(ang);
    }
    temp = Math.sqrt(n) * K * 0.11;
  }

  var dx = new Float64Array(n);
  var dy = new Float64Array(n);

  function stepLayout() {
    var i, j, e, a, b, ox, oy, d2, d, f;
    /* Repulsion is cut off at the cell neighbourhood, so a cell that is small
       relative to K lets a dense graph implode: nodes stop feeling anything
       beyond their immediate surroundings. */
    var cell = K * 1.7;
    var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    for (i = 0; i < n; i++) {
      dx[i] = 0; dy[i] = 0;
      if (px[i] < minX) { minX = px[i]; }
      if (py[i] < minY) { minY = py[i]; }
      if (px[i] > maxX) { maxX = px[i]; }
      if (py[i] > maxY) { maxY = py[i]; }
    }
    var gw = Math.max(1, Math.ceil((maxX - minX) / cell) + 1);
    var gh = Math.max(1, Math.ceil((maxY - minY) / cell) + 1);
    /* A stray far-flung node could otherwise blow the bucket array up; widen the
       cell until the grid is bounded rather than clamping the dimensions, which
       would silently mismatch cell size and grid extent. */
    while (gw * gh > 1000000) {
      cell *= 2;
      gw = Math.max(1, Math.ceil((maxX - minX) / cell) + 1);
      gh = Math.max(1, Math.ceil((maxY - minY) / cell) + 1);
    }

    var head = new Int32Array(gw * gh).fill(-1);
    var next = new Int32Array(n);
    var cx = new Int32Array(n);
    var cy = new Int32Array(n);
    for (i = 0; i < n; i++) {
      cx[i] = Math.min(gw - 1, Math.floor((px[i] - minX) / cell));
      cy[i] = Math.min(gh - 1, Math.floor((py[i] - minY) / cell));
      var slot = cy[i] * gw + cx[i];
      next[i] = head[slot];
      head[slot] = i;
    }

    /* Repulsion within the 3x3 cell neighbourhood. The cutoff must be exactly
       one cell: that is the largest radius the neighbourhood is guaranteed to
       cover in every direction, so the force field stays circular. A larger
       cutoff makes it effectively square and etches a visible lattice into
       dense regions. */
    var cutoff = cell;
    for (i = 0; i < n; i++) {
      var gx0 = Math.max(0, cx[i] - 1), gx1 = Math.min(gw - 1, cx[i] + 1);
      var gy0 = Math.max(0, cy[i] - 1), gy1 = Math.min(gh - 1, cy[i] + 1);
      for (var yy = gy0; yy <= gy1; yy++) {
        for (var xx = gx0; xx <= gx1; xx++) {
          for (j = head[yy * gw + xx]; j !== -1; j = next[j]) {
            if (j === i) { continue; }
            ox = px[i] - px[j];
            oy = py[i] - py[j];
            d2 = ox * ox + oy * oy;
            if (d2 === 0) { ox = (i - j) * 0.01; oy = 0.01; d2 = ox * ox + oy * oy; }
            if (d2 > cutoff * cutoff) { continue; }
            d = Math.sqrt(d2);
            f = (K * K) / d;
            dx[i] += (ox / d) * f;
            dy[i] += (oy / d) * f;
          }
        }
      }
    }

    /* Attraction along edges, damped by log weight so a 69-team pair does not
       collapse onto its partner, and divided by the endpoints' degree. That
       degree term is what stops an average-degree-26 graph from balling up:
       without it every hub drags its whole neighbourhood into one blob. */
    for (e = 0; e < m; e++) {
      a = E[e * 3]; b = E[e * 3 + 1];
      ox = px[a] - px[b];
      oy = py[a] - py[b];
      d = Math.sqrt(ox * ox + oy * oy) || 0.01;
      f = ((d * d) / K) * (0.5 + Math.log(1 + E[e * 3 + 2]) * 0.30);
      var fx = (ox / d) * f, fy = (oy / d) * f;
      dx[a] -= fx / (1 + Math.log(1 + deg[a]));
      dy[a] -= fy / (1 + Math.log(1 + deg[a]));
      dx[b] += fx / (1 + Math.log(1 + deg[b]));
      dy[b] += fy / (1 + Math.log(1 + deg[b]));
    }

    /* gravity toward the component anchor */
    for (i = 0; i < n; i++) {
      dx[i] += (anchorX[comp[i]] - px[i]) * 0.009;
      dy[i] += (anchorY[comp[i]] - py[i]) * 0.009;
    }

    for (i = 0; i < n; i++) {
      d = Math.sqrt(dx[i] * dx[i] + dy[i] * dy[i]);
      if (d > 0.0001) {
        var lim = Math.min(d, temp);
        px[i] += (dx[i] / d) * lim;
        py[i] += (dy[i] / d) * lim;
      }
    }
    temp *= 0.985;
  }

  /* ---- position cache ---- */

  var cacheKey = "uo-playernetwork-" + D.meta.hash;

  function loadCache() {
    try {
      var raw = window.localStorage.getItem(cacheKey);
      if (!raw) { return false; }
      var v = JSON.parse(raw);
      if (!v || v.length !== n * 2) { return false; }
      for (var i = 0; i < n; i++) { px[i] = v[i * 2]; py[i] = v[i * 2 + 1]; }
      return true;
    } catch (err) {
      return false;
    }
  }

  function saveCache() {
    try {
      var v = new Array(n * 2);
      for (var i = 0; i < n; i++) {
        v[i * 2] = Math.round(px[i] * 10) / 10;
        v[i * 2 + 1] = Math.round(py[i] * 10) / 10;
      }
      window.localStorage.setItem(cacheKey, JSON.stringify(v));
    } catch (err) { /* quota or private mode: positions simply recompute */ }
  }

  /* ---- camera ---- */

  /* Frame the largest component. Fitting everything lets a few far-flung
     two-player components set the scale and shrink the main mass to a dot;
     the satellites stay reachable by panning out or by searching. */
  function fitView() {
    if (n === 0) { return; }
    var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    for (var i = 0; i < n; i++) {
      if (comp[i] !== mainComp) { continue; }
      if (px[i] < minX) { minX = px[i]; }
      if (py[i] < minY) { minY = py[i]; }
      if (px[i] > maxX) { maxX = px[i]; }
      if (py[i] > maxY) { maxY = py[i]; }
    }
    if (!isFinite(minX)) { minX = minY = -100; maxX = maxY = 100; }
    cam.x = (minX + maxX) / 2;
    cam.y = (minY + maxY) / 2;
    cam.s = Math.min(W / (maxX - minX + 120), H / (maxY - minY + 120));
    if (!isFinite(cam.s) || cam.s <= 0) { cam.s = 1; }
  }

  function sx(wx) { return (wx - cam.x) * cam.s + W / 2; }
  function sy(wy) { return (wy - cam.y) * cam.s + H / 2; }
  function wx(x) { return (x - W / 2) / cam.s + cam.x; }
  function wy(y) { return (y - H / 2) / cam.s + cam.y; }

  function flyTo(i) {
    fly = { x0: cam.x, y0: cam.y, s0: cam.s, x1: px[i], y1: py[i], s1: Math.max(cam.s, 1.6), t0: now() };
  }

  function now() { return (window.performance && performance.now) ? performance.now() : Date.now(); }

  /* ---- rendering ---- */

  function resize() {
    dpr = window.devicePixelRatio || 1;
    W = canvas.clientWidth;
    H = canvas.clientHeight;
    canvas.width = Math.round(W * dpr);
    canvas.height = Math.round(H * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }

  function radius(i) { return 1.8 + Math.sqrt(deg[i]) * 0.62; }

  /* Ungrouped exports keep the original single blue; a grouped one swaps in the
     group palette. Either way the draw loop below is the same code path. */
  var groupOf = D.groups ? D.groups.of : null;
  var fills = D.groups ? D.groups.colors : ["#9dc0ea"];

  function draw() {
    var i, j, focus = sel >= 0 ? sel : hov;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.fillStyle = "#10151f";
    ctx.fillRect(0, 0, W, H);

    /* Base edges as one path at low alpha: overlapping density reads as shading
       instead of a solid mass, and 20k+ segments still cost a single stroke. */
    ctx.globalAlpha = focus >= 0 ? 0.05 : 0.13;
    ctx.strokeStyle = "#6f93c4";
    ctx.lineWidth = 1;
    ctx.beginPath();
    for (i = 0; i < m; i++) {
      var a = E[i * 3], b = E[i * 3 + 1];
      var ax = sx(px[a]), ay = sy(py[a]), bx = sx(px[b]), by = sy(py[b]);
      if ((ax < 0 && bx < 0) || (ax > W && bx > W) || (ay < 0 && by < 0) || (ay > H && by > H)) { continue; }
      ctx.moveTo(ax, ay);
      ctx.lineTo(bx, by);
    }
    ctx.stroke();

    /* Base nodes: one path per colour, so an ungrouped map is still a single
       fill and a grouped one costs one fill per group. */
    ctx.globalAlpha = focus >= 0 ? 0.3 : 1;
    var scale = Math.min(1.9, Math.max(0.55, cam.s));
    for (var g = 0; g < fills.length; g++) {
      ctx.fillStyle = fills[g];
      ctx.beginPath();
      for (i = 0; i < n; i++) {
        if (groupOf && groupOf[i] !== g) { continue; }
        var x = sx(px[i]), y = sy(py[i]);
        if (x < -20 || y < -20 || x > W + 20 || y > H + 20) { continue; }
        var r = radius(i) * scale;
        ctx.moveTo(x + r, y);
        ctx.arc(x, y, r, 0, TAU);
      }
      ctx.fill();
    }

    if (focus >= 0) { drawFocus(focus); }
    drawLabels(focus);
    ctx.globalAlpha = 1;
  }

  function drawFocus(f) {
    var j, fx = sx(px[f]), fy = sy(py[f]);

    ctx.globalAlpha = 0.85;
    for (j = adjStart[f]; j < adjStart[f + 1]; j++) {
      var o = adjNode[j];
      ctx.strokeStyle = "#ffca57";
      ctx.lineWidth = Math.min(4, 0.7 + Math.log(1 + adjW[j]) * 0.9);
      ctx.beginPath();
      ctx.moveTo(fx, fy);
      ctx.lineTo(sx(px[o]), sy(py[o]));
      ctx.stroke();
    }

    ctx.globalAlpha = 1;
    ctx.fillStyle = "#ffe6a8";
    ctx.beginPath();
    var qscale = Math.min(1.9, Math.max(0.7, cam.s));
    for (j = adjStart[f]; j < adjStart[f + 1]; j++) {
      var q = adjNode[j];
      var qx = sx(px[q]), qy = sy(py[q]);
      var qr = radius(q) * qscale;
      ctx.moveTo(qx + qr, qy);
      ctx.arc(qx, qy, qr, 0, TAU);
    }
    ctx.fill();

    var r = radius(f) * Math.min(2.2, Math.max(1, cam.s)) + 2;
    ctx.fillStyle = "#ffca57";
    ctx.beginPath();
    ctx.arc(fx, fy, r, 0, TAU);
    ctx.fill();

    if (f === sel) {
      var phase = ((now() - selAt) % 1400) / 1400;
      ctx.globalAlpha = 1 - phase;
      ctx.strokeStyle = "#ffca57";
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.arc(fx, fy, r + phase * 34, 0, TAU);
      ctx.stroke();
      ctx.globalAlpha = 1;
    }
  }

  function drawLabels(focus) {
    ctx.font = "12px 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    ctx.textAlign = "center";
    ctx.textBaseline = "bottom";

    if (focus >= 0) {
      /* With a selection the background is dimmed, so labelling it too just
         produces overlapping noise. Name the neighbourhood only. */
      ctx.globalAlpha = 0.92;
      ctx.fillStyle = "#f0e2be";
      for (var j = adjStart[focus]; j < adjStart[focus + 1]; j++) {
        var q = adjNode[j];
        var qx = sx(px[q]), qy = sy(py[q]);
        if (qx < 0 || qy < 0 || qx > W || qy > H) { continue; }
        ctx.fillText(names[q], qx, qy - radius(q) - 3);
      }
    } else if (cam.s > 0.85) {
      var budget = 220;
      ctx.globalAlpha = 0.8;
      ctx.fillStyle = "#c6d5ea";
      for (var i = 0; i < n && budget > 0; i++) {
        var x = sx(px[i]), y = sy(py[i]);
        if (x < 0 || y < 0 || x > W || y > H) { continue; }
        if (deg[i] < 4 && cam.s < 1.6) { continue; }
        ctx.fillText(names[i], x, y - radius(i) - 3);
        budget--;
      }
    }

    if (focus >= 0) {
      ctx.globalAlpha = 1;
      ctx.fillStyle = "#fff3d4";
      ctx.font = "bold 13px 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
      ctx.fillText(names[focus], sx(px[focus]), sy(py[focus]) - radius(focus) - 7);
    }
  }

  /* ---- hit testing: a linear scan is ample below a few thousand nodes ---- */

  function pick(mx, my) {
    var best = -1, bestD = 18 * 18;
    for (var i = 0; i < n; i++) {
      var ox = sx(px[i]) - mx, oy = sy(py[i]) - my;
      var d2 = ox * ox + oy * oy;
      var r = radius(i) * Math.min(1.9, Math.max(0.55, cam.s)) + 5;
      if (d2 < bestD && d2 < r * r * 4) { best = i; bestD = d2; }
    }
    return best;
  }

  /* ---- interaction ---- */

  function select(i) {
    sel = i;
    selAt = now();
    if (i < 0) { detail.style.display = "none"; return; }
    var partners = [];
    for (var j = adjStart[i]; j < adjStart[i + 1]; j++) {
      partners.push([names[adjNode[j]], adjW[j]]);
    }
    partners.sort(function (a, b) { return b[1] - a[1]; });
    var out = "<b>" + esc(names[i]) + "</b><br/>" + partners.length + " connections";
    if (partners.length) {
      out += "<br/><span style='color:#8b9ab3'>Most teams together:</span><br/>";
      for (var p = 0; p < Math.min(5, partners.length); p++) {
        out += esc(partners[p][0]) + " &middot; " + partners[p][1] + "<br/>";
      }
    }
    detail.innerHTML = out;
    detail.style.display = "block";
  }

  function esc(s) {
    return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  }

  canvas.addEventListener("mousedown", function (ev) {
    dragging = true; dragMoved = false;
    lastX = ev.clientX; lastY = ev.clientY;
    canvas.className = "dragging";
  });

  window.addEventListener("mouseup", function () {
    dragging = false;
    canvas.className = "";
  });

  canvas.addEventListener("mousemove", function (ev) {
    var rect = canvas.getBoundingClientRect();
    var mx = ev.clientX - rect.left, my = ev.clientY - rect.top;
    if (dragging) {
      dragMoved = true;
      fly = null;
      cam.x -= (ev.clientX - lastX) / cam.s;
      cam.y -= (ev.clientY - lastY) / cam.s;
      lastX = ev.clientX; lastY = ev.clientY;
      return;
    }
    hov = pick(mx, my);
    if (hov >= 0) {
      tip.style.display = "block";
      tip.style.left = (mx + 14) + "px";
      tip.style.top = (my + 14) + "px";
      tip.innerHTML = esc(names[hov]) + " &middot; " + deg[hov] + " connections";
    } else {
      tip.style.display = "none";
    }
  });

  canvas.addEventListener("click", function (ev) {
    if (dragMoved) { return; }
    var rect = canvas.getBoundingClientRect();
    select(pick(ev.clientX - rect.left, ev.clientY - rect.top));
  });

  canvas.addEventListener("wheel", function (ev) {
    ev.preventDefault();
    fly = null;
    var rect = canvas.getBoundingClientRect();
    var mx = ev.clientX - rect.left, my = ev.clientY - rect.top;
    var bx = wx(mx), by = wy(my);
    var f = ev.deltaY < 0 ? 1.14 : 1 / 1.14;
    cam.s = Math.max(0.02, Math.min(24, cam.s * f));
    cam.x = bx - (mx - W / 2) / cam.s;
    cam.y = by - (my - H / 2) / cam.s;
  }, { passive: false });

  canvas.addEventListener("touchstart", function (ev) {
    fly = null;
    if (ev.touches.length === 1) {
      dragging = true;
      lastX = ev.touches[0].clientX; lastY = ev.touches[0].clientY;
    } else if (ev.touches.length === 2) {
      dragging = false;
      pinch = touchGap(ev);
    }
  });

  canvas.addEventListener("touchmove", function (ev) {
    ev.preventDefault();
    if (ev.touches.length === 1 && dragging) {
      cam.x -= (ev.touches[0].clientX - lastX) / cam.s;
      cam.y -= (ev.touches[0].clientY - lastY) / cam.s;
      lastX = ev.touches[0].clientX; lastY = ev.touches[0].clientY;
    } else if (ev.touches.length === 2 && pinch > 0) {
      var gap = touchGap(ev);
      cam.s = Math.max(0.02, Math.min(24, cam.s * (gap / pinch)));
      pinch = gap;
    }
  }, { passive: false });

  canvas.addEventListener("touchend", function () { dragging = false; pinch = 0; });

  function touchGap(ev) {
    var a = ev.touches[0], b = ev.touches[1];
    return Math.sqrt(Math.pow(a.clientX - b.clientX, 2) + Math.pow(a.clientY - b.clientY, 2));
  }

  document.getElementById("zin").onclick = function () { cam.s = Math.min(24, cam.s * 1.4); };
  document.getElementById("zout").onclick = function () { cam.s = Math.max(0.02, cam.s / 1.4); };
  document.getElementById("zfit").onclick = function () { fly = null; select(-1); fitView(); };

  /* ---- search ---- */

  var q = document.getElementById("q");
  var hits = document.getElementById("hits");

  var order = [];
  (function () {
    var i;
    for (i = 0; i < n; i++) { order.push({ i: i, l: names[i].toLowerCase(), d: deg[i] }); }
    for (i = 0; i < D.isolated.length; i++) {
      order.push({ i: -1, l: D.isolated[i].toLowerCase(), d: -1, name: D.isolated[i] });
    }
  })();

  function runSearch() {
    var term = q.value.trim().toLowerCase();
    hits.innerHTML = "";
    /* The leaderboard and the hit list compete for the same panel space. */
    document.getElementById("topwrap").style.display = term.length >= 2 ? "none" : "block";
    if (term.length < 2) { return; }
    var found = [];
    for (var i = 0; i < order.length && found.length < 40; i++) {
      if (order[i].l.indexOf(term) !== -1) { found.push(order[i]); }
    }
    found.sort(function (a, b) { return b.d - a.d; });
    for (var f = 0; f < Math.min(20, found.length); f++) {
      (function (hit) {
        var li = document.createElement("li");
        if (hit.i < 0) {
          li.className = "off";
          li.innerHTML = "<span>" + esc(hit.name) + "</span><span class='n'>no repeat teammates</span>";
        } else {
          li.innerHTML = "<span>" + esc(names[hit.i]) + "</span><span class='n'>" + hit.d + "</span>";
          li.onclick = function () { select(hit.i); flyTo(hit.i); };
        }
        hits.appendChild(li);
      })(found[f]);
    }
  }

  q.addEventListener("input", runSearch);

  /* ---- colour key and leaderboard ---- */

  /* Colour without a key is meaningless, so this is not optional decoration. */
  function buildKey() {
    if (!D.groups) { return; }
    var counts = [], i, g;
    for (g = 0; g < D.groups.labels.length; g++) { counts.push(0); }
    for (i = 0; i < n; i++) { counts[groupOf[i]]++; }

    var key = document.getElementById("key");
    key.innerHTML = "";
    for (g = 0; g < D.groups.labels.length; g++) {
      if (counts[g] === 0) { continue; }
      var li = document.createElement("li");
      li.innerHTML = "<span class='sw' style='background:" + D.groups.colors[g] + "'></span>" +
        "<span>" + esc(D.groups.labels[g]) + "</span><span class='c'>" + counts[g] + "</span>";
      key.appendChild(li);
    }
    document.getElementById("keywrap").style.display = "block";
  }

  function buildTop() {
    var idx = [];
    var i;
    for (i = 0; i < n; i++) { idx.push(i); }
    idx.sort(function (a, b) { return deg[b] - deg[a]; });
    var top = document.getElementById("top");
    top.innerHTML = "";
    for (i = 0; i < Math.min(12, idx.length); i++) {
      (function (rank, node) {
        var li = document.createElement("li");
        li.innerHTML = "<span><span class='r'>" + (rank + 1) + "</span>" + esc(names[node]) +
          "</span><span class='n'>" + deg[node] + "</span>";
        li.onclick = function () { select(node); flyTo(node); };
        top.appendChild(li);
      })(i, idx[i]);
    }
  }

  /* ---- boot: layout in slices so the progress bar can paint ---- */

  function frame() {
    if (fly) {
      var t = Math.min(1, (now() - fly.t0) / 620);
      var e = t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
      cam.x = fly.x0 + (fly.x1 - fly.x0) * e;
      cam.y = fly.y0 + (fly.y1 - fly.y0) * e;
      cam.s = fly.s0 + (fly.s1 - fly.s0) * e;
      if (t >= 1) { fly = null; }
    }
    draw();
    window.requestAnimationFrame(frame);
  }

  function start() {
    boot.style.display = "none";
    buildKey();
    buildTop();
    resize();
    fitView();
    window.addEventListener("resize", function () { resize(); });
    window.requestAnimationFrame(frame);
  }

  resize();

  if (n === 0) {
    boot.innerHTML = "<p>This export contains no connections at the chosen threshold.</p>";
    return;
  }

  initLayout();

  if (loadCache()) {
    start();
  } else {
    (function run() {
      var end = now() + 60;
      while (iter < ITER && now() < end) { stepLayout(); iter++; }
      bar.style.width = Math.round((iter / ITER) * 100) + "%";
      bootText.textContent = "Laying out " + n + " players... " + Math.round((iter / ITER) * 100) + "%";
      if (iter < ITER) {
        window.requestAnimationFrame(run);
      } else {
        saveCache();
        start();
      }
    })();
  }
})();
JS;
}

/**
 * Build the self-contained export document.
 *
 * @param array $graph
 * @param array $meta
 * @return string
 */
function PlayerNetworkPluginExportHtml($graph, $meta)
{
    $payload = [
        "meta" => $meta,
        "names" => $graph['names'],
        "degree" => $graph['degree'],
        "edges" => $graph['edges'],
        "isolated" => $graph['isolated'],
        "groups" => $graph['groups'],
    ];

    $json = json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if ($json === false) {
        $json = "null";
    }

    $summary = sprintf(
        "%d players, %d connections, from %d events. Connected when they shared at least %d teams. Built %s.",
        count($graph['names']),
        count($graph['edges']) / 3,
        (int) $meta['events'],
        (int) $meta['minShared'],
        $meta['built'],
    );

    $css = PlayerNetworkPluginExportCss();
    $js = PlayerNetworkPluginExportJs();

    $html = "<!DOCTYPE html>\n";
    $html .= "<html lang='en'>\n<head>\n<meta charset='utf-8'/>\n";
    $html .= "<meta name='viewport' content='width=device-width, initial-scale=1'/>\n";
    $html .= "<title>Player network</title>\n";
    $html .= "<style>\n" . $css . "\n</style>\n</head>\n<body>\n";
    $html .= "<div id='stage'><canvas id='net'></canvas></div>\n";
    $html .= "<div id='tip'></div>\n";
    $html .= "<div id='panel'>\n";
    $html .= "<h1>Player network</h1>\n";
    $html .= "<p id='meta'>" . htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') . "</p>\n";
    $html .= "<input id='q' type='search' placeholder='Search for a player...' autocomplete='off'/>\n";
    $html .= "<ul id='hits'></ul>\n";
    $html .= "<div id='detail'></div>\n";
    $html .= "<div id='keywrap'><h2>Colour key</h2><ul id='key'></ul></div>\n";
    $html .= "<div id='topwrap'><h2>Most connections</h2><ul id='top'></ul></div>\n";
    $html .= "<p id='note'>Drag to pan, scroll to zoom, hover or click a player to light up who they played with.</p>\n";
    $html .= "</div>\n";
    $html .= "<div id='zoom'><button id='zout' title='Zoom out'>&minus;</button>";
    $html .= "<button id='zin' title='Zoom in'>+</button>";
    $html .= "<button id='zfit' class='wide'>Reset</button></div>\n";
    $html .= "<div id='boot'><p id='boottext'>Preparing layout...</p><div id='bar'><i></i></div></div>\n";
    $html .= "<script>window.UO_NETWORK = " . $json . ";</script>\n";
    $html .= "<script>\n" . $js . "\n</script>\n";
    $html .= "</body>\n</html>\n";

    return $html;
}

/* ---------------------------------------------------------------- page ---- */

$title = "Player network export";
$html = "";

$thresholds = array_map('intval', explode(",", PLAYER_NETWORK_THRESHOLDS));

$minShared = PLAYER_NETWORK_DEFAULT_MIN_SHARED;
if (isset($_POST['min_shared']) && ctype_digit((string) $_POST['min_shared'])) {
    $minShared = max(1, min(50, (int) $_POST['min_shared']));
}
/* An unchecked checkbox posts nothing, so the default only applies before the
   form has ever been submitted - otherwise unticking it and pressing Update
   would silently tick itself again. */
$includeIsolated = empty($_POST['submitted']) || !empty($_POST['include_isolated']);

$groupMode = "";
if (isset($_POST['group_mode']) && in_array($_POST['group_mode'], ["gender", "division"], true)) {
    $groupMode = $_POST['group_mode'];
}

$exportPath = UPLOAD_DIR . PLAYER_NETWORK_EXPORT_FILE;
$buildError = "";
$justBuilt = false;

if (!empty($_POST['build'])) {
    set_time_limit(600);

    $summary = PlayerNetworkPluginSummary();
    $graph = PlayerNetworkPluginBuildGraph($minShared, $includeIsolated, $groupMode);

    $meta = [
        "built" => date("Y-m-d H:i"),
        "events" => isset($summary['events']) ? (int) $summary['events'] : 0,
        "teams" => isset($summary['teams_valid']) ? (int) $summary['teams_valid'] : 0,
        "minShared" => $minShared,
        "hash" => substr(sha1(implode("|", [
            count($graph['names']),
            count($graph['edges']),
            $minShared,
            implode(",", array_slice($graph['nodeIds'], 0, 40)),
        ])), 0, 16),
    ];

    $document = PlayerNetworkPluginExportHtml($graph, $meta);

    // Uploads are stored 0644 so a web server running as a different user
    // than PHP can still serve them; matches WriteJpegImage()'s reasoning.
    if (!is_dir(UPLOAD_DIR) || file_put_contents($exportPath, $document) === false) {
        $buildError = "Could not write the export file. Check that " . UPLOAD_DIR . " is writable.";
    } else {
        chmod($exportPath, 0644);
        $justBuilt = true;
    }
}

set_time_limit(300);

$summary = PlayerNetworkPluginSummary();
$pairs = PlayerNetworkPluginPairs(1);
$stats = PlayerNetworkPluginThresholdStats($pairs, $thresholds);

$html .= "<h1>" . $title . "</h1>\n";
$html .= "<p>Builds a standalone, interactive HTML page of the teammate network across the whole database. ";
$html .= "Two players are connected when they appear on the same team roster; the connection weight is the number of ";
$html .= "distinct teams they shared. The exported file works offline and needs no server.</p>\n";

$html .= "<h2>Database</h2>\n";
$html .= "<table class='admintable'>\n";
$html .= "<tr><th class='left'>Events</th><td>" . (int) ($summary['events'] ?? 0) . "</td></tr>\n";
$html .= "<tr><th class='left'>Teams (participating)</th><td>" . (int) ($summary['teams_valid'] ?? 0);
$html .= " of " . (int) ($summary['teams'] ?? 0) . "</td></tr>\n";
$html .= "<tr><th class='left'>Player profiles</th><td>" . (int) ($summary['profiles'] ?? 0) . "</td></tr>\n";
$html .= "<tr><th class='left'>Roster records counted</th><td>" . (int) ($summary['roster_rows'] ?? 0) . "</td></tr>\n";
$html .= "</table>\n";

$html .= "<h2>Connection threshold</h2>\n";
$html .= "<p>Every team of <i>n</i> players creates <i>n(n-1)/2</i> connections, so counting one-off pairings ";
$html .= "floods the graph. Raising the minimum keeps only players who keep reappearing on the same roster.</p>\n";
$html .= "<table class='admintable'>\n";
$html .= "<tr><th>Minimum shared teams</th><th>Connections</th><th>Players</th><th>Average connections</th></tr>\n";
foreach ($stats as $row) {
    $isSelected = $row['threshold'] === $minShared;
    $html .= $isSelected ? "<tr class='highlight'>" : "<tr>";
    $html .= "<td class='center'>" . $row['threshold'] . ($isSelected ? " (selected)" : "") . "</td>";
    $html .= "<td class='center'>" . number_format($row['edges']) . "</td>";
    $html .= "<td class='center'>" . number_format($row['nodes']) . "</td>";
    $html .= "<td class='center'>" . number_format($row['degree'], 1) . "</td>";
    $html .= "</tr>\n";
}
$html .= "</table>\n";

$html .= "<h2>Settings</h2>\n";
$html .= "<form method='post' action='?view=plugins/player_network'>\n";
$html .= "<div><input type='hidden' name='submitted' value='1'/></div>\n";
$html .= "<p><label for='min-shared'>Minimum shared teams per connection:</label> ";
$html .= "<select class='dropdown' id='min-shared' name='min_shared'>";
foreach ($thresholds as $threshold) {
    $html .= "<option value='" . $threshold . "'";
    $html .= $threshold === $minShared ? " selected='selected'" : "";
    $html .= ">" . $threshold . "</option>";
}
$html .= "</select></p>\n";
$html .= "<p><label for='group-mode'>Colour players by:</label> ";
$html .= "<select class='dropdown' id='group-mode' name='group_mode'>";
foreach (["" => "Nothing", "division" => "Division", "gender" => "Gender"] as $value => $label) {
    $html .= "<option value='" . $value . "'";
    $html .= $value === $groupMode ? " selected='selected'" : "";
    $html .= ">" . $label . "</option>";
}
$html .= "</select><br/>\n";
$html .= "<small>Division uses the divisions a player's teams played in, merged to open, women and mixed, ";
$html .= "and is recorded for every team. Gender uses the player profile field, which is empty for ";
$html .= "roughly half of all players, so a large group ends up as \"not recorded\".</small></p>\n";
$html .= "<p><label for='include-isolated'><input type='checkbox' id='include-isolated' name='include_isolated' value='1'";
$html .= $includeIsolated ? " checked='checked'" : "";
$html .= "/> Keep players below the threshold in the search box</label><br/>\n";
$html .= "<small>They are not drawn, but a search still finds them and reports that they have no repeat teammates.</small></p>\n";
$html .= "<div class='warning'>The exported file lists every player by name together with who they played alongside. ";
$html .= "It is personal data. Store and share it accordingly.</div>\n";
$html .= "<p><input class='button' type='submit' name='preview' value='Update'/> ";
$html .= "<input class='button' type='submit' name='build' value='Build'/></p>\n";
$html .= "</form>\n";

if ($buildError !== "") {
    $html .= "<p class='warning'>" . htmlspecialchars($buildError, ENT_QUOTES, 'UTF-8') . "</p>\n";
} elseif (is_file($exportPath)) {
    $builtOn = date("Y-m-d H:i", filemtime($exportPath));
    $html .= "<p class='notice'>";
    $html .= $justBuilt ? "Export updated. " : "";
    $html .= "<a href='" . UPLOAD_DIR . PLAYER_NETWORK_EXPORT_FILE . "'>View the exported player network</a>";
    $html .= " (built " . $builtOn . ")</p>\n";
}

$topConnected = PlayerNetworkPluginTopConnected($pairs, $minShared, 20);
if (!empty($topConnected)) {
    $html .= "<h2>Most connections</h2>\n";
    $html .= "<p>Distinct teammates at a minimum of " . $minShared . " shared ";
    $html .= ($minShared === 1 ? "team" : "teams") . ", so this counts how many ";
    $html .= "different people someone has played with repeatedly rather than how long their career is. ";
    $html .= "Change the minimum above and press Update to recount.</p>\n";
    $html .= "<table class='admintable'>\n";
    $html .= "<tr><th>#</th><th class='left'>Player</th><th>Connections</th></tr>\n";
    $rank = 0;
    foreach ($topConnected as $row) {
        $rank++;
        $html .= "<tr>";
        $html .= "<td class='center'>" . $rank . "</td>";
        $html .= "<td class='left'><a href='?view=playercard&amp;profile=" . (int) $row['profile_id'] . "'>";
        $html .= utf8entities($row['name']) . "</a></td>";
        $html .= "<td class='center'>" . (int) $row['connections'] . "</td>";
        $html .= "</tr>\n";
    }
    $html .= "</table>\n";
}

$html .= "<h2>How to read it</h2>\n";
$html .= "<ul>\n";
$html .= "<li>Type your name in the search box to jump to yourself; your connections light up.</li>\n";
$html .= "<li>Drag to pan, scroll or pinch to zoom, click a player to pin them and list their closest teammates.</li>\n";
$html .= "<li>Clusters are groups of people who keep turning up on the same rosters.</li>\n";
$html .= "<li>A player's circle grows with the number of connections, so the busiest players stand out.</li>\n";
$html .= "</ul>\n";

$html .= "<h2>What is counted</h2>\n";
$html .= "<ul>\n";
$html .= "<li>Roster membership, not game appearances: a player listed but never on the field still counts.</li>\n";
$html .= "<li>Only participating teams. Withdrawn teams and BYE placeholders are excluded, so numbers can differ ";
$html .= "from a team page.</li>\n";
$html .= "<li>Roster records without a player profile are skipped, because they cannot be tied to a person.</li>\n";
$html .= "<li>One person split across several profiles appears as several unconnected players.</li>\n";
$html .= "</ul>\n";

showPage($title, $html);
