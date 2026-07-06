/* Gauguin 30 Anni — popup teaser del muro dei ricordi (solo home).
   Mostra un ricordo casuale + invito a lasciare il proprio. Una volta a sessione. */
(function () {
  var C = window.GX30T || {};
  if (!C.rest) return;

  var SEEN = 'gx30_teaser';
  try { if (sessionStorage.getItem(SEEN) === '1') return; } catch (e) {}

  function markSeen() { try { sessionStorage.setItem(SEEN, '1'); } catch (e) {} }

  function show(data) {
    if (!data || !data.memory) return;

    var box = document.createElement('div');
    box.className = 'gx30t';
    box.setAttribute('role', 'complementary');
    box.setAttribute('aria-label', C.kicker || 'Ricordi');
    box.innerHTML =
      '<button type="button" class="gx30t-x" aria-label="Chiudi">×</button>' +
      '<div class="gx30t-kicker"></div>' +
      '<p class="gx30t-quote"></p>' +
      '<div class="gx30t-name"></div>' +
      '<a class="gx30t-cta" href="' + C.landing + '"></a>';

    box.querySelector('.gx30t-kicker').textContent = C.kicker || '';
    box.querySelector('.gx30t-quote').textContent = '« ' + data.memory + ' »';
    box.querySelector('.gx30t-name').textContent = data.name ? ('— ' + data.name) : '';
    box.querySelector('.gx30t-cta').textContent = C.cta || 'Racconta il tuo ricordo';

    document.body.appendChild(box);
    requestAnimationFrame(function () {
      requestAnimationFrame(function () { box.classList.add('is-in'); });
    });

    function dismiss() {
      markSeen();
      box.classList.remove('is-in');
      setTimeout(function () { if (box.parentNode) box.parentNode.removeChild(box); }, 500);
    }
    box.querySelector('.gx30t-x').addEventListener('click', dismiss);
    // Se clicca la CTA e va al form, non riproporlo in questa sessione.
    box.querySelector('.gx30t-cta').addEventListener('click', markSeen);
  }

  function start() {
    fetch(C.rest, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return (r.status === 204) ? null : r.json(); })
      .then(function (d) { if (d) setTimeout(function () { show(d); }, 1400); })
      .catch(function () {});
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
