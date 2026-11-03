<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/surf-beta-engine.php';
header('Content-Type: application/json; charset=utf-8');

$pdo=cc_db();
$action=(string)($_POST['action']??$_GET['action']??'');
if($action==='health'){
    echo json_encode(['ok'=>true,'transport'=>'php_mail','php_mail_available'=>function_exists('mail'),'database'=>(bool)$pdo]);
    exit;
}
if(!$pdo){http_response_code(503);echo json_encode(['ok'=>false,'message'=>'Database unavailable.']);exit;}

$pdo->exec("CREATE TABLE IF NOT EXISTS surf_beta_subscribers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(190) NOT NULL UNIQUE,
 confirmed TINYINT(1) NOT NULL DEFAULT 0,
 active TINYINT(1) NOT NULL DEFAULT 1,
 profile_json MEDIUMTEXT NOT NULL,
 email_frequency ENUM('morning','evening','both') NOT NULL DEFAULT 'morning',
 origin_lat DECIMAL(9,5) NULL,
 origin_lon DECIMAL(9,5) NULL,
 last_email_at DATETIME NULL,
 last_morning_date DATE NULL,
 last_evening_date DATE NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS surf_beta_sample_log_v6 (
 email_hash CHAR(64) NOT NULL,
 ip_hash CHAR(64) NOT NULL,
 sample_date DATE NOT NULL,
 sample_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
 last_attempt_at DATETIME NULL,
 PRIMARY KEY(email_hash,sample_date), INDEX idx_v6_sample_ip(ip_hash,sample_date)
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

function ccv6_add_column(PDO $pdo,string $name,string $definition): void {
    try{$st=$pdo->prepare('SHOW COLUMNS FROM surf_beta_subscribers LIKE ?');$st->execute([$name]);if(!$st->fetch())$pdo->exec('ALTER TABLE surf_beta_subscribers ADD COLUMN '.$name.' '.$definition);}catch(Throwable $e){}
}
ccv6_add_column($pdo,'origin_postcode','VARCHAR(8) NULL AFTER origin_lon');
ccv6_add_column($pdo,'location_source','VARCHAR(20) NULL AFTER origin_postcode');
ccv6_add_column($pdo,'schedule_mode','VARCHAR(30) NULL AFTER location_source');
ccv6_add_column($pdo,'schedule_hour','DECIMAL(4,2) NULL AFTER schedule_mode');
ccv6_add_column($pdo,'session_minutes','SMALLINT UNSIGNED NULL AFTER schedule_hour');
ccv6_add_column($pdo,'buffer_minutes','SMALLINT UNSIGNED NULL AFTER session_minutes');

function ccv6_secret(): string {$c=cc_config();return hash('sha256','surf-beta-v2|'.($c['db_pass']??'fallback').'|costacams');}
function ccv6_token(int $id,string $email,string $purpose): string {$sig=hash_hmac('sha256',$id.'|'.$purpose.'|'.strtolower($email),ccv6_secret());return rtrim(strtr(base64_encode($id.'|'.$purpose.'|'.$sig),'+/','-_'),'=');}
function ccv6_parse_token(string $token): ?array {$raw=base64_decode(strtr($token,'-_','+/'),true);if(!$raw)return null;$p=explode('|',$raw);return count($p)===3&&ctype_digit($p[0])?[(int)$p[0],$p[1],$p[2]]:null;}
function ccv6_valid_manage(PDO $pdo,string $token): ?array {$p=ccv6_parse_token($token);if(!$p||$p[1]!=='manage')return null;$st=$pdo->prepare('SELECT * FROM surf_beta_subscribers WHERE id=? LIMIT 1');$st->execute([$p[0]]);$r=$st->fetch();if(!$r)return null;return hash_equals(ccv6_token((int)$r['id'],(string)$r['email'],'manage'),$token)?$r:null;}
function ccv6_client_ip(): string {$cf=(string)($_SERVER['HTTP_CF_CONNECTING_IP']??'');if($cf!==''&&filter_var($cf,FILTER_VALIDATE_IP))return $cf;$r=(string)($_SERVER['REMOTE_ADDR']??'unknown');return filter_var($r,FILTER_VALIDATE_IP)?$r:'unknown';}
function ccv6_profile(array $p): array {$p=array_replace(cc_surf_profile_defaults(),$p);$p['session_minutes']=max(20,min(180,(int)($p['session_minutes']??40)));$p['buffer_minutes']=max(5,min(45,(int)($p['buffer_minutes']??10)));return $p;}

function ccv6_php_mail(PDO $pdo,string $to,string $type,string $subject,string $html): array {
    $eh=hash('sha256',strtolower($to).'|'.ccv6_secret());
    $pdo->prepare("INSERT INTO surf_beta_mail_log(email_hash,mail_type,status,detail) VALUES(?,?, 'attempted','PHP mail() v6')")->execute([$eh,$type]);
    $id=(int)$pdo->lastInsertId();
    $host=preg_replace('/[^a-z0-9.-]/i','',($_SERVER['HTTP_HOST']??'costacams.com'))?:'costacams.com';
    $msgId='<'.bin2hex(random_bytes(10)).'.'.time().'@'.$host.'>';
    $headers="MIME-Version: 1.0\r\n".
             "Content-Type: text/html; charset=UTF-8\r\n".
             "Content-Transfer-Encoding: 8bit\r\n".
             "From: CostaCams <support@costacams.com>\r\n".
             "Reply-To: support@costacams.com\r\n".
             "Date: ".date(DATE_RFC2822)."\r\n".
             "Message-ID: {$msgId}\r\n".
             "X-Mailer: CostaCams PHP mail\r\n";
    $ok=false;
    if(function_exists('mail')){
        $ok=@mail($to,$subject,$html,$headers,'-f support@costacams.com');
        if(!$ok)$ok=@mail($to,$subject,$html,$headers);
    }
    $detail=$ok?'Accepted by PHP mail(); final inbox delivery depends on the receiving mail system.':'PHP mail() rejected the message.';
    $pdo->prepare('UPDATE surf_beta_mail_log SET status=?,detail=? WHERE id=?')->execute([$ok?'accepted':'failed',$detail,$id]);
    return ['accepted'=>$ok,'log_id'=>$id,'transport'=>'php_mail'];
}
function ccv6_sample_html(array $rank): string {
    $top=array_slice($rank,0,3);$a=$top[0];$h=$a['hour'];$rows='';
    foreach($top as $i=>$x){$hh=$x['hour'];$rows.='<tr><td style="padding:9px;border-bottom:1px solid #e6e9ea">#'.($i+1).' <b>'.htmlspecialchars((string)$x['spot']['name']).'</b></td><td style="padding:9px;border-bottom:1px solid #e6e9ea">'.(int)$x['score'].'%</td><td style="padding:9px;border-bottom:1px solid #e6e9ea">'.number_format((float)($hh['wave_height']??0),1).' m · '.round((float)($hh['wave_period']??0)).' s · '.htmlspecialchars((string)($hh['wind_compass']??'—')).' '.round((float)($hh['wind_speed']??0)).' km/h</td></tr>';}
    return '<div style="font-family:Arial,sans-serif;max-width:660px;margin:auto;color:#172126"><div style="font-size:12px;font-weight:bold;color:#28798a">COSTACAMS · SAMPLE SURF BRIEF</div><h1>'.htmlspecialchars((string)$a['spot']['name']).' · '.(int)$a['score'].'%</h1><p>This is a one-time example using your current Surf Match profile. You are not subscribed by receiving this sample.</p><p>🌊 '.number_format((float)($h['wave_height']??0),1).' m · '.round((float)($h['wave_period']??0)).' s &nbsp; ⚡ '.number_format((float)($h['energy_kwm']??0),1).' kW/m &nbsp; 💨 '.htmlspecialchars((string)($h['wind_compass']??'—')).' '.round((float)($h['wind_speed']??0)).' km/h</p><table style="border-collapse:collapse;width:100%;margin:16px 0">'.$rows.'</table><p><a style="display:inline-block;background:#0d6578;color:#fff;text-decoration:none;padding:11px 16px;border-radius:8px" href="https://costacams.com/ccdeploy-v3/costacams-v3/surf-match-beta-v6.php">Open Surf Match Beta</a></p><p style="font-size:11px;color:#778">Forecast/model guidance only. Check local conditions, lifeguards and your own ability before entering the water.</p></div>';
}
function ccv6_manage_mail(PDO $pdo,array $r,string $reason='manage_link_v6'): array {
    $base='https://costacams.com/ccdeploy-v3/costacams-v3/surf-match-beta-v6.php';$email=(string)$r['email'];
    $manage=$base.'?manage='.rawurlencode(ccv6_token((int)$r['id'],$email,'manage'));$unsub=$base.'?unsubscribe='.rawurlencode(ccv6_token((int)$r['id'],$email,'unsubscribe'));
    $html='<div style="font-family:Arial,sans-serif;max-width:620px;margin:auto;color:#172024"><h2>CostaCams Surf Profile</h2><p>Use your private link to edit your saved Surf Match profile.</p><p><a style="display:inline-block;padding:12px 18px;background:#0d6578;color:white;text-decoration:none;border-radius:8px" href="'.htmlspecialchars($manage).'">Edit my Surf Profile</a></p><p style="font-size:12px;color:#667">No password is required. Keep this link private.</p><p style="font-size:11px"><a href="'.htmlspecialchars($unsub).'">Unsubscribe</a></p></div>';
    return ccv6_php_mail($pdo,$email,$reason,'Your CostaCams Surf Profile link',$html);
}

if($action==='sample'){
    if(!empty($_POST['website'])){echo json_encode(['ok'=>true]);exit;}
    $email=strtolower(trim((string)($_POST['email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Enter a valid email first.']);exit;}
    $eh=hash('sha256',$email.'|'.ccv6_secret());$ih=hash('sha256',ccv6_client_ip().'|'.ccv6_secret());$today=date('Y-m-d');
    $st=$pdo->prepare('SELECT sample_count FROM surf_beta_sample_log_v6 WHERE email_hash=? AND sample_date=?');$st->execute([$eh,$today]);if((int)($st->fetchColumn()?:0)>=1){http_response_code(429);echo json_encode(['ok'=>false,'message'=>'A PHP-mail sample has already been requested for this email today. Please check inbox/spam.']);exit;}
    $ipst=$pdo->prepare('SELECT COALESCE(SUM(sample_count),0) FROM surf_beta_sample_log_v6 WHERE ip_hash=? AND sample_date=?');$ipst->execute([$ih,$today]);if((int)$ipst->fetchColumn()>=3){http_response_code(429);echo json_encode(['ok'=>false,'message'=>'Sample limit reached for this connection today.']);exit;}
    $p=json_decode((string)($_POST['profile']??'{}'),true);if(!is_array($p))$p=[];$p=ccv6_profile($p);$rank=cc_surf_rank(cc_surf_load_spots($pdo),$p,cc_surf_selected_epoch($p));if(!$rank){http_response_code(503);echo json_encode(['ok'=>false,'message'=>'Forecast unavailable.']);exit;}
    $mail=ccv6_php_mail($pdo,$email,'sample_v6','Your CostaCams Surf Brief sample',ccv6_sample_html($rank));
    if(!$mail['accepted']){http_response_code(503);echo json_encode(['ok'=>false,'message'=>'PHP mail() could not accept the message.']);exit;}
    $pdo->prepare('INSERT INTO surf_beta_sample_log_v6(email_hash,ip_hash,sample_date,sample_count,last_attempt_at) VALUES(?,?,?,1,NOW()) ON DUPLICATE KEY UPDATE sample_count=sample_count+1,last_attempt_at=NOW(),ip_hash=VALUES(ip_hash)')->execute([$eh,$ih,$today]);
    echo json_encode(['ok'=>true,'message'=>'Sample accepted by the website PHP mailer. Check inbox and spam.','transport'=>'php_mail']);exit;
}

if($action==='manage_request'){
    $email=strtolower(trim((string)($_POST['email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Enter a valid email.']);exit;}
    $st=$pdo->prepare('SELECT * FROM surf_beta_subscribers WHERE email=? LIMIT 1');$st->execute([$email]);$r=$st->fetch();
    if($r){$recent=!empty($r['last_email_at'])&&strtotime((string)$r['last_email_at'])>time()-300;if(!$recent){$m=ccv6_manage_mail($pdo,$r);if($m['accepted'])$pdo->prepare('UPDATE surf_beta_subscribers SET last_email_at=NOW() WHERE id=?')->execute([(int)$r['id']]);}}
    echo json_encode(['ok'=>true,'message'=>'If that email has a saved profile, the website PHP mailer has requested a private edit link. Check inbox/spam.']);exit;
}

if($action==='subscribe'){
    if(!empty($_POST['website'])){echo json_encode(['ok'=>true]);exit;}
    $email=strtolower(trim((string)($_POST['email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Please enter a valid email.']);exit;}
    $p=json_decode((string)($_POST['profile']??'{}'),true);if(!is_array($p))$p=[];$p=ccv6_profile($p);$freq=in_array((string)($_POST['frequency']??'morning'),['morning','evening','both'],true)?(string)$_POST['frequency']:'morning';
    $manageToken=(string)($_POST['manage_token']??'');$managed=$manageToken!==''?ccv6_valid_manage($pdo,$manageToken):null;
    $st=$pdo->prepare('SELECT * FROM surf_beta_subscribers WHERE email=? LIMIT 1');$st->execute([$email]);$old=$st->fetch();
    if($old&&(bool)$old['confirmed']&&(!$managed||(int)$managed['id']!==(int)$old['id'])){$recent=!empty($old['last_email_at'])&&strtotime((string)$old['last_email_at'])>time()-300;if(!$recent){$m=ccv6_manage_mail($pdo,$old);if($m['accepted'])$pdo->prepare('UPDATE surf_beta_subscribers SET last_email_at=NOW() WHERE id=?')->execute([(int)$old['id']]);}echo json_encode(['ok'=>true,'message'=>'That email already has a confirmed profile. A private edit link was requested using PHP mail().']);exit;}
    $olat=is_numeric($p['origin_lat']??null)?round((float)$p['origin_lat'],5):null;$olon=is_numeric($p['origin_lon']??null)?round((float)$p['origin_lon'],5):null;$pc=preg_replace('/[^0-9]/','',(string)($p['origin_postcode']??''));$src=(string)($p['location_source']??($pc!==''?'postcode':($olat!==null?'browser':'')));$json=json_encode($p,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if($old){$id=(int)$old['id'];$q=$pdo->prepare('UPDATE surf_beta_subscribers SET profile_json=?,email_frequency=?,origin_lat=?,origin_lon=?,origin_postcode=?,location_source=?,schedule_mode=?,schedule_hour=?,session_minutes=?,buffer_minutes=?,active=1 WHERE id=?');$q->execute([$json,$freq,$olat,$olon,$pc?:null,$src?:null,(string)($p['time_mode']??'finish_work'),(float)($p['session_hour']??17),(int)$p['session_minutes'],(int)$p['buffer_minutes'],$id]);$confirmed=(bool)$old['confirmed'];}
    else{$q=$pdo->prepare('INSERT INTO surf_beta_subscribers(email,profile_json,email_frequency,origin_lat,origin_lon,origin_postcode,location_source,schedule_mode,schedule_hour,session_minutes,buffer_minutes) VALUES(?,?,?,?,?,?,?,?,?,?,?)');$q->execute([$email,$json,$freq,$olat,$olon,$pc?:null,$src?:null,(string)($p['time_mode']??'finish_work'),(float)($p['session_hour']??17),(int)$p['session_minutes'],(int)$p['buffer_minutes']]);$id=(int)$pdo->lastInsertId();$confirmed=false;}
    if($confirmed){echo json_encode(['ok'=>true,'confirmed'=>true,'message'=>'Profile changes saved.']);exit;}
    $base='https://costacams.com/ccdeploy-v3/costacams-v3/surf-match-beta-v6.php';$confirm=$base.'?confirm='.rawurlencode(ccv6_token($id,$email,'confirm'));$manage=$base.'?manage='.rawurlencode(ccv6_token($id,$email,'manage'));$unsub=$base.'?unsubscribe='.rawurlencode(ccv6_token($id,$email,'unsubscribe'));
    $html='<div style="font-family:Arial,sans-serif;max-width:620px;margin:auto;color:#172024"><h2>Confirm CostaCams Surf Brief</h2><p>Confirm the email address used for your Surf Match profile.</p><p><a style="display:inline-block;padding:12px 18px;background:#0d6578;color:white;text-decoration:none;border-radius:8px" href="'.htmlspecialchars($confirm).'">Confirm Surf Brief</a></p><p><a href="'.htmlspecialchars($manage).'">Edit my Surf Profile</a></p><p style="font-size:11px"><a href="'.htmlspecialchars($unsub).'">Unsubscribe</a></p></div>';
    $mail=ccv6_php_mail($pdo,$email,'confirmation_v6','Confirm your CostaCams Surf Brief',$html);if($mail['accepted'])$pdo->prepare('UPDATE surf_beta_subscribers SET last_email_at=NOW() WHERE id=?')->execute([$id]);
    echo json_encode(['ok'=>true,'confirmed'=>false,'mail_accepted'=>$mail['accepted'],'message'=>$mail['accepted']?'Profile saved. Confirmation was accepted by the website PHP mailer; check inbox/spam.':'Profile saved, but PHP mail() rejected the confirmation message.','transport'=>'php_mail']);exit;
}

http_response_code(400);echo json_encode(['ok'=>false,'message'=>'Unknown action.']);
