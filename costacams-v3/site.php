<?php
declare(strict_types=1);

$isHome=empty($_GET['slug']);
$homeCache='/tmp/costacams-home-v6.html';
$cacheTtl=30;

// The homepage is safe to cache because live presence and the random camera are
// hydrated in the browser. This skips PHP/database work for almost every visit.
if($isHome && empty($_GET['nocache']) && is_file($homeCache) && (time()-filemtime($homeCache))<$cacheTtl){
    header('X-CostaCams-Cache: HIT');
    header('Cache-Control: public, max-age=15, stale-while-revalidate=45');
    readfile($homeCache);
    exit;
}

ob_start();
require __DIR__.'/index.php';
$html=ob_get_clean();

$needle='<nav class="topnav"><a href="/#channels">Channels</a>';
$replacement='<nav class="topnav"><a href="/map">Map</a><a href="/#channels">Channels</a>';
$html=str_replace($needle,$replacement,$html);

// Warm up only the hosts used after first paint. Nothing here blocks rendering.
$preconnect='<link rel="preconnect" href="https://d1nm4r8e5x1rwd.cloudfront.net" crossorigin><link rel="dns-prefetch" href="//d1nm4r8e5x1rwd.cloudfront.net"><link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin><link rel="dns-prefetch" href="//cdn.jsdelivr.net"><link rel="dns-prefetch" href="//a.tile.openstreetmap.org">';
$html=str_replace('</head>',$preconnect.'</head>',$html);

$recommendCandidates=[];

if ($isHome) {
    $html=preg_replace('~(<section class="hero">.*?</section>)~s','$1<!--CC_HOME_MAP-->',$html,1)??$html;

    $healthySlugs=[
        'main-beach','surfers-paradise','kurrawa','miami',
        'palm-beach','currumbin','tugun','kirra',
        'rainbow-bay','the-spit','tweed-river','kingscliff','cabarita'
    ];

    // index.php already loaded locations + popular data; reuse it instead of
    // issuing duplicate queries.
    $locationsBySlug=[];
    foreach(($locations??[]) as $loc){$locationsBySlug[(string)$loc['slug']]=$loc;}
    $views=[];
    foreach(($popular??[]) as $row){$views[(string)$row['slug']]=(int)($row['views']??0);}

    // IMPORTANT: web requests NEVER probe stream URLs. The 5-minute cron is
    // the only health checker. The homepage only reads its latest result.
    $healthReady=false;$okStreamIds=[];
    try{
        $pdo=cc_db();
        if($pdo){
            $tableExists=(bool)$pdo->query("SHOW TABLES LIKE 'stream_health'")->fetchColumn();
            if($tableExists){
                $fresh=(int)$pdo->query("SELECT COUNT(*) FROM stream_health WHERE checked_at>=DATE_SUB(NOW(),INTERVAL 10 MINUTE)")->fetchColumn();
                if($fresh>0){
                    $healthReady=true;
                    foreach($pdo->query("SELECT stream_id FROM stream_health WHERE status='ok' AND checked_at>=DATE_SUB(NOW(),INTERVAL 10 MINUTE)")->fetchAll() as $r){
                        $okStreamIds[(int)$r['stream_id']]=true;
                    }
                }
            }
        }
    }catch(Throwable $e){$healthReady=false;}

    $healthy=[];
    foreach($healthySlugs as $order=>$slug){
        $loc=$locationsBySlug[$slug]??null;if(!$loc)continue;
        $workingStream=null;
        foreach($loc['streams']??[] as $stream){
            if(!in_array($stream['stream_type']??'',['hls','iframe'],true)||empty($stream['stream_url']))continue;
            if(!$healthReady || !empty($okStreamIds[(int)($stream['id']??0)])){$workingStream=$stream;break;}
        }
        if(!$workingStream)continue;
        $loc['_views']=$views[$slug]??0;
        $loc['_editorial_order']=$order;
        $loc['_working_stream']=$workingStream;
        $healthy[]=$loc;

        $recommendCandidates[]=[
            'slug'=>(string)$loc['slug'],
            'name'=>(string)$loc['name'],
            'region'=>(string)$loc['region'],
            'type'=>(string)$workingStream['stream_type'],
            'url'=>(string)$workingStream['stream_url'],
        ];
    }

    $recommendHtml='<aside class="cc-home-recommend" id="ccRecommended"><div class="cc-rec-label"><span class="live-dot"></span>RECOMMENDED LIVE CAM</div><div class="cc-rec-preview" id="ccRecPreview"><div class="cc-rec-loading">Picking a live beach…</div></div><div class="cc-rec-body"><span class="tag" id="ccRecRegion">LIVE</span><h3 id="ccRecName">Finding a camera…</h3><p>Changes each time you open the page. Only cameras currently considered healthy are included.</p><a class="cc-map-btn" id="ccRecLink" href="/#channels">Browse cameras →</a></div></aside>';

    $mapSpotlight='<section class="cc-home-explore"><div class="cc-home-map-panel"><div class="cc-home-map-head"><div><span class="tag">LIVE CAMERA MAP</span><h2>Explore the coast.</h2></div><a href="/map">Open full map ↗</a></div><div class="cc-home-map-frame"><div class="cc-map-skeleton" id="ccMapSkeleton"><span>🗺</span><b>Loading live map…</b></div><iframe id="ccHomeMap" data-src="/map-home.php" title="Interactive CostaCams camera map"></iframe></div></div>'.$recommendHtml.'</section>';
    $html=str_replace('<!--CC_HOME_MAP-->',$mapSpotlight,$html);

    usort($healthy,function($a,$b){
        $v=((int)$b['_views'])<=>((int)$a['_views']);
        return $v!==0?$v:(((int)$a['_editorial_order'])<=>((int)$b['_editorial_order']));
    });
    $healthy=array_slice($healthy,0,8);

    $cards='';
    foreach($healthy as $loc){
        $slug=htmlspecialchars((string)$loc['slug'],ENT_QUOTES,'UTF-8');
        $name=htmlspecialchars((string)$loc['name'],ENT_QUOTES,'UTF-8');
        $region=htmlspecialchars((string)$loc['region'],ENT_QUOTES,'UTF-8');
        $viewText=((int)$loc['_views']>0)?number_format((int)$loc['_views']).' views · ':'';
        $cards.='<a class="card" href="/'.$slug.'"><span class="tag"><span class="live-dot"></span>LIVE · '.$region.'</span><h3>'.$name.'</h3><span class="muted">'.$viewText.'Watch →</span><span class="cc-live-card" data-live-slug="'.$slug.'">● — watching now</span></a>';
    }

    if($cards!==''){
        $sub=$healthReady?'Verified working feeds · popularity last 7 days':'Reliable live feeds · popularity last 7 days';
        $section='<section class="section"><div class="section-head"><h2>Popular live cameras</h2><span class="muted">'.$sub.'</span></div><div class="grid">'.$cards.'</div></section>';
        $html=preg_replace('~<section class="section"><div class="section-head"><h2>Most watched</h2>.*?</section>~s',$section,$html,1)??$html;
    }
}

$presenceCss=<<<'CSS'
<style id="cc-presence-style">
.cc-presence-bar{display:flex;justify-content:center;gap:8px;align-items:center;padding:7px 14px;background:#07110d;border-bottom:1px solid #183329;color:#bce7cf;font-size:12px}.cc-presence-dot,.cc-live-card:before{display:inline-block;width:7px;height:7px;border-radius:50%;background:#69e0a0;box-shadow:0 0 8px #69e0a0}.cc-channel-live{display:inline-flex;gap:8px;align-items:center;margin:2px 0 12px;padding:8px 12px;border:1px solid #244936;background:#09140f;border-radius:999px;color:#c7eed6;font-size:13px;font-weight:700}.cc-live-card{display:block;margin-top:8px;color:#8fd8aa;font-size:12px}.cc-live-card:before{content:'';margin-right:6px;width:6px;height:6px}.cc-presence-peak{color:#789c87;font-weight:500}
.cc-home-explore{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(280px,.65fr);gap:14px;margin:4px 0 34px}.cc-home-map-panel,.cc-home-recommend{border:1px solid #273238;background:linear-gradient(180deg,#0e1214,#090b0c);border-radius:20px;overflow:hidden}.cc-home-map-head{display:flex;justify-content:space-between;align-items:end;gap:15px;padding:17px 18px}.cc-home-map-head h2{margin:3px 0 0;font-size:24px}.cc-home-map-head>a{color:#50dcff;font-size:13px;font-weight:800;white-space:nowrap}.cc-home-map-frame{height:430px;background:#0b0d0e;position:relative;overflow:hidden}.cc-home-map-frame iframe{position:absolute;inset:0;width:100%;height:100%;border:0;display:block;opacity:0;transition:opacity .18s ease}.cc-home-map-frame iframe.ready{opacity:1}.cc-map-skeleton{position:absolute;inset:0;display:grid;place-content:center;text-align:center;gap:8px;color:#7f9198;background:radial-gradient(circle at 50% 45%,#142026,#0a0d0e 58%);z-index:1}.cc-map-skeleton span{font-size:34px}.cc-map-skeleton.hide{opacity:0;pointer-events:none;transition:opacity .18s ease}.cc-home-recommend{display:flex;flex-direction:column}.cc-rec-label{padding:12px 14px;border-bottom:1px solid #222b2e;color:#9fd7b4;font-weight:800;font-size:11px;letter-spacing:.07em}.cc-rec-preview{aspect-ratio:16/10;background:#000;overflow:hidden;display:grid;place-items:center;position:relative}.cc-rec-preview video,.cc-rec-preview iframe{width:100%;height:100%;display:block;border:0;background:#000}.cc-rec-loading{color:#78868b;font-size:12px}.cc-rec-body{padding:16px}.cc-rec-body h3{font-size:24px;margin:5px 0}.cc-rec-body p{color:#9da9ae;font-size:13px;margin:0 0 16px}.cc-map-btn{display:inline-block;border:1px solid #50dcff;color:#b9f3ff;background:#0c2026;padding:10px 14px;border-radius:999px;font-weight:800;font-size:13px}.cc-map-btn:hover{background:#12313a}
@media(max-width:900px){.cc-home-explore{grid-template-columns:1fr}.cc-home-recommend{display:grid;grid-template-columns:minmax(0,1fr) minmax(260px,.8fr)}.cc-rec-label{grid-column:1/-1}.cc-rec-preview{aspect-ratio:16/9}.cc-rec-body{align-self:center}}@media(max-width:650px){.cc-presence-bar{font-size:11px}.cc-channel-live{font-size:12px}.cc-home-map-frame{height:360px}.cc-home-map-head{align-items:flex-start;flex-direction:column}.cc-home-recommend{display:block}.cc-rec-preview{aspect-ratio:16/9}}
</style>
CSS;
$html=str_replace('</head>',$presenceCss.'</head>',$html);

$channelSlug=preg_replace('/[^a-z0-9-]/','',strtolower((string)($_GET['slug']??'')));
$presenceBar='<div class="cc-presence-bar"><span class="cc-presence-dot"></span><strong id="ccSiteNow">Checking live viewers…</strong><span class="cc-presence-peak" id="ccSitePeak"></span></div>';
$html=str_replace('</header>','</header>'.$presenceBar,$html);

if($channelSlug!==''){
    $channelBadge='<div class="cc-channel-live" id="ccChannelLive"><span class="cc-presence-dot"></span><span><b id="ccChannelNow">Checking viewers…</b> <span class="cc-presence-peak" id="ccChannelPeak"></span></span></div>';
    $html=preg_replace('~(<section class="hero">.*?</h1>)~s','$1'.$channelBadge,$html,1)??$html;
}

$presenceJs=<<<'HTML'
<script>
(function(){
  const channel=__CHANNEL__;
  function rid(n){const a=new Uint8Array(n);crypto.getRandomValues(a);return Array.from(a,b=>b.toString(16).padStart(2,'0')).join('');}
  function getStored(key,session){try{const store=session?sessionStorage:localStorage;let v=store.getItem(key);if(!v){v=rid(session?10:16);store.setItem(key,v);}return v;}catch(e){return rid(session?10:16);}}
  const visitor=getStored('ccVisitorId',false),tab=getStored('ccTabId',true);
  function render(d){
    if(!d||!d.ok)return;
    const sn=document.getElementById('ccSiteNow'),sp=document.getElementById('ccSitePeak');
    if(sn)sn.textContent=d.site.now+' watching CostaCams now';if(sp)sp.textContent='· Peak today '+d.site.peak_today;
    if(channel){const n=(d.channels&&d.channels[channel])||0,p=(d.channel_peaks&&d.channel_peaks[channel])||0,cn=document.getElementById('ccChannelNow'),cp=document.getElementById('ccChannelPeak');if(cn)cn.textContent=n+' watching now';if(cp)cp.textContent='· Peak today '+p;}
    document.querySelectorAll('[data-live-slug]').forEach(el=>{const s=el.getAttribute('data-live-slug'),n=(d.channels&&d.channels[s])||0;el.textContent='● '+n+' watching now';});
    document.querySelectorAll('a.card').forEach(card=>{if(card.querySelector('[data-live-slug]'))return;const tag=card.querySelector('.tag');if(!tag||!tag.textContent.includes('LIVE'))return;let href=(card.getAttribute('href')||'').split(/[?#]/)[0];if(href.charAt(0)==='/')href=href.slice(1);if(!href)return;const el=document.createElement('span');el.className='cc-live-card';el.dataset.liveSlug=href;el.textContent='● '+(((d.channels&&d.channels[href])||0))+' watching now';card.appendChild(el);});
  }
  function showUnavailable(){const sn=document.getElementById('ccSiteNow'),cn=document.getElementById('ccChannelNow');if(sn)sn.textContent='Live viewer count unavailable';if(cn)cn.textContent='Viewer count unavailable';}
  async function beat(){try{const body=new URLSearchParams({action:'heartbeat',visitor,tab,channel});const r=await fetch('/presence.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body,cache:'no-store',credentials:'same-origin',keepalive:true});if(!r.ok){showUnavailable();return;}render(await r.json());}catch(e){showUnavailable();}}
  // Presence is secondary; let first paint win.
  setTimeout(beat,120);
  setInterval(beat,25000);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)beat();});
})();
</script>
HTML;
$presenceJs=str_replace('__CHANNEL__',json_encode($channelSlug,JSON_UNESCAPED_SLASHES),$presenceJs);
$html=str_replace('</body>',$presenceJs.'</body>',$html);

if($isHome){
    $candidateJson=json_encode($recommendCandidates,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    $homeJs=<<<'HTML'
<script>
(function(){
  const candidates=__CANDIDATES__;
  function afterPaint(fn){requestAnimationFrame(()=>requestAnimationFrame(()=>setTimeout(fn,40)));}
  function loadScript(src,done){const existing=document.querySelector('script[data-cc-src="'+src+'"]');if(existing){if(window.Hls)done();else existing.addEventListener('load',done,{once:true});return;}const s=document.createElement('script');s.src=src;s.async=true;s.dataset.ccSrc=src;s.onload=done;document.head.appendChild(s);}
  function chooseCandidate(){
    if(!candidates.length)return null;
    let last='';try{last=sessionStorage.getItem('ccLastRecommended')||'';}catch(e){}
    let pool=candidates.length>1?candidates.filter(c=>c.slug!==last):candidates;
    const c=pool[Math.floor(Math.random()*pool.length)];
    try{sessionStorage.setItem('ccLastRecommended',c.slug);}catch(e){}
    return c;
  }
  function renderRecommended(){
    const c=chooseCandidate();if(!c)return;
    const name=document.getElementById('ccRecName'),region=document.getElementById('ccRecRegion'),link=document.getElementById('ccRecLink'),box=document.getElementById('ccRecPreview');
    if(name)name.textContent=c.name;if(region)region.textContent=c.region;if(link){link.href='/'+encodeURIComponent(c.slug);link.textContent='Watch this camera →';}if(!box)return;
    box.innerHTML='';
    if(c.type==='iframe'){
      const f=document.createElement('iframe');f.allow='autoplay; fullscreen';f.allowFullscreen=true;f.loading='lazy';box.appendChild(f);setTimeout(()=>{f.src=c.url;},220);return;
    }
    const v=document.createElement('video');v.muted=true;v.autoplay=true;v.playsInline=true;v.controls=true;v.preload='metadata';box.appendChild(v);
    const start=()=>{if(v.canPlayType('application/vnd.apple.mpegurl')){v.src=c.url;v.play().catch(()=>{});return;}loadScript('https://cdn.jsdelivr.net/npm/hls.js@latest',()=>{if(window.Hls&&Hls.isSupported()){const h=new Hls({enableWorker:true,maxBufferLength:12});h.loadSource(c.url);h.attachMedia(v);h.on(Hls.Events.MANIFEST_PARSED,()=>v.play().catch(()=>{}));}});};
    setTimeout(start,260);
  }
  function loadMap(){const f=document.getElementById('ccHomeMap');if(!f||f.src)return;f.onload=()=>{f.classList.add('ready');const s=document.getElementById('ccMapSkeleton');if(s)s.classList.add('hide');};f.src=f.dataset.src;}
  // First paint happens with pure HTML/CSS. Heavy map/video start afterwards.
  afterPaint(()=>{renderRecommended();if('requestIdleCallback' in window)requestIdleCallback(loadMap,{timeout:650});else setTimeout(loadMap,160);});
})();
</script>
HTML;
    $homeJs=str_replace('__CANDIDATES__',$candidateJson?:'[]',$homeJs);
    $html=str_replace('</body>',$homeJs.'</body>',$html);
}

if($isHome){
    @file_put_contents($homeCache,$html,LOCK_EX);
    header('X-CostaCams-Cache: MISS');
    header('Cache-Control: public, max-age=15, stale-while-revalidate=45');
}else{
    header('Cache-Control: private, no-cache');
}

echo $html;
