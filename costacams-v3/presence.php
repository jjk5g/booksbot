<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function cc_presence_db(): ?PDO {
    $cfg=cc_config();
    $name=(string)($cfg['db_name']??'');
    if(str_contains($name,';')) $name=explode(';',$name,2)[0];
    $user=(string)($cfg['db_user']??'');
    $pass=(string)($cfg['db_pass']??'');
    if($name===''||$user===''||$pass==='') return null;
    try{
        return new PDO(
            'mysql:host=localhost;dbname='.$name.';charset=utf8mb4',
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES=>false,
            ]
        );
    }catch(Throwable $e){
        error_log('CostaCams presence DB: '.$e->getMessage());
        return null;
    }
}

function cc_presence_setup(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS active_presence (
      visitor_hash CHAR(64) NOT NULL,
      tab_hash CHAR(64) NOT NULL,
      channel_slug VARCHAR(100) NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY(visitor_hash,tab_hash),
      INDEX idx_presence_updated(updated_at),
      INDEX idx_presence_channel(channel_slug,updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS presence_peaks (
      scope_type ENUM('site','channel') NOT NULL,
      scope_key VARCHAR(100) NOT NULL,
      peak_date DATE NOT NULL,
      peak_count INT UNSIGNED NOT NULL DEFAULT 0,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY(scope_type,scope_key,peak_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function cc_presence_counts(PDO $pdo): array {
    $cutoff="DATE_SUB(NOW(),INTERVAL 90 SECOND)";
    $site=(int)$pdo->query("SELECT COUNT(DISTINCT visitor_hash) FROM active_presence WHERE updated_at >= $cutoff")->fetchColumn();
    $channels=[];
    $q=$pdo->query("SELECT channel_slug,COUNT(DISTINCT visitor_hash) viewers FROM active_presence WHERE updated_at >= $cutoff AND channel_slug IS NOT NULL AND channel_slug<>'' GROUP BY channel_slug");
    foreach($q->fetchAll() as $r){$channels[(string)$r['channel_slug']]=(int)$r['viewers'];}
    return [$site,$channels];
}

function cc_presence_peaks(PDO $pdo): array {
    $sitePeak=0;$channelPeaks=[];
    $st=$pdo->prepare("SELECT scope_type,scope_key,peak_count FROM presence_peaks WHERE peak_date=CURDATE()");
    $st->execute();
    foreach($st->fetchAll() as $r){
        if($r['scope_type']==='site')$sitePeak=max($sitePeak,(int)$r['peak_count']);
        else $channelPeaks[(string)$r['scope_key']]=(int)$r['peak_count'];
    }
    return [$sitePeak,$channelPeaks];
}

$pdo=cc_presence_db();
if(!$pdo){http_response_code(503);echo json_encode(['ok'=>false,'reason'=>'database_unavailable']);exit;}
cc_presence_setup($pdo);
$pdo->exec("DELETE FROM active_presence WHERE updated_at < DATE_SUB(NOW(),INTERVAL 10 MINUTE)");

$action=(string)($_GET['action']??$_POST['action']??'stats');
if($action==='heartbeat'){
    $visitor=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($_POST['visitor']??''));
    $tab=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($_POST['tab']??''));
    $channel=preg_replace('/[^a-z0-9-]/','',strtolower((string)($_POST['channel']??'')));
    if(strlen($visitor)<12||strlen($tab)<8){http_response_code(400);echo json_encode(['ok'=>false]);exit;}
    if($channel!==''){
        $chk=$pdo->prepare("SELECT COUNT(*) FROM locations WHERE slug=? AND active=1");
        $chk->execute([$channel]);
        if(!(int)$chk->fetchColumn())$channel='';
    }
    $vh=hash('sha256',$visitor);$th=hash('sha256',$tab);
    $st=$pdo->prepare("INSERT INTO active_presence(visitor_hash,tab_hash,channel_slug,updated_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE channel_slug=VALUES(channel_slug),updated_at=NOW()");
    $st->execute([$vh,$th,$channel!==''?$channel:null]);
}

[$site,$channels]=cc_presence_counts($pdo);
$up=$pdo->prepare("INSERT INTO presence_peaks(scope_type,scope_key,peak_date,peak_count,updated_at) VALUES(?,?,CURDATE(),?,NOW()) ON DUPLICATE KEY UPDATE peak_count=GREATEST(peak_count,VALUES(peak_count)),updated_at=NOW()");
$up->execute(['site','all',$site]);
foreach($channels as $slug=>$count){$up->execute(['channel',$slug,$count]);}
[$sitePeak,$channelPeaks]=cc_presence_peaks($pdo);

echo json_encode([
  'ok'=>true,
  'site'=>['now'=>$site,'peak_today'=>$sitePeak],
  'channels'=>$channels,
  'channel_peaks'=>$channelPeaks,
  'window_seconds'=>90
],JSON_UNESCAPED_SLASHES);
