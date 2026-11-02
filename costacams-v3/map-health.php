<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/map.php';
$html=ob_get_clean();

$cameraHealth=[];
$verifiedLive=0;
try{
    $pdo=cc_db();
    if($pdo && $pdo->query("SHOW TABLES LIKE 'stream_health'")->fetchColumn()){
        $rows=$pdo->query("SELECT l.slug,s.id AS stream_id,s.priority,sh.status,sh.checked_at,sh.last_ok_at,sh.consecutive_failures
          FROM locations l
          JOIN streams s ON s.location_id=l.id AND s.active=1 AND s.stream_type IN ('hls','iframe') AND s.stream_url IS NOT NULL AND s.stream_url<>''
          LEFT JOIN stream_health sh ON sh.stream_id=s.id
          WHERE l.active=1
          ORDER BY l.id,s.priority,s.id")->fetchAll();
        foreach($rows as $r){
            $slug=(string)$r['slug'];
            if(isset($cameraHealth[$slug])) continue;
            $checked=(string)($r['checked_at']??'');
            $checkedTs=$checked!==''?strtotime($checked):false;
            $fresh=$checkedTs!==false && $checkedTs>=time()-600;
            $state=$fresh?(((string)$r['status']==='ok')?'ok':'down'):'unknown';
            if($state==='ok') $verifiedLive++;
            $cameraHealth[$slug]=[
                'status'=>$state,
                'checked_at'=>$checked,
                'last_ok_at'=>(string)($r['last_ok_at']??''),
                'failures'=>(int)($r['consecutive_failures']??0),
            ];
        }
    }
}catch(Throwable $e){}

$healthJson=json_encode($cameraHealth,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$healthCss=<<<'HTML'
<style id="cc-camera-health-style">
.camera-pin.down,.camera-pin.unknown{background:#69737a!important;box-shadow:0 2px 8px #0008!important}.dot.down{background:#7d878d}.pop .tag.offline{color:#aab3b8}.preview.offline{background:#15191b;color:#a9b2b7;border:1px solid #343b3e;text-align:center;padding:18px}.btn.conditions{border-color:#6e7a80;color:#d4dadd}
</style>
HTML;
$html=str_replace('</head>',$healthCss.'</head>',$html);
$html=str_replace('<span><i class="dot live"></i>Live camera</span><span><i class="dot forecast"></i>Forecast / camera being verified</span>', '<span><i class="dot live"></i>Verified live camera</span><span><i class="dot down"></i>Camera temporarily unavailable</span><span><i class="dot forecast"></i>Forecast / no verified camera</span>', $html);
$html=preg_replace_callback('~<div class="stats">(\d+) locations · \d+ live feeds</div>~',fn($m)=>'<div class="stats">'.$m[1].' locations · '.$verifiedLive.' verified live feeds</div>',$html,1)??$html;

$inject=<<<HTML
const cameraHealth={$healthJson};
function ccCameraState(cam){
  if(!cam.live)return 'forecast';
  const h=cameraHealth[cam.slug];
  if(!h||h.status==='unknown')return 'unknown';
  return h.status==='ok'?'live':'down';
}
iconFor=function(cam){const state=ccCameraState(cam);return L.divIcon({className:'camera-marker',html:`<div class="camera-pin \${state}"><span>📹</span></div>`,iconSize:[34,34],iconAnchor:[17,34],popupAnchor:[0,-31]});};
popupHtml=function(cam){
  const state=ccCameraState(cam);
  if(state==='live')return `<div class="pop"><div class="tag">LIVE CAMERA · \${esc(cam.region)}</div><h3>\${esc(cam.name)}</h3><div class="preview" id="preview-\${esc(cam.slug)}">Loading preview…</div><div class="pop-actions"><a class="btn primary" href="/\${encodeURIComponent(cam.slug)}">Open channel</a></div></div>`;
  if(state==='forecast')return `<div class="pop"><div class="tag">FORECAST CHANNEL · \${esc(cam.region)}</div><h3>\${esc(cam.name)}</h3><div class="preview offline">A verified live camera is not available for this location yet.</div><div class="pop-actions"><a class="btn conditions" href="/\${encodeURIComponent(cam.slug)}">Beach conditions</a></div></div>`;
  const msg=state==='down'?'Camera temporarily unavailable. We recheck it automatically every 5 minutes.':'Camera status is being rechecked. It will turn green automatically once verified.';
  return `<div class="pop"><div class="tag offline">CAMERA TEMPORARILY OFFLINE · \${esc(cam.region)}</div><h3>\${esc(cam.name)}</h3><div class="preview offline">\${esc(msg)}</div><div class="pop-actions"><a class="btn conditions" href="/\${encodeURIComponent(cam.slug)}">Beach conditions</a></div></div>`;
};
const ccLoadPreviewHealthy=loadPreview;
loadPreview=function(cam){if(ccCameraState(cam)!=='live')return;return ccLoadPreviewHealthy(cam);};
HTML;
$html=str_replace('addMarkers();',$inject."\naddMarkers();",$html);

echo $html;
