<?php
declare(strict_types=1);

$isHome=empty($_GET['slug']);
$appCache='/tmp/costacams-app-home-v1.html';
if($isHome && empty($_GET['nocache']) && is_file($appCache) && time()-filemtime($appCache)<30){
  header('X-CostaCams-App-Cache: HIT');
  header('Cache-Control: public, max-age=15, stale-while-revalidate=45');
  readfile($appCache);exit;
}

// site.php has its own home cache. Disable that inner cache so this controller
// can add current freshness metadata, then cache the final HTML here.
$_GET['nocache']='1';
ob_start();
require __DIR__.'/site.php';
$html=ob_get_clean();

function cc_app_age(?string $dt): string {
  if(!$dt)return 'not available';
  try{$then=new DateTimeImmutable($dt,new DateTimeZone('Australia/Brisbane'));$now=new DateTimeImmutable('now',new DateTimeZone('Australia/Brisbane'));$sec=max(0,$now->getTimestamp()-$then->getTimestamp());}catch(Throwable $e){return 'unknown';}
  if($sec<60)return 'just now';$m=(int)floor($sec/60);if($m<60)return $m.' min ago';$h=(int)floor($m/60);if($h<24)return $h.' h ago';return (int)floor($h/24).' d ago';
}
function cc_app_clock(?string $dt): string {if(!$dt)return '—';try{return (new DateTimeImmutable($dt,new DateTimeZone('Australia/Brisbane')))->format('D j M, g:i a');}catch(Throwable $e){return '—';}}

$pdo=cc_db();$status=[];$cacheTimes=[];$sharkStatus=null;$sharkAlerts=[];
if($pdo){
  try{
    if($pdo->query("SHOW TABLES LIKE 'system_data_status'")->fetchColumn()){
      foreach($pdo->query("SELECT * FROM system_data_status")->fetchAll() as $r)$status[(string)$r['dataset']]=$r;
    }
    $slug=preg_replace('/[^a-z0-9-]/','',strtolower((string)($_GET['slug']??'')));
    if($slug!==''){
      $q=$pdo->prepare("SELECT cache_key,updated_at FROM forecast_cache WHERE cache_key IN (?,?)");$q->execute(['weather:'.$slug,'marine:'.$slug]);foreach($q->fetchAll() as $r)$cacheTimes[(string)$r['cache_key']]=$r['updated_at'];
      $loc=cc_location($slug);
      if($loc){
        $regionKey=match((string)$loc['region']){'Gold Coast'=>'gold-coast','Tweed Coast'=>'tweed-coast','Byron Shire'=>'byron-shire',default=>''};
        if($regionKey!=='' && $pdo->query("SHOW TABLES LIKE 'shark_status'")->fetchColumn()){$s=$pdo->prepare("SELECT * FROM shark_status WHERE region_key=?");$s->execute([$regionKey]);$sharkStatus=$s->fetch()?:null;}
        if($regionKey!=='' && $pdo->query("SHOW TABLES LIKE 'shark_alerts'")->fetchColumn()){$a=$pdo->prepare("SELECT * FROM shark_alerts WHERE region_key=? AND active=1 AND (expires_at IS NULL OR expires_at>=NOW()) ORDER BY COALESCE(observed_at,verified_at) DESC LIMIT 5");$a->execute([$regionKey]);$sharkAlerts=$a->fetchAll();}
      }
    }
  }catch(Throwable $e){}
}

$extraCss=<<<'CSS'
<style id="cc-data-freshness-style">
.cc-data-freshness{display:flex;flex-wrap:wrap;gap:8px 16px;margin:-24px 0 34px;padding:11px 14px;border:1px solid #202a2d;border-radius:12px;background:#090c0d;color:#839198;font-size:11px}.cc-data-freshness b{color:#b9c6ca}.cc-safety-alert{margin:14px 0 20px;padding:14px 16px;border:1px solid #7a3d36;border-left:4px solid #ff7468;border-radius:12px;background:#190d0b;color:#ffd7d2}.cc-safety-alert h3{margin:0 0 4px;font-size:15px}.cc-safety-alert p{margin:3px 0;font-size:13px}.cc-safety-alert a{color:#fff;text-decoration:underline}.cc-source-ok{color:#7edba4}.cc-source-warn{color:#ffc27a}
</style>
CSS;
$html=str_replace('</head>',$extraCss.'</head>',$html);

if(!$isHome){
  $slug=preg_replace('/[^a-z0-9-]/','',strtolower((string)($_GET['slug']??'')));
  $wt=$cacheTimes['weather:'.$slug]??($status['weather']['last_updated_at']??null);
  $mt=$cacheTimes['marine:'.$slug]??($status['marine']['last_updated_at']??null);
  $st=$sharkStatus['checked_at']??($status['shark_safety']['last_checked_at']??null);
  $freshLabel='Weather '.cc_app_age($wt).' · sea '.cc_app_age($mt).' · scheduled every 15 min';
  $html=str_replace('<span class="muted">Updated automatically</span>','<span class="muted">'.htmlspecialchars($freshLabel,ENT_QUOTES,'UTF-8').'</span>',$html);

  $sourceState=$sharkStatus?((int)$sharkStatus['source_reachable']===1?'official source reachable':'official source temporarily unreachable'):'official source status unavailable';
  $sourceClass=$sharkStatus&&((int)$sharkStatus['source_reachable']===1)?'cc-source-ok':'cc-source-warn';
  $sourceUrl=htmlspecialchars((string)($sharkStatus['source_url']??'https://www.sharksmart.nsw.gov.au/'),ENT_QUOTES,'UTF-8');
  $sourceName=htmlspecialchars((string)($sharkStatus['source_name']??'SharkSmart'),ENT_QUOTES,'UTF-8');
  $meta='<div class="cc-data-freshness"><span><b>Weather:</b> '.htmlspecialchars(cc_app_clock($wt),ENT_QUOTES,'UTF-8').' ('.htmlspecialchars(cc_app_age($wt),ENT_QUOTES,'UTF-8').')</span><span><b>Marine:</b> '.htmlspecialchars(cc_app_clock($mt),ENT_QUOTES,'UTF-8').' ('.htmlspecialchars(cc_app_age($mt),ENT_QUOTES,'UTF-8').')</span><span><b>Shark safety:</b> checked '.htmlspecialchars(cc_app_age($st),ENT_QUOTES,'UTF-8').' · <span class="'.$sourceClass.'">'.htmlspecialchars($sourceState,ENT_QUOTES,'UTF-8').'</span> · <a href="'.$sourceUrl.'" target="_blank" rel="noopener">'.$sourceName.'</a></span></div>';
  $html=preg_replace('~(<section class="section"><div class="section-head"><h2>Conditions now</h2>.*?</section>)~s','$1'.$meta,$html,1)??$html;

  if($sharkAlerts){
    $alertHtml='<div class="cc-safety-alert"><h3>⚠ Current shark / beach-safety alert</h3>';
    foreach($sharkAlerts as $a){$alertHtml.='<p><b>'.htmlspecialchars((string)$a['title'],ENT_QUOTES,'UTF-8').'</b>'.(!empty($a['locality'])?' · '.htmlspecialchars((string)$a['locality'],ENT_QUOTES,'UTF-8'):'').'<br>'.htmlspecialchars((string)$a['detail'],ENT_QUOTES,'UTF-8').' · <a href="'.htmlspecialchars((string)$a['source_url'],ENT_QUOTES,'UTF-8').'" target="_blank" rel="noopener">source</a></p>';}
    $alertHtml.='</div>';
    $html=str_replace('<section class="section"><div class="section-head"><h2>Conditions now</h2>',$alertHtml.'<section class="section"><div class="section-head"><h2>Conditions now</h2>',$html);
  }
}else{
  $contentUpdated=$status['content']['last_updated_at']??null;
  $contentText=$contentUpdated?'Updated daily · last import '.cc_app_age($contentUpdated):'Updated daily';
  $html=str_replace('<h2>Upcoming surf events</h2><span class="muted">Read here — no redirect required</span>','<h2>What’s happening on the coast</h2><span class="muted">Music · culture · markets · sport · community · '.htmlspecialchars($contentText,ENT_QUOTES,'UTF-8').'</span>',$html);
  $html=str_replace('<h2>Surf & coast news</h2><span class="muted">Full CostaCams summaries on-page</span>','<h2>Surf & coast news</h2><span class="muted">'.htmlspecialchars($contentText,ENT_QUOTES,'UTF-8').' · verified sources</span>',$html);
}

$footerNote=' · Data refresh: weather & sea 15 min · shark-source check 5 min · news/events daily';
$html=str_replace('</footer>',$footerNote.'</footer>',$html);

if($isHome){@file_put_contents($appCache,$html,LOCK_EX);header('X-CostaCams-App-Cache: MISS');header('Cache-Control: public, max-age=15, stale-while-revalidate=45');}
echo $html;
