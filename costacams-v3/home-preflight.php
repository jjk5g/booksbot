<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}

$_GET['nocache']='1';
$_GET['preflight']='1';
$_SERVER['REQUEST_METHOD']='GET';
$_SERVER['HTTP_HOST']='beta.costacams.com';
$_SERVER['REQUEST_URI']='/?nocache=1&preflight=1';

$finished=false;
register_shutdown_function(function() use (&$finished): void {
  if($finished)return;
  $e=error_get_last();
  if($e){echo 'SHUTDOWN_ERROR type='.$e['type'].' message='.preg_replace('/\s+/',' ',(string)$e['message']).' file='.basename((string)$e['file']).' line='.$e['line']."\n";}
  else echo "SHUTDOWN_WITHOUT_RESULT\n";
});

try{
  ob_start();
  require __DIR__.'/render-stable.php';
  $html=(string)ob_get_clean();
  $finished=true;
  $markers=[
    'title'=>str_contains($html,'<title>CostaCams'),
    'channels'=>str_contains($html,'id="channels"'),
    'explore'=>str_contains($html,'cc-home-explore'),
    'surf_match'=>str_contains($html,'id="surf-match"'),
  ];
  echo 'HOME_PREFLIGHT_OK bytes='.strlen($html).' markers='.json_encode($markers,JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){
  while(ob_get_level()>0)ob_end_clean();
  $finished=true;
  echo 'THROW '.get_class($e).' message='.preg_replace('/\s+/',' ',$e->getMessage()).' file='.basename($e->getFile()).' line='.$e->getLine()."\n";
  exit(1);
}
