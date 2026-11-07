<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

$slug=preg_replace('/[^a-z0-9-]/','',strtolower((string)($_GET['slug']??'')))??'';
$event=null;
try {
    if($slug!=='' && strlen($slug)<=150 && ($pdo=cc_db())) {
        try {
            $st=$pdo->prepare("SELECT title,summary,body,event_date,event_end_date,event_type,region,locality,source_name,source_url
                FROM news_items WHERE slug=? AND category='event' AND active=1 LIMIT 1");
            $st->execute([$slug]);$event=$st->fetch(PDO::FETCH_ASSOC)?:null;
        } catch(Throwable $e) {
            $st=$pdo->prepare("SELECT title,summary,body,event_date,NULL AS event_end_date,
                NULL AS event_type,NULL AS region,NULL AS locality,source_name,source_url
                FROM news_items WHERE slug=? AND category='event' AND active=1 LIMIT 1");
            $st->execute([$slug]);$event=$st->fetch(PDO::FETCH_ASSOC)?:null;
        }
    }
} catch(Throwable $e) {
    error_log('CostaCams event page: '.get_class($e));
}
if(!$event)http_response_code(404);
$esc=static fn($s):string=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$title=$event?(string)$event['title']:'Event not found';
$summary=$event?(string)($event['summary']??''):'This event is not available.';
$body=$event?(string)($event['body']??''):'';
$start=$event?(string)($event['event_date']??''):'';
$end=$event?(string)($event['event_end_date']??''):'';
$displayDate=static function(string $s):string {
    if(!$s)return '';
    $timestamp=strtotime($s);
    return $timestamp?date('l j F Y',$timestamp).(substr($s,11,5)!=='00:00'?' · '.date('g:i A',$timestamp):''):'';
};
$place=trim(implode(' · ',array_filter([(string)($event['locality']??''),(string)($event['region']??'')])));
$src=trim((string)($event['source_url']??''));
$validSource=(bool)filter_var($src,FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($src,PHP_URL_SCHEME)),['https','http'],true);
$meta=mb_substr($summary!==''?$summary:($title.' · CostaCams coastal event'),0,160);
$canonical='https://costacams.com/event.php?slug='.rawurlencode($slug);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=$esc($title)?> | CostaCams Events</title>
<meta name="description" content="<?=$esc($meta)?>">
<meta name="robots" content="<?=$event?'index,follow':'noindex'?>">
<link rel="canonical" href="<?=$esc($canonical)?>">
<meta property="og:title" content="<?=$esc($title)?> | CostaCams">
<meta property="og:description" content="<?=$esc($meta)?>">
<meta property="og:type" content="article">
<meta property="og:url" content="<?=$esc($canonical)?>">
<style>
:root{color-scheme:dark;--bg:#050708;--panel:#0d1518;--line:#283c43;--aqua:#50dcff;--text:#eaf3f5;--muted:#a0b0b6}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:radial-gradient(circle at 50% -15%,#15313a,#050708 55%);color:var(--text);font:16px/1.65 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
a{color:#76e6ff}.top{border-bottom:1px solid #263138;background:#080e11ee;padding:14px max(18px,4vw);display:flex;align-items:center;justify-content:space-between;gap:12px}.brand{color:#eff9fc;text-decoration:none;font-weight:900;letter-spacing:.09em}.brand b{color:var(--aqua)}.top nav{display:flex;gap:18px}.top nav a{text-decoration:none;font-size:13px;color:#b3c2c6}
main{max-width:880px;margin:0 auto;padding:32px 18px 70px}
.back{display:inline-block;font-size:13px;text-decoration:none;margin-bottom:28px;color:#a7dce7}
article{border:1px solid var(--line);background:linear-gradient(150deg,#111e23,#0a0f11 75%);padding:clamp(20px,5vw,43px);border-radius:22px}
.tag{text-transform:uppercase;color:var(--aqua);font-weight:800;letter-spacing:.12em;font-size:11px}
h1{font-size:clamp(30px,6vw,54px);line-height:1.08;letter-spacing:-.04em;margin:12px 0 17px}
.summary{font-size:19px;color:#bfd1d7;line-height:1.55;margin-bottom:24px}
.facts{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 27px}.fact{border:1px solid #31505a;border-radius:999px;padding:8px 12px;font-size:12px;color:#c1dbe3;background:#0c1a20}
.body{padding-top:12px;border-top:1px solid #24353b;color:#d2dce0;line-height:1.85;overflow-wrap:anywhere}
.source{margin-top:26px;padding-top:19px;border-top:1px solid #27383e;font-size:13px;color:#8fa5ac}
.source a{overflow-wrap:anywhere}.endnav{display:flex;gap:10px;flex-wrap:wrap;margin-top:28px}.endnav a{border:1px solid #325966;padding:11px 14px;border-radius:11px;text-decoration:none;font-weight:750;font-size:13px}
@media(max-width:480px){.top nav a{font-size:12px}.facts{display:grid}.fact{border-radius:10px}}
</style></head>
<body><header class="top"><a class="brand" href="/"><b>COSTA</b>CAMS</a><nav><a href="/#calendar">Calendar</a><a href="/#channels">Cameras</a></nav></header>
<main><a class="back" href="/#calendar">← Back to events calendar</a>
<article><span class="tag">COAST EVENTS<?=$event&&!empty($event['event_type'])?' · '.$esc(str_replace('_',' ',(string)$event['event_type'])):''?></span>
<h1><?=$esc($title)?></h1>
<?php if($event): ?>
<?php if($summary!==''):?><p class="summary"><?=$esc($summary)?></p><?php endif;?>
<div class="facts">
<?php if($start!==''):?><span class="fact">📅 <?=$esc($displayDate($start))?><?php if($end!=='' && substr($end,0,10)!==substr($start,0,10)):?> — <?=$esc($displayDate($end))?><?php endif;?></span><?php endif;?>
<?php if($place!==''):?><span class="fact">📍 <?=$esc($place)?></span><?php endif;?>
</div>
<div class="body"><?=nl2br($esc($body?:$summary))?></div>
<div class="source">Published by CostaCams from <?=$esc((string)($event['source_name']?:'public event information'))?>.<?php if($validSource):?> <a href="<?=$esc($src)?>" target="_blank" rel="noopener noreferrer">Verify event details with the organiser ↗</a><?php endif;?></div>
<?php else:?>
<p class="summary">This listing is unavailable or no longer published.</p>
<?php endif;?>
<div class="endnav"><a href="/#calendar">📅 Browse more events</a><a href="/">Watch live beach cameras</a></div>
</article>
</main></body></html>
