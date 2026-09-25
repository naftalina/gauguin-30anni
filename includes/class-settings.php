<?php
if (!defined('ABSPATH')) exit;

/**
 * Impostazioni della landing. Tutto ciò che l'utente puo' modificare
 * dall'admin vive in una singola opzione array: 'gx30_settings'.
 */
class GX30_Settings {

    const OPTION = 'gx30_settings';
    private static $instance = null;
    private static $cache = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /**
     * Valori di default (presi 1:1 dal design "30 Anni").
     */
    public static function defaults() {
        return [
            // Evento / countdown
            'event_datetime' => '2026-11-02T19:00', // formato datetime-local — 30 anni: 2 novembre 2026

            // Top bar
            'topbar_left'    => 'Pizzeria · Birreria',
            'topbar_right'   => 'Est. 1996 · Alba Adriatica',

            // Hero
            'lockup_image'   => '', // vuoto = usa il logo bundle del plugin
            'hero_sub'       => 'Pizzeria · Birreria · Alba Adriatica · dal 1996',
            'hero_lead'      => "Trent'anni di pizza nel forno a legna, birre da tutto il mondo e quel chiasso allegro da vero pub. Li festeggiamo il {data} — e la serata sarà una sorpresa.",
            'hero_lead_highlight' => '', // evidenzia una frase qualsiasi (opzionale); per la data usa {data}

            // Storia
            'story_kicker'   => 'La nostra storia',
            'story_title'    => 'DAL 1996, CON UN PIZZICO DI FOLLIA.',
            'story_p1'       => "Il Gauguin nasce nel 1996 dalla passione di Giancarlo — allora poco più che un ragazzo — per la compagnia, il divertimento e la buona birra. Ad accompagnarlo in quell'avventura la sua famiglia, esperta nella ristorazione.",
            'story_p2'       => "Da lì un luogo d'incontro per giovanissimi e meno giovani: legno alle pareti, tavolate generose, birre da tutto il mondo e la pizza, rigorosamente nel forno a legna. Trent'anni dopo, lo spirito è identico.",
            'story_image'    => 'https://www.gauguin.it/wp-content/uploads/2020/08/IMG_1869-scaled.jpg',

            // Galleria foto (array di URL immagine). Vuoto = sezione nascosta.
            'gallery'        => [],

            // --- TV del locale: slideshow a ciclo continuo su /tv/ ---------
            'tv_slide_ms'      => '9000',  // durata base di una schermata
            'tv_show_countdown'=> '1',
            'tv_logo'          => '',      // vuoto = stesso logo della landing
            'tv_gallery'       => [],      // vuoto = usa la galleria qui sopra
            // Fascia "cena": ritmo piu' lento e niente schermate fuori luogo.
            'tv_dinner_from'   => '19:00',
            'tv_dinner_to'     => '23:59',
            // Frasi a tutto schermo; fanno anche da didascalia sulle foto.
            'tv_claims' => [
                'Dal 1996 la stessa legna nel forno',
                "Trent'anni di pizza, birra e chiasso allegro",
                'Birre da tutto il mondo',
                "Qui ci si conosce per nome",
                'Il 2 novembre festeggiamo insieme',
            ],
            // Schermate con QR code: l'immagine si carica dal pannello.
            // 'when': always | day (fuori cena) | dinner (durante la cena).
            'tv_qr' => [],

            // Sezione ricordi
            'mem_kicker'     => "Trent'anni di ricordi",
            'mem_title'      => 'RACCONTACI LA TUA SERATA AL GAUGUIN',
            'mem_lead'       => 'Una pizza tra amici, una birra speciale, una festa indimenticabile. Lascia il tuo ricordo: i più belli li racconteremo alla serata dei 30 anni.',

            // Bigliettini che svolazzano nella hero: con 'seeds_auto' attivo
            // sono i ricordi approvati piu' recenti, e questi qui sotto servono
            // solo a riempire i posti che restano vuoti.
            'seeds_auto' => '1',
            'seeds' => [
                ['name' => 'Marco',          'memory' => 'La mia prima birra al Gauguin, estate 1999. Da allora non ho più smesso.'],
                ['name' => 'Elisa',          'memory' => 'Qui ho festeggiato la laurea con tutti gli amici. Pizza, risate e musica fino a tardi.'],
                ['name' => 'Davide',         'memory' => 'Le partite viste insieme, urlando come matti. Casa nostra di mercoledì sera.'],
                ['name' => 'Giulia & Paolo', 'memory' => 'Il nostro primo appuntamento è stato a quel tavolo d’angolo. Vent’anni fa.'],
                ['name' => 'Andrea',         'memory' => 'Giancarlo che conosce sempre il tuo nome e la tua birra preferita. Questo è il Gauguin.'],
                ['name' => 'Sara',           'memory' => 'Forno a legna e profumo di pizza appena sfornata. Un ricordo d’infanzia.'],
                ['name' => 'Luca',           'memory' => 'Trent’anni di serate. Auguri a una seconda famiglia.'],
            ],

            // SEO / anteprima social
            'meta_description' => 'Gauguin Pizzeria Birreria, Alba Adriatica dal 1996: pizza nel forno a legna e birre da tutto il mondo. Festeggiamo insieme i 30 anni!',
            'og_image'         => '', // vuoto = copertina inclusa nel plugin

            // Email a cui notificare i nuovi ricordi (vuoto = admin del sito)
            'notify_email'   => '',

            // Footer: call-to-action (aprono i popup del plugin ordini)
            'cta_order_label'   => 'Ordina',
            'cta_reserve_label' => 'Prenota',

            // Footer: informazioni
            'footer_address' => 'Alba Adriatica (TE)',
            'footer_phone'   => '0861 75 34 67',
            // '1' = la riga orari la scrive il plugin ordini dai giorni di
            // chiusura spuntati li'; i tre campi qui sotto restano inutilizzati.
            'footer_hours_auto' => '1',
            'footer_hours'   => 'Aperti tutti i giorni tranne il martedì',
            'footer_hours_highlight' => 'martedì',
            // Testo mostrato al posto di footer_hours mentre nel plugin ordini
            // e' attiva un'apertura straordinaria (chiusura settimanale sospesa).
            'footer_hours_suspended' => 'Aperti tutti i giorni, anche il martedì',
            'footer_maps_url'=> 'https://maps.app.goo.gl/Fck6uMmRbUvjxWJr7',

            // --- Dati strutturati schema.org (GEO / ricerca AI). Servono a farsi
            // citare da ChatGPT, Perplexity, Google AI Overviews. Ogni campo vuoto
            // viene semplicemente omesso dal JSON-LD: mai pubblicare dati inventati.
            'schema_name'    => '',            // vuoto = nome azienda di Yoast, poi titolo del sito
            'schema_street'  => 'Via Cesare Battisti',   // AGGIUNGERE IL CIVICO
            'schema_postal'  => '64011',
            'schema_locality'=> 'Alba Adriatica',
            'schema_region'  => 'TE',
            'schema_lat'     => '42.8362303',
            'schema_lng'     => '13.9306367',
            // Orario di servizio, formato 24h HH:MM. Vuoti = niente
            // openingHoursSpecification (i giorni di chiusura arrivano dal plugin ordini).
            'schema_open'    => '',
            'schema_close'   => '',
            'social_facebook' => 'https://www.facebook.com/GauguinPizzeria/',
            'social_instagram'=> 'https://www.instagram.com/gauguinpizzeria',
            'footer_text'    => 'Gauguin · Pizzeria Birreria · dal 1996',
        ];
    }

    /**
     * Numero di telefono in formato tel: (cifre, prefisso +39 se inizia per 0).
     */
    public static function footer_phone_tel() {
        $digits = preg_replace('/\D+/', '', (string) self::get('footer_phone'));
        if ($digits === '') return '';
        if (strpos($digits, '0') === 0) $digits = '39' . $digits;
        return '+' . $digits;
    }

    /**
     * Giorni di chiusura spuntati nel plugin ordini (l'unico posto dove si
     * spuntano), COMPRESI quelli temporaneamente sospesi da un'apertura
     * straordinaria. Restituisce null se il plugin non c'e' o e' una versione
     * precedente: in quel caso il footer torna al testo scritto a mano.
     *
     * @return array|null indici PHP date('w'): 0=domenica ... 6=sabato
     */
    public static function ordering_closed_weekdays_raw() {
        if (!class_exists('Gauguin_Orders')) return null;
        if (!method_exists('Gauguin_Orders', 'get_closed_weekdays')) return null;
        $days = Gauguin_Orders::get_closed_weekdays();
        return is_array($days) ? $days : null;
    }

    /**
     * True se nel plugin ordini e' impostata un'apertura straordinaria ancora
     * in corso (la chiusura settimanale e' sospesa fino a quella data).
     */
    public static function ordering_closure_suspended() {
        if (!class_exists('Gauguin_Orders')) return false;
        if (!method_exists('Gauguin_Orders', 'get_closure_suspend_until')) return false;
        return Gauguin_Orders::get_closure_suspend_until() !== '';
    }

    /**
     * Giorni in cui il locale e' davvero chiuso adesso: durante un'apertura
     * straordinaria nessuno.
     *
     * @return array|null indici PHP date('w'): 0=domenica ... 6=sabato
     */
    public static function ordering_closed_weekdays() {
        $days = self::ordering_closed_weekdays_raw();
        if ($days === null) return null;
        return self::ordering_closure_suspended() ? [] : $days;
    }

    /**
     * Frase orari generata dai giorni di chiusura. Restituisce null se il
     * plugin ordini non e' disponibile.
     *
     * Durante un'apertura straordinaria la frase NON diventa un generico
     * "Aperti tutti i giorni" (indistinguibile dall'aver tolto la spunta): dice
     * "anche il martedi'", cosi' dal sito si vede che e' una deroga temporanea.
     *
     * @param bool $html true = giorni evidenziati in <strong>
     */
    public static function auto_hours($html = false) {
        $days = self::ordering_closed_weekdays_raw();
        if ($days === null) return null;
        $suspended = self::ordering_closure_suspended();

        $names = [
            1 => 'lunedì', 2 => 'martedì', 3 => 'mercoledì', 4 => 'giovedì',
            5 => 'venerdì', 6 => 'sabato', 0 => 'domenica',
        ];
        $class = $suspended ? 'gx-open' : 'gx-closed';
        $parts = [];
        foreach ($names as $idx => $name) {          // ordine lunedi' -> domenica
            if (!in_array($idx, $days, true)) continue;
            $article = ($idx === 0) ? 'la ' : 'il ';
            $parts[] = $article . ($html ? '<strong class="' . $class . '">' . esc_html($name) . '</strong>' : $name);
        }
        if (empty($parts)) return 'Aperti tutti i giorni';

        if (count($parts) === 1) {
            $list = $parts[0];
        } else {
            $last = array_pop($parts);
            $list = implode(', ', $parts) . ' e ' . $last;
        }
        return $suspended
            ? 'Aperti tutti i giorni, anche ' . $list
            : 'Aperti tutti i giorni tranne ' . $list;
    }

    /**
     * Crea l'opzione coi default se non esiste (in attivazione).
     */
    public static function seed_defaults() {
        if (get_option(self::OPTION) === false) {
            add_option(self::OPTION, self::defaults());
        }
    }

    /**
     * Tutte le impostazioni (default + salvate), con cache di richiesta.
     */
    public static function all() {
        if (self::$cache !== null) return self::$cache;
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) $saved = [];
        self::$cache = array_merge(self::defaults(), $saved);
        return self::$cache;
    }

    /**
     * Singolo valore.
     */
    public static function get($key, $fallback = '') {
        $all = self::all();
        return isset($all[$key]) ? $all[$key] : $fallback;
    }

    /**
     * URL del logo lockup: setting personalizzato o asset del plugin.
     */
    public static function lockup_url() {
        $custom = self::get('lockup_image');
        return $custom ? $custom : GX30_URL . 'public/assets/gauguin-30-lockup.png';
    }

    /**
     * Immagine per l'anteprima social (Open Graph).
     */
    /**
     * Nome dell'attività per i dati strutturati. NON usare get_bloginfo('name')
     * da solo: il titolo del sito è ottimizzato per la SERP
     * ("Pizzeria Birreria Gauguin - Alba Adriatica | Pizza, Birra Artigianale")
     * e un'AI lo citerebbe come se fosse la ragione sociale. Ordine: campo del
     * pannello → nome azienda di Yoast → titolo del sito troncato al primo
     * separatore.
     */
    public static function business_name() {
        $manual = trim((string) self::get('schema_name'));
        if ($manual !== '') return $manual;

        $titles = get_option('wpseo_titles', []);
        if (is_array($titles) && !empty($titles['company_name'])) {
            return trim((string) $titles['company_name']);
        }

        $name = (string) get_bloginfo('name');
        $name = preg_split('/\s+[|\x{2013}\x{2014}]\s+|\s+-\s+/u', $name)[0];
        return trim($name);
    }

    public static function og_image_url() {
        $c = self::get('og_image');
        return $c ? $c : GX30_URL . 'public/assets/og-cover.png';
    }

    /**
     * Email notifiche (fallback su admin del sito).
     */
    public static function notify_email() {
        $e = trim((string) self::get('notify_email'));
        return ($e && is_email($e)) ? $e : get_option('admin_email');
    }

    /**
     * Data evento formattata in italiano, es. "15 ottobre 2026".
     */
    public static function event_date_formatted() {
        list($y, $mo, $d) = self::event_parts();
        $mesi = [1 => 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno',
                 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
        $mese = isset($mesi[$mo]) ? $mesi[$mo] : '';
        return trim($d . ' ' . $mese . ' ' . $y);
    }

    /**
     * Componenti del datetime evento per il countdown JS.
     * Ritorna [anno, mese(1-12), giorno, ora, minuto].
     */
    public static function event_parts() {
        $dt = (string) self::get('event_datetime');
        // formato atteso: YYYY-MM-DDTHH:MM
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/', $dt, $m)) {
            return [(int)$m[1], (int)$m[2], (int)$m[3], (int)$m[4], (int)$m[5]];
        }
        return [2026, 11, 2, 19, 0];
    }

    /**
     * Migrazione one-shot: la data 30 anni reale è il 2 novembre 2026
     * (in precedenza il default era 15 ottobre, sbagliato). Correggo il
     * valore salvato SOLO se è ancora il vecchio default, così non tocco
     * una data eventualmente impostata a mano dall'utente.
     */
    public static function maybe_migrate() {
        $saved = get_option(self::OPTION);
        if (!is_array($saved)) return;
        if (isset($saved['event_datetime']) && $saved['event_datetime'] === '2026-10-15T19:00') {
            $saved['event_datetime'] = '2026-11-02T19:00';
            update_option(self::OPTION, $saved);
            self::$cache = null;
        }
    }
}
