<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$locations=cc_locations();
function e($v): string {return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function live_stream(array $loc): ?array {foreach($loc['streams']??[] as $s){if(in_array($s['stream_type'],['hls','iframe'],true)&&!empty($s['stream_url']))return $s;}return null;}
function cc_map_place_key(string $s): string {return preg_replace('/[^a-z0-9]+/','',strtolower($s))??'';}
$mapData=[];$liveCount=0;$placeCoords=[];
foreach($locations as $loc){
  $s=live_stream($loc); if($s)$liveCount++;
  $mapData[]=[
    'slug'=>$loc['slug'],'name'=>$loc['name'],'region'=>$loc['region'],
    'lat'=>(float)$loc['lat'],'lon'=>(float)$loc['lon'],'live'=>(bool)$s,
    'stream'=>$s?['type'=>$s['stream_type'],'url'=>$s['stream_url'],'name'=>$s['name']]:null
  ];
  $coords=[(float)$loc['lat'],(float)$loc['lon']];
  $placeCoords[cc_map_place_key((string)$loc['name'])]=$coords;
  $placeCoords[cc_map_place_key(str_replace('-',' ',(string)$loc['slug']))]=$coords;
}
$sharkMap=[];
try{
  $pdo=cc_db();
  if($pdo && $pdo->query("SHOW TABLES LIKE 'shark_alerts'")->fetchColumn()){
    $rows=$pdo->query("SELECT * FROM shark_alerts ORDER BY COALESCE(observed_at,verified_at,created_at) DESC, id DESC")->fetchAll();
    $seen=[];$regionNames=['gold-coast'=>'Gold Coast','tweed-coast'=>'Tweed Coast','byron-shire'=>'Byron Shire'];
    foreach($rows as $r){
      $rk=(string)$r['region_key'];
      $locality=trim((string)($r['locality']??''));
      $lat=isset($r['latitude'])&&$r['latitude']!==null?(float)$r['latitude']:null;
      $lon=isset($r['longitude'])&&$r['longitude']!==null?(float)$r['longitude']:null;
      if(($lat===null||$lon===null)&&$locality!==''){
        $keys=[cc_map_place_key($locality)];$first=trim(explode(',',$locality)[0]??'');if($first!=='')$keys[]=cc_map_place_key($first);
        foreach($keys as $pk){if(isset($placeCoords[$pk])){[$lat,$lon]=$placeCoords[$pk];break;}}
      }
      if($lat===null||$lon===null)continue;
      $placeKey=cc_map_place_key($locality);
      if($placeKey==='')$placeKey=$rk.'-'.round($lat,4).'-'.round($lon,4);
      if(isset($seen[$placeKey]))continue;$seen[$placeKey]=true;
      $when=(string)($r['observed_at']??$r['verified_at']??$r['created_at']??'');
      $ts=$when!==''?strtotime($when):false;
      $ageMin=$ts===false?null:max(0,(int)floor((time()-$ts)/60));
      $dateLabel=$ts===false?'Unknown date':date('j M Y',$ts);
      $sharkMap[]=[
        'region'=>$regionNames[$rk]??$rk,'region_key'=>$rk,'locality'=>$locality?:($regionNames[$rk]??$rk),
        'lat'=>$lat,'lon'=>$lon,'title'=>(string)$r['title'],'detail'=>(string)$r['detail'],
        'source_name'=>(string)$r['source_name'],'source_url'=>(string)$r['source_url'],
        'observed_at'=>$when,'date_label'=>$dateLabel,'age_minutes'=>$ageMin,'active'=>(bool)$r['active']
      ];
    }
  }
}catch(Throwable $e){}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Live Beach Camera Map | CostaCams</title>
<meta name="description" content="Explore CostaCams live beach cameras and the latest verified shark notifications on an interactive map from the Gold Coast through Tweed Coast to Byron Bay.">
<link rel="canonical" href="https://costacams.com/map">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<style>
:root{--bg:#050505;--panel:#0d0f10;--panel2:#121516;--line:#252a2d;--text:#f4f7f8;--muted:#9da9ae;--aqua:#50dcff;--good:#69e0a0;--warn:#ffbf69;--bad:#ff7468}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}a{color:inherit;text-decoration:none}.top{position:sticky;top:0;z-index:1000;display:flex;justify-content:space-between;align-items:center;padding:13px max(18px,4vw);background:#050505ed;border-bottom:1px solid #1d2224;backdrop-filter:blur(14px)}.brand{font-size:18px;font-weight:900;letter-spacing:.1em}.brand b{color:var(--aqua)}.topnav{display:flex;gap:14px;color:var(--muted);font-size:13px}.topnav .active{color:var(--aqua)}.wrap{max-width:1500px;margin:auto;padding:22px max(16px,3vw) 36px}.hero{display:flex;justify-content:space-between;gap:24px;align-items:end;margin-bottom:14px}.eyebrow{color:var(--aqua);font-weight:800;font-size:11px;letter-spacing:.09em;text-transform:uppercase}.hero h1{font-size:clamp(32px,5vw,58px);line-height:1;margin:5px 0 8px;letter-spacing:-.04em}.hero p{margin:0;color:var(--muted);max-width:760px}.stats{white-space:nowrap;color:var(--muted);font-size:13px}.controls{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}.chip{border:1px solid #343b3e;background:#111416;color:#dce5e8;border-radius:999px;padding:8px 12px;cursor:pointer}.chip.active{border-color:var(--aqua);color:var(--aqua)}#map{height:min(76vh,860px);min-height:520px;border:1px solid var(--line);border-radius:20px;overflow:hidden;background:#15191b}.legend{display:flex;gap:14px;flex-wrap:wrap;color:var(--muted);font-size:12px;margin-top:10px}.dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:5px}.dot.live{background:var(--good);box-shadow:0 0 8px var(--good)}.dot.forecast{background:var(--warn)}.camera-marker,.shark-marker{background:transparent;border:0}.camera-pin{width:34px;height:34px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:grid;place-items:center;border:2px solid #050505;box-shadow:0 2px 8px #0008}.camera-pin.live{background:var(--good)}.camera-pin.forecast{background:var(--warn)}.camera-pin span{transform:rotate(45deg);font-size:15px}.shark-wrap{position:relative;width:66px;height:60px;display:flex;justify-content:center}.shark-pin{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;border:2px solid;box-shadow:0 0 0 3px #050505aa,0 3px 12px #000b}.shark-pin span{font-size:22px;line-height:1}.shark-age{position:absolute;top:39px;left:50%;transform:translateX(-50%);white-space:nowrap;border:1px solid;border-radius:999px;padding:1px 6px;font-size:10px;font-weight:900;box-shadow:0 2px 8px #0008}.shark-pin.age-red,.shark-age.age-red{background:#4b0b0b;border-color:#ff4f52;color:#ffe1df}.shark-pin.age-orange-dark,.shark-age.age-orange-dark{background:#542306;border-color:#ff8438;color:#ffe3ca}.shark-pin.age-orange-light,.shark-age.age-orange-light{background:#55400b;border-color:#ffc24f;color:#fff0c2}.shark-pin.age-green,.shark-age.age-green{background:#123727;border-color:#65d29b;color:#d8f7e7}.shark-pin.age-gray,.shark-age.age-gray{background:#282d31;border-color:#89949b;color:#d5dade}.leaflet-popup-content-wrapper,.leaflet-popup-tip{background:#0d0f10;color:#f4f7f8}.leaflet-popup-content{margin:12px;width:290px!important}.pop .tag{font-size:10px;font-weight:800;letter-spacing:.08em;color:var(--aqua)}.pop .tag.shark{color:#ff9c92}.pop h3{margin:3px 0 8px;font-size:18px}.preview{width:100%;aspect-ratio:16/9;background:#000;border-radius:10px;overflow:hidden;display:flex;align-items:center;justify-content:center;color:#89969b;font-size:12px;margin:8px 0}.preview video,.preview iframe{width:100%;height:100%;border:0;background:#000}.pop-actions{display:flex;gap:7px;margin-top:9px}.btn{flex:1;text-align:center;border:1px solid #343b3e;background:#111416;border-radius:999px;padding:8px 10px;color:#fff;font-size:12px}.btn.primary{border-color:var(--aqua);color:var(--aqua)}.shark-source{margin-top:8px;color:#9da9ae;font-size:11px}.shark-source a{color:#ffc4be;text-decoration:underline}.leaflet-control-zoom a{background:#111!important;color:#fff!important;border-color:#333!important}.leaflet-control-attribution{background:#070909cc!important;color:#95a1a6!important}.leaflet-control-attribution a{color:#b9c5ca!important}.footer{padding:26px max(18px,4vw);border-top:1px solid #1c2224;color:#78868b}.footer a{color:#b9c6ca}
@media(max-width:700px){.topnav{gap:9px;font-size:12px}.topnav a:nth-child(3),.topnav a:nth-child(4){display:none}.hero{display:block}.stats{margin-top:8px}#map{height:72vh;min-height:480px}.leaflet-popup-content{width:245px!important}}
</style></head><body>
<header class="top"><a class="brand" href="/"><b>COSTA</b>CAMS</a><nav class="topnav"><a href="/#channels">Channels</a><a class="active" href="/map">Map</a><a href="/#news">Surf news</a><a href="/#events">Events</a><a href="/contact.php">Contact</a></nav></header>
<main class="wrap"><section class="hero"><div><div class="eyebrow">CAMERA + SHARK MAP</div><h1>Find your beach.</h1><p>Explore every CostaCams location from The Spit to Byron Bay. Camera pins open a live preview. Shark pins show the latest verified notification stored for each beach or locality.</p></div><div class="stats"><?=count($locations)?> locations · <?=$liveCount?> live feeds</div></section>
<div class="controls"><button class="chip active" data-region="all">All</button><button class="chip" data-region="Gold Coast">Gold Coast</button><button class="chip" data-region="Tweed Coast">Tweed Coast</button><button class="chip" data-region="Byron Shire">Byron</button></div>
<div id="map" aria-label="Interactive map of CostaCams beach cameras and latest shark notifications"></div><div class="legend"><span><i class="dot live"></i>Live camera</span><span><i class="dot forecast"></i>Forecast / camera being verified</span><span>🦈 &lt;12h red · 12–48h dark orange · 48h–7d light orange · 7–30d green · &gt;30d grey/date</span></div></main>
<footer class="footer">CostaCams · Interactive beach camera and shark-notification map · <a href="/">All channels</a></footer>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script>
const cams=<?=json_encode($mapData,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>;
const sharkReports=<?=json_encode($sharkMap,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>;
const map=L.map('map',{zoomControl:true,scrollWheelZoom:true}).setView([-28.25,153.52],9);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
let cluster=L.markerClusterGroup({showCoverageOnHover:false,maxClusterRadius:44});
let sharkLayer=L.layerGroup().addTo(map);
let activeHls=null;
const markers=[];
function esc(s){return String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
function iconFor(cam){return L.divIcon({className:'camera-marker',html:`<div class="camera-pin ${cam.live?'live':'forecast'}"><span>📹</span></div>`,iconSize:[34,34],iconAnchor:[17,34],popupAnchor:[0,-31]});}
function sharkAgeClass(mins){if(mins===null||mins===undefined)return 'age-gray';if(mins<12*60)return 'age-red';if(mins<48*60)return 'age-orange-dark';if(mins<7*24*60)return 'age-orange-light';if(mins<30*24*60)return 'age-green';return 'age-gray';}
function ageText(r,long=false){const mins=r.age_minutes;if(mins===null||mins===undefined)return long?'Time unavailable':'?';if(mins<60)return long?'Less than 1 hour ago':'<1h';if(mins<72*60){const h=Math.max(1,Math.floor(mins/60));return long?`${h} hour${h===1?'':'s'} ago`:`${h}h`; }if(mins<30*24*60){const d=Math.floor(mins/1440),h=Math.floor((mins%1440)/60);if(long)return `${d} day${d===1?'':'s'}${h?` ${h} h`:''} ago`;return `${d}d${h?` ${h}h`:''}`;}return r.date_label||r.observed_at||'Older report';}
function sharkIcon(r){const cls=sharkAgeClass(r.age_minutes);return L.divIcon({className:'shark-marker',html:`<div class="shark-wrap"><div class="shark-pin ${cls}"><span>🦈</span></div><b class="shark-age ${cls}">${esc(ageText(r,false))}</b></div>`,iconSize:[66,60],iconAnchor:[33,30],popupAnchor:[0,-24]});}
function popupHtml(cam){return `<div class="pop"><div class="tag">${cam.live?'LIVE CAMERA':'FORECAST CHANNEL'} · ${esc(cam.region)}</div><h3>${esc(cam.name)}</h3><div class="preview" id="preview-${esc(cam.slug)}">${cam.live?'Loading preview…':'Live video is still being verified for this location.'}</div><div class="pop-actions"><a class="btn primary" href="/${encodeURIComponent(cam.slug)}">Open channel</a></div></div>`;}
function sharkPopup(r){return `<div class="pop"><div class="tag shark">🦈 LATEST SHARK NOTIFICATION · ${esc(r.region)}</div><h3>${esc(r.locality)}</h3><p><b>${esc(r.title)}</b></p><p>${esc(r.detail)}</p><p><strong>${esc(ageText(r,true))}</strong>${r.active?' · alert currently marked active':' · latest stored notification for this beach/locality'}</p><div class="shark-source">Source: <a href="${esc(r.source_url)}" target="_blank" rel="noopener">${esc(r.source_name)}</a></div></div>`;}
function loadPreview(cam){
  if(activeHls){activeHls.destroy();activeHls=null;}
  const box=document.getElementById('preview-'+cam.slug); if(!box||!cam.stream)return;
  box.innerHTML='';
  if(cam.stream.type==='iframe'){
    const f=document.createElement('iframe');f.src=cam.stream.url;f.allow='autoplay; fullscreen';f.allowFullscreen=true;f.loading='eager';box.appendChild(f);return;
  }
  if(cam.stream.type==='hls'){
    const v=document.createElement('video');v.muted=true;v.autoplay=true;v.playsInline=true;v.controls=true;box.appendChild(v);
    if(window.Hls&&Hls.isSupported()){activeHls=new Hls({enableWorker:true,lowLatencyMode:true});activeHls.loadSource(cam.stream.url);activeHls.attachMedia(v);activeHls.on(Hls.Events.MANIFEST_PARSED,()=>v.play().catch(()=>{}));activeHls.on(Hls.Events.ERROR,(ev,data)=>{if(data.fatal)box.innerHTML='Preview unavailable right now. Open the channel to retry.';});}
    else if(v.canPlayType('application/vnd.apple.mpegurl')){v.src=cam.stream.url;v.play().catch(()=>{});}else box.textContent='Preview not supported in this browser.';
  }
}
function addSharks(region='all'){
  sharkLayer.clearLayers();
  sharkReports.filter(r=>region==='all'||r.region===region).forEach(r=>L.marker([r.lat,r.lon],{icon:sharkIcon(r),zIndexOffset:1000}).bindPopup(sharkPopup(r),{maxWidth:330}).addTo(sharkLayer));
}
function addMarkers(region='all'){
  cluster.clearLayers();markers.length=0;
  const visible=cams.filter(c=>region==='all'||c.region===region);
  visible.forEach(cam=>{const m=L.marker([cam.lat,cam.lon],{icon:iconFor(cam)}).bindPopup(popupHtml(cam),{maxWidth:315});m.on('popupopen',()=>loadPreview(cam));m.on('popupclose',()=>{if(activeHls){activeHls.destroy();activeHls=null;}});markers.push(m);cluster.addLayer(m);});
  map.addLayer(cluster);addSharks(region);if(visible.length){const bounds=L.latLngBounds(visible.map(c=>[c.lat,c.lon]));map.fitBounds(bounds.pad(.08),{maxZoom:12});}
}
addMarkers();
document.querySelectorAll('[data-region]').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('[data-region]').forEach(b=>b.classList.remove('active'));btn.classList.add('active');addMarkers(btn.dataset.region);}));
</script></body></html>