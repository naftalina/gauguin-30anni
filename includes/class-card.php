<?php
if (!defined('ABSPATH')) exit;

/**
 * Genera lato server (GD) l'immagine "card" di un ricordo, per allegarla
 * alla mail di notifica. Rispecchia la card disegnata su canvas nell'admin.
 *
 * Robusto: se GD o i font TTF non sono disponibili, i metodi ritornano
 * valori vuoti e la mail viene comunque inviata senza allegato.
 */
class GX30_Card {

    /* Palette "30 Anni" */
    private static $CREAM  = [0xF7, 0xED, 0xDD];
    private static $INK    = [0x3A, 0x13, 0x18];
    private static $BORD   = [0x9E, 0x15, 0x2A];
    private static $BORD2  = [0xA6, 0x18, 0x2D];
    private static $BORDD  = [0x7C, 0x0F, 0x20];
    private static $PINK   = [0xF0, 0xC9, 0xCF];
    private static $WHITE  = [0xFF, 0xFF, 0xFF];

    private static function font_dir() { return GX30_DIR . 'public/assets/fonts-ttf/'; }
    private static function f_italic()  { return self::font_dir() . 'Spectral-Italic.ttf'; }
    private static function f_bold()     { return self::font_dir() . 'Spectral-SemiBold.ttf'; }
    private static function f_anton()    { return self::font_dir() . 'Anton-Regular.ttf'; }

    /**
     * True se possiamo generare l'immagine su questo server.
     */
    public static function available() {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext') || !function_exists('imagettfbbox')) {
            return false;
        }
        foreach ([self::f_italic(), self::f_bold(), self::f_anton()] as $f) {
            if (!is_readable($f)) return false;
        }
        return true;
    }

    /**
     * Crea i file PNG (quadrato + storia) e ne ritorna i percorsi temporanei,
     * pronti come $attachments per wp_mail(). Array vuoto se non generabile.
     */
    public static function make_attachments($name, $memory) {
        if (!self::available()) return [];
        $paths = [];
        foreach (['square', 'story'] as $fmt) {
            $png = self::render($name, $memory, $fmt);
            if ($png === '') continue;
            $slug = sanitize_title($name);
            if ($slug === '') $slug = 'ricordo';
            $tmp = trailingslashit(get_temp_dir()) . 'gauguin-ricordo-' . $slug . '-' . $fmt . '-' . wp_generate_password(6, false) . '.png';
            if (@file_put_contents($tmp, $png) !== false) {
                $paths[] = $tmp;
            }
        }
        return $paths;
    }

    /**
     * Elimina i file temporanei dopo l'invio della mail.
     */
    public static function cleanup($paths) {
        if (!is_array($paths)) return;
        foreach ($paths as $p) {
            if (is_string($p) && $p !== '' && file_exists($p)) @unlink($p);
        }
    }

    /**
     * Rende la card in PNG (stringa binaria). '' se qualcosa va storto.
     */
    public static function render($name, $memory, $fmt = 'square') {
        $W = 1080;
        $H = ($fmt === 'story') ? 1920 : 1080;

        $img = imagecreatetruecolor($W, $H);
        if (!$img) return '';
        imagealphablending($img, true);
        imagesavealpha($img, false);

        // Sfondo bordeaux (gradiente verticale)
        for ($y = 0; $y < $H; $y++) {
            $t = $y / $H;
            $r = (int) round(self::$BORD2[0] + (self::$BORD[0] - self::$BORD2[0]) * $t);
            $g = (int) round(self::$BORD2[1] + (self::$BORD[1] - self::$BORD2[1]) * $t);
            $b = (int) round(self::$BORD2[2] + (self::$BORD[2] - self::$BORD2[2]) * $t);
            $c = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, $W, $y, $c);
        }

        // Colori riutilizzabili
        $cream = imagecolorallocate($img, self::$CREAM[0], self::$CREAM[1], self::$CREAM[2]);
        $ink   = imagecolorallocate($img, self::$INK[0], self::$INK[1], self::$INK[2]);
        $bord  = imagecolorallocate($img, self::$BORD[0], self::$BORD[1], self::$BORD[2]);
        $bord2 = imagecolorallocate($img, self::$BORD2[0], self::$BORD2[1], self::$BORD2[2]);
        $bordd = imagecolorallocate($img, self::$BORDD[0], self::$BORDD[1], self::$BORDD[2]);
        $pink  = imagecolorallocate($img, self::$PINK[0], self::$PINK[1], self::$PINK[2]);
        $white = imagecolorallocate($img, self::$WHITE[0], self::$WHITE[1], self::$WHITE[2]);

        // Logo lockup (PNG con trasparenza)
        $logoTop    = ($fmt === 'story') ? 150 : 100;
        $logoMaxW   = ($fmt === 'story') ? 620 : 560;
        $logoBottom = $logoTop;
        $logoPath   = self::lockup_path();
        if ($logoPath) {
            $logo = @imagecreatefrompng($logoPath);
            if ($logo) {
                $lw0 = imagesx($logo); $lh0 = imagesy($logo);
                if ($lw0 > 0) {
                    $lw = $logoMaxW; $lh = (int) round($lw * $lh0 / $lw0);
                    imagecopyresampled($img, $logo, (int) round(($W - $lw) / 2), $logoTop, 0, 0, $lw, $lh, $lw0, $lh0);
                    $logoBottom = $logoTop + $lh;
                }
                imagedestroy($logo);
            }
        }

        // Aree
        $bottomY     = $H - (($fmt === 'story') ? 150 : 100);
        $invitoBlock = 130;
        $availTop    = $logoBottom + (($fmt === 'story') ? 90 : 56);
        $availBottom = $bottomY - $invitoBlock;

        // Auto-fit del testo del ricordo
        $margin   = 96;
        $cardPad  = 60;
        $maxTextW = $W - $margin * 2 - $cardPad * 2;
        $quote    = '« ' . $memory . ' »';
        $qSize    = ($fmt === 'story') ? 44 : 40;
        $minSize  = 20;
        $nameSize = ($fmt === 'story') ? 28 : 26;
        $gapQN    = 38;
        $divGap   = 30;
        $lines = []; $lineH = 0;
        while ($qSize >= $minSize) {
            $lines = self::wrap($quote, self::f_italic(), $qSize, $maxTextW);
            $lineH = (int) round($qSize * 1.72);
            $block = count($lines) * $lineH + $divGap + $gapQN + (int) round($nameSize * 1.4);
            if ($block <= ($availBottom - $availTop - $cardPad * 2)) break;
            $qSize -= 2;
        }
        $lineH = (int) round($qSize * 1.72);

        $textBlockH = count($lines) * $lineH + $divGap + $gapQN + (int) round($nameSize * 1.4);
        $cardH = $textBlockH + $cardPad * 2;
        $cardW = $W - $margin * 2;
        $cardX = $margin;
        $cardY = $availTop + (int) round(max(0, (($availBottom - $availTop) - $cardH) / 2));
        $radius = 26;

        // Card: barra bordeaux in alto + corpo panna
        self::round_rect($img, $cardX, $cardY, $cardW, $cardH, $radius, $bord2);
        self::round_rect($img, $cardX, $cardY + 12, $cardW, $cardH - 12, $radius, $cream);

        // Testo del ricordo (Spectral corsivo)
        $ty = $cardY + 12 + $cardPad + $qSize;
        foreach ($lines as $line) {
            self::center_text($img, $qSize, self::f_italic(), $ink, $W, $ty, $line);
            $ty += $lineH;
        }

        // Divisore
        $ty += 6;
        imagesetthickness($img, 3);
        imageline($img, (int) ($W / 2 - 42), $ty, (int) ($W / 2 + 42), $ty, $bord2);
        imagesetthickness($img, 1);
        $ty += $gapQN;

        // Nome (Anton, tracking)
        self::tracked_text($img, $nameSize, self::f_anton(), $bordd, $W / 2, $ty + $nameSize, self::upper($name), 2);

        // Invito in basso
        $kick = ($fmt === 'story') ? 20 : 19;
        $hostSize = ($fmt === 'story') ? 34 : 32;
        self::tracked_text($img, $kick, self::f_bold(), $pink, $W / 2, $bottomY - 46, self::upper(GX30_Settings::get('mem_share_invite', 'Raccontaci anche il tuo ricordo')), 5);
        $host = preg_replace('/^www\./', '', (string) wp_parse_url(home_url(), PHP_URL_HOST));
        self::tracked_text($img, $hostSize, self::f_anton(), $white, $W / 2, $bottomY + 6, self::upper($host), 2);

        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);
        return is_string($data) ? $data : '';
    }

    /* ---------------- helper ---------------- */

    private static function upper($s) {
        return function_exists('mb_strtoupper') ? mb_strtoupper((string) $s, 'UTF-8') : strtoupper((string) $s);
    }

    /**
     * Percorso locale del lockup (custom se e' un upload locale, altrimenti bundle).
     */
    private static function lockup_path() {
        $custom = trim((string) GX30_Settings::get('lockup_image'));
        if ($custom !== '') {
            $up = wp_get_upload_dir();
            if (!empty($up['baseurl']) && strpos($custom, $up['baseurl']) === 0) {
                $p = $up['basedir'] . substr($custom, strlen($up['baseurl']));
                if (is_readable($p) && preg_match('/\.png$/i', $p)) return $p;
            }
        }
        $bundle = GX30_DIR . 'public/assets/gauguin-30-lockup.png';
        return is_readable($bundle) ? $bundle : '';
    }

    private static function text_width($size, $font, $text) {
        $b = imagettfbbox($size, 0, $font, $text);
        return abs($b[2] - $b[0]);
    }

    private static function wrap($text, $font, $size, $maxW) {
        $words = preg_split('/\s+/u', trim($text));
        $lines = []; $cur = '';
        foreach ($words as $w) {
            $try = ($cur === '') ? $w : $cur . ' ' . $w;
            if ($cur !== '' && self::text_width($size, $font, $try) > $maxW) {
                $lines[] = $cur; $cur = $w;
            } else {
                $cur = $try;
            }
        }
        if ($cur !== '') $lines[] = $cur;
        return $lines;
    }

    private static function center_text($img, $size, $font, $color, $W, $baselineY, $text) {
        $b = imagettfbbox($size, 0, $font, $text);
        $w = $b[2] - $b[0];
        $x = (int) round(($W - $w) / 2 - $b[0]);
        imagettftext($img, $size, 0, $x, (int) round($baselineY), $color, $font, $text);
    }

    /**
     * Testo centrato con spaziatura tra le lettere (letter-spacing).
     */
    private static function tracked_text($img, $size, $font, $color, $cx, $baselineY, $text, $track) {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $widths = []; $total = 0;
        foreach ($chars as $ch) {
            $w = self::text_width($size, $font, $ch);
            $widths[] = $w;
            $total += $w + $track;
        }
        $total -= $track;
        $x = (int) round($cx - $total / 2);
        foreach ($chars as $i => $ch) {
            $b = imagettfbbox($size, 0, $font, $ch);
            imagettftext($img, $size, 0, $x - $b[0], (int) round($baselineY), $color, $font, $ch);
            $x += $widths[$i] + $track;
        }
    }

    private static function round_rect($img, $x, $y, $w, $h, $r, $color) {
        $x = (int) $x; $y = (int) $y; $w = (int) $w; $h = (int) $h; $r = (int) $r;
        imagefilledrectangle($img, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($img, $x, $y + $r, $x + $w, $y + $h - $r, $color);
        imagefilledarc($img, $x + $r,        $y + $r,        2 * $r, 2 * $r, 180, 270, $color, IMG_ARC_PIE);
        imagefilledarc($img, $x + $w - $r,   $y + $r,        2 * $r, 2 * $r, 270, 360, $color, IMG_ARC_PIE);
        imagefilledarc($img, $x + $r,        $y + $h - $r,   2 * $r, 2 * $r,  90, 180, $color, IMG_ARC_PIE);
        imagefilledarc($img, $x + $w - $r,   $y + $h - $r,   2 * $r, 2 * $r,   0,  90, $color, IMG_ARC_PIE);
    }
}
