<?php
if (!defined('ABSPATH')) exit;

/**
 * "TV Gauguin": slideshow a ciclo continuo per gli schermi del locale.
 *
 * Una sola pagina standalone (/tv/) che tutte le TV aprono allo stesso
 * indirizzo: foto, ricordi approvati, countdown e QR si alternano da soli
 * e si aggiornano senza toccare le chiavette HDMI.
 *
 * Note di campo (Fire Stick / Chromecast):
 *  - niente audio, niente lampeggi: gira anche mentre la gente cena;
 *  - tutto in unita' viewport, cosi' scala identico da 720p a 4K;
 *  - margine di sicurezza per l'overscan delle TV, che taglia i bordi;
 *  - la pagina non va mai cachata dal full page cache, altrimenti i ricordi
 *    nuovi non arrivano mai sugli schermi.
 */
class GX30_TV {

    private static $instance = null;

    /** Bump quando cambiano le rewrite rules, per rifare il flush una sola volta. */
    const REWRITE_VERSION = '1';

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'add_rewrite']);
        add_filter('query_vars', [$this, 'add_query_var']);
        add_action('template_redirect', [$this, 'maybe_render'], 0);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /* ---------------------------------------------------------------- rotta */

    public function add_rewrite() {
        add_rewrite_rule('^tv/?$', 'index.php?gx30_tv=1', 'top');
        self::maybe_flush();
    }

    public function add_query_var($vars) {
        $vars[] = 'gx30_tv';
        return $vars;
    }

    /**
     * Flush delle rewrite rules una volta sola per versione: senza, /tv/
     * risponderebbe 404 finche' non si risalvano i permalink a mano.
     */
    public static function maybe_flush() {
        if (get_option('gx30_tv_rewrite') === self::REWRITE_VERSION) return;
        flush_rewrite_rules(false);
        update_option('gx30_tv_rewrite', self::REWRITE_VERSION, false);
    }

    private function is_tv_request() {
        if (get_query_var('gx30_tv')) return true;
        // Fallback se i permalink non sono ancora stati rigenerati.
        return isset($_GET['gx30_tv']) && $_GET['gx30_tv'] === '1';
    }

    /* ------------------------------------------------------------ rendering */

    public function maybe_render() {
        if (!$this->is_tv_request()) return;

        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        nocache_headers();
        status_header(200);

        $this->render();
        exit;
    }

    /**
     * URL pubblico dello slideshow (mostrato nel pannello admin).
     */
    public static function tv_url() {
        $permalinks = get_option('permalink_structure');
        return $permalinks ? home_url('/tv/') : home_url('/?gx30_tv=1');
    }

    /* ----------------------------------------------------------------- dati */

    /**
     * Tutto cio' che lo slideshow deve sapere. Stessa struttura per l'HTML
     * iniziale e per il refresh REST, cosi' il JS ha un solo formato.
     */
    public static function payload() {
        $s = GX30_Settings::all();

        // Foto: galleria dedicata alle TV, altrimenti quella della landing.
        $photos = isset($s['tv_gallery']) && is_array($s['tv_gallery']) ? $s['tv_gallery'] : [];
        $photos = array_values(array_filter(array_map('strval', $photos)));
        if (!$photos) {
            $gal = isset($s['gallery']) && is_array($s['gallery']) ? $s['gallery'] : [];
            $photos = array_values(array_filter(array_map('strval', $gal)));
        }
        // La foto della sezione storia e' sempre buona da mostrare.
        $story = isset($s['story_image']) ? trim((string) $s['story_image']) : '';
        if ($story !== '' && !in_array($story, $photos, true)) $photos[] = $story;

        // Ricordi approvati; se non ce ne sono ancora, i bigliettini iniziali.
        $memories = [];
        foreach (GX30_Memories::published_list(80) as $row) {
            $text = trim((string) $row->memory);
            if ($text === '') continue;
            $memories[] = ['name' => trim((string) $row->name), 'memory' => $text];
        }
        if (!$memories) {
            $seeds = isset($s['seeds']) && is_array($s['seeds']) ? $s['seeds'] : [];
            foreach ($seeds as $seed) {
                if (empty($seed['memory'])) continue;
                $memories[] = [
                    'name'   => isset($seed['name']) ? (string) $seed['name'] : '',
                    'memory' => (string) $seed['memory'],
                ];
            }
        }

        // Frasi tipografiche.
        $claims = isset($s['tv_claims']) && is_array($s['tv_claims']) ? $s['tv_claims'] : [];
        $claims = array_values(array_filter(array_map('trim', array_map('strval', $claims))));

        // Schermate QR: solo quelle con un'immagine caricata.
        $qr = [];
        $raw_qr = isset($s['tv_qr']) && is_array($s['tv_qr']) ? $s['tv_qr'] : [];
        foreach ($raw_qr as $item) {
            if (!is_array($item)) continue;
            $img = isset($item['img']) ? trim((string) $item['img']) : '';
            if ($img === '') continue;
            $when = isset($item['when']) ? (string) $item['when'] : 'always';
            if (!in_array($when, ['always', 'day', 'dinner'], true)) $when = 'always';
            $qr[] = [
                'img'   => $img,
                'title' => isset($item['title']) ? (string) $item['title'] : '',
                'text'  => isset($item['text'])  ? (string) $item['text']  : '',
                'when'  => $when,
            ];
        }

        $logo = isset($s['tv_logo']) ? trim((string) $s['tv_logo']) : '';
        if ($logo === '') {
            $logo = isset($s['lockup_image']) ? trim((string) $s['lockup_image']) : '';
        }
        if ($logo === '') $logo = GX30_URL . 'public/assets/gauguin-30-lockup.png';

        $slide_ms = isset($s['tv_slide_ms']) ? (int) $s['tv_slide_ms'] : 9000;
        if ($slide_ms < 4000)  $slide_ms = 4000;
        if ($slide_ms > 30000) $slide_ms = 30000;

        return [
            'photos'        => $photos,
            'memories'      => $memories,
            'claims'        => $claims,
            'qr'            => $qr,
            'logo'          => $logo,
            'event'         => isset($s['event_datetime']) ? (string) $s['event_datetime'] : '',
            'showCountdown' => !empty($s['tv_show_countdown']),
            'slideMs'       => $slide_ms,
            'dinnerFrom'    => isset($s['tv_dinner_from']) ? (string) $s['tv_dinner_from'] : '19:00',
            'dinnerTo'      => isset($s['tv_dinner_to'])   ? (string) $s['tv_dinner_to']   : '23:59',
            'memKicker'     => isset($s['mem_kicker']) ? (string) $s['mem_kicker'] : "Trent'anni di ricordi",
            'footerText'    => isset($s['footer_text']) ? (string) $s['footer_text'] : '',
            'stamp'         => GX30_VERSION,
        ];
    }

    public function register_routes() {
        // Pubblico e in sola lettura: le TV non sono loggate e non hanno nonce.
        register_rest_route('gauguin30/v1', '/tv-data', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_data'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_data() {
        $res = new WP_REST_Response(self::payload(), 200);
        $res->header('Cache-Control', 'no-store, max-age=0');
        return $res;
    }

    /* ----------------------------------------------------------------- HTML */

    private function render() {
        $ver  = GX30_VERSION;
        $css  = GX30_URL . 'public/assets/tv.css';
        $fnt  = GX30_URL . 'public/assets/fonts.css';
        $js   = GX30_URL . 'public/assets/tv.js';
        $rest = esc_url_raw(rest_url('gauguin30/v1/tv-data'));

        $json = wp_json_encode(array_merge(self::payload(), ['endpoint' => $rest]));
        if ($json === false) $json = '{}';

        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Gauguin &middot; TV</title>
<link rel="stylesheet" href="<?php echo esc_url($fnt . '?v=' . $ver); ?>">
<link rel="stylesheet" href="<?php echo esc_url($css . '?v=' . $ver); ?>">
</head>
<body class="gx-tv">
<div class="gx-tv-stage" id="gx-tv-stage"></div>
<div class="gx-tv-mark" id="gx-tv-mark" aria-hidden="true"></div>
<div class="gx-tv-bar"><i id="gx-tv-bar-fill"></i></div>
<script>window.GX30_TV = <?php echo $json; ?>;</script>
<script src="<?php echo esc_url($js . '?v=' . $ver); ?>"></script>
</body>
</html><?php
    }
}
