/* Gauguin 30 Anni — landing: countdown, muro dei ricordi, form.
   Config iniettata da WordPress in window.GX30. */
(function(){
  "use strict";
  var CFG = window.GX30 || {};
  var ev = CFG.event || {y:2026, mo:10, d:15, h:19, mi:0};
  var seeds = Array.isArray(CFG.seeds) ? CFG.seeds : [];
  var published = Array.isArray(CFG.published) ? CFG.published : [];
  var base = seeds.concat(published); // bigliettini iniziali + ricordi approvati

  /* ---------- Countdown ---------- */
  var target = new Date(ev.y, (ev.mo - 1), ev.d, ev.h, ev.mi, 0).getTime();
  var elDays = document.getElementById('gx-days'),
      elHours = document.getElementById('gx-hours'),
      elMins = document.getElementById('gx-mins'),
      elSecs = document.getElementById('gx-secs');
  function pad(n){ return String(n).padStart(2,'0'); }
  var celebrated = false, timer;
  function tick(){
    if (!elDays) return;
    if (Date.now() >= target) { celebrate(); return; }
    var diff = Math.max(0, target - Date.now());
    var d = Math.floor(diff/86400000); diff -= d*86400000;
    var h = Math.floor(diff/3600000); diff -= h*3600000;
    var m = Math.floor(diff/60000); diff -= m*60000;
    var s = Math.floor(diff/1000);
    elDays.textContent = String(d);
    elHours.textContent = pad(h);
    elMins.textContent = pad(m);
    if (elSecs.textContent !== pad(s)) {
      elSecs.textContent = pad(s);
      elSecs.style.animation='none'; void elSecs.offsetWidth; elSecs.style.animation='';
    }
  }

  /* ---------- Gran finale: countdown scaduto ---------- */
  function celebrate(){
    if (celebrated) return; celebrated = true;
    if (timer) clearInterval(timer);
    var cd = document.getElementById('gx-countdown');
    if (cd) {
      cd.classList.add('gx-party');
      cd.innerHTML =
        '<div class="gx-party-emoji">🎉🥳🍕🍺🎊</div>' +
        '<div class="gx-party-title">BUON 30°, GAUGUIN!</div>' +
        '<div class="gx-party-sub">1996 — 2026 · oggi si festeggia</div>';
    }
    startConfetti();
  }

  function startConfetti(){
    var c = document.createElement('canvas');
    c.className = 'gx-confetti';
    document.body.appendChild(c);
    var ctx = c.getContext('2d'), W, H;
    function resize(){ W = c.width = window.innerWidth; H = c.height = window.innerHeight; }
    resize(); window.addEventListener('resize', resize);
    var colors = ['#9E152A','#A6182D','#F7EDDD','#FFFFFF','#F0BFC5','#FFD27D','#7C0F20'];
    function mk(){ return {x:Math.random()*W, y:Math.random()*-H, w:6+Math.random()*8, h:9+Math.random()*12,
      c:colors[Math.floor(Math.random()*colors.length)], vy:2+Math.random()*4.5, vx:-1.4+Math.random()*2.8,
      rot:Math.random()*6.28, vr:-0.25+Math.random()*0.5}; }
    var P = []; for (var i=0;i<170;i++) P.push(mk());
    function loop(){
      ctx.clearRect(0,0,W,H);
      for (var i=0;i<P.length;i++){
        var p = P[i];
        p.y += p.vy; p.x += p.vx; p.rot += p.vr;
        if (p.y > H + 24){ p.y = -24; p.x = Math.random()*W; }
        ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rot);
        ctx.fillStyle = p.c; ctx.fillRect(-p.w/2, -p.h/2, p.w, p.h); ctx.restore();
      }
      requestAnimationFrame(loop);
    }
    loop();
  }

  timer = setInterval(tick, 1000);
  tick();

  /* ---------- Muro dei ricordi (bigliettini) ---------- */
  var palette = [
    {bg:'#FFFFFF', fg:'#9E152A', sub:'#B33547', bd:''},
    {bg:'#F7EDDD', fg:'#9E152A', sub:'#A8705A', bd:''},
    {bg:'#9E152A', fg:'#FFFFFF', sub:'#F0BFC5', bd:'rgba(255,255,255,.4)'}
  ];
  var anims = ['gx-float-a','gx-float-b','gx-float-c'];
  var slots = [
    {side:'l',x:1,t:40}, {side:'r',x:1,t:18},
    {side:'l',x:4,t:250}, {side:'r',x:3,t:226},
    {side:'l',x:0,t:458}, {side:'r',x:0,t:432},
    {side:'l',x:5,t:632}, {side:'r',x:4,t:618}
  ];
  var cloud = document.getElementById('gx-cloud');
  var MAX_CARDS = 8; // mostra al massimo 8 bigliettini, scelti a caso ad ogni visita
  function shuffle(a){ for (var i=a.length-1;i>0;i--){ var j=Math.floor(Math.random()*(i+1)); var t=a[i]; a[i]=a[j]; a[j]=t; } return a; }

  function makeNote(m, i, isNew){
    var c = palette[i % palette.length];
    var slot = slots[i % slots.length];
    var dur = 7 + (i % 5);
    var el = document.createElement('div');
    el.className = 'gx-note';
    el.style[slot.side === 'l' ? 'left' : 'right'] = slot.x + '%';
    el.style.top = slot.t + 'px';
    el.style.background = c.bg;
    el.style.color = c.fg;
    if (c.bd) el.style.border = '1px solid ' + c.bd;
    el.style.animation = (isNew ? 'gx-pop .5s ease both, ' : '') + anims[i%3] + ' ' + dur + 's ease-in-out infinite';
    el.style.animationDelay = (isNew ? '0s, ' : '') + (i*0.4) + 's';
    var t = document.createElement('div'); t.className='gx-note-text'; t.textContent = '“'+m.memory+'”';
    var n = document.createElement('div'); n.className='gx-note-name'; n.style.color=c.sub; n.textContent = m.name;
    el.appendChild(t); el.appendChild(n);
    return el;
  }
  function renderCloud(){
    if (!cloud) return;
    cloud.innerHTML='';
    var mine = [];
    try { mine = JSON.parse(localStorage.getItem('gauguin_ricordi')||'[]'); } catch(e){}
    // selezione casuale (varia ad ogni caricamento) + il proprio ricordo davanti
    var pool = shuffle(base.slice()).slice(0, MAX_CARDS);
    var all = mine.concat(pool);
    all.forEach(function(m,i){ cloud.appendChild(makeNote(m, i, i < mine.length)); });
  }
  renderCloud();

  /* ---------- Form ricordi ---------- */
  var form = document.getElementById('gx-form'),
      thanks = document.getElementById('gx-thanks'),
      nameEl = document.getElementById('gx-name'),
      memEl = document.getElementById('gx-memory'),
      hpEl = document.getElementById('gx-website'),
      msgEl = document.getElementById('gx-form-msg'),
      submit = document.getElementById('gx-submit');

  function validate(){ if (submit) submit.disabled = !(nameEl.value.trim() && memEl.value.trim()); }
  if (nameEl) nameEl.addEventListener('input', validate);
  if (memEl) memEl.addEventListener('input', validate);

  function setMsg(text, isError){
    if (!msgEl) return;
    msgEl.textContent = text || '';
    msgEl.classList.toggle('is-error', !!isError);
  }

  if (form) form.addEventListener('submit', function(e){
    e.preventDefault();
    var name = nameEl.value.trim(), memory = memEl.value.trim();
    if (!name || !memory) return;
    submit.disabled = true;
    setMsg('Invio in corso…', false);

    var payload = { name:name, memory:memory, website: hpEl ? hpEl.value : '' };

    function onSuccess(){
      // salva localmente cosi' il proprio ricordo svolazza nella hero
      try {
        var k='gauguin_ricordi';
        var arr = JSON.parse(localStorage.getItem(k)||'[]');
        arr.push({name:name, memory:memory}); localStorage.setItem(k, JSON.stringify(arr));
      } catch(e){}
      form.classList.add('gx-hidden');
      thanks.classList.remove('gx-hidden');
      renderCloud();
    }

    if (!CFG.restUrl) { onSuccess(); return; } // fallback (anteprima senza backend)

    fetch(CFG.restUrl, {
      method:'POST',
      headers:{'Content-Type':'application/json', 'X-WP-Nonce': CFG.nonce || ''},
      body: JSON.stringify(payload)
    }).then(function(r){
      if (!r.ok) throw new Error('http');
      return r.json();
    }).then(function(){
      onSuccess();
    }).catch(function(){
      submit.disabled = false;
      setMsg('Ops, qualcosa è andato storto. Riprova tra poco.', true);
    });
  });

  /* ---------- Galleria giustificata (righe a tutta larghezza) ---------- */
  function justifyGallery(){
    var g = document.getElementById('gx-gallery'); if (!g) return;
    var imgs = Array.prototype.slice.call(g.querySelectorAll('img')); if (!imgs.length) return;
    var pad = 6, gap = 6;
    var cw = g.clientWidth - pad * 2;
    if (cw <= 0) return;
    var th = cw < 600 ? 140 : 220; // altezza riga target
    var data = imgs.map(function(img){
      var r = (img.naturalWidth && img.naturalHeight) ? (img.naturalWidth / img.naturalHeight) : 1.5;
      return { img: img, r: r };
    });
    var row = [], sumR = 0;
    function flush(last){
      var gaps = (row.length - 1) * gap;
      var h = last ? th : ((cw - gaps) / sumR);
      row.forEach(function(d){
        d.img.style.width = Math.floor(d.r * h) + 'px';
        d.img.style.height = Math.round(h) + 'px';
      });
      row = []; sumR = 0;
    }
    data.forEach(function(d){
      row.push(d); sumR += d.r;
      var gaps = (row.length - 1) * gap;
      if (sumR * th + gaps >= cw) flush(false);
    });
    if (row.length) flush(true);
  }
  (function initGallery(){
    var g = document.getElementById('gx-gallery'); if (!g) return;
    var imgs = Array.prototype.slice.call(g.querySelectorAll('img')); if (!imgs.length) return;
    var pending = imgs.length;
    function done(){ if (--pending <= 0) justifyGallery(); }
    imgs.forEach(function(img){
      if (img.complete && img.naturalWidth) done();
      else { img.addEventListener('load', done); img.addEventListener('error', done); }
    });
    var t; window.addEventListener('resize', function(){ clearTimeout(t); t = setTimeout(justifyGallery, 150); });
    window.addEventListener('load', justifyGallery);
  })();
})();
