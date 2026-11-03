<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
$src=__DIR__;
$dst=dirname(__DIR__,2);
$files=[
 'production-render-stable.php'=>'render-stable.php',
 'surf-match.php'=>'surf-match.php',
 'surf-match-beta-v13.php'=>'surf-match-beta-v13.php',
 'surf-match-beta-v5.php'=>'surf-match-beta-v5.php',
 'surf-match-beta-v4.php'=>'surf-match-beta-v4.php',
 'surf-match-beta-v3.php'=>'surf-match-beta-v3.php',
 'surf-match-beta-v2.php'=>'surf-match-beta-v2.php',
 'surf-beta-v5-api.php'=>'surf-beta-v5-api.php',
 'surf-beta-engine.php'=>'surf-beta-engine.php',
];
$ok=0;
foreach($files as $from=>$to){
  if(!is_file($src.'/'.$from)){fwrite(STDERR,"missing $from\n");continue;}
  if(@copy($src.'/'.$from,$dst.'/'.$to))$ok++;else fwrite(STDERR,"copy failed $to\n");
}
echo "copied=$ok/".count($files)."\n";
$warmer=$dst.'/page-warm.php';
if(is_file($warmer)){
  passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg($warmer),$code);
  exit((int)$code);
}
fwrite(STDERR,"page warmer missing\n");exit(1);
