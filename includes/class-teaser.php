<?php
if (!defined('ABSPATH')) exit;

/**
 * Popup "teaser" del muro dei ricordi, mostrato SOLO in home.
 * Carica un mini CSS/JS che, a runtime, chiama l'endpoint REST
 * /ricordo-casuale e mostra un bigliettino con l'invito a lasciare il
 * proprio ricordo (link al form della landing). Una volta a sessione.
 */
class GX30_Teaser {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue() {
        if (is_admin() || !is_front_page()) return;

        // Se la home coincide con la landing 30 Anni, il muro + form sono gia'
        // in pagina: niente popup, sarebbe un doppione.
        $id = get_queried_object_id();
        if ($id && get_post_meta($id, '_wp_page_template', true) === GX30_TEMPLATE_SLUG) return;

        wp_enqueue_style('gx30-fonts', GX30_URL . 'public/assets/fonts.css', [], GX30_VERSION);
        wp_enqueue_style('gx30-teaser', GX30_URL . 'public/assets/teaser.css', ['gx30-fonts'], GX30_VERSION);
        wp_enqueue_script('gx30-teaser', GX30_URL . 'public/assets/teaser.js', [], GX30_VERSION, true);

        wp_localize_script('gx30-teaser', 'GX30T', [
            'rest'    => esc_url_raw(rest_url('gauguin30/v1/ricordo-casuale')),
            'landing' => esc_url_raw(GX30_Template::landing_url()) . '#gx-form',
            'kicker'  => 'I ricordi dei nostri clienti',
            'cta'     => 'Racconta il tuo ricordo',
        ]);
    }
}
