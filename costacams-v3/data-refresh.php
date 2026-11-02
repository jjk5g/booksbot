<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__.'/bootstrap.php';

$pdo=cc_db();
if(!$pdo){fwrite(STDERR,"database unavailable\n");exit(1);}

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

$pdo->exec("CREATE TABLE IF NOT EXISTS shark_status (
  region_key VARCHAR(64) PRIMARY KEY,
  region_name VARCHAR(120) NOT NULL,
  jurisdiction VARCHAR(40) NOT NULL,
  source_name VARCHAR(160) NOT NULL,
  source_url TEXT NOT NULL,
  status_text VARCHAR(255) NOT NULL,
  alert_mode ENUM('official_app','lifeguard_official','public_web') NOT NULL,
  source_reachable TINYINT(1) NOT NULL DEFAULT 0,
  checked_at DATETIME NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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

// NSW observes daylight saving; Queensland does not.
$pdo->exec("UPDATE locations SET timezone='Australia/Brisbane' WHERE region='Gold Coast'");
$pdo->exec("UPDATE locations SET timezone='Australia/Sydney' WHERE region IN ('Tweed Coast','Byron Shire')");

function cc_status(PDO $pdo,string $dataset,string $source,string $url,int $minutes,string $status,string $detail,bool $updated): void {
  $st=$pdo->prepare("INSERT INTO system_data_status(dataset,source_name,source_url,refresh_minutes,status,detail,last_checked_at,last_updated_at)
    VALUES(?,?,?,?,?,?,NOW(),IF(?,NOW(),NULL))
    ON DUPLICATE KEY UPDATE source_name=VALUES(source_name),source_url=VALUES(source_url),refresh_minutes=VALUES(refresh_minutes),status=VALUES(status),detail=VALUES(detail),last_checked_at=NOW(),last_updated_at=IF(?,NOW(),last_updated_at)");
  $st->execute([$dataset,$source,$url,$minutes,$status,$detail,$updated?1:0,$updated?1:0]);
}
function cc_http_json(string $url,int $timeout=18): ?array {
  $ctx=stream_context_create(['http'=>['timeout'=>$timeout,'user_agent'=>'CostaCamsData/1.0 (+https://costacams.com)','ignore_errors'=>true]]);
  $raw=@file_get_contents($url,false,$ctx);
  if(!$raw)return null;
  $j=json_decode($raw,true);
  return is_array($j)&&empty($j['error'])?$j:null;
}
function cc_reachable(string $url): bool {
  $ctx=stream_context_create(['http'=>['timeout'=>8,'user_agent'=>'CostaCamsData/1.0 (+https://costacams.com)','ignore_errors'=>true]]);
  $h=@get_headers($url,true,$ctx);
  if(!$h)return false;
  $line=is_array($h[0]??null)?end($h[0]):($h[0]??'');
  return is_string($line)&&preg_match('~\s(2\d\d|3\d\d)\s~',$line)===1;
}

$locations=$pdo->query("SELECT slug,name,region,lat,lon,timezone FROM locations WHERE active=1 ORDER BY id")->fetchAll();
$weatherOk=0;$marineOk=0;
foreach($locations as $loc){
  $lat=(float)$loc['lat'];$lon=(float)$loc['lon'];$tz=rawurlencode((string)$loc['timezone']);
  $wurl='https://api.open-meteo.com/v1/forecast?latitude='.$lat.'&longitude='.$lon.'&current=temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,rain,showers,weather_code,cloud_cover,wind_speed_10m,wind_direction_10m,wind_gusts_10m&hourly=precipitation_probability,weather_code,wind_speed_10m,wind_direction_10m,wind_gusts_10m&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,precipitation_sum,rain_sum,showers_sum,sunrise,sunset,uv_index_max,wind_speed_10m_max,wind_gusts_10m_max,wind_direction_10m_dominant&timezone='.$tz.'&forecast_days=7';
  $murl='https://marine-api.open-meteo.com/v1/marine?latitude='.$lat.'&longitude='.$lon.'&current=wave_height,wave_direction,wave_period,swell_wave_height,swell_wave_direction,swell_wave_period,sea_surface_temperature,sea_level_height_msl,ocean_current_velocity,ocean_current_direction&hourly=wave_height,wave_direction,wave_period,swell_wave_height,swell_wave_direction,swell_wave_period,sea_level_height_msl,sea_surface_temperature,ocean_current_velocity,ocean_current_direction&daily=wave_height_max,wave_direction_dominant,wave_period_max,swell_wave_height_max,swell_wave_direction_dominant,swell_wave_period_max&timezone='.$tz.'&forecast_days=7';
  $w=cc_http_json($wurl);if($w){cc_cache_put('weather:'.$loc['slug'],$w,1200);$weatherOk++;}
  $m=cc_http_json($murl);if($m){cc_cache_put('marine:'.$loc['slug'],$m,1200);$marineOk++;}
}
$total=count($locations);
cc_status($pdo,'weather','Open-Meteo','https://open-meteo.com/en/docs',15,$weatherOk===$total?'ok':($weatherOk?'partial':'error'),"Updated $weatherOk of $total coastal locations",$weatherOk>0);
cc_status($pdo,'marine','Open-Meteo Marine','https://open-meteo.com/en/docs/marine-weather-api',15,$marineOk===$total?'ok':($marineOk?'partial':'error'),"Updated $marineOk of $total coastal locations",$marineOk>0);

$sharkSources=[
 ['gold-coast','Gold Coast','QLD','Queensland SharkSmart','https://www.dpi.qld.gov.au/initiatives/sharksmart','Official SharkSmart monitoring; follow lifeguard beach closures and warnings.','lifeguard_official'],
 ['tweed-coast','Tweed Coast','NSW','NSW SharkSmart','https://www.sharksmart.nsw.gov.au/sharksmart-app','Real-time shark alerts are provided by the official NSW SharkSmart app.','official_app'],
 ['byron-shire','Byron Shire','NSW','NSW SharkSmart','https://www.sharksmart.nsw.gov.au/sharksmart-app','Real-time shark alerts are provided by the official NSW SharkSmart app.','official_app']
];
$sst=$pdo->prepare("INSERT INTO shark_status(region_key,region_name,jurisdiction,source_name,source_url,status_text,alert_mode,source_reachable,checked_at) VALUES(?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE region_name=VALUES(region_name),jurisdiction=VALUES(jurisdiction),source_name=VALUES(source_name),source_url=VALUES(source_url),status_text=VALUES(status_text),alert_mode=VALUES(alert_mode),source_reachable=VALUES(source_reachable),checked_at=NOW()");
$reach=0;foreach($sharkSources as $s){$ok=cc_reachable($s[4]);if($ok)$reach++;$sst->execute([$s[0],$s[1],$s[2],$s[3],$s[4],$s[5],$s[6],$ok?1:0]);}
cc_status($pdo,'shark_safety','QLD SharkSmart / NSW SharkSmart','https://www.sharksmart.nsw.gov.au/',5,$reach===count($sharkSources)?'ok':($reach?'partial':'error'),"Official shark-safety sources reachable: $reach/".count($sharkSources),true);

$pdo->exec("UPDATE shark_alerts SET active=0 WHERE active=1 AND expires_at IS NOT NULL AND expires_at<NOW()");

echo "weather=$weatherOk/$total marine=$marineOk/$total shark_sources=$reach/".count($sharkSources)."\n";
