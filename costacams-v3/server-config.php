<?php
date_default_timezone_set('Australia/Brisbane');
$dbPass='';
$privateKeyPath='/home/u751177156/.costacams_dbkey2.pem';
$encryptedPassword=base64_decode('t2O5rmjuhP7HpDDa3rFWPnWoE7X/1pc6ju2Gh7jkqUYZeXXcUHeavpZYG7dNzG9krpcEdYRoGZ4zbBHRqy4RmNLHZaxao8zjfF+8V797zoXQyL2d4FqJ05gXq8pdfAK580z15ku5hf9nzs6FoY2nMRWVNJjECJw2HNa3Bbx1yzLH5V+ZIuo915cR+bFNYdmlaKMokWSyXR8l/ftm6uRhGdiKf28qVD3lfsYwZ6LYzUy8AfKQcu0tsekkvIXsHt0NPhXKTduKYFsuzUU/itZEO30rWWMrsIVsdEHIKoHNR4D+P4J11fFF9TV2q4k6MvUmPeho0jTipTzMKS5ijkOygg==',true);
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
