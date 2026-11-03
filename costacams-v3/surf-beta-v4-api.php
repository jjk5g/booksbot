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
 PRIMARY KEY(email_hash,sample_date), INDEX idx_sample_ip_date(ip_hash,sample_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS surf_beta_mail_log (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email_hash CHAR(64) NOT NULL,
 mail_type VARCHAR(40) NOT NULL,
 status ENUM('attempted','accepted','failed') NOT NULL DEFAULT 'attempted',
 detail VARCHAR(255) DEFAULT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_mail_email_time(email_hash,created_at), INDEX idx_mail_type_time(mail_type,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function ccv4_secret(): string {$c=cc_config();return hash('sha256','surf-beta-v2|'.($c['db_pass']??'fallback').'|costacams');}
function ccv4_token(int $id,string $email,string $purpose): string {$sig=hash_hmac('sha256',$id.'|'.$purpose.'|'.strtolower($email),ccv4_secret());return rtrim(strtr(base64_encode($id.'|'.$purpose.'|'.$sig),'+/','-_'),'=');}
function ccv4_parse_token(string $token): ?array {$raw=base64_decode(strtr($token,'-_','+/'),true);if(!$raw)return null;$p=explode('|',$raw);return count($p)===3&&ctype_digit($p[0])?[(int)$p[0],$p[1],$p[2]]:null;}
function ccv4_valid_manage(PDO $pdo,string $token): ?array {$p=ccv4_parse_token($token);if(!$p||$p[1]!=='manage')return null;$st=$pdo->prepare('SELECT * FROM surf_beta_subscribers WHERE id=? LIMIT 1');$st->execute([$p[0]]);$r=$st->fetch();if(!$r)return null;return hash_equals(ccv4_token((int)$r['id'],(string)$r['email'],'manage'),$token)?$r:null;}
function ccv4_client_ip(): string {$c=(string)($_SERVER['HTTP_CF_CONNECTING_IP']??'');if($c!==''&&filter_var($c,FILTER_VALIDATE_IP))return $c;$r=(string)($_SERVER['REMOTE_ADDR']??'unknown');return filter_var($r,FILTER_VALIDATE_IP)?$r:'unknown';}
function ccv4_mail(PDO $pdo,string $to,string $type,string $subject,string $html): array {
  $eh=hash('sha256',strtolower($to).'|'.ccv4_secret());
  $pdo->prepare("INSERT INTO surf_beta_mail_log(email_hash,mail_type,status,detail) VALUES(?,?, 'attempted','PHP mail transport')")->execute([$eh,$type]);$id=(int)$pdo->lastInsertId();
  $headers="MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: CostaCams <support@costacams.com>\r\nReply-To: support@costacams.com\r\n";
  $ok=@mail($to,$subject,$html,$headers,'-f support@costacams.com');
  $pdo->prepare("UPDATE surf_beta_mail_log SET status=?,detail=? WHERE id=?")->execute([$ok?'accepted':'failed',$ok?'Accepted by local mail transport; inbox delivery is not confirmed.':'Local mail transport rejected the message.',$id]);
  return ['accepted'=>$ok,'log_id'=>$id];
}
function ccv4_uv(float $u): string {if($u<3)return 'UV low';if($u<6)return 'UV moderate — SPF50+ water-resistant sunscreen recommended';if($u<8)return 'UV high — SPF50+ + UPF rashie';if($u<11)return 'UV very high — strong sun protection';return 'UV extreme — maximum sun protection';}
function ccv4_sample_html(array $rank): string {
  $top=array_slice($rank,0,3);$a=$top[0];$h=$a['hour'];$rows='';foreach($top as $i=>$x){$hh=$x['hour'];$rows.='<tr><td style="padding:9px;border-bottom:1px solid #e6e9ea">#'.($i+1).' <b>'.htmlspecialchars((string)$x['spot']['name']).'</b></td><td style="padding:9px;border-bottom:1px solid #e6e9ea">'.(int)$x['score'].'/100</td><td style="padding:9px;border-bottom:1px solid #e6e9ea">'.number_format((float)($hh['wave_height']??0),1).'m · '.round((float)($hh['wave_period']??0)).'s · '.htmlspecialchars((string)($hh['wind_compass']??'—')).' '.round((float)($hh['wind_speed']??0)).'km/h</td></tr>';}
  return '<div style="font-family:Arial,sans-serif;max-width:660px;margin:auto;color:#172126"><div style="font-size:12px;font-weight:bold;color:#28798a">COSTACAMS · SAMPLE SURF BRIEF</div><h1>'.$a['spot']['name'].' · '.$a['score'].'/100</h1><p>This is a one-time example. You are not subscribed.</p><p>🌊 '.number_format((float)($h['wave_height']??0),1).' m · '.round((float)($h['wave_period']??0)).' s &nbsp; ⚡ '.number_format((float)($h['energy_kwm']??0),1).' kW/m &nbsp; 💨 '.htmlspecialchars((string)($h['wind_compass']??'—')).' '.round((float)($h['wind_speed']??0)).' km/h</p><p>☀️ UV '.number_format((float)($h['uv']??0),1).' — '.htmlspecialchars(ccv4_uv((float)($h['uv']??0))).'</p><table style="border-collapse:collapse;width:100%;margin:16px 0">'.$rows.'</table><p><a href="https://costacams.com/ccdeploy-v3/costacams-v3/surf-match-beta-v4.php">Open Surf Match Beta</a></p><p style="font-size:11px;color:#778">Forecast/model guidance only. Check local conditions, lifeguards and your own ability.</p></div>';
}
function ccv4_manage_mail(PDO $pdo,array $r,string $reason='manage'): array {
  $base='https://costacams.com/ccdeploy-v3/costacams-v3/surf-match-beta-v4.php';$email=(string)$r['email'];
  $manage=$base.'?manage='.rawurlencode(ccv4_token((int)$r['id'],$email,'manage'));$unsub=$base.'?unsubscribe='.rawurlencode(ccv4_token((int)$r['id'],$email,'unsubscribe'));
  $html='<div style="font-family:Arial,sans-serif;max-width:620px;margin:auto;color:#172024"><h2>CostaCams Surf Profile</h2><p>Use your private link to edit your saved Surf Match profile.</p><p><a style="display:inline-block;padding:12px 18px;background:#0d6578;color:white;text-decoration:none;border-radius:8px" href="'.htmlspecialchars($manage).'">Edit my Surf Profile</a></p><p style="font-size:12px;color:#667">No password is required. Keep this link private.</p><p style="font-size:11px"><a href="'.htmlspecialchars($unsub).'">Unsubscribe</a></p></div>';
  return ccv4_mail($pdo,$email,$reason,'Your CostaCams Surf Profile link',$html);
}

$action=(string)($_POST['action']??$_GET['action']??'');
if(in_array($action,['postcode','route_table'],true)){require __DIR__.'/surf-beta-v3-api.php';exit;}

if($action==='sample'){
  if(!empty($_POST['website'])){echo json_encode(['ok'=>true]);exit;}
  $email=strtolower(trim((string)($_POST['email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Enter a valid email first.']);exit;}
  $eh=hash('sha256',$email.'|'.ccv4_secret());$ih=hash('sha256',ccv4_client_ip().'|'.ccv4_secret());$today=date('Y-m-d');
  $st=$pdo->prepare('SELECT sample_count FROM surf_beta_sample_log WHERE email_hash=? AND sample_date=?');$st->execute([$eh,$today]);if((int)($st->fetchColumn()?:0)>=1){http_response_code(429);echo json_encode(['ok'=>false,'message'=>'A sample has already been requested for this email today. Please check inbox/spam.']);exit;}
  $ipst=$pdo->prepare('SELECT COALESCE(SUM(sample_count),0) FROM surf_beta_sample_log WHERE ip_hash=? AND sample_date=?');$ipst->execute([$ih,$today]);if((int)$ipst->fetchColumn()>=3){http_response_code(429);echo json_encode(['ok'=>false,'message'=>'Sample limit reached for this connection today.']);exit;}
  $pdo->prepare('INSERT INTO surf_beta_sample_log(email_hash,ip_hash,sample_date,sample_count,last_sent_at) VALUES(?,?,?,1,NOW()) ON DUPLICATE KEY UPDATE sample_count=sample_count+1,last_sent_at=NOW(),ip_hash=VALUES(ip_hash)')->execute([$eh,$ih,$today]);
  $profile=json_decode((string)($_POST['profile']??'{}'),true);if(!is_array($profile))$profile=[];$profile=array_replace(cc_surf_profile_defaults(),$profile);$rank=cc_surf_rank(cc_surf_load_spots($pdo),$profile,cc_surf_selected_epoch($profile));if(!$rank){http_response_code(503);echo json_encode(['ok'=>false,'message'=>'Forecast unavailable. Your sample attempt is locked for today to protect the service.']);exit;}
  $mail=ccv4_mail($pdo,$email,'sample','Your CostaCams Surf Brief sample',ccv4_sample_html($rank));
  if(!$mail['accepted']){http_response_code(503);echo json_encode(['ok'=>false,'message'=>'The mail server rejected this attempt. It is locked for today to prevent abuse.']);exit;}
  echo json_encode(['ok'=>true,'message'=>'Sample request accepted by the mail server. Inbox delivery is not yet confirmed; check spam too.','cooldown'=>'1_per_day']);exit;
}

if($action==='manage_request'){
  $email=strtolower(trim((string)($_POST['email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Enter a valid email.']);exit;}
  $st=$pdo->prepare('SELECT * FROM surf_beta_subscribers WHERE email=? LIMIT 1');$st->execute([$email]);$r=$st->fetch();
  if($r){$recent=!empty($r['last_email_at'])&&strtotime((string)$r['last_email_at'])>time()-900;if(!$recent){ccv4_manage_mail($pdo,$r,'manage_link');$pdo->prepare('UPDATE surf_beta_subscribers SET last_email_at=NOW() WHERE id=?')->execute([(int)$r['id']]);}}
  echo json_encode(['ok'=>true,'message'=>'If that email has a saved profile, a private edit link has been requested. Check inbox/spam.']);exit;
}

if($action==='subscribe'){
  if(!empty($_POST['website'])){echo json_encode(['ok'=>true]);exit;}
  $email=strtolower(trim((string)($_POST['email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Please enter a valid email.']);exit;}
  $profile=json_decode((string)($_POST['profile']??'{}'),true);if(!is_array($profile))$profile=cc_surf_profile_defaults();$freq=in_array((string)($_POST['frequency']??'morning'),['morning','evening','both'],true)?(string)$_POST['frequency']:'morning';
  $olat=isset($profile['origin_lat'])&&is_numeric($profile['origin_lat'])?round((float)$profile['origin_lat'],3):null;$olon=isset($profile['origin_lon'])&&is_numeric($profile['origin_lon'])?round((float)$profile['origin_lon'],3):null;
  $manageToken=(string)($_POST['manage_token']??'');$managed=$manageToken!==''?ccv4_valid_manage($pdo,$manageToken):null;
  $st=$pdo->prepare('SELECT * FROM surf_beta_subscribers WHERE email=? LIMIT 1');$st->execute([$email]);$old=$st->fetch();
  if($old && (bool)$old['confirmed'] && (!$managed || (int)$managed['id']!==(int)$old['id'])){
    $recent=!empty($old['last_email_at'])&&strtotime((string)$old['last_email_at'])>time()-900;if(!$recent){ccv4_manage_mail($pdo,$old,'manage_link');$pdo->prepare('UPDATE surf_beta_subscribers SET last_email_at=NOW() WHERE id=?')->execute([(int)$old['id']]);}
    echo json_encode(['ok'=>true,'message'=>'That email already has a confirmed profile. We requested a private edit link instead of changing it here.']);exit;
  }
  if($old){$id=(int)$old['id'];$pdo->prepare('UPDATE surf_beta_subscribers SET profile_json=?,email_frequency=?,origin_lat=?,origin_lon=?,active=1 WHERE id=?')->execute([json_encode($profile,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$freq,$olat,$olon,$id]);$confirmed=(bool)$old['confirmed'];}
  else{$pdo->prepare('INSERT INTO surf_beta_subscribers(email,profile_json,email_frequency,origin_lat,origin_lon) VALUES(?,?,?,?,?)')->execute([$email,json_encode($profile,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$freq,$olat,$olon]);$id=(int)$pdo->lastInsertId();$confirmed=false;}
  if($confirmed){echo json_encode(['ok'=>true,'confirmed'=>true,'message'=>'Profile changes saved.']);exit;}
  $base='https://costacams.com/ccdeploy-v3/costacams-v3/surf-match-beta-v4.php';$confirm=$base.'?confirm='.rawurlencode(ccv4_token($id,$email,'confirm'));$manage=$base.'?manage='.rawurlencode(ccv4_token($id,$email,'manage'));$unsub=$base.'?unsubscribe='.rawurlencode(ccv4_token($id,$email,'unsubscribe'));
  $html='<div style="font-family:Arial,sans-serif;max-width:620px;margin:auto;color:#172024"><h2>Confirm CostaCams Surf Brief</h2><p><a style="display:inline-block;padding:12px 18px;background:#0d6578;color:white;text-decoration:none;border-radius:8px" href="'.htmlspecialchars($confirm).'">Confirm Surf Brief</a></p><p><a href="'.htmlspecialchars($manage).'">Edit my Surf Profile</a></p><p style="font-size:11px"><a href="'.htmlspecialchars($unsub).'">Unsubscribe</a></p></div>';
  $mail=ccv4_mail($pdo,$email,'confirmation','Confirm your CostaCams Surf Brief',$html);$pdo->prepare('UPDATE surf_beta_subscribers SET last_email_at=NOW() WHERE id=?')->execute([$id]);
  echo json_encode(['ok'=>true,'confirmed'=>false,'mail_accepted'=>$mail['accepted'],'message'=>$mail['accepted']?'Profile saved. Confirmation was accepted by the mail server; check inbox/spam.':'Profile saved, but the local mail transport rejected the confirmation email.']);exit;
}

http_response_code(400);echo json_encode(['ok'=>false,'message'=>'Unknown action.']);
