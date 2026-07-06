/* Gauguin 30 Anni — generatore immagini social dei ricordi (admin).
   Disegna una card brandizzata su <canvas> e la scarica come PNG.
   Nessun servizio esterno: font e logo del plugin, tutto in locale. */
(function () {
  var CFG = window.GX30SOC || {};
  var COL = {
    b1: '#A6182D', b2: '#9E152A', bDark: '#7C0F20',
    cream: '#F7EDDD', ink: '#3A1318', pink: '#F0C9CF'
  };

  var assetsPromise = null;
  function loadAssets() {
    if (assetsPromise) return assetsPromise;
    var F = CFG.fonts || {};
    var faces = [];
    if (window.FontFace) {
      faces = [
        new FontFace('GX30Anton', 'url(' + F.anton + ')', { weight: '400' }),
        new FontFace('GX30Spectral', 'url(' + F.spectral + ')', { weight: '600' }),
        new FontFace('GX30SpectralIt', 'url(' + F.spectralItalic + ')', { weight: '400' })
      ];
    }
    var fontJobs = faces.map(function (f) {
      return f.load().then(function (l) { document.fonts.add(l); }).catch(function () {});
    });
    var imgJob = new Promise(function (res) {
      if (!CFG.lockup) { res(null); return; }
      var im = new Image();
      im.onload = function () { res(im); };
      im.onerror = function () { res(null); };
      im.src = CFG.lockup;
    });
    assetsPromise = Promise.all([Promise.all(fontJobs), imgJob]).then(function (r) {
      return { logo: r[1] };
    });
    return assetsPromise;
  }

  function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  function wrapText(ctx, text, maxWidth) {
    var words = String(text).split(/\s+/);
    var lines = [], cur = '';
    for (var i = 0; i < words.length; i++) {
      var test = cur ? cur + ' ' + words[i] : words[i];
      if (cur && ctx.measureText(test).width > maxWidth) {
        lines.push(cur); cur = words[i];
      } else {
        cur = test;
      }
    }
    if (cur) lines.push(cur);
    return lines;
  }

  function ls(ctx, v) { if ('letterSpacing' in ctx) ctx.letterSpacing = v; }

  function draw(canvas, data, fmt) {
    return loadAssets().then(function (A) {
      var W = 1080, H = (fmt === 'story') ? 1920 : 1080;
      canvas.width = W; canvas.height = H;
      var ctx = canvas.getContext('2d');
      ctx.textAlign = 'center';
      ctx.textBaseline = 'alphabetic';

      // Sfondo bordeaux + highlight in alto
      var g = ctx.createLinearGradient(0, 0, 0, H);
      g.addColorStop(0, COL.b1); g.addColorStop(1, COL.b2);
      ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
      var rg = ctx.createRadialGradient(W / 2, H * 0.02, 0, W / 2, H * 0.02, W * 0.72);
      rg.addColorStop(0, 'rgba(255,255,255,0.10)');
      rg.addColorStop(1, 'rgba(255,255,255,0)');
      ctx.fillStyle = rg; ctx.fillRect(0, 0, W, H);

      // Logo lockup
      var logoTop = (fmt === 'story') ? 160 : 100;
      var logoMaxW = (fmt === 'story') ? 640 : 560;
      var logoBottom = logoTop;
      if (A.logo && A.logo.width) {
        var lw = logoMaxW, lh = lw * (A.logo.height / A.logo.width);
        ctx.drawImage(A.logo, (W - lw) / 2, logoTop, lw, lh);
        logoBottom = logoTop + lh;
      }

      var bottomY = H - ((fmt === 'story') ? 160 : 100);
      var invitoBlock = 130;
      var availTop = logoBottom + ((fmt === 'story') ? 96 : 56);
      var availBottom = bottomY - invitoBlock;

      // Adatta la dimensione del testo del ricordo finché il blocco entra nella card
      var margin = 96, cardPad = 62;
      var maxTextW = W - margin * 2 - cardPad * 2;
      var quoteText = '« ' + String(data.memory || '') + ' »';
      var qSize = (fmt === 'story') ? 60 : 54, minSize = 30;
      var nameSize = (fmt === 'story') ? 36 : 32, gapQN = 40, dividerGap = 34;
      var lines, lineH;
      while (qSize >= minSize) {
        ctx.font = '400 ' + qSize + 'px GX30SpectralIt, Georgia, serif';
        lines = wrapText(ctx, quoteText, maxTextW);
        lineH = Math.round(qSize * 1.42);
        var block = lines.length * lineH + dividerGap + gapQN + nameSize;
        if (block <= (availBottom - availTop - cardPad * 2)) break;
        qSize -= 2;
      }
      ctx.font = '400 ' + qSize + 'px GX30SpectralIt, Georgia, serif';
      lineH = Math.round(qSize * 1.42);

      var textBlockH = lines.length * lineH + dividerGap + gapQN + nameSize;
      var cardH = textBlockH + cardPad * 2;
      var cardW = W - margin * 2, cardX = margin;
      var cardY = availTop + Math.max(0, ((availBottom - availTop) - cardH) / 2);

      // Card panna + barra bordeaux in alto
      roundRect(ctx, cardX, cardY, cardW, cardH, 28);
      ctx.fillStyle = COL.cream; ctx.fill();
      ctx.save();
      roundRect(ctx, cardX, cardY, cardW, cardH, 28);
      ctx.clip();
      ctx.fillStyle = COL.b2; ctx.fillRect(cardX, cardY, cardW, 12);
      ctx.restore();

      // Testo del ricordo
      var ty = cardY + cardPad + qSize;
      ctx.fillStyle = COL.ink;
      ctx.font = '400 ' + qSize + 'px GX30SpectralIt, Georgia, serif';
      for (var i = 0; i < lines.length; i++) {
        ctx.fillText(lines[i], W / 2, ty);
        ty += lineH;
      }

      // Divisore
      ty += 6;
      ctx.strokeStyle = COL.b2; ctx.lineWidth = 3;
      ctx.beginPath(); ctx.moveTo(W / 2 - 42, ty); ctx.lineTo(W / 2 + 42, ty); ctx.stroke();
      ty += gapQN;

      // Nome
      ctx.fillStyle = COL.bDark;
      ctx.font = '400 ' + nameSize + 'px GX30Anton, Impact, sans-serif';
      ls(ctx, '2px');
      ctx.fillText(String(data.name || '').toUpperCase(), W / 2, ty + nameSize * 0.8);
      ls(ctx, '0px');

      // Invito in basso
      var ky = bottomY - 54;
      ctx.fillStyle = COL.pink;
      ctx.font = '600 26px GX30Spectral, Georgia, serif';
      ls(ctx, '4px');
      ctx.fillText(String(CFG.invito || '').toUpperCase(), W / 2, ky);
      ls(ctx, '0px');
      ctx.fillStyle = '#fff';
      ctx.font = '400 42px GX30Anton, Impact, sans-serif';
      ls(ctx, '1px');
      ctx.fillText(String(CFG.host || '').toUpperCase(), W / 2, ky + 54);
      ls(ctx, '0px');
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('gx30-soc-modal');
    var canvas = document.getElementById('gx30-soc-canvas');
    if (!modal || !canvas) return;

    var current = { name: '', memory: '', fmt: 'square' };

    function setActiveFmt(fmt) {
      var btns = modal.querySelectorAll('.gx30-soc-fmt');
      Array.prototype.forEach.call(btns, function (b) {
        b.classList.toggle('is-active', b.getAttribute('data-fmt') === fmt);
      });
    }
    function render() { return draw(canvas, current, current.fmt); }
    function openModal(name, memory) {
      current.name = name; current.memory = memory; current.fmt = 'square';
      setActiveFmt('square');
      modal.hidden = false;
      render();
    }
    function closeModal() { modal.hidden = true; }

    document.addEventListener('click', function (e) {
      var t = e.target.closest ? e.target.closest('.gx30-soc-open') : null;
      if (t) { openModal(t.getAttribute('data-name') || '', t.getAttribute('data-memory') || ''); }
    });
    modal.querySelector('.gx30-soc-close').addEventListener('click', closeModal);
    modal.querySelector('.gx30-soc-backdrop').addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) closeModal();
    });
    Array.prototype.forEach.call(modal.querySelectorAll('.gx30-soc-fmt'), function (b) {
      b.addEventListener('click', function () {
        current.fmt = b.getAttribute('data-fmt');
        setActiveFmt(current.fmt);
        render();
      });
    });
    document.getElementById('gx30-soc-download').addEventListener('click', function () {
      render().then(function () {
        canvas.toBlob(function (blob) {
          if (!blob) return;
          var safe = (current.name || 'ricordo').toLowerCase()
            .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'ricordo';
          var a = document.createElement('a');
          a.href = URL.createObjectURL(blob);
          a.download = 'gauguin-ricordo-' + safe + '-' + current.fmt + '.png';
          document.body.appendChild(a); a.click(); document.body.removeChild(a);
          setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
        }, 'image/png');
      });
    });
  });
})();
