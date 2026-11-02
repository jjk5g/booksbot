<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/map.php';
$html=ob_get_clean();

// Reduce third-party work on initial map load: use one CDN, remove clustering
// (34 camera markers do not require it), and load HLS only after a user opens a
// verified camera popup.
$html=str_replace('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css','https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css',$html);
$html=str_replace('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js','https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js',$html);
$html=preg_replace('~<link rel="stylesheet" href="https://unpkg\.com/leaflet\.markercluster[^>]+>~','',$html)??$html;
$html=preg_replace('~<script src="https://unpkg\.com/leaflet\.markercluster[^>]+></script>~','',$html)??$html;
$html=preg_replace('~<script src="https://cdn\.jsdelivr\.net/npm/hls\.js@latest"></script>~','',$html)??$html;
$html=str_replace("let cluster=L.markerClusterGroup({showCoverageOnHover:false,maxClusterRadius:44});","let cluster=L.layerGroup();",$html);

$cameraHealth=[];$verifiedLive=0;
try{
    $pdo=cc_db();
    if($pdo && $pdo->query("SHOW TABLES LIKE 'stream_health'")->fetchColumn()){
        $rows=$pdo->query("SELECT l.slug,s.id AS stream_id,s.priority,sh.status,sh.checked_at,sh.last_ok_at,sh.consecutive_failures,
          CASE WHEN sh.checked_at IS NOT NULL AND sh.checked_at>=DATE_SUB(NOW(),INTERVAL 10 MINUTE) THEN 1 ELSE 0 END AS is_fresh
          FROM locations l JOIN streams s ON s.location_id=l.id AND s.active=1 AND s.stream_type IN ('hls','iframe') AND s.stream_url IS NOT NULL AND s.stream_url<>''
          LEFT JOIN stream_health sh ON sh.stream_id=s.id WHERE l.active=1 ORDER BY l.id,s.priority,s.id")->fetchAll();
        foreach($rows as $r){$slug=(string)$r['slug'];if(isset($cameraHealth[$slug]))continue;$state=((int)$r['is_fresh']===1)?(((string)$r['status']==='ok')?'ok':'down'):'unknown';if($state==='ok')$verifiedLive++;$cameraHealth[$slug]=['status'=>$state,'checked_at'=>(string)($r['checked_at']??''),'last_ok_at'=>(string)($r['last_ok_at']??''),'failures'=>(int)($r['consecutive_failures']??0)];}
    }
}catch(Throwable $e){}

// Keep detailed history in MySQL but show only the newest verified report for
// each broad coastal zone. Fixed zone centres make the overlay a regional
// indicator rather than falsely suggesting the shark travelled from the report
// point across the whole radius.
$zoneGeometry=[
 'gold-coast'=>['name'=>'Gold Coast','lat'=>-28.055,'lon'=>153.455,'radius'=>17000],
 'tweed-coast'=>['name'=>'Tweed Coast','lat'=>-28.285,'lon'=>153.570,'radius'=>15000],
 'byron-shire'=>['name'=>'Byron Shire','lat'=>-28.610,'lon'=>153.590,'radius'=>17000],
];
$sharkZones=[];
try{
    $pdo=cc_db();
    if($pdo && $pdo->query("SHOW TABLES LIKE 'shark_alerts'")->fetchColumn()){
        $rows=$pdo->query("SELECT * FROM shark_alerts ORDER BY COALESCE(observed_at,verified_at,created_at) DESC,id DESC")->fetchAll();$seen=[];
        foreach($rows as $r){$rk=(string)($r['region_key']??'');if(!isset($zoneGeometry[$rk])||isset($seen[$rk]))continue;$seen[$rk]=true;$g=$zoneGeometry[$rk];$when=(string)($r['observed_at']??$r['verified_at']??$r['created_at']??'');$ts=$when!==''?strtotime($when):false;
            $sharkZones[]=['region'=>$g['name'],'region_key'=>$rk,'locality'=>(string)($r['locality']??$g['name']),'lat'=>$g['lat'],'lon'=>$g['lon'],'radius'=>$g['radius'],'report_lat'=>isset($r['latitude'])?(float)$r['latitude']:null,'report_lon'=>isset($r['longitude'])?(float)$r['longitude']:null,'title'=>(string)$r['title'],'detail'=>(string)$r['detail'],'source_name'=>(string)$r['source_name'],'source_url'=>(string)$r['source_url'],'observed_at'=>$when,'date_label'=>$ts===false?'Unknown date':date('j M Y',$ts),'age_minutes'=>$ts===false?null:max(0,(int)floor((time()-$ts)/60)),'active'=>(bool)$r['active']];
        }
    }
}catch(Throwable $e){}

$healthJson=json_encode($cameraHealth,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$sharkJson=json_encode($sharkZones,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$extraCss=<<<'HTML'
<style id="cc-map-fast-style">
.camera-pin.down,.camera-pin.unknown{background:#69737a!important;box-shadow:0 2px 8px #0008!important}.dot.down{background:#7d878d}.pop .tag.offline{color:#aab3b8}.preview.offline{background:#15191b;color:#a9b2b7;border:1px solid #343b3e;text-align:center;padding:18px}.btn.conditions{border-color:#6e7a80;color:#d4dadd}.shark-zone-note{font-size:11px;color:#9da9ae}.shark-edge-marker{background:transparent!important;border:0!important}.shark-edge{width:22px;height:22px;display:grid;place-items:center;border-radius:50%;background:#0b0e0fcc;border:1px solid #d7e0e4;font-size:13px;box-shadow:0 2px 6px #0009}.cc-map-seo{margin:18px 0 0;padding:16px 18px;border:1px solid #20282b;border-radius:14px;background:#090c0d;color:#aeb9bd}.cc-map-seo h2{margin:0 0 7px;color:#edf2f4;font-size:20px}.cc-map-seo p{margin:5px 0;max-width:1000px}
</style>
HTML;
$html=str_replace('</head>',$extraCss.'</head>',$html);
$html=str_replace('<span><i class="dot live"></i>Live camera</span><span><i class="dot forecast"></i>Forecast / camera being verified</span>','<span><i class="dot live"></i>Verified live camera</span><span><i class="dot down"></i>Camera temporarily unavailable</span><span><i class="dot forecast"></i>Forecast / no verified camera</span>',$html);
$html=preg_replace_callback('~<div class="stats">(\d+) locations · \d+ live feeds</div>~',fn($m)=>'<div class="stats">'.$m[1].' locations · '.$verifiedLive.' verified live feeds</div>',$html,1)??$html;
$html=preg_replace('~<span>🦈.*?</span>~','<span>🦈 Shark activity zones: red &lt;12h · dark orange 12–48h · light orange 48h–7d · green 7–30d · grey &gt;30d</span>',$html,1)??$html;
$seo='<section class="cc-map-seo"><h2>Gold Coast, Tweed Coast and Byron live beach camera map</h2><p>Use the CostaCams map to compare live or available beach cameras, surf locations and recent shark-safety activity across the coast. It is designed for surfers, swimmers, beachgoers, locals and visitors deciding which beach to check next.</p><p>Camera status is updated automatically. Shark colours show the age of the latest verified report for each broad zone and are not a prediction of current shark location or beach safety.</p></section>';
$html=str_replace('</main>',$seo.'</main>',$html);

$ga=<<<'HTML'
<script async src="https://www.googletagmanager.com/gtag/js?id=G-6WLK1YD519"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-6WLK1YD519');</script>
HTML;
$html=str_replace('</head>','<meta name="robots" content="index,follow,max-image-preview:large">'.$ga.'</head>',$html);

$inject=<<<HTML
const cameraHealth={$healthJson};
const ccSharkZones={$sharkJson};
sharkReports.splice(0,sharkReports.length,...ccSharkZones);
function ccCameraState(cam){if(!cam.live)return 'forecast';const h=cameraHealth[cam.slug];if(!h||h.status==='unknown')return 'unknown';return h.status==='ok'?'live':'down';}
iconFor=function(cam){const state=ccCameraState(cam);return L.divIcon({className:'camera-marker',html:`<div class="camera-pin \${state}"><span>📹</span></div>`,iconSize:[34,34],iconAnchor:[17,34],popupAnchor:[0,-31]});};
popupHtml=function(cam){const state=ccCameraState(cam);if(state==='live')return `<div class="pop"><div class="tag">LIVE CAMERA · \${esc(cam.region)}</div><h3>\${esc(cam.name)}</h3><div class="preview" id="preview-\${esc(cam.slug)}">Loading preview…</div><div class="pop-actions"><a class="btn primary" href="/\${encodeURIComponent(cam.slug)}">Open channel</a></div></div>`;if(state==='forecast')return `<div class="pop"><div class="tag">FORECAST CHANNEL · \${esc(cam.region)}</div><h3>\${esc(cam.name)}</h3><div class="preview offline">A verified live camera is not available for this location yet.</div><div class="pop-actions"><a class="btn conditions" href="/\${encodeURIComponent(cam.slug)}">Beach conditions</a></div></div>`;return `<div class="pop"><div class="tag offline">CAMERA TEMPORARILY OFFLINE · \${esc(cam.region)}</div><h3>\${esc(cam.name)}</h3><div class="preview offline">Camera temporarily unavailable. We recheck it automatically.</div><div class="pop-actions"><a class="btn conditions" href="/\${encodeURIComponent(cam.slug)}">Beach conditions</a></div></div>`;};
function ccLoadScript(src,cb){if(window.Hls)return cb();const s=document.createElement('script');s.src=src;s.async=true;s.onload=cb;document.head.appendChild(s);}
loadPreview=function(cam){if(ccCameraState(cam)!=='live'||!cam.stream)return;const box=document.getElementById('preview-'+cam.slug);if(!box)return;if(activeHls){activeHls.destroy();activeHls=null;}box.innerHTML='';if(cam.stream.type==='iframe'){const f=document.createElement('iframe');f.src=cam.stream.url;f.allow='autoplay; fullscreen';f.allowFullscreen=true;f.loading='lazy';box.appendChild(f);return;}const v=document.createElement('video');v.muted=true;v.autoplay=true;v.playsInline=true;v.controls=true;v.preload='metadata';box.appendChild(v);if(v.canPlayType('application/vnd.apple.mpegurl')){v.src=cam.stream.url;v.play().catch(()=>{});return;}ccLoadScript('https://cdn.jsdelivr.net/npm/hls.js@latest',()=>{if(window.Hls&&Hls.isSupported()){activeHls=new Hls({enableWorker:true,maxBufferLength:10});activeHls.loadSource(cam.stream.url);activeHls.attachMedia(v);activeHls.on(Hls.Events.MANIFEST_PARSED,()=>v.play().catch(()=>{}));}});};
function ccSharkColour(mins){if(mins===null||mins===undefined)return '#89949b';if(mins<12*60)return '#ff4f52';if(mins<48*60)return '#ff8438';if(mins<7*24*60)return '#ffc24f';if(mins<30*24*60)return '#65d29b';return '#89949b';}
function ccSharkAge(r,long=false){const mins=r.age_minutes;if(mins===null||mins===undefined)return long?'Time unavailable':'?';if(mins<60)return long?'Less than 1 hour ago':'<1h';if(mins<72*60){const h=Math.max(1,Math.floor(mins/60));return long?`\${h} hour\${h===1?'':'s'} ago`:`\${h}h`;}if(mins<30*24*60){const d=Math.floor(mins/1440),h=Math.floor((mins%1440)/60);return long?`\${d} day\${d===1?'':'s'}\${h?` \${h} h`:''} ago`:`\${d}d\${h?` \${h}h`:''}`;}return r.date_label||r.observed_at||'Older report';}
function ccSharkPopup(r){return `<div class="pop"><div class="tag shark">🦈 LATEST SHARK NOTIFICATION · \${esc(r.region)}</div><h3>\${esc(r.locality)}</h3><p><b>\${esc(r.title)}</b></p><p>\${esc(r.detail)}</p><p><strong>\${esc(ccSharkAge(r,true))}</strong>\${r.active?' · alert currently marked active':' · latest stored notification for this zone'}</p><p class="shark-zone-note">The coloured radius is a regional activity indicator, not the shark's exact travel area.</p><div class="shark-source">Source: <a href="\${esc(r.source_url)}" target="_blank" rel="noopener">\${esc(r.source_name)}</a></div></div>`;}
addSharks=function(region='all'){sharkLayer.clearLayers();sharkReports.filter(r=>region==='all'||r.region===region).forEach(r=>{const c=ccSharkColour(r.age_minutes),pop=ccSharkPopup(r);L.circle([r.lat,r.lon],{radius:r.radius,color:c,weight:2,opacity:.75,fillColor:c,fillOpacity:.16,interactive:true}).bindPopup(pop,{maxWidth:340}).addTo(sharkLayer);const east=r.lon+(r.radius/(111320*Math.cos(r.lat*Math.PI/180)))*.88;L.marker([r.lat,east],{icon:L.divIcon({className:'shark-edge-marker',html:'<div class="shark-edge">🦈</div>',iconSize:[22,22],iconAnchor:[11,11]}),zIndexOffset:900}).bindPopup(pop,{maxWidth:340}).addTo(sharkLayer);});};
HTML;
$html=str_replace('addMarkers();',$inject."\naddMarkers();",$html);
echo $html;
