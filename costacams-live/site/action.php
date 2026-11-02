<?php
require __DIR__.'/lib.php'; session_start();
if($_SERVER['REQUEST_METHOD']!=='POST' || !check_csrf()){http_response_code(400);exit('Invalid request');}
$action=$_POST['action']??'';
if($action==='contact'){
    $name=trim((string)($_POST['name']??'')); $email=trim((string)($_POST['email']??'')); $subject=trim((string)($_POST['subject']??'')); $message=trim((string)($_POST['message']??''));
    if(!$message||!$subject){header('Location:/contact');exit;}
    if($email && !filter_var($email,FILTER_VALIDATE_EMAIL)) $email='';
    $st=db()->prepare("INSERT INTO contacts(name,email,subject,message) VALUES(?,?,?,?)");$st->execute([$name?:null,$email?:null,$subject,$message]);
    send_owner_mail('[CostaCams Contact] '.$subject,"New CostaCams contact\n\nName: {$name}\nReply email: {$email}\n\n{$message}",$email?:null);
    header('Location:/contact?sent=1');exit;
}
if($action==='report'){
    $loc=(int)($_POST['location_id']??0);$stream=(int)($_POST['stream_id']??0);$reason=substr(trim((string)($_POST['reason']??'other')),0,60);$message=trim((string)($_POST['message']??''));$email=trim((string)($_POST['email']??''));$slug=preg_replace('/[^a-z0-9-]/','',(string)($_POST['return_slug']??''));
    if($email && !filter_var($email,FILTER_VALIDATE_EMAIL))$email='';
    $hash=visitor_hash();$rate=db()->prepare("SELECT COUNT(*) FROM stream_reports WHERE ip_hash=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)");$rate->execute([$hash]);if((int)$rate->fetchColumn()>=5){header('Location:/'.$slug.'?report=limited');exit;}
    $st=db()->prepare("INSERT INTO stream_reports(stream_id,location_id,reason,message,reporter_email,ip_hash) VALUES(NULLIF(?,0),?,?,?,?,?)");$st->execute([$stream,$loc,$reason,$message,$email?:null,$hash]);
    $q=db()->prepare("SELECT l.name location_name,s.name stream_name FROM locations l LEFT JOIN streams s ON s.id=? WHERE l.id=? LIMIT 1");$q->execute([$stream,$loc]);$r=$q->fetch();
    $urgent=in_array($reason,['offline','buffering'],true);$prefix=$urgent?'[URGENT CostaCams]':'[CostaCams Report]';
    send_owner_mail($prefix.' '.($r['location_name']??'Camera'),"Camera report\n\nLocation: ".($r['location_name']??$loc)."\nStream: ".($r['stream_name']??$stream)."\nProblem: {$reason}\n\n{$message}\n\nReporter email: {$email}",$email?:null);
    if($stream){$u=db()->prepare("UPDATE streams SET last_alert_at=NOW(), status=IF(?='offline','reported','unknown') WHERE id=?");$u->execute([$reason,$stream]);}
    header('Location:/'.$slug.'?reported=1');exit;
}
http_response_code(400);echo 'Unknown action';
