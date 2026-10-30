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
