/* Gauguin — home del sito: comparsa delle fasce, aperto/chiuso, barra in basso,
   Come arrivare. Config iniettata da WordPress in window.GXHOME. */
(function(){
  "use strict";
  var CFG = window.GXHOME || {};

  (function(){
    var voci = document.querySelectorAll('.appare');
    if (!('IntersectionObserver' in window)) {
      voci.forEach(function(el){ el.classList.add('vista'); });
      return;
    }
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(v){ if (v.isIntersecting){ v.target.classList.add('vista'); io.unobserve(v.target); } });
    }, {threshold:.12, rootMargin:'0px 0px -40px 0px'});
    voci.forEach(function(el){ io.observe(el); });
  })();

  /* Aperto o chiuso adesso. La coda dopo mezzanotte appartiene al servizio
     della sera prima. La pagina e' in cache: il calcolo va fatto qui. */
  (function(){
    var box = document.getElementById('stato');
    // wp_localize_script trasforma i numeri in testo ("1110", "30"): vanno riconvertiti,
    // altrimenti "30" <= "1110" e falso (confronto tra parole) e dopo le 18:30 risultava chiuso.
    var apre = parseInt(CFG.open, 10), chiude = parseInt(CFG.close, 10);
    if (!box || isNaN(apre) || isNaN(chiude)) return;
    var parola = box.querySelector('.parola'), coda = box.querySelector('.coda');
    var giorni = ['domenica','lunedì','martedì','mercoledì','giovedì','venerdì','sabato'];
    var chiusi = Array.isArray(CFG.closed) ? CFG.closed.map(Number) : [];
    var oltre = chiude <= apre; // oltre: chiude dopo mezzanotte
    var pad = function(n){ return (n < 10 ? '0' : '') + n; };
    var hhmm = function(m){ return pad(Math.floor(m/60)) + ':' + pad(m%60); };
    var isoGiorno = function(d){ return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()); };
    var aFerie = function(iso){ return !!CFG.vacFrom && iso >= CFG.vacFrom && iso <= CFG.vacTo; };
    var chiusoIl = function(d){ return chiusi.indexOf(d.getDay()) !== -1 || aFerie(isoGiorno(d)); };

    // Su telefono la .coda e' nascosta: la .parola deve bastare da sola.
    function aggiorna(){
      var ora = new Date(), m = ora.getHours()*60 + ora.getMinutes();
      var sera = new Date(ora);
      if (oltre && m < chiude) sera.setDate(sera.getDate() - 1);   // servizio della sera prima
      var dentro = oltre ? (m >= apre || m < chiude) : (m >= apre && m < chiude);
      var aperto = dentro && !chiusoIl(sera);
      box.classList.toggle('chiuso', !aperto);
      if (aperto) {
        parola.textContent = 'Aperti ora';
        coda.textContent = ' · fino alle ' + hhmm(chiude);
      } else if (m < apre && !chiusoIl(ora)) {
        parola.textContent = 'Apriamo alle ' + hhmm(apre);
        coda.textContent = '';
      } else {
        var d = 1, g = new Date(ora);
        g.setDate(g.getDate() + 1);
        while (chiusoIl(g) && d < 30) { g.setDate(g.getDate() + 1); d++; }
        var quando = d === 1 ? 'domani' : (d < 7 ? giorni[g.getDay()] : 'il ' + g.getDate() + '/' + (g.getMonth()+1));
        parola.textContent = aFerie(isoGiorno(ora)) ? 'Chiusi per ferie' : (chiusoIl(ora) ? 'Oggi chiusi' : 'Riapriamo ' + quando);
        coda.textContent = chiusoIl(ora) ? ' · riapriamo ' + quando + ' alle ' + hhmm(apre) : ' · alle ' + hhmm(apre);
      }
      box.hidden = false;
    }
    aggiorna(); setInterval(aggiorna, 60000);
  })();

  /* La barra in basso spunta quando l'apertura e' fuori schermo: sotto, i due
     pulsanti restano a portata di pollice senza risalire la pagina. */
  (function(){
    var barra = document.getElementById('barraBasso');
    var apertura = document.querySelector('.apertura .azioni') || document.querySelector('.apertura');
    if (!barra || !apertura || !('IntersectionObserver' in window)) return;
    new IntersectionObserver(function(v){
      barra.classList.toggle('su', !v[0].isIntersecting);
    }, {threshold:0}).observe(apertura);
  })();

  /* Galleria: scorre piano da sola, come un nastro senza fine (le foto sono
     duplicate una volta). Si ferma quando la tocchi o ci passi sopra col
     mouse e riparte dopo qualche secondo; ferma del tutto con "riduci
     movimento" e quando non e' sullo schermo. */
  (function(){
    var s = document.querySelector('.galleria .striscia');
    if (!s) return;
    var originali = Array.prototype.slice.call(s.children);
    if (originali.length < 3) return;
    originali.forEach(function(a){
      var c = a.cloneNode(true);
      c.setAttribute('aria-hidden', 'true'); c.tabIndex = -1;
      s.appendChild(c);
    });
    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var pos = 0, fermo = false, visibile = false, timer = null, ultimoT = 0;
    var giro = function(){ return originali[originali.length - 1].nextElementSibling.offsetLeft - originali[0].offsetLeft; };
    function passo(t){
      var dt = ultimoT ? Math.min(t - ultimoT, 50) : 16; ultimoT = t;
      if (!fermo && visibile) {
        pos += dt * 0.022;                       // ~22 px al secondo
        var g = giro();
        if (g > 0 && pos >= g) pos -= g;
        s.scrollLeft = pos;
      }
      requestAnimationFrame(passo);
    }
    function ferma(){ fermo = true; clearTimeout(timer); }
    function riparti(ms){
      clearTimeout(timer);
      timer = setTimeout(function(){ pos = s.scrollLeft; fermo = false; }, ms);
    }
    s.addEventListener('pointerdown', ferma);
    s.addEventListener('touchstart', ferma, {passive:true});
    s.addEventListener('wheel', function(){ ferma(); riparti(3000); }, {passive:true});
    s.addEventListener('mouseenter', ferma);
    s.addEventListener('mouseleave', function(){ riparti(800); });
    s.addEventListener('touchend', function(){ riparti(3500); }, {passive:true});
    s.addEventListener('pointerup', function(e){ if (e.pointerType !== 'mouse') riparti(3500); });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function(v){ visibile = v[0].isIntersecting; }, {threshold:0}).observe(s);
    } else { visibile = true; }
    requestAnimationFrame(passo);
  })();

  /* Galleria: un tocco apre la foto grande; tocco ovunque o Esc per chiudere.
     Senza <dialog> (browser vecchi) il link apre semplicemente la foto. */
  (function(){
    var v = document.getElementById('visore');
    if (!v || typeof v.showModal !== 'function') return;
    var img = v.querySelector('img');
    document.querySelectorAll('.galleria .scatto').forEach(function(a){
      a.addEventListener('click', function(e){
        e.preventDefault();
        img.src = a.href;
        v.showModal();
      });
    });
    v.addEventListener('click', function(){ v.close(); });
    v.addEventListener('close', function(){ img.removeAttribute('src'); });
  })();

  /* I ricordi dei clienti: uno a caso, e un altro a richiesta. Si salta solo
     quello appena mostrato e quelli senza nemmeno tre lettere (un'emoji sola). */
  (function(){
    var box = document.getElementById('ricordi');
    if (!box || !CFG.ricordo || !window.fetch) return;
    box.hidden = false;   // il form c'e' anche quando non arriva nessun ricordo
    var q = box.querySelector('.ricordo'), testo = q.querySelector('.testo'),
        nome = q.querySelector('.nome'), btn = box.querySelector('.altro');
    var ultimo = '', occupato = false;
    var buono = function(r){
      return r && r.memory && (r.memory.match(/\p{L}/gu) || []).length >= 3 && r.memory !== ultimo;
    };
    function prendi(tentativi){
      return fetch(CFG.ricordo, {credentials:'omit', cache:'no-store'})
        .then(function(res){ return res.status === 200 ? res.json() : null; })
        .then(function(r){ return buono(r) ? r : (tentativi > 1 ? prendi(tentativi - 1) : null); });
    }
    function mostra(primo){
      if (occupato) return; occupato = true;
      if (!primo) q.classList.add('via');
      prendi(4).then(function(r){
        if (!r || !r.memory) { occupato = false; q.classList.remove('via'); return; }
        setTimeout(function(){
          ultimo = r.memory;
          testo.textContent = r.memory.trim();
          nome.textContent = r.name || '';
          q.classList.toggle('lungo', r.memory.length > 280);
          q.hidden = false; btn.hidden = false;
          q.classList.remove('via');
          occupato = false;
        }, primo ? 0 : 350);
      }).catch(function(){ occupato = false; q.classList.remove('via'); });
    }
    btn.addEventListener('click', function(){ mostra(false); });
    mostra(true);

    /* «Lascia il tuo ricordo»: form chiuso finche' non lo chiedi. Va in
       moderazione come quello della vecchia landing (stesso endpoint).
       Niente cookie ne' X-WP-Nonce: con un nonce WP risponde 403. */
    var lascia = box.querySelector('.lascia'), form = box.querySelector('.form-ricordo');
    if (!lascia || !form || !CFG.invia) { if (lascia) lascia.hidden = true; return; }
    var esito = form.querySelector('.esito'), invia = form.querySelector('.invia');
    lascia.addEventListener('click', function(){
      var apri = form.hidden;
      form.hidden = !apri;
      lascia.setAttribute('aria-expanded', apri ? 'true' : 'false');
      if (apri) form.querySelector('input[name="name"]').focus({preventScroll:false});
    });
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var n = form.elements['name'].value.trim(), m = form.elements['memory'].value.trim();
      if (!n || !m) { esito.textContent = 'Scrivi il tuo nome e il ricordo.'; return; }
      invia.disabled = true; esito.textContent = 'Invio…';
      fetch(CFG.invia, {
        method:'POST', credentials:'omit',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({name:n, memory:m, website:form.elements['website'].value})
      }).then(function(res){
        if (!res.ok) throw new Error(res.status);
        form.reset();
        form.querySelectorAll('label,.invia,.nota').forEach(function(el){ el.hidden = true; });
        esito.textContent = 'Grazie, ' + n + '! Il tuo ricordo è arrivato: lo pubblichiamo appena lo leggiamo.';
      }).catch(function(){
        invia.disabled = false;
        esito.textContent = 'Non è partito, riprova tra poco.';
      });
    });
  })();

  /* Come arrivare: su iPhone, iPad e Mac conviene Apple Maps, che apre l'app;
     altrove resta Google Maps, che su Android apre la sua. Stesso punto. */
  (function(){
    if (!CFG.lat || !CFG.lng) return;
    var apple = /iPhone|iPad|iPod|Macintosh/.test(navigator.userAgent) ||
                (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (!apple) return;
    var url = 'https://maps.apple.com/?daddr=' + CFG.lat + ',' + CFG.lng + '&q=' +
              encodeURIComponent('Gauguin Pizzeria Birreria');
    document.querySelectorAll('a.mappa').forEach(function(a){ a.href = url; });
  })();
})();
