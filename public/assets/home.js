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
    if (!box || CFG.open == null || CFG.close == null) return;
    var parola = box.querySelector('.parola'), coda = box.querySelector('.coda');
    var giorni = ['domenica','lunedì','martedì','mercoledì','giovedì','venerdì','sabato'];
    var chiusi = Array.isArray(CFG.closed) ? CFG.closed : [];
    var apre = CFG.open, chiude = CFG.close, oltre = chiude <= apre; // oltre: chiude dopo mezzanotte
    var pad = function(n){ return (n < 10 ? '0' : '') + n; };
    var hhmm = function(m){ return pad(Math.floor(m/60)) + ':' + pad(m%60); };
    var isoGiorno = function(d){ return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()); };
    var aFerie = function(iso){ return !!CFG.vacFrom && iso >= CFG.vacFrom && iso <= CFG.vacTo; };
    var chiusoIl = function(d){ return chiusi.indexOf(d.getDay()) !== -1 || aFerie(isoGiorno(d)); };

    function aggiorna(){
      var ora = new Date(), m = ora.getHours()*60 + ora.getMinutes();
      var sera = new Date(ora);
      if (oltre && m < chiude) sera.setDate(sera.getDate() - 1);   // servizio della sera prima
      var dentro = oltre ? (m >= apre || m < chiude) : (m >= apre && m < chiude);
      var aperto = dentro && !chiusoIl(sera);
      box.classList.toggle('chiuso', !aperto);
      if (aperto) {
        parola.textContent = 'Aperto ora';
        coda.textContent = ' · fino alle ' + hhmm(chiude);
      } else {
        parola.textContent = 'Chiuso adesso';
        if (m < apre && !chiusoIl(ora)) {
          coda.textContent = ' · apre oggi alle ' + hhmm(apre);
        } else {
          var d = 1, g = new Date(ora);
          g.setDate(g.getDate() + 1);
          while (chiusoIl(g) && d < 30) { g.setDate(g.getDate() + 1); d++; }
          var quando = d === 1 ? 'domani' : (d < 7 ? giorni[g.getDay()] : 'il ' + g.getDate() + '/' + (g.getMonth()+1));
          coda.textContent = ' · apre ' + quando + ' alle ' + hhmm(apre);
        }
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
          box.hidden = false;
          q.classList.remove('via');
          occupato = false;
        }, primo ? 0 : 350);
      }).catch(function(){ occupato = false; q.classList.remove('via'); });
    }
    btn.addEventListener('click', function(){ mostra(false); });
    mostra(true);
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
