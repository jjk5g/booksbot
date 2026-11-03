<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/surf-match-beta-v10.php';
$html=ob_get_clean();
$html=str_replace('Surf Match Beta v10 | CostaCams','Surf Match Beta v11 | CostaCams',$html);
$html=str_replace('SURF MATCH BETA v10','SURF MATCH BETA v11',$html);

$css=<<<'HTML'
<style id="cc-surf-v11-style">
#ccv10BomFrame{display:none!important}
.ccv11-bom-wrap{height:270px;display:grid;place-items:center;background:#050606;position:relative;overflow:hidden}
.ccv11-bom-img{width:100%;height:100%;object-fit:contain;display:block;background:#050606}
.ccv11-bom-status{position:absolute;left:9px;right:9px;bottom:9px;padding:6px 8px;border:1px solid #34484f;border-radius:8px;background:#071014df;color:#bfd0d4;font-size:9px;line-height:1.35;backdrop-filter:blur(4px)}
.ccv11-bom-status.error{border-color:#6d4933;color:#ffd0a2;background:#1b0f08e8}
@media(max-width:720px){.ccv11-bom-wrap{height:260px}}
</style>
HTML;
$html=str_replace('</head>',$css.'</head>',$html);

$js=<<<'HTML'
<script id="cc-surf-v11-js">
(function(){
  const $11=id=>document.getElementById(id);
  const radarBase='https://www.bom.gov.au/radar/IDR663.gif';
  const bomPage='https://www.bom.gov.au/weather-and-climate/rain-radar-and-weather-maps';
  let refreshTimer=null;

  function installBomImage(){
    const box=$11('ccv10BomBox');if(!box)return;
    const body=box.querySelector('.ccv10-mapbody');if(!body)return;
    const old=body.querySelector('iframe');if(old)old.remove();
    if($11('ccv11BomImg'))return;

    const wrap=document.createElement('div');wrap.className='ccv11-bom-wrap';
    wrap.innerHTML=`<img id="ccv11BomImg" class="ccv11-bom-img" alt="BOM Mt Stapylton 128 km rain radar"><div id="ccv11BomStatus" class="ccv11-bom-status">Loading official BOM Mt Stapylton 128 km radar…</div>`;
    body.replaceWith(wrap);

    const head=box.querySelector('.ccv10-boxhead span');
    if(head)head.innerHTML='BOM rain radar<small>Mt Stapylton 128 km · official Bureau radar image · refreshes every 5 min</small>';
    const open=box.querySelector('.ccv10-boxhead a');if(open)open.href=bomPage;

    const note=box.querySelector('.ccv10-bom-note');
    if(note)note.innerHTML='Official Bureau of Meteorology radar image. Mt Stapylton covers the Gold Coast, Tweed and Byron corridor. Radar shows observed rain, not a forecast. <a href="'+bomPage+'" target="_blank" rel="noopener">Open full BOM radar ↗</a>';

    const img=$11('ccv11BomImg'),status=$11('ccv11BomStatus');
    img.onload=()=>{
      const t=new Date();
      status.className='ccv11-bom-status';
      status.textContent='BOM radar loaded · refreshed '+t.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
    };
    img.onerror=()=>{
      status.className='ccv11-bom-status error';
      status.innerHTML='BOM radar image is temporarily unavailable. <a style="color:#8feaff" href="'+bomPage+'" target="_blank" rel="noopener">Open BOM radar ↗</a>';
    };
    refreshRadar();
    clearInterval(refreshTimer);
    refreshTimer=setInterval(refreshRadar,5*60*1000);
  }

  function refreshRadar(){
    const img=$11('ccv11BomImg');if(!img)return;
    img.src=radarBase+'?v='+Date.now();
  }

  installBomImage();
  setTimeout(installBomImage,120);
  setTimeout(installBomImage,700);
})();
</script>
HTML;
$html=str_replace('</body>',$js.'</body>',$html);

echo $html;
