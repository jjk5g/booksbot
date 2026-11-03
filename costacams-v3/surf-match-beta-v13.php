<?php
declare(strict_types=1);

/*
 * Surf Match v13 CLEAN
 * Intentionally starts from v5 instead of stacking v6-v12 wrappers.
 * Keeps one source of truth for postcode, drive radius, beach selection,
 * route/camera, Waze, weather and CO2.
 */
ob_start();
require __DIR__.'/surf-match-beta-v5.php';
$html=ob_get_clean();
$html=str_replace('Surf Match Beta v5 | CostaCams','Surf Match Beta v13 | CostaCams',$html);
$html=str_replace('SURF MATCH BETA v5','SURF MATCH BETA v13',$html);

$css=<<<'HTML'
<style id="cc-surf-v13-style">
.ccv13-drive{border-top:1px solid #2a4851!important;margin-top:13px!important;padding-top:13px!important}
.ccv13-drive-top{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:7px}.ccv13-drive-top b{font-size:12px}.ccv13-drive-top strong{font-size:14px;color:var(--aqua)}
.ccv13-drive-grid{display:grid;grid-template-columns:126px 1fr;gap:9px;align-items:center}.ccv13-scale{display:flex;justify-content:space-between;gap:8px;color:#71858b;font-size:9px;margin-top:3px}.ccv13-radius-status{margin-top:8px;padding:8px 9px;border:1px solid #29434b;border-radius:10px;background:#081216;color:#a9c1c7;font-size:10px;line-height:1.4}.ccv13-radius-status.ok{border-color:#315c4b;color:#bce7cc}.ccv13-radius-status.warn{border-color:#604d2d;color:#ffd08a;background:#181207}
.ccv13-beaches{margin-top:14px;border:1px solid var(--line);border-radius:16px;background:#080d0f;overflow:hidden}.ccv13-beaches-head{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:10px 12px;border-bottom:1px solid #20282b}.ccv13-beaches-head b{font-size:12px}.ccv13-beaches-head span{font-size:9px;color:#8fa2a8}.ccv13-rail{display:flex;gap:8px;overflow-x:auto;padding:10px 12px 12px;scroll-snap-type:x proximity}.ccv13-card{scroll-snap-align:start;min-width:158px;max-width:158px;border:1px solid #283438;background:#0b1113;color:#dce9ec;border-radius:12px;padding:10px;text-align:left;cursor:pointer}.ccv13-card:hover,.ccv13-card.active{border-color:#50dcff;background:#0b2026}.ccv13-card .row{display:flex;justify-content:space-between;gap:6px;align-items:flex-start}.ccv13-card b{font-size:11px;line-height:1.15}.ccv13-card strong{font-size:18px;color:#eafcff}.ccv13-card small{display:block;color:#8fa2a8;font-size:9px;line-height:1.35;margin-top:5px}.ccv13-chip{display:inline-block;margin-top:6px;padding:3px 6px;border:1px solid #315c4b;border-radius:999px;color:#aee1c0;font-size:8px}.ccv13-chip.out{border-color:#5a4430;color:#d4ad7b}.ccv13-chip.noloc{border-color:#34464d;color:#9fb1b7}
.ccv13-impact{margin-top:12px;border:1px solid #31505a;background:linear-gradient(145deg,#08181c,#091013);border-radius:16px;padding:13px}.ccv13-impact-head{display:flex;justify-content:space-between;gap:10px}.ccv13-impact-head b{font-size:12px}.ccv13-impact-head span{font-size:9px;color:#8fa2a8}.ccv13-impact-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px;margin-top:9px}.ccv13-impact-item{border:1px solid #29434b;border-radius:10px;background:#0b171b;padding:8px}.ccv13-impact-item strong{display:block;font-size:15px;color:#eafcff}.ccv13-impact-item span{display:block;font-size:9px;line-height:1.35;color:#9fb2b8;margin-top:2px}.ccv13-impact-note{margin-top:7px;color:#82979d;font-size:9px;line-height:1.4}
.ccv13-extra{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px}.ccv13-mapbox{border:1px solid var(--line);border-radius:16px;overflow:hidden;background:#090c0d;min-width:0}.ccv13-boxhead{min-height:45px;padding:9px 12px;border-bottom:1px solid #20282b;display:flex;justify-content:space-between;gap:10px;align-items:center}.ccv13-boxhead span{display:grid;gap:1px;font-size:11px;font-weight:850;color:#cdd8dc}.ccv13-boxhead small{font-size:9px;font-weight:500;color:#8fa2a8}.ccv13-boxhead a{font-size:9px;color:#8feaff;text-decoration:none;white-space:nowrap}.ccv13-mapbody{height:270px;background:#050606;position:relative}.ccv13-mapbody iframe{width:100%;height:100%;border:0;display:block;background:#050606}.ccv13-lazy{position:absolute;inset:0;display:grid;place-items:center;color:#75898f;font-size:10px;background:#070a0b}.ccv13-weather-actions{display:flex;gap:6px;padding:8px 10px;border-top:1px solid #20282b;background:#080c0d}.ccv13-weather-actions button{border:1px solid #334044;background:#0a0e0f;color:#c7d5d9;border-radius:8px;padding:6px 8px;font-size:9px;cursor:pointer}.ccv13-weather-actions button.active{border-color:#50dcff;color:#d5f9ff;background:#0c2025}
@media(max-width:720px){.ccv13-drive-grid{grid-template-columns:110px 1fr}.ccv13-impact-grid{grid-template-columns:1fr 1fr}.ccv13-extra{grid-template-columns:1fr}.ccv13-mapbody{height:260px}}
@media(max-width:430px){.ccv13-impact-grid{grid-template-columns:1fr}.ccv13-card{min-width:148px;max-width:148px}}
</style>
HTML;
$html=str_replace('</head>',$css.'</head>',$html);

$js=<<<'HTML'
<script id="cc-surf-v13-js">
(function(){
  const $13=id=>document.getElementById(id);
  const coreCalculate=calculate;
  let manualSlug=null;
  let weatherLayer='radar';
  let panelsLoaded=false;
  let pendingRow=null;
  let radiusTimer=null;

  profile.drive_limit_mode=profile.drive_limit_mode||'time';
  profile.drive_limit_value=Number(profile.drive_limit_value||45);

  function kmBetween(lat1,lon1,lat2,lon2){
    const R=6371,r=x=>Number(x)*Math.PI/180;
    const a1=r(lat1),a2=r(lat2),dlat=a2-a1,dlon=r(Number(lon2)-Number(lon1));
    const a=Math.sin(dlat/2)**2+Math.cos(a1)*Math.cos(a2)*Math.sin(dlon/2)**2;
    return 2*R*Math.asin(Math.min(1,Math.sqrt(a)));
  }
  function approxRoadKm(s){
    if(profile.origin_lat==null||profile.origin_lon==null||!s)return null;
    return kmBetween(profile.origin_lat,profile.origin_lon,s.lat,s.lon)*1.22;
  }
  function travelFor(row){
    if(row&&row.travel!=null&&Number.isFinite(Number(row.travel)))return Number(row.travel);
    try{const t=travelMin(row.s);if(t!=null&&Number.isFinite(Number(t)))return Number(t);}catch(e){}
    const km=approxRoadKm(row.s);return km==null?null:Math.max(1,Math.round(km/45*60));
  }
  function allRows(epoch=selectedEpoch()){
    try{return coreCalculate(epoch).slice();}catch(e){return [];}
  }
  function inside(row){
    if(profile.origin_lat==null||profile.origin_lon==null)return true;
    const lim=Number(profile.drive_limit_value||45);
    if(profile.drive_limit_mode==='distance'){
      const km=approxRoadKm(row.s);return km==null?true:km<=lim;
    }
    const mins=travelFor(row);return mins==null?true:mins<=lim;
  }
  function nearestRow(rows){
    if(!rows.length)return null;
    return rows.slice().sort((a,b)=>{
      if(profile.drive_limit_mode==='distance')return (approxRoadKm(a.s)||9999)-(approxRoadKm(b.s)||9999);
      return (travelFor(a)||9999)-(travelFor(b)||9999);
    })[0];
  }

  // One recommendation rule: best score inside selected drive radius.
  calculate=function(epoch=selectedEpoch()){
    const rows=coreCalculate(epoch);
    if(profile.origin_lat==null||profile.origin_lon==null)return rows;
    const ok=rows.filter(inside);
    if(ok.length)return ok;
    const near=nearestRow(rows);return near?[near]:rows;
  };

  function ensureDrive(){
    if($13('ccv13Drive'))return;
    const lc=document.querySelector('.location-card');
    const anchor=lc?lc.closest('.group'):null;if(!anchor)return;
    const box=document.createElement('div');box.id='ccv13Drive';box.className='group ccv13-drive';
    box.innerHTML=`<div class="ccv13-drive-top"><b>How far are you willing to drive?</b><strong id="ccv13DriveOut">45 min</strong></div><div class="ccv13-drive-grid"><select class="select" id="ccv13DriveMode"><option value="time">Drive time</option><option value="distance">Distance</option></select><input class="range" id="ccv13DriveRange" type="range" min="10" max="90" step="5" value="45"></div><div class="ccv13-scale"><span id="ccv13Min">10 min</span><span>best beach is chosen inside this limit</span><span id="ccv13Max">90 min</span></div><div id="ccv13RadiusStatus" class="ccv13-radius-status">Enter a postcode/location to apply the drive radius.</div>`;
    anchor.insertAdjacentElement('afterend',box);
    const mode=$13('ccv13DriveMode'),range=$13('ccv13DriveRange');
    mode.value=profile.drive_limit_mode==='distance'?'distance':'time';
    function syncControl(reset){
      if(mode.value==='distance'){
        range.min='5';range.max='120';range.step='5';if(reset)range.value='50';
        $13('ccv13Min').textContent='5 km';$13('ccv13Max').textContent='120 km';$13('ccv13DriveOut').textContent=range.value+' km';
      }else{
        range.min='10';range.max='90';range.step='5';if(reset)range.value='45';
        $13('ccv13Min').textContent='10 min';$13('ccv13Max').textContent='90 min';$13('ccv13DriveOut').textContent=range.value+' min';
      }
      profile.drive_limit_mode=mode.value;profile.drive_limit_value=Number(range.value);
    }
    if(profile.drive_limit_mode==='distance'){range.value=Math.min(120,Math.max(5,profile.drive_limit_value));}else{range.value=Math.min(90,Math.max(10,profile.drive_limit_value));}
    syncControl(false);
    mode.addEventListener('change',()=>{syncControl(true);manualSlug=null;refresh();});
    range.addEventListener('input',()=>{
      syncControl(false);manualSlug=null;clearTimeout(radiusTimer);radiusTimer=setTimeout(()=>refresh(),140);
    });
    range.addEventListener('change',()=>{syncControl(false);manualSlug=null;clearTimeout(radiusTimer);refresh();});
  }

  function rowTravelLabel(row){
    const mins=travelFor(row),km=approxRoadKm(row.s);
    if(profile.origin_lat==null||profile.origin_lon==null)return 'set postcode';
    if(profile.drive_limit_mode==='distance'&&km!=null)return '~'+Math.round(km)+' km';
    if(mins!=null)return '~'+Math.round(mins)+' min';
    return 'travel unavailable';
  }
  function ensureBeachBar(){
    if($13('ccv13Beaches'))return;
    const detail=document.querySelector('.spot-detail');if(!detail)return;
    const box=document.createElement('div');box.id='ccv13Beaches';box.className='ccv13-beaches';
    box.innerHTML='<div class="ccv13-beaches-head"><b>All beaches</b><span>Nothing is hidden — click any beach to preview it</span></div><div class="ccv13-rail" id="ccv13Rail"></div>';
    detail.parentNode.insertBefore(box,detail);
  }
  function renderBeachBar(rows,activeSlug){
    ensureBeachBar();const rail=$13('ccv13Rail');if(!rail)return;
    rail.innerHTML=rows.map(row=>{
      const isIn=inside(row),hasLoc=profile.origin_lat!=null&&profile.origin_lon!=null;
      const chip=!hasLoc?'<span class="ccv13-chip noloc">set location</span>':(isIn?'<span class="ccv13-chip">inside drive limit</span>':'<span class="ccv13-chip out">outside drive limit</span>');
      return `<button type="button" class="ccv13-card ${row.s.slug===activeSlug?'active':''}" data-v13slug="${row.s.slug}"><div class="row"><b>${row.s.name}</b><strong>${row.score}%</strong></div><small>${rowTravelLabel(row)}${row.s.camera?' · 📹 camera':' · no camera'}</small>${chip}</button>`;
    }).join('');
    rail.querySelectorAll('[data-v13slug]').forEach(btn=>btn.onclick=()=>{
      const row=allRows().find(x=>x.s.slug===btn.dataset.v13slug);if(row)previewRow(row,true);
    });
  }

  function ensureImpact(){
    if($13('ccv13Impact'))return;
    const best=document.querySelector('.best');if(!best)return;
    const box=document.createElement('div');box.id='ccv13Impact';box.className='ccv13-impact';best.insertAdjacentElement('afterend',box);
  }
  function updateImpact(row){
    ensureImpact();const box=$13('ccv13Impact');if(!box)return;
    if(!row||profile.origin_lat==null||profile.origin_lon==null){
      box.innerHTML='<div class="ccv13-impact-head"><b>🌍 Driving CO₂</b><span>set postcode/location</span></div><div class="ccv13-impact-grid"><div class="ccv13-impact-item"><strong>7.6 kg CO₂</strong><span>example for a 40 km return drive</span></div><div class="ccv13-impact-item"><strong>⛽ 3.3 L</strong><span>rough petrol-equivalent comparison</span></div><div class="ccv13-impact-item"><strong>↔ 40 km</strong><span>example return trip</span></div><div class="ccv13-impact-item"><strong>📍 Set location</strong><span>then this becomes your selected beach</span></div></div><div class="ccv13-impact-note">Immediate estimate only, so it does not add another route request and slow the page down.</div>';
      return;
    }
    const one=approxRoadKm(row.s);if(one==null)return;
    const round=one*2,co2=round*0.191,petrol=co2/2.348,treePct=(co2/60)*100;
    box.innerHTML=`<div class="ccv13-impact-head"><b>🌍 Driving CO₂ to ${row.s.name}</b><span>fast estimate · ${round.toFixed(1)} km return</span></div><div class="ccv13-impact-grid"><div class="ccv13-impact-item"><strong>${co2.toFixed(1)} kg CO₂</strong><span>estimated return-drive emissions</span></div><div class="ccv13-impact-item"><strong>⛽ ${petrol.toFixed(1)} L</strong><span>rough petrol-equivalent comparison</span></div><div class="ccv13-impact-item"><strong>🌳 ${treePct.toFixed(1)}%</strong><span>of a rough 10-year tree absorption comparison</span></div><div class="ccv13-impact-item"><strong>${rowTravelLabel(row)}</strong><span>current drive-limit travel estimate</span></div></div><div class="ccv13-impact-note">Actual vehicle emissions vary by vehicle, fuel, traffic and driving style.</div>`;
  }

  function ensureExtras(){
    if($13('ccv13Extra'))return;
    const detail=document.querySelector('.spot-detail');if(!detail)return;
    const row=document.createElement('div');row.id='ccv13Extra';row.className='ccv13-extra';
    row.innerHTML=`<div class="ccv13-mapbox"><div class="ccv13-boxhead"><span>Waze traffic<small>selected beach / driving corridor</small></span><a id="ccv13WazeOpen" href="#" target="_blank" rel="noopener">Open Waze ↗</a></div><div class="ccv13-mapbody" id="ccv13WazeBody"><div class="ccv13-lazy">Traffic map loads when this section is near your screen</div></div></div><div class="ccv13-mapbox"><div class="ccv13-boxhead"><span>Weather around selected beach<small>Rain radar or cloud/satellite view</small></span><a id="ccv13WindyOpen" href="https://www.windy.com" target="_blank" rel="noopener">Open Windy ↗</a></div><div class="ccv13-mapbody" id="ccv13WeatherBody"><div class="ccv13-lazy">Weather map loads when this section is near your screen</div></div><div class="ccv13-weather-actions"><button type="button" class="active" id="ccv13RadarBtn">Rain radar</button><button type="button" id="ccv13CloudBtn">Clouds / satellite</button></div></div>`;
    detail.insertAdjacentElement('afterend',row);
    $13('ccv13RadarBtn').onclick=()=>{weatherLayer='radar';$13('ccv13RadarBtn').classList.add('active');$13('ccv13CloudBtn').classList.remove('active');if(panelsLoaded&&pendingRow)loadWeather(pendingRow,true);};
    $13('ccv13CloudBtn').onclick=()=>{weatherLayer='satellite';$13('ccv13CloudBtn').classList.add('active');$13('ccv13RadarBtn').classList.remove('active');if(panelsLoaded&&pendingRow)loadWeather(pendingRow,true);};
    const activate=()=>{if(panelsLoaded)return;panelsLoaded=true;if(pendingRow){loadWaze(pendingRow,true);loadWeather(pendingRow,true);}};
    if('IntersectionObserver' in window){const io=new IntersectionObserver(entries=>{if(entries.some(e=>e.isIntersecting||e.intersectionRatio>0)){activate();io.disconnect();}},{rootMargin:'500px'});io.observe(row);}else setTimeout(activate,700);
  }

  function wazeSrc(row){
    const dLat=Number(row.s.lat),dLon=Number(row.s.lon);let lat=dLat,lon=dLon,zoom=12;
    if(profile.origin_lat!=null&&profile.origin_lon!=null){const oLat=Number(profile.origin_lat),oLon=Number(profile.origin_lon),d=kmBetween(oLat,oLon,dLat,dLon);lat=(oLat+dLat)/2;lon=(oLon+dLon)/2;zoom=d<8?12:(d<18?11:(d<40?10:9));}
    return `https://embed.waze.com/iframe?zoom=${zoom}&lat=${lat.toFixed(5)}&lon=${lon.toFixed(5)}&pin=1&desc=1`;
  }
  function loadWaze(row,force){
    const body=$13('ccv13WazeBody');if(!body||!row)return;
    const src=wazeSrc(row);let f=$13('ccv13WazeFrame');
    if(!f){body.innerHTML='<iframe id="ccv13WazeFrame" title="Waze traffic" loading="lazy"></iframe>';f=$13('ccv13WazeFrame');}
    if(force||f.dataset.src!==src){f.src=src;f.dataset.src=src;}
    const a=$13('ccv13WazeOpen'),lat=Number(row.s.lat),lon=Number(row.s.lon);if(a)a.href=`https://www.waze.com/ul?ll=${lat.toFixed(5)}%2C${lon.toFixed(5)}&navigate=yes&utm_source=costacams`;
  }
  function weatherSrc(row){
    const lat=Number(row.s.lat).toFixed(4),lon=Number(row.s.lon).toFixed(4),overlay=weatherLayer,product=weatherLayer==='radar'?'radar':'satellite';
    return `https://embed.windy.com/embed.html?type=map&location=coordinates&metricRain=default&metricTemp=default&metricWind=default&zoom=8&overlay=${overlay}&product=${product}&level=surface&lat=${lat}&lon=${lon}`;
  }
  function loadWeather(row,force){
    const body=$13('ccv13WeatherBody');if(!body||!row)return;
    const src=weatherSrc(row);let f=$13('ccv13WeatherFrame');
    if(!f){body.innerHTML='<iframe id="ccv13WeatherFrame" title="Weather around selected beach" loading="lazy"></iframe>';f=$13('ccv13WeatherFrame');}
    if(force||f.dataset.src!==src){f.src=src;f.dataset.src=src;}
    const a=$13('ccv13WindyOpen');if(a)a.href=`https://www.windy.com/?${weatherLayer},${Number(row.s.lat).toFixed(4)},${Number(row.s.lon).toFixed(4)},8`;
  }

  function currentRow(){
    const rows=allRows(),name=($13('winnerName')?.textContent||'').trim();
    return rows.find(r=>r.s.slug===manualSlug)||rows.find(r=>r.s.name===name)||rows[0]||null;
  }
  function updateStatus(rows,row){
    const s=$13('ccv13RadiusStatus');if(!s)return;
    if(profile.origin_lat==null||profile.origin_lon==null){s.className='ccv13-radius-status';s.innerHTML='Enter a postcode/location to choose the best beach inside your drive radius.';return;}
    const ok=rows.filter(inside),lim=Number(profile.drive_limit_value||45),unit=profile.drive_limit_mode==='distance'?'km':'min';
    if(ok.length){s.className='ccv13-radius-status ok';s.innerHTML=`<b>${row?row.s.name:ok[0].s.name}</b> · best match inside <b>${lim} ${unit}</b>. All other beaches remain visible below.`;}
    else{const near=nearestRow(rows);s.className='ccv13-radius-status warn';s.innerHTML=`No beach is inside <b>${lim} ${unit}</b>. Showing nearest available beach <b>${near?near.s.name:'—'}</b>, while all beaches stay visible below.`;}
  }

  function syncExtras(){
    ensureDrive();ensureBeachBar();ensureImpact();ensureExtras();
    const rows=allRows(),row=currentRow();if(!rows.length||!row)return;
    pendingRow=row;renderBeachBar(rows,row.s.slug);updateStatus(rows,row);updateImpact(row);
    if(panelsLoaded){loadWaze(row,false);loadWeather(row,false);}else{
      const a=$13('ccv13WazeOpen');if(a)a.href=`https://www.waze.com/ul?ll=${Number(row.s.lat).toFixed(5)}%2C${Number(row.s.lon).toFixed(5)}&navigate=yes&utm_source=costacams`;
    }
  }

  function previewRow(row,scroll){
    manualSlug=row.s.slug;
    const rows=allRows();
    try{ranked=[row,...rows.filter(r=>r.s.slug!==row.s.slug)];render();}catch(e){
      try{renderTravel(row);renderMap(row);renderCamera(row);}catch(_e){}
    }
    syncExtras();
    if(scroll){const cam=$13('camPreview');if(cam)cam.scrollIntoView({behavior:'smooth',block:'center'});}
  }

  // Enter in postcode = click Use postcode. This is native in v13, not a later patch.
  const postcode=$13('postcode');
  if(postcode)postcode.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();manualSlug=null;const b=$13('usePostcode');if(b)b.click();}});
  const pcBtn=$13('usePostcode');if(pcBtn)pcBtn.addEventListener('click',()=>{manualSlug=null;});
  const locBtn=$13('useLocation');if(locBtn)locBtn.addEventListener('click',()=>{manualSlug=null;});

  // One refresh hook only. Older v6-v12 wrappers are not part of this page.
  const coreRefresh=refresh;
  refresh=function(){coreRefresh();setTimeout(syncExtras,0);};

  ensureDrive();ensureBeachBar();ensureImpact();ensureExtras();
  setTimeout(syncExtras,80);
  setTimeout(syncExtras,450);
})();
</script>
HTML;
$html=str_replace('</body>',$js.'</body>',$html);

echo $html;
