<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/map-health.php';
$html=ob_get_clean();

// Only locations with an actual HLS/iframe stream get a camera pin. Forecast-
// only beaches remain available as pages but are not represented by a fake cam.
$html=str_replace(
    "const visible=cams.filter(c=>region==='all'||c.region===region);",
    "const visible=cams.filter(c=>c.live&&(region==='all'||c.region===region));",
    $html
);
$html=str_replace('<span><i class="dot forecast"></i>Forecast / no verified camera</span>','',$html);
$html=str_replace('Explore every CostaCams location from The Spit to Byron Bay. Camera pins open a live preview. Shark pins show the latest verified notification stored for each beach or locality.','Explore verified camera locations from The Spit to Byron Bay. Camera pins appear only where CostaCams has an actual stream. Shark activity is shown separately by coastal zone.',$html);

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
