<footer class="r9-footer-studio"><div class="r9-wrap r9-footer-grid"><div><h3>Region 9 Weather</h3><p>Local weather intelligence for Kankakee, Iroquois, Ford, Livingston, McLean, DeWitt, Piatt, Champaign, and Vermilion counties.</p></div><div><h3>Studio</h3><p><a href="<?php echo esc_url(home_url('/daily/'));?>">Daily Forecast</a><br><a href="<?php echo esc_url(home_url('/hazards/'));?>">Hazards</a><br><a href="#radar">Live Radar</a></p></div><div><h3>Weather Support LLC</h3><p>Decision support, private forecasting, and emergency planning.</p></div></div></footer>
<script id="r9-local-storm-reports-layout-fix">
(function(){
  'use strict';

  function norm(v){return String(v||'').replace(/\s+/g,' ').trim().toLowerCase();}

  function reportPanelFrom(el){
    if(!el) return null;
    return el.closest('[data-r9-local-storm-reports], .r9-local-storm-reports, .local-storm-reports, .widget, section, article, .r9-panel');
  }

  function findReportPanels(){
    var hits=[];
    document.querySelectorAll('h1,h2,h3,h4,h5,strong,.widget-title,.r9-panel-head').forEach(function(el){
      if(norm(el.textContent).indexOf('local storm reports')!==-1){
        var panel=reportPanelFrom(el);
        if(panel && hits.indexOf(panel)===-1) hits.push(panel);
      }
    });
    return hits;
  }

  function findObservationTarget(){
    var selectors=[
      '.r9-current-panel',
      '.r9-current-conditions',
      '.r9-observation-panel',
      '.r9-observation-card',
      '[data-r9-current-conditions]',
      '[data-r9-observation]'
    ];
    for(var i=0;i<selectors.length;i++){
      var direct=document.querySelector(selectors[i]);
      if(direct) return direct;
    }

    var nodes=[].slice.call(document.querySelectorAll('section,article,.r9-panel'));
    for(var j=0;j<nodes.length;j++){
      var txt=norm(nodes[j].textContent);
      if(txt.indexOf('live observation')!==-1 || txt.indexOf('current conditions')!==-1){
        return nodes[j];
      }
    }
    return null;
  }

  function cleanEmptyTopSpace(){
    document.querySelectorAll('.r9-page-grid,.site-content,.inside-article,.entry-content,.r9-wrap').forEach(function(el){
      if(el.children.length===0 && !norm(el.textContent)){
        el.style.marginTop='0';
        el.style.marginBottom='0';
        el.style.paddingTop='0';
        el.style.paddingBottom='0';
        el.style.minHeight='0';
      }
    });
  }

  function applyFix(){
    if(!document.body.classList.contains('home') && !document.body.classList.contains('front-page')) return;

    var panels=findReportPanels();
    if(!panels.length) return;

    /* Keep only one Local Storm Reports module. Prefer the last copy because
       legacy header/top-hook copies appear before the intended content copy. */
    var keep=panels[panels.length-1];
    panels.slice(0,-1).forEach(function(panel){
      if(panel!==keep && panel.parentNode) panel.parentNode.removeChild(panel);
    });

    var target=findObservationTarget();
    if(target && keep!==target && target.parentNode){
      target.insertAdjacentElement('afterend',keep);
    }

    keep.setAttribute('data-r9-local-storm-reports','normalized');
    keep.style.setProperty('display','block','important');
    keep.style.setProperty('width','100%','important');
    keep.style.setProperty('max-width','none','important');
    keep.style.setProperty('min-width','0','important');
    keep.style.setProperty('grid-column','1 / -1','important');
    keep.style.setProperty('float','none','important');
    keep.style.setProperty('clear','both','important');
    keep.style.setProperty('margin','16px 0 18px','important');

    var parent=keep.parentElement;
    if(parent){
      parent.style.setProperty('min-width','0','important');
    }

    /* The homepage header/county-strip transition should be compact. */
    document.querySelectorAll('.r9-county-carousel,.r9-county-strip,.county-forecast-carousel,.county-selector').forEach(function(el){
      el.style.setProperty('margin-bottom','0','important');
    });
    document.querySelectorAll('.r9-studio-bar,.site-header,.r9-header').forEach(function(el){
      el.style.setProperty('margin-top','0','important');
    });

    cleanEmptyTopSpace();
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',applyFix);
  else applyFix();

  /* Re-run once after widgets/shortcodes that render asynchronously. */
  window.setTimeout(applyFix,700);
  window.setTimeout(applyFix,1800);
})();
</script>
<?php wp_footer();?></body></html>
