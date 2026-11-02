<?php
date_default_timezone_set('Australia/Brisbane');
$dbPass='';
$privateKeyPath='/home/u751177156/.cckey.pem';
$encryptedPassword=base64_decode('EDBbiqquRffqHEsLUTLwEhjy+DnaG4tum49uAAVEomdrL0MBWmv7CYJIGQ+7Z60zy31HE47j3NPg++tM1uTwofR/YY64vAtT4lNRrwgGxp3HE52wSYZ50TL5eqHu86g5t2Ew826yZSrmF3vzkL3nwu7V1JzM3JBArx2R1QnrgvE+DuZcCuskBFYy84oE5P22So9zvjPSRBdetEZg0vauzc0YgkiEFWkBiRmb7JTXqk90fWAMausHHynLquBxHR57805/wiU2LkW8yFtrLJCrxhARM/+yDGuQy/MgdetypbYgnUu+qQ7lf1dfkT8ozTyFCjw1r7e4C2DG29CrSeHiNw==',true);
if(is_file($privateKeyPath) && $encryptedPassword!==false){
  $pem=@file_get_contents($privateKeyPath);
  $key=$pem?@openssl_pkey_get_private($pem):false;
  if($key){@openssl_private_decrypt($encryptedPassword,$dbPass,$key,OPENSSL_PKCS1_OAEP_PADDING);}
}
return [
  'alert_email' => 'support@costacams.com',
  'db_name' => 'u751177156_costav3prod;host=srv929.hstgr.io',
  'db_user' => 'u751177156_costav3prod',
  'db_pass' => $dbPass,
];
