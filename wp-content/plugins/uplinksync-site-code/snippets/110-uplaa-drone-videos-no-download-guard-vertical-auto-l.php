<?php
/**
 * UPLAA drone videos — no-download guard + vertical auto-loop (2026-07-31)
 *
 * Migrated from database-resident Code Snippets row id=110 (DR-004 tranche 2).
 * scope: front-end   priority: 99
 *
 * /drone-services/ video behavior: (1) deterrent against right-click/download on all reel+vertical players (controlsList=nodownload, disablePictureInPicture, contextmenu prevented) — NOTE: deterrent only, the Immich share URL is still publicly fetchable; the burned-in credit is the real authorship pro
 *
 * Migrated VERBATIM. Any behaviour change to this snippet is a separate commit, so
 * that the migration itself can be proven byte-identical in rendered output.
 *
 * The wp_snippets row is DEACTIVATED, not deleted - re-activating it in wp-admin
 * is the rollback and needs no deploy. Exactly one copy must be active at a time.
 */
defined( 'ABSPATH' ) || exit;

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
