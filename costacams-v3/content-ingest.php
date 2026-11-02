<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
ini_set('display_errors','1');error_reporting(E_ALL);
require __DIR__.'/bootstrap.php';
$pdo=cc_db();if(!$pdo){fwrite(STDERR,"database unavailable\n");exit(1);}

function cc_has_col(PDO $pdo,string $table,string $col): bool {
  $st=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
  $st->execute([$table,$col]);return (int)$st->fetchColumn()>0;
}
function cc_has_index(PDO $pdo,string $table,string $idx): bool {
  $st=$pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?");
  $st->execute([$table,$idx]);return (int)$st->fetchColumn()>0;
}
function cc_add_col(PDO $pdo,string $table,string $col,string $def): void {if(!cc_has_col($pdo,$table,$col))$pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");}
function cc_place_key(string $s): string {return preg_replace('/[^a-z0-9]+/','',strtolower($s))??'';}
cc_add_col($pdo,'news_items','event_type',"VARCHAR(80) DEFAULT NULL AFTER category");
cc_add_col($pdo,'news_items','region',"VARCHAR(100) DEFAULT NULL AFTER event_type");
cc_add_col($pdo,'news_items','locality',"VARCHAR(160) DEFAULT NULL AFTER region");
cc_add_col($pdo,'news_items','event_end_date',"DATETIME DEFAULT NULL AFTER event_date");
cc_add_col($pdo,'news_items','external_key',"VARCHAR(190) DEFAULT NULL AFTER event_end_date");
cc_add_col($pdo,'news_items','content_hash',"CHAR(64) DEFAULT NULL AFTER external_key");
cc_add_col($pdo,'news_items','auto_managed',"TINYINT(1) NOT NULL DEFAULT 0 AFTER content_hash");
cc_add_col($pdo,'news_items','verified_at',"DATETIME DEFAULT NULL AFTER auto_managed");
cc_add_col($pdo,'news_items','last_seen_at',"DATETIME DEFAULT NULL AFTER verified_at");
cc_add_col($pdo,'news_items','updated_at',"TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
if(!cc_has_index($pdo,'news_items','uq_news_external_key'))$pdo->exec("CREATE UNIQUE INDEX uq_news_external_key ON news_items(external_key)");

$pdo->exec("CREATE TABLE IF NOT EXISTS shark_alerts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  external_key VARCHAR(190) NOT NULL UNIQUE,
  region_key VARCHAR(64) NOT NULL,
  locality VARCHAR(160) DEFAULT NULL,
  alert_type VARCHAR(80) NOT NULL DEFAULT 'shark_alert',
  title VARCHAR(220) NOT NULL,
  detail TEXT NOT NULL,
  observed_at DATETIME DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL,
  source_name VARCHAR(160) NOT NULL,
  source_url TEXT NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  verified_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_shark_active_region(active,region_key,observed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
cc_add_col($pdo,'shark_alerts','latitude',"DECIMAL(9,6) DEFAULT NULL AFTER locality");
cc_add_col($pdo,'shark_alerts','longitude',"DECIMAL(9,6) DEFAULT NULL AFTER latitude");
$pdo->exec("CREATE TABLE IF NOT EXISTS system_data_status (
  dataset VARCHAR(64) PRIMARY KEY,
  source_name VARCHAR(160) DEFAULT NULL,
  source_url TEXT DEFAULT NULL,
  refresh_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  status ENUM('ok','partial','error') NOT NULL DEFAULT 'ok',
  detail VARCHAR(500) DEFAULT NULL,
  last_checked_at DATETIME NOT NULL,
  last_updated_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$placeCoords=[];
foreach($pdo->query("SELECT slug,name,lat,lon FROM locations WHERE active=1")->fetchAll() as $p){
  $coords=[(float)$p['lat'],(float)$p['lon']];
  $placeCoords[cc_place_key((string)$p['name'])]=$coords;
  $placeCoords[cc_place_key(str_replace('-',' ',(string)$p['slug']))]=$coords;
}

$feedUrl='https://raw.githubusercontent.com/jjk5g/booksbot/costacams-v2-deploy/costacams-v3/content-feed.json';
$ctx=stream_context_create(['http'=>['timeout'=>20,'user_agent'=>'CostaCamsContent/1.0 (+https://costacams.com)','ignore_errors'=>true]]);
$raw=@file_get_contents($feedUrl,false,$ctx);
$data=$raw?json_decode($raw,true):null;
if(!is_array($data)){fwrite(STDERR,"content feed unavailable or invalid\n");$st=$pdo->prepare("INSERT INTO system_data_status(dataset,source_name,source_url,refresh_minutes,status,detail,last_checked_at) VALUES('content','CostaCams curated feed',?,1440,'error','Feed unavailable or invalid',NOW()) ON DUPLICATE KEY UPDATE status='error',detail='Feed unavailable or invalid',last_checked_at=NOW()");$st->execute([$feedUrl]);exit(2);}

$items=is_array($data['items']??null)?$data['items']:[];
$up=$pdo->prepare("INSERT INTO news_items(slug,category,event_type,region,locality,title,summary,body,source_name,source_url,published_at,event_date,event_end_date,external_key,content_hash,auto_managed,verified_at,last_seen_at,active,sort_order)
VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),1,?)
ON DUPLICATE KEY UPDATE category=VALUES(category),event_type=VALUES(event_type),region=VALUES(region),locality=VALUES(locality),title=VALUES(title),summary=VALUES(summary),body=VALUES(body),source_name=VALUES(source_name),source_url=VALUES(source_url),published_at=VALUES(published_at),event_date=VALUES(event_date),event_end_date=VALUES(event_end_date),external_key=VALUES(external_key),content_hash=VALUES(content_hash),auto_managed=1,verified_at=NOW(),last_seen_at=NOW(),active=1,sort_order=VALUES(sort_order)");
$count=0;
foreach($items as $i){
  if(!is_array($i))continue;
  $cat=in_array(($i['category']??''),['news','event'],true)?$i['category']:'news';
  $title=trim((string)($i['title']??''));$url=trim((string)($i['source_url']??''));if($title===''||$url==='')continue;
  $slug=preg_replace('/[^a-z0-9-]+/','-',strtolower((string)($i['slug']??$title)));$slug=trim((string)$slug,'-');if($slug==='')continue;
  $external=substr((string)($i['external_key']??hash('sha256',$url)),0,190);
  $summary=trim((string)($i['summary']??''));$body=trim((string)($i['body']??$summary));
  $hash=hash('sha256',json_encode([$title,$summary,$body,$i['event_date']??null,$i['event_end_date']??null],JSON_UNESCAPED_UNICODE));
  $up->execute([$slug,$cat,substr((string)($i['event_type']??($cat==='event'?'community':'coast_news')),0,80),substr((string)($i['region']??''),0,100)?:null,substr((string)($i['locality']??''),0,160)?:null,$title,$summary,$body,substr((string)($i['source_name']??'Source'),0,150),$url,$i['published_at']??null,$i['event_date']??null,$i['event_end_date']??null,$external,$hash,1,(int)($i['sort_order']??100)]);$count++;
}

$alerts=is_array($data['shark_alerts']??null)?$data['shark_alerts']:[];
$aup=$pdo->prepare("INSERT INTO shark_alerts(external_key,region_key,locality,latitude,longitude,alert_type,title,detail,observed_at,expires_at,source_name,source_url,active,verified_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,1,NOW()) ON DUPLICATE KEY UPDATE region_key=VALUES(region_key),locality=VALUES(locality),latitude=VALUES(latitude),longitude=VALUES(longitude),alert_type=VALUES(alert_type),title=VALUES(title),detail=VALUES(detail),observed_at=VALUES(observed_at),expires_at=VALUES(expires_at),source_name=VALUES(source_name),source_url=VALUES(source_url),active=1,verified_at=NOW()");
$acount=0;
foreach($alerts as $a){
  if(!is_array($a))continue;$url=trim((string)($a['source_url']??''));$title=trim((string)($a['title']??''));if(!$url||!$title)continue;
  $key=substr((string)($a['external_key']??hash('sha256',$url.'|'.$title.'|'.($a['observed_at']??''))),0,190);
  $locality=substr(trim((string)($a['locality']??'')),0,160)?:null;
  $lat=is_numeric($a['latitude']??null)?(float)$a['latitude']:null;$lon=is_numeric($a['longitude']??null)?(float)$a['longitude']:null;
  if(($lat===null||$lon===null)&&$locality){$keys=[cc_place_key($locality)];$first=trim(explode(',',$locality)[0]??'');if($first!=='')$keys[]=cc_place_key($first);foreach($keys as $pk){if(isset($placeCoords[$pk])){[$lat,$lon]=$placeCoords[$pk];break;}}}
  $aup->execute([$key,substr((string)($a['region_key']??'unknown'),0,64),$locality,$lat,$lon,substr((string)($a['alert_type']??'shark_alert'),0,80),$title,(string)($a['detail']??''),$a['observed_at']??null,$a['expires_at']??null,substr((string)($a['source_name']??'Official source'),0,160),$url]);$acount++;
}

$pdo->exec("UPDATE news_items SET active=0 WHERE auto_managed=1 AND category='event' AND COALESCE(event_end_date,event_date) IS NOT NULL AND COALESCE(event_end_date,event_date)<DATE_SUB(NOW(),INTERVAL 3 HOUR)");
$pdo->exec("UPDATE news_items SET active=0 WHERE auto_managed=1 AND category='news' AND published_at IS NOT NULL AND published_at<DATE_SUB(NOW(),INTERVAL 21 DAY)");
$pdo->exec("UPDATE shark_alerts SET active=0 WHERE active=1 AND expires_at IS NOT NULL AND expires_at<NOW()");

$st=$pdo->prepare("INSERT INTO system_data_status(dataset,source_name,source_url,refresh_minutes,status,detail,last_checked_at,last_updated_at) VALUES('content','CostaCams curated feed',?,1440,'ok',?,NOW(),NOW()) ON DUPLICATE KEY UPDATE source_name=VALUES(source_name),source_url=VALUES(source_url),refresh_minutes=1440,status='ok',detail=VALUES(detail),last_checked_at=NOW(),last_updated_at=NOW()");
$st->execute([$feedUrl,"Imported $count news/events and $acount shark alerts"]);
@unlink(sys_get_temp_dir().'/costacams-home-v6.html');
@unlink(sys_get_temp_dir().'/costacams-app-home-v1.html');
foreach(glob(sys_get_temp_dir().'/costacams-app-channel-*-v2.html')?:[] as $f)@unlink($f);
@unlink(sys_get_temp_dir().'/costacams-map-home-v2.html');
echo "items=$count shark_alerts=$acount\n";
