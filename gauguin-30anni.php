<?php
/**
 * Plugin Name: Gauguin 30 Anni
 * Plugin URI: https://gauguin.it
 * Description: Landing page del 30° anniversario Gauguin (1996—2026): countdown, "muro dei ricordi" e form, completamente modificabile dall'admin.
 * Version: 1.12.1
 * Author: Gauguin
 * Text Domain: gauguin-30anni
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('GX30_VERSION', '1.12.1');
define('GX30_FILE', __FILE__);
define('GX30_DIR', plugin_dir_path(__FILE__));
define('GX30_URL', plugin_dir_url(__FILE__));
define('GX30_TEMPLATE_SLUG', 'gauguin-30anni'); // valore salvato in _wp_page_template

// Auto-update via GitHub (Plugin Update Checker) — repo privato, modalita' tag-only.
// Riusa lo stesso PAT salvato dal plugin "Gauguin Ordering" (wp_option gauguin_github_token).
require_once GX30_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
$gx30UpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/naftalina/gauguin-30anni/',
    __FILE__,
    'gauguin-30anni'
);
$gx30_github_token = get_option('gauguin_github_token', '');
if (!empty($gx30_github_token)) {
    $gx30UpdateChecker->setAuthentication($gx30_github_token);
}

require_once GX30_DIR . 'includes/class-settings.php';
require_once GX30_DIR . 'includes/class-memories.php';
require_once GX30_DIR . 'includes/class-template.php';
require_once GX30_DIR . 'includes/class-admin.php';

/**
 * Bootstrap: istanzia i moduli.
 */
function gx30_boot() {
    GX30_Settings::instance();
    GX30_Memories::instance();
    GX30_Memories::maybe_upgrade();
    GX30_Template::instance();
    if (is_admin()) {
        GX30_Admin::instance();
    }
}
add_action('plugins_loaded', 'gx30_boot');

/**
 * Attivazione: crea la tabella ricordi e i default delle impostazioni.
 */
function gx30_activate() {
    GX30_Memories::create_table();
    GX30_Settings::seed_defaults();
}
register_activation_hook(__FILE__, 'gx30_activate');
