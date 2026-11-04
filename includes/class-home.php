<?php
if (!defined('ABSPATH')) exit;

/**
 * Home definitiva del sito (restyle "la sala piena"). Prende il posto della
 * landing 30 Anni da sola, al momento impostato nel pannello ('home_new_from'),
 * cosi' la festa resta online fino a chiusura e nessuno deve alzarsi di notte.
 * Prima di quel momento si vede solo da admin con /?anteprima-home=1.
 */
class GX30_Home {

    const PURGE_OPTION = 'gx30_home_purged';
    const TITLE = 'Pizzeria Birreria Gauguin - Alba Adriatica | Pizza, Birra Artigianale';
    const DESC  = 'Gauguin, pizzeria birreria di Alba Adriatica dal 1996. Pizza nel forno a legna, birre da tutto il mondo e la brace. Ordina d\'asporto o prenota un tavolo.';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'maybe_purge']);
    }

    /** Timestamp del passaggio alla nuova home, 0 se non impostato. */
    public static function switch_timestamp() {
        $raw = trim((string) GX30_Settings::get('home_new_from'));
        if ($raw === '') return 0;
        try {
            $dt = new DateTimeImmutable($raw, wp_timezone());
        } catch (Exception $e) {
            return 0;
        }
        return $dt->getTimestamp();
    }

    public static function is_live() {
        $ts = self::switch_timestamp();
        return $ts > 0 && time() >= $ts;
    }

    private static function is_preview() {
        return !empty($_GET['anteprima-home']) && current_user_can('manage_options');
    }

    /** True se la richiesta corrente deve vedere la nuova home. */
    public static function should_render() {
        return is_front_page() && (self::is_live() || self::is_preview());
    }

    /**
     * WP Fastest Cache tiene la home: al passaggio l'HTML della landing va
     * buttato, una volta sola. Scatta alla prima richiesta che arriva a PHP:
     * basta una visita, perche' il popup della landing in cache chiama la REST.
     */
    public function maybe_purge() {
        if (!self::is_live()) return;
        $key = (string) GX30_Settings::get('home_new_from');
        if (get_option(self::PURGE_OPTION) === $key) return;
        update_option(self::PURGE_OPTION, $key);
        delete_transient('gx30_landing_url');
        do_action('wpfc_clear_all_cache');
    }

    public function render() {
        if (self::is_preview() && !defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);

        add_filter('gauguin_force_load_popup', '__return_true');
        $this->seo_filters();

        wp_enqueue_style('gx30-home', GX30_URL . 'public/assets/home.css', [], GX30_VERSION);
        wp_enqueue_script('gx30-home', GX30_URL . 'public/assets/home.js', [], GX30_VERSION, true);
        wp_localize_script('gx30-home', 'GXHOME', $this->js_config());

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#12100E">
    <script>document.documentElement.className+=' js';</script>
    <link rel="preload" as="image" href="<?php echo esc_url(self::img('sala-1280.webp')); ?>" imagesrcset="<?php echo esc_attr(self::srcset('sala', [828, 1280, 1920])); ?>" imagesizes="100vw" fetchpriority="high">
    <link rel="preload" as="font" type="font/woff2" href="<?php echo esc_url(GX30_URL . 'public/assets/fonts/fraunces-var.woff2'); ?>" crossorigin>
    <?php GX30_Template::instance()->schema_jsonld(self::DESC, self::img('og-sala.jpg')); ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class('gh-body'); ?>>
<?php $this->render_body(); ?>
<?php wp_footer(); ?>
</body>
</html>
        <?php
    }

    /** Titolo e descrizione: quelli salvati in Yoast parlano ancora dei 30 anni. */
    private function seo_filters() {
        $title = function () { return self::TITLE; };
        $desc  = function () { return self::DESC; };
        $image = function () { return self::img('og-sala.jpg'); };
        add_filter('pre_get_document_title', $title, 99);
        add_filter('wpseo_title', $title, 99);
        add_filter('wpseo_opengraph_title', $title, 99);
        add_filter('wpseo_twitter_title', $title, 99);
        add_filter('wpseo_metadesc', $desc, 99);
        add_filter('wpseo_opengraph_desc', $desc, 99);
        add_filter('wpseo_twitter_description', $desc, 99);
        add_filter('wpseo_opengraph_image', $image, 99);
        add_filter('wpseo_twitter_image', $image, 99);
    }

    private function js_config() {
        $mins = function ($v) {
            return preg_match('/^(\d{1,2}):(\d{2})$/', trim((string) $v), $m) ? ((int) $m[1] * 60 + (int) $m[2]) : null;
        };
        $closed = GX30_Settings::ordering_closed_weekdays_raw();
        if (!is_array($closed) || GX30_Settings::ordering_closure_suspended()) $closed = [];

        $vac = function_exists('gauguin_vacation_notice') ? gauguin_vacation_notice() : null;

        return [
            'open'    => $mins(GX30_Settings::get('schema_open')),
            'close'   => $mins(GX30_Settings::get('schema_close')),
            'closed'  => array_values(array_map('intval', $closed)),
            'vacFrom' => $vac ? (string) $vac['from'] : '',
            'vacTo'   => $vac ? (string) $vac['to'] : '',
            'lat'     => trim((string) GX30_Settings::get('schema_lat')),
            'lng'     => trim((string) GX30_Settings::get('schema_lng')),
            'ricordo' => esc_url_raw(rest_url('gauguin30/v1/ricordo-casuale')),
            'invia'   => esc_url_raw(rest_url('gauguin30/v1/ricordi')),
        ];
    }

    private static function img($file) {
        return GX30_URL . 'public/assets/home/' . $file;
    }

    private static function srcset($name, array $widths) {
        $out = [];
        foreach ($widths as $w) $out[] = self::img($name . '-' . $w . '.webp') . ' ' . $w . 'w';
        return implode(', ', $out);
    }

    /** Foto di banda: 50vw da tablet in su, tutto schermo su telefono. */
    private static function photo($name, array $widths, $alt, $sizes = '(min-width: 900px) 50vw, 100vw') {
        printf(
            '<img loading="lazy" decoding="async" src="%s" srcset="%s" sizes="%s" alt="%s">',
            esc_url(self::img($name . '-' . $widths[1 % count($widths)] . '.webp')),
            esc_attr(self::srcset($name, $widths)),
            esc_attr($sizes),
            esc_attr($alt)
        );
    }

    /**
     * Striscia di foto dalla libreria Media (campo «Galleria della home»).
     * Si scorre col dito; un tocco apre la foto grande nel <dialog>.
     * Le misure ridotte le sceglie WP via srcset: sul telefono scarica poco.
     */
    private static function gallery() {
        $urls = GX30_Settings::get('home_gallery', []);
        if (!is_array($urls) || !$urls) return;

        $items = [];
        foreach ($urls as $url) {
            $url = (string) $url;
            if ($url === '') continue;
            $key = 'gx30_att_' . md5($url);
            $id  = get_transient($key);
            if ($id === false) {
                $id = (int) attachment_url_to_postid($url);
                set_transient($key, $id, DAY_IN_SECONDS);
            }
            $id = (int) $id;
            if ($id) {
                $big = wp_get_attachment_image_url($id, 'full');
                $img = wp_get_attachment_image($id, 'large', false, [
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                    'sizes'    => '(max-width: 760px) 300px, 390px',
                    'alt'      => '',
                ]);
            } else {
                $big = $url;
                $img = '<img loading="lazy" decoding="async" src="' . esc_url($url) . '" alt="">';
            }
            if (!$img) continue;
            $items[] = '<a class="scatto" href="' . esc_url($big ? $big : $url) . '">' . $img . '</a>';
        }
        if (!$items) return;
        ?>
<section class="galleria appare" aria-label="Foto dal Gauguin">
  <div class="striscia" tabindex="0"><?php echo implode('', $items); // gia' escaped ?></div>
</section>
<dialog class="visore" id="visore" aria-label="Foto">
  <img alt="">
  <button type="button" class="chiudi" aria-label="Chiudi">×</button>
</dialog>
        <?php
    }

    /** Ultimo orario di ritiro impostato nel plugin ordini, '' se non disponibile. */
    private static function last_pickup() {
        if (!class_exists('Gauguin_Database')) return '';
        $times = json_decode((string) Gauguin_Database::get_setting('pickup_times', '[]'), true);
        if (!is_array($times) || !$times) return '';
        return (string) end($times);
    }

    private function render_body() {
        $s = function ($k, $fb = '') { return GX30_Settings::get($k, $fb); };

        $menu   = esc_url(home_url('/menu/'));
        $phone  = (string) $s('footer_phone');
        $tel    = GX30_Settings::footer_phone_tel();
        $place  = (string) $s('footer_address');
        $lat    = trim((string) $s('schema_lat'));
        $lng    = trim((string) $s('schema_lng'));
        $maps   = ($lat !== '' && $lng !== '')
            ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($lat . ',' . $lng)
            : (string) $s('footer_maps_url');
        $open   = trim((string) $s('schema_open'));
        $close  = trim((string) $s('schema_close'));
        $hours  = ($open !== '' && $close !== '') ? $open . ' – ' . $close : '';
        $days   = GX30_Settings::auto_hours(true);
        $pickup = self::last_pickup();
        $fb     = (string) $s('social_facebook');
        $ig     = (string) $s('social_instagram');
        $wa     = (string) $s('social_whatsapp');
        $ico_ordina  = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 7h12l-1 13H7L6 7Z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>';
        $ico_prenota = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>';
        ?>
<div class="gh">

<?php GX30_Template::instance()->vacation_notice(); ?>

<div class="bar">
  <img class="mark" src="<?php echo esc_url(self::img('logo.png')); ?>" alt="Gauguin" width="596" height="212" decoding="async">
  <span class="stato" id="stato" hidden><span class="pallino"></span><span class="parola"></span><span class="coda"></span></span>
  <a class="ordina" href="#gauguin-order">Ordina</a>
</div>

<header class="apertura">
  <img src="<?php echo esc_url(self::img('sala-1280.webp')); ?>" srcset="<?php echo esc_attr(self::srcset('sala', [828, 1280, 1920])); ?>" sizes="100vw" alt="La sala del Gauguin piena di gente una sera" fetchpriority="high" decoding="async">
  <div class="dentro">
    <p class="occhiello">Alba Adriatica · dal 1996</p>
    <h1>Il forno è acceso,<br><em>e la spina pure</em></h1>
  </div>
  <p class="sottotitolo">Pizza, birre da tutto il mondo e la brace. Da trent'anni, il posto dove ci si ritrova.</p>
  <div class="azioni">
    <a class="b-pieno" href="#gauguin-order"><?php echo $ico_ordina; ?> Ordina d'asporto</a>
    <a class="b-vuoto" href="#gauguin-reservation"><?php echo $ico_prenota; ?> Prenota un tavolo</a>
  </div>
  <div class="servizio">
    <a href="<?php echo $menu; ?>">Il menu</a>
    <?php if ($phone): ?><a href="tel:<?php echo esc_attr($tel); ?>"><?php echo esc_html($phone); ?></a><?php endif; ?>
    <?php if ($maps): ?><a class="mappa" href="<?php echo esc_url($maps); ?>" target="_blank" rel="noopener">Come arrivare</a><?php endif; ?>
  </div>
</header>

<div class="pratico">
  <?php if ($hours): ?><span>Aperti <b><?php echo esc_html($hours); ?></b><?php if ($days): ?>, <?php echo $days; // gia' escaped ?><?php endif; ?></span><?php endif; ?>
  <?php if ($pickup): ?><span class="div">·</span><span>Si ordina d'asporto fino alle <b><?php echo esc_html($pickup); ?></b></span><?php endif; ?>
  <?php if ($place): ?><span class="div">·</span><span><?php echo esc_html($place); ?></span><?php endif; ?>
</div>

<section class="banda appare">
  <div class="foto"><?php self::photo('forno', [640, 1000, 1600], 'Le pizze stese sul marmo, pronte per il forno'); ?></div>
  <div class="parole">
    <p class="occhiello">La pizza</p>
    <h2>Lievitazione naturale,<br><em>cotta su pietra</em></h2>
    <p>Rosse, bianche, calzoni, nei forni a legna. C'è il formato maxi, che raddoppia, e la Carnivora da tre chili e mezzo per chi non si accontenta.</p>
    <a class="vai" href="<?php echo $menu; ?>#category-pizze-rosse">Guarda le pizze →</a>
  </div>
</section>

<section class="banda rovescia appare">
  <div class="foto"><?php self::photo('birra', [640, 1000, 1600], 'Una birra ambrata nel calice del Gauguin, con la schiuma alta'); ?></div>
  <div class="parole">
    <p class="occhiello">La birra</p>
    <h2>Otto birre,<br><em>quattro paesi</em></h2>
    <p>Belgio, Germania, Irlanda e America: Hoegaarden, Delirium Tremens, Gulden Draak, Leffe, Franziskaner, Guinness, Goose IPA, Stella. La spina arriva fino al litro.</p>
    <a class="vai" href="<?php echo $menu; ?>#category-birre">Guarda le birre →</a>
  </div>
</section>

<section class="banda appare">
  <div class="foto"><?php self::photo('brace', [640, 1100], 'Tagliata alla griglia affettata sul tagliere'); ?></div>
  <div class="parole">
    <p class="occhiello">E poi</p>
    <h2>C'è anche<br><em>la brace</em></h2>
    <p>Bistecche e tagliate, arrosticini, i Cicciaburger con wagyu e black angus, cotti piano, venti minuti. Cantina abruzzese, e dopocena con gin, whisky e rum.</p>
    <a class="vai" href="<?php echo $menu; ?>">Tutto il menu →</a>
  </div>
</section>

<?php self::gallery(); ?>

<section class="storia appare">
  <div class="dentro">
    <p class="occhiello">La nostra storia</p>
    <h2>Dal 1996, con un pizzico di follia</h2>
    <p>Il Gauguin nasce nel 1996 dalla passione di Giancarlo — allora poco più che un ragazzo — per la compagnia, il divertimento e la buona birra. Ad accompagnarlo in quell'avventura la sua famiglia, esperta nella ristorazione.</p>
    <p>Da lì un luogo d'incontro per giovanissimi e meno giovani: legno alle pareti, tavolate generose, birre da tutto il mondo e la pizza, rigorosamente nel forno a legna. Trent'anni dopo, lo spirito è identico.</p>
    <p class="firma">Giancarlo Di Carlo</p>
  </div>
  <figure class="ritratto">
    <?php self::photo('squadra', [640, 1000, 1600], 'Giancarlo con la squadra del Gauguin, dietro al bancone'); ?>
    <figcaption class="didascalia">Chi vi apre la porta ogni sera.</figcaption>
  </figure>
</section>

<?php /* Un ricordo dei clienti alla volta, preso a caso via REST (la pagina e'
         in cache, il ricordo no). Resta nascosta finche' non arriva qualcosa. */ ?>
<section class="ricordi" id="ricordi" hidden>
  <div class="dentro">
    <p class="occhiello">Chi ci è passato</p>
    <blockquote class="ricordo" aria-live="polite" hidden>
      <p class="testo"></p>
      <footer class="nome"></footer>
    </blockquote>
    <div class="azioni-ricordi">
      <button type="button" class="altro" hidden>Un altro ricordo</button>
      <button type="button" class="lascia" aria-expanded="false" aria-controls="formRicordo">Lascia il tuo ricordo</button>
    </div>
    <form class="form-ricordo" id="formRicordo" hidden novalidate>
      <label>Il tuo nome<input name="name" maxlength="80" autocomplete="given-name" required></label>
      <label>Un ricordo, una serata, una persona<textarea name="memory" maxlength="500" rows="4" required></textarea></label>
      <input class="trappola" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <button type="submit" class="invia">Invia il ricordo</button>
      <p class="nota">Lo leggiamo noi prima di pubblicarlo.</p>
      <p class="esito" role="status"></p>
    </form>
  </div>
</section>

<footer class="piede">
  <div class="griglia">
    <div><h4>Dove</h4><p><?php echo esc_html($place); ?><?php if ($maps): ?><br><a class="mappa" href="<?php echo esc_url($maps); ?>" target="_blank" rel="noopener">Come arrivare</a><?php endif; ?></p></div>
    <?php if ($phone): ?><div><h4>Telefono</h4><p><a href="tel:<?php echo esc_attr($tel); ?>"><?php echo esc_html($phone); ?></a></p></div><?php endif; ?>
    <?php if ($hours): ?><div><h4>Orari</h4><p><?php echo esc_html($hours); ?><?php if ($days): ?><br><?php echo $days; // gia' escaped ?><?php endif; ?></p></div><?php endif; ?>
    <?php if ($fb || $ig || $wa): ?><div><h4>Social</h4><p>
      <?php
      $links = [];
      if ($fb) $links[] = '<a href="' . esc_url($fb) . '" target="_blank" rel="noopener">Facebook</a>';
      if ($ig) $links[] = '<a href="' . esc_url($ig) . '" target="_blank" rel="noopener">Instagram</a>';
      if ($wa) $links[] = '<a href="' . esc_url($wa) . '" target="_blank" rel="noopener">WhatsApp</a>';
      echo implode(' · ', $links);
      ?>
    </p></div><?php endif; ?>
  </div>
  <p class="coda-piede">Gauguin · Pizzeria Birreria · Alba Adriatica · gauguin.it · <a href="<?php echo esc_url(home_url('/privacy-cookie-policy/')); ?>">Privacy &amp; cookie</a></p>
</footer>

<nav class="barra-basso" id="barraBasso" aria-label="Azioni rapide">
  <a class="b-pieno" href="#gauguin-order"><?php echo $ico_ordina; ?> Ordina</a>
  <a class="b-vuoto" href="#gauguin-reservation"><?php echo $ico_prenota; ?> Prenota</a>
</nav>

</div>
        <?php
    }
}
