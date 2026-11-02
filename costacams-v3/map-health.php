<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/map.php';
$html=ob_get_clean();

// Camera health: let MySQL decide freshness so DB/PHP timezone differences
// cannot incorrectly turn a freshly checked camera into "unknown".
$cameraHealth=[];
$verifiedLive=0;
try{
    $pdo=cc_db();
    if($pdo && $pdo->query("SHOW TABLES LIKE 'stream_health'")->fetchColumn()){
        $rows=$pdo->query("SELECT l.slug,s.id AS stream_id,s.priority,sh.status,sh.checked_at,sh.last_ok_at,sh.consecutive_failures,
          CASE WHEN sh.checked_at IS NOT NULL AND sh.checked_at>=DATE_SUB(NOW(),INTERVAL 10 MINUTE) THEN 1 ELSE 0 END AS is_fresh
          FROM locations l
          JOIN streams s ON s.location_id=l.id AND s.active=1 AND s.stream_type IN ('hls','iframe') AND s.stream_url IS NOT NULL AND s.stream_url<>''
          LEFT JOIN stream_health sh ON sh.stream_id=s.id
          WHERE l.active=1
          ORDER BY l.id,s.priority,s.id")->fetchAll();
        foreach($rows as $r){
            $slug=(string)$r['slug'];
            if(isset($cameraHealth[$slug])) continue;
            $state=((int)$r['is_fresh']===1)?(((string)$r['status']==='ok')?'ok':'down'):'unknown';
            if($state==='ok') $verifiedLive++;
            $cameraHealth[$slug]=[
                'status'=>$state,
                'checked_at'=>(string)($r['checked_at']??''),
                'last_ok_at'=>(string)($r['last_ok_at']??''),
                'failures'=>(int)($r['consecutive_failures']??0),
            ];
        }
    }
}catch(Throwable $e){}

// Shark coverage: one marker for every CostaCams beach/spot. A marker without a
// verified report is deliberately neutral/grey and never implies the beach is safe.
function cc_cov_key(string $s): string {
    $s=strtolower($s);
    $s=preg_replace('/\b(the|beach|head|bay|qld|nsw)\b/',' ',$s)??$s;
    return preg_replace('/[^a-z0-9]+/','',$s)??'';
}
function cc_region_key(string $region): string {
    return match($region){'Gold Coast'=>'gold-coast','Tweed Coast'=>'tweed-coast','Byron Shire'=>'byron-shire',default=>''};
}
$sharkCoverage=[];$usedAlertIds=[];
try{
    $pdo=cc_db();
    $alerts=[];
    if($pdo && $pdo->query("SHOW TABLES LIKE 'shark_alerts'")->fetchColumn()){
        $alerts=$pdo->query("SELECT * FROM shark_alerts ORDER BY COALESCE(observed_at,verified_at,created_at) DESC,id DESC")->fetchAll();
    }
    foreach(($locations??[]) as $loc){
        $rk=cc_region_key((string)$loc['region']);
        $keys=[cc_cov_key((string)$loc['slug']),cc_cov_key((string)$loc['name'])];
        foreach(preg_split('~[/,]~',(string)$loc['name'])?:[] as $part){$k=cc_cov_key(trim($part));if($k!=='')$keys[]=$k;}
        $keys=array_values(array_unique(array_filter($keys,fn($v)=>strlen($v)>=3)));
        $match=null;
        foreach($alerts as $a){
            if((string)($a['region_key']??'')!==$rk)continue;
            $ak=cc_cov_key((string)($a['locality']??''));if($ak==='')continue;
            $ok=false;
            foreach($keys as $k){if($ak===$k || (strlen($k)>=5 && (str_contains($ak,$k)||str_contains($k,$ak)))){$ok=true;break;}}
            if($ok){$match=$a;break;}
        }
        if($match){
            $usedAlertIds[(int)$match['id']]=true;
            $when=(string)($match['observed_at']??$match['verified_at']??$match['created_at']??'');$ts=$when!==''?strtotime($when):false;
            $sharkCoverage[]=[
                'has_report'=>true,'region'=>(string)$loc['region'],'region_key'=>$rk,'locality'=>(string)$loc['name'],
                'lat'=>(float)$loc['lat'],'lon'=>(float)$loc['lon'],'title'=>(string)$match['title'],'detail'=>(string)$match['detail'],
                'source_name'=>(string)$match['source_name'],'source_url'=>(string)$match['source_url'],'observed_at'=>$when,
                'date_label'=>$ts===false?'Unknown date':date('j M Y',$ts),'age_minutes'=>$ts===false?null:max(0,(int)floor((time()-$ts)/60)),
                'active'=>(bool)$match['active']
            ];
        }else{
            $sharkCoverage[]=[
                'has_report'=>false,'region'=>(string)$loc['region'],'region_key'=>$rk,'locality'=>(string)$loc['name'],
                'lat'=>(float)$loc['lat'],'lon'=>(float)$loc['lon'],'title'=>'No verified shark report stored for this beach yet',
                'detail'=>'CostaCams has not yet matched a verified shark notification to this exact beach. This does not mean there are no sharks or that the beach is safe.',
                'source_name'=>'CostaCams monitoring','source_url'=>'','observed_at'=>'','date_label'=>'','age_minutes'=>null,'active'=>false
            ];
        }
    }
    // Preserve verified reports for nearby localities that are not yet CostaCams channel spots (e.g. Clarkes Beach).
    foreach($alerts as $a){
        $id=(int)($a['id']??0);if(isset($usedAlertIds[$id]))continue;
        $lat=isset($a['latitude'])&&$a['latitude']!==null?(float)$a['latitude']:null;$lon=isset($a['longitude'])&&$a['longitude']!==null?(float)$a['longitude']:null;
        if($lat===null||$lon===null)continue;
        $when=(string)($a['observed_at']??$a['verified_at']??$a['created_at']??'');$ts=$when!==''?strtotime($when):false;
        $rn=match((string)$a['region_key']){'gold-coast'=>'Gold Coast','tweed-coast'=>'Tweed Coast','byron-shire'=>'Byron Shire',default=>(string)$a['region_key']};
        $sharkCoverage[]=['has_report'=>true,'region'=>$rn,'region_key'=>(string)$a['region_key'],'locality'=>(string)($a['locality']??$rn),'lat'=>$lat,'lon'=>$lon,'title'=>(string)$a['title'],'detail'=>(string)$a['detail'],'source_name'=>(string)$a['source_name'],'source_url'=>(string)$a['source_url'],'observed_at'=>$when,'date_label'=>$ts===false?'Unknown date':date('j M Y',$ts),'age_minutes'=>$ts===false?null:max(0,(int)floor((time()-$ts)/60)),'active'=>(bool)$a['active']];
    }
}catch(Throwable $e){}

$healthJson=json_encode($cameraHealth,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$sharkJson=json_encode($sharkCoverage,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$extraCss=<<<'HTML'
<style id="cc-camera-health-style">
.camera-pin.down,.camera-pin.unknown{background:#69737a!important;box-shadow:0 2px 8px #0008!important}.dot.down{background:#7d878d}.pop .tag.offline{color:#aab3b8}.preview.offline{background:#15191b;color:#a9b2b7;border:1px solid #343b3e;text-align:center;padding:18px}.btn.conditions{border-color:#6e7a80;color:#d4dadd}.shark-pin.age-none,.shark-age.age-none{background:#22282b;border-color:#626d72;color:#aab2b6;opacity:.72}.shark-wrap.no-report{transform:scale(.82);transform-origin:center}.shark-wrap.no-report .shark-age{display:none}
</style>
HTML;
$html=str_replace('</head>',$extraCss.'</head>',$html);
$html=str_replace('<span><i class="dot live"></i>Live camera</span><span><i class="dot forecast"></i>Forecast / camera being verified</span>', '<span><i class="dot live"></i>Verified live camera</span><span><i class="dot down"></i>Camera temporarily unavailable</span><span><i class="dot forecast"></i>Forecast / no verified camera</span>', $html);
$html=preg_replace_callback('~<div class="stats">(\d+) locations · \d+ live feeds</div>~',fn($m)=>'<div class="stats">'.$m[1].' locations · '.$verifiedLive.' verified live feeds</div>',$html,1)??$html;
$html=preg_replace('~<span>🦈.*?</span>~','<span>🦈 One shark-status marker per beach · coloured when a verified report exists · grey/blank means no matched report stored, not “no sharks”</span>',$html,1)??$html;

$inject=<<<HTML
const cameraHealth={$healthJson};
const ccSharkCoverage={$sharkJson};
sharkReports.splice(0,sharkReports.length,...ccSharkCoverage);
function ccCameraState(cam){
  if(!cam.live)return 'forecast';
  const h=cameraHealth[cam.slug];
  if(!h||h.status==='unknown')return 'unknown';
  return h.status==='ok'?'live':'down';
}
iconFor=function(cam){const state=ccCameraState(cam);return L.divIcon({className:'camera-marker',html:`<div class="camera-pin \${state}"><span>📹</span></div>`,iconSize:[34,34],iconAnchor:[17,34],popupAnchor:[0,-31]});};
popupHtml=function(cam){
  const state=ccCameraState(cam);
  if(state==='live')return `<div class="pop"><div class="tag">LIVE CAMERA · \${esc(cam.region)}</div><h3>\${esc(cam.name)}</h3><div class="preview" id="preview-\${esc(cam.slug)}">Loading preview…</div><div class="pop-actions"><a class="btn primary" href="/\${encodeURIComponent(cam.slug)}">Open channel</a></div></div>`;
  if(state==='forecast')return `<div class="pop"><div class="tag">FORECAST CHANNEL · \${esc(cam.region)}</div><h3>\${esc(cam.name)}</h3><div class="preview offline">A verified live camera is not available for this location yet.</div><div class="pop-actions"><a class="btn conditions" href="/\${encodeURIComponent(cam.slug)}">Beach conditions</a></div></div>`;
  const msg=state==='down'?'Camera temporarily unavailable. We recheck it automatically every 5 minutes.':'Camera status is being rechecked. It will turn green automatically once verified.';
  return `<div class="pop"><div class="tag offline">CAMERA TEMPORARILY OFFLINE · \${esc(cam.region)}</div><h3>\${esc(cam.name)}</h3><div class="preview offline">\${esc(msg)}</div><div class="pop-actions"><a class="btn conditions" href="/\${encodeURIComponent(cam.slug)}">Beach conditions</a></div></div>`;
};
const ccLoadPreviewHealthy=loadPreview;
loadPreview=function(cam){if(ccCameraState(cam)!=='live')return;return ccLoadPreviewHealthy(cam);};
sharkAgeClass=function(mins){if(mins===null||mins===undefined)return 'age-none';if(mins<12*60)return 'age-red';if(mins<48*60)return 'age-orange-dark';if(mins<7*24*60)return 'age-orange-light';if(mins<30*24*60)return 'age-green';return 'age-gray';};
ageText=function(r,long=false){if(!r.has_report)return long?'No verified report stored for this exact beach yet':'—';const mins=r.age_minutes;if(mins===null||mins===undefined)return long?'Time unavailable':'?';if(mins<60)return long?'Less than 1 hour ago':'<1h';if(mins<72*60){const h=Math.max(1,Math.floor(mins/60));return long?`\${h} hour\${h===1?'':'s'} ago`:`\${h}h`;}if(mins<30*24*60){const d=Math.floor(mins/1440),h=Math.floor((mins%1440)/60);if(long)return `\${d} day\${d===1?'':'s'}\${h?` \${h} h`:''} ago`;return `\${d}d\${h?` \${h}h`:''}`;}return r.date_label||r.observed_at||'Older report';};
sharkIcon=function(r){const cls=sharkAgeClass(r.age_minutes),none=!r.has_report;return L.divIcon({className:'shark-marker',html:`<div class="shark-wrap \${none?'no-report':''}"><div class="shark-pin \${cls}"><span>🦈</span></div><b class="shark-age \${cls}">\${esc(ageText(r,false))}</b></div>`,iconSize:[66,60],iconAnchor:[8,30],popupAnchor:[25,-22]});};
sharkPopup=function(r){if(!r.has_report)return `<div class="pop"><div class="tag shark">🦈 SHARK MONITORING · \${esc(r.region)}</div><h3>\${esc(r.locality)}</h3><p><b>No verified shark notification stored for this exact beach yet.</b></p><p>This is not a safety clearance and does not mean there are no sharks. CostaCams will colour this marker automatically when a verified report is matched to this beach.</p></div>`;return `<div class="pop"><div class="tag shark">🦈 LATEST SHARK NOTIFICATION · \${esc(r.region)}</div><h3>\${esc(r.locality)}</h3><p><b>\${esc(r.title)}</b></p><p>\${esc(r.detail)}</p><p><strong>\${esc(ageText(r,true))}</strong>\${r.active?' · alert currently marked active':' · latest stored notification for this beach'}</p><div class="shark-source">Source: <a href="\${esc(r.source_url)}" target="_blank" rel="noopener">\${esc(r.source_name)}</a></div></div>`;};
HTML;
$html=str_replace('addMarkers();',$inject."\naddMarkers();",$html);

echo $html;
