<?php
declare(strict_types=1);
if(($_SERVER['HTTP_HOST']??'')!=='beta.costacams.com'){http_response_code(404);exit;}
if(!hash_equals('FmM3PCJD_P4GonXeno3ygwrugME4YoUErEoKYaPBII4',(string)($_GET['token']??''))){http_response_code(403);exit('Forbidden');}
$path='/home/u751177156/.costacams-private.php';
if(is_file($path)){exit('CONFIG_EXISTS');}
$email=filter_var($_GET['email']??'',FILTER_VALIDATE_EMAIL);
$required=['DB_NAME','DB_USER','DB_PASSWORD'];$env=[];
foreach($required as $k){$env[$k]=getenv($k);if(!$env[$k]){http_response_code(500);exit('DB_ENV_MISSING');}}
if(!$email){http_response_code(400);exit('EMAIL_INVALID');}
$data=['alert_email'=>$email,'db_name'=>$env['DB_NAME'],'db_user'=>$env['DB_USER'],'db_pass'=>$env['DB_PASSWORD']];
$content="<?php\nreturn ".var_export($data,true).";\n";
if(file_put_contents($path,$content,LOCK_EX)===false){http_response_code(500);exit('WRITE_FAILED');}
@chmod($path,0600);
echo 'CONFIG_CREATED';
