<?php
declare(strict_types=1);
ob_start();
require __DIR__.'/surf-match-beta-v3.php';
$html=ob_get_clean();
$html=str_replace('Surf Match Beta v3 | CostaCams','Surf Match Beta v4 | CostaCams',$html);
$html=str_replace('SURF MATCH BETA v3','SURF MATCH BETA v4',$html);
$html=str_replace("const api='surf-beta-v3-api.php'","const api='surf-beta-v4-api.php'",$html);

$boardBlock=<<<'HTML'
<div class="group ccv4-board-profile">
  <div class="labelrow"><b>Your body & board</b><span class="value" id="floatLabel">Set your fit</span></div>
  <div class="ccv4-board-visual">
    <div class="ccv4-person"><div class="ccv4-head"></div><div class="ccv4-body"></div><div class="ccv4-arms"></div><div class="ccv4-legs"></div><small id="personLabel">175 cm</small></div>
    <div class="ccv4-board-wrap"><div class="ccv4-board" id="boardShape"></div><small id="boardVisualLabel">7'0 · 45 L</small></div>
  </div>
  <div class="simple-note">Compare the board with your body. A longer / higher-volume board generally paddles and floats more easily; a smaller / lower-volume board usually needs more technique.</div>
  <div class="ccv4-grid3">
    <label><span>Your height</span><input class="range" id="heightCm" type="range" min="145" max="210" step="1" value="175"><b id="heightOut">175 cm</b></label>
    <label><span>Your weight</span><input class="range" id="weightKg" type="range" min="40" max="130" step="1" value="75"><b id="weightOut">75 kg</b></label>
    <label><span>Board volume</span><input class="range" id="boardLitres" type="range" min="18" max="100" step="1" value="45"><b id="litresOut">45 L</b></label>
  </div>
  <div class="ccv4-fit" id="boardFit"></div>
  <div class="advanced-only ccv4-advanced-fit" id="advancedFit"></div>
</div>
HTML;
$html=str_replace('</aside><section class="panel results">',$boardBlock.'</aside><section class="panel results">',$html,1);
$html=str_replace('<button class="action" id="editSavedBtn" type="button" style="width:100%;margin-top:7px;display:none">Edit my saved profile</button>','<button class="action" id="editSavedBtn" type="button" style="width:100%;margin-top:7px;display:none">Edit my saved profile</button><button class="action" id="requestManageBtn" type="button" style="width:100%;margin-top:7px">Email me my profile link</button>',$html);
$html=str_replace('“Send sample” sends one example only and does not subscribe you.','“Send sample” is limited to one attempt per email per day and does not subscribe you.',$html);

$css=<<<'HTML'
<style id="cc-surf-v4-style">
.ccv4-board-profile{border-top-color:#31515a}.ccv4-board-visual{height:190px;display:flex;justify-content:center;align-items:flex-end;gap:42px;padding:12px;border:1px solid #25363b;border-radius:14px;background:linear-gradient(180deg,#0b171b,#080b0c);overflow:hidden}.ccv4-person{height:145px;width:55px;position:relative;display:flex;justify-content:center}.ccv4-head{position:absolute;top:1px;width:20px;height:20px;border:2px solid #dce8eb;border-radius:50%}.ccv4-body{position:absolute;top:23px;width:3px;height:65px;background:#dce8eb}.ccv4-arms{position:absolute;top:41px;width:46px;height:3px;background:#dce8eb}.ccv4-legs{position:absolute;top:86px;width:35px;height:48px;border-left:3px solid #dce8eb;border-right:3px solid #dce8eb;transform:perspective(30px) rotateX(-6deg)}.ccv4-person small,.ccv4-board-wrap small{position:absolute;bottom:-2px;white-space:nowrap;color:#9fb1b6;font-size:10px}.ccv4-board-wrap{height:160px;width:70px;position:relative;display:flex;align-items:flex-end;justify-content:center}.ccv4-board{height:135px;width:23px;border:2px solid #50dcff;border-radius:55% 55% 48% 48%;background:linear-gradient(90deg,#0e6578,#43d4f5 50%,#0e6578);box-shadow:0 0 14px #50dcff22;transition:.18s}.ccv4-grid3{display:grid;grid-template-columns:1fr;gap:8px;margin-top:11px}.ccv4-grid3 label{display:grid;grid-template-columns:88px 1fr 55px;gap:7px;align-items:center;color:#9dacb0;font-size:10px}.ccv4-grid3 b{text-align:right;color:#dff7fc}.ccv4-fit{margin-top:9px;padding:9px 10px;border:1px solid #2a3d42;border-radius:10px;background:#080d0f;color:#c4d4d8;font-size:11px}.ccv4-advanced-fit{margin-top:7px;color:#8ea2a8;font-size:10px}.ccv4-mail-warning{color:#ffc56f}.ccv4-mail-ok{color:#9de6bb}.ccv4-disabled{opacity:.5;cursor:not-allowed!important}
</style>
HTML;
$html=str_replace('</head>',$css.'</head>',$html);

$js=<<<'HTML'
<script id="cc-surf-v4-js">
(function(){
  const api4='surf-beta-v4-api.php';
  const manageToken=(new URLSearchParams(location.search)).get('manage')||'';
  profile.height_cm=Number(initial.height_cm||profile.height_cm||175);
  profile.weight_kg=Number(initial.weight_kg||profile.weight_kg||75);
  profile.board_litres=Number(initial.board_litres||profile.board_litres||45);
  $('heightCm').value=profile.height_cm;$('weightKg').value=profile.weight_kg;$('boardLitres').value=profile.board_litres;

  function volumeRange(){let k={learning:[.62,.85],competent:[.45,.65],good:[.34,.50],very_good:[.28,.42]}[profile.level]||[.45,.65];let adj=profile.board==='softboard'?1.12:(profile.board==='longboard'?1.08:(profile.board==='midlength'?1.04:1));return [Math.round(profile.weight_kg*k[0]*adj),Math.round(profile.weight_kg*k[1]*adj)];}
  function updateBoardVisual(){
    profile.height_cm=Number($('heightCm').value);profile.weight_kg=Number($('weightKg').value);profile.board_litres=Number($('boardLitres').value);
    $('heightOut').textContent=profile.height_cm+' cm';$('weightOut').textContent=profile.weight_kg+' kg';$('litresOut').textContent=profile.board_litres+' L';$('personLabel').textContent=profile.height_cm+' cm';
    const boardCm=profile.board_length*30.48,rel=boardCm/profile.height_cm;const px=clamp(92*rel,72,158),w=clamp(17+profile.board_litres*.14,20,32);$('boardShape').style.height=px+'px';$('boardShape').style.width=w+'px';$('boardVisualLabel').textContent=profile.board_length.toFixed(1).replace('.0','')+"' · "+profile.board_litres+' L';
    const lpk=profile.board_litres/Math.max(1,profile.weight_kg),rg=volumeRange();let feel=profile.board_litres<rg[0]?'Lower flotation for your profile':(profile.board_litres>rg[1]?'High flotation / easier paddling':'Balanced flotation range');$('floatLabel').textContent=feel;
    $('boardFit').innerHTML='<b>'+feel+'</b><br>Rough beta guide for your weight + level: about <b>'+rg[0]+'–'+rg[1]+' L</b>. This is a fit guide, not a board prescription.';
    $('advancedFit').textContent='Volume / weight: '+lpk.toFixed(2)+' L/kg · board/body length ratio: '+rel.toFixed(2)+' · actual board shape, width, thickness and construction also matter.';
  }
  const baseScore=scoreSpot;scoreSpot=function(s,h){const z=baseScore(s,h),rg=volumeRange(),lit=profile.board_litres||45,w=Number(h.wave_height||0);let adj=0;if(lit<rg[0])adj-=Math.min(10,(rg[0]-lit)*.6);if(lit>rg[1]&&w<1.0)adj+=Math.min(7,(lit-rg[1])*.35);if(profile.board_length>=8&&w<.8)adj+=4;if(profile.board_length<=6.5&&w<.5)adj-=4;z.score=Math.round(clamp(z.score+adj,0,100));if(adj>=4)z.re=[...(z.re||[]),'board flotation suits smaller surf'].slice(0,3);return z;};
  ['heightCm','weightKg','boardLitres'].forEach(id=>$(id).addEventListener('input',()=>{updateBoardVisual();refresh();}));
  ['board','boardLen','level'].forEach(id=>$(id).addEventListener('input',()=>setTimeout(updateBoardVisual,0)));
  updateBoardVisual();setTimeout(refresh,0);

  async function post4(data){const body=new URLSearchParams();Object.entries(data).forEach(([k,v])=>body.set(k,String(v)));const r=await fetch(api4,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body,cache:'no-store'});let j={};try{j=await r.json()}catch(e){}if(!r.ok)throw new Error(j.message||'Request failed');return j;}
  function pForMail(){const p=Object.assign({},profile);p.origin_postcode=$('postcode').value.trim();if(!$('saveLocation').checked){p.origin_lat=null;p.origin_lon=null;p.origin_postcode='';}return p;}

  $('sampleBtn').onclick=async()=>{const email=$('email').value.trim();if(!email){$('emailMsg').textContent='Enter your email first.';return;}const b=$('sampleBtn');b.disabled=true;b.classList.add('ccv4-disabled');$('emailMsg').textContent='Requesting your one-time sample…';try{const j=await post4({action:'sample',email,profile:JSON.stringify(pForMail()),website:$('website').value});$('emailMsg').innerHTML='<span class="ccv4-mail-ok">'+j.message+'</span>';b.textContent='Sample requested today';try{localStorage.setItem('ccSampleDay:'+email.toLowerCase(),new Date().toISOString().slice(0,10));}catch(e){}}catch(e){$('emailMsg').innerHTML='<span class="ccv4-mail-warning">'+e.message+'</span>';b.textContent='Sample unavailable today';}finally{b.disabled=true;b.classList.add('ccv4-disabled');}};
  try{const e=$('email').value.trim();if(e&&localStorage.getItem('ccSampleDay:'+e.toLowerCase())===new Date().toISOString().slice(0,10)){$('sampleBtn').disabled=true;$('sampleBtn').classList.add('ccv4-disabled');$('sampleBtn').textContent='Sample requested today';}}catch(e){}

  $('subscribeBtn').onclick=async()=>{const email=$('email').value.trim();if(!email){$('emailMsg').textContent='Enter your email first.';return;}$('emailMsg').textContent=manageToken?'Saving profile changes…':'Saving subscription…';try{const j=await post4({action:'subscribe',email,profile:JSON.stringify(pForMail()),frequency:$('frequency').value,website:$('website').value,manage_token:manageToken});$('emailMsg').textContent=j.message||'Saved.';}catch(e){$('emailMsg').textContent=e.message||'Could not save.';}};
  $('requestManageBtn').onclick=async()=>{const email=$('email').value.trim();if(!email){$('emailMsg').textContent='Enter the email used for your Surf Profile.';return;}$('emailMsg').textContent='Requesting private profile link…';try{const j=await post4({action:'manage_request',email});$('emailMsg').textContent=j.message;}catch(e){$('emailMsg').textContent=e.message;}};
})();
</script>
HTML;
$html=str_replace('</body>',$js.'</body>',$html);
echo $html;
