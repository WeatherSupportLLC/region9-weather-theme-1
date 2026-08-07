<?php
defined('ABSPATH') || exit;

/**
 * Production graphics renderer for Region 9 public products.
 *
 * The renderer intentionally keeps source/provider names off public artwork.
 * It renders a 1600x1600 SVG master with the approved Region 9 broadcast
 * language, then converts to PNG when Imagick is available. The public URL is
 * stored on the product and the same product state is used for its discussion.
 */
class R9LS_Graphics_Engine {
    const WIDTH = 1600;
    const HEIGHT = 1600;
    const FORECASTER = 'NEAL';
    const RENDER_VERSION = 'r9-production-1';

    private $audit;

    public function __construct($audit = null) { $this->audit = $audit; }

    public function render_product($product, $force = false) {
        if (!function_exists('wp_upload_dir')) {
            $state_hash = hash('sha256', wp_json_encode(array(
                'render_version'=>self::RENDER_VERSION,
                'product_id'=>$product['product_id'] ?? 'region9-product',
                'content_hash'=>$product['content_hash'] ?? '',
                'discussion'=>$product['discussion'] ?? '',
            )));
            $product['graphic_url'] = 'r9ls-validation://' . sanitize_key($product['product_id'] ?? 'region9-product');
            $product['graphic_path'] = '';
            $product['graphic_hash'] = $state_hash;
            $product['graphic_generated_at'] = current_time('mysql');
            $product['graphic_renderer'] = self::RENDER_VERSION;
            $product['forecaster'] = self::FORECASTER;
            $product['discussion_state_hash'] = hash('sha256', (string)($product['discussion'] ?? ''));
            return $product;
        }
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) { return $product; }
        $dir = trailingslashit($uploads['basedir']) . 'region9-generated';
        $url = trailingslashit($uploads['baseurl']) . 'region9-generated';
        if (!wp_mkdir_p($dir)) { return $product; }

        $id = sanitize_key($product['product_id'] ?? 'region9-product');
        $state_hash = hash('sha256', wp_json_encode(array(
            'render_version' => self::RENDER_VERSION,
            'product_id' => $id,
            'risk' => $product['risk'] ?? array(),
            'score' => $product['score'] ?? 0,
            'confidence' => $product['confidence'] ?? 0,
            'timing' => $product['timing'] ?? array(),
            'summary' => $product['summary'] ?? '',
            'discussion' => $product['discussion'] ?? '',
            'counties' => $product['affected_counties'] ?? array(),
            'drivers' => $product['primary_drivers'] ?? array(),
        )));
        $base = $id . '-' . substr($state_hash, 0, 16);
        $png_path = trailingslashit($dir) . $base . '.png';
        $svg_path = trailingslashit($dir) . $base . '.svg';
        $public_path = $png_path;
        $public_url = trailingslashit($url) . $base . '.png';

        if (!$force && file_exists($png_path)) {
            return $this->attach_metadata($product, $public_url, $png_path, $state_hash);
        }

        $svg = $this->svg($product);
        file_put_contents($svg_path, $svg, LOCK_EX);
        if (class_exists('Imagick')) {
            try {
                $im = new Imagick();
                $im->setBackgroundColor('white');
                $im->readImageBlob($svg);
                $im->setImageFormat('png32');
                $im->resizeImage(self::WIDTH, self::HEIGHT, Imagick::FILTER_LANCZOS, 1, true);
                $im->writeImage($png_path);
                $im->clear();
                $im->destroy();
            } catch (Exception $e) {
                if ($this->audit) { $this->audit->write('warning', 'Region 9 PNG rendering fell back to SVG.', array('product'=>$id,'error'=>$e->getMessage())); }
            }
        }
        if (!file_exists($png_path)) {
            $public_path = $svg_path;
            $public_url = trailingslashit($url) . $base . '.svg';
        }
        return $this->attach_metadata($product, $public_url, $public_path, $state_hash);
    }

    private function attach_metadata($product, $url, $path, $hash) {
        $product['graphic_url'] = esc_url_raw($url);
        $product['graphic_path'] = $path;
        $product['graphic_hash'] = $hash;
        $product['graphic_generated_at'] = current_time('mysql');
        $product['graphic_renderer'] = self::RENDER_VERSION;
        $product['forecaster'] = self::FORECASTER;
        $product['discussion_state_hash'] = hash('sha256', (string)($product['discussion'] ?? ''));
        return $product;
    }

    private function svg($p) {
        $title = $this->x($p['title'] ?? 'Region 9 Weather Update');
        $risk = strtoupper($this->risk_label($p));
        $confidence = max(0, min(100, (int)($p['confidence'] ?? 0)));
        $summary = $this->wrap($p['summary'] ?? '', 48, 3);
        $discussion = $this->wrap($p['discussion'] ?? '', 72, 5);
        $counties = $p['affected_counties'] ?? array();
        $county_line = $counties ? implode(' • ', array_map(array($this,'x'), $counties)) : 'Kankakee • Watseka • Pontiac • Paxton • Bloomington • Clinton • Monticello • Champaign • Danville';
        $timing = $this->x($p['timing']['local'] ?? ($p['timing']['label'] ?? 'Continue monitoring updates'));
        $drivers = array_values(array_filter((array)($p['primary_drivers'] ?? array())));
        $driver1 = $this->x($drivers[0] ?? 'Forecast trends');
        $driver2 = $this->x($drivers[1] ?? 'Timing and coverage');
        $driver3 = $this->x($drivers[2] ?? 'Local impacts');
        $palette = $this->risk_palette($risk);
        $date = $this->x(function_exists('wp_date') ? wp_date('l, F j, Y') : date('l, F j, Y'));
        $updated = $this->x(function_exists('wp_date') ? wp_date('g:i A T') : date('g:i A'));
        $main = $this->x($this->one_line($p['summary'] ?? 'Continue to monitor Region 9 Weather updates.', 118));

        $panel = function($x,$y,$w,$h,$heading,$body,$accent='#FDB515') {
            return '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" rx="22" fill="#FFFFFF" stroke="#06264B" stroke-width="4"/>' .
                '<path d="M'.$x.' '.($y+22).' Q'.$x.' '.$y.' '.($x+22).' '.$y.' H'.($x+$w-22).' Q'.($x+$w).' '.$y.' '.($x+$w).' '.($y+22).' V'.($y+72).' H'.$x.' Z" fill="#06264B"/>' .
                '<text x="'.($x+26).'" y="'.($y+48).'" class="panelHead">'.$heading.'</text>' . $body;
        };

        $body1 = '<circle cx="145" cy="765" r="70" fill="#06264B"/><text x="145" y="790" class="icon" text-anchor="middle">☀</text>' .
                 '<text x="245" y="742" class="bigDark">'.$driver1.'</text><text x="245" y="790" class="bodyDark">Primary forecast signal</text>';
        $body2 = '<circle cx="650" cy="765" r="70" fill="#06264B"/><text x="650" y="790" class="icon" text-anchor="middle">◷</text>' .
                 '<text x="750" y="742" class="bigDark">'.$driver2.'</text><text x="750" y="790" class="bodyDark">'.$timing.'</text>';
        $body3 = '<circle cx="1155" cy="765" r="70" fill="#06264B"/><text x="1155" y="790" class="icon" text-anchor="middle">✓</text>' .
                 '<text x="1255" y="742" class="bigDark">'.$driver3.'</text><text x="1255" y="790" class="bodyDark">Decision support focus</text>';

        $discussionLines = '';
        $dy = 1110;
        foreach ($discussion as $line) { $discussionLines .= '<text x="82" y="'.$dy.'" class="discussion">'.$this->x($line).'</text>'; $dy += 42; }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="1600" viewBox="0 0 1600 1600">' .
        '<defs><linearGradient id="gold" x1="0" x2="1"><stop stop-color="#F6A800"/><stop offset=".5" stop-color="#FFD54A"/><stop offset="1" stop-color="#F4A000"/></linearGradient><linearGradient id="navy" x1="0" y1="1"><stop stop-color="#021A36"/><stop offset="1" stop-color="#0A4F83"/></linearGradient><style><![CDATA[text{font-family:Arial,Helvetica,sans-serif}.brand{font-size:78px;font-weight:900;font-style:italic;fill:#06264B}.title{font-size:62px;font-weight:900;fill:#06264B;letter-spacing:-1px}.sub{font-size:28px;font-weight:700;fill:#06264B}.risk{font-size:66px;font-weight:900;fill:'.$palette['text'].'}.panelHead{font-size:27px;font-weight:900;fill:white}.bigDark{font-size:29px;font-weight:900;fill:#06264B}.bodyDark{font-size:22px;font-weight:700;fill:#23334A}.icon{font-size:66px;font-weight:900;fill:white}.discussion{font-size:29px;font-weight:600;fill:#23334A}.footer{font-size:27px;font-weight:800;fill:white}.gold{fill:#FDB515}]]></style></defs>' .
        '<rect width="1600" height="1600" fill="#FDFDFD"/><rect width="1600" height="18" fill="#FDB515"/>' .
        '<path d="M0 0 H650 L555 300 H0 Z" fill="#FFFFFF"/><path d="M0 278 L700 278 L0 370 Z" fill="#06264B"/><path d="M0 278 L700 278 L640 287 L0 342 Z" fill="#FDB515"/>' .
        '<text x="48" y="125" class="brand">REGION <tspan fill="#F4A000">9</tspan></text><text x="54" y="173" class="sub" letter-spacing="13">WEATHER</text>' .
        '<text x="740" y="80" class="title">'.$title.'</text><text x="740" y="127" class="sub">EAST-CENTRAL ILLINOIS</text><line x1="740" y1="150" x2="1518" y2="150" stroke="#FDB515" stroke-width="7"/>' .
        '<text x="740" y="205" class="sub">'.$date.'</text><text x="740" y="250" class="sub" font-size="22">'.$this->x($county_line).'</text>' .
        '<rect x="28" y="392" width="1544" height="205" rx="28" fill="url(#navy)"/><text x="72" y="466" class="panelHead" font-size="34">OVERALL REGION 9 CONCERN</text>' .
        '<rect x="575" y="420" width="950" height="145" rx="20" fill="'.$palette['fill'].'"/><text x="1050" y="517" class="risk" text-anchor="middle">'.$risk.'</text>' .
        '<g transform="translate(36,610)"><rect width="1528" height="52" rx="9" fill="#E7EBEF"/><rect width="382" height="52" fill="#25933B"/><rect x="382" width="382" height="52" fill="#FFC51B"/><rect x="764" width="382" height="52" fill="#F46A0A"/><rect x="1146" width="382" height="52" fill="#C80B20"/><text x="191" y="36" class="panelHead" text-anchor="middle">LOW</text><text x="573" y="36" class="panelHead" fill="#111" text-anchor="middle">LIMITED</text><text x="955" y="36" class="panelHead" text-anchor="middle">ELEVATED</text><text x="1337" y="36" class="panelHead" text-anchor="middle">SIGNIFICANT</text></g>' .
        $panel(28,695,490,270,'FORECAST FOCUS',$body1) . $panel(555,695,490,270,'TIMING',$body2) . $panel(1082,695,490,270,'DECISION SUPPORT',$body3) .
        '<rect x="28" y="995" width="1544" height="355" rx="22" fill="#FFFFFF" stroke="#06264B" stroke-width="4"/><rect x="28" y="995" width="1544" height="72" rx="22" fill="#06264B"/><text x="66" y="1044" class="panelHead">FORECAST DISCUSSION</text>' . $discussionLines .
        '<rect x="28" y="1370" width="1544" height="92" rx="18" fill="#06264B"/><text x="66" y="1428" class="footer"><tspan class="gold">⚡ MAIN MESSAGE:</tspan> '.$main.'</text>' .
        '<rect x="0" y="1480" width="1600" height="120" fill="#06264B"/><text x="48" y="1548" class="footer">⚡ STAY WEATHER AWARE. <tspan class="gold">STAY PREPARED.</tspan></text><text x="800" y="1548" class="footer" text-anchor="middle">Updated '.$updated.'</text><text x="1540" y="1548" class="footer" text-anchor="end">FORECASTER: <tspan class="gold">'.self::FORECASTER.'</tspan></text>' .
        '</svg>';
    }

    private function risk_label($p) { $r = $p['risk'] ?? 'None'; return is_array($r) ? ($r['label'] ?? 'None') : $r; }
    private function risk_palette($risk) { $r = strtoupper($risk); if (strpos($r,'SIGNIFICANT')!==false || strpos($r,'HIGH')!==false) return array('fill'=>'#C80B20','text'=>'#FFFFFF'); if (strpos($r,'ELEVATED')!==false) return array('fill'=>'#F46A0A','text'=>'#FFFFFF'); if (strpos($r,'LIMITED')!==false) return array('fill'=>'url(#gold)','text'=>'#06264B'); return array('fill'=>'#25933B','text'=>'#FFFFFF'); }
    private function x($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8'); }
    private function one_line($s,$max) { $s=preg_replace('/\s+/',' ',trim((string)$s)); return strlen($s)>$max ? rtrim(substr($s,0,$max-1)).'…' : $s; }
    private function wrap($s,$width,$max) { $lines=explode("\n",wordwrap(preg_replace('/\s+/',' ',trim((string)$s)),$width,"\n",true)); return array_slice($lines,0,$max); }
}
