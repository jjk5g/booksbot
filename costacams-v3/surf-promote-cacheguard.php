<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
$prod=dirname(__DIR__,2);
$src=__DIR__.'/surf-match.php';
$dst=$prod.'/surf-match.php';
$bak=$prod.'/surf-match.php.precache-'.date('Ymd-His');
if(!copy($dst,$bak))throw new RuntimeException('backup failed');
if(!copy($src,$dst))throw new RuntimeException('copy failed');
exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($dst).' 2>&1',$lint,$code);
if($code!==0){copy($bak,$dst);throw new RuntimeException('lint failed: '.implode("\n",$lint));}
ob_start();
require $dst;
$html=ob_get_clean();
$checks=[
 'v13'=>str_contains($html,'SURF MATCH BETA v13'),
 'postcode'=>str_contains($html,'id="postcode"'),
 'all_beaches'=>str_contains($html,'All beaches'),
 'drive_limit'=>str_contains($html,'How far are you willing to drive?'),
 'waze'=>str_contains($html,'Waze traffic'),
 'rain'=>str_contains($html,'Rain radar'),
 'clouds'=>str_contains($html,'Clouds / satellite'),
 'enter'=>str_contains($html,"e.key==='Enter'"),
];
$ok=!in_array(false,$checks,true)&&strlen($html)>30000;
if(!$ok){copy($bak,$dst);echo 'SURF_PROMOTE_ROLLBACK checks='.json_encode($checks)."\n";exit(1);}
echo 'SURF_PROMOTE_OK bytes='.strlen($html).' checks='.json_encode($checks).' backup='.$bak."\n";
