<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require __DIR__.'/bootstrap.php';
$pdo=cc_db();if(!$pdo){fwrite(STDERR,"database unavailable\n");exit(1);}
$slugs=$pdo->query("SELECT slug FROM locations WHERE active=1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$urls=['https://costacams.com/?nocache=1&warm=1'];
foreach($slugs as $slug)$urls[]='https://costacams.com/'.rawurlencode((string)$slug).'?nocache=1&warm=1';
$ok=0;$fail=0;$batchSize=5;
for($i=0;$i<count($urls);$i+=$batchSize){
  $batch=array_slice($urls,$i,$batchSize);$mh=curl_multi_init();$chs=[];
  foreach($batch as $url){
    $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>25,CURLOPT_USERAGENT=>'CostaCamsCacheWarmer/1.0',CURLOPT_NOBODY=>false]);curl_multi_add_handle($mh,$ch);$chs[]=$ch;
  }
  do{$status=curl_multi_exec($mh,$running);if($running)curl_multi_select($mh,1.0);}while($running&&$status===CURLM_OK);
  foreach($chs as $ch){$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);if($code>=200&&$code<400)$ok++;else$fail++;curl_multi_remove_handle($mh,$ch);curl_close($ch);}curl_multi_close($mh);
}
echo "warmed=$ok failed=$fail\n";
exit($fail?1:0);
