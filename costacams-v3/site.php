<?php
declare(strict_types=1);
ob_start();
require __DIR__.'/index.php';
$html=ob_get_clean();

$needle='<nav class="topnav"><a href="/#channels">Channels</a>';
$replacement='<nav class="topnav"><a href="/#channels">Channels</a><a href="/map">Map</a>';
$html=str_replace($needle,$replacement,$html);

if (empty($_GET['slug'])) {
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

            // Fallback self-check if scheduled monitoring has not populated fresh results yet.
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

    usort($healthy,function($a,$b){$v=((int)$b['_views'])<=>((int)$a['_views']);return $v!==0?$v:(((int)$a['_editorial_order'])<=>((int)$b['_editorial_order']));});
    $healthy=array_slice($healthy,0,8);

    $cards='';
    foreach($healthy as $loc){
        $slug=htmlspecialchars((string)$loc['slug'],ENT_QUOTES,'UTF-8');$name=htmlspecialchars((string)$loc['name'],ENT_QUOTES,'UTF-8');$region=htmlspecialchars((string)$loc['region'],ENT_QUOTES,'UTF-8');
        $viewText=((int)$loc['_views']>0)?number_format((int)$loc['_views']).' views · ':'';
        $cards.='<a class="card" href="/'.$slug.'"><span class="tag"><span class="live-dot"></span>LIVE · '.$region.'</span><h3>'.$name.'</h3><span class="muted">'.$viewText.'Watch →</span></a>';
    }

    if($cards!==''){
        $sub=$healthReady?'Verified working feeds · popularity last 7 days':'Reliable live feeds · popularity last 7 days';
        $section='<section class="section"><div class="section-head"><h2>Popular live cameras</h2><span class="muted">'.$sub.'</span></div><div class="grid">'.$cards.'</div></section>';
        $html=preg_replace('~<section class="section"><div class="section-head"><h2>Most watched</h2>.*?</section>~s',$section,$html,1)??$html;
    }
}

echo $html;
