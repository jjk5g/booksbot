<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/bootstrap.php';
$pdo=cc_db();
if(!$pdo){fwrite(STDERR,"DB unavailable\n");exit(1);}
$pdo->exec("CREATE TABLE IF NOT EXISTS stream_health (
 stream_id INT UNSIGNED PRIMARY KEY,
 status ENUM('ok','down') NOT NULL DEFAULT 'down',
 http_code INT NOT NULL DEFAULT 0,
 response_ms INT NOT NULL DEFAULT 0,
 checked_at DATETIME NOT NULL,
 last_ok_at DATETIME NULL,
 consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0,
 CONSTRAINT fk_health_stream FOREIGN KEY(stream_id) REFERENCES streams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$rows=$pdo->query("SELECT id,stream_type,stream_url FROM streams WHERE active=1 AND stream_type IN ('hls','iframe') AND stream_url IS NOT NULL AND stream_url<>''")->fetchAll();
$mh=curl_multi_init();$handles=[];$started=[];
foreach($rows as $r){
 $ch=curl_init($r['stream_url']);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>8,CURLOPT_USERAGENT=>'CostaCamsHealth/1.0',CURLOPT_SSL_VERIFYPEER=>true]);
 curl_multi_add_handle($mh,$ch);$handles[(int)$r['id']]=[$ch,$r];$started[(int)$r['id']]=microtime(true);
}
do{$mrc=curl_multi_exec($mh,$running);if($running)curl_multi_select($mh,1.0);}while($running&&$mrc===CURLM_OK);
$up=$pdo->prepare("INSERT INTO stream_health(stream_id,status,http_code,response_ms,checked_at,last_ok_at,consecutive_failures) VALUES(?,?,?,?,NOW(),?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),http_code=VALUES(http_code),response_ms=VALUES(response_ms),checked_at=NOW(),last_ok_at=IF(VALUES(status)='ok',NOW(),last_ok_at),consecutive_failures=IF(VALUES(status)='ok',0,consecutive_failures+1)");
$okCount=0;$downCount=0;
foreach($handles as $id=>[$ch,$r]){
 $body=curl_multi_getcontent($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$ms=(int)round((microtime(true)-$started[$id])*1000);
 $ok=$code>=200&&$code<400;
 if($ok&&$r['stream_type']==='hls')$ok=is_string($body)&&str_contains($body,'#EXTM3U');
 $status=$ok?'ok':'down';$ok?$okCount++:$downCount++;
 $up->execute([$id,$status,$code,$ms,$ok?date('Y-m-d H:i:s'):null,$ok?0:1]);
 curl_multi_remove_handle($mh,$ch);curl_close($ch);
}
curl_multi_close($mh);
echo "checked=".count($rows)." ok=$okCount down=$downCount\n";
