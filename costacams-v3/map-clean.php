<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/map-health.php';
$html=ob_get_clean();

// Only currently VERIFIED LIVE cameras get a camera pin. A beach may still have
// a page and historical/forecast data, but if its latest health result is not OK
// it is deliberately absent from the map instead of appearing as a fake/offline cam.
$html=str_replace(
    "const visible=cams.filter(c=>region==='all'||c.region===region);",
    "const visible=cams.filter(c=>ccCameraState(c)==='live'&&(region==='all'||c.region===region));",
    $html
);
$html=str_replace('<span><i class="dot down"></i>Camera temporarily unavailable</span>','',$html);
$html=str_replace('<span><i class="dot forecast"></i>Forecast / no verified camera</span>','',$html);
$html=str_replace('Explore every CostaCams location from The Spit to Byron Bay. Camera pins open a live preview. Shark pins show the latest verified notification stored for each beach or locality.','Explore currently verified live camera locations from The Spit to Byron Bay. A camera pin appears only while that feed is passing the automatic health check. Shark activity is shown separately by coastal zone.',$html);
$html=str_replace('Explore verified camera locations from The Spit to Byron Bay. Camera pins appear only where CostaCams has an actual stream. Shark activity is shown separately by coastal zone.','Explore currently verified live camera locations from The Spit to Byron Bay. A camera pin appears only while that feed is passing the automatic health check. Shark activity is shown separately by coastal zone.',$html);

// Shark radius is context only: it sits visually behind camera markers and never
// captures clicks. The tiny shark at the eastern edge is the only clickable shark
// control, preventing a camera click from opening a shark popup.
$html=str_replace('fillOpacity:.16,interactive:true','fillOpacity:.10,interactive:false',$html);
$html=str_replace("iconSize:[22,22],iconAnchor:[11,11]","iconSize:[18,18],iconAnchor:[9,9]",$html);
$sharkCss=<<<'HTML'
<style id="cc-shark-click-fix">
.shark-edge{width:18px!important;height:18px!important;font-size:10px!important;border-width:1px!important;box-shadow:0 1px 4px #0008!important}
</style>
HTML;
$html=str_replace('</head>',$sharkCss.'</head>',$html);

// HLS.js is unnecessary for the initial map render. Safari can play HLS natively;
// other browsers download the library only after the user actually opens an HLS cam.
$html=str_replace('<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>','',$html);
$lazy=<<<'HTML'
<script id="cc-map-hls-lazy">
(function(){
  const base=window.loadPreview;
  if(typeof base!=='function')return;
  let loading=false,queued=[];
  function ensureHls(done){
    if(window.Hls){done();return;}
    queued.push(done);if(loading)return;loading=true;
    const s=document.createElement('script');s.src='https://cdn.jsdelivr.net/npm/hls.js@latest';s.async=true;
    s.onload=()=>{loading=false;const q=queued.splice(0);q.forEach(fn=>fn());};
    s.onerror=()=>{loading=false;queued.length=0;};
    document.head.appendChild(s);
  }
  window.loadPreview=function(cam){
    if(!cam||!cam.stream||cam.stream.type!=='hls'){return base(cam);}
    const probe=document.createElement('video');
    if(probe.canPlayType('application/vnd.apple.mpegurl')||window.Hls){return base(cam);}
    ensureHls(()=>base(cam));
  };
})();
</script>
HTML;
$html=str_replace('</body>',$lazy.'</body>',$html);

echo $html;
