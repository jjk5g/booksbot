<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root='/home/u751177156/domains/costacams.com/public_html';
$visitor='analytics-smoketest-20261107-abcdd120bb';
$key='beach:analytics-smoke-test';
$_SERVER['REQUEST_METHOD']='POST';
$_SERVER['HTTP_ORIGIN']='https://costacams.com';
$_POST=['kind'=>'page','key'=>$key,'visitor'=>$visitor];
ob_start();
try{require $root.'/analytics-collect.php';$response=(string)ob_get_clean();}
catch(Throwable $e){ob_end_clean();echo 'ANALYTICS_SMOKE_FAIL collector '.get_class($e)."\n";exit(1);}
$code=http_response_code();
try{
 $pdo=cc_db();
 if(!$pdo)throw new RuntimeException('database not available');
 $st=$pdo->prepare("SELECT COALESCE(SUM(events),0) FROM cc_analytics_daily WHERE day=CURDATE() AND kind='page' AND metric_key=?");
 $st->execute([$key]);
 $seen=(int)$st->fetchColumn();
 $hash=hash('sha256',$visitor.'|costacams-analytics-v1');
 $st=$pdo->prepare("SELECT COUNT(*) FROM cc_analytics_visitors WHERE day=CURDATE() AND visitor_hash=?");
 $st->execute([$hash]);$unique=(int)$st->fetchColumn();
 $pdo->prepare("DELETE FROM cc_analytics_daily WHERE day=CURDATE() AND kind='page' AND metric_key=?")->execute([$key]);
 $pdo->prepare("DELETE FROM cc_analytics_visitors WHERE day=CURDATE() AND visitor_hash=?")->execute([$hash]);
 echo (($code===204&&$seen>=1&&$unique===1)?'ANALYTICS_SMOKE_OK':'ANALYTICS_SMOKE_FAIL')
     ." status=$code count=$seen unique=$unique response=".substr($response,0,100)."\n";
}catch(Throwable $e){echo 'ANALYTICS_SMOKE_FAIL query '.get_class($e)."\n";exit(2);}
