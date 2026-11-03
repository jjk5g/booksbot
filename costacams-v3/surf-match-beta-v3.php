<?php
declare(strict_types=1);
ob_start();
require __DIR__.'/surf-match-beta-v2.php';
$html=ob_get_clean();

$html=str_replace('Surf Match Beta v2 | CostaCams','Surf Match Beta v3 | CostaCams',$html);
$html=str_replace('SURF MATCH BETA v2','SURF MATCH BETA v3',$html);
$html=str_replace('No street address needed. Your browser can share a location point only when you choose.','Choose browser location or just enter a postcode. No street address is requested.',$html);

$old='<button class="action" id="useLocation" type="button" style="margin-top:9px">Use my location</button>';
$new=$old.'<div class="ccv3-or">or</div><div class="ccv3-postcode"><input id="postcode" inputmode="numeric" maxlength="4" placeholder="Postcode e.g. 4218"><button class="action" id="usePostcode" type="button">Use postcode</button></div><div class="tiny ccv3-route-note" id="routeSource">Road travel time will be calculated after you set a starting area.</div>';
$html=str_replace($old,$new,$html);

$oldBtn='<button class="action" id="subscribeBtn" style="width:100%;margin-top:8px">Save profile & email me</button>';
$newBtn='<button class="action" id="sampleBtn" type="button" style="width:100%;margin-top:8px">Send me a sample now</button><button class="action ccv3-primary" id="subscribeBtn" type="button" style="width:100%;margin-top:7px">Subscribe & save profile</button><button class="action" id="editSavedBtn" type="button" style="width:100%;margin-top:7px;display:none">Edit my saved profile</button>';
$html=str_replace($oldBtn,$newBtn,$html);
$html=str_replace('Save this profile in the beta database. No password: your email contains a private manage link.','Try a real sample email first, or subscribe and save this profile. No password: your private manage link opens the saved profile for editing.',$html);
$html=str_replace('You must confirm your email before recurring beta briefs are sent. Every email includes Manage Profile and Unsubscribe. This beta is for testing and may change.','“Send sample” sends one example only and does not subscribe you. Recurring briefs require email confirmation. Every recurring email includes Edit Profile and Unsubscribe.',$html);

$css=<<<'HTML'
<style id="cc-surf-v3-style">
.ccv3-or{text-align:center;color:#65767c;font-size:10px;margin:8px 0}.ccv3-postcode{display:grid;grid-template-columns:1fr auto;gap:7px}.ccv3-postcode input{min-width:0;border:1px solid #334044;background:#080c0d;color:#e9f2f4;border-radius:10px;padding:9px 10px}.ccv3-route-note{margin-top:7px;color:#7e9299}.ccv3-primary{border-color:#50dcff!important;background:#0c2025!important;color:#d5f9ff!important}.ccv3-sample{color:#bdeed0}.ccv3-edit-banner{margin:12px 0;padding:10px 12px;border:1px solid #315c4b;background:#0a1812;border-radius:11px;color:#c9efda;font-size:12px}
@media(max-width:430px){.ccv3-postcode{grid-template-columns:1fr}.ccv3-postcode .action{width:100%}}
</style>
HTML;
$html=str_replace('</head>',$css.'</head>',$html);

if(isset($_GET['manage']) && (string)$_GET['manage']!==''){
  $banner='<div class="ccv3-edit-banner">✏️ You are editing your saved Surf Profile. Change any mixer setting and press <b>Save profile changes</b>.</div>';
  $html=str_replace('<section class="layout">',$banner.'<section class="layout">',$html,1);
  $html=str_replace('Subscribe & save profile','Save profile changes',$html);
}

$js=<<<'HTML'
<script id="cc-surf-v3-js">
(function(){
  const api='surf-beta-v3-api.php';
  let roadTimes={},roadSource='';
  const fallbackTravel=travelMin;
  travelMin=function(s){const r=roadTimes[s.slug];return r&&Number.isFinite(Number(r.minutes))?Number(r.minutes):fallbackTravel(s);};

  async function post(data){
    const body=new URLSearchParams();Object.entries(data).forEach(([k,v])=>body.set(k,String(v)));
    const r=await fetch(api,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body,cache:'no-store'});
    let j={};try{j=await r.json()}catch(e){}if(!r.ok)throw new Error(j.message||'Request failed');return j;
  }
  function profileForEmail(){const p=Object.assign({},profile);p.origin_postcode=$('postcode').value.trim();if(!$('saveLocation').checked){p.origin_lat=null;p.origin_lon=null;p.origin_postcode='';}return p;}
  async function loadRoadTimes(){
    if(profile.origin_lat==null||profile.origin_lon==null)return;
    $('routeSource').textContent='Calculating road drive times to all beaches…';
    try{
      const destinations=spots.map(s=>({slug:s.slug,lat:s.lat,lon:s.lon}));
      const j=await post({action:'route_table',origin_lat:profile.origin_lat,origin_lon:profile.origin_lon,destinations:JSON.stringify(destinations)});
      roadTimes=j.routes||{};roadSource=j.source||'';
      $('routeSource').textContent=roadSource==='road_route'?'Road driving times loaded and included in the recommendation.':'Road router unavailable — using an approximate drive estimate for this beta.';
      refresh();
    }catch(e){$('routeSource').textContent='Could not load road routes — using approximate travel time.';refresh();}
  }

  $('usePostcode').onclick=async()=>{
    const pc=$('postcode').value.trim();$('locationState').textContent='Finding postcode area…';
    try{const j=await post({action:'postcode',postcode:pc});profile.origin_lat=Number(j.lat);profile.origin_lon=Number(j.lon);profile.origin_postcode=j.postcode;$('postcode').value=j.postcode;$('locationState').textContent='Starting area set to postcode '+j.postcode+'. No street address was requested.';roadTimes={};await loadRoadTimes();}
    catch(e){$('locationState').textContent=e.message||'Could not find that postcode.';}
  };

  $('useLocation').onclick=()=>{
    if(!navigator.geolocation){$('locationState').textContent='Location is not supported in this browser. Use postcode instead.';return;}
    $('locationState').textContent='Requesting location permission…';
    navigator.geolocation.getCurrentPosition(async pos=>{profile.origin_lat=Number(pos.coords.latitude.toFixed(5));profile.origin_lon=Number(pos.coords.longitude.toFixed(5));profile.origin_postcode='';$('postcode').value='';$('locationState').textContent='Location point set. No street address was requested.';roadTimes={};await loadRoadTimes();},()=>{$('locationState').textContent='Location not shared. You can use a postcode instead.';},{enableHighAccuracy:false,timeout:8000,maximumAge:300000});
  };

  $('sampleBtn').onclick=async()=>{
    const email=$('email').value.trim();if(!email){$('emailMsg').textContent='Enter your email to receive the sample.';return;}
    $('emailMsg').textContent='Building and sending your one-time sample…';
    try{const j=await post({action:'sample',email,profile:JSON.stringify(profileForEmail()),website:$('website').value});$('emailMsg').innerHTML='<span class="ccv3-sample">'+(j.message||'Sample sent.')+'</span>';}
    catch(e){$('emailMsg').textContent=e.message||'Could not send the sample.';}
  };

  $('subscribeBtn').onclick=async()=>{
    const email=$('email').value.trim();if(!email){$('emailMsg').textContent='Enter your email first.';return;}
    $('emailMsg').textContent='Saving profile…';
    try{const j=await post({action:'subscribe',email,profile:JSON.stringify(profileForEmail()),frequency:$('frequency').value,website:$('website').value});if(j.manage_url){try{localStorage.setItem('ccSurfManageUrl',j.manage_url)}catch(e){}showEditButton(j.manage_url);}$('emailMsg').textContent=j.message||'Saved.';}
    catch(e){$('emailMsg').textContent=e.message||'Could not save the profile.';}
  };

  function showEditButton(url){const b=$('editSavedBtn');if(!b)return;b.style.display='block';b.onclick=()=>{if(url)location.href=url;};}
  const params=new URLSearchParams(location.search);
  if(params.has('manage')){try{localStorage.setItem('ccSurfManageUrl',location.href)}catch(e){}showEditButton(location.href);$('editSavedBtn').textContent='Reload my saved profile';}
  else{try{const u=localStorage.getItem('ccSurfManageUrl');if(u)showEditButton(u);}catch(e){}}

  if(initial.origin_postcode)$('postcode').value=initial.origin_postcode;
  if(profile.origin_lat!=null&&profile.origin_lon!=null)setTimeout(loadRoadTimes,250);
})();
</script>
HTML;
$html=str_replace('</body>',$js.'</body>',$html);
echo $html;
