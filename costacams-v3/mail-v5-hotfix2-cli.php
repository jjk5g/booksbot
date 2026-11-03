<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$targets=[
 '/home/u751177156/domains/costacams.com/public_html/ccdeploy-v3/costacams-v3/surf-beta-v5-api.php',
 '/home/u751177156/domains/costacams.com/public_html/surf-beta-v5-api.php',
];
$oldUrl='https://costacams.com/ccdeploy-v3/costacams-v3/surf-match-beta-v5.php';
$newUrl='https://costacams.com/surf-match.php';
$manageOld=<<<'OLD'
if($action==='manage_request'){$email=strtolower(trim((string)($_POST['email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Enter a valid email.']);exit;}$st=$pdo->prepare('SELECT * FROM surf_beta_subscribers WHERE email=? LIMIT 1');$st->execute([$email]);$r=$st->fetch();if($r){$recent=!empty($r['last_email_at'])&&strtotime((string)$r['last_email_at'])>time()-900;if(!$recent){$m=ccv5_manage_mail($pdo,$r,'manage_link_v5');if($m['accepted'])$pdo->prepare('UPDATE surf_beta_subscribers SET last_email_at=NOW() WHERE id=?')->execute([(int)$r['id']]);}}echo json_encode(['ok'=>true,'message'=>'If that email has a saved profile, a private edit link has been sent. Check inbox/spam.']);exit;}
OLD;
$manageNew=<<<'NEW'
if($action==='manage_request'){$email=strtolower(trim((string)($_POST['email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Enter a valid email.']);exit;}$st=$pdo->prepare('SELECT * FROM surf_beta_subscribers WHERE email=? LIMIT 1');$st->execute([$email]);$r=$st->fetch();if($r){$m=ccv5_manage_mail($pdo,$r,'manage_link_v5');if(!$m['accepted']){http_response_code(503);echo json_encode(['ok'=>false,'message'=>'Your profile exists, but the email service did not accept the profile-link message. Please try again.']);exit;}$pdo->prepare('UPDATE surf_beta_subscribers SET last_email_at=NOW() WHERE id=?')->execute([(int)$r['id']]);}echo json_encode(['ok'=>true,'message'=>'If that email has a saved profile, a fresh private edit link has been requested. Check inbox and spam.']);exit;}
NEW;
$confirmedOld=<<<'OLD'
if($old&&(bool)$old['confirmed']&&(!$managed||(int)$managed['id']!==(int)$old['id'])){$recent=!empty($old['last_email_at'])&&strtotime((string)$old['last_email_at'])>time()-900;if(!$recent){$m=ccv5_manage_mail($pdo,$old,'manage_link_v5');if($m['accepted'])$pdo->prepare('UPDATE surf_beta_subscribers SET last_email_at=NOW() WHERE id=?')->execute([(int)$old['id']]);}echo json_encode(['ok'=>true,'message'=>'That email already has a confirmed profile. A private edit link was sent instead of changing it here.']);exit;}
OLD;
$confirmedNew=<<<'NEW'
if($old&&(bool)$old['confirmed']&&(!$managed||(int)$managed['id']!==(int)$old['id'])){$m=ccv5_manage_mail($pdo,$old,'manage_link_v5');if(!$m['accepted']){http_response_code(503);echo json_encode(['ok'=>false,'message'=>'Your saved profile was found, but the email service did not accept the profile-link message. Please try again.']);exit;}$pdo->prepare('UPDATE surf_beta_subscribers SET last_email_at=NOW() WHERE id=?')->execute([(int)$old['id']]);echo json_encode(['ok'=>true,'message'=>'That email already has a confirmed profile. A fresh private edit link was requested.']);exit;}
NEW;
$sampleOld="if((int)(\$st->fetchColumn()?:0)>=1){http_response_code(429);echo json_encode(['ok'=>false,'message'=>'A sample has already been requested for this email today. Please check inbox/spam.']);exit;}";
$sampleNew="if((int)(\$st->fetchColumn()?:0)>=3){http_response_code(429);echo json_encode(['ok'=>false,'message'=>'Three sample attempts have already been requested for this email today. Please check inbox/spam.']);exit;}";
foreach($targets as $f){
  if(!is_file($f)){fwrite(STDERR,"missing $f\n");exit(2);}
  $s=(string)file_get_contents($f);$original=$s;
  $s=str_replace($oldUrl,$newUrl,$s,$urlCount);
  $s=str_replace($manageOld,$manageNew,$s,$manageCount);
  $s=str_replace($confirmedOld,$confirmedNew,$s,$confirmedCount);
  $s=str_replace($sampleOld,$sampleNew,$s,$sampleCount);
  if($manageCount!==1||$confirmedCount!==1||$sampleCount!==1){fwrite(STDERR,"pattern mismatch $f manage=$manageCount confirmed=$confirmedCount sample=$sampleCount\n");exit(3);}
  $bak=$f.'.mail-v5-'.date('Ymd-His').'.bak';copy($f,$bak);
  if(file_put_contents($f,$s,LOCK_EX)===false){copy($bak,$f);exit(4);}
  $out=[];$rc=0;exec('php -l '.escapeshellarg($f).' 2>&1',$out,$rc);if($rc!==0){copy($bak,$f);fwrite(STDERR,"lint failed $f ".implode(' ',$out)."\n");exit(5);}
  echo "patched=$f urls=$urlCount manage=$manageCount confirmed=$confirmedCount sample=$sampleCount\n";
}
$brief='/home/u751177156/domains/costacams.com/public_html/ccdeploy-v3/costacams-v3/surf-brief-beta-send-v6.php';
if(is_file($brief)){$s=(string)file_get_contents($brief);$o=$s;$s=str_replace('https://costacams.com/ccdeploy-v3/costacams-v3/surf-match-beta-v6.php','https://costacams.com/surf-match.php',$s,$n);if($s!==$o){$bak=$brief.'.mail-v5-'.date('Ymd-His').'.bak';copy($brief,$bak);file_put_contents($brief,$s,LOCK_EX);$out=[];$rc=0;exec('php -l '.escapeshellarg($brief).' 2>&1',$out,$rc);if($rc!==0){copy($bak,$brief);exit(6);}}echo "brief_links=$n\n";}
