/* Gauguin 30 Anni — motore dello slideshow TV.
 *
 * Gira per giorni senza che nessuno lo tocchi, quindi:
 *  - nessun testo va in innerHTML (i ricordi li scrivono i clienti);
 *  - le foto si precaricano e quelle rotte vengono saltate, non lasciano buchi;
 *  - i dati si rinfrescano da soli; se il sito non risponde, si tira avanti
 *    con quelli gia' in memoria invece di mostrare uno schermo nero;
 *  - quando esce una nuova versione del plugin, gli schermi si ricaricano soli.
 */
(function () {
  'use strict';

  var D = window.GX30_TV || {};
  var stage = document.getElementById('gx-tv-stage');
  var mark  = document.getElementById('gx-tv-mark');
  var bar   = document.getElementById('gx-tv-bar-fill');

  var REFRESH_MS = 10 * 60 * 1000;   // ricontrolla i contenuti ogni 10 minuti
  var current = null;                // slide a schermo
  var timer = null;
  var badPhotos = {};                // url che non si caricano: saltati

  /* ------------------------------------------------------------- utilita' */

  function el(tag, cls) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    return n;
  }

  function shuffle(arr) {
    var a = arr.slice();
    for (var i = a.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = a[i]; a[i] = a[j]; a[j] = t;
    }
    return a;
  }

  /* Giro completo prima di ripetersi: nessuna foto due volte di fila. */
  function cycler(getList) {
    var pool = [], i = 0;
    return function next() {
      var list = getList();
      if (!list || !list.length) return null;
      if (i >= pool.length || pool.length !== list.length) {
        pool = shuffle(list);
        i = 0;
      }
      return pool[i++];
    };
  }

  function minutesOf(hhmm, fallback) {
    var m = /^(\d{1,2}):(\d{2})$/.exec(String(hhmm || ''));
    if (!m) return fallback;
    return (parseInt(m[1], 10) % 24) * 60 + (parseInt(m[2], 10) % 60);
  }

  /* Ora di cena: ritmo piu' lento e niente schermate fuori contesto. */
  function isDinner() {
    var now = new Date();
    var cur = now.getHours() * 60 + now.getMinutes();
    var from = minutesOf(D.dinnerFrom, 19 * 60);
    var to   = minutesOf(D.dinnerTo, 23 * 60 + 59);
    if (from === to) return false;
    return from < to ? (cur >= from && cur <= to) : (cur >= from || cur <= to);
  }

  /* --------------------------------------------------------------- sorgenti */

  var nextPhoto = cycler(function () {
    return (D.photos || []).filter(function (u) { return !badPhotos[u]; });
  });
  var nextMemory = cycler(function () { return D.memories || []; });
  var nextClaim  = cycler(function () { return D.claims || []; });
  var nextQr     = cycler(function () {
    var dinner = isDinner();
    return (D.qr || []).filter(function (q) {
      if (q.when === 'dinner') return dinner;
      if (q.when === 'day') return !dinner;
      return true;
    });
  });

  /* Precarica: la foto entra in scena solo quando e' davvero pronta. */
  function loadPhoto(url) {
    return new Promise(function (resolve) {
      var img = new Image();
      img.onload = function () { resolve(true); };
      img.onerror = function () { badPhotos[url] = 1; resolve(false); };
      img.src = url;
    });
  }

  /* --------------------------------------------------------------- le slide */

  function slidePhoto(url, line) {
    var s = el('div', 'gx-slide gx-slide-photo');
    var ph = el('div', 'gx-photo');
    ph.style.backgroundImage = 'url("' + url.replace(/"/g, '%22') + '")';
    s.appendChild(ph);
    s.appendChild(el('div', 'gx-photo-veil'));

    var cap = el('div', 'gx-photo-cap');
    var l = el('div', 'gx-photo-line');
    l.textContent = line || '';
    var t = el('div', 'gx-photo-tag');
    t.textContent = D.footerText || 'Gauguin · dal 1996';
    cap.appendChild(l);
    cap.appendChild(t);
    s.appendChild(cap);
    return s;
  }

  function slideMemory(m) {
    var s = el('div', 'gx-slide gx-slide-memory');
    var wrap = el('div', 'gx-mem');

    var k = el('div', 'gx-kicker');
    k.textContent = D.memKicker || "Trent'anni di ricordi";
    wrap.appendChild(k);

    var card = el('div', 'gx-mem-card');
    var q = el('div', 'gx-mem-quote' + (m.memory.length > 150 ? ' is-long' : ''));
    q.textContent = '“' + m.memory + '”';
    card.appendChild(q);
    if (m.name) {
      var n = el('div', 'gx-mem-name');
      n.textContent = m.name;
      card.appendChild(n);
    }
    wrap.appendChild(card);
    s.appendChild(wrap);
    return s;
  }

  function slideClaim(text) {
    var s = el('div', 'gx-slide gx-slide-claim');
    var wrap = el('div', 'gx-claim');
    var t = el('div', 'gx-claim-text' + (text.length > 42 ? ' is-long' : ''));
    t.textContent = text;
    wrap.appendChild(t);
    wrap.appendChild(el('div', 'gx-claim-rule'));
    var sub = el('div', 'gx-claim-sub');
    sub.textContent = '1996 — 2026';
    wrap.appendChild(sub);
    s.appendChild(wrap);
    return s;
  }

  function eventDate() {
    if (!D.event) return null;
    // 'YYYY-MM-DDTHH:MM' senza fuso: va letto come ora locale, non UTC.
    var m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(D.event);
    if (!m) return null;
    return new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], 0);
  }

  function slideCountdown() {
    var s = el('div', 'gx-slide gx-slide-cd');
    var wrap = el('div', 'gx-cd');

    if (D.logo) {
      var logo = el('img', 'gx-cd-logo');
      logo.src = D.logo;
      logo.alt = '';
      logo.onerror = function () { logo.style.display = 'none'; };
      wrap.appendChild(logo);
    }

    var when = eventDate();
    var diff = when ? (when.getTime() - Date.now()) : -1;

    if (diff > 0) {
      var days  = Math.floor(diff / 86400000);
      var hours = Math.floor((diff % 86400000) / 3600000);
      var mins  = Math.floor((diff % 3600000) / 60000);
      var grid = el('div', 'gx-cd-grid');
      [[days, 'Giorni', true], [hours, 'Ore', false], [mins, 'Minuti', false]].forEach(function (c) {
        var cell = el('div', 'gx-cd-cell' + (c[2] ? ' is-days' : ''));
        var num = el('div', 'gx-cd-num');
        num.textContent = String(c[0]);
        var lab = el('div', 'gx-cd-lab');
        lab.textContent = c[1];
        cell.appendChild(num);
        cell.appendChild(lab);
        grid.appendChild(cell);
      });
      wrap.appendChild(grid);

      var foot = el('div', 'gx-cd-foot');
      foot.textContent = 'ci separano dai nostri trent’anni';
      wrap.appendChild(foot);
    } else {
      // Dopo la festa il countdown non ha piu' senso: resta il grazie.
      var done = el('div', 'gx-claim-text');
      done.textContent = 'TRENT’ANNI INSIEME';
      wrap.appendChild(done);
      var f2 = el('div', 'gx-cd-foot');
      f2.textContent = 'grazie a chi c’è stato, dal 1996 a oggi';
      wrap.appendChild(f2);
    }

    s.appendChild(wrap);
    return s;
  }

  function slideQr(q) {
    var s = el('div', 'gx-slide gx-slide-qr');
    var wrap = el('div', 'gx-qr');

    var card = el('div', 'gx-qr-card');
    var img = el('img');
    img.src = q.img;
    img.alt = '';
    card.appendChild(img);
    wrap.appendChild(card);

    var body = el('div', 'gx-qr-body');
    if (q.title) {
      var t = el('div', 'gx-qr-title');
      t.textContent = q.title;
      body.appendChild(t);
    }
    if (q.text) {
      var p = el('div', 'gx-qr-text');
      p.textContent = q.text;
      body.appendChild(p);
    }
    var h = el('div', 'gx-qr-hint');
    h.textContent = 'inquadra con la fotocamera';
    body.appendChild(h);
    wrap.appendChild(body);

    s.appendChild(wrap);
    return s;
  }

  /* ------------------------------------------------------------- scaletta */

  /* Una slide "da leggere" non segue mai un'altra slide da leggere:
     in mezzo ci va sempre una foto, cosi' l'occhio riposa. */
  function buildOrder() {
    var order = [];
    var fillers = ['memory', 'claim', 'countdown', 'qr'];
    fillers.forEach(function (kind) {
      order.push('photo');
      order.push(kind);
    });
    return order;
  }

  var order = buildOrder();
  var orderIdx = 0;

  function durationFor(kind) {
    var base = D.slideMs || 9000;
    if (isDinner()) base = Math.round(base * 1.2);
    if (kind === 'memory') return Math.round(base * 1.3);
    if (kind === 'claim') return Math.round(base * 0.85);
    if (kind === 'qr') return Math.round(base * 1.45);
    return base;
  }

  /* Costruisce la prossima slide disponibile, saltando i tipi senza contenuto.
     Ritorna null solo se davvero non c'e' nulla da mostrare. */
  function nextSlide(guard) {
    guard = guard || 0;
    if (guard > order.length + 2) return null;

    var kind = order[orderIdx % order.length];
    orderIdx++;

    if (kind === 'photo') {
      var url = nextPhoto();
      if (!url) return nextSlide(guard + 1);
      // La didascalia riusa una frase, se c'e': testo e immagine in coppia.
      var line = (D.claims && D.claims.length) ? nextClaim() : '';
      return { kind: kind, node: slidePhoto(url, line), photo: url };
    }
    if (kind === 'memory') {
      var m = nextMemory();
      if (!m) return nextSlide(guard + 1);
      return { kind: kind, node: slideMemory(m) };
    }
    if (kind === 'claim') {
      var c = nextClaim();
      if (!c) return nextSlide(guard + 1);
      return { kind: kind, node: slideClaim(c) };
    }
    if (kind === 'countdown') {
      if (!D.showCountdown) return nextSlide(guard + 1);
      return { kind: kind, node: slideCountdown() };
    }
    if (kind === 'qr') {
      var q = nextQr();
      if (!q) return nextSlide(guard + 1);
      return { kind: kind, node: slideQr(q) };
    }
    return nextSlide(guard + 1);
  }

  /* ---------------------------------------------------------------- ciclo */

  function setMark(kind) {
    if (!mark) return;
    // Nascosta dove darebbe fastidio; spostata di poco per non bruciare i pixel.
    var hide = (kind === 'photo' || kind === 'qr');
    mark.classList.toggle('is-hidden', hide);
    if (!hide) {
      mark.textContent = D.footerText || 'Gauguin · dal 1996';
      var dx = -Math.round(Math.random() * 14);
      var dy = -Math.round(Math.random() * 10);
      mark.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
    }
  }

  function runBar(ms) {
    if (!bar) return;
    bar.style.transition = 'none';
    bar.style.width = '0%';
    // Forza il reflow, altrimenti la transizione parte dal valore vecchio.
    void bar.offsetWidth;
    bar.style.transition = 'width ' + ms + 'ms linear';
    bar.style.width = '100%';
  }

  function present(slide) {
    stage.appendChild(slide.node);
    // Un frame di stacco: senza, il browser non anima il fade in entrata.
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        slide.node.classList.add('is-in');
      });
    });

    var old = current;
    current = slide;
    setMark(slide.kind);

    if (old) {
      old.node.classList.remove('is-in');
      setTimeout(function () {
        if (old.node.parentNode) old.node.parentNode.removeChild(old.node);
      }, 1400);
    }
  }

  function step() {
    var slide = nextSlide();
    if (!slide) {
      // Niente contenuti: riprova tra poco invece di restare a schermo nero.
      timer = setTimeout(step, 15000);
      return;
    }

    var go = function () {
      present(slide);
      var ms = durationFor(slide.kind);
      runBar(ms);
      timer = setTimeout(step, ms);
    };

    if (slide.photo) {
      loadPhoto(slide.photo).then(function (ok) {
        if (!ok) { step(); return; }   // foto rotta: si passa oltre
        go();
      });
    } else {
      go();
    }
  }

  /* -------------------------------------------------------------- refresh */

  function refresh() {
    if (!D.endpoint) return;
    fetch(D.endpoint, { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || !data.stamp) return;
        // Plugin aggiornato: ricarica per prendere CSS e JS nuovi.
        if (data.stamp !== D.stamp) { location.reload(); return; }
        data.endpoint = D.endpoint;
        D = window.GX30_TV = data;
        badPhotos = {};
      })
      .catch(function () { /* rete giu': si continua con i dati in memoria */ });
  }

  /* ------------------------------------------------------------------ avvio */

  // Tiene lo schermo sveglio dove il browser lo consente.
  function keepAwake() {
    if (!navigator.wakeLock || !navigator.wakeLock.request) return;
    navigator.wakeLock.request('screen').catch(function () {});
  }

  // Un tocco/click manda a schermo intero: utile sui browser delle TV.
  function armFullscreen() {
    var go = function () {
      var r = document.documentElement;
      var fn = r.requestFullscreen || r.webkitRequestFullscreen;
      if (fn) { try { fn.call(r); } catch (e) {} }
    };
    document.addEventListener('click', go, { once: true });
    document.addEventListener('keydown', go, { once: true });
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) keepAwake();
  });

  keepAwake();
  armFullscreen();
  step();
  setInterval(refresh, REFRESH_MS);
})();
