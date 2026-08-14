<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 110
 * name  : UPLAA drone videos — no-download guard + vertical auto-loop (2026-07-31)
 * scope : front-end
 * state : ACTIVE
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

add_action( 'wp_footer', function () { ?>
<script id="uls-video-guard">
(function(){
  function init(){
    var players = document.querySelectorAll('.uls-immich-video__player');
    if(!players.length) return;
    players.forEach(function(v){
      try{
        v.setAttribute('controlsList','nodownload noplaybackrate');
        v.setAttribute('disablePictureInPicture','');
        v.disablePictureInPicture = true;
        v.addEventListener('contextmenu', function(e){ e.preventDefault(); });
      }catch(e){}
    });
    var verticals = document.querySelectorAll('.uls-air-videocard--9x16 .uls-immich-video__player');
    verticals.forEach(function(v){
      v.muted = true; v.loop = true; v.playsInline = true;
      v.setAttribute('muted',''); v.setAttribute('loop',''); v.setAttribute('playsinline','');
      v.removeAttribute('controls');
      if(v.preload === 'none'){ v.preload = 'metadata'; }
    });
    function tryPlay(v){ var p; try{ p = v.play(); }catch(e){ return; } if(p && p.catch){ p.catch(function(){ v.setAttribute('controls',''); }); } }
    if('IntersectionObserver' in window){
      var io = new IntersectionObserver(function(entries){
        entries.forEach(function(en){
          var v = en.target;
          if(en.isIntersecting){ if(v.paused) tryPlay(v); }
          else if(!v.paused){ try{ v.pause(); }catch(e){} }
        });
      }, { threshold: 0.25 });
      verticals.forEach(function(v){ io.observe(v); });
    } else {
      verticals.forEach(function(v){ tryPlay(v); });
    }
  }
  if(document.readyState === 'loading'){ document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
</script>
<?php }, 99 );
