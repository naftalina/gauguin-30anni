<?php
if (!defined('ABSPATH')) exit;

/**
 * Pagine pubbliche dell'app iOS «Cartellino» (presenze dei dipendenti),
 * richieste dall'App Store: supporto su /cartellino/ e informativa privacy
 * su /cartellino/privacy/. Pagine statiche servite direttamente dal plugin,
 * senza pagine WP ne' regole di rewrite (che dopo un aggiornamento via
 * GitHub non verrebbero rigenerate): si intercetta il percorso della richiesta.
 */
class GX30_Cartellino {

    const EMAIL = 'info@gauguin.it';
    const AGGIORNATA = '4 novembre 2026';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('parse_request', [$this, 'maybe_serve'], 0);
    }

    public function maybe_serve() {
        $path = isset($_SERVER['REQUEST_URI']) ? (string) parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH) : '';
        $home = (string) parse_url(home_url('/'), PHP_URL_PATH);
        if ($home !== '/' && strpos($path, rtrim($home, '/')) === 0) {
            $path = substr($path, strlen(rtrim($home, '/')));
        }
        $path = '/' . trim($path, '/') . '/';

        if ($path === '/cartellino/') {
            $this->send('Cartellino — Supporto', 'Cartellino: le presenze dei dipendenti sul tuo iPhone, tutto in locale. Supporto e domande frequenti.', $this->support());
        } elseif ($path === '/cartellino/privacy/') {
            $this->send('Cartellino — Informativa privacy', 'Informativa privacy dell\'app Cartellino: nessun dato raccolto, tutto resta sul telefono.', $this->privacy());
        }
    }

    private function send($title, $desc, $body) {
        status_header(200);
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        $css = 'body{margin:0;background:#F5F7FB;color:#14213D;font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;-webkit-text-size-adjust:100%}'
             . '.w{max-width:44rem;margin:0 auto;padding:28px 18px 56px}'
             . 'header{display:flex;align-items:center;gap:12px;margin-bottom:22px}'
             . '.ico{width:48px;height:48px;border-radius:12px;background:#1D4ED8;display:flex;align-items:center;justify-content:center;flex:none}'
             . 'h1{font-size:26px;line-height:1.2;margin:0}h2{font-size:19px;margin:30px 0 8px}'
             . 'p,li{margin:0 0 12px}ul{padding-left:20px}a{color:#1D4ED8}'
             . '.box{background:#fff;border:1px solid #DDE3EE;border-radius:14px;padding:16px 18px;margin:18px 0}'
             . '.nota{color:#5B6478;font-size:14px}nav{margin-top:34px;font-size:15px}';
        $ico = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2.5" fill="#fff"/><path d="M8.5 12.5l2.4 2.4 4.6-5" stroke="#16A34A" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>' . esc_html($title) . '</title><meta name="description" content="' . esc_attr($desc) . '">'
           . '<style>' . $css . '</style></head><body><div class="w"><header><div class="ico">' . $ico . '</div><h1>' . esc_html($title) . '</h1></header>'
           . $body
           . '<nav><a href="' . esc_url(home_url('/cartellino/')) . '">Supporto</a> · <a href="' . esc_url(home_url('/cartellino/privacy/')) . '">Privacy</a></nav>'
           . '</div></body></html>';
        exit;
    }

    private function support() {
        $mail = esc_html(self::EMAIL);
        return <<<HTML
<p><strong>Cartellino</strong> è l'app per tenere le presenze dei dipendenti senza fogli e senza conti a fine mese. È pensata per ristoranti, pizzerie e bar, ma va bene per ogni piccola attività.</p>
<div class="box">
<p><strong>Come funziona</strong></p>
<ul>
<li>Aggiungi i dipendenti con nome e cognome.</li>
<li>Ogni sera segni chi era presente: un tocco a testa, oppure copi la squadra del giorno prima.</li>
<li>A fine mese, nella scheda Mese, vedi i giorni lavorati di ognuno ed esporti il foglio Excel per il commercialista.</li>
</ul>
</div>
<h2>Domande frequenti</h2>
<p><strong>Dove finiscono i miei dati?</strong><br>Solo sul tuo iPhone. L'app non ha account, non ha server e non invia niente in rete.</p>
<p><strong>Se cambio telefono perdo tutto?</strong><br>No, se fai un backup prima. In Impostazioni → Backup crei una copia e la salvi dove vuoi (File, iCloud Drive, email). Sul telefono nuovo la ripristini dalla stessa schermata.</p>
<p><strong>Cosa succede se cancello l'app?</strong><br>Insieme all'app si cancellano anche i dati. Fai prima un backup.</p>
<p><strong>Un dipendente non lavora più con me: lo cancello?</strong><br>Meglio disattivarlo dalla scheda Dipendenti: sparisce dalla lista di ogni giorno, ma i suoi giorni restano nello storico e negli export.</p>
<p><strong>Il promemoria serale non arriva.</strong><br>Controlla in Impostazioni dell'iPhone → Notifiche → Cartellino che le notifiche siano consentite, e nell'app l'orario del promemoria.</p>
<h2>Contatti</h2>
<p>Per problemi, dubbi o suggerimenti scrivi a <a href="mailto:{$mail}?subject=Cartellino">{$mail}</a>. Rispondiamo di solito entro due giorni lavorativi.</p>
HTML;
    }

    private function privacy() {
        $mail = esc_html(self::EMAIL);
        $data = esc_html(self::AGGIORNATA);
        return <<<HTML
<p class="nota">Ultimo aggiornamento: {$data}</p>
<div class="box"><p><strong>In breve: Cartellino non raccoglie nessun dato.</strong> Tutto quello che inserisci resta sul tuo iPhone. Lo sviluppatore non vede, non riceve e non conserva nessuna informazione.</p></div>
<h2>Chi è il titolare</h2>
<p>L'app è pubblicata da Giancarlo Di Carlo. Contatto: <a href="mailto:{$mail}">{$mail}</a>.</p>
<h2>Quali dati tratta l'app</h2>
<p>Nell'app inserisci i nomi e i cognomi dei tuoi dipendenti, le loro presenze giornaliere e, se vuoi, l'indirizzo email del tuo commercialista. Questi dati sono salvati <strong>solo nella memoria del tuo dispositivo</strong>, dentro lo spazio riservato all'app.</p>
<p>L'app non si collega a internet, non ha account e non usa server. Non contiene strumenti di statistica, pubblicità o tracciamento, né servizi di terze parti che raccolgano dati.</p>
<h2>Promemoria</h2>
<p>Il promemoria serale è una notifica <strong>locale</strong>, programmata sul telefono stesso: non passa da nessun server.</p>
<h2>Export e backup</h2>
<p>Quando esporti il foglio Excel o crei un backup, il file viene creato sul telefono. Sei tu a decidere se e dove condividerlo (email, File, iCloud Drive, messaggi…). Da quel momento il file è trattato dal servizio che hai scelto, secondo le sue regole.</p>
<h2>I dati dei tuoi dipendenti</h2>
<p>Usando l'app per registrare le presenze del tuo personale, il titolare del trattamento di quei dati sei tu, come datore di lavoro. Cartellino è solo lo strumento: non li riceve e non può accedervi.</p>
<h2>Conservazione e cancellazione</h2>
<p>I dati restano sul dispositivo finché non li cancelli. Puoi eliminare i singoli dipendenti dall'app; cancellando l'app si cancellano tutti i dati, compresi i backup salvati al suo interno.</p>
<h2>Minori</h2>
<p>L'app è destinata a titolari di attività e non è rivolta ai minori.</p>
<h2>Modifiche</h2>
<p>Se questa informativa cambia, la nuova versione sarà pubblicata su questa pagina con la data di aggiornamento.</p>
<h2>Contatti</h2>
<p>Per qualsiasi domanda: <a href="mailto:{$mail}">{$mail}</a>.</p>
HTML;
    }
}
