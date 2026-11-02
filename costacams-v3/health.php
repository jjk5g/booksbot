<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__.'/bootstrap.php';
$pdo=cc_db();
$out=[
  'ok'=>true,
  'config_file'=>is_file(COSTACAMS_CONFIG),
  'db_connected'=>$pdo instanceof PDO,
  'env_db_name'=>getenv('DB_NAME')?true:false,
  'env_db_user'=>getenv('DB_USER')?true:false,
  'env_db_password'=>getenv('DB_PASSWORD')?true:false,
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
