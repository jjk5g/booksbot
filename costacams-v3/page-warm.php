<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require __DIR__.'/bootstrap.php';
$pdo=cc_db();if(!$pdo){fwrite(STDERR,"database unavailable\n");exit(1);}

$cacheDir=__DIR__.'/cache';
if(!is_dir($cacheDir) && !@mkdir($cacheDir,0755,true) && !is_dir($cacheDir)){fwrite(STDERR,"cache directory unavailable\n");exit(1);}

// The cron runs every 10 minutes. Home/map/contact are cheap and refreshed on
// every run. Rebuilding every beach page at the same frequency caused CPU/DB
// spikes on shared hosting, so the full coastal catalogue is rebuilt only at
// minute 00 and 30. Existing static pages remain available while this runs.
$minute=(int)date('i');
$fullRun=($minute===0 || $minute===30);
$jobs=[
  ['https://costacams.com/?nocache=1&warm=1',$cacheDir.'/home.html'],
  ['https://costacams.com/map?nocache=1&warm=1',$cacheDir.'/map.html'],
  ['https://costacams.com/contact?nocache=1&warm=1',$cacheDir.'/contact.html'],
];
if($fullRun){
  $slugs=$pdo->query("SELECT slug FROM locations WHERE active=1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
  foreach($slugs as $slug){$s=(string)$slug;$jobs[]=['https://costacams.com/'.rawurlencode($s).'?nocache=1&warm=1',$cacheDir.'/'.$s.'.html'];}
}

$ok=0;$fail=0;
// Sequential generation deliberately favours visitor latency over warmer speed.
$batchSize=1;
for($i=0;$i<count($jobs);$i+=$batchSize){
  $batch=array_slice($jobs,$i,$batchSize);$mh=curl_multi_init();$chs=[];
  foreach($batch as [$url,$dest]){
    $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>25,CURLOPT_USERAGENT=>'CostaCamsStaticWarmer/3.0',CURLOPT_NOBODY=>false,CURLOPT_ENCODING=>'']);curl_multi_add_handle($mh,$ch);$chs[]=[ $ch,$dest ];
  }
  do{$status=curl_multi_exec($mh,$running);if($running)curl_multi_select($mh,1.0);}while($running&&$status===CURLM_OK);
  foreach($chs as [$ch,$dest]){
    $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$body=curl_multi_getcontent($ch);
    if($code>=200&&$code<400&&is_string($body)&&strlen($body)>1000){$tmp=$dest.'.tmp';@file_put_contents($tmp,$body,LOCK_EX);@rename($tmp,$dest);$ok++;}else{$fail++;}
    curl_multi_remove_handle($mh,$ch);curl_close($ch);
  }
  curl_multi_close($mh);
}
@file_put_contents($cacheDir.'/.generated-at',date(DATE_ATOM));
echo "static_warm_mode=".($fullRun?'full':'core')." warmed=$ok failed=$fail total=".count($jobs)."\n";
exit($fail?1:0);
