<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/surf-match-beta-v9.php';
$html=ob_get_clean();
$html=str_replace('Surf Match Beta v9 | CostaCams','Surf Match Beta v10 | CostaCams',$html);
$html=str_replace('SURF MATCH BETA v9','SURF MATCH BETA v10',$html);

$css=<<<'HTML'
<style id="cc-surf-v10-style">
/* v10: aligned route/camera + Waze/BOM weather grid. */
#ccv7Traffic{display:none!important}
.ccv10-second-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px}
.ccv10-mapbox{border:1px solid var(--line);border-radius:16px;overflow:hidden;background:#090c0d;min-width:0}
.ccv10-boxhead{min-height:42px;padding:9px 12px;border-bottom:1px solid #20282b;display:flex;justify-content:space-between;gap:10px;align-items:center;font-size:11px;font-weight:850;color:#cdd8dc}
.ccv10-boxhead span{display:grid;gap:1px}.ccv10-boxhead small{font-size:9px;font-weight:500;color:#8fa2a8}
.ccv10-boxhead a{font-size:9px;color:#8feaff;text-decoration:none;white-space:nowrap}
.ccv10-mapbody{height:270px;background:#050606;position:relative;overflow:hidden}
.ccv10-mapbody iframe{width:100%!important;height:100%!important;min-height:270px!important;border:0!important;display:block!important;background:#0b0d0e!important}
.ccv10-fallback{position:absolute;inset:auto 10px 10px 10px;z-index:2;padding:7px 9px;border:1px solid #34484f;border-radius:9px;background:#071014dd;color:#a9bdc3;font-size:9px;pointer-events:none}
.ccv10-bom-note{padding:7px 10px;border-top:1px solid #20282b;background:#080c0d;color:#7f9298;font-size:9px;line-height:1.4}
.ccv10-bom-note a{color:#8feaff}
@media(max-width:720px){.ccv10-second-row{grid-template-columns:1fr}.ccv10-mapbody{height:260px}.ccv10-mapbody iframe{min-height:260px!important}}
</style>
HTML;
$html=str_replace('</head>',$css.'</head>',$html);

$js=<<<'HTML'
<script id="cc-surf-v10-js">
(function(){
  const $10=id=>document.getElementById(id);
  const bomUrl='https://www.bom.gov.au/weather-and-climate/rain-radar-and-weather-maps?locations=true&looping=1&range=true&topography=true';

  function ensureSecondRow(){
    if($10('ccv10SecondRow'))return;
    const detail=document.querySelector('.spot-detail');if(!detail)return;
    const row=document.createElement('div');row.id='ccv10SecondRow';row.className='ccv10-second-row';
    row.innerHTML=`
      <div class="ccv10-mapbox" id="ccv10WazeBox">
        <div class="ccv10-boxhead"><span>Waze live traffic<small>Route/traffic view for the selected beach</small></span><a id="ccv10WazeOpen" href="#" target="_blank" rel="noopener">Open Waze ↗</a></div>
        <div class="ccv10-mapbody" id="ccv10WazeBody"></div>
      </div>
      <div class="ccv10-mapbox" id="ccv10BomBox">
        <div class="ccv10-boxhead"><span>BOM rain radar<small>Live Bureau of Meteorology rain radar / weather map</small></span><a href="${bomUrl}" target="_blank" rel="noopener">Open BOM ↗</a></div>
        <div class="ccv10-mapbody"><iframe id="ccv10BomFrame" title="Bureau of Meteorology rain radar" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="${bomUrl}"></iframe></div>
        <div class="ccv10-bom-note">Official Bureau of Meteorology radar/weather map. The current BOM viewer uses a national radar mosaic, so one map covers the Gold Coast, Tweed and Byron corridor. If the Bureau blocks embedded viewing in your browser, use <a href="${bomUrl}" target="_blank" rel="noopener">Open BOM</a>.</div>
      </div>`;
    detail.insertAdjacentElement('afterend',row);
    moveWazeIntoGrid();
  }

  function moveWazeIntoGrid(){
    const body=$10('ccv10WazeBody');if(!body)return;
    const frame=$10('ccv7WazeFrame');
    if(frame&&frame.parentElement!==body){
      frame.classList.add('show');frame.style.display='block';
      frame.style.width='100%';frame.style.height='100%';
      body.appendChild(frame);
    }
    const legacyOpen=$10('ccv7WazeOpen'),open=$10('ccv10WazeOpen');
    if(open&&legacyOpen&&legacyOpen.href)open.href=legacyOpen.href;
  }

  function selectedSpot(){
    const name=($10('winnerName')?.textContent||'').trim();
    if(typeof spots==='undefined'||!Array.isArray(spots))return null;
    return spots.find(s=>s.name===name)||null;
  }

  function syncWazeLink(){
    const spot=selectedSpot(),open=$10('ccv10WazeOpen');if(!spot||!open)return;
    const lat=Number(spot.lat),lon=Number(spot.lon);
    open.href=`https://www.waze.com/ul?ll=${lat.toFixed(5)}%2C${lon.toFixed(5)}&navigate=yes&utm_source=costacams`;
    moveWazeIntoGrid();
  }

  ensureSecondRow();
  setTimeout(()=>{ensureSecondRow();moveWazeIntoGrid();syncWazeLink();},100);
  setTimeout(()=>{moveWazeIntoGrid();syncWazeLink();},700);

  const winner=$10('winnerName');
  if(winner){new MutationObserver(()=>setTimeout(()=>{syncWazeLink();moveWazeIntoGrid();},30)).observe(winner,{childList:true,subtree:true,characterData:true});}

  const traffic=$10('ccv7Traffic');
  if(traffic){new MutationObserver(()=>setTimeout(moveWazeIntoGrid,20)).observe(traffic,{childList:true,subtree:true});}
})();
</script>
HTML;
$html=str_replace('</body>',$js.'</body>',$html);

echo $html;
