<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/surf-match-beta-v6-hotfix.php';
$html=ob_get_clean();
$html=str_replace('Surf Match Beta v6 | CostaCams','Surf Match Beta v7 | CostaCams',$html);
$html=str_replace('SURF MATCH BETA v6','SURF MATCH BETA v7',$html);

$css=<<<'HTML'
<style id="cc-surf-v7-style">
/* v7 owns the reliable beach chooser and traffic UI. */
.ccv6-beach-browser{display:none!important}.ccv6-traffic{display:none!important}
.ccv7-panel{margin-top:14px;border:1px solid var(--line);border-radius:16px;background:#080d0f;overflow:hidden}
.ccv7-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;padding:12px;border-bottom:1px solid #20282b}
.ccv7-head>div{display:grid;gap:2px}.ccv7-head b{font-size:13px}.ccv7-head span{font-size:10px;color:#8fa2a8}.ccv7-head small{font-size:10px;color:#9fb2b8}
.ccv7-subhead{padding:9px 12px 0;color:#cbdadd;font-size:10px;font-weight:850;text-transform:uppercase;letter-spacing:.06em}
.ccv7-rail{display:flex;gap:8px;overflow-x:auto;padding:10px 12px 12px;scroll-snap-type:x proximity}
.ccv7-card{min-width:154px;max-width:154px;scroll-snap-align:start;border:1px solid #283438;background:#0b1113;color:#dce9ec;border-radius:12px;padding:10px;text-align:left;cursor:pointer}
.ccv7-card:hover,.ccv7-card.active{border-color:#50dcff;background:#0b2026}.ccv7-card.recommended{box-shadow:inset 0 0 0 1px #69e0a066}
.ccv7-card .top{position:static;background:none;border:0;backdrop-filter:none;padding:0;display:flex;justify-content:space-between;gap:5px;align-items:flex-start}
.ccv7-card b{font-size:11px;line-height:1.15}.ccv7-card .pct{font-size:20px;font-weight:950;color:#eafcff;line-height:1}.ccv7-card small{display:block;color:#8fa2a8;font-size:9px;margin-top:5px;line-height:1.35}
.ccv7-track{height:5px;background:#1a2529;border-radius:99px;overflow:hidden;margin-top:7px}.ccv7-fill{height:100%;background:linear-gradient(90deg,#ff7468,#ffbf69,#69e0a0)}
.ccv7-tag{display:inline-block;margin-top:5px;padding:3px 6px;border:1px solid #314149;border-radius:999px;font-size:8px;color:#a9bcc1}.ccv7-tag.out{color:#d1a679;border-color:#5a4430}
#rank .rankrow{cursor:pointer;transition:.12s}#rank .rankrow:hover{border-color:#50dcff;background:#0b171a}
.ccv7-impact{margin-top:10px;border:1px solid #31505a;background:#08161a;border-radius:13px;padding:11px;color:#cbe7ed}
.ccv7-impact h4{margin:0 0 4px;font-size:12px;color:#fff}.ccv7-impact p{margin:0;color:#91a7ad;font-size:10px;line-height:1.45}
.ccv7-impact-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px;margin-top:9px}.ccv7-impact-item{border:1px solid #29434b;border-radius:10px;background:#0b171b;padding:8px}.ccv7-impact-item strong{display:block;font-size:16px;color:#eafcff}.ccv7-impact-item span{display:block;font-size:9px;color:#9fb2b8;line-height:1.35}
.ccv7-traffic-body{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center;padding:14px}.ccv7-traffic-body p{margin:4px 0 0;color:#8fa2a8;font-size:11px;max-width:720px}.ccv7-traffic-actions{display:flex;gap:7px;flex-wrap:wrap}.ccv7-btn{display:inline-block;border:1px solid #3c6470;background:#0c2025;color:#d0f7ff;border-radius:10px;padding:9px 11px;text-decoration:none;font-size:11px;font-weight:850;cursor:pointer}.ccv7-btn.secondary{background:#0a0e0f;border-color:#334044;color:#cbd8db}
.ccv7-waze-frame{display:none;width:100%;height:320px;border:0;border-top:1px solid #20282b;background:#111}.ccv7-waze-frame.show{display:block}
@media(max-width:720px){.ccv7-impact-grid{grid-template-columns:1fr}.ccv7-head{flex-direction:column}.ccv7-traffic-body{grid-template-columns:1fr}.ccv7-card{min-width:146px;max-width:146px}.ccv7-waze-frame{height:260px}}
</style>
HTML;
$html=str_replace('</head>',$css.'</head>',$html);

$js=<<<'HTML'
<script id="cc-surf-v7-js">
(function(){
  const $7=id=>document.getElementById(id);
  let selectedSlug=null;
  let exactImpactRequest=0;

  function kmBetween(lat1,lon1,lat2,lon2){
    const R=6371,r=x=>Number(x)*Math.PI/180;
    const a1=r(lat1),a2=r(lat2),dlat=a2-a1,dlon=r(Number(lon2)-Number(lon1));
    const a=Math.sin(dlat/2)**2+Math.cos(a1)*Math.cos(a2)*Math.sin(dlon/2)**2;
    return 2*R*Math.asin(Math.min(1,Math.sqrt(a)));
  }
  function approxRoadKm(s){
    if(profile.origin_lat==null||profile.origin_lon==null)return null;
    return kmBetween(profile.origin_lat,profile.origin_lon,s.lat,s.lon)*1.22;
  }
  function evaluationEpoch(s,base){
    const tr=travelMin(s),buf=Number(profile.buffer_minutes||10)*60,sess=Number(profile.session_minutes||40)*60;
    if(tr==null)return base;
    const road=Number(tr)*60;
    if(profile.time_mode==='finish_work')return base+road+buf;
    if(profile.time_mode==='must_work')return base-road-buf-Math.round(sess/2);
    return base;
  }
  function allRows(){
    const base=selectedEpoch(),out=[];
    for(const s of spots){
      const evalEpoch=evaluationEpoch(s,base),h=nearest(s,evalEpoch);if(!h)continue;
      const z=scoreSpot(s,h);out.push({s,h,score:z.score,re:z.re,travel:travelMin(s),evalEpoch});
    }
    out.sort((a,b)=>b.score-a.score);return out;
  }
  function insideLimit(row){
    const lim=Number(profile.drive_limit_value||45);
    if(profile.drive_limit_mode==='distance'){
      const km=approxRoadKm(row.s);return km==null||km<=lim;
    }
    return row.travel==null||Number(row.travel)<=lim;
  }
  function ensureChooser(){
    if($7('ccv7Chooser'))return;
    const anchor=document.querySelector('.spot-detail');if(!anchor)return;
    const box=document.createElement('div');box.id='ccv7Chooser';box.className='ccv7-panel';
    box.innerHTML=`<div class="ccv7-head"><div><b>Choose any beach</b><span>Recommended beaches first. Click any beach to preview its camera, map, drive and climate impact.</span></div><small>All available beaches</small></div><div class="ccv7-subhead">Best matches</div><div class="ccv7-rail" id="ccv7TopRail"></div><div class="ccv7-subhead">Nearby to selected beach</div><div class="ccv7-rail" id="ccv7NearRail"></div>`;
    anchor.parentNode.insertBefore(box,anchor);
  }
  function cardHtml(row,i,active,nearKm){
    const km=approxRoadKm(row.s),travel=row.travel!=null?Math.round(Number(row.travel))+' min':(km!=null?'~'+Math.round(km)+' km':'set location');
    const within=insideLimit(row);
    const near=nearKm!=null?' · '+nearKm.toFixed(1)+' km from selected':'';
    return `<button type="button" class="ccv7-card ${i===0?'recommended ':''}${row.s.slug===active?'active':''}" data-v7slug="${row.s.slug}"><div class="top"><b>${row.s.name}</b><span class="pct">${row.score}%</span></div><div class="ccv7-track"><div class="ccv7-fill" style="width:${Math.max(0,Math.min(100,row.score))}%"></div></div><small>${i===0?'★ Best match · ':''}${travel}${row.s.camera?' · 📹 camera':''}${near}</small><span class="ccv7-tag ${within?'':'out'}">${within?'inside drive limit':'outside drive limit · still previewable'}</span></button>`;
  }
  function renderChooser(rows){
    ensureChooser();const top=$7('ccv7TopRail'),near=$7('ccv7NearRail');if(!top||!near)return;
    if(!rows.length){top.innerHTML='<span class="sub">No beach data available.</span>';near.innerHTML='';return;}
    if(!selectedSlug||!rows.some(x=>x.s.slug===selectedSlug))selectedSlug=rows[0].s.slug;
    top.innerHTML=rows.map((r,i)=>cardHtml(r,i,selectedSlug,null)).join('');
    const chosen=rows.find(x=>x.s.slug===selectedSlug)||rows[0];
    const nearRows=rows.filter(x=>x.s.slug!==chosen.s.slug).map(x=>({row:x,d:kmBetween(chosen.s.lat,chosen.s.lon,x.s.lat,x.s.lon)})).sort((a,b)=>a.d-b.d).slice(0,8);
    near.innerHTML=nearRows.map((x,i)=>cardHtml(x.row,i+1,selectedSlug,x.d)).join('');
    document.querySelectorAll('[data-v7slug]').forEach(btn=>btn.onclick=()=>{
      const row=allRows().find(x=>x.s.slug===btn.dataset.v7slug);if(row)preview(row,true);
    });
  }
  function updateWinner(row){
    if($7('score'))$7('score').textContent=row.score;
    if($7('winnerName'))$7('winnerName').textContent=row.s.name;
    if($7('winnerWhen'))$7('winnerWhen').textContent='Previewing selected beach · '+(row.h.local||'selected time');
    if($7('metrics'))$7('metrics').innerHTML=`<span class="metric">🌊 ${Number(row.h.wave_height||0).toFixed(1)} m</span><span class="metric">↔ ${Number(row.h.wave_period||0).toFixed(0)} s</span><span class="metric">⚡ ${Number(row.h.energy_kwm||0).toFixed(1)} kW/m</span><span class="metric">💨 ${row.h.wind_compass||'—'} ${Math.round(row.h.wind_speed||0)} km/h</span><span class="metric">🌧 ${Math.round(row.h.rain_probability||0)}%</span>`;
  }
  function impactMarkup(roundKm,exact){
    const kg=roundKm*0.191;
    const petrol=kg/2.348;
    const treePct=(kg/60)*100;
    const treeText=treePct<1?treePct.toFixed(1)+'%':Math.round(treePct)+'%';
    return `<div class="ccv7-impact" id="ccv7Impact"><h4>🌍 Climate pollution from this drive · ${exact?'road route':'fast estimate'} · ${roundKm.toFixed(1)} km return</h4><p>CO₂ is climate pollution; it is not the same thing as contaminating water. These comparisons make the scale easier to understand.</p><div class="ccv7-impact-grid"><div class="ccv7-impact-item"><strong>${kg.toFixed(1)} kg CO₂</strong><span>estimated emissions from the return drive</span></div><div class="ccv7-impact-item"><strong>⛽ ${petrol.toFixed(1)} L</strong><span>roughly the CO₂ from burning this much petrol</span></div><div class="ccv7-impact-item"><strong>🌳 ${treeText}</strong><span>of the CO₂ an urban tree seedling can absorb over about 10 years (EPA equivalency)</span></div></div></div>`;
  }
  function showImpact(row){
    let host=$7('travel');if(!host)return;
    const old=$7('ccv7Impact');if(old)old.remove();
    if(profile.origin_lat==null||profile.origin_lon==null){host.insertAdjacentHTML('beforeend','<div class="ccv7-impact" id="ccv7Impact"><h4>🌍 Driving climate pollution</h4><p>Enter a postcode and press Enter, or use browser location, to calculate return-trip kilometres, CO₂ and real-world equivalents.</p></div>');return;}
    const one=approxRoadKm(row.s);if(one==null)return;
    host.insertAdjacentHTML('beforeend',impactMarkup(one*2,false));
    const req=++exactImpactRequest;
    const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),4500);
    const u=`https://router.project-osrm.org/route/v1/driving/${profile.origin_lon},${profile.origin_lat};${row.s.lon},${row.s.lat}?overview=false`;
    fetch(u,{signal:controller.signal}).then(r=>r.json()).then(j=>{
      if(req!==exactImpactRequest||!j.routes||!j.routes[0])return;
      const oneKm=Number(j.routes[0].distance||0)/1000;if(!oneKm)return;
      const el=$7('ccv7Impact');if(el)el.outerHTML=impactMarkup(oneKm*2,true);
    }).catch(()=>{}).finally(()=>clearTimeout(timer));
  }
  function ensureTraffic(){
    if($7('ccv7Traffic'))return;
    const anchor=document.querySelector('.timeline');if(!anchor)return;
    const box=document.createElement('div');box.id='ccv7Traffic';box.className='ccv7-panel';
    box.innerHTML=`<div class="ccv7-head"><div><b>Traffic before you leave</b><span>Live traffic is opened directly in Waze because some browsers render the embedded Waze map as a black frame.</span></div><small>Live traffic</small></div><div class="ccv7-traffic-body"><div><b id="ccv7TrafficTitle">Select a beach</b><p id="ccv7TrafficText">The normal CostaCams route map remains visible above. Use Waze for the live traffic layer.</p></div><div class="ccv7-traffic-actions"><a class="ccv7-btn" id="ccv7WazeOpen" href="#" target="_blank" rel="noopener">Open live traffic in Waze ↗</a><button class="ccv7-btn secondary" id="ccv7TryEmbed" type="button">Try embedded Waze map</button></div></div><iframe class="ccv7-waze-frame" id="ccv7WazeFrame" title="Waze live traffic"></iframe>`;
    anchor.parentNode.insertBefore(box,anchor);
    $7('ccv7TryEmbed').onclick=()=>{const f=$7('ccv7WazeFrame');f.classList.toggle('show');$7('ccv7TryEmbed').textContent=f.classList.contains('show')?'Hide embedded map':'Try embedded Waze map';};
  }
  function updateTraffic(row){
    ensureTraffic();const a=$7('ccv7WazeOpen'),f=$7('ccv7WazeFrame');if(!a||!f)return;
    const lat=Number(row.s.lat),lon=Number(row.s.lon);
    a.href=`https://www.waze.com/ul?ll=${lat.toFixed(5)}%2C${lon.toFixed(5)}&navigate=yes&utm_source=costacams`;
    f.src=`https://embed.waze.com/iframe?zoom=12&lat=${lat.toFixed(5)}&lon=${lon.toFixed(5)}&pin=1&desc=1`;
    if($7('ccv7TrafficTitle'))$7('ccv7TrafficTitle').textContent='Live traffic to '+row.s.name;
  }
  function makeSuggestedClickable(rows){
    document.querySelectorAll('#rank .rankrow').forEach(el=>{
      const name=(el.querySelector('b')?.textContent||'').trim();const row=rows.find(x=>x.s.name===name);if(!row)return;
      el.title='Click to preview '+name+' camera and trip';el.onclick=()=>preview(row,true);
    });
  }
  function preview(row,scroll){
    selectedSlug=row.s.slug;updateWinner(row);
    try{renderTravel(row)}catch(e){}
    try{renderMap(row)}catch(e){}
    try{renderCamera(row)}catch(e){}
    showImpact(row);updateTraffic(row);
    const rows=allRows();renderChooser(rows);makeSuggestedClickable(rows);
    if(scroll){const cam=$7('camPreview');if(cam)cam.scrollIntoView({behavior:'smooth',block:'center'});}
  }
  function sync(){
    const rows=allRows();if(!rows.length)return;
    if(!selectedSlug)selectedSlug=rows[0].s.slug;
    renderChooser(rows);makeSuggestedClickable(rows);
    const row=rows.find(x=>x.s.slug===selectedSlug)||rows[0];showImpact(row);updateTraffic(row);
  }

  // Pressing Enter in postcode now performs the same action as the button.
  const postcode=$7('postcode');
  if(postcode)postcode.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();const b=$7('usePostcode');if(b)b.click();}});

  // Keep the chooser synced whenever the older renderer refreshes after profile/location changes.
  const priorRender=render;
  render=function(){priorRender();setTimeout(sync,0);};
  ensureChooser();ensureTraffic();setTimeout(sync,40);
})();
</script>
HTML;
$html=str_replace('</body>',$js.'</body>',$html);

echo $html;
