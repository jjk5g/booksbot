<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$locations=cc_locations();
function e($v): string {return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function live_stream(array $loc): ?array {foreach($loc['streams']??[] as $s){if(in_array($s['stream_type'],['hls','iframe'],true)&&!empty($s['stream_url']))return $s;}return null;}
$mapData=[];$liveCount=0;
foreach($locations as $loc){
  $s=live_stream($loc); if($s)$liveCount++;
  $mapData[]=[
    'slug'=>$loc['slug'],'name'=>$loc['name'],'region'=>$loc['region'],
    'lat'=>(float)$loc['lat'],'lon'=>(float)$loc['lon'],'live'=>(bool)$s,
    'stream'=>$s?['type'=>$s['stream_type'],'url'=>$s['stream_url'],'name'=>$s['name']]:null
  ];
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Live Beach Camera Map | CostaCams</title>
<meta name="description" content="Explore CostaCams live beach cameras on an interactive map from the Gold Coast through Tweed Coast to Byron Bay.">
<link rel="canonical" href="https://costacams.com/map">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<style>
:root{--bg:#050505;--panel:#0d0f10;--panel2:#121516;--line:#252a2d;--text:#f4f7f8;--muted:#9da9ae;--aqua:#50dcff;--good:#69e0a0;--warn:#ffbf69}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}a{color:inherit;text-decoration:none}.top{position:sticky;top:0;z-index:1000;display:flex;justify-content:space-between;align-items:center;padding:13px max(18px,4vw);background:#050505ed;border-bottom:1px solid #1d2224;backdrop-filter:blur(14px)}.brand{font-size:18px;font-weight:900;letter-spacing:.1em}.brand b{color:var(--aqua)}.topnav{display:flex;gap:14px;color:var(--muted);font-size:13px}.topnav .active{color:var(--aqua)}.wrap{max-width:1500px;margin:auto;padding:22px max(16px,3vw) 36px}.hero{display:flex;justify-content:space-between;gap:24px;align-items:end;margin-bottom:14px}.eyebrow{color:var(--aqua);font-weight:800;font-size:11px;letter-spacing:.09em;text-transform:uppercase}.hero h1{font-size:clamp(32px,5vw,58px);line-height:1;margin:5px 0 8px;letter-spacing:-.04em}.hero p{margin:0;color:var(--muted);max-width:760px}.stats{white-space:nowrap;color:var(--muted);font-size:13px}.controls{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}.chip{border:1px solid #343b3e;background:#111416;color:#dce5e8;border-radius:999px;padding:8px 12px;cursor:pointer}.chip.active{border-color:var(--aqua);color:var(--aqua)}#map{height:min(76vh,860px);min-height:520px;border:1px solid var(--line);border-radius:20px;overflow:hidden;background:#15191b}.legend{display:flex;gap:14px;flex-wrap:wrap;color:var(--muted);font-size:12px;margin-top:10px}.dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:5px}.dot.live{background:var(--good);box-shadow:0 0 8px var(--good)}.dot.forecast{background:var(--warn)}.camera-marker{background:transparent;border:0}.camera-pin{width:34px;height:34px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:grid;place-items:center;border:2px solid #050505;box-shadow:0 2px 8px #0008}.camera-pin.live{background:var(--good)}.camera-pin.forecast{background:var(--warn)}.camera-pin span{transform:rotate(45deg);font-size:15px}.leaflet-popup-content-wrapper,.leaflet-popup-tip{background:#0d0f10;color:#f4f7f8}.leaflet-popup-content{margin:12px;width:290px!important}.pop .tag{font-size:10px;font-weight:800;letter-spacing:.08em;color:var(--aqua)}.pop h3{margin:3px 0 8px;font-size:18px}.preview{width:100%;aspect-ratio:16/9;background:#000;border-radius:10px;overflow:hidden;display:flex;align-items:center;justify-content:center;color:#89969b;font-size:12px;margin:8px 0}.preview video,.preview iframe{width:100%;height:100%;border:0;background:#000}.pop-actions{display:flex;gap:7px;margin-top:9px}.btn{flex:1;text-align:center;border:1px solid #343b3e;background:#111416;border-radius:999px;padding:8px 10px;color:#fff;font-size:12px}.btn.primary{border-color:var(--aqua);color:var(--aqua)}.leaflet-control-zoom a{background:#111!important;color:#fff!important;border-color:#333!important}.leaflet-control-attribution{background:#070909cc!important;color:#95a1a6!important}.leaflet-control-attribution a{color:#b9c5ca!important}.footer{padding:26px max(18px,4vw);border-top:1px solid #1c2224;color:#78868b}.footer a{color:#b9c6ca}
@media(max-width:700px){.topnav{gap:9px;font-size:12px}.topnav a:nth-child(3),.topnav a:nth-child(4){display:none}.hero{display:block}.stats{margin-top:8px}#map{height:72vh;min-height:480px}.leaflet-popup-content{width:245px!important}}
</style></head><body>
<header class="top"><a class="brand" href="/"><b>COSTA</b>CAMS</a><nav class="topnav"><a href="/#channels">Channels</a><a class="active" href="/map">Map</a><a href="/#news">Surf news</a><a href="/#events">Events</a><a href="/contact.php">Contact</a></nav></header>
<main class="wrap"><section class="hero"><div><div class="eyebrow">CAMERA MAP</div><h1>Find your beach.</h1><p>Explore every CostaCams location from the Gold Coast to Byron Bay. Tap a camera pin for a live mini preview, then open the full channel for surf, weather, tides and beach conditions.</p></div><div class="stats"><?=count($locations)?> locations · <?=$liveCount?> live feeds</div></section>
<div class="controls"><button class="chip active" data-region="all">All cameras</button><button class="chip" data-region="Gold Coast">Gold Coast</button><button class="chip" data-region="Tweed Coast">Tweed Coast</button><button class="chip" data-region="Byron Shire">Byron</button></div>
<div id="map" aria-label="Interactive map of CostaCams beach camera locations"></div><div class="legend"><span><i class="dot live"></i>Live camera</span><span><i class="dot forecast"></i>Forecast / camera being verified</span><span>Preview loads only when a marker is opened, keeping the map fast.</span></div></main>
<footer class="footer">CostaCams · Interactive beach camera map · <a href="/">All channels</a></footer>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script>
const cams=<?=json_encode($mapData,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>;
const map=L.map('map',{zoomControl:true,scrollWheelZoom:true}).setView([-28.25,153.52],9);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
let cluster=L.markerClusterGroup({showCoverageOnHover:false,maxClusterRadius:44});
let activeHls=null;
const markers=[];
function esc(s){return String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
function iconFor(cam){return L.divIcon({className:'camera-marker',html:`<div class="camera-pin ${cam.live?'live':'forecast'}"><span>📹</span></div>`,iconSize:[34,34],iconAnchor:[17,34],popupAnchor:[0,-31]});}
function popupHtml(cam){return `<div class="pop"><div class="tag">${cam.live?'LIVE CAMERA':'FORECAST CHANNEL'} · ${esc(cam.region)}</div><h3>${esc(cam.name)}</h3><div class="preview" id="preview-${esc(cam.slug)}">${cam.live?'Loading preview…':'Live video is still being verified for this location.'}</div><div class="pop-actions"><a class="btn primary" href="/${encodeURIComponent(cam.slug)}">Open channel</a></div></div>`;}
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
function addMarkers(region='all'){
  cluster.clearLayers();markers.length=0;
  const visible=cams.filter(c=>region==='all'||c.region===region);
  visible.forEach(cam=>{const m=L.marker([cam.lat,cam.lon],{icon:iconFor(cam)}).bindPopup(popupHtml(cam),{maxWidth:315});m.on('popupopen',()=>loadPreview(cam));m.on('popupclose',()=>{if(activeHls){activeHls.destroy();activeHls=null;}});markers.push(m);cluster.addLayer(m);});
  map.addLayer(cluster);if(visible.length){const bounds=L.latLngBounds(visible.map(c=>[c.lat,c.lon]));map.fitBounds(bounds.pad(.08),{maxZoom:12});}
}
addMarkers();
document.querySelectorAll('[data-region]').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('[data-region]').forEach(b=>b.classList.remove('active'));btn.classList.add('active');addMarkers(btn.dataset.region);}));
</script></body></html>
