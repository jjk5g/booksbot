<?php
declare(strict_types=1);
ob_start();
require __DIR__.'/surf-match-beta-v4.php';
$html=ob_get_clean();
$html=str_replace('Surf Match Beta v4 | CostaCams','Surf Match Beta v5 | CostaCams',$html);
$html=str_replace('SURF MATCH BETA v4','SURF MATCH BETA v5',$html);
$html=str_replace('surf-beta-v4-api.php','surf-beta-v5-api.php',$html);
$old='<select class="select" id="timeMode" style="width:auto"><option value="arrive">I want to be surfing at</option><option value="leave">I can leave at</option></select>';
$new='<select class="select" id="timeMode" style="width:auto"><option value="finish_work" selected>I finish work at</option><option value="in_water">I want to be in the water at</option><option value="must_work">I need to be at work by</option></select>';
$html=str_replace($old,$new,$html);
$anchor='</div></div>\n<div class="group"><div class="labelrow"><b>Your level</b></div>';
$extra=<<<'HTML'
</div></div>
<div class="group ccv5-schedule"><div class="labelrow"><b>How long do you want to surf?</b><span class="value" id="sessionOut">40 min</span></div><input class="range" id="sessionMinutes" type="range" min="20" max="120" step="10" value="40"><div class="labelrow" style="margin-top:9px"><b>Parking / change / paddle buffer</b><span class="value" id="bufferOut">10 min</span></div><input class="range" id="bufferMinutes" type="range" min="5" max="30" step="5" value="10"><div class="simple-note" id="scheduleExplain">If you finish work at 5:00 PM, CostaCams adds the road drive plus this buffer before scoring the conditions you will actually reach.</div></div>
<div class="group"><div class="labelrow"><b>Your level</b></div>
HTML;
$html=str_replace($anchor,$extra,$html);
$html=str_replace('No street address is requested.','No street address is requested. The postcode is used only as an approximate starting / return area for travel planning.',$html);
$css=<<<'HTML'
<style id="cc-surf-v5-style">
.ccv5-schedule{border-top-color:#3b5962}.ccv5-plan{margin-top:9px;padding:10px 11px;border:1px solid #29434b;border-radius:11px;background:#081216;color:#c8e9f0;font-size:11px}.ccv5-plan b{color:#fff}.ccv5-plan .muted{color:#8ea2a8}.ccv5-route-chip{display:inline-block;margin:3px 4px 0 0;padding:4px 7px;border-radius:999px;border:1px solid #29434b;background:#0b171b;font-size:10px}
</style>
HTML;
$html=str_replace('</head>',$css.'</head>',$html);
$js=<<<'HTML'
<script id="cc-surf-v5-js">
(function(){
  const api5='surf-beta-v5-api.php';
  profile.session_minutes=Number(initial.session_minutes||profile.session_minutes||40);
  profile.buffer_minutes=Number(initial.buffer_minutes||profile.buffer_minutes||10);
  if(!['finish_work','in_water','must_work'].includes(profile.time_mode)) profile.time_mode=profile.time_mode==='arrive'?'in_water':'finish_work';
  $('timeMode').value=profile.time_mode;$('sessionMinutes').value=profile.session_minutes;$('bufferMinutes').value=profile.buffer_minutes;
  $('sessionOut').textContent=profile.session_minutes+' min';$('bufferOut').textContent=profile.buffer_minutes+' min';

  function planFor(s,baseEpoch){
    const tr=travelMin(s),buf=Number(profile.buffer_minutes||10)*60,sess=Number(profile.session_minutes||40)*60,mode=profile.time_mode;
    let p={travel:tr,evalEpoch:baseEpoch,inWater:baseEpoch,leaveBase:null,leaveWater:null,arriveBack:null};
    if(tr==null)return p;const road=tr*60;
    if(mode==='finish_work'){
      p.leaveBase=baseEpoch; p.inWater=baseEpoch+road+buf; p.evalEpoch=p.inWater; p.leaveWater=p.inWater+sess; p.arriveBack=p.leaveWater+road+buf;
    } else if(mode==='in_water'){
      p.inWater=baseEpoch;p.leaveBase=baseEpoch-road-buf;p.evalEpoch=baseEpoch;p.leaveWater=baseEpoch+sess;p.arriveBack=p.leaveWater+road+buf;
    } else {
      p.arriveBack=baseEpoch;p.leaveWater=baseEpoch-road-buf;p.inWater=p.leaveWater-sess;p.leaveBase=p.inWater-road-buf;p.evalEpoch=p.inWater+Math.round(sess/2);
    }
    return p;
  }
  calculate=function(epoch=selectedEpoch()){
    const out=[];for(const s of spots){const p=planFor(s,epoch),h=nearest(s,p.evalEpoch);if(!h)continue;const z=scoreSpot(s,h);out.push({s,h,score:z.score,re:z.re,travel:p.travel,evalEpoch:p.evalEpoch,plan:p});}out.sort((a,b)=>b.score-a.score);return out;
  };
  function clock(epoch){return new Date(epoch*1000).toLocaleTimeString([], {hour:'numeric',minute:'2-digit'});}
  renderTravel=function(a){
    const p=a.plan||planFor(a.s,selectedEpoch());if(p.travel==null){$('travel').innerHTML='<span class="sub">Set browser location or postcode to calculate the real trip plan.</span>';return;}
    let html='';
    if(profile.time_mode==='finish_work'){
      $('winnerWhen').textContent='If you finish work at '+fmtTime(profile.session_hour)+' · forecast scored around '+clock(p.evalEpoch);
      html=`<strong>In the water about ${clock(p.inWater)}</strong><span class="sub">Finish ${fmtTime(profile.session_hour)} → ~${p.travel} min drive → ${profile.buffer_minutes} min parking/change/paddle.</span><div class="ccv5-plan"><span class="ccv5-route-chip">Finish ${fmtTime(profile.session_hour)}</span><span class="ccv5-route-chip">Drive ${p.travel} min</span><span class="ccv5-route-chip">Setup ${profile.buffer_minutes} min</span><span class="ccv5-route-chip">Surf ${profile.session_minutes} min</span></div>`;
    } else if(profile.time_mode==='in_water'){
      $('winnerWhen').textContent='To be surfing at '+fmtTime(profile.session_hour)+' · forecast scored at that time';
      html=`<strong>Leave about ${clock(p.leaveBase)}</strong><span class="sub">~${p.travel} min drive + ${profile.buffer_minutes} min parking/change/paddle to be in the water at ${fmtTime(profile.session_hour)}.</span><div class="ccv5-plan"><span class="ccv5-route-chip">Leave ${clock(p.leaveBase)}</span><span class="ccv5-route-chip">Surf ${profile.session_minutes} min</span><span class="ccv5-route-chip">Back about ${clock(p.arriveBack)}</span></div>`;
    } else {
      $('winnerWhen').textContent='If you must be back for work by '+fmtTime(profile.session_hour)+' · session planned backwards';
      html=`<strong>Leave your starting area about ${clock(p.leaveBase)}</strong><span class="sub">Surf about ${clock(p.inWater)}–${clock(p.leaveWater)} for ${profile.session_minutes} min, then ~${p.travel} min back + ${profile.buffer_minutes} min logistics to be back by ${fmtTime(profile.session_hour)}.</span><div class="ccv5-plan"><span class="ccv5-route-chip">Out ${clock(p.leaveBase)}</span><span class="ccv5-route-chip">Surf ${clock(p.inWater)}</span><span class="ccv5-route-chip">Leave beach ${clock(p.leaveWater)}</span><span class="ccv5-route-chip">Work ${fmtTime(profile.session_hour)}</span></div>`;
    }
    $('travel').innerHTML=html;drawRouteOnly(a);
  };
  async function drawRouteOnly(a){if(profile.origin_lat==null||profile.origin_lon==null||!map)return;try{const u=`https://router.project-osrm.org/route/v1/driving/${profile.origin_lon},${profile.origin_lat};${a.s.lon},${a.s.lat}?overview=full&geometries=geojson`;const r=await fetch(u);const j=await r.json();if(!j.routes||!j.routes[0])return;if(routeLine)routeLine.remove();routeLine=L.geoJSON(j.routes[0].geometry,{interactive:false}).addTo(map);map.fitBounds(routeLine.getBounds().pad(.12));}catch(e){}}
  const oldRefresh=refresh;refresh=function(){profile.session_minutes=Number($('sessionMinutes').value);profile.buffer_minutes=Number($('bufferMinutes').value);$('sessionOut').textContent=profile.session_minutes+' min';$('bufferOut').textContent=profile.buffer_minutes+' min';oldRefresh();updateScheduleHelp();};
  function updateScheduleHelp(){const m=$('timeMode').value;profile.time_mode=m;let t='';if(m==='finish_work')t='CostaCams treats this as the time you become free. It adds the drive plus '+profile.buffer_minutes+' min for parking/change/paddle, then checks the forecast at the time you would actually enter the water.';else if(m==='in_water')t='CostaCams works backwards from your desired in-water time and tells you when to leave.';else t='CostaCams works backwards from your work/start deadline, including your return drive, '+profile.buffer_minutes+' min logistics and a '+profile.session_minutes+' min surf.';$('scheduleExplain').textContent=t;}
  ['sessionMinutes','bufferMinutes'].forEach(id=>$(id).addEventListener('input',refresh));$('timeMode').addEventListener('change',refresh);

  const oldUsePostcode=$('usePostcode').onclick;$('usePostcode').onclick=async()=>{const pc=$('postcode').value.trim();$('locationState').textContent='Finding postcode area…';try{const body=new URLSearchParams({action:'postcode',postcode:pc});const r=await fetch(api5,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body,cache:'no-store'});const j=await r.json();if(!r.ok)throw new Error(j.message||'Area not found');profile.origin_lat=Number(j.lat);profile.origin_lon=Number(j.lon);profile.origin_postcode=j.postcode;profile.location_source='postcode';$('postcode').value=j.postcode;$('locationState').textContent=(j.label||('Postcode '+j.postcode))+' set as your approximate starting / return area.';if(typeof loadRoadTimes==='function')await loadRoadTimes();else refresh();}catch(e){$('locationState').textContent=e.message||'Could not find that postcode.';}};
  const baseGeo=$('useLocation').onclick;$('useLocation').onclick=()=>{if(!navigator.geolocation){$('locationState').textContent='Location is not supported. Use postcode instead.';return}$('locationState').textContent='Requesting location permission…';navigator.geolocation.getCurrentPosition(async pos=>{profile.origin_lat=Number(pos.coords.latitude.toFixed(5));profile.origin_lon=Number(pos.coords.longitude.toFixed(5));profile.origin_postcode='';profile.location_source='browser';$('postcode').value='';$('locationState').textContent='Location point set. No street address was requested.';if(typeof loadRoadTimes==='function')await loadRoadTimes();else refresh();},()=>{$('locationState').textContent='Location not shared. Use postcode instead.';},{enableHighAccuracy:false,timeout:8000,maximumAge:300000});};
  setTimeout(()=>{refresh();updateScheduleHelp();},50);
})();
</script>
HTML;
$html=str_replace('</body>',$js.'</body>',$html);
echo $html;
