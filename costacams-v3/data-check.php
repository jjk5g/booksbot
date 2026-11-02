<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
ini_set('display_errors','1');error_reporting(E_ALL);
require __DIR__.'/bootstrap.php';
$pdo=cc_db();if(!$pdo){echo "DB_FAIL\n";exit(1);}
function t(PDO $pdo,string $name):bool{$q=$pdo->prepare('SHOW TABLES LIKE ?');$q->execute([$name]);return (bool)$q->fetchColumn();}
foreach(['forecast_cache','news_items','system_data_status','shark_status','shark_alerts'] as $table){echo $table.':'.(t($pdo,$table)?'yes':'no');if(t($pdo,$table)){echo ':'.$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();}echo "\n";}
if(t($pdo,'news_items')){$cols=$pdo->query('SHOW COLUMNS FROM news_items')->fetchAll();echo 'news_cols='.implode(',',array_column($cols,'Field'))."\n";}
if(t($pdo,'system_data_status')){foreach($pdo->query('SELECT dataset,status,detail,last_checked_at,last_updated_at FROM system_data_status ORDER BY dataset')->fetchAll() as $r){echo 'status:'.implode('|',array_map(fn($v)=>$v??'',array_values($r)))."\n";}}
