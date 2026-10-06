/* Scoreboard: game picker and full-screen score display. ES5. */
(function () {
  'use strict';

  var i18n = window.SCOREBOARD_I18N || {};
  var LIST_MS = 15000;
  var GAME_MS = 3000;
  var picker = document.getElementById('sb-picker');
  var board = document.getElementById('sb-board');
  var listEl = document.getElementById('sb-list');
  var timer = null;
  var lastScores = null;
  var wakeLock = null;
  var clockEl = document.getElementById('sb-clock');
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

  // done(data, sampledAt): sampledAt estimates when the server read the data,
  // as the midpoint of the request round trip.
  function getJson(url, done) {
    var xhr = new XMLHttpRequest();
    var sentAt = Date.now();
    xhr.open('GET', url, true);
    xhr.onload = function () {
      var data = null;
      try {
        data = JSON.parse(xhr.responseText);
      } catch (err) { // eslint-disable-line no-unused-vars
        data = null;
      }
      done(xhr.status === 200 ? data : null, (sentAt + Date.now()) / 2);
    };
    xhr.onerror = function () {
      done(null);
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
      var button = el('button', 'sb-game');
      button.type = 'button';
      var label = (game.ongoing ? '' : game.time + '  ') + game.home + ' - ' + game.visitor;
      button.appendChild(el('span', 'sb-game-name', label));
      if (game.ongoing) {
        button.appendChild(el('span', 'sb-game-score', game.homescore + ' - ' + game.visitorscore));
      } else if (game.place) {
        button.appendChild(el('span', 'sb-game-place', game.place));
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
    getJson('?json=list', function (data) {
      renderList(data);
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

  function fitNames() {
    fitName(document.querySelector('#sb-home .sb-name'));
    fitName(document.querySelector('#sb-visitor .sb-name'));
  }

  function setTeam(id, name, score, changed) {
    var team = document.getElementById(id);
    var nameEl = team.querySelector('.sb-name');
    if (nameEl.textContent !== name) {
      nameEl.textContent = name;
      fitName(nameEl);
    }
    team.querySelector('.sb-score').textContent = score;
    if (changed) {
      team.className = 'sb-team sb-changed';
      window.setTimeout(function () {
        team.className = 'sb-team';
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
    clockEl.className = paused ? 'sb-paused' : '';
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
    setTeam('sb-home', data.home, data.homescore, changed && lastScores.split('-')[0] !== String(data.homescore));
    setTeam('sb-visitor', data.visitor, data.visitorscore, changed && lastScores.split('-')[1] !== String(data.visitorscore));
    lastScores = scores;
  }

  function pollGame(id) {
    getJson('?json=game&game=' + id, function (data, sampledAt) {
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
    stop();
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

  document.getElementById('sb-back').onclick = function () {
    window.location.hash = '';
  };
  document.getElementById('sb-full').onclick = function () {
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
