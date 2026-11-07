<?php
declare(strict_types=1);

// Dynamic fallback renderer used only when a pre-generated static page is not
// available or a query string explicitly requires a fresh response.
$slug=preg_replace('/[^a-z0-9-]/','',strtolower((string)($_GET['slug']??'')));
$_GET['nocache']='1';
ob_start();
require __DIR__.'/app.php';
$html=ob_get_clean();
$isHome=$slug==='';

// The homepage recommendation deliberately uses a curated HLS-only pool. The
// server-side health probe can be blocked by upstream CDNs even while a stream
// plays normally in a visitor's browser, so it must not leave the UI stuck on
// “Picking a live beach…”. Low-quality iframe feeds such as Kingscliff are not
// eligible for the hero recommendation.
$preferred=['main-beach','surfers-paradise','kurrawa','palm-beach','currumbin','tugun','kirra','rainbow-bay','the-spit'];
$fallback=[];
if($isHome && isset($locations) && is_array($locations)){
    $bySlug=[];foreach($locations as $l)$bySlug[(string)($l['slug']??'')]=$l;
    foreach($preferred as $s){
        $l=$bySlug[$s]??null;if(!$l)continue;
        foreach(($l['streams']??[]) as $st){
            if(($st['stream_type']??'')==='hls' && !empty($st['stream_url'])){
                $fallback[]=['slug'=>$s,'name'=>(string)$l['name'],'region'=>(string)$l['region'],'type'=>'hls','url'=>(string)$st['stream_url']];
                break;
            }
        }
    }
    $json=json_encode($fallback,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?:'[]';
    $html=preg_replace('~const candidates=\[[^;]*\];~s','const candidates='.$json.';',$html,1)??$html;
    $html=str_replace('Only cameras currently considered healthy are included.','Rotates through selected high-quality live coastal cameras.',$html);

    $fallbackJs=<<<HTML
<script id="cc-recommended-fallback">
(function(){
 const cams={$json};if(!cams.length)return;
 function loadHls(cb){if(window.Hls)return cb();const s=document.createElement('script');s.src='https://cdn.jsdelivr.net/npm/hls.js@latest';s.async=true;s.onload=cb;document.head.appendChild(s);}
 function show(i){
  const c=cams[i%cams.length],box=document.getElementById('ccRecPreview'),name=document.getElementById('ccRecName'),region=document.getElementById('ccRecRegion'),link=document.getElementById('ccRecLink');if(!box)return;
  if(name)name.textContent=c.name;if(region)region.textContent=c.region;if(link){link.href='/'+c.slug;link.textContent='Watch this camera →';}
  const v=document.createElement('video');v.muted=true;v.autoplay=true;v.playsInline=true;v.controls=true;v.preload='metadata';box.innerHTML='';box.appendChild(v);
  let moved=false;const next=()=>{if(moved)return;moved=true;setTimeout(()=>show(i+1),120);};v.addEventListener('error',next,{once:true});
  const start=()=>{if(v.canPlayType('application/vnd.apple.mpegurl')){v.src=c.url;v.play().catch(()=>{});}else loadHls(()=>{if(window.Hls&&Hls.isSupported()){const h=new Hls({enableWorker:true,maxBufferLength:10});h.loadSource(c.url);h.attachMedia(v);h.on(Hls.Events.MANIFEST_PARSED,()=>v.play().catch(()=>{}));h.on(Hls.Events.ERROR,(e,d)=>{if(d&&d.fatal)next();});}else next();});};
  start();setTimeout(()=>{if(v.readyState<2)next();},5500);
 }
 setTimeout(()=>{const box=document.getElementById('ccRecPreview'),v=box&&box.querySelector('video');if(!v||v.readyState<2)show(Math.floor(Math.random()*cams.length));},4200);
})();
</script>
HTML;
    $html=str_replace('</body>',$fallbackJs.'</body>',$html);
}

// Google Analytics 4. Measurement ID recovered from the previous CostaCams site.
$ga=<<<'HTML'
<script async src="https://www.googletagmanager.com/gtag/js?id=G-6WLK1YD519"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-6WLK1YD519');</script>
HTML;
$html=str_replace('</head>',$ga.'</head>',$html);
// Fast first-party analytics also works when the page itself is served from static cache.
$html=str_replace('</head>','<script defer src="/cc-analytics.js?v=20261107-01"></script></head>',$html);
$html=str_replace('<meta name="description"','<meta name="robots" content="index,follow,max-image-preview:large"><meta name="description"',$html);

$seoCss=<<<'HTML'
<style id="cc-seo-style">
.cc-seo{margin:46px 0 22px;padding:22px;border:1px solid #20282b;border-radius:16px;background:#090c0d;color:#c4ced2}.cc-seo h2{margin:0 0 10px;font-size:23px}.cc-seo h3{margin:18px 0 7px;font-size:16px}.cc-seo p{color:#aeb9bd;max-width:1000px}.cc-seo-links{display:flex;flex-wrap:wrap;gap:8px;margin-top:13px}.cc-seo-links a{border:1px solid #2d393d;border-radius:999px;padding:7px 10px;color:#cceff7;font-size:12px}.cc-seo details{border-top:1px solid #20282b;padding:9px 0}.cc-seo summary{cursor:pointer;font-weight:700}
</style>
HTML;
$html=str_replace('</head>',$seoCss.'</head>',$html);

if($isHome){
    $seo='<section class="cc-seo" aria-label="About CostaCams"><h2>Live beach cameras and coastal conditions from the Gold Coast to Byron Bay</h2><p>CostaCams is built for surfers, swimmers, beachgoers, locals and visitors who want a fast way to check the coast before leaving home. Browse live beach and surf cameras, current weather, wind, wave and swell conditions, tide information, shark-safety updates and coastal events across the Gold Coast, Tweed Coast and Byron Shire.</p><p>Use the live camera map to compare beaches, check whether conditions look crowded or clean, and open a beach page for more detailed marine and weather data. CostaCams combines practical coastal information in one place so people planning a surf, swim, beach walk or day trip can make a better-informed choice.</p><div class="cc-seo-links"><a href="/map">Live beach camera map</a><a href="/surfers-paradise">Surfers Paradise cam</a><a href="/burleigh">Burleigh Heads</a><a href="/currumbin">Currumbin Alley</a><a href="/kirra">Kirra</a><a href="/byron-bay">Byron Bay</a></div><h3>Frequently asked questions</h3><details><summary>Who is CostaCams for?</summary><p>Surfers, swimmers, beachgoers, travellers and locals checking live coastal conditions between the Gold Coast and Byron Bay.</p></details><details><summary>How often are conditions updated?</summary><p>Weather and marine data are refreshed automatically throughout the day, while camera and shark-safety systems are checked on separate recurring schedules.</p></details></section>';
    $schema=['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'CostaCams','url'=>'https://costacams.com/','description'=>'Live beach cameras, surf conditions, weather, tides, shark-safety updates and coastal events from the Gold Coast through Tweed Coast to Byron Bay.','audience'=>['@type'=>'Audience','audienceType'=>'Surfers, swimmers, beachgoers, locals and coastal travellers']];
}else{
    $name=isset($current['name'])?(string)$current['name']:ucwords(str_replace('-',' ',$slug));
    $region=isset($current['region'])?(string)$current['region']:'Gold Coast, Tweed Coast and Byron region';
    $en=htmlspecialchars($name,ENT_QUOTES,'UTF-8');$er=htmlspecialchars($region,ENT_QUOTES,'UTF-8');
    $links='';
    if(isset($locations)&&is_array($locations)){
        $near=[];foreach($locations as $l){if(($l['region']??'')===$region && ($l['slug']??'')!==$slug)$near[]=$l;}
        foreach(array_slice($near,0,5) as $l)$links.='<a href="/'.htmlspecialchars((string)$l['slug'],ENT_QUOTES,'UTF-8').'">'.htmlspecialchars((string)$l['name'],ENT_QUOTES,'UTF-8').'</a>';
    }
    $seo='<section class="cc-seo" aria-label="'.$en.' beach information"><h2>'.$en.' live beach cam, surf and coastal conditions</h2><p>Check '.$en.' in '.$er.' before heading to the beach. This page brings together the live or nearest available beach camera, surf and swell conditions, wind, weather, rain, sea temperature, tide modelling and shark-safety context in one place.</p><p>It is designed for surfers deciding whether to paddle out, swimmers and families checking beach conditions, and visitors planning a coastal trip. Camera images are a visual guide only; always follow lifeguards, official beach closures and local safety advice when you arrive.</p><div class="cc-seo-links"><a href="/map">Open live camera map</a>'.$links.'</div><h3>About '.$en.'</h3><details><summary>Can I use this page to check surf conditions?</summary><p>Yes. CostaCams combines the available camera view with wave, swell, wind and weather data so you can quickly compare current coastal conditions.</p></details><details><summary>Are shark alerts and beach closures guaranteed to be complete?</summary><p>No. CostaCams provides verified safety context where available, but official lifeguard, council and SharkSmart warnings remain the primary source for beach-safety decisions.</p></details></section>';
    $schema=['@context'=>'https://schema.org','@type'=>'WebPage','name'=>$name.' live beach cam and conditions','url'=>'https://costacams.com/'.$slug,'description'=>'Live or nearest available beach camera, surf, weather, wind, swell, tides and coastal safety context for '.$name.', '.$region.'.','about'=>['@type'=>'Place','name'=>$name,'address'=>['@type'=>'PostalAddress','addressRegion'=>$region]],'audience'=>['@type'=>'Audience','audienceType'=>'Surfers, swimmers, beachgoers and coastal travellers']];
}
$html=str_replace('</main>',$seo.'</main>',$html);
$html=str_replace('</head>','<script type="application/ld+json">'.json_encode($schema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP).'</script></head>',$html);

echo $html;
