<?php
$to='Jere0589+costa@gmail.com';
$channel=preg_replace('/[^a-z0-9-]/','',strtolower($_POST['channel']??'unknown'));
$issue=substr(strip_tags($_POST['issue']??'unknown'),0,80);
$details=substr(strip_tags($_POST['details']??''),0,500);
$subject='[CostaCams URGENT] Camera report: '.$channel;
$body="CostaCams camera report\n\nChannel: $channel\nIssue: $issue\nDetails: $details\nTime: ".date('c')."\n";
$headers="From: CostaCams <noreply@costacams.com>\r\nReply-To: noreply@costacams.com\r\n";
@mail($to,$subject,$body,$headers);
header('Location: /'.$channel.'?reported=1'); exit;
