<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/surf-match-beta-v10.php';
$html=ob_get_clean();
$html=str_replace('Surf Match Beta v10 | CostaCams','Surf Match Beta v12 | CostaCams',$html);
$html=str_replace('SURF MATCH BETA v10','SURF MATCH BETA v12',$html);

$css=<<<'HTML'
<style id="cc-surf-v12-style">
#ccv10BomBox .ccv10-mapbody{position:relative}
.ccv12-weather-tabs{display:flex;gap:6px;flex-wrap:wrap}
.ccv12-weather-tab{border:1px solid #334044;background:#0a0e0f;color:#cbd8db;border-radius:9px;padding:6px 9px;font-size:9px;font-weight:800;cursor:pointer}
.ccv12-weather-tab.active{border-color:#50dcff;background:#0c2025;color:#d0f7ff}
#ccv12WeatherFrame{width:100%;height:100%;min-height:270px;border:0;display:block;background:#0b0d0e}
.ccv12-weather-note{padding:7px 10px;border-top:1px solid #20282b;background:#080c0d;color:#7f9298;font-size:9px;line-height:1.4}
.ccv12-weather-note a{color:#8feaff}
@media(max-width:720px){#ccv12WeatherFrame{min-height:260px}}
</style>
HTML;
$html=str_replace('</head>',$css.'</head>',$html);

$js=<<<'HTML'
<script id="cc-surf-v12-js">
(function(){
  const $12=id=>document.getElementById(id);
  let weatherLayer='radar';

  function selectedSpot(){
    const name=($12('winnerName')?.textContent||'').trim();
    if(typeof spots==='undefined'||!Array.isArray(spots))return null;
    return spots.find(s=>s.name===name)||spots[0]||null;
  }

  function windySrc(layer,spot){
    const lat=Number(spot?.lat ?? -28.0167),lon=Number(spot?.lon ?? 153.4);
    const base='https://embed.windy.com/embed.html?type=map&location=coordinates&metricRain=mm&metricTemp=%C2%B0C&metricWind=km%2Fh&zoom=9&level=surface&marker=true';
    if(layer==='clouds')return `${base}&overlay=clouds&product=ecmwf&lat=${lat.toFixed(5)}&lon=${lon.toFixed(5)}&detailLat=${lat.toFixed(5)}&detailLon=${lon.toFixed(5)}`;
    return `${base}&overlay=radar&product=radar&lat=${lat.toFixed(5)}&lon=${lon.toFixed(5)}&detailLat=${lat.toFixed(5)}&detailLon=${lon.toFixed(5)}`;
  }

  function installWeatherPanel(){
    const box=$12('ccv10BomBox');if(!box)return;
    const head=box.querySelector('.ccv10-boxhead');
    const body=box.querySelector('.ccv10-mapbody');
    if(head){
      head.innerHTML=`<span>Weather around selected beach<small>Rain radar + cloud view centred on the beach</small></span><div class="ccv12-weather-tabs"><button type="button" class="ccv12-weather-tab active" data-weather-layer="radar">Rain radar</button><button type="button" class="ccv12-weather-tab" data-weather-layer="clouds">Clouds</button></div>`;
    }
    if(body){body.innerHTML='<iframe id="ccv12WeatherFrame" title="Weather around selected beach" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';}
    const note=box.querySelector('.ccv10-bom-note');
    if(note)note.outerHTML='<div class="ccv12-weather-note">Weather map by <a href="https://www.windy.com/" target="_blank" rel="noopener">Windy.com</a>. The map automatically follows the selected beach. Use <b>Rain radar</b> for current precipitation and <b>Clouds</b> for cloud cover.</div>';
    document.querySelectorAll('[data-weather-layer]').forEach(btn=>btn.addEventListener('click',()=>{
      weatherLayer=btn.dataset.weatherLayer||'radar';
      document.querySelectorAll('[data-weather-layer]').forEach(x=>x.classList.toggle('active',x===btn));
      updateWeather();
    }));
  }

  function updateWeather(){
    const f=$12('ccv12WeatherFrame'),spot=selectedSpot();if(!f||!spot)return;
    const src=windySrc(weatherLayer,spot);
    if(f.dataset.src!==src){f.src=src;f.dataset.src=src;}
  }

  installWeatherPanel();
  updateWeather();
  setTimeout(updateWeather,250);
  setTimeout(updateWeather,900);

  const winner=$12('winnerName');
  if(winner){new MutationObserver(()=>setTimeout(updateWeather,30)).observe(winner,{childList:true,subtree:true,characterData:true});}
})();
</script>
HTML;
$html=str_replace('</body>',$js.'</body>',$html);

echo $html;
