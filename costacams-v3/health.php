<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__.'/bootstrap.php';
$cfg=cc_config();
$pdo=cc_db();
$resolved=gethostbyname('srv929.hstgr.io');
$out=[
  'ok'=>true,
  'config_file'=>is_file(COSTACAMS_CONFIG),
  'private_key_exists'=>is_file('/home/u751177156/.cckey.pem'),
  'db_password_ready'=>!empty($cfg['db_pass']),
  'pdo_mysql'=>extension_loaded('pdo_mysql'),
  'mysql_host_resolves'=>$resolved!=='srv929.hstgr.io',
  'db_connected'=>$pdo instanceof PDO,
  'locations'=>0,
  'streams'=>0,
];
if($pdo){
  try{$out['locations']=(int)$pdo->query('SELECT COUNT(*) FROM locations')->fetchColumn();}catch(Throwable $e){}
  try{$out['streams']=(int)$pdo->query('SELECT COUNT(*) FROM streams')->fetchColumn();}catch(Throwable $e){}
}else{
  $locs=cc_fallback_locations();$out['locations']=count($locs);foreach($locs as $l)$out['streams']+=count($l['streams']??[]);
}
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
