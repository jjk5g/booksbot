<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/map.php';
$html=ob_get_clean();

// Camera health: let MySQL decide freshness so DB/PHP timezone differences
// cannot incorrectly turn a freshly checked camera into "unknown".
$cameraHealth=[];
$verifiedLive=0;
try{
    $pdo=cc_db();
    if($pdo && $pdo->query("SHOW TABLES LIKE 'stream_health'")->fetchColumn()){
        $rows=$pdo->query("SELECT l.slug,s.id AS stream_id,s.priority,sh.status,sh.checked_at,sh.last_ok_at,sh.consecutive_failures,
          CASE WHEN sh.checked_at IS NOT NULL AND sh.checked_at>=DATE_SUB(NOW(),INTERVAL 10 MINUTE) THEN 1 ELSE 0 END AS is_fresh
          FROM locations l
          JOIN streams s ON s.location_id=l.id AND s.active=1 AND s.stream_type IN ('hls','iframe') AND s.stream_url IS NOT NULL AND s.stream_url<>''
          LEFT JOIN stream_health sh ON sh.stream_id=s.id
          WHERE l.active=1
          ORDER BY l.id,s.priority,s.id")->fetchAll();
        foreach($rows as $r){
            $slug=(string)$r['slug'];
            if(isset($cameraHealth[$slug])) continue;
            $state=((int)$r['is_fresh']===1)?(((string)$r['status']==='ok')?'ok':'down'):'unknown';
            if($state==='ok') $verifiedLive++;
            $cameraHealth[$slug]=[
                'status'=>$state,
                'checked_at'=>(string)($r['checked_at']??''),
                'last_ok_at'=>(string)($r['last_ok_at']??''),
                'failures'=>(int)($r['consecutive_failures']??0),
            ];
        }
    }
}catch(Throwable $e){}

// Shark map is intentionally aggregated by coastal zone so the map stays clean.
// Detailed per-beach/locality history remains in MySQL; the map displays only
// the newest verified notification for Gold Coast, Tweed Coast and Byron Shire.
$sharkZones=[];
try{
    $pdo=cc_db();
    if($pdo && $pdo->query("SHOW TABLES LIKE 'shark_alerts'")->fetchColumn()){
        $rows=$pdo->query("SELECT * FROM shark_alerts ORDER BY COALESCE(observed_at,verified_at,created_at) DESC,id DESC")->fetchAll();
        $seen=[];
        $regionNames=['gold-coast'=>'Gold Coast','tweed-coast'=>'Tweed Coast','byron-shire'=>'Byron Shire'];
        foreach($rows as $r){
            $rk=(string)($r['region_key']??'');
            if($rk==='' || isset($seen[$rk])) continue;
            $lat=isset($r['latitude'])&&$r['latitude']!==null?(float)$r['latitude']:null;
            $lon=isset($r['longitude'])&&$r['longitude']!==null?(float)$r['longitude']:null;
            if($lat===null||$lon===null) continue;
            $seen[$rk]=true;
            $when=(string)($r['observed_at']??$r['verified_at']??$r['created_at']??'');
            $ts=$when!==''?strtotime($when):false;
            $sharkZones[]=[
                'region'=>$regionNames[$rk]??$rk,
                'region_key'=>$rk,
                'locality'=>(string)($r['locality']??($regionNames[$rk]??$rk)),
                'lat'=>$lat,'lon'=>$lon,
                'title'=>(string)$r['title'],'detail'=>(string)$r['detail'],
                'source_name'=>(string)$r['source_name'],'source_url'=>(string)$r['source_url'],
                'observed_at'=>$when,
                'date_label'=>$ts===false?'Unknown date':date('j M Y',$ts),
                'age_minutes'=>$ts===false?null:max(0,(int)floor((time()-$ts)/60)),
                'active'=>(bool)$r['active']
            ];
        }
    }
}catch(Throwable $e){}

$healthJson=json_encode($cameraHealth,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$sharkJson=json_encode($sharkZones,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$extraCss=<<<'HTML'
<style id="cc-camera-health-style">
.camera-pin.down,.camera-pin.unknown{background:#69737a!important;box-shadow:0 2px 8px #0008!important}.dot.down{background:#7d878d}.pop .tag.offline{color:#aab3b8}.preview.offline{background:#15191b;color:#a9b2b7;border:1px solid #343b3e;text-align:center;padding:18px}.btn.conditions{border-color:#6e7a80;color:#d4dadd}.shark-zone-label{font-size:11px;font-weight:800;color:#f0f3f4}.shark-zone-note{font-size:11px;color:#9da9ae}
</style>
HTML;
$html=str_replace('</head>',$extraCss.'</head>',$html);
$html=str_replace('<span><i class="dot live"></i>Live camera</span><span><i class="dot forecast"></i>Forecast / camera being verified</span>', '<span><i class="dot live"></i>Verified live camera</span><span><i class="dot down"></i>Camera temporarily unavailable</span><span><i class="dot forecast"></i>Forecast / no verified camera</span>', $html);
$html=preg_replace_callback('~<div class="stats">(\d+) locations · \d+ live feeds</div>~',fn($m)=>'<div class="stats">'.$m[1].' locations · '.$verifiedLive.' verified live feeds</div>',$html,1)??$html;
$html=preg_replace('~<span>🦈.*?</span>~','<span>🦈 Shark activity zones: red &lt;12h · dark orange 12–48h · light orange 48h–7d · green 7–30d · grey &gt;30d</span>',$html,1)??$html;

$inject=<<<HTML
const cameraHealth={$healthJson};
const ccSharkZones={$sharkJson};
sharkReports.splice(0,sharkReports.length,...ccSharkZones);
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
function ccSharkColour(mins){if(mins===null||mins===undefined)return '#89949b';if(mins<12*60)return '#ff4f52';if(mins<48*60)return '#ff8438';if(mins<7*24*60)return '#ffc24f';if(mins<30*24*60)return '#65d29b';return '#89949b';}
function ccSharkAge(r,long=false){const mins=r.age_minutes;if(mins===null||mins===undefined)return long?'Time unavailable':'?';if(mins<60)return long?'Less than 1 hour ago':'<1h';if(mins<72*60){const h=Math.max(1,Math.floor(mins/60));return long?`\${h} hour\${h===1?'':'s'} ago`:`\${h}h`;}if(mins<30*24*60){const d=Math.floor(mins/1440),h=Math.floor((mins%1440)/60);if(long)return `\${d} day\${d===1?'':'s'}\${h?` \${h} h`:''} ago`;return `\${d}d\${h?` \${h}h`:''}`;}return r.date_label||r.observed_at||'Older report';}
function ccZoneRadius(r){if(r.region_key==='gold-coast')return 12000;if(r.region_key==='tweed-coast')return 9000;return 8000;}
function ccSharkPopup(r){return `<div class="pop"><div class="tag shark">🦈 LATEST SHARK NOTIFICATION · \${esc(r.region)}</div><h3>\${esc(r.locality)}</h3><p><b>\${esc(r.title)}</b></p><p>\${esc(r.detail)}</p><p><strong>\${esc(ccSharkAge(r,true))}</strong>\${r.active?' · alert currently marked active':' · latest stored notification for this zone'}</p><p class="shark-zone-note">The coloured area is a regional activity indicator, not the shark's exact travel area.</p><div class="shark-source">Source: <a href="\${esc(r.source_url)}" target="_blank" rel="noopener">\${esc(r.source_name)}</a></div></div>`;}
addSharks=function(region='all'){
  sharkLayer.clearLayers();
  sharkReports.filter(r=>region==='all'||r.region===region).forEach(r=>{
    const c=ccSharkColour(r.age_minutes);
    L.circle([r.lat,r.lon],{radius:ccZoneRadius(r),color:c,weight:2,opacity:.8,fillColor:c,fillOpacity:.18,interactive:true})
      .bindPopup(ccSharkPopup(r),{maxWidth:340}).addTo(sharkLayer);
  });
};
HTML;
$html=str_replace('addMarkers();',$inject."\naddMarkers();",$html);

echo $html;