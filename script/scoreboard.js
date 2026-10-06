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

  function getJson(url, done) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onload = function () {
      var data = null;
      try {
        data = JSON.parse(xhr.responseText);
      } catch (err) { // eslint-disable-line no-unused-vars
        data = null;
      }
      done(xhr.status === 200 ? data : null);
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

  function renderGame(data) {
    if (!data) {
      return;
    }
    var scores = data.homescore + '-' + data.visitorscore;
    var changed = lastScores !== null && lastScores !== scores;
    setTeam('sb-home', data.home, data.homescore, changed && lastScores.split('-')[0] !== String(data.homescore));
    setTeam('sb-visitor', data.visitor, data.visitorscore, changed && lastScores.split('-')[1] !== String(data.visitorscore));
    lastScores = scores;
  }

  function pollGame(id) {
    getJson('?json=game&game=' + id, function (data) {
      renderGame(data);
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
      fitName(document.querySelector('#sb-home .sb-name'));
      fitName(document.querySelector('#sb-visitor .sb-name'));
    }
  });
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && !board.hidden) {
      keepAwake();
    }
  });
  route();
}());
