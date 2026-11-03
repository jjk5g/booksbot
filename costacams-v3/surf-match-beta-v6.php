<?php
declare(strict_types=1);
ob_start();
require __DIR__.'/surf-match-beta-v5.php';
$html=ob_get_clean();
$html=str_replace('Surf Match Beta v5 | CostaCams','Surf Match Beta v6 | CostaCams',$html);
$html=str_replace('SURF MATCH BETA v5','SURF MATCH BETA v6',$html);
$html=str_replace('surf-beta-v5-api.php','surf-beta-v6-api.php',$html);
// v4 owns the sample/subscribe/manage click handlers. Point those handlers at the
// dedicated v6 PHP mail endpoint instead of any external mail API.
$html=str_replace("const api4='surf-beta-v4-api.php'","const api4='surf-beta-v6-mail.php'",$html);
$html=str_replace('Emails are sent from the authenticated CostaCams mailbox to the address you enter.','Emails are sent by the CostaCams website PHP mailer from support@costacams.com.',$html);
// New / legacy profiles default to the practical after-work interpretation.
$html=str_replace("profile.time_mode=profile.time_mode==='arrive'?'in_water':'finish_work';","profile.time_mode='finish_work';",$html);
$html=str_replace('Try a real sample email first, or subscribe and save this profile.','Try a real sample email first, or subscribe and save this profile. Emails are sent by the website PHP mailer from support@costacams.com.',$html);

// Make the page usable immediately instead of blocking on the road-routing service.
$html=str_replace("$('routeSource').textContent='Calculating road drive times to the beaches…';","$('routeSource').textContent='Showing instant drive estimates. Refining nearby road times in the background…';refresh();",$html);
$html=str_replace("if(profile.origin_lat!=null&&profile.origin_lon!=null)setTimeout(loadV5RoadTimes,150);else setTimeout(()=>{refresh();updateScheduleHelp();},50);","if(profile.origin_lat!=null&&profile.origin_lon!=null)setTimeout(()=>{refresh();updateScheduleHelp();loadV5RoadTimes();},40);else setTimeout(()=>{refresh();updateScheduleHelp();},40);",$html);
$html=str_replace("await loadV5RoadTimes();","refresh();loadV5RoadTimes();",$html);

// Route only nearby candidates, not every beach on the site.
$old="const destinations=spots.map(s=>({slug:s.slug,lat:s.lat,lon:s.lon}));const j=await post5({action:'route_table',origin_lat:profile.origin_lat,origin_lon:profile.origin_lon,destinations:JSON.stringify(destinations)});";
$new="let candidates=spots.slice();const mode=profile.drive_limit_mode||'time';const lim=Number(profile.drive_limit_value||45);candidates=candidates.filter(s=>{const mins=priorTravelMin(s);const km=(window.ccApproxKm?window.ccApproxKm(s):null);return mode==='distance'?(km==null||km<=lim):(mins==null||mins<=lim);}).sort((a,b)=>(priorTravelMin(a)||999)-(priorTravelMin(b)||999)).slice(0,12);const destinations=candidates.map(s=>({slug:s.slug,lat:s.lat,lon:s.lon}));if(!destinations.length){v5RoadTimes={};refresh();return;}const j=await post5({action:'route_table',origin_lat:profile.origin_lat,origin_lon:profile.origin_lon,destinations:JSON.stringify(destinations)});";
$html=str_replace($old,$new,$html);

$driveBlock=<<<'HTML'
<div class="group ccv6-drive-limit">
  <div class="labelrow"><b>How far do you want to drive?</b><span class="value" id="driveLimitOut">45 min</span></div>
  <div class="ccv6-limit-row">
    <select class="select" id="driveLimitMode" style="width:120px"><option value="time" selected>Drive time</option><option value="distance">Distance</option></select>
    <input class="range" id="driveLimitValue" type="range" min="10" max="90" step="5" value="45">
  </div>
  <div class="simple-note">Only beaches inside this drive limit are ranked. CostaCams shows a fast estimate first, then refines nearby road times in the background.</div>
</div>
HTML;
$html=str_replace('<div class="group ccv5-schedule">',$driveBlock.'<div class="group ccv5-schedule">',$html);

$railBlock=<<<'HTML'
<div class="ccv6-beach-browser">
  <div class="ccv6-browser-head"><div><b>All beach matches</b><span>Click any beach to preview its camera, map, trip and impact.</span></div><small>Match % is for your selected profile and surf time.</small></div>
  <div class="ccv6-beach-rail" id="beachQualityRail"></div>
</div>
HTML;
$html=str_replace('<div class="spot-detail">',$railBlock.'<div class="spot-detail">',$html,1);

$trafficBlock=<<<'HTML'
<div class="ccv6-traffic">
  <div class="ccv6-browser-head"><div><b>Traffic before you leave</b><span id="trafficExplain">Live road conditions around the selected beach.</span></div><a id="wazeOpen" href="#" target="_blank" rel="noopener">Open route in Waze ↗</a></div>
  <div class="ccv6-traffic-grid">
    <iframe id="wazeTraffic" title="Waze live traffic map" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    <div class="ccv6-traffic-note"><b>Live traffic now</b><p>Waze's embedded Live Map shows real-time traffic. For a surf planned later, use this as a live check shortly before departure. Historical/predicted traffic by future departure time needs a licensed traffic-history API, so CostaCams does not invent that data.</p><div id="trafficDeparture" class="ccv6-traffic-departure"></div></div>
  </div>
</div>
HTML;
$html=str_replace('<div class="timeline">',$trafficBlock.'<div class="timeline">',$html,1);

$css=<<<'HTML'
<style id="cc-surf-v6-style">
.ccv6-limit-row{display:grid;grid-template-columns:120px 1fr;gap:10px;align-items:center}
.ccv6-carbon{margin-top:9px;padding:11px;border:1px solid #29434b;border-radius:12px;background:#081216;color:#c8e9f0;font-size:11px}.ccv6-carbon b{color:#fff}.ccv6-carbon small{display:block;color:#8ea2a8;margin-top:4px;line-height:1.45}.ccv6-impact-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;margin-top:8px}.ccv6-impact-stat{border:1px solid #29434b;border-radius:10px;background:#0b171b;padding:8px}.ccv6-impact-stat strong{font-size:16px;display:block;color:#eafcff}.ccv6-impact-stat span{font-size:10px;color:#9fb2b8}.ccv6-carbon a{color:#8feaff}
.ccv6-beach-browser,.ccv6-traffic{margin-top:14px;border:1px solid var(--line);border-radius:16px;background:#080d0f;overflow:hidden}.ccv6-browser-head{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:11px 12px;border-bottom:1px solid #20282b}.ccv6-browser-head div{display:grid;gap:2px}.ccv6-browser-head span,.ccv6-browser-head small{font-size:10px;color:#8fa2a8}.ccv6-browser-head a{font-size:10px;color:#8feaff;text-decoration:none;white-space:nowrap}.ccv6-beach-rail{display:flex;gap:8px;overflow-x:auto;padding:10px;scroll-snap-type:x proximity}.ccv6-beach-card{min-width:138px;max-width:138px;scroll-snap-align:start;border:1px solid #283438;background:#0b1113;color:#dce9ec;border-radius:12px;padding:9px;text-align:left;cursor:pointer}.ccv6-beach-card:hover,.ccv6-beach-card.active{border-color:#50dcff;background:#0b2026}.ccv6-beach-card.recommended{box-shadow:inset 0 0 0 1px #69e0a055}.ccv6-beach-card .topline{display:flex;justify-content:space-between;gap:5px;align-items:start}.ccv6-beach-card b{font-size:11px;line-height:1.15}.ccv6-beach-card .pct{font-size:20px;font-weight:950;line-height:1}.ccv6-beach-card small{display:block;color:#8fa2a8;font-size:9px;margin-top:4px}.ccv6-score-track{height:5px;background:#1a2529;border-radius:99px;overflow:hidden;margin-top:7px}.ccv6-score-fill{height:100%;background:linear-gradient(90deg,#ff7468,#ffbf69,#69e0a0);border-radius:99px}
.ccv6-traffic-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(210px,.5fr);min-height:280px}.ccv6-traffic iframe{width:100%;height:300px;border:0;background:#050606}.ccv6-traffic-note{padding:14px;border-left:1px solid #20282b;color:#b9c8cc}.ccv6-traffic-note p{font-size:11px;color:#8fa2a8}.ccv6-traffic-departure{margin-top:10px;padding:9px;border-radius:10px;background:#0d171a;border:1px solid #283a40;font-size:11px}
@media(max-width:720px){.ccv6-impact-grid{grid-template-columns:1fr}.ccv6-browser-head{align-items:flex-start;flex-direction:column}.ccv6-traffic-grid{grid-template-columns:1fr}.ccv6-traffic-note{border-left:0;border-top:1px solid #20282b}.ccv6-traffic iframe{height:260px}}
</style>
HTML;
$html=str_replace('</head>',$css.'</head>',$html);

$js=<<<'HTML'
<script id="cc-surf-v6-js">
(function(){
  profile.drive_limit_mode=profile.drive_limit_mode||'time';
  profile.drive_limit_value=Number(profile.drive_limit_value||45);
  const modeEl=$('driveLimitMode'),limitEl=$('driveLimitValue'),outEl=$('driveLimitOut');
  modeEl.value=profile.drive_limit_mode;
  limitEl.value=profile.drive_limit_value;
  let selectedBeachSlug=null;
  let impactRequest=0;

  window.ccApproxKm=function(s){
    if(profile.origin_lat==null||profile.origin_lon==null||!s)return null;
    const r=6371,toRad=x=>x*Math.PI/180;
    const lat1=toRad(Number(profile.origin_lat)),lat2=toRad(Number(s.lat));
    const dLat=lat2-lat1,dLon=toRad(Number(s.lon)-Number(profile.origin_lon));
    const a=Math.sin(dLat/2)**2+Math.cos(lat1)*Math.cos(lat2)*Math.sin(dLon/2)**2;
    const straight=2*r*Math.asin(Math.min(1,Math.sqrt(a)));
    return straight*1.22;
  };

  function updateLimitUi(){
    profile.drive_limit_mode=modeEl.value;
    profile.drive_limit_value=Number(limitEl.value);
    if(profile.drive_limit_mode==='distance'){
      limitEl.min='5';limitEl.max='120';limitEl.step='5';
      if(Number(limitEl.value)>120)limitEl.value='120';
      outEl.textContent=limitEl.value+' km';
    }else{
      limitEl.min='10';limitEl.max='90';limitEl.step='5';
      if(Number(limitEl.value)<10)limitEl.value='10';
      outEl.textContent=limitEl.value+' min';
    }
    profile.drive_limit_value=Number(limitEl.value);
  }
  updateLimitUi();

  const baseCalculate=calculate;
  calculate=function(epoch=selectedEpoch()){
    let rows=baseCalculate(epoch);
    const mode=profile.drive_limit_mode||'time',limit=Number(profile.drive_limit_value||45);
    rows=rows.filter(a=>{
      if(mode==='distance'){
        const km=window.ccApproxKm(a.s);return km==null||km<=limit;
      }
      return a.travel==null||Number(a.travel)<=limit;
    });
    return rows;
  };

  function impactHtml(roundKm,kg,exact){
    const petrol=kg/2.3;
    const waterLitres=kg/0.00167;
    const waterTxt=waterLitres>=10000?(waterLitres/1000).toFixed(1)+'k L':Math.round(waterLitres).toLocaleString()+' L';
    return `<div class="ccv6-carbon" id="ccv6Impact"><b>${exact?'Road route':'Estimated route'} · return trip ${roundKm.toFixed(1)} km · ${kg.toFixed(1)} kg CO₂</b><div class="ccv6-impact-grid"><div class="ccv6-impact-stat"><strong>⛽ ${petrol.toFixed(1)} L</strong><span>roughly the CO₂ released by burning this much petrol</span></div><div class="ccv6-impact-stat"><strong>🚰 ${waterTxt}</strong><span>roughly the same greenhouse footprint as Queensland water + wastewater service</span></div></div><small>Car estimate uses 191 g CO₂/km, close to the Australian registered light-vehicle fleet average. Water comparison uses 1.67 kg CO₂e/m³ for Queensland water + wastewater and is only an impact analogy, not litres of water physically polluted.</small></div>`;
  }

  const baseRenderTravel=renderTravel;
  renderTravel=function(a){
    baseRenderTravel(a);
    const kmOne=window.ccApproxKm(a.s);
    if(kmOne==null)return;
    const roundKm=kmOne*2,kg=roundKm*0.191;
    $('travel').insertAdjacentHTML('beforeend',impactHtml(roundKm,kg,false));
    const req=++impactRequest;
    if(profile.origin_lat==null||profile.origin_lon==null)return;
    const controller=new AbortController();const timer=setTimeout(()=>controller.abort(),3500);
    const u=`https://router.project-osrm.org/route/v1/driving/${profile.origin_lon},${profile.origin_lat};${a.s.lon},${a.s.lat}?overview=false`;
    fetch(u,{signal:controller.signal}).then(r=>r.json()).then(j=>{
      if(req!==impactRequest||!j.routes||!j.routes[0])return;
      const oneKm=Number(j.routes[0].distance||0)/1000;if(!oneKm)return;
      const exactRound=oneKm*2,exactKg=exactRound*0.191;const el=$('ccv6Impact');if(el)el.outerHTML=impactHtml(exactRound,exactKg,true);
    }).catch(()=>{}).finally(()=>clearTimeout(timer));
  };

  function renderBeachRail(rows,activeSlug){
    const rail=$('beachQualityRail');if(!rail)return;
    if(!rows.length){rail.innerHTML='<div class="sub">No beaches are inside the selected drive limit.</div>';return;}
    rail.innerHTML=rows.map((x,i)=>{const km=window.ccApproxKm(x.s);const t=x.travel!=null?Math.round(x.travel)+' min':(km!=null?'~'+Math.round(km)+' km':'');return `<button type="button" class="ccv6-beach-card ${i===0?'recommended ':''}${x.s.slug===activeSlug?'active':''}" data-slug="${x.s.slug}"><div class="topline"><b>${x.s.name}</b><span class="pct">${x.score}%</span></div><div class="ccv6-score-track"><div class="ccv6-score-fill" style="width:${Math.max(0,Math.min(100,x.score))}%"></div></div><small>${i===0?'★ Recommended · ':''}${t}${x.s.camera?' · 📹 camera':''}</small></button>`}).join('');
    rail.querySelectorAll('[data-slug]').forEach(btn=>btn.addEventListener('click',()=>{selectedBeachSlug=btn.dataset.slug;render();const cam=$('camPreview');if(cam)cam.scrollIntoView({behavior:'smooth',block:'center'});}));
  }

  function renderTraffic(a){
    const frame=$('wazeTraffic'),open=$('wazeOpen'),explain=$('trafficExplain'),dep=$('trafficDeparture');if(!frame||!a)return;
    let lat=Number(a.s.lat),lon=Number(a.s.lon);
    if(profile.origin_lat!=null&&profile.origin_lon!=null){lat=(Number(profile.origin_lat)+lat)/2;lon=(Number(profile.origin_lon)+lon)/2;}
    const src=`https://embed.waze.com/iframe?zoom=11&lat=${lat.toFixed(5)}&lon=${lon.toFixed(5)}&pin=0&desc=1`;
    if(frame.dataset.src!==src){frame.src=src;frame.dataset.src=src;}
    open.href=`https://www.waze.com/ul?ll=${Number(a.s.lat).toFixed(5)}%2C${Number(a.s.lon).toFixed(5)}&navigate=yes`;
    const p=a.plan||{};let leave='';if(p.leaveBase)leave=new Date(p.leaveBase*1000).toLocaleTimeString([], {hour:'numeric',minute:'2-digit'});else if(profile.time_mode==='finish_work')leave=fmtTime(profile.session_hour);
    explain.textContent='Live traffic around the route to '+a.s.name+'.';
    dep.innerHTML=leave?`<b>Planned departure: ${leave}</b><br><span class="sub">The Waze panel is live traffic now, not a future prediction. Check it again close to ${leave}.</span>`:'<span class="sub">Set your location and schedule to show the planned departure time.</span>';
  }

  const baseRender=render;
  render=function(){
    const sorted=ranked.slice();
    if(!sorted.length){renderBeachRail([],null);return;}
    let chosen=selectedBeachSlug?sorted.find(x=>x.s.slug===selectedBeachSlug):null;
    if(!chosen){chosen=sorted[0];selectedBeachSlug=chosen.s.slug;}
    const original=ranked;
    ranked=[chosen,...sorted.filter(x=>x.s.slug!==chosen.s.slug)];
    baseRender();
    ranked=original;
    renderBeachRail(sorted,chosen.s.slug);
    renderTraffic(chosen);
  };

  function changed(){updateLimitUi();selectedBeachSlug=null;refresh();}
  modeEl.addEventListener('change',()=>{if(modeEl.value==='distance'&&Number(limitEl.value)>90)limitEl.value='60';changed();});
  limitEl.addEventListener('input',changed);

  // The v4 layer stores a local one-day sample lock. v6 has its own server-side
  // abuse limit, so allow one fresh v6 PHP-mail test even if an older beta was tried.
  const sb=$('sampleBtn');if(sb&&sb.textContent.includes('Sample requested')){sb.disabled=false;sb.classList.remove('ccv4-disabled');sb.textContent='Send me a sample now';}
  setTimeout(()=>{updateLimitUi();refresh();},20);
})();
</script>
HTML;
$html=str_replace('</body>',$js.'</body>',$html);
echo $html;
