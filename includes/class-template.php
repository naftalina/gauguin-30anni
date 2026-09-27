<?php
if (!defined('ABSPATH')) exit;

/**
 * Registra un template di pagina selezionabile dall'editor di WordPress
 * ("Gauguin 30 Anni") e ne renderizza la landing standalone, leggendo
 * tutti i contenuti dalle impostazioni.
 */
class GX30_Template {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // Aggiunge la voce nel menu a tendina "Template" della pagina.
        add_filter('theme_page_templates', [$this, 'register_choice']);
        // Intercetta il caricamento del template per le pagine che lo usano.
        add_filter('template_include', [$this, 'maybe_render'], 99);
    }

    public function register_choice($templates) {
        $templates[GX30_TEMPLATE_SLUG] = 'Gauguin 30 Anni';
        return $templates;
    }

    /**
     * URL della pagina che usa il template "Gauguin 30 Anni" (per i link
     * esterni, es. il popup teaser). Fallback: home. Cache 6h in transient.
     */
    public static function landing_url() {
        $cached = get_transient('gx30_landing_url');
        if ($cached !== false) return $cached;

        $ids = get_posts([
            'post_type'        => 'page',
            'post_status'      => 'publish',
            'posts_per_page'   => 1,
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'meta_key'         => '_wp_page_template',
            'meta_value'       => GX30_TEMPLATE_SLUG,
        ]);
        $url = !empty($ids) ? get_permalink($ids[0]) : home_url('/');

        set_transient('gx30_landing_url', $url, 6 * HOUR_IN_SECONDS);
        return $url;
    }

    private function page_uses_template() {
        if (!is_page()) return false;
        $id = get_queried_object_id();
        return get_post_meta($id, '_wp_page_template', true) === GX30_TEMPLATE_SLUG;
    }

    public function maybe_render($template) {
        if (!$this->page_uses_template()) {
            return $template;
        }
        // Fa iniettare al plugin ordini (se attivo) i popup Ordina/Prenota + asset.
        add_filter('gauguin_force_load_popup', '__return_true');
        $this->enqueue();
        // Renderizziamo noi l'intero documento ed usciamo.
        $this->render_document();
        exit;
    }

    private function enqueue() {
        wp_enqueue_style('gx30-fonts', GX30_URL . 'public/assets/fonts.css', [], GX30_VERSION);
        wp_enqueue_style('gx30-landing', GX30_URL . 'public/assets/landing.css', ['gx30-fonts'], GX30_VERSION);
        wp_enqueue_script('gx30-landing', GX30_URL . 'public/assets/landing.js', [], GX30_VERSION, true);

        list($y, $mo, $d, $h, $mi) = GX30_Settings::event_parts();
        $seeds = GX30_Settings::get('seeds', []);
        if (!is_array($seeds)) $seeds = [];

        wp_localize_script('gx30-landing', 'GX30', [
            'restUrl' => esc_url_raw(rest_url('gauguin30/v1/ricordi')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'event'   => ['y' => $y, 'mo' => $mo, 'd' => $d, 'h' => $h, 'mi' => $mi],
            'seeds'   => array_values(array_map(function ($s) {
                return [
                    'name'   => isset($s['name']) ? (string) $s['name'] : '',
                    'memory' => isset($s['memory']) ? (string) $s['memory'] : '',
                ];
            }, $seeds)),
            // Con 'seeds_auto' i bigliettini della hero sono i ricordi approvati
            // piu' recenti (published_list e' gia' ordinata dal piu' nuovo) e i
            // seeds scritti a mano riempiono solo i posti che restano.
            'seedsAuto' => (string) GX30_Settings::get('seeds_auto', '1') === '1',
            'published' => array_values(array_map(function ($r) {
                return ['name' => (string) $r->name, 'memory' => (string) $r->memory];
            }, GX30_Memories::published_list())),
        ]);
    }

    private function render_document() {
        $page_title = get_the_title(get_queried_object_id());
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
    // Preload dell'immagine LCP (logo "30 anni" nella hero): la scarica subito,
    // in parallelo al CSS, così il Largest Contentful Paint arriva prima.
    printf(
        '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n    ",
        esc_url(GX30_Settings::lockup_url())
    );
    ?>
    <?php $this->seo_tags(); ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class('gx30-body'); ?>>
<?php $this->render_body(); ?>
<?php wp_footer(); ?>
</body>
</html>
        <?php
    }

    /**
     * Meta Open Graph / Twitter: se e' attivo un plugin SEO lascio gestire a
     * lui, per non duplicare i tag. Il JSON-LD invece NON passa di qui: viene
     * emesso sempre da schema_jsonld(), perche' Yoast pubblica solo un
     * Organization generico e non dichiara mai che siamo un ristorante.
     */
    private function seo_tags() {
        $this->og_tags();
        $this->schema_jsonld();
    }

    private function og_tags() {
        if (defined('WPSEO_VERSION') || class_exists('RankMath') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION')) {
            return;
        }
        $id    = get_queried_object_id();
        $title = wp_strip_all_tags(get_the_title($id));
        $site  = get_bloginfo('name');
        $desc  = trim((string) GX30_Settings::get('meta_description'));
        $img   = GX30_Settings::og_image_url();
        $url   = get_permalink($id);
        $og_title = $title . ($site && mb_stripos($title, $site) === false ? ' · ' . $site : '');
        ?>
    <meta name="description" content="<?php echo esc_attr($desc); ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo esc_attr($site); ?>">
    <meta property="og:title" content="<?php echo esc_attr($og_title); ?>">
    <meta property="og:description" content="<?php echo esc_attr($desc); ?>">
    <meta property="og:url" content="<?php echo esc_url($url); ?>">
    <meta property="og:image" content="<?php echo esc_url($img); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo esc_attr($og_title); ?>">
    <meta name="twitter:description" content="<?php echo esc_attr($desc); ?>">
    <meta name="twitter:image" content="<?php echo esc_url($img); ?>">
        <?php
    }

    /**
     * JSON-LD "Restaurant" completo. E' il blocco che i motori generativi
     * (ChatGPT, Perplexity, Google AI Overviews) leggono per rispondere a
     * "dove mangio ad Alba Adriatica", "a che ora apre", "fanno asporto".
     * Emesso SEMPRE, anche con Yoast attivo: ha un @id proprio, quindi non
     * va in conflitto col grafo di Yoast, lo arricchisce.
     */
    private function schema_jsonld() {
        // NON get_bloginfo('name'): il titolo del sito e' ottimizzato per la
        // SERP e un'AI lo citerebbe come ragione sociale. Vedi business_name().
        $site = GX30_Settings::business_name();
        $desc = trim((string) GX30_Settings::get('meta_description'));

        $schema = [
            '@context'           => 'https://schema.org',
            '@type'              => 'Restaurant',
            '@id'                => home_url('/#restaurant'),
            'name'               => $site,
            'url'                => home_url('/'),
            'image'              => GX30_Settings::og_image_url(),
            'servesCuisine'      => ['Pizza', 'Cucina italiana', 'Birreria'],
            'priceRange'         => '€€',
            'currenciesAccepted' => 'EUR',
            'paymentAccepted'    => 'Contanti, Carte di credito, Carte di debito',
            'foundingDate'       => '1996',
        ];
        if ($desc !== '') $schema['description'] = $desc;

        $phone = GX30_Settings::footer_phone_tel();
        if ($phone) $schema['telephone'] = $phone;

        // Indirizzo: includo solo le parti effettivamente compilate, mai
        // valori inventati (un indirizzo sbagliato e' peggio di nessun dato).
        $street   = trim((string) GX30_Settings::get('schema_street'));
        $postal   = trim((string) GX30_Settings::get('schema_postal'));
        $locality = trim((string) GX30_Settings::get('schema_locality'));
        $region   = trim((string) GX30_Settings::get('schema_region'));
        if ($locality === '') {
            // Fallback sul vecchio campo libero del footer.
            $locality = trim((string) GX30_Settings::get('footer_address'));
        }
        if ($locality !== '') {
            $addr = ['@type' => 'PostalAddress', 'addressCountry' => 'IT', 'addressLocality' => $locality];
            if ($street !== '') $addr['streetAddress'] = $street;
            if ($postal !== '') $addr['postalCode']    = $postal;
            if ($region !== '') $addr['addressRegion'] = $region;
            $schema['address'] = $addr;
        }

        $lat = trim((string) GX30_Settings::get('schema_lat'));
        $lng = trim((string) GX30_Settings::get('schema_lng'));
        if (is_numeric($lat) && is_numeric($lng)) {
            $schema['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $lat, 'longitude' => (float) $lng];
        }

        $maps = trim((string) GX30_Settings::get('footer_maps_url'));
        if ($maps !== '') $schema['hasMap'] = $maps;

        $hours = $this->opening_hours_spec();
        if ($hours) $schema['openingHoursSpecification'] = $hours;

        // Profili social: aiutano le AI a collegare sito, pagina FB e IG.
        $sameas = array_values(array_filter([
            trim((string) GX30_Settings::get('social_facebook')),
            trim((string) GX30_Settings::get('social_instagram')),
        ]));
        if ($sameas) $schema['sameAs'] = $sameas;

        // Menu e azioni: dicono all'AI che si puo' ordinare e prenotare, e dove.
        $menu_url = $this->menu_page_url();
        if ($menu_url) $schema['hasMenu'] = $menu_url;

        $schema['acceptsReservations'] = home_url('/');

        $order_url = $this->ordering_page_url();
        if ($order_url) {
            $schema['potentialAction'] = [
                '@type'  => 'OrderAction',
                'target' => [
                    '@type'          => 'EntryPoint',
                    'urlTemplate'    => $order_url,
                    'inLanguage'     => 'it-IT',
                    'actionPlatform' => [
                        'http://schema.org/DesktopWebPlatform',
                        'http://schema.org/MobileWebPlatform',
                    ],
                ],
                'deliveryMethod' => ['http://purl.org/goodrelations/v1#DeliveryModePickUp'],
            ];
        }

        echo "\n    " . '<script type="application/ld+json">'
            . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . '</script>' . "\n";
    }

    /**
     * openingHoursSpecification costruito dai giorni di chiusura del plugin
     * ordini (stessa fonte della riga orari del footer, cosi' non divergono)
     * piu' l'orario di apertura/chiusura dalle impostazioni. Se manca uno dei
     * due orari restituisco null: meglio nessun dato che un orario sbagliato.
     */
    private function opening_hours_spec() {
        $open  = trim((string) GX30_Settings::get('schema_open'));
        $close = trim((string) GX30_Settings::get('schema_close'));
        if (!preg_match('/^\d{1,2}:\d{2}$/', $open) || !preg_match('/^\d{1,2}:\d{2}$/', $close)) {
            return null;
        }

        $map = [
            0 => 'https://schema.org/Sunday',
            1 => 'https://schema.org/Monday',
            2 => 'https://schema.org/Tuesday',
            3 => 'https://schema.org/Wednesday',
            4 => 'https://schema.org/Thursday',
            5 => 'https://schema.org/Friday',
            6 => 'https://schema.org/Saturday',
        ];
        $closed = GX30_Settings::ordering_closed_weekdays_raw();
        // Durante un'apertura straordinaria la chiusura settimanale non vale.
        if (!is_array($closed) || GX30_Settings::ordering_closure_suspended()) $closed = [];

        $days = [];
        foreach ($map as $idx => $url) {
            if (in_array($idx, $closed, true)) continue;
            $days[] = $url;
        }
        if (!$days) return null;

        return [[
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => $days,
            'opens'     => str_pad($open, 5, '0', STR_PAD_LEFT),
            'closes'    => str_pad($close, 5, '0', STR_PAD_LEFT),
        ]];
    }

    /** URL della pagina che ospita il menu digitale (plugin gauguin-menu). */
    private function menu_page_url() {
        if (class_exists('GXM_Template') && method_exists('GXM_Template', 'menu_url')) {
            $u = GXM_Template::menu_url();
            if ($u) return $u;
        }
        $page = get_page_by_path('menu');
        return $page ? get_permalink($page) : '';
    }

    /** URL della pagina ordini (/ordina/), se pubblicata. */
    private function ordering_page_url() {
        $page = get_page_by_path('ordina');
        return $page ? get_permalink($page) : '';
    }

    /**
     * Markup della landing (contenuti dalle impostazioni).
     */
    private function render_body() {
        $s = function ($k, $fb = '') { return GX30_Settings::get($k, $fb); };

        // Spezza il lead per evidenziare la porzione "highlight" in bianco/grassetto.
        $lead      = (string) GX30_Settings::get('hero_lead');
        $lead_html = esc_html($lead);
        // {data} → data evento live (in grassetto bianco), si aggiorna col countdown.
        if (mb_strpos($lead, '{data}') !== false) {
            $lead_html = str_replace(
                '{data}',
                '<strong>' . esc_html(GX30_Settings::event_date_formatted()) . '</strong>',
                $lead_html
            );
        } else {
            // fallback: evidenzia una frase arbitraria, se impostata
            $highlight = (string) GX30_Settings::get('hero_lead_highlight');
            if ($highlight !== '' && mb_strpos($lead, $highlight) !== false) {
                $lead_html = str_replace(
                    esc_html($highlight),
                    '<strong>' . esc_html($highlight) . '</strong>',
                    $lead_html
                );
            }
        }
        ?>
<div class="gx-wrap">

    <div class="gx-topbar">
        <div class="gx-eyebrow"><?php echo esc_html($s('topbar_left')); ?></div>
        <div class="gx-eyebrow"><?php echo esc_html($s('topbar_right')); ?></div>
    </div>

    <div class="gx-redzone">

    <div class="gx-hero">
        <div class="gx-cloud-head">I ricordi dei nostri clienti</div>
        <div class="gx-cloud" id="gx-cloud" aria-hidden="true"></div>
        <div class="gx-hero-inner">
            <img class="gx-lockup" src="<?php echo esc_url(GX30_Settings::lockup_url()); ?>" alt="Gauguin · 30 anni · 1996—2026" fetchpriority="high" decoding="async">
            <div class="gx-hero-sub"><?php echo esc_html($s('hero_sub')); ?></div>
            <p class="gx-hero-lead"><?php echo $lead_html; // già escaped sopra ?></p>

            <div class="gx-countdown" id="gx-countdown" role="timer" aria-label="Conto alla rovescia">
                <div class="gx-cd-box"><div class="gx-cd-num" id="gx-days">0</div><div class="gx-cd-label">Giorni</div></div>
                <div class="gx-cd-box"><div class="gx-cd-num" id="gx-hours">00</div><div class="gx-cd-label">Ore</div></div>
                <div class="gx-cd-box"><div class="gx-cd-num" id="gx-mins">00</div><div class="gx-cd-label">Minuti</div></div>
                <div class="gx-cd-box is-secs"><div class="gx-cd-num gx-secs" id="gx-secs">00</div><div class="gx-cd-label">Secondi</div></div>
            </div>

            <?php if (shortcode_exists('gauguin_ordering')): ?>
            <div class="gx-hero-cta">
                <a href="#gauguin-order" data-action="gauguin-order" class="gx-cta-btn gx-cta-order"><?php echo esc_html($s('cta_order_label')); ?></a>
                <a href="#gauguin-reservation" data-action="gauguin-reservation" class="gx-cta-btn gx-cta-reserve"><?php echo esc_html($s('cta_reserve_label')); ?></a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="gx-memories">
        <div class="gx-memories-inner">
            <div class="gx-mem-kicker"><?php echo esc_html($s('mem_kicker')); ?></div>
            <h2><?php echo esc_html($s('mem_title')); ?></h2>
            <p class="gx-mem-lead"><?php echo esc_html($s('mem_lead')); ?></p>

            <div class="gx-thanks gx-hidden" id="gx-thanks">
                <div class="gx-thanks-title">GRAZIE DI CUORE!</div>
                <p>Il tuo ricordo è stato registrato. Ci vediamo alla festa.</p>
            </div>

            <form class="gx-form" id="gx-form" novalidate>
                <div class="gx-field">
                    <div>
                        <label for="gx-name">Il tuo nome</label>
                        <input type="text" id="gx-name" name="name" placeholder="Come ti chiami?" maxlength="60" autocomplete="name">
                    </div>
                    <div>
                        <label for="gx-memory">Il tuo ricordo</label>
                        <textarea id="gx-memory" name="memory" rows="4" placeholder="Raccontaci una serata, una risata, un momento al Gauguin…" maxlength="500"></textarea>
                    </div>
                    <!-- honeypot anti-spam -->
                    <input type="text" id="gx-website" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
                    <button type="submit" class="gx-submit" id="gx-submit" disabled>INVIA IL MIO RICORDO</button>
                    <p class="gx-form-msg" id="gx-form-msg" role="status"></p>
                </div>
            </form>
        </div>
    </div>

    </div><!-- /gx-redzone -->

    <?php $gallery = GX30_Settings::get('gallery', []); if (is_array($gallery) && !empty($gallery)): ?>
    <div class="gx-gallery" id="gx-gallery">
        <?php foreach ($gallery as $img): if (!$img) continue; ?>
            <div class="gx-gallery-item"><img src="<?php echo esc_url($img); ?>" alt="Gauguin" loading="lazy"></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="gx-story">
        <div class="gx-story-grid">
            <div>
                <div class="gx-kicker"><?php echo esc_html($s('story_kicker')); ?></div>
                <h2><?php echo esc_html($s('story_title')); ?></h2>
                <p><?php echo esc_html($s('story_p1')); ?></p>
                <p><?php echo esc_html($s('story_p2')); ?></p>
            </div>
            <div class="gx-story-photo">
                <img src="<?php echo esc_url($s('story_image')); ?>" alt="<?php echo esc_attr($s('story_kicker')); ?>">
            </div>
        </div>
    </div>

    <footer class="gx-foot">
        <div class="gx-foot-info">
            <?php if ($s('footer_address')): ?>
                <div class="gx-foot-line"><?php echo $this->icon_pin(); ?> <?php echo esc_html($s('footer_address')); ?></div>
            <?php endif; ?>
            <?php if ($s('footer_phone')): $tel = GX30_Settings::footer_phone_tel(); ?>
                <div class="gx-foot-line"><?php echo $this->icon_phone(); ?> <a href="tel:<?php echo esc_attr($tel); ?>"><?php echo esc_html($s('footer_phone')); ?></a></div>
            <?php endif; ?>
            <?php $hours_html = $this->hours_html(); if ($hours_html !== ''): ?>
                <div class="gx-foot-line"><?php echo $this->icon_clock(); ?> <?php echo $hours_html; // testo escaped, giorni evidenziati ?></div>
            <?php endif; ?>
            <?php if ($s('footer_maps_url')): ?>
                <div class="gx-foot-line"><a href="<?php echo esc_url($s('footer_maps_url')); ?>" target="_blank" rel="noopener"><?php echo $this->icon_directions(); ?> Come raggiungerci</a></div>
            <?php endif; ?>
        </div>

        <?php $fb = $s('social_facebook'); $ig = $s('social_instagram'); $wa = $s('social_whatsapp'); if ($fb || $ig || $wa): ?>
        <div class="gx-foot-social">
            <?php if ($fb): ?><a href="<?php echo esc_url($fb); ?>" target="_blank" rel="noopener" aria-label="Facebook"><?php echo $this->icon_facebook(); ?></a><?php endif; ?>
            <?php if ($ig): ?><a href="<?php echo esc_url($ig); ?>" target="_blank" rel="noopener" aria-label="Instagram"><?php echo $this->icon_instagram(); ?></a><?php endif; ?>
            <?php if ($wa): ?><a href="<?php echo esc_url($wa); ?>" target="_blank" rel="noopener" aria-label="Canale WhatsApp"><?php echo $this->icon_whatsapp(); ?></a><?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="gx-foot-bottom"><?php echo esc_html($s('footer_text')); ?> · <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(wp_parse_url(home_url(), PHP_URL_HOST)); ?></a></div>
    </footer>

</div>
        <?php
    }

    /**
     * Orario col giorno di chiusura evidenziato (escaped + <strong>).
     */
    private function hours_html() {
        // Modalita' automatica: la riga orari la scrive il plugin ordini, in
        // base ai giorni di chiusura spuntati li'. Una spunta sola, due posti
        // aggiornati (sito + ordini online).
        $auto = self::auto_hours_html();
        if ($auto !== null) return $auto;

        $hours = (string) GX30_Settings::get('footer_hours');
        // Se il plugin ordini ha un'apertura straordinaria in corso, il footer
        // non deve annunciare una chiusura che in quei giorni non c'e'.
        if (self::closure_suspended()) {
            $alt = trim((string) GX30_Settings::get('footer_hours_suspended'));
            if ($alt !== '') $hours = $alt;
        }
        $word  = trim((string) GX30_Settings::get('footer_hours_highlight'));
        $html  = esc_html($hours);
        if ($word !== '' && mb_stripos($hours, $word) !== false) {
            $html = preg_replace(
                '/' . preg_quote(esc_html($word), '/') . '/iu',
                '<strong class="gx-closed">$0</strong>',
                $html, 1
            );
        }
        return $html;
    }

    /**
     * Riga orari generata dai giorni di chiusura del plugin ordini.
     * null se la sincronizzazione e' spenta o il plugin non e' disponibile:
     * in quel caso vale il testo scritto a mano.
     */
    private static function auto_hours_html() {
        if ((string) GX30_Settings::get('footer_hours_auto', '1') !== '1') return null;
        return GX30_Settings::auto_hours(true); // gia' escaped, giorni in <strong>
    }

    /**
     * True se il plugin ordini ha un'apertura straordinaria attiva (la
     * chiusura settimanale e' sospesa fino a una data). Se il plugin non c'e'
     * o e' una versione precedente, si comporta come se non ci fosse nulla.
     */
    private static function closure_suspended() {
        if (!class_exists('Gauguin_Orders')) return false;
        if (!method_exists('Gauguin_Orders', 'get_closure_suspend_until')) return false;
        return Gauguin_Orders::get_closure_suspend_until() !== '';
    }

    private function icon_pin() {
        return '<svg class="gx-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>';
    }
    private function icon_phone() {
        return '<svg class="gx-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.09 4.18 2 2 0 0 1 4.07 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"/></svg>';
    }
    private function icon_clock() {
        return '<svg class="gx-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
    }
    private function icon_directions() {
        return '<svg class="gx-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>';
    }

    private function icon_facebook() {
        return '<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" focusable="false"><path fill="currentColor" d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.45 2.89h-2.33v6.99A10 10 0 0 0 22 12Z"/></svg>';
    }

    private function icon_instagram() {
        return '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.1" fill="currentColor" stroke="none"/></svg>';
    }

    private function icon_whatsapp() {
        return '<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12.04 2a9.9 9.9 0 0 0-8.46 15.03L2.5 21.5l4.6-1.05A9.9 9.9 0 1 0 12.04 2Zm0 18.06a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-2.73.62.64-2.64-.2-.31a8.2 8.2 0 1 1 6.77 3.66Zm4.5-6.14c-.25-.12-1.46-.72-1.69-.8-.23-.08-.39-.12-.55.12-.16.25-.63.8-.78.97-.14.16-.29.18-.53.06-.25-.12-1.04-.38-1.98-1.22-.73-.65-1.23-1.46-1.37-1.7-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.16.04-.31-.02-.43-.06-.12-.55-1.33-.76-1.82-.2-.48-.4-.41-.55-.42h-.47a.9.9 0 0 0-.65.31c-.23.25-.86.84-.86 2.05 0 1.2.88 2.37 1 2.53.12.16 1.73 2.64 4.2 3.7.59.25 1.05.4 1.4.52.59.19 1.13.16 1.55.1.47-.07 1.46-.6 1.67-1.18.2-.58.2-1.07.14-1.18-.06-.1-.22-.16-.47-.29Z"/></svg>';
    }
}
