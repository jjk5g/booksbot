<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$dir=__DIR__;
$files=['cc-analytics.js','analytics-collect.php','analytics-dashboard.php','render-fast.php','surf-match.php','map-clean.php','event.php','admin.php','render-stable.php'];
foreach($files as $f){if(!is_file($dir.'/'.$f)||filesize($dir.'/'.$f)<200){echo "ANALYTICS_GATE_FAIL missing $f\n";exit(1);}}
foreach(['analytics-collect.php','analytics-dashboard.php','render-fast.php','surf-match.php','map-clean.php','event.php','admin.php'] as $f){
    $out=[];$ret=0;exec('php -l '.escapeshellarg($dir.'/'.$f).' 2>&1',$out,$ret);
    if($ret!==0){echo "ANALYTICS_GATE_FAIL lint $f ".implode(' ',$out)."\n";exit(1);}
}
$_GET=['nocache'=>'1'];ob_start();
try{require $dir.'/render-stable.php';$home=(string)ob_get_clean();}
catch(Throwable $e){ob_end_clean();echo "ANALYTICS_GATE_FAIL homepage ".get_class($e)."\n";exit(2);}
$checks=[
 'home_full'=>strlen($home)>30000&&str_contains($home,'</html>'),
 'hero'=>str_contains(strtolower($home),'pick your beach'),
 'cameras'=>str_contains($home,'id="channels"'),
 'map'=>str_contains($home,'cc-home-explore'),
 'surf_launcher'=>str_contains($home,'id="surf-match"'),
 'calendar'=>str_contains($home,'id="calendar"'),
 'ga4'=>str_contains($home,'G-6WLK1YD519'),
 'first_party'=>str_contains($home,'cc-analytics.js'),
 'surf_v13'=>str_contains(file_get_contents($dir.'/surf-match.php'),'surf-match-beta-v13.php'),
 'surf_postcode'=>str_contains(file_get_contents($dir.'/surf-match-beta-v13.php'),'usePostcode'),
 'surf_beaches'=>str_contains(file_get_contents($dir.'/surf-match-beta-v13.php'),'All beaches'),
 'map_ga_iframe_guard'=>str_contains(file_get_contents($dir.'/map-clean.php'),'window.self!==window.top'),
 'admin_session_guard'=>str_contains(file_get_contents($dir.'/analytics-dashboard.php'),"empty(\$_SESSION['cc_admin'])"),
 'collect_origin_guard'=>str_contains(file_get_contents($dir.'/analytics-collect.php'),'HTTP_ORIGIN')
];
echo (in_array(false,$checks,true)?'ANALYTICS_GATE_FAIL':'ANALYTICS_GATE_OK').' bytes='.strlen($home).' checks='.json_encode($checks)."\n";
exit(in_array(false,$checks,true)?1:0);
