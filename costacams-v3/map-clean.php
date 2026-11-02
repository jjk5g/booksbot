<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/map-health.php';
$html=ob_get_clean();

// Hostinger can fail to fetch some HLS playlists with cURL even while those same
// feeds play correctly in a visitor's browser. Treat an HLS camera as map-live
// when it is OK now OR it has a recent successful check. Iframes remain strict.
$trustedCams=[];
$trustedCount=0;
foreach(($locations??[]) as $loc){
    $stream=null;
    foreach(($loc['streams']??[]) as $s){
        if(in_array((string)($s['stream_type']??''),['hls','iframe'],true) && !empty($s['stream_url'])){$stream=$s;break;}
    }
    if(!$stream)continue;
    $slug=(string)$loc['slug'];
    $h=$cameraHealth[$slug]??null;
    $trusted=false;
    if(is_array($h) && ($h['status']??'')==='ok')$trusted=true;
    elseif(($stream['stream_type']??'')==='hls' && is_array($h) && !empty($h['last_ok_at'])){
        $t=strtotime((string)$h['last_ok_at']);
        if($t!==false && $t>=time()-172800)$trusted=true; // 48h grace for checker transport failures
    }
    if($trusted){$trustedCams[$slug]=true;$trustedCount++;}
}

// Build a compact current surf snapshot for every beach from the data-refresh
// cache. This adds zero external API calls to a visitor request.
$surfData=[];
foreach(($locations??[]) as $loc){
    $slug=(string)$loc['slug'];
    $w=cc_cache_get('weather:'.$slug);
    $m=cc_cache_get('marine:'.$slug);
    $wc=is_array($w)?($w['current']??[]):[];
    $mc=is_array($m)?($m['current']??[]):[];
    $wh=isset($mc['wave_height'])&&$mc['wave_height']!==null?(float)$mc['wave_height']:null;
    $wp=isset($mc['wave_period'])&&$mc['wave_period']!==null?(float)$mc['wave_period']:null;
    $sh=isset($mc['swell_wave_height'])&&$mc['swell_wave_height']!==null?(float)$mc['swell_wave_height']:null;
    $sp=isset($mc['swell_wave_period'])&&$mc['swell_wave_period']!==null?(float)$mc['swell_wave_period']:null;
    $sd=isset($mc['swell_wave_direction'])&&$mc['swell_wave_direction']!==null?(float)$mc['swell_wave_direction']:null;
    $ws=isset($wc['wind_speed_10m'])&&$wc['wind_speed_10m']!==null?(float)$wc['wind_speed_10m']:null;
    $wd=isset($wc['wind_direction_10m'])&&$wc['wind_direction_10m']!==null?(float)$wc['wind_direction_10m']:null;
    $power=($wh!==null&&$wp!==null)?0.49*$wh*$wh*$wp:null; // deep-water wave-power proxy, kW/m

    $score=0;
    if($wp!==null){$score += $wp>=12?35:($wp>=10?30:($wp>=8?20:10));}
    if($wh!==null){$score += ($wh>=0.6&&$wh<=2.5)?25:(($wh>=0.35&&$wh<=3.0)?15:7);}
    if($ws!==null){$score += $ws<=10?25:($ws<=18?18:($ws<=28?8:0));}
    if($wd!==null){
        if($wd>=225&&$wd<=315)$score+=15; // broadly offshore for this east-facing coastline
        elseif(($wd>=180&&$wd<225)||$wd>315||$wd<45)$score+=10;
        elseif($wd>135&&$wd<180)$score+=6;
    }
    $quality=($wh===null||$wp===null)?'unknown':($score>=70?'good':($score>=48?'fair':'poor'));
    $surfData[]=[
        'slug'=>$slug,'name'=>(string)$loc['name'],'region'=>(string)$loc['region'],
        'lat'=>(float)$loc['lat'],'lon'=>(float)$loc['lon'],'quality'=>$quality,'score'=>$score,
        'wave_height'=>$wh,'wave_period'=>$wp,'swell_height'=>$sh,'swell_period'=>$sp,
        'swell_direction'=>$sd,'swell_compass'=>$sd===null?'—':cc_compass($sd),
        'wind_speed'=>$ws,'wind_direction'=>$wd,'wind_compass'=>$wd===null?'—':cc_compass($wd),
        'energy_kwm'=>$power,
    ];
}

$trustedJson=json_encode($trustedCams,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?:'{}';
$surfJson=json_encode($surfData,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?:'[]';

// Camera pins: only actual feeds considered live by the resilient check above.
$html=str_replace(
    "const visible=cams.filter(c=>region==='all'||c.region===region);",
    "const visible=cams.filter(c=>ccMapCameraIsLive(c)&&(region==='all'||c.region===region));",
    $html
);
$html=str_replace('<span><i class="dot down"></i>Camera temporarily unavailable</span>','',$html);
$html=str_replace('<span><i class="dot forecast"></i>Forecast / no verified camera</span>','',$html);
$html=preg_replace('~<div class="stats">(\d+) locations · \d+ verified live feeds</div>~','<div class="stats">$1 locations · '.$trustedCount.' live cameras</div>',$html,1)??$html;
$html=str_replace('Explore every CostaCams location from The Spit to Byron Bay. Camera pins open a live preview. Shark pins show the latest verified notification stored for each beach or locality.','Explore live cameras plus current surf conditions from The Spit to Byron Bay. Camera pins are only shown for live feeds; every beach also has a compact surf-conditions marker.',$html);
$html=str_replace('Explore verified camera locations from The Spit to Byron Bay. Camera pins appear only where CostaCams has an actual stream. Shark activity is shown separately by coastal zone.','Explore live cameras plus current surf conditions from The Spit to Byron Bay. Camera pins are only shown for live feeds; every beach also has a compact surf-conditions marker.',$html);
$html=str_replace('Explore currently verified live camera locations from The Spit to Byron Bay. A camera pin appears only while that feed is passing the automatic health check. Shark activity is shown separately by coastal zone.','Explore live cameras plus current surf conditions from The Spit to Byron Bay. Camera pins are only shown for live feeds; every beach also has a compact surf-conditions marker.',$html);

// Shark radius is context only. It never captures clicks; only the tiny shark on
// the east edge opens the shark report.
$html=str_replace('fillOpacity:.16,interactive:true','fillOpacity:.10,interactive:false',$html);
$html=str_replace("iconSize:[22,22],iconAnchor:[11,11]","iconSize:[18,18],iconAnchor:[9,9]",$html);

$mapCss=<<<'HTML'
<style id="cc-map-surf-style">
.shark-edge{width:18px!important;height:18px!important;font-size:10px!important;border-width:1px!important;box-shadow:0 1px 4px #0008!important}
.surf-marker{background:transparent!important;border:0!important}.surf-badge{width:94px;min-height:40px;border:1px solid #465157;border-radius:9px;background:#101416e8;color:#edf3f5;padding:4px 6px;font-size:9px;line-height:1.2;box-shadow:0 2px 7px #0009;backdrop-filter:blur(4px)}.surf-badge.good{border-color:#3fd08a;background:#0b2118e8}.surf-badge.fair{border-color:#e5a942;background:#241b0ce8}.surf-badge.poor{border-color:#727d83;background:#171b1de8}.surf-badge.unknown{border-color:#4d565b;color:#aab4b8}.surf-top{display:flex;align-items:center;justify-content:space-between;gap:4px;font-weight:900}.surf-q{font-size:8px;letter-spacing:.04em}.surf-line{white-space:nowrap;margin-top:2px;color:#c7d1d5}.surf-arrows{display:flex;gap:7px;margin-top:2px;color:#9fb0b6}.surf-arrow{display:inline-block;font-size:12px;line-height:10px;transform-origin:50% 50%}.surf-pop-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin:8px 0}.surf-pop-grid div{padding:7px;background:#111618;border:1px solid #283135;border-radius:8px}.surf-pop-grid small{display:block;color:#8f9da2}.surf-pop-grid b{font-size:13px}.surf-good{color:#69e0a0}.surf-fair{color:#ffbf69}.surf-poor{color:#adb7bb}
</style>
HTML;
$html=str_replace('</head>',$mapCss.'</head>',$html);

// HLS.js is unnecessary for initial map render; load it only on a camera click.
$html=str_replace('<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>','',$html);

$inject=<<<HTML
const ccTrustedCams={$trustedJson};
const surfSpots={$surfJson};
ccCameraState=function(cam){if(!cam||!cam.live)return 'forecast';return ccTrustedCams[cam.slug]?'live':'down';};
function ccMapCameraIsLive(cam){return !!(cam&&cam.live&&ccTrustedCams[cam.slug]);}
let surfLayer=L.layerGroup().addTo(map);
function ccNum(v,d=1){return v===null||v===undefined?'—':Number(v).toFixed(d);}
function ccSurfColour(q){return q==='good'?'#3fd08a':(q==='fair'?'#e5a942':(q==='poor'?'#727d83':'#4d565b'));}
function ccSurfArrow(deg){return deg===null||deg===undefined?'':`<span class="surf-arrow" style="transform:rotate(\${Number(deg)}deg)">↓</span>`;}
function ccSurfIcon(s){
 const q=(s.quality||'unknown');const energy=s.energy_kwm===null?'—':Number(s.energy_kwm).toFixed(1);
 const top=`<div class="surf-top"><span>🌊 \${ccNum(s.wave_height)}m · \${ccNum(s.wave_period,0)}s</span><span class="surf-q">\${q.toUpperCase()}</span></div>`;
 const line=`<div class="surf-line">⚡ \${energy} kW/m · 💨 \${ccNum(s.wind_speed,0)}</div>`;
 const arrows=`<div class="surf-arrows"><span>S \${esc(s.swell_compass)} \${ccSurfArrow(s.swell_direction)}</span><span>W \${esc(s.wind_compass)} \${ccSurfArrow(s.wind_direction)}</span></div>`;
 return L.divIcon({className:'surf-marker',html:`<div class="surf-badge \${q}">\${top}\${line}\${arrows}</div>`,iconSize:[94,44],iconAnchor:[106,22],popupAnchor:[-54,-19]});
}
function ccSurfPopup(s){
 const q=s.quality||'unknown',cls=q==='good'?'surf-good':(q==='fair'?'surf-fair':'surf-poor');
 return `<div class="pop"><div class="tag">SURF CONDITIONS · \${esc(s.region)}</div><h3>\${esc(s.name)}</h3><p class="\${cls}"><b>\${q.toUpperCase()} · surf score \${s.score}/100</b></p><div class="surf-pop-grid"><div><small>Wave</small><b>\${ccNum(s.wave_height)} m · \${ccNum(s.wave_period,0)} s</b></div><div><small>Energy</small><b>\${s.energy_kwm===null?'—':Number(s.energy_kwm).toFixed(1)} kW/m</b></div><div><small>Swell</small><b>\${ccNum(s.swell_height)} m · \${ccNum(s.swell_period,0)} s · \${esc(s.swell_compass)}</b></div><div><small>Wind</small><b>\${ccNum(s.wind_speed,0)} km/h · \${esc(s.wind_compass)}</b></div></div><p style="font-size:11px;color:#8f9da2">Arrows show where swell and wind are coming from. The Good/Fair/Poor colour is a simple CostaCams heuristic using wave period, size and wind; local banks, tide and shelter can change actual surf quality.</p><div class="pop-actions"><a class="btn primary" href="/\${encodeURIComponent(s.slug)}">Full conditions</a></div></div>`;
}
function addSurf(region='all'){
 surfLayer.clearLayers();
 surfSpots.filter(s=>region==='all'||s.region===region).forEach(s=>L.marker([s.lat,s.lon],{icon:ccSurfIcon(s),zIndexOffset:-150}).bindPopup(ccSurfPopup(s),{maxWidth:330}).addTo(surfLayer));
}
HTML;
$html=str_replace('addMarkers();',$inject."\naddMarkers();",$html);
$html=str_replace('map.addLayer(cluster);addSharks(region);if(visible.length){const bounds=L.latLngBounds(visible.map(c=>[c.lat,c.lon]));map.fitBounds(bounds.pad(.08),{maxZoom:12});}',"map.addLayer(cluster);addSharks(region);addSurf(region);const fit=surfSpots.filter(s=>region==='all'||s.region===region);if(fit.length){const bounds=L.latLngBounds(fit.map(s=>[s.lat,s.lon]));map.fitBounds(bounds.pad(.08),{maxZoom:12});}",$html);
$html=str_replace('<span><i class="dot live"></i>Verified live camera</span>','<span><i class="dot live"></i>Live camera</span><span><i class="dot" style="background:#3fd08a"></i>Good surf</span><span><i class="dot" style="background:#e5a942"></i>Fair surf</span>',$html);

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
    s.onerror=()=>{loading=false;queued.length=0;};document.head.appendChild(s);
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
