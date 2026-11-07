<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root='/home/u751177156/domains/costacams.com/public_html';
$r=file_get_contents($root.'/render-stable.php');
$h=file_get_contents($root.'/cache/home.html');
echo 'root_renderer_size='.strlen((string)$r).' has_pick='.((int)str_contains((string)$r,'Pick your beach')).' has_calendar='.((int)str_contains((string)$r,'cc_calendar_home'))."\n";
echo 'cached_home_size='.strlen((string)$h).' has_pick='.((int)str_contains((string)$h,'Pick your beach')).' has_calendar='.((int)str_contains((string)$h,'id="calendar"'))."\n";
$_GET=['nocache'=>'1'];ob_start();
try{require $root.'/render-stable.php';$html=(string)ob_get_clean();}
catch(Throwable $e){ob_end_clean();echo 'BASE_RENDER_ERROR='.get_class($e).' '.$e->getMessage()."\n";exit(1);}
$checks=[];
foreach(['<!doctype html','</html>','cc-home-explore','id="channels"','id="surf-match"','id="events"','id="calendar"','id="ccCalData"','id="cc-cal-script"','Pick your beach'] as $v)$checks[$v]=str_contains($html,$v);
echo 'base_render_bytes='.strlen($html).' markers='.json_encode($checks)."\n";
