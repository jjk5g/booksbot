<?php
declare(strict_types=1);
ob_start();
require __DIR__.'/surf-match-beta-v5.php';
$html=ob_get_clean();
$html=str_replace('Surf Match Beta v5 | CostaCams','Surf Match Beta v6 | CostaCams',$html);
$html=str_replace('SURF MATCH BETA v5','SURF MATCH BETA v6',$html);
$html=str_replace('surf-beta-v5-api.php','surf-beta-v6-api.php',$html);
// New / legacy profiles default to the practical after-work interpretation.
$html=str_replace("profile.time_mode=profile.time_mode==='arrive'?'in_water':'finish_work';","profile.time_mode='finish_work';",$html);
$html=str_replace('Try a real sample email first, or subscribe and save this profile.','Try a real sample email first, or subscribe and save this profile. Emails are sent from the authenticated CostaCams mailbox to the address you enter.',$html);

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

$css=<<<'HTML'
<style id="cc-surf-v6-style">
.ccv6-limit-row{display:grid;grid-template-columns:120px 1fr;gap:10px;align-items:center}.ccv6-carbon{margin-top:8px;padding:9px 10px;border:1px solid #29434b;border-radius:10px;background:#081216;color:#c8e9f0;font-size:11px}.ccv6-carbon b{color:#fff}.ccv6-carbon small{display:block;color:#8ea2a8;margin-top:3px}
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

  window.ccApproxKm=function(s){
    if(profile.origin_lat==null||profile.origin_lon==null||!s)return null;
    const r=6371,toRad=x=>x*Math.PI/180;
    const lat1=toRad(Number(profile.origin_lat)),lat2=toRad(Number(s.lat));
    const dLat=lat2-lat1,dLon=toRad(Number(s.lon)-Number(profile.origin_lon));
    const a=Math.sin(dLat/2)**2+Math.cos(lat1)*Math.cos(lat2)*Math.sin(dLon/2)**2;
    const straight=2*r*Math.asin(Math.min(1,Math.sqrt(a)));
    return straight*1.22; // practical road-distance estimate until exact routing is available
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

  const baseRenderTravel=renderTravel;
  renderTravel=function(a){
    baseRenderTravel(a);
    const kmOne=window.ccApproxKm(a.s);
    if(kmOne==null)return;
    const roundKm=kmOne*2;
    const kg=roundKm*0.192; // simple average petrol-car estimate, 192 g CO2/km
    const petrol=kg/2.31;   // approx tailpipe CO2 per litre of petrol burned
    $('travel').insertAdjacentHTML('beforeend',`<div class="ccv6-carbon"><b>Return trip: ~${roundKm.toFixed(1)} km · ~${kg.toFixed(1)} kg CO₂</b><small>Equivalent to the tailpipe CO₂ from burning about ${petrol.toFixed(1)} L of petrol. Estimate uses an average petrol car; actual emissions vary by vehicle and traffic.</small></div>`);
  };

  function changed(){updateLimitUi();refresh();}
  modeEl.addEventListener('change',()=>{if(modeEl.value==='distance'&&Number(limitEl.value)>90)limitEl.value='60';changed();});
  limitEl.addEventListener('input',changed);
  setTimeout(()=>{updateLimitUi();refresh();},20);
})();
</script>
HTML;
$html=str_replace('</body>',$js.'</body>',$html);
echo $html;
