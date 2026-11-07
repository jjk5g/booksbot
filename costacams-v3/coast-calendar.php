<?php
declare(strict_types=1);

/**
 * Coast Events Calendar: read-only view of published news_items events.
 * Called when static homepage is regenerated (not on each visitor click).
 */
function cc_calendar_home(): string {
    $rows=[];
    try {
        $pdo=cc_db();
        if ($pdo) {
            $sql="SELECT slug,title,summary,event_date,event_end_date,event_type,region,locality
                  FROM news_items WHERE active=1 AND category='event'
                  AND event_date IS NOT NULL
                  AND event_date < DATE_ADD(CURDATE(), INTERVAL 18 MONTH)
                  AND COALESCE(event_end_date,event_date) >= DATE_SUB(CURDATE(), INTERVAL 31 DAY)
                  ORDER BY event_date ASC, sort_order ASC LIMIT 250";
            try {$rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);}
            catch (Throwable $ignored) {
                $rows=$pdo->query("SELECT slug,title,summary,event_date,NULL AS event_end_date,
                    NULL AS event_type,NULL AS region,NULL AS locality
                    FROM news_items WHERE active=1 AND category='event' AND event_date IS NOT NULL
                    AND event_date >= DATE_SUB(CURDATE(), INTERVAL 31 DAY)
                    ORDER BY event_date ASC LIMIT 250")->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Throwable $e) {
        error_log('CostaCams calendar data: '.get_class($e));
    }
    $events=[];
    foreach ($rows as $r) {
        $date=(string)($r['event_date']??'');
        if(!preg_match('/^\d{4}-\d\d-\d\d/', $date))continue;
        $slug=(string)($r['slug']??'');
        if(!preg_match('/^[a-z0-9-]{1,150}$/',$slug))continue;
        $events[]=[
            'slug'=>$slug,
            'title'=>mb_substr((string)($r['title']??'Event'),0,180),
            'summary'=>mb_substr((string)($r['summary']??''),0,180),
            'start'=>$date,
            'end'=>(string)($r['event_end_date']??''),
            'type'=>(string)($r['event_type']??''),
            'region'=>(string)($r['region']??''),
            'place'=>(string)($r['locality']??'')
        ];
    }
    $json=json_encode($events,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?:'[]';
    $h=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $today=date('Y-m-d');
    $upcoming=array_values(array_filter($events,static fn($a)=>substr($a['end']?:$a['start'],0,10)>=$today));
    $top=array_slice($upcoming,0,5);
    ob_start();
?>
<style id="cc-calendar-style">
#calendar{margin:34px 0 42px;min-width:0}
#calendar *{box-sizing:border-box}
.cc-cal-heading{display:flex;justify-content:space-between;align-items:end;gap:14px;flex-wrap:wrap;margin-bottom:15px}
.cc-cal-heading h2{margin:4px 0 0;font-size:clamp(25px,3vw,34px);line-height:1.15}
.cc-cal-heading p{margin:5px 0 0;color:#91a5ac;font-size:13px}
.cc-cal-shell{display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,.85fr);gap:14px;align-items:stretch}
.cc-cal-board,.cc-cal-agenda{border:1px solid #2b4148;border-radius:19px;background:linear-gradient(155deg,#0f1c21,#090d0f 70%);min-width:0;overflow:hidden}
.cc-cal-board{padding:17px}
.cc-cal-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:15px}
.cc-cal-toolbar h3{font-size:19px;letter-spacing:-.02em;margin:0}
.cc-cal-actions{display:flex;gap:6px;align-items:center}
.cc-cal-actions button{border:1px solid #33474e;background:#101c20;color:#e4f2f5;border-radius:10px;min-width:40px;min-height:40px;padding:7px 10px;cursor:pointer;font:inherit}
.cc-cal-actions button:disabled{opacity:.3;cursor:default}
.cc-cal-actions .cc-cal-today{font-size:12px}
.cc-cal-days{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:5px}
.cc-cal-weekday{text-align:center;color:#77919a;font-size:11px;font-weight:700;padding:6px 0}
.cc-cal-cell{min-width:0;min-height:65px;position:relative;border:1px solid transparent;border-radius:11px;background:#0b1215;color:#b2c2c7;display:flex;flex-direction:column;align-items:flex-start;gap:7px;padding:9px;cursor:pointer;text-align:left;font:inherit}
.cc-cal-cell:hover{background:#11232a;border-color:#366878}
.cc-cal-cell.outside{opacity:.25}
.cc-cal-cell.today{border-color:#3d636f}
.cc-cal-cell.selected{border-color:#50dcff;background:#12303a;box-shadow:inset 0 0 0 1px #50dcff38;color:#fff}
.cc-cal-cell .num{font-size:14px;font-weight:750}
.cc-cal-count{font-size:10px;font-weight:800;padding:3px 6px;border-radius:999px;background:#164f60;color:#a5f3ff;min-width:23px;text-align:center}
.cc-cal-dot{width:6px;height:6px;border-radius:50%;background:#50dcff;display:inline-block;box-shadow:0 0 6px #50dcff55}
.cc-cal-agenda{padding:19px;display:flex;flex-direction:column}
.cc-cal-agenda-head{display:flex;justify-content:space-between;align-items:center;gap:10px;padding-bottom:13px;border-bottom:1px solid #26383e}
.cc-cal-agenda-head h3{margin:0;font-size:18px}
.cc-cal-agenda-head small{font-size:11px;color:#89a2a9}
.cc-cal-items{display:grid;gap:9px;margin-top:13px}
.cc-cal-item{display:block;border:1px solid #2a3c42;border-radius:12px;background:#0c1519;padding:12px;transition:background .15s,border-color .15s}
.cc-cal-item:hover{background:#12252c;border-color:#50dcff}
.cc-cal-item time{display:block;color:#6fdff5;font-weight:750;font-size:11px}
.cc-cal-item strong{display:block;font-size:15px;margin:5px 0;line-height:1.32;color:#eef7f8}
.cc-cal-item small{display:block;font-size:11px;color:#9eb0b6;line-height:1.4}
.cc-cal-item .cc-cal-read{display:block;margin-top:8px;font-size:12px;font-weight:800;color:#7be4fa}
.cc-cal-empty{padding:24px 8px;color:#a3b4b9;text-align:center;font-size:13px}
.cc-cal-note{margin-top:auto;padding-top:13px;font-size:11px;color:#6f898f}
#calendar button:focus-visible,#calendar a:focus-visible{outline:3px solid #50dcff;outline-offset:2px}
@media(max-width:800px){.cc-cal-shell{grid-template-columns:minmax(0,1fr)}.cc-cal-board{padding:13px}.cc-cal-agenda{padding:15px}.cc-cal-cell{min-height:60px}}
@media(max-width:430px){.cc-cal-heading h2{font-size:26px}.cc-cal-toolbar h3{font-size:17px}.cc-cal-actions .cc-cal-today{padding:6px}.cc-cal-days{gap:3px}.cc-cal-cell{min-height:54px;padding:6px;gap:5px;border-radius:8px}.cc-cal-cell .num{font-size:12px}.cc-cal-count{font-size:9px;padding:2px 5px}}
@media(prefers-reduced-motion:reduce){#calendar *{scroll-behavior:auto!important;transition:none!important}}
</style>
<section class="section" id="calendar" aria-labelledby="ccCalTitle">
  <div class="cc-cal-heading">
    <div><span class="tag">GOLD COAST · TWEED COAST · BYRON</span><h2 id="ccCalTitle">What's on the coast <span aria-hidden="true">📅</span></h2><p>Choose a day to see events, then open its full story.</p></div>
    <span class="muted" id="ccCalTotal"><?=count($upcoming)?> upcoming events</span>
  </div>
  <div class="cc-cal-shell">
    <div class="cc-cal-board">
      <div class="cc-cal-toolbar"><h3 id="ccCalMonth">Calendar</h3>
        <div class="cc-cal-actions"><button type="button" id="ccCalPrev" aria-label="Previous month">‹</button><button type="button" class="cc-cal-today" id="ccCalToday">Today</button><button type="button" id="ccCalNext" aria-label="Next month">›</button></div>
      </div>
      <div class="cc-cal-days" id="ccCalDays" role="group" aria-label="Event dates"><span class="cc-cal-weekday">Mon</span><span class="cc-cal-weekday">Tue</span><span class="cc-cal-weekday">Wed</span><span class="cc-cal-weekday">Thu</span><span class="cc-cal-weekday">Fri</span><span class="cc-cal-weekday">Sat</span><span class="cc-cal-weekday">Sun</span></div>
    </div>
    <div class="cc-cal-agenda" aria-live="polite">
      <div class="cc-cal-agenda-head"><h3 id="ccCalSelected">Coming up</h3><small id="ccCalSubtitle">Select a highlighted date</small></div>
      <div id="ccCalItems" class="cc-cal-items">
       <?php foreach($top as $event): ?>
        <a class="cc-cal-item" href="/event.php?slug=<?=rawurlencode($event['slug'])?>"><time><?= $h(substr($event['start'],0,16)) ?></time><strong><?=$h($event['title'])?></strong><small><?=$h($event['place']?:$event['region'])?></small><span class="cc-cal-read">Read event details →</span></a>
       <?php endforeach; ?>
       <?php if(!$top): ?><div class="cc-cal-empty">No upcoming events published yet. Check back soon.</div><?php endif; ?>
      </div>
      <div class="cc-cal-note">Times and details are taken from published event listings. Confirm final details with organisers.</div>
    </div>
  </div>
  <script type="application/json" id="ccCalData"><?=$json?></script>
</section>
<script id="cc-cal-script">
(function(){
'use strict';
const root=document.getElementById('calendar');
if(!root)return;
let events=[];
try{events=JSON.parse(document.getElementById('ccCalData').textContent||'[]');}catch(e){}
const byId=id=>document.getElementById(id);
const dayKey=d=>[d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');
const parseDay=s=>new Date(Number(s.slice(0,4)),Number(s.slice(5,7))-1,Number(s.slice(8,10)));
const today=new Date(),startMonth=new Date(today.getFullYear(),today.getMonth(),1);
let month=new Date(startMonth),selected=null;
const dateToString=d=>d.toLocaleDateString('en-AU',{weekday:'long',day:'numeric',month:'long'});
const countMap=new Map();
events.forEach(ev=>{
 if(!ev.start)return;
 let d=parseDay(ev.start.slice(0,10));
 const end=ev.end&&ev.end.slice(0,10)>=ev.start.slice(0,10)?parseDay(ev.end.slice(0,10)):d;
 let loop=0;
 while(d<=end&&loop++<32){let k=dayKey(d);countMap.set(k,(countMap.get(k)||0)+1);d.setDate(d.getDate()+1);}
});
function listFor(day){return events.filter(ev=>{
 const start=ev.start.slice(0,10),end=(ev.end||ev.start).slice(0,10);
 return start<=day&&day<=end;
});}
function item(ev){
 const a=document.createElement('a');a.className='cc-cal-item';a.href='/event.php?slug='+encodeURIComponent(ev.slug);
 const t=document.createElement('time');t.textContent=ev.start.slice(0,10).split('-').reverse().join('/')+(ev.start.slice(11,16)!=='00:00'&&ev.start.length>=16?' · '+ev.start.slice(11,16):'');
 const title=document.createElement('strong');title.textContent=ev.title;
 const meta=document.createElement('small');meta.textContent=[ev.place||ev.region,ev.summary].filter(Boolean).join(' · ');
 const read=document.createElement('span');read.className='cc-cal-read';read.textContent='Read event details →';
 a.append(t,title,meta,read);return a;
}
function renderList(day){
 const list=byId('ccCalItems');list.replaceChildren();
 let items;
 if(day){items=listFor(day);byId('ccCalSelected').textContent=dateToString(parseDay(day));byId('ccCalSubtitle').textContent=items.length+' event'+(items.length===1?'':'s');}
 else{let td=dayKey(today);items=events.filter(e=>(e.end||e.start).slice(0,10)>=td).slice(0,5);byId('ccCalSelected').textContent='Coming up';byId('ccCalSubtitle').textContent='Next published events';}
 if(!items.length){const p=document.createElement('div');p.className='cc-cal-empty';p.textContent=day?'No published events on this date. Try a highlighted day.':'No upcoming events published yet.';list.append(p);}
 else items.forEach(e=>list.append(item(e)));
}
function render(){
 byId('ccCalMonth').textContent=month.toLocaleDateString('en-AU',{month:'long',year:'numeric'});
 const grid=byId('ccCalDays');while(grid.children.length>7)grid.lastChild.remove();
 const y=month.getFullYear(),m=month.getMonth(),first=new Date(y,m,1);
 const lead=(first.getDay()+6)%7,last=new Date(y,m+1,0).getDate();
 const total=Math.ceil((lead+last)/7)*7;
 for(let i=0;i<total;i++){
 const dt=new Date(y,m,i-lead+1),key=dayKey(dt),count=countMap.get(key)||0;
 const btn=document.createElement('button');btn.type='button';
 btn.className='cc-cal-cell'+(dt.getMonth()!==m?' outside':'')+(key===dayKey(today)?' today':'')+(selected===key?' selected':'');
 btn.setAttribute('aria-label',dateToString(dt)+(count?' · '+count+' events':' · no events'));
 btn.setAttribute('aria-pressed',selected===key?'true':'false');
 const n=document.createElement('span');n.className='num';n.textContent=dt.getDate();btn.append(n);
 if(count){const badge=document.createElement('span');badge.className='cc-cal-count';badge.textContent=count+' event'+(count>1?'s':'');btn.append(badge);}
 btn.onclick=()=>{selected=key;if(dt.getMonth()!==month.getMonth())month=new Date(dt.getFullYear(),dt.getMonth(),1);render();renderList(selected);};
 grid.append(btn);
 }
 const delta=(month.getFullYear()-startMonth.getFullYear())*12+(month.getMonth()-startMonth.getMonth());
 byId('ccCalPrev').disabled=delta<=-1;byId('ccCalNext').disabled=delta>=18;
}
byId('ccCalPrev').addEventListener('click',()=>{month=new Date(month.getFullYear(),month.getMonth()-1,1);selected=null;render();renderList(null);});
byId('ccCalNext').addEventListener('click',()=>{month=new Date(month.getFullYear(),month.getMonth()+1,1);selected=null;render();renderList(null);});
byId('ccCalToday').addEventListener('click',()=>{month=new Date(startMonth);selected=dayKey(today);render();renderList(selected);});
render();renderList(null);
})();
</script>
<?php
    return (string)ob_get_clean();
}
