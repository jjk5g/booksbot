<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$channel=preg_replace('/[^a-z0-9-]/','',strtolower($_POST['channel']??'unknown'));
$streamId=filter_var($_POST['stream_id']??null,FILTER_VALIDATE_INT)?:null;
$issue=substr(trim(strip_tags($_POST['issue']??'unknown')),0,80);
$details=substr(trim(strip_tags($_POST['details']??'')),0,500);
$loc=cc_location($channel);
$pdo=cc_db();
if($pdo){
  $st=$pdo->prepare('INSERT INTO stream_reports(location_id,stream_id,issue,details) VALUES(?,?,?,?)');
  try{$st->execute([$loc['id']??null,$streamId,$issue,$details]);}catch(Throwable $e){error_log('CostaCams report: '.$e->getMessage());}
}
$name=$loc['name']??$channel;
$body="CostaCams urgent camera report\n\nLocation: {$name}\nChannel: {$channel}\nIssue: {$issue}\nDetails: {$details}\nTime: ".date('c')."\n";
cc_send_mail('[CostaCams URGENT] Camera report: '.$name,$body);
$dest='/'.rawurlencode($channel).'?reported=1';
header('Location: '.$dest, true, 303);exit;
