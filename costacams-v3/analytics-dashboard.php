<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
if(empty($_SESSION['cc_admin'])){
    http_response_code(302);header('Location: /admin.php');exit;
}
require __DIR__.'/bootstrap.php';
$e=static fn($s):string=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$pdo=cc_db();
$error='';
$now=0;$peak=0;$views7=0;$views30=0;$uniq7=0;$uniq30=0;$legacyViews=0;
$days=[];$pages=[];$actions=[];$topCameras=[];
try{
 if(!$pdo)throw new RuntimeException('Database unavailable');
 $q=static function(PDO $db,string $sql,array $params=[]):array{$st=$db->prepare($sql);$st->execute($params);return $st->fetchAll(PDO::FETCH_ASSOC);};
 $sum=static function(PDO $db,string $sql,array $params=[])use($q):int{$rows=$q($db,$sql,$params);return (int)($rows[0]['total']??0);};
 $now=$sum($pdo,"SELECT COUNT(DISTINCT visitor_hash) total FROM active_presence WHERE updated_at>=DATE_SUB(NOW(),INTERVAL 90 SECOND)");
 $peak=$sum($pdo,"SELECT COALESCE(MAX(peak_count),0) total FROM presence_peaks WHERE scope_type='site' AND peak_date=CURDATE()");
 $views7=$sum($pdo,"SELECT COALESCE(SUM(events),0) total FROM cc_analytics_daily WHERE kind='page' AND day>=DATE_SUB(CURDATE(),INTERVAL 6 DAY)");
 $views30=$sum($pdo,"SELECT COALESCE(SUM(events),0) total FROM cc_analytics_daily WHERE kind='page' AND day>=DATE_SUB(CURDATE(),INTERVAL 29 DAY)");
 $uniq7=$sum($pdo,"SELECT COUNT(DISTINCT visitor_hash) total FROM cc_analytics_visitors WHERE day>=DATE_SUB(CURDATE(),INTERVAL 6 DAY)");
 $uniq30=$sum($pdo,"SELECT COUNT(DISTINCT visitor_hash) total FROM cc_analytics_visitors WHERE day>=DATE_SUB(CURDATE(),INTERVAL 29 DAY)");
 $legacyViews=$sum($pdo,"SELECT COALESCE(SUM(views),0) total FROM daily_views WHERE view_date>=DATE_SUB(CURDATE(),INTERVAL 6 DAY)");
 $days=$q($pdo,"SELECT day,SUM(events) total FROM cc_analytics_daily WHERE kind='page' AND day>=DATE_SUB(CURDATE(),INTERVAL 13 DAY) GROUP BY day ORDER BY day");
 $pages=$q($pdo,"SELECT metric_key,SUM(events) total FROM cc_analytics_daily WHERE kind='page' AND day>=DATE_SUB(CURDATE(),INTERVAL 29 DAY) GROUP BY metric_key ORDER BY total DESC LIMIT 15");
 $actions=$q($pdo,"SELECT metric_key,SUM(events) total FROM cc_analytics_daily WHERE kind='action' AND day>=DATE_SUB(CURDATE(),INTERVAL 29 DAY) GROUP BY metric_key ORDER BY total DESC LIMIT 15");
 $topCameras=$q($pdo,"SELECT l.name,COALESCE(SUM(d.views),0) total FROM daily_views d JOIN locations l ON l.id=d.location_id WHERE d.view_date>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY l.id,l.name ORDER BY total DESC LIMIT 10");
}catch(Throwable $ex){$error='Some statistics are not available yet. Tracking starts after the collection tables are provisioned.';error_log('CostaCams dashboard: '.get_class($ex));}
$chart=[];$lookup=[];
foreach($days as $d)$lookup[substr((string)$d['day'],0,10)]=(int)$d['total'];
for($i=13;$i>=0;$i--){$day=date('Y-m-d',strtotime("-$i days"));$chart[]=[$day,$lookup[$day]??0];}
$max=max(1,...array_column($chart,1));
$label=static function(string $key):string{return ucwords(str_replace(['event:','beach:','_','-'],['Event: ','Camera: ',' ',' '],$key));};
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>CostaCams · Private Analytics</title><meta name="robots" content="noindex,nofollow">
<style>
:root{color-scheme:dark;--a:#50dcff;--bg:#05090b;--card:#0f191e;--edge:#27434c;--txt:#e4f0f3;--muted:#9fb0b7}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 75% 0,#102a33,var(--bg) 45%);color:var(--txt);font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
a{color:var(--a)}.wrap{width:min(1200px,100%);margin:auto;padding:20px max(14px,3vw) 80px}
.top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:10px 0 24px}
.top h1{font-size:clamp(25px,4vw,35px);line-height:1.1;letter-spacing:-.04em;margin:6px 0}
.tag{font-size:11px;color:var(--a);font-weight:850;letter-spacing:.12em}
nav{display:flex;gap:9px;flex-wrap:wrap}nav a,.link{display:inline-flex;padding:10px 13px;border-radius:10px;border:1px solid #36515a;text-decoration:none;font-weight:700}
.cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:16px 0}
.card,.panel{border:1px solid var(--edge);background:linear-gradient(155deg,#10232b,#0a1115);border-radius:17px;padding:19px;min-width:0}
.card small{display:block;color:var(--muted);font-size:11px}.card strong{display:block;font-size:clamp(28px,4vw,42px);line-height:1.3;margin:7px 0;color:#eefcff;letter-spacing:-.035em}
.card .note{font-size:11px;color:#879da5}
.panel{margin:14px 0}.panel h2{margin:0 0 15px;font-size:19px}.two{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.chart{display:flex;gap:7px;height:175px;border-bottom:1px solid #2a4d55;align-items:end;padding-top:8px;overflow:hidden}
.bar{flex:1 1 0;min-width:0;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;height:100%;gap:4px}
.bar span{display:block;background:linear-gradient(0deg,#12677b,#50dcff);border-radius:5px 5px 0 0;min-height:2px;width:100%;max-width:43px}
.bar b{font-weight:550;font-size:9px;color:#91c1cb}
.chartlabels{display:flex;justify-content:space-between;font-size:10px;color:#88a0a7;margin-top:7px}
table{width:100%;border-collapse:collapse;font-size:12px}th,td{padding:9px 7px;border-bottom:1px solid #25404a;text-align:left;overflow-wrap:anywhere}
th{color:#92b0b9;font-weight:700}td:last-child,th:last-child{text-align:right;font-variant-numeric:tabular-nums}
.empty{color:#9eafb5;padding:12px 0}.hint{font-size:12px;color:#91a5ac}.warn{border:1px solid #73512f;background:#24190c;border-radius:11px;padding:13px;color:#ffe0ab}
@media(max-width:830px){.cards{grid-template-columns:repeat(2,minmax(0,1fr))}.two{grid-template-columns:1fr}}
@media(max-width:490px){.cards{grid-template-columns:1fr 1fr;gap:8px}.card{padding:13px}.card strong{font-size:26px}.panel{padding:14px}}
</style></head><body><main class="wrap">
<header class="top"><div><span class="tag">COSTACAMS · PRIVATE</span><h1>Analytics dashboard</h1><span class="hint">First-party statistics · Australia/Brisbane server time</span></div><nav><a href="/admin.php">← Admin</a><a href="https://analytics.google.com/" target="_blank" rel="noopener noreferrer">Google Analytics ↗</a><a href="/admin.php?logout=1">Sign out</a></nav></header>
<?php if($error):?><p class="warn"><?=$e($error)?></p><?php endif;?>
<div class="cards">
<div class="card"><small>Watching right now · last 90 sec</small><strong><?=number_format($now)?></strong><span class="note">Distinct anonymous active browsers</span></div>
<div class="card"><small>Peak simultaneous today</small><strong><?=number_format($peak)?></strong><span class="note">Largest observed live count</span></div>
<div class="card"><small>Page views · last 7 days</small><strong><?=number_format($views7)?></strong><span class="note">Collected from cached and dynamic pages</span></div>
<div class="card"><small>Approx. unique browsers · 7 days</small><strong><?=number_format($uniq7)?></strong><span class="note">Browser IDs, not verified people</span></div>
<div class="card"><small>Page views · last 30 days</small><strong><?=number_format($views30)?></strong><span class="note">New counter, starts at activation</span></div>
<div class="card"><small>Approx. unique browsers · 30 days</small><strong><?=number_format($uniq30)?></strong><span class="note">No IP or email stored</span></div>
</div>
<section class="panel"><h2>Traffic · last 14 days</h2><div class="chart" role="img" aria-label="Page views per day over the last 14 days">
<?php foreach($chart as [$day,$count]):?><div class="bar" title="<?=$e($day)?>: <?=number_format($count)?> views"><b><?=$count?:''?></b><span style="height:<?=max(2,(int)round($count/$max*138))?>px"></span></div><?php endforeach;?>
</div><div class="chartlabels"><span><?=date('j M',strtotime($chart[0][0]))?></span><span>Today · <?=date('j M')?></span></div></section>
<div class="two">
<section class="panel"><h2>Most-viewed pages · 30 days</h2>
<?php if(!$pages):?><div class="empty">Collection has just started.</div><?php else:?><table><tr><th>Page</th><th>Views</th></tr><?php foreach($pages as $p):?><tr><td><?=$e($label((string)$p['metric_key']))?></td><td><?=number_format((int)$p['total'])?></td></tr><?php endforeach;?></table><?php endif;?>
</section>
<section class="panel"><h2>Important actions · 30 days</h2>
<?php if(!$actions):?><div class="empty">Clicks will appear after the first visitors interact.</div><?php else:?><table><tr><th>Action</th><th>Count</th></tr><?php foreach($actions as $a):?><tr><td><?=$e($label((string)$a['metric_key']))?></td><td><?=number_format((int)$a['total'])?></td></tr><?php endforeach;?></table><?php endif;?>
</section>
</div>
<section class="panel"><h2>Legacy camera counter · 7 days</h2><p class="hint">Existing PHP-session camera counts: <?=number_format($legacyViews)?>. This counter can undercount statically cached visits; use the new “Most-viewed pages” table for newer traffic.</p>
<?php if($topCameras):?><table><tr><th>Camera</th><th>Legacy views</th></tr><?php foreach($topCameras as $c):?><tr><td><?=$e($c['name'])?></td><td><?=number_format((int)$c['total'])?></td></tr><?php endforeach;?></table><?php endif;?></section>
<section class="panel"><h2>Google Analytics 4</h2><p>Measurement ID: <code>G-6WLK1YD519</code>. Open Google Analytics for acquisition sources, devices, countries, engagement and its own user estimates. These values are <b>not automatically imported</b> into this private dashboard; the numbers above come from the first-party CostaCams database.</p><a class="link" target="_blank" rel="noopener noreferrer" href="https://analytics.google.com/">Open Google Analytics reports ↗</a><p class="hint">Tracking starts on activation; historical first-party visits cannot be reconstructed from the new tracker. Ad blockers and disabled JavaScript can reduce counts. Events record click attempts, not necessarily completed subscriptions/emails.</p></section>
</main></body></html>
