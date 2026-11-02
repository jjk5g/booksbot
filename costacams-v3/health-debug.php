<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/bootstrap.php';
$pdo=cc_db();if(!$pdo)exit(2);
$sql="SELECT l.slug,s.name,sh.status,sh.http_code,sh.checked_at,sh.last_ok_at,sh.consecutive_failures FROM stream_health sh JOIN streams s ON s.id=sh.stream_id JOIN locations l ON l.id=s.location_id ORDER BY sh.status DESC,l.sort_order,s.priority";
foreach($pdo->query($sql)->fetchAll() as $r){echo implode('|',[$r['slug'],$r['name'],$r['status'],$r['http_code'],$r['checked_at'],$r['last_ok_at'],$r['consecutive_failures']])."\n";}
