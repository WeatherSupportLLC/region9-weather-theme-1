<?php
defined('ABSPATH') || exit;

final class R9LS_Graphic_Renderer {
    const MANIFEST = 'r9ls_graphic_manifest';
    const LAST = 'r9ls_graphic_generation_last';
    private $audit;

    public function __construct($audit = null) { $this->audit = $audit; }
    public function hooks() { add_action('r9ls_products_published', array($this,'render_published'), 5, 3); }

    public function render_published($products, $changed_ids, $context = array()) {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) { return $this->fail('WordPress uploads directory is unavailable.'); }
        $dir = trailingslashit($uploads['basedir']) . 'region9-weather/generated';
        $url = trailingslashit($uploads['baseurl']) . 'region9-weather/generated';
        if (!wp_mkdir_p($dir)) { return $this->fail('Region 9 generated graphics directory could not be created.'); }

        $manifest = get_option(self::MANIFEST, array());
        $stored = get_option(R9LS_Product_Generator::PRODUCTS, array());
        $rendered = array();
        foreach ((array)$changed_ids as $id) {
            if (empty($products[$id])) continue;
            $product = $products[$id];
            $hash = sanitize_key(substr((string)($product['content_hash'] ?? hash('sha256', wp_json_encode($product))),0,16));
            $base = sanitize_file_name($id . '-' . $hash);
            $svg_name = $base . '.svg';
            $svg_path = $dir . '/' . $svg_name;
            $svg_url = $url . '/' . $svg_name;
            $svg = $this->svg($product);
            if (file_put_contents($svg_path, $svg) === false) { continue; }
            $image_url = $svg_url;
            $png_name = $base . '.png';
            $png_path = $dir . '/' . $png_name;
            if ($this->png_from_svg($svg, $png_path)) { $image_url = $url . '/' . $png_name; }
            $alt = $this->alt_text($product);
            if (isset($stored[$id])) {
                $stored[$id]['graphic_url'] = esc_url_raw($image_url);
                $stored[$id]['graphic_svg_url'] = esc_url_raw($svg_url);
                $stored[$id]['image_url'] = esc_url_raw($image_url);
                $stored[$id]['graphic_alt'] = $alt;
                $stored[$id]['graphic_generated_at'] = current_time('mysql');
            }
            $manifest[$id] = array(
                'product_id'=>$id,'content_hash'=>$product['content_hash'] ?? '','url'=>esc_url_raw($image_url),'svg_url'=>esc_url_raw($svg_url),
                'alt'=>$alt,'generated_at'=>current_time('mysql'),'publication_version'=>$product['publication_version'] ?? '',
            );
            $rendered[] = $id;
        }
        update_option(R9LS_Product_Generator::PRODUCTS, $stored, false);
        update_option(self::MANIFEST, $manifest, false);
        delete_transient(R9LS_Product_Generator::CACHE_PREFIX . 'all');
        update_option(self::LAST, array('status'=>'ok','time'=>current_time('mysql'),'count'=>count($rendered),'products'=>$rendered), false);
        if ($this->audit) $this->audit->write('info','Region 9 graphics generated.',array('count'=>count($rendered),'products'=>$rendered));
        return $rendered;
    }

    private function svg($p) {
        $title = $this->x($p['title'] ?? 'Region 9 Weather');
        $risk = $this->x($p['risk']['label'] ?? 'None');
        $level = (int)($p['risk']['level'] ?? 0);
        $summary = $this->wrap($p['summary'] ?? '', 56, 5);
        $counties = !empty($p['affected_counties']) ? implode(' • ', $p['affected_counties']) : 'All Region 9 counties / no focused county impacts';
        $counties = $this->x($counties);
        $confidence = max(0,min(100,(int)($p['confidence'] ?? 0)));
        $timing = $p['timing']['label'] ?? ($p['timing']['local'] ?? 'Timing not specified');
        $timing = $this->x(is_array($timing) ? wp_json_encode($timing) : $timing);
        $updated = $this->x($p['updated_at'] ?? current_time('mysql'));
        $category = strtoupper(str_replace('-', ' ', $p['category'] ?? 'REGION 9 FORECAST'));
        $category = $this->x($category);
        $palette = array(
            0=>array('#667784','#cbd5db'),1=>array('#237642','#dcefe2'),2=>array('#b58a00','#fff0a8'),3=>array('#c45a00','#ffd6ac'),4=>array('#b5161b','#ffd0d2')
        );
        $accent=$palette[$level][0] ?? $palette[0][0]; $soft=$palette[$level][1] ?? $palette[0][1];
        $variant = abs(crc32((string)($p['product_id'] ?? 'r9'))) % 4;
        $lines=''; $y=442; foreach($summary as $line){$lines.='<text x="86" y="'.$y.'" class="body">'.$this->x($line).'</text>'; $y+=42;}
        $decor = $variant===0 ? '<circle cx="1030" cy="220" r="165" fill="'.$soft.'" opacity=".34"/><path d="M930 250 C970 180 1080 180 1120 250" fill="none" stroke="'.$accent.'" stroke-width="28" stroke-linecap="round"/>' : ($variant===1 ? '<path d="M870 145 L1160 145 L1110 350 L920 350 Z" fill="'.$soft.'" opacity=".42"/><path d="M900 310 L1130 185" stroke="'.$accent.'" stroke-width="25"/>' : ($variant===2 ? '<g opacity=".35" fill="none" stroke="'.$accent.'" stroke-width="10"><circle cx="1030" cy="250" r="140"/><circle cx="1030" cy="250" r="100"/><circle cx="1030" cy="250" r="60"/></g>' : '<g opacity=".4"><rect x="890" y="150" width="250" height="42" rx="10" fill="'.$accent.'"/><rect x="920" y="220" width="220" height="42" rx="10" fill="'.$accent.'"/><rect x="950" y="290" width="190" height="42" rx="10" fill="'.$accent.'"/></g>'));
        return '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" width="1200" height="1200" viewBox="0 0 1200 1200"><style>.sans{font-family:Arial,Helvetica,sans-serif}.brand{font:900 56px Arial,sans-serif;fill:#fff}.kicker{font:700 24px Arial,sans-serif;letter-spacing:3px;fill:#f7c843}.title{font:900 64px Arial,sans-serif;fill:#071e31}.risk{font:900 34px Arial,sans-serif;fill:#fff}.label{font:700 22px Arial,sans-serif;fill:#46606f}.value{font:800 26px Arial,sans-serif;fill:#071e31}.body{font:600 30px Arial,sans-serif;fill:#162b39}.foot{font:600 19px Arial,sans-serif;fill:#d8e7ef}</style><rect width="1200" height="1200" fill="#f7fafc"/><rect width="1200" height="140" fill="#061827"/><text x="62" y="76" class="brand">REGION 9</text><text x="64" y="112" class="kicker">WEATHER • EAST-CENTRAL ILLINOIS</text><rect x="0" y="140" width="1200" height="12" fill="#f7c843"/><text x="70" y="215" class="label">'.$category.'</text><text x="70" y="300" class="title">'.$title.'</text>'.$decor.'<rect x="70" y="338" width="310" height="64" rx="32" fill="'.$accent.'"/><text x="100" y="382" class="risk">'.$risk.' RISK</text><rect x="70" y="420" width="1060" height="310" rx="22" fill="#fff" stroke="#d5e1e7" stroke-width="2"/>'.$lines.'<rect x="70" y="765" width="1060" height="180" rx="22" fill="'.$soft.'"/><text x="96" y="810" class="label">TIMING</text><text x="96" y="850" class="value">'.$timing.'</text><text x="96" y="895" class="label">AFFECTED AREA</text><text x="96" y="932" class="value">'.$counties.'</text><text x="790" y="810" class="label">CONFIDENCE</text><text x="790" y="862" class="title">'.$confidence.'%</text><rect x="0" y="1000" width="1200" height="200" fill="#061827"/><text x="62" y="1060" class="kicker">STAY WEATHER AWARE. STAY PREPARED.</text><text x="62" y="1110" class="foot">Generated from the approved Region 9 forecast state • Updated '.$updated.'</text><text x="62" y="1150" class="foot">Region 9 risk is a local communication scale, not an official NWS/SPC category.</text><text x="940" y="1150" class="kicker">@Region9Weather</text></svg>';
    }

    private function png_from_svg($svg, $path) {
        if (!class_exists('Imagick')) return false;
        try {
            $im = new Imagick(); $im->setBackgroundColor(new ImagickPixel('white')); $im->readImageBlob($svg); $im->setImageFormat('png');
            $im->setImageCompressionQuality(92); $ok=$im->writeImage($path); $im->clear(); $im->destroy(); return (bool)$ok;
        } catch (Exception $e) { return false; }
    }

    private function alt_text($p) {
        $risk=$p['risk']['label']??'None'; $counties=!empty($p['affected_counties'])?implode(', ',$p['affected_counties']):'Region 9';
        return sanitize_text_field(($p['title']??'Region 9 Weather').' graphic. '.$risk.' risk. '.($p['summary']??'').' Affected area: '.$counties.'.');
    }
    private function wrap($text,$width,$max){$text=trim(wp_strip_all_tags((string)$text));$lines=$text===''?array('Forecast information is being updated.'):explode("\n",wordwrap($text,$width,"\n",true));return array_slice($lines,0,$max);}
    private function x($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1,'UTF-8');}
    private function fail($message){$state=array('status'=>'failure','time'=>current_time('mysql'),'message'=>$message);update_option(self::LAST,$state,false);if($this->audit)$this->audit->write('warning','Region 9 graphic generation failed.',$state);return array();}
}
