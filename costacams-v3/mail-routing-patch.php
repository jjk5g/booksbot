<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}

$root=__DIR__;
$prod=dirname(__DIR__,2);
$targets=[$root.'/bootstrap.php',$prod.'/bootstrap.php'];

$old=<<<'PHP'
function cc_send_mail(string $subject,string $body,?string $replyTo=null): bool {
    $cfg=cc_config();$to=$cfg['alert_email']??'';if(!$to||!filter_var($to,FILTER_VALIDATE_EMAIL))return false;$headers="From: CostaCams <noreply@costacams.com>\r\n";$headers.="Reply-To: ".($replyTo&&filter_var($replyTo,FILTER_VALIDATE_EMAIL)?$replyTo:'noreply@costacams.com')."\r\n";return @mail($to,$subject,$body,$headers);
}
PHP;

$new=<<<'PHP'
function cc_send_mail(string $subject,string $body,?string $replyTo=null): bool {
    // Administrator alerts are delivered to the real CostaCams mailbox first.
    // Hostinger then forwards them to the private notification destination.
    // Do not send directly from PHP to the private external address: native
    // mail() can return true even when the message never enters the mail service.
    $to='support@costacams.com';
    $headers='';
    if($replyTo&&filter_var($replyTo,FILTER_VALIDATE_EMAIL))$headers.="Reply-To: {$replyTo}\r\n";
    $headers.="X-CostaCams-Mail-Route: support-forwarder\r\n";
    $ok=@mail($to,$subject,$body,$headers);
    if(!$ok)error_log('CostaCams mail route: PHP mail() rejected administrator message');
    return $ok;
}
PHP;

foreach($targets as $file){
    if(!is_file($file)){fwrite(STDERR,"missing $file\n");exit(1);}
    $src=(string)file_get_contents($file);
    if(str_contains($src,$new)){echo "already_patched=$file\n";continue;}
    if(!str_contains($src,$old)){fwrite(STDERR,"expected mail function not found in $file\n");exit(2);}
    $backup=$file.'.mailfix-'.date('Ymd-His').'.bak';
    if(!copy($file,$backup)){fwrite(STDERR,"backup failed $file\n");exit(3);}
    $patched=str_replace($old,$new,$src,$count);
    if($count!==1||file_put_contents($file,$patched,LOCK_EX)===false){copy($backup,$file);fwrite(STDERR,"patch failed $file\n");exit(4);}
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1',$out,$code);
    if($code!==0){copy($backup,$file);fwrite(STDERR,"lint failed $file: ".implode(' ', $out)."\n");exit(5);}
    echo "patched_and_linted=$file backup=$backup\n";
}

// Server-side functional check: acceptance into the local mailbox route.
require_once $prod.'/bootstrap.php';
$ok=cc_send_mail('[CostaCams TEST] website mail route','Mail route validation '.date(DATE_ATOM));
echo 'mail_route_acceptance='.($ok?'true':'false')."\n";
exit($ok?0:6);
