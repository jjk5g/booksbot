<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
error_reporting(E_ALL);
$base=__DIR__;
foreach(['coast-calendar.php','event.php','render-stable.php'] as $file){
 if(!is_readable($base.'/'.$file)){echo "STAGE_FAIL missing $file\n";exit(2);}
}
ob_start();
try{require $base.'/render-stable.php';$html=ob_get_clean();}
catch(Throwable $e){ob_end_clean();echo "STAGE_FAIL render ".get_class($e)." ".$e->getMessage()."\n";exit(3);}
$checks=['doctype'=>stripos($html,'<!doctype html')!==false,
    'full_html'=>str_contains($html,'</html>'),
    'live_map'=>str_contains($html,'cc-home-explore'),
    'beach_channels'=>str_contains($html,'id="channels"'),
    'surf_match'=>str_contains($html,'id="surf-match"'),
    'events_articles'=>str_contains($html,'id="events"'),
    'calendar'=>str_contains($html,'id="calendar"'),
    'calendar_js'=>str_contains($html,'id="cc-cal-script"'),
    'calendar_data'=>str_contains($html,'id="ccCalData"'),
    'calendar_links'=>str_contains($html,'/event.php?slug='),
    'hero'=>str_contains(strtolower($html),'pick your beach')];
$count=-1;
if(preg_match('~<script type="application/json" id="ccCalData">(.*?)</script>~s',$html,$m)){
 $events=json_decode($m[1],true);
 if(is_array($events))$count=count($events);
}
$checks['json_ok']=$count>=0;
if(strlen($html)<30000)$checks['html_size']=false;
echo (in_array(false,$checks,true)?"STAGE_FAIL":"STAGE_OK")." bytes=".strlen($html)." calendar_events=$count markers=".json_encode($checks)."\n";
exit(in_array(false,$checks,true)?1:0);
