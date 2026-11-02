<?php
declare(strict_types=1);
ob_start();
require __DIR__.'/index.php';
$html=ob_get_clean();

$needle='<nav class="topnav"><a href="/#channels">Channels</a>';
$replacement='<nav class="topnav"><a href="/#channels">Channels</a><a href="/map">Map</a><a href="#" id="ccMusicNav">Music</a>';
$html=str_replace($needle,$replacement,$html);

$musicCss=<<<'CSS'
<style id="cc-music-style">
.cc-music-fab{position:fixed;right:20px;bottom:20px;z-index:1200;border:1px solid #32d16d;background:#0c1510;color:#eafff1;border-radius:999px;padding:11px 16px;font:700 13px/1 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;box-shadow:0 10px 30px #0008;cursor:pointer}.cc-music-fab:hover{transform:translateY(-1px);border-color:#55ed8e}.cc-music-panel{position:fixed;right:20px;bottom:72px;z-index:1201;width:min(360px,calc(100vw - 28px));background:#0b0e0c;border:1px solid #29332d;border-radius:18px;padding:16px;box-shadow:0 24px 70px #000c;display:none;font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}.cc-music-panel.open{display:block}.cc-music-head{display:flex;justify-content:space-between;gap:12px;align-items:start}.cc-music-head h3{margin:0;color:#fff;font-size:18px}.cc-music-close{border:0;background:transparent;color:#aab6af;font-size:20px;cursor:pointer}.cc-music-copy{color:#9da9a2;margin:8px 0 14px}.cc-music-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px}.cc-music-btn{border:1px solid #35413a;background:#111613;color:#fff;border-radius:999px;padding:10px 12px;text-align:center;cursor:pointer;font-weight:700}.cc-music-btn.spotify{background:#1ed760;color:#07150b;border-color:#1ed760}.cc-music-note{margin-top:12px;padding-top:11px;border-top:1px solid #202823;color:#758279;font-size:11px}.cc-music-status{display:none;margin-top:10px;color:#9fe6b7;font-size:12px}.cc-music-status.show{display:block}@media(max-width:650px){.cc-music-fab{right:12px;bottom:12px}.cc-music-panel{right:12px;bottom:62px}}
</style>
CSS;
$html=str_replace('</head>',$musicCss.'</head>',$html);

$musicUi=<<<'HTML'
<button class="cc-music-fab" id="ccMusicFab" type="button" aria-haspopup="dialog" aria-controls="ccMusicPanel">♫ Spotify</button>
<div class="cc-music-panel" id="ccMusicPanel" role="dialog" aria-label="Music mode" aria-hidden="true">
  <div class="cc-music-head"><div><div style="color:#1ed760;font-size:11px;font-weight:800;letter-spacing:.08em">MUSIC MODE</div><h3>Beach TV + your music</h3></div><button class="cc-music-close" id="ccMusicClose" type="button" aria-label="Close">×</button></div>
  <p class="cc-music-copy">Open Spotify in its own app or tab, choose any playlist, then come back to CostaCams. The beach video stays muted while your Spotify plays independently.</p>
  <div class="cc-music-actions">
    <a class="cc-music-btn spotify" id="ccOpenSpotify" href="https://open.spotify.com/" target="_blank" rel="noopener">Open Spotify</a>
    <button class="cc-music-btn" id="ccMuteVideo" type="button">Mute beach video</button>
  </div>
  <div class="cc-music-status" id="ccMusicStatus">Music mode ready — choose your music in Spotify and keep CostaCams open.</div>
  <div class="cc-music-note">Spotify playback is kept separate from the CostaCams video so the site does not combine or synchronize Spotify audio with third-party camera streams.</div>
</div>
<script>
(function(){
 const panel=document.getElementById('ccMusicPanel'),fab=document.getElementById('ccMusicFab'),nav=document.getElementById('ccMusicNav'),close=document.getElementById('ccMusicClose'),mute=document.getElementById('ccMuteVideo'),openSpotify=document.getElementById('ccOpenSpotify'),status=document.getElementById('ccMusicStatus');
 function setOpen(v){if(!panel)return;panel.classList.toggle('open',v);panel.setAttribute('aria-hidden',v?'false':'true');}
 function openPanel(e){if(e)e.preventDefault();setOpen(true);}
 fab&&fab.addEventListener('click',()=>setOpen(!panel.classList.contains('open'))); nav&&nav.addEventListener('click',openPanel); close&&close.addEventListener('click',()=>setOpen(false));
 mute&&mute.addEventListener('click',()=>{document.querySelectorAll('video').forEach(v=>{v.muted=true;v.volume=0;});mute.textContent='Beach audio muted ✓';status&&status.classList.add('show');localStorage.setItem('ccMusicMode','1');});
 openSpotify&&openSpotify.addEventListener('click',()=>{document.querySelectorAll('video').forEach(v=>{v.muted=true;v.volume=0;});status&&status.classList.add('show');localStorage.setItem('ccMusicMode','1');});
 if(localStorage.getItem('ccMusicMode')==='1'){document.querySelectorAll('video').forEach(v=>{v.muted=true;v.volume=0;});status&&status.classList.add('show');}
 document.addEventListener('keydown',e=>{if(e.key==='Escape')setOpen(false);});
})();
</script>
HTML;
$html=str_replace('</body>',$musicUi.'</body>',$html);

echo $html;
