<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/surf-match-beta-v8.php';
$html=ob_get_clean();
$html=str_replace('Surf Match Beta v8 | CostaCams','Surf Match Beta v9 | CostaCams',$html);
$html=str_replace('SURF MATCH BETA v8','SURF MATCH BETA v9',$html);

$css=<<<'HTML'
<style id="cc-surf-v9-style">
.ccv9-radius-status{margin-top:8px;padding:8px 9px;border:1px solid #29434b;border-radius:10px;background:#081216;color:#a9c1c7;font-size:10px;line-height:1.4}
.ccv9-radius-status.ok{border-color:#315c4b;color:#bce7cc}.ccv9-radius-status.warn{border-color:#604d2d;color:#ffd08a;background:#181207}
.ccv9-map-note{padding:8px 12px;border-top:1px solid #20282b;color:#8fa2a8;font-size:10px;background:#090d0f}
</style>
HTML;
$html=str_replace('</head>',$css.'</head>',$html);

$js=<<<'HTML'
<script id="cc-surf-v9-js">
(function(){
  const $9=id=>document.getElementById(id);
  let syncTimer=null, impactReq=0, lastOrigin='';

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
  function approxMinutes(s){
    const km=approxRoadKm(s);return km==null?null:Math.max(1,Math.round(km/45*60));
  }
  function allRowsUnfiltered(){
    const oldMode=profile.drive_limit_mode,oldVal=profile.drive_limit_value;
    profile.drive_limit_mode='time';profile.drive_limit_value=10000;
    let rows=[];try{rows=calculate(selectedEpoch()).slice();}catch(e){}
    profile.drive_limit_mode=oldMode;profile.drive_limit_value=oldVal;
    return rows;
  }
  function travelFor(row){
    if(row&&row.travel!=null&&Number.isFinite(Number(row.travel)))return Number(row.travel);
    try{const t=travelMin(row.s);if(t!=null&&Number.isFinite(Number(t)))return Number(t);}catch(e){}
    return approxMinutes(row.s);
  }
  function inside(row){
    const lim=Number(profile.drive_limit_value||45);
    if(profile.drive_limit_mode==='distance'){
      const km=approxRoadKm(row.s);return km==null?true:km<=lim;
    }
    const mins=travelFor(row);return mins==null?true:mins<=lim;
  }
  function ensureStatus(){
    const box=$9('ccv8Drive');if(!box||$9('ccv9DriveStatus'))return;
    const s=document.createElement('div');s.id='ccv9DriveStatus';s.className='ccv9-radius-status';s.textContent='Set a postcode/location to choose the best beach inside your drive radius.';box.appendChild(s);
  }
  function setStatus(html,type){ensureStatus();const s=$9('ccv9DriveStatus');if(!s)return;s.className='ccv9-radius-status '+(type||'');s.innerHTML=html;}

  function rowLabel(row){
    const mins=travelFor(row),km=approxRoadKm(row.s);
    if(profile.drive_limit_mode==='distance'&&km!=null)return '~'+Math.round(km)+' km';
    if(mins!=null)return '~'+Math.round(mins)+' min';
    return 'travel estimate unavailable';
  }

  function updateWaze(row){
    const frame=$9('ccv7WazeFrame'),open=$9('ccv7WazeOpen');if(!frame||!row)return;
    const dLat=Number(row.s.lat),dLon=Number(row.s.lon);
    let lat=dLat,lon=dLon,zoom=12;
    if(profile.origin_lat!=null&&profile.origin_lon!=null){
      const oLat=Number(profile.origin_lat),oLon=Number(profile.origin_lon);
      lat=(oLat+dLat)/2;lon=(oLon+dLon)/2;
      const straight=kmBetween(oLat,oLon,dLat,dLon);
      zoom=straight<8?12:(straight<18?11:(straight<40?10:9));
    }
    const src=`https://embed.waze.com/iframe?zoom=${zoom}&lat=${lat.toFixed(5)}&lon=${lon.toFixed(5)}&pin=0&desc=1`;
    if(frame.dataset.v9src!==src){frame.src=src;frame.dataset.v9src=src;}
    frame.classList.add('show');frame.style.display='block';
    if(open)open.href=`https://www.waze.com/ul?ll=${dLat.toFixed(5)}%2C${dLon.toFixed(5)}&navigate=yes&utm_source=costacams`;
    const title=$9('ccv7TrafficTitle');if(title)title.textContent='Traffic corridor to '+row.s.name;
    let note=$9('ccv9WazeNote');
    if(!note){note=document.createElement('div');note.id='ccv9WazeNote';note.className='ccv9-map-note';frame.insertAdjacentElement('afterend',note);}
    note.textContent=profile.origin_lat!=null?'Waze view is centred between your postcode/location and '+row.s.name+'.':'Set postcode/location to centre Waze on your driving corridor.';
  }

  function updateImpact(row){
    const box=$9('ccv8Impact');if(!box||!row)return;
    if(profile.origin_lat==null||profile.origin_lon==null){return;}
    const one=approxRoadKm(row.s);if(one==null)return;
    const render=(round,exact)=>{
      const co2=round*0.191,petrol=co2/2.348,trees=co2/60;
      const treeText=trees<1?(trees*100).toFixed(1)+'% of one tree / 10 yrs':trees.toFixed(2)+' trees / 10 yrs';
      box.innerHTML=`<div class="ccv8-impact-head"><div><b>🌍 Driving pollution to ${row.s.name}</b><span>${exact?'Road route':'Fast road estimate'} · ${round.toFixed(1)} km return</span></div><span>Synced with maps</span></div><div class="ccv8-impact-grid"><div class="ccv8-impact-item"><strong>${co2.toFixed(1)} kg CO₂</strong><span>estimated climate pollution from the return drive</span></div><div class="ccv8-impact-item"><strong>⛽ ${petrol.toFixed(1)} L petrol</strong><span>rough tailpipe CO₂ equivalent</span></div><div class="ccv8-impact-item"><strong>🌳 ${treeText}</strong><span>rough tree absorption comparison</span></div><div class="ccv8-impact-item"><strong>${rowLabel(row)}</strong><span>current drive-radius travel estimate</span></div></div><div class="ccv8-impact-note">Changes automatically when postcode, drive radius or selected beach changes.</div>`;
    };
    render(one*2,false);
    const req=++impactReq,ctl=new AbortController(),timer=setTimeout(()=>ctl.abort(),4500);
    const u=`https://router.project-osrm.org/route/v1/driving/${profile.origin_lon},${profile.origin_lat};${row.s.lon},${row.s.lat}?overview=false`;
    fetch(u,{signal:ctl.signal}).then(r=>r.json()).then(j=>{
      if(req!==impactReq||!j.routes||!j.routes[0])return;
      const km=Number(j.routes[0].distance||0)/1000;if(km)render(km*2,true);
    }).catch(()=>{}).finally(()=>clearTimeout(timer));
  }

  function markChooser(slug){
    document.querySelectorAll('[data-v7slug]').forEach(b=>b.classList.toggle('active',b.dataset.v7slug===slug));
  }
  function applyRow(row){
    if(!row)return;
    try{
      if($9('winnerName'))$9('winnerName').textContent=row.s.name;
      if($9('score'))$9('score').textContent=row.score;
      renderTravel(row);renderMap(row);renderCamera(row);
    }catch(e){}
    updateWaze(row);updateImpact(row);markChooser(row.s.slug);
  }

  function syncRecommendation(reason){
    clearTimeout(syncTimer);
    syncTimer=setTimeout(()=>{
      const all=allRowsUnfiltered();if(!all.length)return;
      if(profile.origin_lat==null||profile.origin_lon==null){
        setStatus('Enter a postcode/location first. Then the radius will select the best reachable beach.','');
        updateWaze(all[0]);return;
      }
      const eligible=all.filter(inside).sort((a,b)=>b.score-a.score);
      if(eligible.length){
        const chosen=eligible[0];
        const lim=Number(profile.drive_limit_value||45),unit=profile.drive_limit_mode==='distance'?'km':'min';
        setStatus(`<b>${chosen.s.name}</b> is the best match inside your <b>${lim} ${unit}</b> drive limit (${rowLabel(chosen)}). Both maps, camera and CO₂ are synced to this beach.`,'ok');
        applyRow(chosen);
      }else{
        const byTravel=all.slice().sort((a,b)=>{
          if(profile.drive_limit_mode==='distance')return (approxRoadKm(a.s)||9999)-(approxRoadKm(b.s)||9999);
          return (travelFor(a)||9999)-(travelFor(b)||9999);
        });
        const nearest=byTravel[0],lim=Number(profile.drive_limit_value||45),unit=profile.drive_limit_mode==='distance'?'km':'min';
        setStatus(`No beach is reachable inside <b>${lim} ${unit}</b>. Nearest available is <b>${nearest.s.name}</b> (${rowLabel(nearest)}), shown only as the nearest preview.`,'warn');
        applyRow(nearest);
      }
    },reason==='postcode'?350:80);
  }

  function waitForPostcode(){
    const start=Date.now();
    const poll=()=>{
      const key=profile.origin_lat!=null&&profile.origin_lon!=null?profile.origin_lat+','+profile.origin_lon:'';
      const routeText=($9('routeSource')?.textContent||'').toLowerCase();
      if(key && (key!==lastOrigin || routeText.includes('loaded') || routeText.includes('unavailable'))){lastOrigin=key;syncRecommendation('postcode');return;}
      if(Date.now()-start<7000)setTimeout(poll,250);
    };poll();
  }

  ensureStatus();
  const mode=$9('ccv8DriveMode'),range=$9('ccv8DriveRange');
  if(mode)mode.addEventListener('change',()=>syncRecommendation('radius'));
  if(range){range.addEventListener('input',()=>syncRecommendation('radius'));range.addEventListener('change',()=>syncRecommendation('radius'));}

  const pcBtn=$9('usePostcode');if(pcBtn)pcBtn.addEventListener('click',waitForPostcode);
  const pc=$9('postcode');if(pc)pc.addEventListener('keydown',e=>{if(e.key==='Enter')waitForPostcode();});
  const loc=$9('useLocation');if(loc)loc.addEventListener('click',waitForPostcode);
  const state=$9('locationState');if(state){new MutationObserver(()=>{if(profile.origin_lat!=null&&profile.origin_lon!=null)waitForPostcode();}).observe(state,{childList:true,subtree:true,characterData:true});}

  setTimeout(()=>syncRecommendation('initial'),700);
})();
</script>
HTML;
$html=str_replace('</body>',$js.'</body>',$html);

echo $html;
