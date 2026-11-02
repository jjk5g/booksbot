<?php
declare(strict_types=1);
ob_start();
require __DIR__.'/index.php';
$html=ob_get_clean();
$recommendedHlsUrl=null;

$needle='<nav class="topnav"><a href="/#channels">Channels</a>';
$replacement='<nav class="topnav"><a href="/map">Map</a><a href="/#channels">Channels</a>';
$html=str_replace($needle,$replacement,$html);

if (empty($_GET['slug'])) {
    $html=preg_replace('~(<section class="hero">.*?</section>)~s','$1<!--CC_HOME_MAP-->',$html,1)??$html;

    $healthySlugs=[
        'main-beach','surfers-paradise','kurrawa','miami',
        'palm-beach','currumbin','tugun','kirra',
        'rainbow-bay','the-spit','tweed-river','kingscliff','cabarita'
    ];

    $views=[];
    foreach(cc_popular(7,100) as $row){$views[(string)$row['slug']]=(int)($row['views']??0);}

    $healthReady=false;$okStreamIds=[];
    try{
        $pdo=cc_db();
        if($pdo){
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

            $fresh=(int)$pdo->query("SELECT COUNT(*) FROM stream_health WHERE checked_at>=DATE_SUB(NOW(),INTERVAL 10 MINUTE)")->fetchColumn();

            if($fresh===0 && function_exists('curl_multi_init')){
                $marks=implode(',',array_fill(0,count($healthySlugs),'?'));
                $q=$pdo->prepare("SELECT s.id,s.stream_type,s.stream_url FROM streams s JOIN locations l ON l.id=s.location_id WHERE l.slug IN ($marks) AND s.active=1 AND s.stream_type IN ('hls','iframe') AND s.stream_url IS NOT NULL AND s.stream_url<>''");
                $q->execute($healthySlugs);$rows=$q->fetchAll();
                $mh=curl_multi_init();$handles=[];$started=[];
                foreach($rows as $r){
                    $ch=curl_init($r['stream_url']);
                    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>6,CURLOPT_USERAGENT=>'CostaCamsHealth/1.0']);
                    curl_multi_add_handle($mh,$ch);$handles[(int)$r['id']]=[$ch,$r];$started[(int)$r['id']]=microtime(true);
                }
                do{$mrc=curl_multi_exec($mh,$running);if($running)curl_multi_select($mh,.5);}while($running&&$mrc===CURLM_OK);
                $up=$pdo->prepare("INSERT INTO stream_health(stream_id,status,http_code,response_ms,checked_at,last_ok_at,consecutive_failures) VALUES(?,?,?,?,NOW(),?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),http_code=VALUES(http_code),response_ms=VALUES(response_ms),checked_at=NOW(),last_ok_at=IF(VALUES(status)='ok',NOW(),last_ok_at),consecutive_failures=IF(VALUES(status)='ok',0,consecutive_failures+1)");
                foreach($handles as $id=>[$ch,$r]){
                    $body=curl_multi_getcontent($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$ms=(int)round((microtime(true)-$started[$id])*1000);
                    $ok=$code>=200&&$code<400;if($ok&&$r['stream_type']==='hls')$ok=is_string($body)&&str_contains($body,'#EXTM3U');
                    $up->execute([$id,$ok?'ok':'down',$code,$ms,$ok?date('Y-m-d H:i:s'):null,$ok?0:1]);
                    curl_multi_remove_handle($mh,$ch);curl_close($ch);
                }
                curl_multi_close($mh);
            }

            $fresh=(int)$pdo->query("SELECT COUNT(*) FROM stream_health WHERE checked_at>=DATE_SUB(NOW(),INTERVAL 10 MINUTE)")->fetchColumn();
            if($fresh>0){
                $healthReady=true;
                foreach($pdo->query("SELECT stream_id FROM stream_health WHERE status='ok' AND checked_at>=DATE_SUB(NOW(),INTERVAL 10 MINUTE)")->fetchAll() as $r){$okStreamIds[(int)$r['stream_id']]=true;}
            }
        }
    }catch(Throwable $e){$healthReady=false;}

    $healthy=[];
    foreach($healthySlugs as $order=>$slug){
        $loc=cc_location($slug);if(!$loc)continue;
        $hasLive=false;
        foreach($loc['streams']??[] as $stream){
            if(!in_array($stream['stream_type']??'',['hls','iframe'],true)||empty($stream['stream_url']))continue;
            if(!$healthReady || !empty($okStreamIds[(int)($stream['id']??0)])){$hasLive=true;break;}
        }
        if(!$hasLive)continue;
        $loc['_views']=$views[$slug]??0;$loc['_editorial_order']=$order;$healthy[]=$loc;
    }

    $recommended=null;$recommendedStream=null;
    if($healthy){
        try{$recommended=$healthy[random_int(0,count($healthy)-1)];}catch(Throwable $e){$recommended=$healthy[array_rand($healthy)];}
        foreach($recommended['streams']??[] as $stream){
            if(!in_array($stream['stream_type']??'',['hls','iframe'],true)||empty($stream['stream_url']))continue;
            if(!$healthReady || !empty($okStreamIds[(int)($stream['id']??0)])){$recommendedStream=$stream;break;}
        }
    }

    $recommendHtml='';
    if($recommended && $recommendedStream){
        $rs=htmlspecialchars((string)$recommended['slug'],ENT_QUOTES,'UTF-8');
        $rn=htmlspecialchars((string)$recommended['name'],ENT_QUOTES,'UTF-8');
        $rr=htmlspecialchars((string)$recommended['region'],ENT_QUOTES,'UTF-8');
        $preview='';
        if(($recommendedStream['stream_type']??'')==='iframe'){
            $src=htmlspecialchars((string)$recommendedStream['stream_url'],ENT_QUOTES,'UTF-8');
            $preview='<iframe src="'.$src.'" loading="eager" allow="autoplay; fullscreen" allowfullscreen></iframe>';
        }else{
            $recommendedHlsUrl=(string)$recommendedStream['stream_url'];
            $preview='<video id="ccRecommendedVideo" muted autoplay playsinline controls></video>';
        }
        $recommendHtml='<aside class="cc-home-recommend"><div class="cc-rec-label"><span class="live-dot"></span>RECOMMENDED LIVE CAM</div><div class="cc-rec-preview">'.$preview.'</div><div class="cc-rec-body"><span class="tag">'.$rr.'</span><h3>'.$rn.'</h3><p>Random pick from cameras currently considered healthy. Reload the page for another beach.</p><a class="cc-map-btn" href="/'.$rs.'">Watch this camera →</a></div></aside>';
    }

    $mapSpotlight='<section class="cc-home-explore"><div class="cc-home-map-panel"><div class="cc-home-map-head"><div><span class="tag">LIVE CAMERA MAP</span><h2>Explore the coast.</h2></div><a href="/map">Open full map ↗</a></div><div class="cc-home-map-frame"><iframe src="/map-home.php" title="Interactive CostaCams camera map" loading="eager"></iframe></div></div>'.$recommendHtml.'</section>';
    $html=str_replace('<!--CC_HOME_MAP-->',$mapSpotlight,$html);

    usort($healthy,function($a,$b){$v=((int)$b['_views'])<=>((int)$a['_views']);return $v!==0?$v:(((int)$a['_editorial_order'])<=>((int)$b['_editorial_order']));});
    $healthy=array_slice($healthy,0,8);

    $cards='';
    foreach($healthy as $loc){
        $slug=htmlspecialchars((string)$loc['slug'],ENT_QUOTES,'UTF-8');$name=htmlspecialchars((string)$loc['name'],ENT_QUOTES,'UTF-8');$region=htmlspecialchars((string)$loc['region'],ENT_QUOTES,'UTF-8');
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
.cc-presence-bar{display:flex;justify-content:center;gap:8px;align-items:center;padding:7px 14px;background:#07110d;border-bottom:1px solid #183329;color:#bce7cf;font-size:12px}.cc-presence-dot,.cc-live-card:before{display:inline-block;width:7px;height:7px;border-radius:50%;background:#69e0a0;box-shadow:0 0 8px #69e0a0}.cc-channel-live{display:inline-flex;gap:8px;align-items:center;margin:2px 0 12px;padding:8px 12px;border:1px solid #244936;background:#09140f;border-radius:999px;color:#c7eed6;font-size:13px;font-weight:700}.cc-live-card{display:block;margin-top:8px;color:#8fd8aa;font-size:12px}.cc-live-card:before{content:'';margin-right:6px;width:6px;height:6px}.cc-presence-peak{color:#789c87;font-weight:500}.cc-home-explore{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(280px,.65fr);gap:14px;margin:4px 0 34px}.cc-home-map-panel,.cc-home-recommend{border:1px solid #273238;background:linear-gradient(180deg,#0e1214,#090b0c);border-radius:20px;overflow:hidden}.cc-home-map-head{display:flex;justify-content:space-between;align-items:end;gap:15px;padding:17px 18px}.cc-home-map-head h2{margin:3px 0 0;font-size:24px}.cc-home-map-head>a{color:#50dcff;font-size:13px;font-weight:800;white-space:nowrap}.cc-home-map-frame{height:430px;background:#111}.cc-home-map-frame iframe{width:100%;height:100%;border:0;display:block}.cc-home-recommend{display:flex;flex-direction:column}.cc-rec-label{padding:12px 14px;border-bottom:1px solid #222b2e;color:#9fd7b4;font-weight:800;font-size:11px;letter-spacing:.07em}.cc-rec-preview{aspect-ratio:16/10;background:#000;overflow:hidden}.cc-rec-preview video,.cc-rec-preview iframe{width:100%;height:100%;display:block;border:0;background:#000}.cc-rec-body{padding:16px}.cc-rec-body h3{font-size:24px;margin:5px 0}.cc-rec-body p{color:#9da9ae;font-size:13px;margin:0 0 16px}.cc-map-btn{display:inline-block;border:1px solid #50dcff;color:#b9f3ff;background:#0c2026;padding:10px 14px;border-radius:999px;font-weight:800;font-size:13px}.cc-map-btn:hover{background:#12313a}@media(max-width:900px){.cc-home-explore{grid-template-columns:1fr}.cc-home-recommend{display:grid;grid-template-columns:minmax(0,1fr) minmax(260px,.8fr)}.cc-rec-label{grid-column:1/-1}.cc-rec-preview{aspect-ratio:16/9}.cc-rec-body{align-self:center}}@media(max-width:650px){.cc-presence-bar{font-size:11px}.cc-channel-live{font-size:12px}.cc-home-map-frame{height:360px}.cc-home-map-head{align-items:flex-start;flex-direction:column}.cc-home-recommend{display:block}.cc-rec-preview{aspect-ratio:16/9}}
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
  function rid(n){
    const a=new Uint8Array(n);
    crypto.getRandomValues(a);
    return Array.from(a,b=>b.toString(16).padStart(2,'0')).join('');
  }
  function getStored(key,session){
    try{
      const store=session?sessionStorage:localStorage;
      let v=store.getItem(key);
      if(!v){v=rid(session?10:16);store.setItem(key,v);}
      return v;
    }catch(e){return rid(session?10:16);}
  }
  const visitor=getStored('ccVisitorId',false);
  const tab=getStored('ccTabId',true);

  function render(d){
    if(!d||!d.ok)return;
    const sn=document.getElementById('ccSiteNow');
    const sp=document.getElementById('ccSitePeak');
    if(sn)sn.textContent=d.site.now+' watching CostaCams now';
    if(sp)sp.textContent='· Peak today '+d.site.peak_today;

    if(channel){
      const n=(d.channels&&d.channels[channel])||0;
      const p=(d.channel_peaks&&d.channel_peaks[channel])||0;
      const cn=document.getElementById('ccChannelNow');
      const cp=document.getElementById('ccChannelPeak');
      if(cn)cn.textContent=n+' watching now';
      if(cp)cp.textContent='· Peak today '+p;
    }

    document.querySelectorAll('[data-live-slug]').forEach(el=>{
      const s=el.getAttribute('data-live-slug');
      const n=(d.channels&&d.channels[s])||0;
      el.textContent='● '+n+' watching now';
    });

    document.querySelectorAll('a.card').forEach(card=>{
      if(card.querySelector('[data-live-slug]'))return;
      const tag=card.querySelector('.tag');
      if(!tag||!tag.textContent.includes('LIVE'))return;
      let href=(card.getAttribute('href')||'').split(/[?#]/)[0];
      if(href.charAt(0)==='/')href=href.slice(1);
      if(!href)return;
      const el=document.createElement('span');
      el.className='cc-live-card';
      el.dataset.liveSlug=href;
      el.textContent='● '+(((d.channels&&d.channels[href])||0))+' watching now';
      card.appendChild(el);
    });
  }

  function showUnavailable(){
    const sn=document.getElementById('ccSiteNow');
    const cn=document.getElementById('ccChannelNow');
    if(sn)sn.textContent='Live viewer count unavailable';
    if(cn)cn.textContent='Viewer count unavailable';
  }

  async function beat(){
    try{
      const body=new URLSearchParams({action:'heartbeat',visitor:visitor,tab:tab,channel:channel});
      const r=await fetch('/presence.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
        body:body,
        cache:'no-store',
        credentials:'same-origin'
      });
      if(!r.ok){showUnavailable();return;}
      render(await r.json());
    }catch(e){showUnavailable();}
  }

  beat();
  setInterval(beat,25000);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)beat();});
})();
</script>
HTML;
$presenceJs=str_replace('__CHANNEL__',json_encode($channelSlug,JSON_UNESCAPED_SLASHES),$presenceJs);
$html=str_replace('</body>',$presenceJs.'</body>',$html);

if($recommendedHlsUrl){
    $recommendedJs='<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script><script>(function(){const v=document.getElementById("ccRecommendedVideo");if(!v)return;const src='.json_encode($recommendedHlsUrl,JSON_UNESCAPED_SLASHES).';if(window.Hls&&Hls.isSupported()){const h=new Hls({enableWorker:true});h.loadSource(src);h.attachMedia(v);h.on(Hls.Events.MANIFEST_PARSED,()=>v.play().catch(()=>{}));}else if(v.canPlayType("application/vnd.apple.mpegurl")){v.src=src;v.play().catch(()=>{});}})();</script>';
    $html=str_replace('</body>',$recommendedJs.'</body>',$html);
}

echo $html;