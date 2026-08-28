<?php
if (!defined('ABSPATH')) exit;

function r9ls_theme_rc1_active(){
    if (defined('R9LS_VERSION') && version_compare((string) R9LS_VERSION, '17.0.0-rc.1', '>=')) { return true; }
    if (class_exists('R9LS_Plugin') && defined('R9LS_VERSION')) { return true; }
    return function_exists('r9ls_get_public_products') && function_exists('r9ls_public_settings');
}
function r9ls_theme_admin_url(){ return r9ls_theme_rc1_active() ? admin_url('admin.php?page=r9ls') : admin_url('admin.php?page=r9-studio'); }
function r9ls_theme_product_map(){return array(
 'daily'=>array('todays-forecast','seven-day-forecast','morning-weather-brief','current-conditions'),
 'hazards'=>array('severe-weather-risk','threat-breakdown','storm-timing'),
 'agriculture'=>array('agriculture','fieldwork','spraying','harvest','livestock'),
 'travel-outdoor'=>array('travel','outdoor','schools','construction'),
 'special'=>array('forecast-confidence','decision-support-brief','watching'),
 'temperature-outlook'=>array('todays-forecast','forecast-confidence'),
 'precipitation-outlook'=>array('todays-forecast','decision-support-brief'),
 'severe-weather'=>array('severe-weather-risk','threat-breakdown','storm-timing'),
);}
function r9ls_theme_enabled(){return r9ls_theme_rc1_active()||function_exists('r9ls_get_published_product')||function_exists('r9ls_get_public_products');}
function r9ls_theme_products(){static $cache=null;if($cache!==null)return $cache;if(!r9ls_theme_enabled())return $cache=array();if(function_exists('r9ls_get_public_products')){$p=r9ls_get_public_products();}else{$all=function_exists('r9ls_get_published_products')?r9ls_get_published_products():array();$p=array();foreach((array)$all as $id=>$x){if(($x['approval_state']??'')==='approved'&&($x['publication_state']??'')==='published')$p[$id]=$x;}}return $cache=is_array($p)?$p:array();}
function r9ls_theme_product($id){$all=r9ls_theme_products();$id=sanitize_key($id);return $all[$id]??null;}
function r9ls_theme_is_stale($p){$threshold=12*HOUR_IN_SECONDS;if(function_exists('r9ls_public_settings')){$s=r9ls_public_settings();$threshold=max(HOUR_IN_SECONDS,(int)($s['stale_data_threshold_minutes']??720)*MINUTE_IN_SECONDS);} $ts=strtotime($p['updated_at']??'');return !$ts||(time()-$ts)>$threshold;}
function r9ls_theme_status($p){if(!$p)return array('state'=>'unavailable','label'=>r9ls_theme_fallback_language());return r9ls_theme_is_stale($p)?array('state'=>'stale','label'=>'Stale approved publication'):array('state'=>'fresh','label'=>'Approved publication');}
function r9ls_theme_fallback_language(){if(function_exists('r9ls_public_settings')){$s=r9ls_public_settings();if(!empty($s['fallback_language']))return $s['fallback_language'];}return 'Region 9 Live Studio publication is temporarily unavailable.';}
function r9ls_theme_risk_badge($risk){$label=is_array($risk)?($risk['label']??'None'):(string)$risk;$class='r9-risk-'.sanitize_html_class(strtolower($label));return '<span class="r9ls-risk-badge '.esc_attr($class).'">'.esc_html($label).' risk</span>';}
function r9ls_theme_confidence($p){$c=max(0,min(100,(int)($p['confidence']??0)));return '<div class="r9ls-confidence" aria-label="Forecast confidence '.$c.' percent"><span>Confidence</span><meter min="0" max="100" value="'.esc_attr($c).'">'.esc_html($c).'%</meter><strong>'.esc_html($c).'%</strong></div>';}
function r9ls_theme_counties($p){$c=array_filter((array)($p['affected_counties']??array()));if(!$c)return '<p class="r9ls-counties">Affected counties: Region 9 area as applicable.</p>';return '<p class="r9ls-counties">Affected counties: '.esc_html(implode(', ',$c)).'.</p>';}
function r9ls_theme_time($p){$t=$p['timing']['label']??($p['timing']['local']??'Timing not specified');return '<p class="r9ls-timing"><strong>Timing:</strong> '.esc_html($t).'</p>';}
function r9ls_theme_updated($p){$s=r9ls_theme_status($p);$u=$p?($p['updated_at']??''):'Unavailable';$txt=$p&&strtotime($u)?wp_date('M j, Y g:i A T',strtotime($u)):$u;return '<p class="r9ls-updated r9ls-source-'.esc_attr($s['state']).'"><strong>'.esc_html($s['label']).'</strong> · Last updated: '.esc_html($txt).'</p>';}
function r9ls_theme_card($id){$p=r9ls_theme_product($id);if(!$p)return '<article class="r9-panel r9ls-product-card is-unavailable"><h3>'.esc_html(ucwords(str_replace('-',' ',$id))).'</h3><p>'.esc_html(r9ls_theme_fallback_language()).'</p></article>';$graphic=!empty($p['graphic_url'])?'<img class="r9-image" src="'.esc_url($p['graphic_url']).'" alt="'.esc_attr($p['title']).' graphic">':r9_media_placeholder(($p['title']??'Forecast').' Graphic');return '<article class="r9-panel r9ls-product-card" data-product="'.esc_attr($id).'"><div class="r9-panel-head"><div><span class="r9-eyebrow">REGION 9 APPROVED PRODUCT</span><h3>'.esc_html($p['title']??$id).'</h3></div>'.r9ls_theme_risk_badge($p['risk']??array()).'</div>'.$graphic.'<p class="r9ls-summary">'.esc_html($p['summary']??'').'</p>'.r9ls_theme_confidence($p).r9ls_theme_time($p).r9ls_theme_counties($p).'<details class="r9ls-discussion"><summary>Full discussion</summary><div>'.wp_kses_post(wpautop($p['discussion']??'')).'</div></details>'.r9ls_theme_updated($p).'</article>';}
function r9ls_theme_product_grid($slug){$map=r9ls_theme_product_map();$ids=$map[$slug]??array();if(!$ids)return '';return '<section class="r9ls-product-grid">'.implode('',array_map('r9ls_theme_card',$ids)).'</section>';}

function r9ls_theme_daily_live_panel($s){
    $title=preg_replace('/\s+—.+$/u','',(string)$s['title']);
    $label=$s['id']==='current-conditions'?'LIVE REGION 9 OBSERVATION SUMMARY':'REGION 9 VERIFIED FORECAST';
    $updated=($s['updated']&&strtotime($s['updated']))?wp_date('M j, Y g:i A T',strtotime($s['updated'])):'Current cycle';
    $risk=$s['risk']?ucfirst((string)$s['risk']).' risk':'Risk monitored';
    $confidence=$s['confidence']?ucfirst((string)$s['confidence']).' confidence':'Confidence monitored';
    return '<div class="r9-daily-live-panel"><div class="r9-live-kicker">'.esc_html($label).'</div><div class="r9-live-head"><div><span class="r9-live-brand">REGION <b>9</b> WEATHER</span><h2>'.esc_html($title).'</h2><p>East-Central Illinois</p></div><span class="r9-live-risk">'.esc_html($risk).'</span></div><div class="r9-live-body"><div class="r9-live-summary"><span>VERIFIED CURRENT PRODUCT</span><p>'.esc_html($s['excerpt']?:$s['discussion']).'</p></div><div class="r9-live-facts"><div><small>STATUS</small><strong>Current</strong></div><div><small>CONFIDENCE</small><strong>'.esc_html($confidence).'</strong></div><div><small>UPDATED</small><strong>'.esc_html($updated).'</strong></div></div></div><div class="r9-live-footer">Kankakee • Iroquois • Ford • Livingston • DeWitt • Piatt • Champaign • Vermilion • McLean</div></div>';
}

function r9ls_theme_daily_carousel(){
    $ids=array('todays-forecast','seven-day-forecast','morning-weather-brief','current-conditions');
    $slides=array();
    foreach($ids as $id){
        $p=r9ls_theme_product($id);
        if(!$p) continue;
        $raw_url=(string)($p['graphic_url']??'');
        $legacy_auto=$raw_url && strpos($raw_url,'/region9-auto/')!==false;
        $url=$legacy_auto?'':$raw_url;
        $stamp=(string)($p['updated_at']??'');
        if($url && $stamp){$url=add_query_arg('v',rawurlencode((string)strtotime($stamp)),$url);}
        $slides[]=array(
            'id'=>$id,
            'title'=>(string)($p['title']??ucwords(str_replace('-',' ',$id))),
            'url'=>$url,
            'legacy_auto'=>$legacy_auto,
            'excerpt'=>(string)($p['excerpt']??$p['summary']??''),
            'discussion'=>(string)($p['discussion']??''),
            'risk'=>(string)($p['risk']??''),
            'confidence'=>(string)($p['confidence']??''),
            'updated'=>$stamp,
        );
    }
    if(!$slides) return '<section class="r9-panel"><p>'.esc_html(r9ls_theme_fallback_language()).'</p></section>';
    ob_start(); ?>
    <section class="r9-daily-broadcast" id="r9-daily-broadcast">
      <div class="r9-daily-stage">
        <?php foreach($slides as $i=>$s): ?>
          <figure class="r9-daily-slide<?php echo $i===0?' is-active':'';?>" data-r9-slide="<?php echo esc_attr($i);?>">
            <?php if($s['url']): ?>
              <button class="r9-daily-expand" type="button" data-r9-full="<?php echo esc_url($s['url']);?>" aria-label="Expand <?php echo esc_attr($s['title']);?>">
                <img src="<?php echo esc_url($s['url']);?>" alt="<?php echo esc_attr($s['title']);?>" decoding="async" fetchpriority="<?php echo $i===0?'high':'auto';?>">
              </button>
            <?php elseif($s['legacy_auto']): ?>
              <?php echo r9ls_theme_daily_live_panel($s); ?>
            <?php else: ?>
              <div class="r9-daily-missing"><strong><?php echo esc_html($s['title']);?></strong><span>Graphic temporarily unavailable.</span></div>
            <?php endif; ?>
          </figure>
        <?php endforeach; ?>
        <button class="r9-daily-arrow r9-prev" type="button" aria-label="Previous forecast">‹</button>
        <button class="r9-daily-arrow r9-next" type="button" aria-label="Next forecast">›</button>
      </div>
      <nav class="r9-daily-tabs" aria-label="Daily forecast graphics">
        <?php foreach($slides as $i=>$s): ?><button type="button" class="<?php echo $i===0?'is-active':'';?>" data-r9-go="<?php echo esc_attr($i);?>"><?php echo esc_html(preg_replace('/\s+—.+$/u','',$s['title']));?></button><?php endforeach; ?>
      </nav>
      <section class="r9-panel r9-daily-discussion">
        <div class="r9-panel-head"><h2>Forecast Discussion</h2></div>
        <?php foreach($slides as $i=>$s): ?>
          <div class="r9-daily-copy<?php echo $i===0?' is-active':'';?>" data-r9-copy="<?php echo esc_attr($i);?>">
            <p><?php echo esc_html(wp_strip_all_tags($s['discussion']));?></p>
            <div class="r9-daily-meta">
              <?php if($s['updated'] && strtotime($s['updated'])): ?><span>Updated <?php echo esc_html(wp_date('M j, g:i A T',strtotime($s['updated'])));?></span><?php endif; ?>
              <?php if($s['risk']): ?><span><?php echo esc_html(ucfirst($s['risk']));?> risk</span><?php endif; ?>
              <?php if($s['confidence']): ?><span><?php echo esc_html(ucfirst($s['confidence']));?> confidence</span><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </section>
      <dialog class="r9-daily-lightbox" id="r9-daily-lightbox"><button type="button" class="r9-daily-close" aria-label="Close expanded graphic">×</button><img alt="Expanded Region 9 forecast graphic"></dialog>
    </section>
    <style>
      .r9-daily-broadcast{display:grid;gap:14px}.r9-daily-stage{position:relative;background:#061d35;border:1px solid #163e66;border-radius:14px;overflow:hidden;box-shadow:0 14px 34px rgba(4,24,44,.18)}
      .r9-daily-slide{display:none;margin:0;min-height:420px;background:#061d35}.r9-daily-slide.is-active{display:flex;align-items:center;justify-content:center}.r9-daily-expand{display:block;width:100%;border:0;padding:0;background:transparent;cursor:zoom-in}.r9-daily-expand img{display:block;width:100%;height:auto;max-height:860px;object-fit:contain;background:#061d35}
      .r9-daily-arrow{position:absolute;top:50%;z-index:3;transform:translateY(-50%);width:46px;height:62px;border:0;border-radius:12px;background:rgba(4,28,51,.88);color:#fff;font-size:36px;line-height:1;cursor:pointer}.r9-prev{left:12px}.r9-next{right:12px}
      .r9-daily-tabs{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.r9-daily-tabs button{padding:13px 10px;border:1px solid #c9d8e6;border-radius:9px;background:#fff;color:#062744;font-weight:800;cursor:pointer}.r9-daily-tabs button.is-active{background:#063d72;color:#fff;border-color:#063d72;box-shadow:inset 0 -3px 0 #f4b400}
      .r9-daily-copy{display:none}.r9-daily-copy.is-active{display:block}.r9-daily-copy p{font-size:1.05rem;line-height:1.7;margin:0}.r9-daily-meta{display:flex;flex-wrap:wrap;gap:9px;margin-top:14px}.r9-daily-meta span{display:inline-flex;padding:6px 10px;border-radius:999px;background:#edf4fa;color:#163b5c;font-size:.85rem;font-weight:700}
      .r9-daily-live-panel{width:100%;min-height:560px;padding:32px 56px 28px;color:#fff;background:radial-gradient(circle at 16% 20%,#1268a1 0,#0b3e69 28%,#061d35 64%);display:flex;flex-direction:column;justify-content:space-between}.r9-live-kicker{color:#f4b400;font-weight:900;letter-spacing:.13em;font-size:.8rem}.r9-live-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;border-bottom:2px solid rgba(244,180,0,.75);padding-bottom:18px}.r9-live-brand{font-size:1.25rem;font-weight:900;letter-spacing:.04em}.r9-live-brand b{color:#f4b400;font-size:1.5em}.r9-live-head h2{margin:10px 0 2px;font-size:clamp(2.1rem,5vw,4.6rem);line-height:.98;color:#fff}.r9-live-head p{margin:0;color:#c8d8e6;font-weight:800;text-transform:uppercase;letter-spacing:.1em}.r9-live-risk{background:#178b3f;border-radius:8px;padding:10px 16px;font-weight:900;white-space:nowrap}.r9-live-body{display:grid;grid-template-columns:1.7fr 1fr;gap:28px;margin:32px 0}.r9-live-summary{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);border-radius:14px;padding:26px}.r9-live-summary span{color:#f4b400;font-weight:900;font-size:.82rem;letter-spacing:.1em}.r9-live-summary p{font-size:clamp(1.25rem,2.3vw,2rem);line-height:1.35;margin:14px 0 0}.r9-live-facts{display:grid;gap:10px}.r9-live-facts div{background:rgba(2,19,36,.72);border:1px solid #1d537d;border-radius:12px;padding:15px 18px}.r9-live-facts small{display:block;color:#83b8dd;font-weight:900;letter-spacing:.1em}.r9-live-facts strong{display:block;margin-top:5px;font-size:1.1rem}.r9-live-footer{padding-top:16px;border-top:1px solid rgba(255,255,255,.2);color:#d7e4ef;font-size:.82rem;font-weight:800;text-transform:uppercase;letter-spacing:.03em}
      .r9-daily-lightbox{width:min(96vw,1500px);max-width:1500px;border:0;border-radius:14px;padding:44px 14px 14px;background:#061d35}.r9-daily-lightbox::backdrop{background:rgba(0,0,0,.84)}.r9-daily-lightbox img{display:block;max-width:100%;max-height:88vh;margin:auto}.r9-daily-close{position:absolute;right:10px;top:7px;border:0;background:transparent;color:#fff;font-size:32px;cursor:pointer}
      @media(max-width:760px){.r9-daily-tabs{grid-template-columns:1fr 1fr}.r9-daily-slide{min-height:240px}.r9-daily-arrow{width:38px;height:50px;font-size:30px}.r9-daily-live-panel{min-height:430px;padding:28px 24px}.r9-live-head{display:block}.r9-live-risk{display:inline-block;margin-top:12px}.r9-live-body{grid-template-columns:1fr;margin:20px 0}.r9-live-summary p{font-size:1.15rem}}
    </style>
    <script>
      (function(){const root=document.getElementById('r9-daily-broadcast');if(!root)return;const slides=[...root.querySelectorAll('[data-r9-slide]')],copies=[...root.querySelectorAll('[data-r9-copy]')],tabs=[...root.querySelectorAll('[data-r9-go]')];let i=0,t;
      const show=n=>{i=(n+slides.length)%slides.length;slides.forEach((el,k)=>el.classList.toggle('is-active',k===i));copies.forEach((el,k)=>el.classList.toggle('is-active',k===i));tabs.forEach((el,k)=>el.classList.toggle('is-active',k===i));};
      const reset=()=>{clearInterval(t);t=setInterval(()=>show(i+1),7000)};root.querySelector('.r9-prev').onclick=()=>{show(i-1);reset()};root.querySelector('.r9-next').onclick=()=>{show(i+1);reset()};tabs.forEach(b=>b.onclick=()=>{show(parseInt(b.dataset.r9Go,10));reset()});reset();
      const dlg=root.querySelector('#r9-daily-lightbox'),dlgImg=dlg.querySelector('img');root.querySelectorAll('[data-r9-full]').forEach(b=>b.onclick=()=>{dlgImg.src=b.dataset.r9Full;dlg.showModal()});dlg.querySelector('.r9-daily-close').onclick=()=>dlg.close();dlg.addEventListener('click',e=>{if(e.target===dlg)dlg.close()});
      })();
    </script>
    <?php return ob_get_clean();
}

function r9ls_theme_home_value($id,$field='summary',$fallback='Unavailable'){$p=r9ls_theme_product($id);return $p?($p[$field]??$fallback):$fallback;}
function r9ls_theme_latest_publication_time(){$latest=0;foreach(r9ls_theme_products() as $p){$latest=max($latest,(int)strtotime($p['updated_at']??''));}return $latest?wp_date('M j, Y g:i A T',$latest):'Unavailable';}
add_shortcode('r9ls_public_product',function($atts){$a=shortcode_atts(array('id'=>'todays-forecast'),$atts);return r9ls_theme_card($a['id']);});
