<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
ob_start();
require __DIR__.'/surf-match.php';
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
echo 'SURF_PREFLIGHT_'.($ok?'OK':'FAIL').' bytes='.strlen($html).' checks='.json_encode($checks)."\n";
exit($ok?0:1);
