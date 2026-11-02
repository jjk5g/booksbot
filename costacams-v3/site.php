<?php
declare(strict_types=1);
ob_start();
require __DIR__.'/index.php';
$html=ob_get_clean();

// Main navigation: keep the map available everywhere.
$needle='<nav class="topnav"><a href="/#channels">Channels</a>';
$replacement='<nav class="topnav"><a href="/#channels">Channels</a><a href="/map">Map</a>';
$html=str_replace($needle,$replacement,$html);

// Homepage hero shelf: only promote live feeds we deliberately trust.
// Actual 7-day views still determine the order inside this healthy shortlist.
if (empty($_GET['slug'])) {
    $healthySlugs=[
        'main-beach','surfers-paradise','kurrawa','miami',
        'palm-beach','currumbin','tugun','kirra',
        'rainbow-bay','the-spit','tweed-river','kingscliff','cabarita'
    ];

    $views=[];
    foreach(cc_popular(7,100) as $row){
        $views[(string)$row['slug']]=(int)($row['views']??0);
    }

    $healthy=[];
    foreach($healthySlugs as $order=>$slug){
        $loc=cc_location($slug);
        if(!$loc) continue;
        $hasLive=false;
        foreach($loc['streams']??[] as $stream){
            if(in_array($stream['stream_type']??'',['hls','iframe'],true) && !empty($stream['stream_url'])){$hasLive=true;break;}
        }
        if(!$hasLive) continue;
        $loc['_views']=$views[$slug]??0;
        $loc['_editorial_order']=$order;
        $healthy[]=$loc;
    }

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
        $cards.='<a class="card" href="/'.$slug.'"><span class="tag"><span class="live-dot"></span>LIVE · '.$region.'</span><h3>'.$name.'</h3><span class="muted">'.$viewText.'Watch →</span></a>';
    }

    if($cards!==''){
        $section='<section class="section"><div class="section-head"><h2>Popular live cameras</h2><span class="muted">Working feeds first · popularity last 7 days</span></div><div class="grid">'.$cards.'</div></section>';
        $pattern='~<section class="section"><div class="section-head"><h2>Most watched</h2>.*?</section>~s';
        $html=preg_replace($pattern,$section,$html,1)??$html;
    }
}

echo $html;
