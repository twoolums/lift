(function () {
  'use strict';

  var body = document.body;
  var api = body.getAttribute('data-api') || '';
  var csrf = body.getAttribute('data-csrf') || '';
  var isWorkout = body.getAttribute('data-page') === 'workout';
  var workoutId = isWorkout ? parseInt(body.getAttribute('data-workout'), 10) : 0;
  var pageSwimmer = isWorkout ? parseInt(body.getAttribute('data-swimmer'), 10) : 0;
  var statusEl = document.getElementById('save-status');

  // ============================================================
  // Small page behaviors
  // ============================================================
  document.addEventListener('submit', function (e) {
    var f = e.target;
    var m = f.getAttribute && f.getAttribute('data-confirm');
    if (m && !window.confirm(m)) {
      e.preventDefault();
      return;
    }
    var md = f.getAttribute && f.getAttribute('data-confirm-del');
    if (md && f.querySelector('input.del:checked') && !window.confirm(md)) {
      e.preventDefault();
    }
  });

  // Drop-downs that jump straight to the next step when changed
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t && t.hasAttribute && t.hasAttribute('data-autosubmit') && t.value !== '') {
      t.form.submit();
    }
  });

  // Coming back to the kiosk with the Back button should show an empty name picker
  window.addEventListener('pageshow', function () {
    var ks = document.querySelector('.kiosk-select');
    if (ks) { ks.selectedIndex = 0; }
  });

  // ============================================================
  // Shared save queue (works on any page, survives going offline)
  // ============================================================
  var QKEY = 'liftq2';
  var queue = {};
  try { queue = JSON.parse(window.localStorage.getItem(QKEY) || '{}') || {}; } catch (err) { queue = {}; }
  var sending = false;
  var blocked = {};      // items the server refused this page load (not logged in, wrong person...)
  var timers = {};

  function persist() {
    try { window.localStorage.setItem(QKEY, JSON.stringify(queue)); } catch (err) { /* private mode */ }
  }

  function setStatus(state) {
    if (!statusEl) { return; }
    var text = {
      saved: 'Saved',
      saving: 'Saving...',
      offline: 'Offline, will retry',
      error: "Couldn't save that one",
      auth: 'Log in again'
    }[state] || '';
    statusEl.textContent = text;
    statusEl.className = 'save-status ' + state;
  }

  function pending() {
    return sending || Object.keys(queue).length > 0;
  }

  function findRow(item) {
    if (!isWorkout || item.workout_id !== workoutId || item.swimmer_id !== pageSwimmer) { return null; }
    return document.querySelector('.setrow[data-exercise="' + item.exercise_id + '"][data-set="' + item.set_no + '"]');
  }

  function markSaved(item) {
    var row = findRow(item);
    if (!row) { return; }
    row.classList.remove('partial');
    var has = (item.val !== null && item.val !== '') || (item.reps !== null && item.reps !== '');
    if (has) { row.classList.add('saved'); } else { row.classList.remove('saved'); }
  }

  function nextKey() {
    var keys = Object.keys(queue);
    for (var i = 0; i < keys.length; i++) {
      if (!blocked[keys[i]]) { return keys[i]; }
    }
    return null;
  }

  function flush() {
    if (sending || !api || !csrf) { return; }
    var key = nextKey();
    if (key === null) {
      if (Object.keys(blocked).length) { setStatus('auth'); } else { setStatus('saved'); }
      return;
    }
    var item = queue[key];
    sending = true;
    setStatus('saving');
    fetch(api, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        csrf: csrf,
        workout_id: item.workout_id,
        swimmer_id: item.swimmer_id,
        exercise_id: item.exercise_id,
        set_no: item.set_no,
        val: item.val,
        reps: item.reps
      })
    }).then(function (r) {
      return r.json().then(function (j) { return { status: r.status, json: j }; });
    }).then(function (res) {
      sending = false;
      if (res.json && res.json.ok) {
        if (queue[key] === item) { delete queue[key]; persist(); }
        markSaved(item);
        flush();
      } else if (res.status === 401 || res.status === 403) {
        blocked[key] = true;
        flush();
      } else {
        delete queue[key];
        persist();
        setStatus('error');
        window.setTimeout(flush, 1500);
      }
    }).catch(function () {
      sending = false;
      setStatus('offline');
      window.setTimeout(flush, 8000);
    });
  }

  window.addEventListener('online', flush);
  if (Object.keys(queue).length) { flush(); }

  // ============================================================
  // Workout logging page
  // ============================================================
  if (isWorkout) { initWorkout(); }

  function initWorkout() {
    function fmt(n) { return String(Math.round(n * 10) / 10); }

    function rowValues(row) {
      var v = row.querySelector('[data-field="val"]');
      var r = row.querySelector('[data-field="reps"]');
      return { val: v ? v.value.trim() : null, reps: r ? r.value.trim() : null };
    }

    function commit(row) {
      var type = row.getAttribute('data-type');
      var vals = rowValues(row);
      var needVal = (type === 'wr' || type === 'sec');
      var needReps = (type === 'wr' || type === 'reps');
      var hasVal = vals.val !== null && vals.val !== '';
      var hasReps = vals.reps !== null && vals.reps !== '';
      var complete = (!needVal || hasVal) && (!needReps || hasReps);
      var empty = !hasVal && !hasReps;
      if (!complete && !empty) {
        row.classList.remove('saved');
        row.classList.add('partial');
        return;
      }
      row.classList.remove('partial');
      var ex = parseInt(row.getAttribute('data-exercise'), 10);
      var sn = parseInt(row.getAttribute('data-set'), 10);
      var key = workoutId + '|' + pageSwimmer + '|' + ex + '|' + sn;
      delete blocked[key];
      queue[key] = {
        workout_id: workoutId,
        swimmer_id: pageSwimmer,
        exercise_id: ex,
        set_no: sn,
        val: (needVal && hasVal) ? vals.val : null,
        reps: (needReps && hasReps) ? vals.reps : null
      };
      persist();
      setStatus('saving');
      flush();
    }

    function schedule(row, delay) {
      var key = row.getAttribute('data-exercise') + '|' + row.getAttribute('data-set');
      window.clearTimeout(timers[key]);
      timers[key] = window.setTimeout(function () { timers[key] = null; commit(row); }, delay);
    }

    // +/- buttons
    body.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('.step') : null;
      if (!btn) { return; }
      var inp = btn.parentNode.querySelector('.inp');
      var row = btn.closest('.setrow');
      if (!inp || !row) { return; }
      var step = parseFloat(inp.getAttribute('data-step')) || 1;
      var dir = btn.classList.contains('plus') ? 1 : -1;
      var cur = inp.value === '' ? NaN : parseFloat(inp.value);
      if (isNaN(cur)) { cur = parseFloat(inp.getAttribute('data-last')); }
      if (isNaN(cur)) { cur = parseFloat(inp.getAttribute('data-start')) || 0; }
      inp.value = fmt(Math.max(0, cur + dir * step));
      schedule(row, 350);
    });

    // typing directly
    body.addEventListener('input', function (e) {
      if (!e.target.classList || !e.target.classList.contains('inp')) { return; }
      var row = e.target.closest('.setrow');
      if (row) { schedule(row, 700); }
    });
    body.addEventListener('change', function (e) {
      if (!e.target.classList || !e.target.classList.contains('inp')) { return; }
      var row = e.target.closest('.setrow');
      if (row) { schedule(row, 0); }
    });

    // "Fill from last time" and "Add a set"
    body.addEventListener('click', function (e) {
      var fill = e.target.closest ? e.target.closest('.fill-last') : null;
      if (fill) {
        var card = fill.closest('.ex');
        var rows = card.querySelectorAll('.setrow');
        for (var i = 0; i < rows.length; i++) {
          var inputs = rows[i].querySelectorAll('.inp');
          for (var j = 0; j < inputs.length; j++) {
            var last = inputs[j].getAttribute('data-last');
            if (inputs[j].value === '' && last !== null && last !== '') { inputs[j].value = last; }
          }
          commit(rows[i]);
        }
        return;
      }
      var add = e.target.closest ? e.target.closest('.add-set') : null;
      if (add) {
        var cardEl = add.closest('.ex');
        var setsEl = cardEl.querySelector('.sets');
        var all = setsEl.querySelectorAll('.setrow');
        var lastRow = all[all.length - 1];
        if (!lastRow) { return; }
        var clone = lastRow.cloneNode(true);
        var n = parseInt(lastRow.getAttribute('data-set'), 10) + 1;
        clone.setAttribute('data-set', String(n));
        clone.classList.remove('saved', 'partial');
        var sn = clone.querySelector('.setn');
        if (sn) { sn.textContent = String(n); }
        var ins = clone.querySelectorAll('.inp');
        for (var k = 0; k < ins.length; k++) {
          ins[k].value = '';
          var prevInp = lastRow.querySelector('.inp[data-field="' + ins[k].getAttribute('data-field') + '"]');
          var carry = prevInp && prevInp.value !== '' ? prevInp.value : (prevInp ? prevInp.getAttribute('data-last') : null);
          if (carry) {
            ins[k].setAttribute('data-last', carry);
            ins[k].setAttribute('placeholder', carry);
          }
        }
        setsEl.appendChild(clone);
      }
    });

    // Kiosk: "Done" waits until everything has saved before moving on
    var doneBtn = document.querySelector('.done-next');
    if (doneBtn) {
      doneBtn.addEventListener('click', function (e) {
        // let any typing that hasn't been committed yet go into the queue
        var rows = document.querySelectorAll('.setrow');
        for (var i = 0; i < rows.length; i++) {
          var key = rows[i].getAttribute('data-exercise') + '|' + rows[i].getAttribute('data-set');
          if (timers[key]) { window.clearTimeout(timers[key]); commit(rows[i]); timers[key] = null; }
        }
        if (!pending()) { return; }
        e.preventDefault();
        var href = doneBtn.getAttribute('href');
        var original = doneBtn.textContent;
        doneBtn.textContent = 'Saving...';
        var waited = 0;
        var iv = window.setInterval(function () {
          waited += 300;
          if (!pending()) {
            window.clearInterval(iv);
            window.location.href = href;
          } else if (waited >= 6000) {
            window.clearInterval(iv);
            doneBtn.textContent = original;
            if (statusEl) { statusEl.textContent = 'Still saving, one moment'; }
          }
        }, 300);
      });
    }

    // Kiosk: go back to the name picker after a few minutes of no touching
    var idleUrl = body.getAttribute('data-idle-url');
    if (idleUrl) {
      var idleTimer = null;
      var resetIdle = function () {
        window.clearTimeout(idleTimer);
        idleTimer = window.setTimeout(function () {
          if (pending()) { resetIdle(); } else { window.location.href = idleUrl; }
        }, 180000);
      };
      ['touchstart', 'mousedown', 'keydown', 'input', 'scroll'].forEach(function (ev) {
        document.addEventListener(ev, resetIdle, { passive: true });
      });
      resetIdle();
    }
  }

  // ============================================================
  // Charts
  // ============================================================
  var singles = document.querySelectorAll('.chart');
  for (var i = 0; i < singles.length; i++) { drawChart(singles[i]); }

  var bars = document.querySelectorAll('.bars');
  for (var b = 0; b < bars.length; b++) { drawBars(bars[b]); }

  var multis = document.querySelectorAll('.mchart');
  for (var m = 0; m < multis.length; m++) {
    (function (el) {
      var toggle = el.parentNode.querySelector('.toggle-athletes');
      drawMulti(el, false);
      if (toggle) {
        toggle.addEventListener('change', function () { drawMulti(el, toggle.checked); });
      }
    })(multis[m]);
  }

  function safeLabel(s) { return String(s).replace(/[<>&"]/g, ''); }

  // Single line (one swimmer, one exercise)
  function drawChart(el) {
    var pts;
    try { pts = JSON.parse(el.getAttribute('data-points')); } catch (err) { return; }
    if (!pts || pts.length < 2) {
      el.innerHTML = '<p class="muted small">Log this exercise in two or more sessions to see a trend.</p>';
      return;
    }
    var W = 320, H = 150, PL = 38, PR = 12, PT = 12, PB = 26;
    var ys = pts.map(function (p) { return p.y; });
    var min = Math.min.apply(null, ys), max = Math.max.apply(null, ys);
    if (min === max) { min = Math.max(0, min - 1); max = max + 1; }
    var pad = (max - min) * 0.1;
    min = Math.max(0, min - pad); max = max + pad;
    function x(i) { return PL + (W - PL - PR) * (i / (pts.length - 1)); }
    function y(v) { return PT + (H - PT - PB) * (1 - (v - min) / (max - min)); }
    var line = pts.map(function (p, i) { return (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(p.y).toFixed(1); }).join(' ');
    var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="Progress chart">';
    svg += '<line x1="' + PL + '" y1="' + (H - PB) + '" x2="' + (W - PR) + '" y2="' + (H - PB) + '" stroke="#d5e1e9"/>';
    svg += '<text x="4" y="' + (PT + 8) + '" font-size="10" fill="#5d6f7b">' + Math.round(max) + '</text>';
    svg += '<text x="4" y="' + (H - PB) + '" font-size="10" fill="#5d6f7b">' + Math.round(min) + '</text>';
    svg += '<path d="' + line + '" fill="none" stroke="#2E86AB" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>';
    pts.forEach(function (p, i) {
      svg += '<circle cx="' + x(i).toFixed(1) + '" cy="' + y(p.y).toFixed(1) + '" r="4" fill="#1B3A5C"/>';
    });
    svg += '<text x="' + PL + '" y="' + (H - 8) + '" font-size="10" fill="#5d6f7b">' + safeLabel(pts[0].label) + '</text>';
    svg += '<text x="' + (W - PR) + '" y="' + (H - 8) + '" font-size="10" text-anchor="end" fill="#5d6f7b">' + safeLabel(pts[pts.length - 1].label) + '</text>';
    svg += '</svg>';
    el.innerHTML = svg;
  }

  // Bars: [{label, value (0-100), detail}]
  function drawBars(el) {
    var data;
    try { data = JSON.parse(el.getAttribute('data-bars')); } catch (err) { return; }
    if (!data || !data.length) {
      el.innerHTML = '<p class="muted small">No sessions have happened yet.</p>';
      return;
    }
    var W = 640, H = 190, PL = 34, PR = 8, PT = 16, PB = 26;
    var n = data.length;
    var slot = (W - PL - PR) / n;
    var bw = Math.min(40, slot * 0.7);
    var step = Math.max(1, Math.ceil(n / 12));
    function y(v) { return PT + (H - PT - PB) * (1 - v / 100); }
    var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="Attendance chart">';
    [0, 50, 100].forEach(function (g) {
      svg += '<line x1="' + PL + '" y1="' + y(g) + '" x2="' + (W - PR) + '" y2="' + y(g) + '" stroke="#e3ebf0"/>';
      svg += '<text x="2" y="' + (y(g) + 4) + '" font-size="10" fill="#5d6f7b">' + g + '%</text>';
    });
    data.forEach(function (d, i) {
      var cx = PL + slot * i + slot / 2;
      var v = Math.max(0, Math.min(100, Number(d.value) || 0));
      var color = v >= 80 ? '#1f9d55' : (v >= 50 ? '#e0a100' : '#c0392b');
      var h = (H - PB) - y(v);
      svg += '<rect x="' + (cx - bw / 2).toFixed(1) + '" y="' + y(v).toFixed(1) + '" width="' + bw.toFixed(1) + '" height="' + Math.max(h, 1).toFixed(1) + '" rx="3" fill="' + color + '"><title>' + safeLabel(d.label) + ': ' + safeLabel(d.detail) + '</title></rect>';
      if (n <= 14) {
        svg += '<text x="' + cx.toFixed(1) + '" y="' + (y(v) - 3).toFixed(1) + '" font-size="10" text-anchor="middle" fill="#17252f">' + Math.round(v) + '</text>';
      }
      if (i % step === 0 || i === n - 1) {
        svg += '<text x="' + cx.toFixed(1) + '" y="' + (H - 8) + '" font-size="10" text-anchor="middle" fill="#5d6f7b">' + safeLabel(d.label) + '</text>';
      }
    });
    svg += '</svg>';
    el.innerHTML = svg;
  }

  // Team chart: bold team average; optionally one colored line per swimmer with a key,
  // a hover tooltip, and click/tap to isolate one swimmer.
  function athleteColor(i) {
    return 'hsl(' + Math.round((i * 137.508) % 360) + ',68%,' + (i % 2 ? 40 : 50) + '%)';
  }

  function drawMulti(el, showAthletes) {
    var cfg;
    try { cfg = JSON.parse(el.getAttribute('data-chart')); } catch (err) { return; }
    var labels = (cfg && cfg.labels) || [];
    var series = (cfg && cfg.series) || [];
    var unit = (cfg && cfg.unit) || '';
    el.classList.remove('has-focus');
    if (labels.length < 2) {
      el.innerHTML = '<p class="muted small">Needs this lift logged in 2 or more sessions to draw a trend.</p>';
      return;
    }
    var teamSeries = series.filter(function (s) { return s.team; });
    var aths = series.filter(function (s) { return !s.team; });
    var W = 640, H = 260, PL = 46, PR = 14, PT = 14, PB = 30;
    var vis = teamSeries.concat(showAthletes ? aths : []);
    var min = Infinity, max = -Infinity;
    vis.forEach(function (s) {
      s.values.forEach(function (v) {
        if (v !== null) { if (v < min) { min = v; } if (v > max) { max = v; } }
      });
    });
    if (!isFinite(min)) { el.innerHTML = '<p class="muted small">No data yet.</p>'; return; }
    if (min === max) { min = Math.max(0, min - 1); max = max + 1; }
    var pad = (max - min) * 0.08;
    min = Math.max(0, min - pad); max = max + pad;
    function x(i) { return PL + (W - PL - PR) * (i / (labels.length - 1)); }
    function y(v) { return PT + (H - PT - PB) * (1 - (v - min) / (max - min)); }
    function path(values) {
      var d = '', pen = false;
      values.forEach(function (v, i) {
        if (v === null) { pen = false; return; }
        d += (pen ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(v).toFixed(1) + ' ';
        pen = true;
      });
      return d;
    }

    var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="Team progress chart">';
    for (var g = 0; g <= 4; g++) {
      var gv = min + (max - min) * (g / 4);
      svg += '<line x1="' + PL + '" y1="' + y(gv).toFixed(1) + '" x2="' + (W - PR) + '" y2="' + y(gv).toFixed(1) + '" stroke="#e3ebf0"/>';
      svg += '<text x="4" y="' + (y(gv) + 4).toFixed(1) + '" font-size="10" fill="#5d6f7b">' + Math.round(gv) + '</text>';
    }
    if (showAthletes) {
      aths.forEach(function (s, ai) {
        var c = athleteColor(ai);
        var d = path(s.values);
        svg += '<g class="aline" data-i="' + ai + '">';
        svg += '<path class="vis" d="' + d + '" fill="none" stroke="' + c + '" stroke-linejoin="round"/>';
        s.values.forEach(function (v, i) {
          if (v !== null) { svg += '<circle cx="' + x(i).toFixed(1) + '" cy="' + y(v).toFixed(1) + '" r="3" fill="' + c + '"/>'; }
        });
        svg += '</g>';
      });
    }
    teamSeries.forEach(function (s) {
      svg += '<path d="' + path(s.values) + '" fill="none" stroke="#1B3A5C" stroke-width="3.5" stroke-linejoin="round" stroke-linecap="round" pointer-events="none"/>';
      s.values.forEach(function (v, i) {
        if (v !== null) { svg += '<circle cx="' + x(i).toFixed(1) + '" cy="' + y(v).toFixed(1) + '" r="4" fill="#1B3A5C" pointer-events="none"/>'; }
      });
    });
    var step = Math.max(1, Math.ceil(labels.length / 8));
    labels.forEach(function (lb, i) {
      if (i % step === 0 || i === labels.length - 1) {
        svg += '<text x="' + x(i).toFixed(1) + '" y="' + (H - 8) + '" font-size="10" text-anchor="middle" fill="#5d6f7b">' + safeLabel(lb) + '</text>';
      }
    });
    svg += '</svg>';
    el.innerHTML = svg;

    if (showAthletes) { attachAthleteUI(el, aths, labels, unit, W, H, PL, PR, x, y); }
  }

  function attachAthleteUI(el, aths, labels, unit, W, H, PL, PR, xOf, yOf) {
    var svgEl = el.querySelector('svg');
    var pinned = -1;

    var tip = document.createElement('div');
    tip.className = 'mtip';
    tip.style.display = 'none';
    el.appendChild(tip);

    // Color key (names are put in with textContent so they can never be treated as HTML)
    var legend = document.createElement('div');
    legend.className = 'mlegend';
    var teamKey = document.createElement('span');
    teamKey.className = 'lg static';
    var teamSw = document.createElement('span');
    teamSw.className = 'sw';
    teamSw.style.background = '#1B3A5C';
    var teamTx = document.createElement('span');
    teamTx.textContent = 'Team average';
    teamKey.appendChild(teamSw);
    teamKey.appendChild(teamTx);
    legend.appendChild(teamKey);

    var order = aths.map(function (a, i) { return i; }).sort(function (a, b) {
      return String(aths[a].name).localeCompare(String(aths[b].name));
    });
    order.forEach(function (i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'lg';
      b.setAttribute('data-i', String(i));
      var sw = document.createElement('span');
      sw.className = 'sw';
      sw.style.background = athleteColor(i);
      var tx = document.createElement('span');
      tx.textContent = aths[i].name;
      b.appendChild(sw);
      b.appendChild(tx);
      legend.appendChild(b);
    });
    el.appendChild(legend);

    function setFocus(i) {
      if (i >= 0) { el.classList.add('has-focus'); } else { el.classList.remove('has-focus'); }
      var gs = el.querySelectorAll('.aline');
      for (var k = 0; k < gs.length; k++) {
        var on = parseInt(gs[k].getAttribute('data-i'), 10) === i;
        if (on) { gs[k].classList.add('focus'); } else { gs[k].classList.remove('focus'); }
      }
      var lbs = el.querySelectorAll('.lg[data-i]');
      for (var m = 0; m < lbs.length; m++) {
        var lon = parseInt(lbs[m].getAttribute('data-i'), 10) === i;
        if (lon) { lbs[m].classList.add('focus'); } else { lbs[m].classList.remove('focus'); }
      }
    }

    function showTip(i, evt) {
      var rect = svgEl.getBoundingClientRect();
      if (!rect.width) { return; }
      var n = labels.length;
      var relX = (evt.clientX - rect.left) * (W / rect.width);
      var idx = Math.round((relX - PL) / ((W - PL - PR) / (n - 1)));
      idx = Math.max(0, Math.min(n - 1, idx));
      var vals = aths[i].values;
      var pick = -1;
      for (var d = 0; d < n && pick < 0; d++) {
        if (idx - d >= 0 && vals[idx - d] !== null) { pick = idx - d; }
        else if (idx + d < n && vals[idx + d] !== null) { pick = idx + d; }
      }
      if (pick < 0) { return; }
      var v = Math.round(vals[pick] * 10) / 10;
      tip.textContent = aths[i].name + ' \u00b7 ' + labels[pick] + ' \u00b7 ' + v + (unit ? ' ' + unit : '');
      var er = el.getBoundingClientRect();
      var px = evt.clientX - er.left;
      tip.style.display = 'block';
      tip.style.top = (evt.clientY - er.top - 38) + 'px';
      if (px > er.width * 0.6) {
        tip.style.left = '';
        tip.style.right = (er.width - px + 12) + 'px';
      } else {
        tip.style.right = '';
        tip.style.left = (px + 12) + 'px';
      }
    }

    // Which swimmer's line is closest to the pointer? (Lines can be very close together,
    // so we measure distance instead of trusting whichever line happens to be drawn on top.)
    function segDist(px, py, ax, ay, bx, by) {
      var dx = bx - ax, dy = by - ay;
      var len2 = dx * dx + dy * dy;
      var t = len2 ? ((px - ax) * dx + (py - ay) * dy) / len2 : 0;
      t = Math.max(0, Math.min(1, t));
      var cx = ax + t * dx, cy = ay + t * dy;
      return Math.sqrt((px - cx) * (px - cx) + (py - cy) * (py - cy));
    }

    function nearest(evt) {
      var rect = svgEl.getBoundingClientRect();
      if (!rect.width) { return -1; }
      var scale = W / rect.width;
      var vx = (evt.clientX - rect.left) * scale;
      var vy = (evt.clientY - rect.top) * scale;
      var limit = 20 * scale;            // about 20 screen pixels
      var bestI = -1, bestD = limit;
      for (var i = 0; i < aths.length; i++) {
        var vals = aths[i].values;
        for (var k = 0; k < vals.length; k++) {
          if (vals[k] === null) { continue; }
          var ax = xOf(k), ay = yOf(vals[k]);
          var d = Math.sqrt((vx - ax) * (vx - ax) + (vy - ay) * (vy - ay));
          if (d < bestD) { bestD = d; bestI = i; }
          if (k + 1 < vals.length && vals[k + 1] !== null) {
            d = segDist(vx, vy, ax, ay, xOf(k + 1), yOf(vals[k + 1]));
            if (d < bestD) { bestD = d; bestI = i; }
          }
        }
      }
      return bestI;
    }

    svgEl.addEventListener('mousemove', function (e) {
      var i = nearest(e);
      if (i >= 0) {
        svgEl.style.cursor = 'pointer';
        setFocus(i);
        showTip(i, e);
      } else {
        svgEl.style.cursor = 'default';
        tip.style.display = 'none';
        setFocus(pinned);
      }
    });
    svgEl.addEventListener('mouseleave', function () {
      tip.style.display = 'none';
      setFocus(pinned);
    });
    svgEl.addEventListener('click', function (e) {
      var i = nearest(e);
      if (i >= 0) {
        pinned = (pinned === i) ? -1 : i;
        setFocus(pinned);
        showTip(i, e);
      } else {
        pinned = -1;
        tip.style.display = 'none';
        setFocus(-1);
      }
    });

    var lbs = legend.querySelectorAll('.lg[data-i]');
    for (var m = 0; m < lbs.length; m++) {
      (function (btn) {
        var i = parseInt(btn.getAttribute('data-i'), 10);
        btn.addEventListener('mouseenter', function () { setFocus(i); });
        btn.addEventListener('mouseleave', function () { setFocus(pinned); });
        btn.addEventListener('click', function () {
          pinned = (pinned === i) ? -1 : i;
          setFocus(pinned);
        });
      })(lbs[m]);
    }

  }
})();
