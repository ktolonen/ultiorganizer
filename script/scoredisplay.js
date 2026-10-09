/* Score display: game picker and full-screen score display. ES5. */
(function () {
  'use strict';

  var i18n = window.SCOREDISPLAY_I18N || {};
  var LIST_MS = 15000;
  var GAME_MS = 3000;
  var picker = document.getElementById('sd-picker');
  var board = document.getElementById('sd-board');
  var listEl = document.getElementById('sd-list');
  var timer = null;
  var generation = 0; // bumped by route(), so replies to an earlier view are dropped
  var lastScores = null;
  var flashTimers = {};
  var wakeLock = null;
  var clockEl = document.getElementById('sd-clock');
  var clockTick = null;
  var clockAnchor = null; // {elapsed, at, paused}

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    if (text !== undefined) {
      node.textContent = text;
    }
    return node;
  }

  // done(data, sampledAt, status): sampledAt estimates when the server read
  // the data, as the midpoint of the request round trip. status is 0 when the
  // request failed, so the caller can tell a network error from a reply.
  function getJson(url, done) {
    var xhr = new XMLHttpRequest();
    var sentAt = Date.now();
    xhr.open('GET', url, true);
    xhr.timeout = 10000;
    xhr.onload = function () {
      var data = null;
      try {
        data = JSON.parse(xhr.responseText);
      } catch (err) { // eslint-disable-line no-unused-vars
        data = null;
      }
      done(xhr.status === 200 ? data : null, (sentAt + Date.now()) / 2, xhr.status);
    };
    xhr.onerror = xhr.ontimeout = function () {
      done(null, 0, 0);
    };
    xhr.send();
  }

  function selectedGame() {
    var match = /game=(\d+)/.exec(window.location.hash);
    return match ? match[1] : null;
  }

  function stop() {
    if (timer) {
      window.clearTimeout(timer);
      timer = null;
    }
  }

  function section(title, games) {
    var wrap = el('section');
    wrap.appendChild(el('h2', '', title));
    games.forEach(function (game) {
      var button = el('button', 'sd-game');
      button.type = 'button';
      var label = (game.ongoing ? '' : game.time + '  ') + game.home + ' - ' + game.visitor;
      button.appendChild(el('span', 'sd-game-name', label));
      if (game.ongoing) {
        button.appendChild(el('span', 'sd-game-score', game.homescore + ' - ' + game.visitorscore));
      } else if (game.place) {
        button.appendChild(el('span', 'sd-game-place', game.place));
      }
      button.onclick = function () {
        window.location.hash = 'game=' + game.id;
      };
      wrap.appendChild(button);
    });
    return wrap;
  }

  function renderList(data) {
    if (!data) {
      return;
    }
    var ongoing = data.games.filter(function (game) { return game.ongoing; });
    var upcoming = data.games.filter(function (game) { return !game.ongoing; });
    listEl.innerHTML = '';
    if (ongoing.length) {
      listEl.appendChild(section(i18n.ongoing, ongoing));
    }
    if (upcoming.length) {
      listEl.appendChild(section(i18n.upcoming, upcoming));
    }
    if (!data.games.length) {
      listEl.appendChild(el('p', '', i18n.noGames));
    }
  }

  function pollList() {
    var gen = generation;
    getJson('?json=list', function (data, sampledAt, status) {
      if (gen !== generation) {
        return;
      }
      // Maintenance hides the games; a network error keeps the last list.
      renderList(status === 503 ? {games: []} : data);
      timer = window.setTimeout(pollList, LIST_MS);
    });
  }

  // Shrink a team name until it fits its half of the screen.
  function fitName(node) {
    var parent = node.parentNode;
    node.style.fontSize = '';
    var size = parseFloat(window.getComputedStyle(node).fontSize);
    while (size > 12 && (node.scrollWidth > parent.clientWidth || node.offsetHeight > parent.clientHeight * 0.4)) {
      size -= 2;
      node.style.fontSize = size + 'px';
    }
  }

  // Fit both names, then give both scores the smaller of their sizes: each
  // score fills what its name leaves, and a wrapped name leaves less.
  function fitNames() {
    var scores = document.querySelectorAll('#sd-board .sd-score');
    var size = Infinity;
    var i;
    fitName(document.querySelector('#sd-home .sd-name'));
    fitName(document.querySelector('#sd-visitor .sd-name'));
    for (i = 0; i < scores.length; i++) {
      scores[i].style.fontSize = '';
      size = Math.min(size, parseFloat(window.getComputedStyle(scores[i]).fontSize));
    }
    for (i = 0; i < scores.length; i++) {
      scores[i].style.fontSize = size + 'px';
    }
  }

  function setTeam(id, name, score, changed) {
    var team = document.getElementById(id);
    var nameEl = team.querySelector('.sd-name');
    if (nameEl.textContent !== name) {
      nameEl.textContent = name;
      fitNames();
    }
    team.querySelector('.sd-score').textContent = score;
    if (changed) {
      team.className = 'sd-team sd-changed';
      window.clearTimeout(flashTimers[id]);
      flashTimers[id] = window.setTimeout(function () {
        team.className = 'sd-team';
      }, 4000);
    }
  }

  function clockSeconds() {
    if (clockAnchor.paused) {
      return clockAnchor.elapsed;
    }
    return clockAnchor.elapsed + Math.max(0, Math.floor((Date.now() - clockAnchor.at) / 1000));
  }

  function drawClock() {
    var seconds = clockSeconds();
    var ss = seconds % 60;
    clockEl.textContent = Math.floor(seconds / 60) + ':' + (ss < 10 ? '0' : '') + ss;
  }

  function stopClock() {
    if (clockTick) {
      window.clearInterval(clockTick);
      clockTick = null;
    }
    clockAnchor = null;
    clockEl.hidden = true;
  }

  // Ticks locally between polls. The server reports whole seconds, so a
  // running clock is re-anchored only when it has drifted, which keeps it
  // from stepping back and forth by a second on each poll.
  function setClock(clock, sampledAt) {
    var wasHidden = clockEl.hidden;
    if (!clock) {
      stopClock();
      if (!wasHidden) {
        fitNames();
      }
      return;
    }
    var paused = !!clock.paused;
    if (!clockAnchor || clockAnchor.paused !== paused || paused
      || Math.abs(clockSeconds() - clock.elapsed) > 1) {
      clockAnchor = {elapsed: clock.elapsed, at: sampledAt, paused: paused};
    }
    clockEl.hidden = false;
    if (wasHidden) {
      fitNames();
    }
    clockEl.className = paused ? 'sd-paused' : '';
    drawClock();
    if (!paused && !clockTick) {
      clockTick = window.setInterval(drawClock, 250);
    } else if (paused && clockTick) {
      window.clearInterval(clockTick);
      clockTick = null;
    }
  }

  function renderGame(data, sampledAt) {
    if (!data) {
      return;
    }
    setClock(data.clock, sampledAt);
    var scores = data.homescore + '-' + data.visitorscore;
    var changed = lastScores !== null && lastScores !== scores;
    setTeam('sd-home', data.home, data.homescore, changed && lastScores.split('-')[0] !== String(data.homescore));
    setTeam('sd-visitor', data.visitor, data.visitorscore, changed && lastScores.split('-')[1] !== String(data.visitorscore));
    lastScores = scores;
  }

  function pollGame(id) {
    var gen = generation;
    getJson('?json=game&game=' + id, function (data, sampledAt, status) {
      if (gen !== generation) {
        return;
      }
      // The game is no longer public, so its last score must not stay on
      // screen. A network error keeps the last reading.
      if (status === 404 || status === 503) {
        window.location.hash = '';
        return;
      }
      renderGame(data, sampledAt);
      timer = window.setTimeout(function () {
        pollGame(id);
      }, GAME_MS);
    });
  }

  function keepAwake() {
    if (navigator.wakeLock && !wakeLock) {
      navigator.wakeLock.request('screen').then(function (lock) {
        wakeLock = lock;
        lock.addEventListener('release', function () {
          wakeLock = null;
        });
      }, function () {});
    }
  }

  function route() {
    generation++;
    stop();
    ['sd-home', 'sd-visitor'].forEach(function (team) {
      window.clearTimeout(flashTimers[team]);
      document.getElementById(team).className = 'sd-team';
    });
    stopClock();
    var id = selectedGame();
    lastScores = null;
    if (id) {
      picker.hidden = true;
      board.hidden = false;
      keepAwake();
      pollGame(id);
    } else {
      board.hidden = true;
      picker.hidden = false;
      pollList();
    }
  }

  document.getElementById('sd-back').onclick = function () {
    window.location.hash = '';
  };
  document.getElementById('sd-full').onclick = function () {
    if (document.fullscreenElement) {
      document.exitFullscreen();
    } else if (document.documentElement.requestFullscreen) {
      document.documentElement.requestFullscreen();
    }
  };
  window.addEventListener('hashchange', route);
  window.addEventListener('resize', function () {
    if (!board.hidden) {
      fitNames();
    }
  });
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && !board.hidden) {
      keepAwake();
    }
  });
  route();
}());
