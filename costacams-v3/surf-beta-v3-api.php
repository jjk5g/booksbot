<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/surf-beta-engine.php';

header('Content-Type: application/json; charset=utf-8');
$pdo=cc_db();
if(!$pdo){http_response_code(503);echo json_encode(['ok'=>false,'message'=>'Database unavailable.']);exit;}

$pdo->exec("CREATE TABLE IF NOT EXISTS surf_beta_subscribers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(190) NOT NULL UNIQUE,
 confirmed TINYINT(1) NOT NULL DEFAULT 0,
 active TINYINT(1) NOT NULL DEFAULT 1,
 profile_json MEDIUMTEXT NOT NULL,
 email_frequency ENUM('morning','evening','both') NOT NULL DEFAULT 'morning',
 origin_lat DECIMAL(8,3) NULL,
 origin_lon DECIMAL(8,3) NULL,
 last_email_at DATETIME NULL,
 last_morning_date DATE NULL,
 last_evening_date DATE NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS surf_beta_sample_log (
 email_hash CHAR(64) NOT NULL,
 ip_hash CHAR(64) NOT NULL,
 sample_date DATE NOT NULL,
 sample_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
 last_sent_at DATETIME NULL,
 PRIMARY KEY(email_hash,sample_date),
 INDEX idx_sample_ip_date(ip_hash,sample_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function ccv3_secret(): string {$c=cc_config();return hash('sha256','surf-beta-v2|'.($c['db_pass']??'fallback').'|costacams');}
function ccv3_token(int $id,string $email,string $purpose): string {$sig=hash_hmac('sha256',$id.'|'.$purpose.'|'.strtolower($email),ccv3_secret());return rtrim(strtr(base64_encode($id.'|'.$purpose.'|'.$sig),'+/','-_'),'=');}
function ccv3_mail(string $to,string $subject,string $html): bool {$h="MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: CostaCams <support@costacams.com>\r\nReply-To: support@costacams.com\r\n";return @mail($to,$subject,$html,$h);}
function ccv3_http_json(string $url,int $timeout=8): ?array {
  if(function_exists('curl_init')){
    $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>$timeout,CURLOPT_USERAGENT=>'CostaCamsSurfBeta/3.0 (+https://costacams.com)',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_HTTPHEADER=>['Accept: application/json']]);$raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($raw===false||$code<200||$code>=400)return null;
  } else {$ctx=stream_context_create(['http'=>['timeout'=>$timeout,'user_agent'=>'CostaCamsSurfBeta/3.0 (+https://costacams.com)']]);$raw=@file_get_contents($url,false,$ctx);if(!$raw)return null;}
  $j=json_decode((string)$raw,true);return is_array($j)?$j:null;
}
function ccv3_client_ip_hash(): string {$ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');return hash('sha256',$ip.'|'.ccv3_secret());}
function ccv3_uv_text(float $u): string {if($u<3)return 'UV low';if($u<6)return 'UV moderate — SPF50+ water-resistant sunscreen recommended for the water';if($u<8)return 'UV high — SPF50+ water-resistant sunscreen + UPF rashie';if($u<11)return 'UV very high — strong sun protection';return 'UV extreme — maximum sun protection';}
function ccv3_sample_html(array $rank,array $profile): string {
  $top=array_slice($rank,0,3);$a=$top[0];$h=$a['hour'];$rows='';
  foreach($top as $i=>$x){$hh=$x['hour'];$rows.='<tr><td style="padding:9px;border-bottom:1px solid #e6e9ea">#'.($i+1).' <b>'.htmlspecialchars((string)$x['spot']['name']).'</b></td><td style="padding:9px;border-bottom:1px solid #e6e9ea">'.(int)$x['score'].'/100</td><td style="padding:9px;border-bottom:1px solid #e6e9ea">'.number_format((float)($hh['wave_height']??0),1).'m · '.round((float)($hh['wave_period']??0)).'s · '.htmlspecialchars((string)($hh['wind_compass']??'—')).' '.round((float)($hh['wind_speed']??0)).'km/h</td></tr>';}
  $travel='';if($a['travel_minutes']!==null){$travel='<p>🚗 Approx. '.$a['travel_minutes'].' min from your saved starting point.</p>';}
  return '<div style="font-family:Arial,sans-serif;max-width:660px;margin:auto;color:#172126"><div style="font-size:12px;font-weight:bold;color:#28798a">COSTACAMS · SAMPLE SURF BRIEF</div><h1 style="margin:8px 0 4px">'.$a['spot']['name'].' · '.$a['score'].'/100</h1><p style="margin-top:0;color:#667">This is a one-time example using the Surf Match settings you just selected. You are not subscribed by receiving this sample.</p><p>🌊 '.number_format((float)($h['wave_height']??0),1).' m · '.round((float)($h['wave_period']??0)).' s &nbsp; ⚡ '.number_format((float)($h['energy_kwm']??0),1).' kW/m &nbsp; 💨 '.htmlspecialchars((string)($h['wind_compass']??'—')).' '.round((float)($h['wind_speed']??0)).' km/h</p><p>☀️ UV '.number_format((float)($h['uv']??0),1).' — '.htmlspecialchars(ccv3_uv_text((float)($h['uv']??0))).'</p>'.$travel.'<table style="border-collapse:collapse;width:100%;margin:16px 0">'.$rows.'</table><p><a style="display:inline-block;background:#0d6578;color:white;text-decoration:none;padding:11px 16px;border-radius:8px" href="https://costacams.com/ccdeploy-v3/costacams-v3/surf-match-beta-v3.php">Open Surf Match Beta</a></p><p style="font-size:11px;color:#778">Forecast/model guidance only. Check local conditions, lifeguards and your own ability before entering the water.</p></div>';
}

$action=(string)($_POST['action']??$_GET['action']??'');
if($action==='postcode'){
  $pc=preg_replace('/\D/','',(string)($_POST['postcode']??''));
  if(!preg_match('/^\d{4}$/',$pc)){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Enter a 4-digit Australian postcode.']);exit;}
  $url='https://nominatim.openstreetmap.org/search?format=jsonv2&country=Australia&postalcode='.rawurlencode($pc).'&countrycodes=au&limit=1';$j=ccv3_http_json($url,8);
  if(!$j||empty($j[0]['lat'])||empty($j[0]['lon'])){http_response_code(404);echo json_encode(['ok'=>false,'message'=>'Could not locate that postcode right now.']);exit;}
  echo json_encode(['ok'=>true,'postcode'=>$pc,'lat'=>(float)$j[0]['lat'],'lon'=>(float)$j[0]['lon'],'label'=>'Postcode '.$pc]);exit;
}

if($action==='route_table'){
  $olat=filter_var($_POST['origin_lat']??null,FILTER_VALIDATE_FLOAT);$olon=filter_var($_POST['origin_lon']??null,FILTER_VALIDATE_FLOAT);$dest=json_decode((string)($_POST['destinations']??'[]'),true);
  if($olat===false||$olon===false||!is_array($dest)||count($dest)<1||count($dest)>40){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Invalid route request.']);exit;}
  if($olat<-45||$olat>-10||$olon<110||$olon>155){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Starting point must be in Australia.']);exit;}
  $coords=[sprintf('%.5f,%.5f',$olon,$olat)];$slugs=[];$fallback=[];
  foreach($dest as $d){if(!is_array($d)||!isset($d['slug'],$d['lat'],$d['lon'])||!is_numeric($d['lat'])||!is_numeric($d['lon']))continue;$lat=(float)$d['lat'];$lon=(float)$d['lon'];if($lat<-45||$lat>-10||$lon<110||$lon>155)continue;$slugs[]=(string)$d['slug'];$coords[]=sprintf('%.5f,%.5f',$lon,$lat);$fallback[(string)$d['slug']]=['minutes'=>cc_surf_travel_minutes((float)$olat,(float)$olon,$lat,$lon),'distance_km'=>round(cc_surf_haversine_km((float)$olat,(float)$olon,$lat,$lon)*1.28,1)];}
  if(!$slugs){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'No valid destinations.']);exit;}
  $idx=range(1,count($slugs));$url='https://router.project-osrm.org/table/v1/driving/'.implode(';',$coords).'?sources=0&destinations='.implode(';',$idx).'&annotations=duration,distance';$j=ccv3_http_json($url,12);$out=[];
  if($j&&($j['code']??'')==='Ok'&&!empty($j['durations'][0])){foreach($slugs as $i=>$slug){$sec=$j['durations'][0][$i]??null;$met=$j['distances'][0][$i]??null;if(is_numeric($sec))$out[$slug]=['minutes'=>max(1,(int)round($sec/60)),'distance_km'=>is_numeric($met)?round($met/1000,1):null];}echo json_encode(['ok'=>true,'source'=>'road_route','routes'=>$out]);exit;}
  echo json_encode(['ok'=>true,'source'=>'approximate','routes'=>$fallback]);exit;
}

if($action==='sample'){
  if(!empty($_POST['website'])){echo json_encode(['ok'=>true]);exit;}
  $email=strtolower(trim((string)($_POST['email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Enter a valid email first.']);exit;}
  $eh=hash('sha256',$email.'|'.ccv3_secret());$ih=ccv3_client_ip_hash();$today=date('Y-m-d');$st=$pdo->prepare('SELECT * FROM surf_beta_sample_log WHERE email_hash=? AND sample_date=?');$st->execute([$eh,$today]);$row=$st->fetch();
  if($row&&(!empty($row['last_sent_at'])&&strtotime((string)$row['last_sent_at'])>time()-900)){http_response_code(429);echo json_encode(['ok'=>false,'message'=>'A sample was sent recently. Please check your inbox.']);exit;}
  if($row&&(int)$row['sample_count']>=3){http_response_code(429);echo json_encode(['ok'=>false,'message'=>'Sample limit reached for today.']);exit;}
  $ipst=$pdo->prepare('SELECT COALESCE(SUM(sample_count),0) FROM surf_beta_sample_log WHERE ip_hash=? AND sample_date=?');$ipst->execute([$ih,$today]);if((int)$ipst->fetchColumn()>=5){http_response_code(429);echo json_encode(['ok'=>false,'message'=>'Sample limit reached for this connection today.']);exit;}
  $profile=json_decode((string)($_POST['profile']??'{}'),true);if(!is_array($profile))$profile=[];$profile=array_replace(cc_surf_profile_defaults(),$profile);$spots=cc_surf_load_spots($pdo);$rank=cc_surf_rank($spots,$profile,cc_surf_selected_epoch($profile));if(!$rank){http_response_code(503);echo json_encode(['ok'=>false,'message'=>'Forecast data is unavailable right now.']);exit;}
  $html=ccv3_sample_html($rank,$profile);$sent=ccv3_mail($email,'Your CostaCams Surf Brief sample',$html);if(!$sent){http_response_code(503);echo json_encode(['ok'=>false,'message'=>'The sample could not be sent right now.']);exit;}
  $pdo->prepare('INSERT INTO surf_beta_sample_log(email_hash,ip_hash,sample_date,sample_count,last_sent_at) VALUES(?,?,?,1,NOW()) ON DUPLICATE KEY UPDATE sample_count=sample_count+1,last_sent_at=NOW(),ip_hash=VALUES(ip_hash)')->execute([$eh,$ih,$today]);echo json_encode(['ok'=>true,'message'=>'Sample sent. Check your inbox — this did not subscribe you.']);exit;
}

if($action==='subscribe'){
  if(!empty($_POST['website'])){echo json_encode(['ok'=>true]);exit;}
  $email=strtolower(trim((string)($_POST['email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Please enter a valid email.']);exit;}
  $profile=json_decode((string)($_POST['profile']??'{}'),true);if(!is_array($profile))$profile=cc_surf_profile_defaults();$freq=in_array((string)($_POST['frequency']??'morning'),['morning','evening','both'],true)?(string)$_POST['frequency']:'morning';
  $olat=isset($profile['origin_lat'])&&is_numeric($profile['origin_lat'])?round((float)$profile['origin_lat'],3):null;$olon=isset($profile['origin_lon'])&&is_numeric($profile['origin_lon'])?round((float)$profile['origin_lon'],3):null;
  $sel=$pdo->prepare('SELECT * FROM surf_beta_subscribers WHERE email=? LIMIT 1');$sel->execute([$email]);$old=$sel->fetch();
  if($old){$id=(int)$old['id'];$pdo->prepare('UPDATE surf_beta_subscribers SET profile_json=?,email_frequency=?,origin_lat=?,origin_lon=?,active=1 WHERE id=?')->execute([json_encode($profile,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$freq,$olat,$olon,$id]);$confirmed=(bool)$old['confirmed'];}
  else{$pdo->prepare('INSERT INTO surf_beta_subscribers(email,profile_json,email_frequency,origin_lat,origin_lon) VALUES(?,?,?,?,?)')->execute([$email,json_encode($profile,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$freq,$olat,$olon]);$id=(int)$pdo->lastInsertId();$confirmed=false;}
  $base='https://costacams.com/ccdeploy-v3/costacams-v3/surf-match-beta-v3.php';$confirm=$base.'?confirm='.rawurlencode(ccv3_token($id,$email,'confirm'));$manage=$base.'?manage='.rawurlencode(ccv3_token($id,$email,'manage'));$unsub=$base.'?unsubscribe='.rawurlencode(ccv3_token($id,$email,'unsubscribe'));
  $body='<div style="font-family:Arial,sans-serif;max-width:620px;margin:auto;color:#172024"><h2>CostaCams Surf Brief</h2>'.($confirmed?'<p>Your Surf Match preferences were updated.</p>':'<p>Confirm your email to activate recurring Surf Briefs.</p><p><a style="display:inline-block;padding:12px 18px;background:#0d6578;color:white;text-decoration:none;border-radius:8px" href="'.htmlspecialchars($confirm).'">Confirm Surf Brief</a></p>').'<p><a href="'.htmlspecialchars($manage).'">Edit my saved Surf Profile</a></p><p style="font-size:12px;color:#667">No password is required. Keep this private manage link.</p><p style="font-size:11px"><a href="'.htmlspecialchars($unsub).'">Unsubscribe</a></p></div>';
  $sent=ccv3_mail($email,$confirmed?'CostaCams Surf Match profile updated':'Confirm your CostaCams Surf Brief',$body);
  echo json_encode(['ok'=>true,'confirmed'=>$confirmed,'mail_sent'=>$sent,'manage_url'=>$manage,'message'=>$confirmed?'Profile changes saved.':'Profile saved. Check your inbox to confirm recurring Surf Briefs.']);exit;
}

http_response_code(400);echo json_encode(['ok'=>false,'message'=>'Unknown action.']);