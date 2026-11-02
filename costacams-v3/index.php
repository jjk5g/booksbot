<?php
declare(strict_types=1);
session_start();
require __DIR__.'/bootstrap.php';

$slug=preg_replace('/[^a-z0-9-]/','',strtolower($_GET['slug']??''));
$locations=cc_locations();
$current=$slug?cc_location($slug):null;
if($slug && !$current){http_response_code(404);}
if($current) cc_record_view($current);
$bundle=$current?cc_forecast($current):['weather'=>null,'marine'=>null];
$weather=$bundle['weather']; $marine=$bundle['marine'];
$tides=$current?cc_tides($marine):[];
$moon=$current?cc_moon_info():null;
$shark=$current?cc_shark_info($current):null;
$popular=cc_popular(7,8);
$news=cc_news('news',6);
$events=cc_news('event',6);

function e($v): string {return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function n($v,$dec=0,$suffix=''): string {return $v===null||$v===''?'—':number_format((float)$v,$dec).$suffix;}
function has_live(array $loc): bool {foreach($loc['streams']??[] as $s){if(in_array($s['stream_type'],['hls','iframe'])&&!empty($s['stream_url']))return true;}return false;}

$title=$current?$current['name'].' Live Beach Cam, Surf Forecast, Weather & Tides | CostaCams':'CostaCams | Live Gold Coast, Tweed & Byron Surf Cams';
$desc=$current?'Watch '.$current['name'].' live beach camera and check surf, weather, rain, wind, tides, sunrise, sunset and moon conditions.':'Watch live beach cameras from the Gold Coast to Byron Bay with surf forecasts, weather, tides and local surf news.';
$canonical='https://costacams.com'.($current?'/'.$current['slug']:'/');
$wc=$weather['current']??[];$mc=$marine['current']??[];
$todayW=$weather['daily']??[];$todayM=$marine['daily']??[];
$condition=cc_weather_text(isset($wc['weather_code'])?(int)$wc['weather_code']:null);
$storm=in_array((int)($wc['weather_code']??-1),[95,96,99]);
$sunrise=$todayW['sunrise'][0]??null;$sunset=$todayW['sunset'][0]??null;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title)?></title>
<meta name="description" content="<?=e($desc)?>">
<link rel="canonical" href="<?=e($canonical)?>">
<meta property="og:title" content="<?=e($title)?>"><meta property="og:description" content="<?=e($desc)?>"><meta property="og:type" content="website">
<script type="application/ld+json"><?=json_encode(["@context"=>"https://schema.org","@type"=>$current?"WebPage":"WebSite","name"=>$title,"url"=>$canonical,"description"=>$desc],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?></script>
<style>
:root{--bg:#050505;--panel:#0d0f10;--panel2:#121516;--line:#252a2d;--text:#f4f7f8;--muted:#9da9ae;--aqua:#50dcff;--good:#69e0a0;--warn:#ffbf69;--bad:#ff6b6b}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}a{color:inherit;text-decoration:none}.top{position:sticky;top:0;z-index:50;display:flex;justify-content:space-between;align-items:center;padding:13px max(18px,4vw);background:#050505ed;border-bottom:1px solid #1d2224;backdrop-filter:blur(14px)}.brand{font-size:18px;font-weight:900;letter-spacing:.1em}.brand b{color:var(--aqua)}.topnav{display:flex;gap:14px;color:var(--muted);font-size:13px}.wrap{max-width:1400px;margin:auto;padding:26px max(18px,4vw)}.hero{padding:26px 0 18px}.hero .eyebrow,.tag{color:var(--aqua);font-weight:800;font-size:11px;letter-spacing:.09em;text-transform:uppercase}.hero h1{font-size:clamp(34px,6vw,68px);line-height:.98;margin:7px 0 13px;letter-spacing:-.04em}.hero p{color:var(--muted);font-size:17px;max-width:760px}.section{margin:30px 0 42px}.section-head{display:flex;justify-content:space-between;align-items:end;gap:15px;margin-bottom:14px}.section-head h2{margin:0;font-size:24px}.muted{color:var(--muted)}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(235px,1fr));gap:12px}.card{background:linear-gradient(180deg,#111516,#0b0d0e);border:1px solid var(--line);border-radius:16px;padding:17px;transition:.18s}.card:hover{border-color:#3d5960;transform:translateY(-1px)}.card h3,.card h2{margin:5px 0 6px}.live-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--good);margin-right:6px;box-shadow:0 0 8px var(--good)}.pending-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--warn);margin-right:6px}.region-title{margin:30px 0 12px;color:#d9e1e4}.player-shell{background:#000;border:1px solid #292f31;border-radius:20px;overflow:hidden}.player{aspect-ratio:16/9;position:relative;display:flex;align-items:center;justify-content:center;background:#000}.player iframe,.player video{width:100%;height:100%;border:0;background:#000}.player-msg{max-width:650px;text-align:center;padding:35px}.player-msg h2{margin-top:0}.source-note{padding:10px 14px;background:#0c0e0f;border-top:1px solid #1e2426;color:var(--muted);font-size:12px}.stream-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}.pill,.btn{border:1px solid #343b3e;background:#111416;color:#fff;border-radius:999px;padding:9px 13px;cursor:pointer}.pill.active,.btn.primary{border-color:var(--aqua);color:var(--aqua)}.btn.warn{border-color:var(--warn);color:#ffd49a}.actions{display:flex;gap:9px;flex-wrap:wrap;margin:14px 0}.conditions{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:16px}.metric{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:14px}.metric small{color:var(--muted);display:block}.metric strong{font-size:20px;display:block;margin-top:4px}.metric .sub{font-size:12px;color:var(--muted);margin-top:4px}.risk{border-color:#604227}.forecast{display:grid;grid-template-columns:repeat(7,1fr);gap:9px}.day{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:12px}.day b{font-size:13px}.day .wave{font-size:20px;font-weight:800;margin:5px 0}.day small{display:block;color:var(--muted);margin-top:2px}.tide-row{display:flex;gap:8px;flex-wrap:wrap}.tide{background:var(--panel2);border:1px solid var(--line);padding:10px 13px;border-radius:12px}.report-box{display:none;background:#101314;border:1px solid #4d4431;border-radius:16px;padding:18px;margin:12px 0}.report-box:target{display:block}.report-box input,.report-box select,.report-box textarea{width:100%;padding:11px;margin:6px 0 10px;border:1px solid #343b3e;background:#070909;color:white;border-radius:10px}.report-box textarea{min-height:90px}.story-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:12px}.story{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:0}.story summary{list-style:none;padding:17px;cursor:pointer}.story summary::-webkit-details-marker{display:none}.story summary h3{margin:5px 0}.story-body{padding:0 17px 17px;color:#cbd3d6}.story-source{font-size:12px;color:var(--muted);margin-top:12px}.notice{border-left:3px solid var(--aqua);padding:10px 14px;background:#0b1214;color:#bdcbd0;border-radius:0 10px 10px 0}.footer{border-top:1px solid #1c2224;margin-top:50px;padding:30px max(18px,4vw);color:#78868b}.footer a{color:#b9c6ca}.tv-nav{display:flex;justify-content:space-between;gap:10px;margin-top:16px}.tv-nav a{flex:1;background:#0d1011;border:1px solid var(--line);padding:12px;border-radius:12px}.tv-nav a:last-child{text-align:right}
@media(max-width:1000px){.conditions{grid-template-columns:repeat(2,1fr)}.forecast{grid-template-columns:repeat(4,1fr)}}@media(max-width:650px){.topnav{display:none}.conditions{grid-template-columns:1fr 1fr}.forecast{grid-template-columns:repeat(2,1fr)}.hero h1{font-size:42px}.wrap{padding-top:18px}}
</style>
<?php if($current):?><script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script><?php endif;?>
</head>
<body>
<header class="top"><a class="brand" href="/"><b>COSTA</b>CAMS</a><nav class="topnav"><a href="/#channels">Channels</a><a href="/#news">Surf news</a><a href="/#events">Events</a><a href="/contact.php">Contact</a></nav></header>
<main class="wrap">
<?php if($current):
$streams=$current['streams']??[]; $primary=$streams[0]??null;
$idx=0; foreach($locations as $i=>$l){if($l['slug']===$current['slug']){$idx=$i;break;}}
$prev=$locations[($idx-1+count($locations))%count($locations)];$next=$locations[($idx+1)%count($locations)];
?>
<section class="hero"><div class="eyebrow">BEACH TV · <?=e($current['region'])?></div><h1><?=e($current['name'])?></h1><p>Live beach view with surf, weather, rain, wind, tide model, shark-safety context, sunrise, sunset and moon phase — all on one screen.</p></section>
<?php if(count($streams)>1):?><div class="stream-tabs"><?php foreach($streams as $i=>$s):?><button class="pill <?=$i===0?'active':''?>" data-stream="<?=$i?>"><?=e($s['name'])?></button><?php endforeach;?></div><?php endif;?>
<div class="player-shell"><div class="player" id="player"></div><div class="source-note" id="sourceNote"></div></div>
<div class="actions"><a class="btn" href="/">← All channels</a><a class="btn warn" href="#report">⚠ Report this stream</a></div>
<form id="report" class="report-box" action="/report.php" method="post"><input type="hidden" name="channel" value="<?=e($current['slug'])?>"><input type="hidden" name="stream_id" id="reportStreamId" value="<?=e($primary['id']??'')?>"><h3>Report this stream</h3><select name="issue"><option value="offline">Camera not working</option><option value="buffering">Buffering / freezes</option><option value="wrong">Wrong beach / image</option><option value="stale">Image appears stale</option><option value="other">Other</option></select><textarea name="details" maxlength="500" placeholder="Optional details"></textarea><button class="btn primary" type="submit">Send report</button></form>
<?php if(!empty($_GET['reported'])):?><p class="notice">Thanks. The report was recorded and an urgent notification was sent to the site administrator.</p><?php endif;?>

<section class="section"><div class="section-head"><h2>Conditions now</h2><span class="muted">Updated automatically</span></div>
<div class="conditions">
<div class="metric"><small>Weather</small><strong><?=e($condition)?></strong><div class="sub"><?=n($wc['temperature_2m']??null,1,'°C')?> · feels <?=n($wc['apparent_temperature']??null,1,'°C')?> · humidity <?=n($wc['relative_humidity_2m']??null,0,'%')?></div></div>
<div class="metric <?=$storm?'risk':''?>"><small>Rain / thunderstorm</small><strong><?=$storm?'⚡ Thunderstorm':'🌧 '.n($wc['rain']??$wc['precipitation']??null,1,' mm')?></strong><div class="sub">Cloud <?=n($wc['cloud_cover']??null,0,'%')?> · showers <?=n($wc['showers']??null,1,' mm')?></div></div>
<div class="metric"><small>Wind</small><strong><?=n($wc['wind_speed_10m']??null,0,' km/h')?> <?=e(cc_compass($wc['wind_direction_10m']??null))?></strong><div class="sub">Gusts <?=n($wc['wind_gusts_10m']??null,0,' km/h')?></div></div>
<div class="metric"><small>Surf</small><strong><?=n($mc['wave_height']??null,1,' m')?> · <?=n($mc['wave_period']??null,1,' s')?></strong><div class="sub">From <?=e(cc_compass($mc['wave_direction']??null))?> · swell <?=n($mc['swell_wave_height']??null,1,' m')?> / <?=n($mc['swell_wave_period']??null,1,' s')?></div></div>
<div class="metric"><small>Sea</small><strong><?=n($mc['sea_surface_temperature']??null,1,'°C')?></strong><div class="sub">Model sea level <?=n($mc['sea_level_height_msl']??null,2,' m')?></div></div>
<div class="metric"><small>Sun</small><strong>☀ <?=e($sunrise?substr($sunrise,11,5):'—')?> → <?=e($sunset?substr($sunset,11,5):'—')?></strong><div class="sub">UV max today <?=n($todayW['uv_index_max'][0]??null,1)?></div></div>
<div class="metric"><small>Moon</small><strong>☾ <?=e($moon['phase'])?></strong><div class="sub"><?=n($moon['illumination'],0,'%')?> illuminated · next full moon <?=e($moon['next_full']->format('j M'))?> · next new moon <?=e($moon['next_new']->format('j M'))?></div></div>
<div class="metric"><small>Shark safety</small><strong><?=e($shark['status'])?></strong><div class="sub"><?=e($shark['detail'])?></div></div>
</div>
</section>

<section class="section"><div class="section-head"><h2>High & low tide model</h2><span class="muted">Approximate coastal model</span></div>
<?php if($tides):?><div class="tide-row"><?php foreach($tides as $t):?><div class="tide"><b><?=$t['type']==='High'?'⬆ High':'⬇ Low'?></b><br><?=e($t['time'])?> · <?=n($t['height'],2,' m')?></div><?php endforeach;?></div><p class="muted" style="font-size:12px">Tide heights are modelled sea-level values and are not for navigation. Check official Bureau of Meteorology tide predictions when precision matters.</p><?php else:?><div class="notice">Tide model temporarily unavailable.</div><?php endif;?>
</section>

<section class="section"><div class="section-head"><h2>7-day surf & weather outlook</h2><span class="muted">Surf + weather</span></div><div class="forecast">
<?php $days=$todayW['time']??[]; foreach($days as $i=>$d):?>
<div class="day"><b><?=e(date('D j M',strtotime($d)))?></b><div class="wave"><?=n($todayM['wave_height_max'][$i]??null,1,' m')?></div><small>Period <?=n($todayM['wave_period_max'][$i]??null,1,' s')?> · <?=e(cc_compass($todayM['wave_direction_dominant'][$i]??null))?></small><small><?=e(cc_weather_text(isset($todayW['weather_code'][$i])?(int)$todayW['weather_code'][$i]:null))?> · <?=n($todayW['temperature_2m_max'][$i]??null,0,'°')?> / <?=n($todayW['temperature_2m_min'][$i]??null,0,'°')?></small><small>Rain <?=n($todayW['precipitation_probability_max'][$i]??null,0,'%')?> · wind <?=n($todayW['wind_speed_10m_max'][$i]??null,0,' km/h')?></small></div>
<?php endforeach;?></div></section>

<div class="tv-nav"><a href="/<?=e($prev['slug'])?>">← <span class="muted">Previous</span><br><b><?=e($prev['name'])?></b></a><a href="/<?=e($next['slug'])?>"><span class="muted">Next</span> →<br><b><?=e($next['name'])?></b></a></div>

<section class="section"><div class="section-head"><h2>Nearby beach TV</h2></div><div class="grid"><?php foreach($locations as $l):if($l['slug']===$current['slug'])continue; if(abs((float)$l['lat']-(float)$current['lat'])>.15)continue;?><a class="card" href="/<?=e($l['slug'])?>"><span class="tag"><?=has_live($l)?'<span class="live-dot"></span>LIVE':'<span class="pending-dot"></span>FORECAST'?> · <?=e($l['region'])?></span><h3><?=e($l['name'])?></h3><span class="muted">Watch channel →</span></a><?php endforeach;?></div></section>

<script>
const streams=<?=json_encode($streams,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>;
let hls=null;
function playStream(i){
 const s=streams[i], p=document.getElementById('player'), note=document.getElementById('sourceNote'), rid=document.getElementById('reportStreamId');
 if(hls){hls.destroy();hls=null;} p.innerHTML=''; if(rid)rid.value=s.id||'';
 document.querySelectorAll('[data-stream]').forEach((b,n)=>b.classList.toggle('active',n===i));
 note.textContent=(s.camera_note||'')+(s.source_name?' · Source: '+s.source_name:'');
 if(s.stream_type==='hls'&&s.stream_url){
   const v=document.createElement('video');v.controls=true;v.muted=true;v.autoplay=true;v.playsInline=true;p.appendChild(v);
   if(window.Hls&&Hls.isSupported()){hls=new Hls({enableWorker:true,lowLatencyMode:true});hls.loadSource(s.stream_url);hls.attachMedia(v);hls.on(Hls.Events.MANIFEST_PARSED,()=>v.play().catch(()=>{}));hls.on(Hls.Events.ERROR,(ev,data)=>{if(data.fatal){note.textContent='Stream error detected · '+(s.camera_note||'Please report the camera if this persists.');}});}
   else if(v.canPlayType('application/vnd.apple.mpegurl')){v.src=s.stream_url;v.play().catch(()=>{});}
 } else if(s.stream_type==='iframe'&&s.stream_url){
   const f=document.createElement('iframe');f.src=s.stream_url;f.allow='autoplay; fullscreen';f.allowFullscreen=true;f.loading='eager';p.appendChild(f);
 } else {
   p.innerHTML='<div class="player-msg"><span class="tag">CHANNEL AVAILABLE</span><h2>Live camera embed is being verified</h2><p class="muted">Forecast, tides, weather and beach conditions are already live. We only add video feeds when the source is stable and can be embedded legitimately.</p></div>';
 }
}
document.querySelectorAll('[data-stream]').forEach((b,i)=>b.addEventListener('click',()=>playStream(i)));
playStream(0);
</script>

<?php else: ?>
<section class="hero"><div class="eyebrow">GOLD COAST · TWEED COAST · BYRON</div><h1>Your coast.<br>Live.</h1><p>Beach TV from The Spit to Byron Bay: live cameras where a stable feed is available, plus surf, weather, rain, wind, tides, sunrise, sunset, moon phase and local surf updates.</p></section>

<section class="section"><div class="section-head"><h2>Most watched</h2><span class="muted">Last 7 days</span></div><div class="grid"><?php foreach($popular as $l):?><a class="card" href="/<?=e($l['slug'])?>"><span class="tag"><?=has_live($l)?'<span class="live-dot"></span>LIVE':'<span class="pending-dot"></span>FORECAST'?> · <?=e($l['region'])?></span><h3><?=e($l['name'])?></h3><span class="muted"><?=isset($l['views'])?number_format((int)$l['views']).' views · ':''?>Watch →</span></a><?php endforeach;?></div></section>

<section class="section" id="channels"><div class="section-head"><h2>Beach channels</h2><span class="muted"><?=count($locations)?> locations</span></div>
<?php $regions=[];foreach($locations as $l)$regions[$l['region']][]=$l;foreach($regions as $region=>$list):?><h3 class="region-title"><?=e($region)?></h3><div class="grid"><?php foreach($list as $l):?><a class="card" href="/<?=e($l['slug'])?>"><span class="tag"><?=has_live($l)?'<span class="live-dot"></span>LIVE CAMERA':'<span class="pending-dot"></span>FORECAST CHANNEL'?></span><h3><?=e($l['name'])?></h3><p class="muted">Surf · weather · tides · moon · beach safety</p></a><?php endforeach;?></div><?php endforeach;?></section>

<section class="section" id="events"><div class="section-head"><h2>Upcoming surf events</h2><span class="muted">Read here — no redirect required</span></div><div class="story-grid"><?php foreach($events as $item):?><details class="story"><summary><span class="tag">EVENT<?=!empty($item['event_date'])?' · '.e(date('D j M',strtotime($item['event_date']))):''?></span><h3><?=e($item['title'])?></h3><div class="muted"><?=e($item['summary'])?></div></summary><div class="story-body"><?=nl2br(e($item['body']))?><div class="story-source">Source: <?=e($item['source_name'])?><?php if(!empty($item['source_url'])):?> · <a href="<?=e($item['source_url'])?>" rel="noopener" target="_blank">Original source</a><?php endif;?></div></div></details><?php endforeach;?></div></section>

<section class="section" id="news"><div class="section-head"><h2>Surf & coast news</h2><span class="muted">Full CostaCams summaries on-page</span></div><div class="story-grid"><?php foreach($news as $item):?><details class="story"><summary><span class="tag">NEWS</span><h3><?=e($item['title'])?></h3><div class="muted"><?=e($item['summary'])?></div></summary><div class="story-body"><?=nl2br(e($item['body']))?><div class="story-source">Source: <?=e($item['source_name'])?><?php if(!empty($item['source_url'])):?> · <a href="<?=e($item['source_url'])?>" rel="noopener" target="_blank">Original source</a><?php endif;?></div></div></details><?php endforeach;?></div></section>
<?php endif;?>
</main>
<footer class="footer">CostaCams · Beach TV from the Gold Coast to Byron Bay · <a href="/contact.php">Contact</a> · Forecast data: Open-Meteo. Tide model is indicative only.</footer>
</body></html>