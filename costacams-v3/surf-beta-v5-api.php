<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/surf-beta-engine.php';
header('Content-Type: application/json; charset=utf-8');
$pdo=cc_db();
if(!$pdo){http_response_code(503);echo json_encode(['ok'=>false,'message'=>'Database unavailable.']);exit;}

$pdo->exec("CREATE TABLE IF NOT EXISTS surf_beta_profile_meta (
 email VARCHAR(190) NOT NULL PRIMARY KEY,
 origin_postcode VARCHAR(8) NULL,
 origin_lat DECIMAL(9,5) NULL,
 origin_lon DECIMAL(9,5) NULL,
 location_source VARCHAR(20) NULL,
 schedule_mode VARCHAR(30) NULL,
 schedule_hour DECIMAL(4,2) NULL,
 session_minutes SMALLINT UNSIGNED NULL,
 buffer_minutes SMALLINT UNSIGNED NULL,
 height_cm SMALLINT UNSIGNED NULL,
 weight_kg SMALLINT UNSIGNED NULL,
 board_litres DECIMAL(6,1) NULL,
 profile_json MEDIUMTEXT NOT NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function ccv5_http_json(string $url,int $timeout=8): ?array {
  if(!function_exists('curl_init'))return null;
  $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>$timeout,CURLOPT_USERAGENT=>'CostaCamsSurfBeta/5.0 (+https://costacams.com)',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_HTTPHEADER=>['Accept: application/json']]);
  $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  if($raw===false||$code<200||$code>=400)return null;$j=json_decode((string)$raw,true);return is_array($j)?$j:null;
}
function ccv5_known_postcode(string $pc): ?array {
  $m=[
   '4000'=>[-27.4698,153.0251,'Brisbane City'],'4215'=>[-27.9718,153.4066,'Southport'],'4216'=>[-27.9330,153.4010,'Runaway Bay / Biggera Waters'],'4217'=>[-28.0020,153.4280,'Surfers Paradise'],'4218'=>[-28.0397,153.4216,'Broadbeach / Mermaid Beach'],'4219'=>[-28.0710,153.4410,'Miami'],'4220'=>[-28.0870,153.4490,'Burleigh Heads'],'4221'=>[-28.1120,153.4660,'Palm Beach'],'4223'=>[-28.1450,153.4920,'Currumbin / Tugun'],'4224'=>[-28.1660,153.5150,'Bilinga / Kirra'],'4225'=>[-28.1730,153.5350,'Coolangatta'],'4226'=>[-28.0470,153.3540,'Robina'],'4227'=>[-28.0850,153.3780,'Reedy Creek'],'4228'=>[-28.1020,153.3370,'Tallebudgera Valley'],'4229'=>[-28.0930,153.4130,'Varsity Lakes'],'4230'=>[-28.0670,153.3930,'Merrimac'],'2481'=>[-28.6474,153.6020,'Byron Bay'],'2482'=>[-28.5530,153.5010,'Mullumbimby'],'2483'=>[-28.5260,153.5480,'Brunswick Heads / Ocean Shores'],'2484'=>[-28.3260,153.3940,'Murwillumbah'],'2485'=>[-28.1810,153.5400,'Tweed Heads'],'2486'=>[-28.2120,153.5450,'Tweed Heads South'],'2487'=>[-28.2570,153.5780,'Kingscliff'],'2488'=>[-28.3320,153.5720,'Cabarita / Bogangar'],'2489'=>[-28.2400,153.5560,'Pottsville / Tweed Coast']
  ];return isset($m[$pc])?['lat'=>$m[$pc][0],'lon'=>$m[$pc][1],'label'=>$m[$pc][2]]:null;
}

$action=(string)($_POST['action']??$_GET['action']??'');
if($action==='postcode'){
  $pc=preg_replace('/\D/','',(string)($_POST['postcode']??''));
  if(!preg_match('/^\d{4}$/',$pc)){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Enter a 4-digit Australian postcode.']);exit;}
  $known=ccv5_known_postcode($pc);
  if($known){echo json_encode(['ok'=>true,'postcode'=>$pc,'lat'=>$known['lat'],'lon'=>$known['lon'],'label'=>$known['label'].' · '.$pc,'source'=>'local_postcode_index']);exit;}
  $queries=[
   'https://nominatim.openstreetmap.org/search?format=jsonv2&countrycodes=au&postalcode='.rawurlencode($pc).'&limit=1',
   'https://nominatim.openstreetmap.org/search?format=jsonv2&countrycodes=au&q='.rawurlencode($pc.', Australia').'&limit=1',
   'https://nominatim.openstreetmap.org/search?format=jsonv2&q='.rawurlencode('postcode '.$pc.' Australia').'&limit=1'
  ];
  foreach($queries as $url){$j=ccv5_http_json($url,8);if($j&&!empty($j[0]['lat'])&&!empty($j[0]['lon'])){echo json_encode(['ok'=>true,'postcode'=>$pc,'lat'=>(float)$j[0]['lat'],'lon'=>(float)$j[0]['lon'],'label'=>'Postcode '.$pc,'source'=>'geocoder']);exit;}}
  http_response_code(404);echo json_encode(['ok'=>false,'message'=>'Postcode '.$pc.' could not be located right now. You can still use browser location.']);exit;
}

if($action==='route_table'){
  $olat=filter_var($_POST['origin_lat']??null,FILTER_VALIDATE_FLOAT);$olon=filter_var($_POST['origin_lon']??null,FILTER_VALIDATE_FLOAT);$dest=json_decode((string)($_POST['destinations']??'[]'),true);
  if($olat===false||$olon===false||!is_array($dest)||count($dest)<1||count($dest)>40){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Invalid route request.']);exit;}
  $coords=[sprintf('%.5f,%.5f',$olon,$olat)];$slugs=[];$fallback=[];
  foreach($dest as $d){if(!is_array($d)||!isset($d['slug'],$d['lat'],$d['lon'])||!is_numeric($d['lat'])||!is_numeric($d['lon']))continue;$lat=(float)$d['lat'];$lon=(float)$d['lon'];$slug=(string)$d['slug'];$slugs[]=$slug;$coords[]=sprintf('%.5f,%.5f',$lon,$lat);$fallback[$slug]=['minutes'=>cc_surf_travel_minutes((float)$olat,(float)$olon,$lat,$lon),'distance_km'=>round(cc_surf_haversine_km((float)$olat,(float)$olon,$lat,$lon)*1.28,1)];}
  if(!$slugs){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'No valid destinations.']);exit;}
  $idx=range(1,count($slugs));$url='https://router.project-osrm.org/table/v1/driving/'.implode(';',$coords).'?sources=0&destinations='.implode(';',$idx).'&annotations=duration,distance';$j=ccv5_http_json($url,12);$out=[];
  if($j&&($j['code']??'')==='Ok'&&!empty($j['durations'][0])){foreach($slugs as $i=>$slug){$sec=$j['durations'][0][$i]??null;$met=$j['distances'][0][$i]??null;if(is_numeric($sec))$out[$slug]=['minutes'=>max(1,(int)round($sec/60)),'distance_km'=>is_numeric($met)?round($met/1000,1):null];}echo json_encode(['ok'=>true,'source'=>'road_route','routes'=>$out]);exit;}
  echo json_encode(['ok'=>true,'source'=>'approximate','routes'=>$fallback]);exit;
}

if($action==='subscribe'){
  $email=strtolower(trim((string)($_POST['email']??'')));$p=json_decode((string)($_POST['profile']??'{}'),true);
  if(filter_var($email,FILTER_VALIDATE_EMAIL)&&is_array($p)){
    $pc=preg_replace('/[^0-9]/','',(string)($p['origin_postcode']??''));$src=(string)($p['location_source']??($pc!==''?'postcode':(!empty($p['origin_lat'])?'browser':'')));
    $st=$pdo->prepare("INSERT INTO surf_beta_profile_meta(email,origin_postcode,origin_lat,origin_lon,location_source,schedule_mode,schedule_hour,session_minutes,buffer_minutes,height_cm,weight_kg,board_litres,profile_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE origin_postcode=VALUES(origin_postcode),origin_lat=VALUES(origin_lat),origin_lon=VALUES(origin_lon),location_source=VALUES(location_source),schedule_mode=VALUES(schedule_mode),schedule_hour=VALUES(schedule_hour),session_minutes=VALUES(session_minutes),buffer_minutes=VALUES(buffer_minutes),height_cm=VALUES(height_cm),weight_kg=VALUES(weight_kg),board_litres=VALUES(board_litres),profile_json=VALUES(profile_json),updated_at=NOW()");
    $st->execute([$email,$pc?:null,is_numeric($p['origin_lat']??null)?round((float)$p['origin_lat'],5):null,is_numeric($p['origin_lon']??null)?round((float)$p['origin_lon'],5):null,$src?:null,(string)($p['time_mode']??''),is_numeric($p['session_hour']??null)?(float)$p['session_hour']:null,(int)($p['session_minutes']??40),(int)($p['buffer_minutes']??10),(int)($p['height_cm']??0)?:null,(int)($p['weight_kg']??0)?:null,is_numeric($p['board_litres']??null)?(float)$p['board_litres']:null,json_encode($p,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
  }
}

require __DIR__.'/surf-beta-v4-api.php';
