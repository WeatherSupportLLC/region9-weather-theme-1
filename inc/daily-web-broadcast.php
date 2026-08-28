<?php
if (!defined('ABSPATH')) exit;

function r9_daily_web_product($code){
    return function_exists('r9ls_theme_product') ? r9ls_theme_product($code) : null;
}
function r9_daily_web_text($p,$key,$fallback=''){
    return is_array($p) && isset($p[$key]) && $p[$key] !== '' ? (string)$p[$key] : $fallback;
}
function r9_daily_web_range($text,$kind,$fallback){
    $patterns = $kind==='high'
      ? array('/highs?\s+(?:generally\s+)?(\d{2})°?\s*(?:to|–|-)\s*(\d{2})°?/i','/highs?\s+(?:around\s+)?(\d{2})/i')
      : array('/lows?\s+(?:generally\s+)?(\d{2})°?\s*(?:to|–|-)\s*(\d{2})°?/i','/lows?\s+(?:near\s+|around\s+)?(\d{2})/i');
    foreach($patterns as $re){ if(preg_match($re,$text,$m)) return isset($m[2]) && $m[2]!=='' ? $m[1].'–'.$m[2].'°' : $m[1].'°'; }
    return $fallback;
}
function r9_daily_web_risk($p){$v=strtolower(r9_daily_web_text($p,'risk','low'));return in_array($v,array('none','low','limited','elevated','significant'),true)?$v:'low';}
function r9_daily_web_updated($p){$raw=r9_daily_web_text($p,'updated_at',r9_daily_web_text($p,'valid_time',''));return $raw&&strtotime($raw)?wp_date('g:i A T · M j, Y',strtotime($raw)):'Current cycle';}
function r9_daily_web_title($p,$fallback){return preg_replace('/\s+—.+$/u','',r9_daily_web_text($p,'title',$fallback));}
function r9_daily_web_discussion($p){return wp_strip_all_tags(r9_daily_web_text($p,'discussion','Forecast discussion temporarily unavailable.'));}

function r9_daily_web_renderer(){
    $today=r9_daily_web_product('todays-forecast');
    $seven=r9_daily_web_product('seven-day-forecast');
    $brief=r9_daily_web_product('morning-weather-brief');
    $current=r9_daily_web_product('current-conditions');
    $products=array($today,$seven,$brief,$current);
    $titles=array("Today’s Forecast",'7-Day Forecast','Morning Weather Brief','Current Conditions');
    $todayText=r9_daily_web_discussion($today);
    $high=r9_daily_web_range($todayText,'high','82–83°');
    $low=r9_daily_web_range($todayText,'low','59–61°');
    ob_start(); ?>
<section class="r9-web-daily" id="r9-web-daily">
  <div class="r9-web-daily-heading"><div><span>REGION 9 WEATHER</span><h1>Daily Forecast</h1></div><div class="r9-web-daily-date"><?php echo esc_html(wp_date('l, F j, Y'));?></div></div>
  <div class="r9-web-stage">
    <article class="r9-web-slide is-active" data-r9-web-slide="0">
      <div class="r9-web-slide-head"><div class="r9-web-logo">REGION <b>9</b><small>WEATHER</small></div><div><h2>TODAY’S FORECAST</h2><p>EAST-CENTRAL ILLINOIS</p></div><span class="r9-web-risk <?php echo esc_attr(r9_daily_web_risk($today));?>"><?php echo esc_html(strtoupper(r9_daily_web_risk($today)));?></span></div>
      <div class="r9-web-today-grid">
        <div class="r9-web-weather-hero"><div class="r9-web-sun"></div><div class="r9-web-cloud c1"></div><div class="r9-web-cloud c2"></div><div class="r9-web-land"></div><div class="r9-web-temp"><small>HIGH</small><strong><?php echo esc_html($high);?></strong><span>Sunny after early patchy fog</span><small class="low-label">LOW</small><strong class="low-temp"><?php echo esc_html($low);?></strong><span>Mostly clear tonight</span></div></div>
        <div class="r9-web-metrics">
          <div><span>CHANCE OF RAIN</span><strong>0%</strong></div>
          <div><span>WINDS</span><strong>Light</strong><small>Variable to light regional flow</small></div>
          <div><span>SPECIAL WEATHER</span><strong>Patchy AM Fog</strong><small>Improves quickly after sunrise</small></div>
          <div><span>RISK LEVEL</span><strong><?php echo esc_html(ucfirst(r9_daily_web_risk($today)));?></strong></div>
          <div><span>CONFIDENCE</span><strong><?php echo esc_html(ucfirst(r9_daily_web_text($today,'confidence','high')));?></strong></div>
          <div><span>UPDATED</span><strong><?php echo esc_html(r9_daily_web_updated($today));?></strong></div>
        </div>
      </div>
      <div class="r9-web-bottom-band"><strong>SPECIAL WEATHER CONSIDERATIONS</strong><span>Localized patchy fog near daybreak, then quiet and dry.</span></div>
    </article>

    <article class="r9-web-slide" data-r9-web-slide="1">
      <div class="r9-web-slide-head"><div class="r9-web-logo">REGION <b>9</b><small>WEATHER</small></div><div><h2>7-DAY FORECAST</h2><p>EAST-CENTRAL ILLINOIS</p></div><span class="r9-web-risk <?php echo esc_attr(r9_daily_web_risk($seven));?>"><?php echo esc_html(strtoupper(r9_daily_web_risk($seven)));?></span></div>
      <div class="r9-web-seven-grid">
        <div class="r9-web-day"><b>FRI</b><span class="sun-dot"></span><strong>82–83°</strong><small>Sunny</small><em>Dry</em></div>
        <div class="r9-web-day"><b>SAT</b><span class="sun-dot"></span><strong>Low–Mid 80s</strong><small>Mostly Dry</small><em>Warming</em></div>
        <div class="r9-web-day"><b>SUN</b><span class="sun-dot"></span><strong>Upper 80s–90°</strong><small>Warm</small><em>Humid</em></div>
        <div class="r9-web-day hot"><b>MON</b><span class="sun-dot"></span><strong>Low–Mid 90s</strong><small>Hot</small><em>Heat Builds</em></div>
        <div class="r9-web-day hot"><b>TUE</b><span class="storm-dot">⚡</span><strong>Low–Mid 90s</strong><small>Hot / Humid</small><em>Low storm chance north</em></div>
        <div class="r9-web-day hot"><b>WED</b><span class="storm-dot">⚡</span><strong>Low–Mid 90s</strong><small>Hot / Humid</small><em>Low storm chance north</em></div>
        <div class="r9-web-day"><b>THU</b><span class="trend-dot">↘</span><strong>Trend Update</strong><small>Next cycle</small><em>Monitoring</em></div>
      </div>
      <div class="r9-web-seven-summary"><strong>WEEKLY TREND</strong><span><?php echo esc_html(r9_daily_web_discussion($seven));?></span></div>
    </article>

    <article class="r9-web-slide" data-r9-web-slide="2">
      <div class="r9-web-slide-head"><div class="r9-web-logo">REGION <b>9</b><small>WEATHER</small></div><div><h2>MORNING WEATHER BRIEF</h2><p>EAST-CENTRAL ILLINOIS</p></div><span class="r9-web-risk <?php echo esc_attr(r9_daily_web_risk($brief));?>"><?php echo esc_html(strtoupper(r9_daily_web_risk($brief)));?></span></div>
      <div class="r9-web-brief-grid"><div class="r9-web-brief-main"><span>TODAY AT A GLANCE</span><div class="brief-temp"><?php echo esc_html($high);?></div><h3>Sunny &amp; Dry</h3><p>Patchy fog around daybreak, then rapidly improving visibility and favorable travel/outdoor conditions.</p></div><div class="r9-web-brief-card"><b>MAIN CONCERN</b><strong>Early Patchy Fog</strong><p>Localized visibility reductions in sheltered and low-lying areas.</p></div><div class="r9-web-brief-card"><b>TRAVEL &amp; OUTDOOR</b><strong>Favorable</strong><p>Good conditions after the early fog dissipates.</p></div><div class="r9-web-brief-card"><b>TONIGHT</b><strong><?php echo esc_html($low);?></strong><p>Mostly clear and comfortable.</p></div><div class="r9-web-brief-card wide"><b>FULL MORNING BRIEF</b><p><?php echo esc_html(r9_daily_web_discussion($brief));?></p></div></div>
    </article>

    <article class="r9-web-slide" data-r9-web-slide="3">
      <div class="r9-web-slide-head"><div class="r9-web-logo">REGION <b>9</b><small>WEATHER</small></div><div><h2>CURRENT CONDITIONS</h2><p>ACROSS REGION 9</p></div><span class="r9-web-risk <?php echo esc_attr(r9_daily_web_risk($current));?>"><?php echo esc_html(strtoupper(r9_daily_web_risk($current)));?></span></div>
      <div class="r9-web-current-grid" id="r9-web-current-grid"><div class="r9-web-current-loading">Loading current Region 9 observations…</div></div>
      <div class="r9-web-seven-summary"><strong>REGIONAL SUMMARY</strong><span><?php echo esc_html(r9_daily_web_discussion($current));?></span></div>
    </article>

    <button class="r9-web-arrow prev" type="button" aria-label="Previous forecast">‹</button><button class="r9-web-arrow next" type="button" aria-label="Next forecast">›</button>
  </div>
  <div class="r9-web-tabs"><?php foreach($titles as $i=>$t):?><button type="button" data-r9-web-go="<?php echo esc_attr($i);?>" class="<?php echo $i===0?'is-active':'';?>"><?php echo esc_html($t);?></button><?php endforeach;?></div>
  <section class="r9-web-discussion"><h2 id="r9-web-discussion-title">Today’s Forecast Discussion</h2><?php foreach($products as $i=>$p):?><div class="r9-web-copy <?php echo $i===0?'is-active':'';?>" data-r9-web-copy="<?php echo esc_attr($i);?>"><p><?php echo esc_html(r9_daily_web_discussion($p));?></p><small>FORECAST UPDATED: <?php echo esc_html(r9_daily_web_updated($p));?> &nbsp; | &nbsp; CONFIDENCE: <?php echo esc_html(strtoupper(r9_daily_web_text($p,'confidence','high')));?></small></div><?php endforeach;?></section>
</section>
<style>
.r9-web-daily{display:grid;gap:14px}.r9-web-daily-heading{display:flex;align-items:end;justify-content:space-between;padding:4px 4px 0}.r9-web-daily-heading span{font-weight:900;color:#c69100;letter-spacing:.12em;font-size:.78rem}.r9-web-daily-heading h1{font-size:2.3rem;line-height:1;margin:5px 0 0;color:#092d55}.r9-web-daily-date{font-weight:800;color:#24394f}.r9-web-stage{position:relative;overflow:hidden;border-radius:10px;border:1px solid #b9c9d7;background:#03142a;box-shadow:0 12px 30px rgba(6,33,59,.15);aspect-ratio:16/9;min-height:510px}.r9-web-slide{display:none;height:100%;min-height:510px;background:#062f5b;color:#fff}.r9-web-slide.is-active{display:flex;flex-direction:column}.r9-web-slide-head{display:grid;grid-template-columns:230px 1fr auto;gap:18px;align-items:center;padding:18px 26px;background:linear-gradient(90deg,#071b38,#0a4179);border-bottom:3px solid #e3b126}.r9-web-logo{font-weight:900;font-size:1.55rem;letter-spacing:.03em}.r9-web-logo b{font-size:2.2rem;color:#f3b400}.r9-web-logo small{display:block;font-size:.64rem;letter-spacing:.28em;margin-top:-6px}.r9-web-slide-head h2{margin:0;font-size:2.25rem;line-height:1}.r9-web-slide-head p{margin:4px 0 0;color:#e2b62d;font-weight:900;letter-spacing:.08em}.r9-web-risk{padding:9px 16px;border-radius:7px;background:#238c3c;font-weight:900}.r9-web-risk.none{background:#718096}.r9-web-risk.limited{background:#e3b126;color:#09213e}.r9-web-risk.elevated{background:#e77d21}.r9-web-risk.significant{background:#c92727}.r9-web-today-grid{display:grid;grid-template-columns:1.7fr 1fr;flex:1;min-height:0}.r9-web-weather-hero{position:relative;overflow:hidden;background:linear-gradient(#1587d0 0%,#73bce9 55%,#b6e0f4 72%,#335b25 73%,#183717 100%)}.r9-web-sun{position:absolute;width:160px;height:160px;border-radius:50%;background:radial-gradient(circle,#fff29a 0,#ffc41f 52%,#f19a00 100%);left:50%;top:25%;box-shadow:0 0 48px 18px rgba(255,197,28,.65)}.r9-web-cloud{position:absolute;width:180px;height:45px;background:rgba(255,255,255,.78);border-radius:45px;filter:blur(.5px)}.r9-web-cloud:before,.r9-web-cloud:after{content:"";position:absolute;border-radius:50%;background:inherit}.r9-web-cloud:before{width:75px;height:75px;left:35px;top:-35px}.r9-web-cloud:after{width:95px;height:95px;right:22px;top:-50px}.r9-web-cloud.c1{left:16%;top:25%;opacity:.55}.r9-web-cloud.c2{right:8%;top:43%;opacity:.72}.r9-web-land{position:absolute;left:0;right:0;bottom:0;height:26%;background:linear-gradient(155deg,transparent 0 33%,rgba(18,57,22,.9) 34% 54%,rgba(11,39,17,.98) 55%)}.r9-web-temp{position:absolute;left:38px;top:28px;display:grid;z-index:2;text-shadow:0 2px 6px rgba(0,0,0,.35)}.r9-web-temp small{font-size:1rem;font-weight:900}.r9-web-temp strong{font-size:4.8rem;line-height:.95}.r9-web-temp span{font-size:1.35rem;font-weight:800;color:#ffd11f}.r9-web-temp .low-label{margin-top:24px}.r9-web-temp .low-temp{font-size:3.2rem}.r9-web-metrics{background:#082d58;display:grid;grid-template-rows:repeat(6,1fr)}.r9-web-metrics>div{padding:13px 22px;border-bottom:1px solid rgba(255,255,255,.16);display:grid;grid-template-columns:1fr auto;align-items:center;gap:10px}.r9-web-metrics span{font-weight:800;font-size:.78rem}.r9-web-metrics strong{font-size:1.2rem}.r9-web-metrics small{grid-column:1/-1;color:#d3e2ef}.r9-web-bottom-band{display:grid;grid-template-columns:1fr 2fr;gap:20px;padding:12px 25px;background:#061f3e;border-top:2px solid #d8a91e}.r9-web-bottom-band strong{color:#f3b400}.r9-web-seven-grid{display:grid;grid-template-columns:repeat(7,1fr);flex:1;padding:18px;background:linear-gradient(#0d4d83,#082d58);gap:8px}.r9-web-day{background:linear-gradient(#0f5d99,#092d58);border:1px solid rgba(255,255,255,.22);border-radius:7px;padding:14px 8px;display:flex;flex-direction:column;align-items:center;text-align:center;gap:10px}.r9-web-day b{font-size:1.15rem;color:#ffd331}.r9-web-day strong{font-size:1.15rem}.r9-web-day small{font-weight:800}.r9-web-day em{font-style:normal;color:#d8e6f1;font-size:.76rem}.r9-web-day.hot{background:linear-gradient(#8d381d,#3d2140)}.sun-dot,.storm-dot,.trend-dot{width:55px;height:55px;border-radius:50%;display:grid;place-items:center;background:#ffc31e;color:#fff;font-size:1.7rem;box-shadow:0 0 18px rgba(255,190,20,.35)}.storm-dot{background:#374b73}.trend-dot{background:#2a688f}.r9-web-seven-summary{padding:13px 24px;background:#061f3e;border-top:2px solid #d8a91e;display:grid;grid-template-columns:170px 1fr;gap:20px;align-items:start}.r9-web-seven-summary strong{color:#f4bd25}.r9-web-seven-summary span{font-size:.88rem;line-height:1.45}.r9-web-brief-grid{display:grid;grid-template-columns:1.25fr 1fr 1fr;grid-template-rows:1fr 1fr;gap:12px;padding:18px;flex:1;background:linear-gradient(140deg,#0d5b93,#072846)}.r9-web-brief-main{grid-row:1/3;border-radius:8px;background:linear-gradient(#1379bc,#0b4776);padding:24px;display:flex;flex-direction:column;justify-content:center}.r9-web-brief-main>span,.r9-web-brief-card b{color:#ffd22d;font-weight:900;letter-spacing:.06em;font-size:.8rem}.brief-temp{font-size:5rem;font-weight:900;line-height:1}.r9-web-brief-main h3{font-size:1.8rem;margin:5px 0}.r9-web-brief-card{background:#071f3e;border:1px solid rgba(255,255,255,.16);border-radius:8px;padding:18px}.r9-web-brief-card strong{display:block;font-size:1.35rem;margin:10px 0}.r9-web-brief-card.wide{grid-column:2/4}.r9-web-brief-card p{margin:0;line-height:1.45}.r9-web-current-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding:16px;flex:1;background:linear-gradient(#0b4776,#071f3e);overflow:auto}.r9-web-current-card{background:#0a335d;border:1px solid rgba(255,255,255,.2);border-radius:8px;padding:14px;display:grid;gap:5px}.r9-web-current-card b{color:#ffd22d}.r9-web-current-card strong{font-size:2rem}.r9-web-current-card small{color:#d5e3ef}.r9-web-current-loading{grid-column:1/-1;display:grid;place-items:center;font-weight:800}.r9-web-arrow{position:absolute;top:50%;transform:translateY(-50%);z-index:5;width:46px;height:58px;border:1px solid rgba(255,255,255,.5);background:rgba(3,18,36,.78);color:#fff;font-size:32px;cursor:pointer}.r9-web-arrow.prev{left:10px}.r9-web-arrow.next{right:10px}.r9-web-tabs{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.r9-web-tabs button{border:1px solid #c9d5e0;background:#fff;color:#0a2f56;padding:14px 8px;font-weight:900;border-radius:7px;cursor:pointer}.r9-web-tabs button.is-active{background:#0d4f8d;color:#fff;box-shadow:inset 0 -4px 0 #e2b026}.r9-web-discussion{border:1px solid #cad5df;border-radius:8px;background:#fff;padding:16px 18px}.r9-web-discussion h2{color:#0d4f8d;font-size:1.1rem;margin:0 0 8px}.r9-web-copy{display:none}.r9-web-copy.is-active{display:block}.r9-web-copy p{margin:0;font-size:.98rem;line-height:1.55}.r9-web-copy small{display:block;margin-top:12px;color:#40576c;font-weight:700}
@media(max-width:980px){.r9-web-stage{aspect-ratio:auto;min-height:560px}.r9-web-slide{min-height:560px}.r9-web-slide-head{grid-template-columns:150px 1fr auto}.r9-web-slide-head h2{font-size:1.7rem}.r9-web-today-grid{grid-template-columns:1fr}.r9-web-metrics{grid-template-columns:repeat(2,1fr);grid-template-rows:auto}.r9-web-seven-grid{grid-template-columns:repeat(4,1fr);overflow:auto}.r9-web-brief-grid{grid-template-columns:1fr 1fr}.r9-web-brief-main{grid-row:auto}.r9-web-brief-card.wide{grid-column:1/3}.r9-web-current-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.r9-web-daily-heading{align-items:start}.r9-web-daily-date{display:none}.r9-web-stage,.r9-web-slide{min-height:640px}.r9-web-slide-head{grid-template-columns:1fr auto}.r9-web-logo{display:none}.r9-web-slide-head h2{font-size:1.45rem}.r9-web-today-grid{display:block}.r9-web-weather-hero{height:330px}.r9-web-metrics{grid-template-columns:1fr 1fr}.r9-web-tabs{grid-template-columns:1fr 1fr}.r9-web-seven-grid{grid-template-columns:repeat(2,1fr)}.r9-web-brief-grid{grid-template-columns:1fr;overflow:auto}.r9-web-brief-card.wide{grid-column:auto}.r9-web-current-grid{grid-template-columns:1fr}.r9-web-seven-summary{grid-template-columns:1fr}.r9-web-arrow{width:38px;height:50px}}
</style>
<script>
(function(){const root=document.getElementById('r9-web-daily');if(!root)return;const slides=[...root.querySelectorAll('[data-r9-web-slide]')],tabs=[...root.querySelectorAll('[data-r9-web-go]')],copies=[...root.querySelectorAll('[data-r9-web-copy]')],title=root.querySelector('#r9-web-discussion-title');let i=0,t;const labels=['Today’s Forecast','7-Day Forecast','Morning Weather Brief','Current Conditions'];function show(n){i=(n+slides.length)%slides.length;slides.forEach((x,k)=>x.classList.toggle('is-active',k===i));tabs.forEach((x,k)=>x.classList.toggle('is-active',k===i));copies.forEach((x,k)=>x.classList.toggle('is-active',k===i));if(title)title.textContent=labels[i]+' Discussion';}function restart(){clearInterval(t);t=setInterval(()=>show(i+1),7000)}root.querySelector('.prev').onclick=()=>{show(i-1);restart()};root.querySelector('.next').onclick=()=>{show(i+1);restart()};tabs.forEach(b=>b.onclick=()=>{show(parseInt(b.dataset.r9WebGo,10));restart()});restart();
if(window.R9Studio&&R9Studio.rest){fetch(R9Studio.rest,{cache:'no-store'}).then(r=>r.json()).then(items=>{const g=root.querySelector('#r9-web-current-grid');if(!g)return;g.innerHTML=(items||[]).map(x=>'<div class="r9-web-current-card"><b>'+String(x.city||'')+'</b><strong>'+String(x.temp||'--')+'°</strong><span>'+String(x.text||'')+'</span><small>Humidity '+String(x.humidity||'--')+'% · Dew point '+String(x.dewpoint||'--')+'°</small><small>Wind '+String(x.wind||'--')+' mph'+(x.gust&&x.gust!=='--'?' · Gust '+String(x.gust)+' mph':'')+'</small></div>').join('')||'<div class="r9-web-current-loading">Current observations temporarily unavailable.</div>'}).catch(()=>{});}
})();
</script>
<?php return ob_get_clean();
}
