<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}

$roots=[__DIR__,dirname(__DIR__,2)];
$patched=[];

function patch_bootstrap(string $path): void {
    $src=file_get_contents($path);
    if($src===false)throw new RuntimeException("Cannot read $path");
    $new=preg_replace(
        '~function cc_send_mail\(string \$subject,string \$body,\?string \$replyTo=null\): bool \{.*?\n\}~s',
        <<<'PHP'
function cc_send_mail(string $subject,string $body,?string $replyTo=null): bool {
    $deliveryMailbox='support@costacams.com';
    $sender='noreply@costacams.com';
    $reply=$replyTo&&filter_var($replyTo,FILTER_VALIDATE_EMAIL)?$replyTo:'support@costacams.com';
    $headers="From: CostaCams <{$sender}>\r\n";
    $headers.="Reply-To: {$reply}\r\n";
    $headers.="MIME-Version: 1.0\r\n";
    $headers.="Content-Type: text/plain; charset=UTF-8\r\n";
    return @mail($deliveryMailbox,$subject,$body,$headers,'-f '.$sender);
}
PHP,
        $src,
        1,
        $count
    );
    if($new===null||$count!==1)throw new RuntimeException("cc_send_mail patch failed for $path count=$count");
    $bak=$path.'.senderfix-'.date('Ymd-His').'.bak';
    if(!copy($path,$bak))throw new RuntimeException("Backup failed for $path");
    if(file_put_contents($path,$new,LOCK_EX)===false)throw new RuntimeException("Write failed for $path");
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1',$out,$code);
    if($code!==0){copy($bak,$path);throw new RuntimeException("Lint failed for $path: ".implode("\n",$out));}
    echo "patched_bootstrap=$path\n";
}

function patch_surf_mail(string $path): void {
    $src=file_get_contents($path);
    if($src===false)return;
    $new=str_replace('From: CostaCams <support@costacams.com>','From: CostaCams <noreply@costacams.com>',$src,$c1);
    $new=str_replace("'-f support@costacams.com'","'-f noreply@costacams.com'",$new,$c2);
    if($c1===0&&$c2===0)return;
    $bak=$path.'.senderfix-'.date('Ymd-His').'.bak';copy($path,$bak);
    if(file_put_contents($path,$new,LOCK_EX)===false)throw new RuntimeException("Write failed for $path");
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1',$out,$code);
    if($code!==0){copy($bak,$path);throw new RuntimeException("Lint failed for $path: ".implode("\n",$out));}
    echo "patched_surf_mail=$path\n";
}

foreach(array_unique($roots) as $root){
    $b=$root.'/bootstrap.php';if(is_file($b))patch_bootstrap($b);
    $s=$root.'/surf-beta-v6-mail.php';if(is_file($s))patch_surf_mail($s);
}

// Final production helper acceptance test. Delivery is verified separately in Hostinger logs.
$prod=dirname(__DIR__,2).'/bootstrap.php';
require_once $prod;
$ok=cc_send_mail('[CostaCams TEST] sender branding','Sender branding validation '.date(DATE_ATOM));
echo 'mail_acceptance='.($ok?'true':'false')."\n";
exit($ok?0:1);
