<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/surf-match-beta-v5.php';
$html=ob_get_clean();

$html=str_replace('Surf Match Beta v5 | CostaCams','Surf Match Beta v6 | CostaCams',$html);
$html=str_replace('SURF MATCH BETA v5','SURF MATCH BETA v6',$html);
$html=str_replace("const api4='surf-beta-v4-api.php'","const api4='surf-beta-v6-mail.php'",$html);
$html=str_replace('Try a real sample email first, or subscribe and save this profile.','Try a real sample email first, or subscribe and save this profile. Emails are sent by the website PHP mailer.',$html);

$driveBlock=<<<'HTML'
<div class="group ccv6-drive-limit">
  <div class="labelrow"><b>How far do you want to drive?</b><span class="value" id="driveLimitOut">45 min</span></div>
  <div class="ccv6-limit-row">
    <select class="select" id="driveLimitMode" style="width:125px"><option value="time" selected>Drive time</option><option value="distance">Distance</option></select>
    <input class="range" id="driveLimitValue" type="range" min="10" max="90" step="5" value="45">
  </div>
  <div class="simple-note">Only beaches inside this limit are ranked.</div>
</div>
HTML;
$html=str_replace('<div class="group ccv5-schedule">',$driveBlock.'<div class="group ccv5-schedule">',$html);

$railBlock=<<<'HTML'
<div class="ccv6-beach-browser">
  <div class="ccv6-browser-head"><div><b>Compare beaches</b><span>Click another beach to preview it instead of the top recommendation.</span></div><small>Surf Match %</small></div>
  <div class="ccv6-beach-rail" id="beachQualityRail"></div>
</div>
HTML;
$html=str_replace('<div class="spot-detail">',$railBlock.'<div class="spot-detail">',$html);

$trafficBlock=<<<'HTML'
<div class="ccv6-traffic">
  <div class="ccv6-browser-head"><div><b>Traffic check</b><span id="trafficExplain">Live traffic near the selected beach.</span></div><a id="wazeOpen" href="#" target="_blank" rel="noopener">Open in Waze ↗</a></div>
  <iframe id="wazeTraffic" title="Waze live traffic map" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
</div>
HTML;
$html=str_replace('<div class="timeline">',$trafficBlock.'<div class="timeline">',$html);

$css=<<<'HTML'
<style id="cc-surf-v6-hotfix-style">
.ccv6-limit-row{display:grid;grid-template-columns:125px 1fr;gap:10px;align-items:center}
.ccv6-beach-browser,.ccv6-traffic{margin-top:14px;border:1px solid var(--line);border-radius:16px;background:#080d0f;overflow:hidden}.ccv6-browser-head{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:11px 12px;border-bottom:1px solid #20282b}.ccv6-browser-head div{display:grid;gap:2px}.ccv6-browser-head span,.ccv6-browser-head small{font-size:10px;color:#8fa2a8}.ccv6-browser-head a{font-size:10px;color:#8feaff;text-decoration:none}.ccv6-beach-rail{display:flex;gap:8px;overflow-x:auto;padding:10px}.ccv6-beach-card{min-width:145px;border:1px solid #283438;background:#0b1113;color:#dce9ec;border-radius:12px;padding:9px;text-align:left;cursor:pointer}.ccv6-beach-card:hover,.ccv6-beach-card.active{border-color:#50dcff;background:#0b2026}.ccv6-beach-card.recommended{box-shadow:inset 0 0 0 1px #69e0a055}.ccv6-beach-card .topline{display:flex;justify-content:space-between;gap:6px;align-items:flex-start}.ccv6-beach-card b{font-size:11px;line-height:1.15}.ccv6-beach-card .pct{font-size:20px;font-weight:950}.ccv6-beach-card small{display:block;color:#8fa2a8;font-size:9px;margin-top:4px}.ccv6-score-track{height:5px;background:#1a2529;border-radius:99px;overflow:hidden;margin-top:7px}.ccv6-score-fill{height:100%;background:linear-gradient(90deg,#ff7468,#ffbf69,#69e0a0)}
.ccv6-carbon{margin-top:10px;padding:12px;border:1px solid #29434b;border-radius:12px;background:#081216;color:#c8e9f0}.ccv6-carbon-title{font-size:12px;font-weight:900;color:#fff}.ccv6-impact-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px;margin-top:8px}.ccv6-impact-stat{border:1px solid #29434b;border-radius:10px;background:#0b171b;padding:8px}.ccv6-impact-stat strong{font-size:16px;display:block;color:#eafcff}.ccv6-impact-stat span{font-size:9px;color:#9fb2b8}.ccv6-carbon small{display:block;color:#8ea2a8;margin-top:7px;line-height:1.4}.ccv6-traffic iframe{width:100%;height:280px;border:0;background:#050606}
@media(max-width:720px){.ccv6-impact-grid{grid-template-columns:1fr}.ccv6-browser-head{align-items:flex-start;flex-direction:column}.ccv6-traffic iframe{height:240px}}
</style>
HTML;
$html=str_replace('</head>',$css.'</head>',$html);

$js=<<<'HTML'
<script id="cc-surf-v6-hotfix-js">
(function(){
  const modeEl=$('driveLimitMode'),limitEl=$('driveLimitValue'),outEl=$('driveLimitOut');
  profile.drive_limit_mode=profile.drive_limit_mode||'time';
  profile.drive_limit_value=Number(profile.drive_limit_value||45);
  modeEl.value=profile.drive_limit_mode;
  limitEl.value=profile.drive_limit_value;
  let selectedBeachSlug=null;
  let impactRequest=0;

  function approxKm(s){
    if(profile.origin_lat==null||profile.origin_lon==null||!s)return null;
    const r=6371,toRad=x=>x*Math.PI/180;
    const a1=toRad(Number(profile.origin_lat)),a2=toRad(Number(s.lat));
    const dLat=a2-a1,dLon=toRad(Number(s.lon)-Number(profile.origin_lon));
    const a=Math.sin(dLat/2)**2+Math.cos(a1)*Math.cos(a2)*Math.sin(dLon/2)**2;
    return 2*r*Math.asin(Math.min(1,Math.sqrt(a)))*1.22;
  }
  window.ccApproxKm=approxKm;

  function updateLimitUi(){
    profile.drive_limit_mode=modeEl.value;
    if(modeEl.value==='distance'){
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
    const rows=baseCalculate(epoch);
    const limit=Number(profile.drive_limit_value||45);
    return rows.filter(x=>{
      if(profile.drive_limit_mode==='distance'){
        const km=approxKm(x.s);return km==null||km<=limit;
      }
      return x.travel==null||Number(x.travel)<=limit;
    });
  };

  function impactHtml(roundKm,kg,exact){
    const petrol=kg/2.31;
    const waterLitres=kg/0.00167;
    const waterTxt=waterLitres>=10000?(waterLitres/1000).toFixed(1)+'k L':Math.round(waterLitres).toLocaleString()+' L';
    return `<div class="ccv6-carbon" id="ccv6Impact"><div class="ccv6-carbon-title">Driving impact · ${exact?'road route':'estimated route'} · return ${roundKm.toFixed(1)} km</div><div class="ccv6-impact-grid"><div class="ccv6-impact-stat"><strong>${kg.toFixed(1)} kg CO₂</strong><span>estimated greenhouse gas from the return drive</span></div><div class="ccv6-impact-stat"><strong>⛽ ${petrol.toFixed(1)} L petrol</strong><span>roughly the same tailpipe CO₂ as burning this much petrol</span></div><div class="ccv6-impact-stat"><strong>🚰 ${waterTxt}</strong><span>roughly the greenhouse footprint of treating/supplying this much Queensland water + wastewater</span></div></div><small>The water number is a climate-impact comparison, not litres of water physically polluted. Car estimate uses about 191 g CO₂/km; actual impact varies by vehicle and traffic.</small></div>`;
  }

  const baseRenderTravel=renderTravel;
  renderTravel=function(a){
    baseRenderTravel(a);
    const one=approxKm(a.s);
    if(one==null){$('travel').insertAdjacentHTML('beforeend','<div class="ccv6-carbon"><div class="ccv6-carbon-title">Driving impact</div><small>Set your postcode or browser location to calculate return-trip kilometres and pollution.</small></div>');return;}
    const round=one*2,kg=round*0.191;
    $('travel').insertAdjacentHTML('beforeend',impactHtml(round,kg,false));
    const req=++impactRequest;
    if(profile.origin_lat==null||profile.origin_lon==null)return;
    const controller=new AbortController();const timer=setTimeout(()=>controller.abort(),3500);
    const u=`https://router.project-osrm.org/route/v1/driving/${profile.origin_lon},${profile.origin_lat};${a.s.lon},${a.s.lat}?overview=false`;
    fetch(u,{signal:controller.signal}).then(r=>r.json()).then(j=>{
      if(req!==impactRequest||!j.routes||!j.routes[0])return;
      const km=Number(j.routes[0].distance||0)/1000;if(!km)return;
      const exactRound=km*2,exactKg=exactRound*0.191;const el=$('ccv6Impact');if(el)el.outerHTML=impactHtml(exactRound,exactKg,true);
    }).catch(()=>{}).finally(()=>clearTimeout(timer));
  };

  function renderRail(rows,activeSlug){
    const rail=$('beachQualityRail');if(!rail)return;
    if(!rows.length){rail.innerHTML='<div class="sub">No beaches are inside the selected drive limit.</div>';return;}
    rail.innerHTML=rows.map((x,i)=>{
      const km=approxKm(x.s);const trip=x.travel!=null?Math.round(x.travel)+' min':(km!=null?'~'+Math.round(km)+' km':'');
      return `<button type="button" class="ccv6-beach-card ${i===0?'recommended ':''}${x.s.slug===activeSlug?'active':''}" data-slug="${x.s.slug}"><div class="topline"><b>${x.s.name}</b><span class="pct">${x.score}%</span></div><div class="ccv6-score-track"><div class="ccv6-score-fill" style="width:${Math.max(0,Math.min(100,x.score))}%"></div></div><small>${i===0?'★ Recommended · ':''}${trip}${x.s.camera?' · 📹 camera':''}</small></button>`;
    }).join('');
    rail.querySelectorAll('[data-slug]').forEach(btn=>btn.onclick=()=>{selectedBeachSlug=btn.dataset.slug;render();const cam=$('camPreview');if(cam)cam.scrollIntoView({behavior:'smooth',block:'center'});});
  }

  function renderTraffic(a){
    const frame=$('wazeTraffic'),open=$('wazeOpen'),txt=$('trafficExplain');if(!frame||!a)return;
    let lat=Number(a.s.lat),lon=Number(a.s.lon);
    if(profile.origin_lat!=null&&profile.origin_lon!=null){lat=(Number(profile.origin_lat)+lat)/2;lon=(Number(profile.origin_lon)+lon)/2;}
    const src=`https://embed.waze.com/iframe?zoom=11&lat=${lat.toFixed(5)}&lon=${lon.toFixed(5)}&pin=0&desc=1`;
    if(frame.dataset.src!==src){frame.src=src;frame.dataset.src=src;}
    open.href=`https://www.waze.com/ul?ll=${Number(a.s.lat).toFixed(5)}%2C${Number(a.s.lon).toFixed(5)}&navigate=yes`;
    txt.textContent='Live traffic around the route to '+a.s.name+'.';
  }

  const baseRender=render;
  render=function(){
    const rows=ranked.slice();
    if(!rows.length){baseRender();renderRail([],null);return;}
    let chosen=selectedBeachSlug?rows.find(x=>x.s.slug===selectedBeachSlug):null;
    if(!chosen){chosen=rows[0];selectedBeachSlug=chosen.s.slug;}
    const original=ranked;
    ranked=[chosen,...rows.filter(x=>x.s.slug!==chosen.s.slug)];
    baseRender();
    ranked=original;
    renderRail(rows,chosen.s.slug);
    renderTraffic(chosen);
  };

  function changed(){updateLimitUi();selectedBeachSlug=null;refresh();}
  modeEl.onchange=changed;
  limitEl.oninput=changed;
  setTimeout(()=>{updateLimitUi();refresh();},25);
})();
</script>
HTML;
$html=str_replace('</body>',$js.'</body>',$html);

echo $html;
