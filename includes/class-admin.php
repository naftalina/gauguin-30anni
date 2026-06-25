<?php
if (!defined('ABSPATH')) exit;

/**
 * Pannello admin: impostazioni editabili + lista ricordi ricevuti.
 */
class GX30_Admin {

    private static $instance = null;
    const CAP  = 'manage_options';
    const SLUG = 'gauguin-30anni';

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_gx30_save', [$this, 'handle_save']);
        add_action('admin_post_gx30_toggle_memory', [$this, 'handle_toggle_memory']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function menu() {
        add_menu_page(
            'Gauguin 30 Anni', 'Gauguin 30 Anni', self::CAP, self::SLUG,
            [$this, 'render_settings'], 'dashicons-buddicons-activity', 58
        );
        add_submenu_page(self::SLUG, 'Impostazioni', 'Impostazioni', self::CAP, self::SLUG, [$this, 'render_settings']);
        add_submenu_page(self::SLUG, 'Ricordi ricevuti', 'Ricordi ricevuti', self::CAP, self::SLUG . '-ricordi', [$this, 'render_memories']);
    }

    public function assets($hook) {
        if (strpos((string) $hook, self::SLUG) === false) return;
        wp_enqueue_media();
    }

    /* ---------------- Impostazioni ---------------- */

    private function field_text($key, $label, $type = 'text', $desc = '') {
        $val = GX30_Settings::get($key);
        echo '<tr><th scope="row"><label for="gx30_' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
        printf(
            '<input type="%s" id="gx30_%s" name="%s" value="%s" class="regular-text">',
            esc_attr($type), esc_attr($key), esc_attr($key), esc_attr($val)
        );
        if ($desc) echo '<p class="description">' . esc_html($desc) . '</p>';
        echo '</td></tr>';
    }

    private function field_textarea($key, $label, $rows = 3, $desc = '') {
        $val = GX30_Settings::get($key);
        echo '<tr><th scope="row"><label for="gx30_' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
        printf(
            '<textarea id="gx30_%s" name="%s" rows="%d" class="large-text">%s</textarea>',
            esc_attr($key), esc_attr($key), (int) $rows, esc_textarea($val)
        );
        if ($desc) echo '<p class="description">' . esc_html($desc) . '</p>';
        echo '</td></tr>';
    }

    private function field_image($key, $label, $desc = '') {
        $val = GX30_Settings::get($key);
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>';
        printf(
            '<input type="url" id="gx30_%1$s" name="%1$s" value="%2$s" class="regular-text gx30-img-url" placeholder="https://…">
             <button type="button" class="button gx30-img-pick" data-target="gx30_%1$s">Scegli immagine</button>',
            esc_attr($key), esc_attr($val)
        );
        if ($desc) echo '<p class="description">' . esc_html($desc) . '</p>';
        echo '</td></tr>';
    }

    public function render_settings() {
        if (!current_user_can(self::CAP)) return;
        $saved = isset($_GET['gx30_saved']);
        $seeds = GX30_Settings::get('seeds', []);
        if (!is_array($seeds)) $seeds = [];
        ?>
        <div class="wrap">
            <h1>Gauguin 30 Anni — Impostazioni</h1>
            <?php if ($saved): ?><div class="notice notice-success is-dismissible"><p>Impostazioni salvate.</p></div><?php endif; ?>

            <p>Crea una pagina WordPress e scegli il template <strong>“Gauguin 30 Anni”</strong>. Qui sotto modifichi tutti i contenuti.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="gx30_save">
                <?php wp_nonce_field('gx30_save', 'gx30_nonce'); ?>

                <h2 class="title">Evento &amp; countdown</h2>
                <table class="form-table"><tbody>
                    <?php $this->field_text('event_datetime', 'Data e ora dell’evento', 'datetime-local', 'Il countdown punta a questo momento.'); ?>
                </tbody></table>

                <h2 class="title">Barra superiore</h2>
                <table class="form-table"><tbody>
                    <?php $this->field_text('topbar_left', 'Testo a sinistra'); ?>
                    <?php $this->field_text('topbar_right', 'Testo a destra'); ?>
                </tbody></table>

                <h2 class="title">Hero (testata)</h2>
                <table class="form-table"><tbody>
                    <?php $this->field_image('lockup_image', 'Logo “30 anni”', 'Lascia vuoto per usare il logo incluso nel plugin.'); ?>
                    <?php $this->field_text('hero_sub', 'Sottotitolo'); ?>
                    <?php $this->field_textarea('hero_lead', 'Frase introduttiva', 3, 'Scrivi {data} dove vuoi la data dell’evento: si aggiorna da sola col countdown (es. “Li festeggiamo il {data}…”).'); ?>
                    <?php $this->field_text('hero_lead_highlight', 'Evidenzia una frase (opzionale)', 'text', 'Mette in grassetto bianco la frase indicata, se presente. Per la data NON serve: usa {data} nella frase qui sopra.'); ?>
                </tbody></table>

                <h2 class="title">Sezione “La nostra storia”</h2>
                <table class="form-table"><tbody>
                    <?php $this->field_text('story_kicker', 'Etichetta'); ?>
                    <?php $this->field_text('story_title', 'Titolo'); ?>
                    <?php $this->field_textarea('story_p1', 'Paragrafo 1', 4); ?>
                    <?php $this->field_textarea('story_p2', 'Paragrafo 2', 4); ?>
                    <?php $this->field_image('story_image', 'Foto'); ?>
                </tbody></table>

                <h2 class="title">Galleria foto</h2>
                <p class="description">Mosaico a tutta larghezza sotto la testata. Aggiungi le foto; lascia vuoto per nascondere la sezione.</p>
                <div id="gx30-gallery">
                    <?php $gallery = GX30_Settings::get('gallery', []); if (!is_array($gallery)) $gallery = [];
                    foreach ($gallery as $img): ?>
                        <div class="gx30-gallery-item" style="display:inline-block;position:relative;margin:4px;vertical-align:top;">
                            <img src="<?php echo esc_url($img); ?>" style="width:90px;height:90px;object-fit:cover;border-radius:6px;display:block;">
                            <input type="hidden" name="gallery[]" value="<?php echo esc_attr($img); ?>">
                            <button type="button" class="button gx30-gallery-del" style="position:absolute;top:-8px;right:-8px;min-width:0;padding:0 7px;line-height:22px;border-radius:50%;">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p><button type="button" class="button" id="gx30-gallery-add">+ Aggiungi foto</button></p>

                <h2 class="title">Sezione “Ricordi”</h2>
                <table class="form-table"><tbody>
                    <?php $this->field_text('mem_kicker', 'Etichetta'); ?>
                    <?php $this->field_text('mem_title', 'Titolo'); ?>
                    <?php $this->field_textarea('mem_lead', 'Testo introduttivo', 3); ?>
                </tbody></table>

                <h2 class="title">Bigliettini iniziali (svolazzano nella hero)</h2>
                <p class="description">Nome + ricordo. Aggiungi o togli quelli che vuoi.</p>
                <div id="gx30-seeds">
                    <?php foreach ($seeds as $i => $seed):
                        $n = isset($seed['name']) ? $seed['name'] : '';
                        $m = isset($seed['memory']) ? $seed['memory'] : ''; ?>
                        <div class="gx30-seed-row" style="margin:8px 0;display:flex;gap:8px;align-items:flex-start;">
                            <input type="text" name="seeds[<?php echo (int)$i; ?>][name]" value="<?php echo esc_attr($n); ?>" placeholder="Nome" style="width:160px">
                            <textarea name="seeds[<?php echo (int)$i; ?>][memory]" rows="2" placeholder="Ricordo" style="flex:1"><?php echo esc_textarea($m); ?></textarea>
                            <button type="button" class="button gx30-seed-del">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p><button type="button" class="button" id="gx30-seed-add">+ Aggiungi bigliettino</button></p>

                <h2 class="title">SEO &amp; anteprima social</h2>
                <table class="form-table"><tbody>
                    <?php $this->field_textarea('meta_description', 'Descrizione', 2, 'Testo mostrato su Google e quando condividi il link (WhatsApp/Facebook).'); ?>
                    <?php $this->field_image('og_image', 'Immagine anteprima social', 'Consigliata 1200×630px. Vuoto = copertina inclusa nel plugin.'); ?>
                </tbody></table>

                <h2 class="title">Footer &amp; pulsanti</h2>
                <table class="form-table"><tbody>
                    <?php $this->field_text('cta_order_label', 'Etichetta pulsante “Ordina”', 'text', 'Apre il popup ordini (richiede il plugin Gauguin Ordering attivo).'); ?>
                    <?php $this->field_text('cta_reserve_label', 'Etichetta pulsante “Prenota”'); ?>
                    <?php $this->field_text('footer_address', 'Indirizzo'); ?>
                    <?php $this->field_text('footer_phone', 'Telefono'); ?>
                    <?php $this->field_text('footer_hours', 'Orari', 'text', 'Es: Aperti tutti i giorni tranne il martedì. Lascia vuoto per nascondere.'); ?>
                    <?php $this->field_text('footer_hours_highlight', 'Parola da evidenziare nell’orario', 'text', 'Es: martedì (il giorno di chiusura, mostrato in grassetto bordeaux).'); ?>
                    <?php $this->field_text('footer_maps_url', 'Link “Come raggiungerci” (Google Maps)', 'url'); ?>
                    <?php $this->field_text('social_facebook', 'Facebook (URL)', 'url'); ?>
                    <?php $this->field_text('social_instagram', 'Instagram (URL)', 'url'); ?>
                    <?php $this->field_text('footer_text', 'Riga finale del footer'); ?>
                </tbody></table>

                <h2 class="title">Notifiche</h2>
                <table class="form-table"><tbody>
                    <?php $this->field_text('notify_email', 'Email per i nuovi ricordi', 'email', 'Lascia vuoto per usare l’email admin del sito.'); ?>
                </tbody></table>

                <?php submit_button('Salva impostazioni'); ?>
            </form>
        </div>

        <script>
        (function(){
            // Media picker
            document.querySelectorAll('.gx30-img-pick').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    var input = document.getElementById(btn.dataset.target);
                    var frame = wp.media({title:'Scegli immagine', multiple:false, library:{type:'image'}});
                    frame.on('select', function(){
                        var a = frame.state().get('selection').first().toJSON();
                        input.value = a.url;
                    });
                    frame.open();
                });
            });
            // Repeater bigliettini
            var wrap = document.getElementById('gx30-seeds');
            document.getElementById('gx30-seed-add').addEventListener('click', function(){
                var i = wrap.querySelectorAll('.gx30-seed-row').length;
                var row = document.createElement('div');
                row.className = 'gx30-seed-row';
                row.style.cssText = 'margin:8px 0;display:flex;gap:8px;align-items:flex-start;';
                row.innerHTML = '<input type="text" name="seeds['+i+'][name]" placeholder="Nome" style="width:160px">'+
                    '<textarea name="seeds['+i+'][memory]" rows="2" placeholder="Ricordo" style="flex:1"></textarea>'+
                    '<button type="button" class="button gx30-seed-del">×</button>';
                wrap.appendChild(row);
            });
            wrap.addEventListener('click', function(e){
                if (e.target.classList.contains('gx30-seed-del')) {
                    e.target.closest('.gx30-seed-row').remove();
                }
            });

            // Galleria pizze (media multi-select)
            var gWrap = document.getElementById('gx30-gallery');
            document.getElementById('gx30-gallery-add').addEventListener('click', function(){
                var frame = wp.media({title:'Foto pizze', multiple:true, library:{type:'image'}});
                frame.on('select', function(){
                    frame.state().get('selection').each(function(att){
                        var a = att.toJSON();
                        var item = document.createElement('div');
                        item.className = 'gx30-gallery-item';
                        item.style.cssText = 'display:inline-block;position:relative;margin:4px;vertical-align:top;';
                        item.innerHTML = '<img src="'+a.url+'" style="width:90px;height:90px;object-fit:cover;border-radius:6px;display:block;">'+
                            '<input type="hidden" name="gallery[]" value="'+a.url+'">'+
                            '<button type="button" class="button gx30-gallery-del" style="position:absolute;top:-8px;right:-8px;min-width:0;padding:0 7px;line-height:22px;border-radius:50%;">×</button>';
                        gWrap.appendChild(item);
                    });
                });
                frame.open();
            });
            gWrap.addEventListener('click', function(e){
                if (e.target.classList.contains('gx30-gallery-del')) {
                    e.target.closest('.gx30-gallery-item').remove();
                }
            });
        })();
        </script>
        <?php
    }

    public function handle_save() {
        if (!current_user_can(self::CAP)) wp_die('Permesso negato.');
        check_admin_referer('gx30_save', 'gx30_nonce');

        $in = wp_unslash($_POST);
        $out = GX30_Settings::all();

        $text_keys = ['event_datetime','topbar_left','topbar_right','hero_sub','hero_lead_highlight',
                      'story_kicker','story_title','mem_kicker','mem_title',
                      'cta_order_label','cta_reserve_label','footer_address','footer_phone','footer_hours','footer_hours_highlight','footer_text'];
        foreach ($text_keys as $k) {
            if (isset($in[$k])) $out[$k] = sanitize_text_field($in[$k]);
        }
        $textarea_keys = ['hero_lead','story_p1','story_p2','mem_lead','meta_description'];
        foreach ($textarea_keys as $k) {
            if (isset($in[$k])) $out[$k] = sanitize_textarea_field($in[$k]);
        }
        foreach (['lockup_image','story_image','og_image','footer_maps_url','social_facebook','social_instagram'] as $k) {
            if (isset($in[$k])) $out[$k] = esc_url_raw(trim($in[$k]));
        }
        if (isset($in['notify_email'])) {
            $out['notify_email'] = sanitize_email($in['notify_email']);
        }

        // Bigliettini
        $seeds = [];
        if (isset($in['seeds']) && is_array($in['seeds'])) {
            foreach ($in['seeds'] as $row) {
                $n = isset($row['name']) ? sanitize_text_field($row['name']) : '';
                $m = isset($row['memory']) ? sanitize_textarea_field($row['memory']) : '';
                $n = trim($n); $m = trim($m);
                if ($n !== '' && $m !== '') $seeds[] = ['name' => $n, 'memory' => $m];
            }
        }
        $out['seeds'] = $seeds;

        // Galleria pizze
        $gallery = [];
        if (isset($in['gallery']) && is_array($in['gallery'])) {
            foreach ($in['gallery'] as $u) {
                $u = esc_url_raw(trim($u));
                if ($u !== '') $gallery[] = $u;
            }
        }
        $out['gallery'] = $gallery;

        update_option(GX30_Settings::OPTION, $out);

        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'gx30_saved' => 1], admin_url('admin.php')));
        exit;
    }

    public function handle_toggle_memory() {
        if (!current_user_can(self::CAP)) wp_die('Permesso negato.');
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        check_admin_referer('gx30_toggle_' . $id, 'gx30_tnonce');
        $pub = isset($_POST['pub']) && $_POST['pub'] === '1';
        GX30_Memories::set_published($id, $pub);
        wp_safe_redirect(add_query_arg(['page' => self::SLUG . '-ricordi'], admin_url('admin.php')));
        exit;
    }

    /* ---------------- Ricordi ricevuti ---------------- */

    public function render_memories() {
        if (!current_user_can(self::CAP)) return;
        $rows = GX30_Memories::recent(500);
        $total = GX30_Memories::count();
        ?>
        <div class="wrap">
            <h1>Ricordi ricevuti <span class="title-count" style="font-size:13px;color:#666;">(<?php echo (int)$total; ?>)</span></h1>
            <?php if (empty($rows)): ?>
                <p>Ancora nessun ricordo ricevuto.</p>
            <?php else: ?>
            <p class="description">“Pubblica” fa comparire il ricordo nel muro che svolazza nella testata della landing.</p>
            <table class="widefat striped" style="margin-top:12px;">
                <thead><tr><th style="width:130px;">Data</th><th style="width:150px;">Nome</th><th>Ricordo</th><th style="width:170px;">Pubblicazione</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo esc_html(mysql2date('d/m/Y H:i', $r->created_at)); ?></td>
                        <td><?php echo esc_html($r->name); ?></td>
                        <td><?php echo esc_html($r->memory); ?></td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;display:flex;align-items:center;gap:8px;">
                                <input type="hidden" name="action" value="gx30_toggle_memory">
                                <input type="hidden" name="id" value="<?php echo (int) $r->id; ?>">
                                <input type="hidden" name="pub" value="<?php echo $r->published ? '0' : '1'; ?>">
                                <?php wp_nonce_field('gx30_toggle_' . (int) $r->id, 'gx30_tnonce'); ?>
                                <?php if ($r->published): ?>
                                    <span style="color:#1f7e49;font-weight:600;">● Pubblicato</span>
                                    <button class="button button-small">Nascondi</button>
                                <?php else: ?>
                                    <span style="color:#999;">○ Nascosto</span>
                                    <button class="button button-small button-primary">Pubblica</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
