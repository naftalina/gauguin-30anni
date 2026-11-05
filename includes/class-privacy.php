<?php
if (!defined('ABSPATH')) exit;

/**
 * Pagina "Privacy & cookie" nello stile della home: barra col logo, testo
 * leggibile, stesso piede. Prende il posto del tema sulla pagina creata dal
 * plugin Gauguin GDPR (slug privacy-cookie-policy). I dati del titolare
 * arrivano dal pannello di quel plugin, se attivo.
 */
class GX30_Privacy {

    const SLUG    = 'privacy-cookie-policy';
    const UPDATED = '5 novembre 2026'; // data fissa: cambiarla solo quando cambia il testo

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public static function should_render() {
        if (!is_page()) return false;
        $id = (int) get_option('ggdpr_page_id', 0);
        if ($id && get_queried_object_id() === $id) return true;
        return is_page(self::SLUG);
    }

    private static function owner($key, $fallback = '') {
        if (class_exists('GGDPR_Settings')) {
            if ($key === 'email') return GGDPR_Settings::email();
            $v = trim((string) GGDPR_Settings::get($key));
            if ($v !== '') return $v;
        }
        return $fallback;
    }

    public function render() {
        $title = 'Privacy e cookie - Pizzeria Birreria Gauguin';
        $desc  = 'Come la Pizzeria Birreria Gauguin tratta i dati di chi ordina, prenota o lascia un ricordo sul sito, e quali cookie usa.';
        $t = function () use ($title) { return $title; };
        $d = function () use ($desc) { return $desc; };
        add_filter('pre_get_document_title', $t, 99);
        add_filter('wpseo_title', $t, 99);
        add_filter('wpseo_opengraph_title', $t, 99);
        add_filter('wpseo_metadesc', $d, 99);
        add_filter('wpseo_opengraph_desc', $d, 99);

        wp_enqueue_style('gx30-home', GX30_URL . 'public/assets/home.css', [], GX30_VERSION);

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#12100E">
    <?php wp_head(); ?>
</head>
<body <?php body_class('gh-body'); ?>>
<div class="gh">
<div class="bar">
  <a href="<?php echo esc_url(home_url('/')); ?>" class="mark-link" aria-label="Torna alla home"><img class="mark" src="<?php echo esc_url(GX30_Home::img('logo.png')); ?>" alt="Gauguin" width="596" height="212" decoding="async"></a>
  <a class="torna" href="<?php echo esc_url(home_url('/')); ?>">← Torna al sito</a>
</div>
<?php $this->body(); ?>
<?php GX30_Home::footer(); ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
        <?php
    }

    private function body() {
        $company = self::owner('company', 'Gauguin S.R.L.');
        $vat     = self::owner('vat');
        $address = self::owner('address', (string) GX30_Settings::get('footer_address'));
        $email   = self::owner('email', get_option('admin_email'));
        $phone   = self::owner('phone', (string) GX30_Settings::get('footer_phone'));
        $mail    = '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
        ?>
<main class="legale">
  <header class="legale-testa">
    <p class="occhiello">Informativa</p>
    <h1>Privacy <em>e cookie</em></h1>
    <p class="legale-intro">Quali dati ci lasci quando ordini, prenoti o ci racconti un ricordo, cosa ne facciamo e per quanto li teniamo. In breve: solo quello che serve, niente pubblicità, niente statistiche di terzi.</p>
    <p class="legale-data">Aggiornata il <?php echo esc_html(self::UPDATED); ?></p>
  </header>

  <nav class="legale-indice" aria-label="Indice">
    <a href="#titolare">Chi siamo</a>
    <a href="#dati">Cosa raccogliamo</a>
    <a href="#terzi">Chi vede i dati</a>
    <a href="#cookie">Cookie</a>
    <a href="#diritti">I tuoi diritti</a>
  </nav>

  <section id="titolare">
    <h2>Chi siamo</h2>
    <p>Il titolare del trattamento è <strong><?php echo esc_html($company); ?></strong><?php if ($vat): ?>, P.IVA <?php echo esc_html($vat); ?><?php endif; ?><?php if ($address): ?>, <?php echo esc_html($address); ?><?php endif; ?>.</p>
    <p>Per qualsiasi domanda sui tuoi dati scrivi a <?php echo $mail; ?><?php if ($phone): ?> o chiama lo <a href="tel:<?php echo esc_attr(GX30_Settings::footer_phone_tel()); ?>"><?php echo esc_html($phone); ?></a><?php endif; ?>.</p>
  </section>

  <section id="dati">
    <h2>Cosa raccogliamo e perché</h2>
    <div class="legale-voci">
      <div class="voce">
        <h3>Ordini d'asporto</h3>
        <p>Nome, telefono, email, cosa hai ordinato, l'orario di ritiro e le eventuali note.</p>
        <dl>
          <dt>Perché</dt><dd>Preparare l'ordine e contattarti se serve.</dd>
          <dt>Base giuridica</dt><dd>Esecuzione del contratto (art. 6.1.b GDPR).</dd>
          <dt>Per quanto</dt><dd>Fino a 6 mesi, il tempo per gestire eventuali contestazioni sul pagamento.</dd>
        </dl>
      </div>
      <div class="voce">
        <h3>Prenotazioni</h3>
        <p>Nome, telefono, email, giorno, ora, numero di persone ed eventuali note.</p>
        <dl>
          <dt>Perché</dt><dd>Tenerti il tavolo e avvisarti in caso di cambiamenti.</dd>
          <dt>Base giuridica</dt><dd>Misure precontrattuali su tua richiesta (art. 6.1.b GDPR).</dd>
          <dt>Per quanto</dt><dd>Cancellate in automatico 7 giorni dopo la data della prenotazione.</dd>
        </dl>
      </div>
      <div class="voce">
        <h3>Pagamenti online</h3>
        <p>Li gestisce <strong>Stripe</strong>. I dati della carta vanno direttamente a Stripe: noi non li vediamo e non li conserviamo, riceviamo solo l'esito del pagamento.</p>
        <dl>
          <dt>Base giuridica</dt><dd>Esecuzione del contratto (art. 6.1.b GDPR).</dd>
          <dt>Approfondisci</dt><dd><a href="https://stripe.com/it/privacy" target="_blank" rel="noopener">Informativa di Stripe</a></dd>
        </dl>
      </div>
      <div class="voce">
        <h3>I ricordi</h3>
        <p>Il nome e il testo che scegli di mandarci dal modulo «Lascia il tuo ricordo».</p>
        <dl>
          <dt>Perché</dt><dd>Pubblicarli sul sito e, a volte, sui nostri social. Li leggiamo prima di pubblicarli.</dd>
          <dt>Base giuridica</dt><dd>Il tuo consenso (art. 6.1.a GDPR), che puoi ritirare quando vuoi.</dd>
          <dt>Per quanto</dt><dd>Finché non ci chiedi di toglierli: basta una mail.</dd>
        </dl>
      </div>
      <div class="voce">
        <h3>Se ci scrivi o ci chiami</h3>
        <p>Usiamo i tuoi recapiti solo per risponderti. Le email di conferma e di avviso che ricevi riguardano solo il tuo ordine o la tua prenotazione: <strong>niente newsletter, niente pubblicità</strong>.</p>
      </div>
    </div>
  </section>

  <section id="terzi">
    <h2>Chi vede i dati</h2>
    <p>Solo noi del Gauguin e i fornitori che ci servono per far funzionare il sito, che trattano i dati per nostro conto e solo per questi scopi: il fornitore dell'hosting e della posta e <strong>Stripe Payments Europe Ltd.</strong> per i pagamenti. Non vendiamo né cediamo i dati a nessuno.</p>
    <p>I link a Facebook, Instagram, WhatsApp e Google Maps portano fuori dal sito: da lì valgono le regole di quei servizi.</p>
  </section>

  <section id="cookie">
    <h2>Cookie</h2>
    <p>Il sito non usa cookie di profilazione né strumenti di statistica o pubblicità: <strong>niente Google Analytics, niente pixel di Facebook</strong>. Anche i caratteri tipografici sono sul nostro server, non su quelli di Google.</p>
    <div class="legale-tabella" role="table" aria-label="Cookie usati dal sito">
      <div class="riga testa" role="row"><span role="columnheader">Cookie</span><span role="columnheader">A cosa serve</span><span role="columnheader">Durata</span></div>
      <div class="riga" role="row"><span role="cell"><code>gauguin_consent</code></span><span role="cell">Ricorda la tua scelta sul banner dei cookie.</span><span role="cell">6 mesi</span></div>
      <div class="riga" role="row"><span role="cell"><code>__stripe_mid</code>, <code>__stripe_sid</code></span><span role="cell">Sicurezza e prevenzione delle frodi, solo quando paghi online.</span><span role="cell">1 anno / 30 minuti</span></div>
      <div class="riga" role="row"><span role="cell"><code>wordpress_*</code></span><span role="cell">Accesso all'area riservata, solo per il personale.</span><span role="cell">Fino all'uscita</span></div>
    </div>
    <p>Il sito salva anche qualche piccola informazione nella memoria del tuo browser, per esempio i ricordi che hai già mandato. Resta sul tuo telefono o computer e non ci viene inviata. Puoi cancellare tutto dalle impostazioni del browser.</p>
  </section>

  <section id="diritti">
    <h2>I tuoi diritti</h2>
    <p>Puoi chiederci in qualsiasi momento di vedere i tuoi dati, correggerli, cancellarli, limitarne l'uso, opporti al trattamento o riceverne una copia (artt. 15–22 GDPR). Scrivi a <?php echo $mail; ?>: rispondiamo entro un mese, di solito molto prima.</p>
    <p>Se pensi che abbiamo trattato male i tuoi dati puoi rivolgerti al <a href="https://www.garanteprivacy.it" target="_blank" rel="noopener">Garante per la protezione dei dati personali</a>.</p>
    <p class="legale-nota">Informativa resa ai sensi del Regolamento (UE) 2016/679 (GDPR). Se cambiamo qualcosa lo scriviamo qui e aggiorniamo la data in cima.</p>
  </section>
</main>
        <?php
    }
}
