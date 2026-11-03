<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/surf-match-beta-v7.php';
$html=ob_get_clean();
$html=str_replace('Surf Match Beta v7 | CostaCams','Surf Match Beta v8 | CostaCams',$html);
$html=str_replace('SURF MATCH BETA v7','SURF MATCH BETA v8',$html);

$css=<<<'HTML'
<style id="cc-surf-v8-style">
/* v8: persistent controls that older renderers cannot overwrite. */
.ccv6-drive-limit{display:none!important}
.ccv8-drive{border-top:1px solid #28454e;margin-top:13px;padding-top:13px}
.ccv8-drive-row{display:grid;grid-template-columns:128px 1fr;gap:10px;align-items:center}
.ccv8-drive-row select{width:100%}
.ccv8-drive-value{display:flex;justify-content:space-between;align-items:center;margin:0 0 7px}.ccv8-drive-value b{font-size:12px}.ccv8-drive-value strong{font-size:14px;color:var(--aqua)}
.ccv8-scale{display:flex;justify-content:space-between;color:#71858b;font-size:9px;margin-top:2px}
.ccv8-impact-panel{margin-top:14px;border:1px solid #31505a;background:linear-gradient(145deg,#08181c,#091013);border-radius:16px;padding:14px;color:#cbe7ed}
.ccv8-impact-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.ccv8-impact-head b{font-size:13px;color:#fff}.ccv8-impact-head span{font-size:10px;color:#8fa2a8}
.ccv8-impact-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:10px}.ccv8-impact-item{border:1px solid #29434b;border-radius:11px;background:#0b171b;padding:9px}.ccv8-impact-item strong{display:block;font-size:17px;color:#eafcff}.ccv8-impact-item span{display:block;margin-top:2px;font-size:9px;line-height:1.35;color:#9fb2b8}
.ccv8-impact-note{margin-top:8px;font-size:10px;line-height:1.45;color:#8fa2a8}
#travel .ccv7-impact,#travel .ccv6-carbon{display:none!important}
.ccv7-waze-frame{display:block!important;width:100%!important;height:320px!important;border:0!important;border-top:1px solid #20282b!important;background:#111!important}
@media(max-width:720px){.ccv8-drive-row{grid-template-columns:110px 1fr}.ccv8-impact-grid{grid-template-columns:1fr 1fr}.ccv7-waze-frame{height:260px!important}}
@media(max-width:430px){.ccv8-impact-grid{grid-template-columns:1fr}}
</style>
HTML;
$html=str_replace('</head>',$css.'</head>',$html);

$js=<<<'HTML'
<script id="cc-surf-v8-js">
(function(){
  const $8=id=>document.getElementById(id);
  let impactReq=0;

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

  function ensureDriveLimit(){
    if($8('ccv8Drive'))return;
    const locationCard=document.querySelector('.location-card');
    const anchor=locationCard?locationCard.closest('.group'):document.querySelector('#mixer .group');
    if(!anchor)return;
    const box=document.createElement('div');box.id='ccv8Drive';box.className='group ccv8-drive';
    box.innerHTML=`<div class="ccv8-drive-value"><b>How far are you willing to drive?</b><strong id="ccv8DriveOut">45 min</strong></div><div class="ccv8-drive-row"><select class="select" id="ccv8DriveMode"><option value="time">Drive time</option><option value="distance">Distance</option></select><input class="range" id="ccv8DriveRange" type="range" min="10" max="90" step="5" value="45"></div><div class="ccv8-scale"><span id="ccv8Min">10 min</span><span>Drag to set recommendation radius</span><span id="ccv8Max">90 min</span></div><div class="simple-note">Beaches outside this limit remain available to preview, but the recommended beach is chosen from inside your limit.</div>`;
    anchor.insertAdjacentElement('afterend',box);
    const mode=$8('ccv8DriveMode'),range=$8('ccv8DriveRange');
    mode.value=profile.drive_limit_mode==='distance'?'distance':'time';
    range.value=Number(profile.drive_limit_value||45);
    function apply(resetForMode){
      if(mode.value==='distance'){
        range.min='5';range.max='120';range.step='5';
        if(resetForMode)range.value='50';
        if(Number(range.value)<5)range.value='5';if(Number(range.value)>120)range.value='120';
        $8('ccv8DriveOut').textContent=range.value+' km';$8('ccv8Min').textContent='5 km';$8('ccv8Max').textContent='120 km';
      }else{
        range.min='10';range.max='90';range.step='5';
        if(resetForMode)range.value='45';
        if(Number(range.value)<10)range.value='10';if(Number(range.value)>90)range.value='90';
        $8('ccv8DriveOut').textContent=range.value+' min';$8('ccv8Min').textContent='10 min';$8('ccv8Max').textContent='90 min';
      }
      profile.drive_limit_mode=mode.value;profile.drive_limit_value=Number(range.value);
      const oldMode=$8('driveLimitMode'),oldRange=$8('driveLimitValue');
      if(oldMode)oldMode.value=mode.value;if(oldRange)oldRange.value=range.value;
    }
    mode.addEventListener('change',()=>{apply(true);try{refresh()}catch(e){};setTimeout(updateImpact,60);});
    range.addEventListener('input',()=>{apply(false);try{refresh()}catch(e){};setTimeout(updateImpact,60);});
    apply(false);
  }

  function ensureImpact(){
    if($8('ccv8Impact'))return;
    const best=document.querySelector('.best');if(!best)return;
    const box=document.createElement('div');box.id='ccv8Impact';box.className='ccv8-impact-panel';
    best.insertAdjacentElement('afterend',box);
  }
  function impactHtml(km,exact,name){
    const co2=km*0.191;
    const petrol=co2/2.348;
    const trees=co2/60;
    const treeText=trees<1?(trees*100).toFixed(1)+'% of one tree / 10 yrs':trees.toFixed(2)+' trees / 10 yrs';
    const oneHundredKm=(100*0.191).toFixed(1);
    return `<div class="ccv8-impact-head"><div><b>🌍 Driving pollution to ${name||'selected beach'}</b><span>${exact?'Road route':'Fast road estimate'} · return trip ${km.toFixed(1)} km</span></div><span>Updates with selected beach</span></div><div class="ccv8-impact-grid"><div class="ccv8-impact-item"><strong>${co2.toFixed(1)} kg CO₂</strong><span>estimated climate pollution from the return drive</span></div><div class="ccv8-impact-item"><strong>⛽ ${petrol.toFixed(1)} L petrol</strong><span>roughly the tailpipe CO₂ from burning this amount of petrol</span></div><div class="ccv8-impact-item"><strong>🌳 ${treeText}</strong><span>rough EPA-style tree absorption comparison</span></div><div class="ccv8-impact-item"><strong>${oneHundredKm} kg / 100 km</strong><span>same estimate expressed per 100 km for easy comparison</span></div></div><div class="ccv8-impact-note">CO₂ is greenhouse-gas pollution, not litres of water physically contaminated. Actual emissions vary with vehicle, traffic, fuel and driving style.</div>`;
  }
  function impactPlaceholder(){
    const exampleKm=40,co2=exampleKm*0.191,petrol=co2/2.348;
    return `<div class="ccv8-impact-head"><div><b>🌍 Driving pollution / CO₂</b><span>Set postcode or browser location for your real trip</span></div><span>Always visible</span></div><div class="ccv8-impact-grid"><div class="ccv8-impact-item"><strong>${co2.toFixed(1)} kg CO₂</strong><span>example for a 40 km return drive</span></div><div class="ccv8-impact-item"><strong>⛽ ${petrol.toFixed(1)} L petrol</strong><span>approximate equivalent petrol burned</span></div><div class="ccv8-impact-item"><strong>📍 Enter postcode</strong><span>press Enter to calculate your selected beach</span></div><div class="ccv8-impact-item"><strong>↔ 40 km example</strong><span>your value will replace this automatically</span></div></div><div class="ccv8-impact-note">This example remains visible so the CO₂ feature is clear even before a location is entered.</div>`;
  }
  function selectedSpot(){
    const name=($8('winnerName')?.textContent||'').trim();
    return (typeof spots!=='undefined'&&Array.isArray(spots))?spots.find(s=>s.name===name):null;
  }
  function updateImpact(){
    ensureImpact();const box=$8('ccv8Impact');if(!box)return;
    const s=selectedSpot();
    if(!s||profile.origin_lat==null||profile.origin_lon==null){box.innerHTML=impactPlaceholder();return;}
    const one=approxRoadKm(s);if(one==null){box.innerHTML=impactPlaceholder();return;}
    box.innerHTML=impactHtml(one*2,false,s.name);
    const req=++impactReq;
    const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),4500);
    const u=`https://router.project-osrm.org/route/v1/driving/${profile.origin_lon},${profile.origin_lat};${s.lon},${s.lat}?overview=false`;
    fetch(u,{signal:controller.signal}).then(r=>r.json()).then(j=>{
      if(req!==impactReq||!j.routes||!j.routes[0])return;
      const km=Number(j.routes[0].distance||0)/1000;if(!km)return;
      const current=selectedSpot();if(!current||current.slug!==s.slug)return;
      box.innerHTML=impactHtml(km*2,true,s.name);
    }).catch(()=>{}).finally(()=>clearTimeout(timer));
  }

  function openWazeByDefault(){
    const frame=$8('ccv7WazeFrame'),btn=$8('ccv7TryEmbed');
    if(!frame)return;
    frame.classList.add('show');frame.style.display='block';
    if(btn){
      btn.textContent='Hide embedded Waze map';
      btn.onclick=()=>{
        const open=frame.style.display!=='none';
        frame.style.display=open?'none':'block';
        btn.textContent=open?'Show embedded Waze map':'Hide embedded Waze map';
      };
    }
  }

  // Recalculate the persistent impact card when the selected beach changes.
  function observeSelection(){
    const n=$8('winnerName');if(!n)return;
    const obs=new MutationObserver(()=>{setTimeout(()=>{updateImpact();openWazeByDefault();},40);});
    obs.observe(n,{childList:true,characterData:true,subtree:true});
  }

  ensureDriveLimit();ensureImpact();updateImpact();
  setTimeout(()=>{openWazeByDefault();observeSelection();updateImpact();},80);
  setTimeout(()=>{openWazeByDefault();updateImpact();},500);

  // Location/postcode can finish asynchronously, so refresh the independent CO2 panel afterwards too.
  const pc=$8('postcode');if(pc)pc.addEventListener('keydown',e=>{if(e.key==='Enter')setTimeout(updateImpact,900);});
  const pcBtn=$8('usePostcode');if(pcBtn)pcBtn.addEventListener('click',()=>setTimeout(updateImpact,900));
  const locBtn=$8('useLocation');if(locBtn)locBtn.addEventListener('click',()=>setTimeout(updateImpact,1200));
})();
</script>
HTML;
$html=str_replace('</body>',$js.'</body>',$html);

echo $html;
