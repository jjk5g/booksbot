<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require '/home/u751177156/domains/costacams.com/public_html/bootstrap.php';
$tokenFile='/home/u751177156/.ccmail-token';
echo 'token_present='.(is_readable($tokenFile)&&filesize($tokenFile)>0?'yes':'no')."\n";
$pdo=cc_db();
if($pdo){
  try{
    $rows=$pdo->query("SELECT mail_type,status,detail,created_at FROM surf_beta_mail_log ORDER BY id DESC LIMIT 10")->fetchAll();
    echo 'recent_logs='.json_encode($rows,JSON_UNESCAPED_SLASHES)."\n";
  }catch(Throwable $e){echo 'log_error='.get_class($e)."\n";}
}
if(!is_readable($tokenFile)){exit;}
$token=trim((string)file_get_contents($tokenFile));
$url='https://api.mail.hostinger.com/api/v1/mailboxes/AC976a0c9e97ca666ca6966bd72016/send';
$payload=['to'=>['support@costacams.com'],'subject'=>'[CostaCams MAIL HEALTH] authenticated delivery test','text'=>'Authenticated Surf Match mail transport health test.','html'=>'<p>Authenticated Surf Match mail transport health test.</p>','displayName'=>'CostaCams'];
$ch=curl_init($url);
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload)]);
$raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
echo 'api_http='.$code.' curl_error='.($err?:'none').' response='.(is_string($raw)?substr(preg_replace('/\s+/',' ',$raw),0,500):'false')."\n";
