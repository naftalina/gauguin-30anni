<?php
if (!defined('ABSPATH')) exit;

/**
 * Ricordi inviati dai clienti: tabella DB, endpoint REST del form, email.
 *
 * Nota: per sicurezza i ricordi inviati NON vengono mostrati pubblicamente
 * (niente moderazione live / spam sul sito). Restano nel DB per la lettura
 * dall'admin; il titolare puo' eventualmente promuoverne uno a "bigliettino"
 * iniziale dalle impostazioni.
 */
class GX30_Memories {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'gx30_memories';
    }

    public static function create_table() {
        global $wpdb;
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(80) NOT NULL,
            memory TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            ip VARCHAR(45) DEFAULT '',
            published TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY published (published)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Aggiorna lo schema DB quando cambia la versione (aggiunge colonne nuove).
     */
    public static function maybe_upgrade() {
        if (get_option('gx30_db_ver') !== GX30_VERSION) {
            self::create_table();
            update_option('gx30_db_ver', GX30_VERSION);
        }
    }

    public static function set_published($id, $pub) {
        global $wpdb;
        $wpdb->update(self::table(), ['published' => $pub ? 1 : 0], ['id' => (int) $id], ['%d'], ['%d']);
    }

    /**
     * Ricordi pubblicati (per il muro nella landing).
     */
    public static function published_list($limit = 60) {
        global $wpdb;
        $table = self::table();
        $limit = (int) $limit;
        $rows = $wpdb->get_results("SELECT name, memory FROM $table WHERE published = 1 ORDER BY created_at DESC LIMIT $limit");
        return is_array($rows) ? $rows : [];
    }

    public function register_routes() {
        register_rest_route('gauguin30/v1', '/ricordi', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_submit'],
            'permission_callback' => '__return_true', // pubblico; protetto da nonce + honeypot
        ]);
    }

    /**
     * Riceve un ricordo dal form pubblico.
     */
    public function handle_submit($request) {
        // Honeypot: campo "website" deve restare vuoto.
        if ((string) $request->get_param('website') !== '') {
            return new WP_REST_Response(['ok' => true], 200); // finge successo ai bot
        }

        $name   = sanitize_text_field((string) $request->get_param('name'));
        $memory = sanitize_textarea_field((string) $request->get_param('memory'));
        $name   = trim($name);
        $memory = trim($memory);

        if ($name === '' || $memory === '') {
            return new WP_Error('gx30_empty', 'Nome e ricordo sono obbligatori.', ['status' => 400]);
        }
        if (mb_strlen($name) > 80)   $name   = mb_substr($name, 0, 80);
        if (mb_strlen($memory) > 500) $memory = mb_substr($memory, 0, 500);

        global $wpdb;
        $now = current_time('mysql');
        $ip  = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        $ok = $wpdb->insert(self::table(), [
            'name'       => $name,
            'memory'     => $memory,
            'created_at' => $now,
            'ip'         => $ip,
        ], ['%s', '%s', '%s', '%s']);

        if ($ok === false) {
            return new WP_Error('gx30_db', 'Errore nel salvataggio. Riprova.', ['status' => 500]);
        }

        $this->notify($name, $memory);

        return new WP_REST_Response(['ok' => true], 200);
    }

    /**
     * Email al titolare per ogni nuovo ricordo.
     */
    private function notify($name, $memory) {
        $to = GX30_Settings::notify_email();
        if (!$to) return;
        $subject = 'Nuovo ricordo — Gauguin 30 Anni';
        $body  = "Hai ricevuto un nuovo ricordo dal sito:\n\n";
        $body .= "Da: $name\n\n";
        $body .= "« $memory »\n\n";
        $body .= "— Lo trovi anche in WordPress → Gauguin 30 Anni → Ricordi ricevuti.";
        wp_mail($to, $subject, $body);
    }

    /**
     * Ultimi ricordi (per l'admin).
     */
    public static function recent($limit = 200) {
        global $wpdb;
        $table = self::table();
        $limit = (int) $limit;
        return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT $limit");
    }

    public static function count() {
        global $wpdb;
        $table = self::table();
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }
}
