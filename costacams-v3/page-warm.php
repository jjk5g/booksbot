<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require __DIR__.'/bootstrap.php';
$pdo=cc_db();if(!$pdo){fwrite(STDERR,"database unavailable\n");exit(1);}

$cacheDir=__DIR__.'/cache';
if(!is_dir($cacheDir) && !@mkdir($cacheDir,0755,true) && !is_dir($cacheDir)){fwrite(STDERR,"cache directory unavailable\n");exit(1);}

function cc_warm_validate(string $body,int $code,string $dest): array {
  $name=basename($dest);
  $len=strlen($body);
  if($code<200||$code>=400)return [false,"http_$code"];
  if($len<1200)return [false,"too_small_$len"];
  $lower=strtolower($body);
  foreach(['fatal error','parse error','uncaught error','uncaught exception','surf match is recovering'] as $bad){
    if(str_contains($lower,$bad))return [false,'error_marker_'.str_replace(' ','_',$bad)];
  }
  if(!str_contains($lower,'<!doctype html')||!str_contains($lower,'</html>'))return [false,'incomplete_html'];
  if(!str_contains($lower,'costacams'))return [false,'brand_missing'];

  // The homepage is mission-critical. Never replace the known-good static file
  // with a partial/error render, even when PHP returned HTTP 200.
  if($name==='home.html'){
    if($len<20000)return [false,"home_too_small_$len"];
    foreach(['<title>costacams','id="channels"','cc-home-explore'] as $marker){
      if(!str_contains($lower,$marker))return [false,'home_marker_missing_'.preg_replace('/[^a-z0-9]+/','_',$marker)];
    }
  }
  return [true,'ok'];
}

// The cron runs every 10 minutes. Existing cache files are immutable fallbacks:
// a candidate is written to *.candidate and promoted with rename() only after
// validation succeeds. A failed render therefore cannot take production down.
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

$ok=0;$fail=0;$errors=[];
foreach($jobs as [$url,$dest]){
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_FOLLOWLOCATION=>true,
    CURLOPT_CONNECTTIMEOUT=>4,
    CURLOPT_TIMEOUT=>25,
    CURLOPT_USERAGENT=>'CostaCamsStaticWarmer/4.0',
    CURLOPT_NOBODY=>false,
    CURLOPT_ENCODING=>'',
  ]);
  $body=curl_exec($ch);
  $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $curlErr=curl_error($ch);
  curl_close($ch);

  if(!is_string($body)){$body='';}
  [$valid,$reason]=cc_warm_validate($body,$code,$dest);
  if(!$valid){
    $fail++;$errors[]=basename($dest).':'.$reason.($curlErr!==''?':'.$curlErr:'');
    continue;
  }

  $candidate=$dest.'.candidate';
  $written=@file_put_contents($candidate,$body,LOCK_EX);
  if($written!==strlen($body) || !@rename($candidate,$dest)){
    @unlink($candidate);$fail++;$errors[]=basename($dest).':atomic_promote_failed';continue;
  }
  $ok++;
}

@file_put_contents($cacheDir.'/.generated-at',date(DATE_ATOM));
if($errors){
  @file_put_contents($cacheDir.'/.warm-errors.log',date(DATE_ATOM).' '.implode(' | ',$errors)."\n",FILE_APPEND|LOCK_EX);
}
echo "static_warm_mode=".($fullRun?'full':'core')." warmed=$ok failed=$fail total=".count($jobs)."\n";
if($errors)echo "preserved_previous_cache=".implode(',',$errors)."\n";
exit($fail?1:0);
