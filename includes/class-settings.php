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
            'event_datetime' => '2026-10-15T19:00', // formato datetime-local

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

            // Sezione ricordi
            'mem_kicker'     => "Trent'anni di ricordi",
            'mem_title'      => 'RACCONTACI LA TUA SERATA AL GAUGUIN',
            'mem_lead'       => 'Una pizza tra amici, una birra speciale, una festa indimenticabile. Lascia il tuo ricordo: i più belli li racconteremo alla serata dei 30 anni.',

            // Bigliettini iniziali che svolazzano nella hero
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
            'footer_hours'   => 'Aperti tutti i giorni tranne il martedì',
            'footer_hours_highlight' => 'martedì',
            'footer_maps_url'=> 'https://maps.app.goo.gl/Fck6uMmRbUvjxWJr7',
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
        return [2026, 10, 15, 19, 0];
    }
}
