<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('{"ok":false}');}
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if($origin!==''){
    $host=strtolower((string)parse_url($origin,PHP_URL_HOST));
    if(!in_array($host,['costacams.com','www.costacams.com','beta.costacams.com'],true)){http_response_code(403);exit('{"ok":false}');}
}
$kind=(string)($_POST['kind']??'');
$key=(string)($_POST['key']??'');
$visitor=(string)($_POST['visitor']??'');
if(!in_array($kind,['page','action'],true)
    ||!preg_match('/^[a-zA-Z0-9:_-]{2,110}$/',$key)
    ||!preg_match('/^[a-zA-Z0-9-]{16,80}$/',$visitor)){
    http_response_code(422);exit('{"ok":false}');
}
if($kind==='page'&&!preg_match('/^(?:home|map|surf-match|other|event:[a-z0-9-]{1,90}|beach:[a-z0-9-]{1,90})$/',$key)){
    http_response_code(422);exit('{"ok":false}');
}
if($kind==='action'&&!preg_match('/^(?:surf_match_open|event_article_open|map_open|beach_camera_open|surf_sample_request|surf_subscribe_request|surf_profile_link_request|surf_drive_limit_change)_[a-zA-Z0-9_-]{2,70}$/',$key)){
    http_response_code(422);exit('{"ok":false}');
}
try {
    require __DIR__.'/bootstrap.php';
    $pdo=cc_db();
    if(!$pdo)throw new RuntimeException('Database unavailable');
    $pdo->beginTransaction();
    $stmt=$pdo->prepare("INSERT INTO cc_analytics_daily (day,kind,metric_key,events)
        VALUES(CURDATE(),?,?,1) ON DUPLICATE KEY UPDATE events=events+1");
    $stmt->execute([$kind,$key]);
    if($kind==='page'){
        // Anonymous random browser identifier is hashed on the server; no IP, email or postcode stored.
        $hash=hash('sha256',$visitor.'|costacams-analytics-v1');
        $u=$pdo->prepare("INSERT IGNORE INTO cc_analytics_visitors(day,visitor_hash)
            VALUES(CURDATE(),?)");
        $u->execute([$hash]);
    }
    $pdo->commit();
    http_response_code(204);
}catch(Throwable $e){
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    error_log('CostaCams analytics collector: '.get_class($e));
    http_response_code(503);
    echo '{"ok":false}';
}
