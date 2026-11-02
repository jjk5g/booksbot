<?php
declare(strict_types=1);

const COSTACAMS_CONFIG = '/home/u751177156/.costacams-private.php';

function cc_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $cfg = [];
    if (is_file(COSTACAMS_CONFIG)) {
        $loaded = require COSTACAMS_CONFIG;
        if (is_array($loaded)) $cfg = $loaded;
    }
    return $cfg;
}

function cc_db(): ?PDO {
    static $pdo = null;
    static $attempted = false;
    if ($attempted) return $pdo;
    $attempted = true;
    $cfg = cc_config();
    if (empty($cfg['db_name']) || empty($cfg['db_user']) || empty($cfg['db_pass'])) return null;
    try {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;dbname='.$cfg['db_name'].';charset=utf8mb4',
            $cfg['db_user'],
            $cfg['db_pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        cc_init_schema($pdo);
        cc_seed($pdo);
    } catch (Throwable $e) {
        error_log('CostaCams DB: '.$e->getMessage());
        $pdo = null;
    }
    return $pdo;
}

function cc_init_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS locations (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      slug VARCHAR(100) NOT NULL UNIQUE,
      name VARCHAR(160) NOT NULL,
      region VARCHAR(100) NOT NULL,
      lat DECIMAL(9,6) NOT NULL,
      lon DECIMAL(9,6) NOT NULL,
      timezone VARCHAR(80) NOT NULL DEFAULT 'Australia/Brisbane',
      seo_description VARCHAR(255) DEFAULT NULL,
      active TINYINT(1) NOT NULL DEFAULT 1,
      featured TINYINT(1) NOT NULL DEFAULT 0,
      sort_order INT NOT NULL DEFAULT 100,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS streams (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      location_id INT UNSIGNED NOT NULL,
      name VARCHAR(160) NOT NULL,
      stream_type ENUM('hls','iframe','external','none') NOT NULL DEFAULT 'none',
      stream_url TEXT NULL,
      source_name VARCHAR(120) DEFAULT NULL,
      source_url TEXT NULL,
      camera_note VARCHAR(255) DEFAULT NULL,
      active TINYINT(1) NOT NULL DEFAULT 1,
      priority INT NOT NULL DEFAULT 100,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uq_stream_location_name (location_id,name),
      CONSTRAINT fk_stream_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_views (
      location_id INT UNSIGNED NOT NULL,
      view_date DATE NOT NULL,
      views INT UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY(location_id,view_date),
      CONSTRAINT fk_views_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS stream_reports (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      location_id INT UNSIGNED NULL,
      stream_id INT UNSIGNED NULL,
      issue VARCHAR(80) NOT NULL,
      details VARCHAR(500) DEFAULT NULL,
      status ENUM('open','resolved','ignored') NOT NULL DEFAULT 'open',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      resolved_at TIMESTAMP NULL,
      INDEX idx_reports_status_created(status,created_at),
      CONSTRAINT fk_report_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
      CONSTRAINT fk_report_stream FOREIGN KEY (stream_id) REFERENCES streams(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS forecast_cache (
      cache_key VARCHAR(190) PRIMARY KEY,
      payload MEDIUMTEXT NOT NULL,
      expires_at DATETIME NOT NULL,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS news_items (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      slug VARCHAR(150) NOT NULL UNIQUE,
      category ENUM('news','event') NOT NULL DEFAULT 'news',
      title VARCHAR(220) NOT NULL,
      summary TEXT NOT NULL,
      body MEDIUMTEXT NOT NULL,
      source_name VARCHAR(150) DEFAULT NULL,
      source_url TEXT NULL,
      published_at DATETIME DEFAULT NULL,
      event_date DATETIME DEFAULT NULL,
      active TINYINT(1) NOT NULL DEFAULT 1,
      sort_order INT NOT NULL DEFAULT 100,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_tokens (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      token_hash CHAR(64) NOT NULL UNIQUE,
      expires_at DATETIME NOT NULL,
      used_at DATETIME NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function cc_seed_channels(): array {
    $h = 'https://d1nm4r8e5x1rwd.cloudfront.net/cw/';
    return [
      ['the-spit','The Spit / Seaway','Gold Coast',-27.946600,153.426000,1,10,
        [['Seaway Spit','hls',$h.'seawayspitcamera.stream/playlist.m3u8','Coastal camera',null,'The Spit / Seaway live view']]],
      ['main-beach','Main Beach','Gold Coast',-27.978000,153.430000,1,20,
        [['Main Beach','hls',$h.'mainbeachcamera.stream/playlist.m3u8','Coastal camera',null,'Main Beach live view']]],
      ['narrowneck','Narrowneck','Gold Coast',-27.990000,153.429000,0,25,
        [['Narrowneck High','hls',$h.'narrowneckhighcamera.stream/playlist.m3u8','Coastal camera',null,'High-angle Narrowneck view'],['Artificial Reef','hls',$h.'artreefcamera.stream/playlist.m3u8','Coastal camera',null,'Artificial reef view']]],
      ['surfers-paradise','Surfers Paradise','Gold Coast',-28.002700,153.431000,1,30,
        [['Surfers Paradise Main','hls',$h.'surfersparadisecamera.stream/playlist.m3u8','Coastal camera',null,'Main Surfers Paradise view']]],
      ['northcliffe','Northcliffe','Gold Coast',-28.013000,153.431000,0,35,
        [['Northcliffe / Surfers High','hls',$h.'surfersparadisehighcamera.stream/playlist.m3u8','Coastal camera',null,'High-angle Northcliffe / Surfers view']]],
      ['kurrawa','Kurrawa Beach','Gold Coast',-28.030000,153.434000,1,40,
        [['Kurrawa','hls',$h.'kurrawacamera.stream/playlist.m3u8','Coastal camera',null,'Kurrawa beachfront']]],
      ['broadbeach','Broadbeach','Gold Coast',-28.028000,153.432000,1,45,
        [['Broadbeach / Kurrawa view','hls',$h.'kurrawacamera.stream/playlist.m3u8','Coastal camera',null,'Nearest live beachfront view is Kurrawa, in Broadbeach']]],
      ['mermaid-beach','Mermaid Beach','Gold Coast',-28.045000,153.434000,0,50,
        [['Mermaid Beach','none',null,'CostaCams',null,'Forecast channel while a stable live camera is verified']]],
      ['miami','Miami','Gold Coast',-28.062000,153.441000,1,55,
        [['Miami','hls',$h.'miamicamera.stream/playlist.m3u8','Coastal camera',null,'Miami beachfront']]],
      ['north-burleigh','North Burleigh','Gold Coast',-28.075000,153.446000,1,60,
        [['North Burleigh / Burleigh High','hls',$h.'burleighhighcamera.stream/playlist.m3u8','Coastal camera',null,'High view covering the northern Burleigh area']]],
      ['burleigh','Burleigh Heads','Gold Coast',-28.090000,153.453000,1,65,
        [['Burleigh Headland','hls',$h.'burleighheadscamera.stream/playlist.m3u8','Coastal camera',null,'Headland view'],['Burleigh High','hls',$h.'burleighhighcamera.stream/playlist.m3u8','Coastal camera',null,'High-angle view']]],
      ['tallebudgera','Tallebudgera','Gold Coast',-28.103000,153.459000,0,70,
        [['Tallebudgera','hls',$h.'tallebudgeracamera.stream/playlist.m3u8','Coastal camera',null,'Tallebudgera beachfront']]],
      ['palm-beach','Palm Beach','Gold Coast',-28.118000,153.469000,1,75,
        [['Palm Beach','hls',$h.'palmbeachqldcamera.stream/playlist.m3u8','Coastal camera',null,'Palm Beach main view']]],
      ['currumbin','Currumbin Alley','Gold Coast',-28.130000,153.484000,1,80,
        [['Currumbin','hls',$h.'currumbincamera.stream/playlist.m3u8','Coastal camera',null,'Currumbin Alley / north Palm Beach']]],
      ['tugun','Tugun','Gold Coast',-28.143000,153.493000,1,85,
        [['Tugun','hls',$h.'tuguncamera.stream/playlist.m3u8','Coastal camera',null,'Tugun / Bilinga view']]],
      ['bilinga','Bilinga','Gold Coast',-28.154000,153.503000,0,90,
        [['Bilinga / Tugun','hls',$h.'tuguncamera.stream/playlist.m3u8','Coastal camera',null,'Nearest live view covers Tugun / Bilinga']]],
      ['kirra','Kirra','Gold Coast',-28.168000,153.527000,1,95,
        [['Kirra','hls',$h.'kirracamera.stream/playlist.m3u8','Coastal camera',null,'Kirra live view']]],
      ['coolangatta','Coolangatta','Gold Coast',-28.169000,153.536000,1,100,
        [['Coolangatta / Rainbow Bay','hls',$h.'rainbowbaycamera.stream/playlist.m3u8','Coastal camera',null,'Rainbow Bay camera covers the Coolangatta point system']]],
      ['greenmount','Greenmount','Gold Coast',-28.165000,153.546000,1,105,
        [['Greenmount / Rainbow Bay','hls',$h.'rainbowbaycamera.stream/playlist.m3u8','Coastal camera',null,'Rainbow Bay camera covers Greenmount']]],
      ['rainbow-bay','Rainbow Bay','Gold Coast',-28.164000,153.551000,0,110,
        [['Rainbow Bay','hls',$h.'rainbowbaycamera.stream/playlist.m3u8','Coastal camera',null,'Rainbow Bay live view']]],
      ['snapper-rocks','Snapper Rocks','Gold Coast',-28.163000,153.551500,1,115,
        [['Snapper / Rainbow Bay view','hls',$h.'rainbowbaycamera.stream/playlist.m3u8','Coastal camera',null,'Live view across Rainbow Bay toward the Superbank / Snapper area']]],
      ['duranbah','Duranbah / D-bah','Tweed Coast',-28.166500,153.558000,1,120,
        [['Duranbah','none',null,'CostaCams','https://www.surfline.com/surf-report/duranbah/','Forecast channel while a stable embeddable Duranbah feed is verified']]],
      ['tweed-river','Tweed River / Froggies','Tweed Coast',-28.169000,153.558000,0,125,
        [['Tweed River Bar','hls',$h.'tweedriverbarcamera.stream/playlist.m3u8','Coastal camera',null,'Tweed River bar view'],['Tweed River Static','hls',$h.'tweedriverstaticcamera.stream/playlist.m3u8','Coastal camera',null,'Static Tweed River view']]],
      ['fingal','Fingal Head / Fingal Beach','Tweed Coast',-28.204000,153.571000,1,130,
        [['Fingal','none',null,'CostaCams',null,'Forecast channel while a stable embeddable live feed is verified']]],
      ['dreamtime','Dreamtime Beach','Tweed Coast',-28.218000,153.574000,1,135,
        [['Dreamtime','none',null,'CostaCams',null,'Forecast channel while a stable embeddable live feed is verified']]],
      ['kingscliff','Kingscliff / Cudgen','Tweed Coast',-28.257000,153.579000,1,140,
        [['Cudgen coastal bar','iframe','https://widget.coastalcoms.com/video/73a52fac-d291-42be-a1f1-dd859c5dd28f','Transport for NSW','https://www.nsw.gov.au/driving-boating-and-transport/using-waterways-boating-and-transport-information/conditions-weather-and-tides/webcams/cudgen','Official Cudgen / Kingscliff coastal bar webcam']]],
      ['cabarita','Cabarita Beach','Tweed Coast',-28.332000,153.570000,1,145,
        [['Cabarita Beach','iframe','https://embed.cdn-surfline.com/cams/5d482f6d6fe8473cf7ae1ce8/85f8446ad566cdab93b699ef13a83238ee563ac8','Surfline / The Beach Cabarita','https://www.thebeachcabarita.com.au/beach-cam/','Publicly embedded Cabarita beach cam']]],
      ['ocean-shores','Ocean Shores / New Brighton','Byron Shire',-28.525000,153.548000,1,150,
        [['Ocean Shores','none',null,'CostaCams',null,'Forecast channel while a stable embeddable live feed is verified']]],
      ['brunswick-heads','Brunswick Heads','Byron Shire',-28.540000,153.551000,0,155,
        [['Brunswick Heads','external',null,'Transport for NSW','https://www.nsw.gov.au/driving-boating-and-transport/using-waterways-boating-and-transport-information/conditions-weather-and-tides/webcams/brunswick-heads','Official coastal bar webcam; direct embed is being verified']]],
      ['byron-bay','Byron Bay','Byron Shire',-28.642000,153.615000,1,160,
        [['Byron Bay Main Beach','external',null,'Surfline','https://www.surfline.com/surf-report/main-beach-byron-bay/5fe0f932f167970e1d4c9516','A live Smart Cam exists; embeddable player is being verified']]],
      ['byron-main-beach','Main Beach, Byron Bay','Byron Shire',-28.642500,153.615500,1,165,
        [['Main Beach Byron','external',null,'Surfline','https://www.surfline.com/surf-report/main-beach-byron-bay/5fe0f932f167970e1d4c9516','A live Smart Cam exists; embeddable player is being verified']]],
      ['the-pass','The Pass, Byron Bay','Byron Shire',-28.635000,153.638000,1,170,
        [['The Pass','none',null,'CostaCams',null,'Forecast channel while a stable embeddable live feed is verified']]],
      ['wategos','Wategos Beach','Byron Shire',-28.638000,153.638500,1,175,
        [['Wategos','none',null,'CostaCams',null,'Forecast channel while a stable embeddable live feed is verified']]],
      ['the-wreck','The Wreck, Byron Bay','Byron Shire',-28.641000,153.612000,0,180,
        [['The Wreck','none',null,'CostaCams',null,'Forecast channel while a stable embeddable live feed is verified']]],
    ];
}

function cc_seed_news(): array {
    return [
      ['abb-tweed-coast-2026','event','Australian Boardriders Battle – NSW North Qualifier','The NSW North regional qualifier lands on the Tweed Coast on 7 November 2026.','Australia’s biggest grassroots boardriders series brings club teams together in a fast teams format. The NSW North qualifier is scheduled for the Tweed Coast on Saturday 7 November 2026, making it one of the most relevant upcoming competitive surf events for CostaCams viewers.','Surfing Australia','https://surfingaustralia.com/sandon-point-boardriders-club-claims-victory-at-australian-boardriders-battle-nsw-south-qualifier/',null,'2026-11-07 07:00:00',10],
      ['lets-go-surfing-currumbin','event','Let’s Go Surfing – Currumbin','An inclusive surf day at Currumbin on Sunday 8 November 2026.','Disabled Surfers Association Gold Coast is hosting an inclusive surfing day at Len Wort Park, Currumbin. Participant check-in starts from 8:30am and surfing is expected to run from around 9am until about 12:30pm.','Humanitix / Disabled Surfers Association Gold Coast','https://events.humanitix.com/let-s-go-surfing-november-2026',null,'2026-11-08 08:30:00',20],
      ['ocean-heroes-currumbin','event','Ocean Heroes – Currumbin Alley','Free supported surfing sessions at Currumbin Alley on 21 November 2026.','Ocean Heroes will run supported 30-minute surf sessions between 9:00am and 1:30pm at Currumbin Alley. Boards, wetsuits, lifejackets and instructors are provided for registered participants.','Humanitix / Ocean Heroes','https://events.humanitix.com/ocean-heroes-currumbin-alley-21st-nov-2026',null,'2026-11-21 09:00:00',30],
      ['burleigh-boardriders-abb-win','news','Burleigh Boardriders win the Queensland ABB qualifier','Burleigh Boardriders claimed the Queensland regional qualifier in October.','Burleigh Boardriders won the Queensland regional qualifier of the Australian Boardriders Battle on the Sunshine Coast in October 2026, adding another strong result for one of the Gold Coast’s best-known clubs.','Surfing Australia','https://surfingaustralia.com/news/events/','2026-10-12 12:00:00',null,40],
      ['tweed-longboard-titles-2026','news','Australian Longboard Titles wrap up on the Tweed Coast','National champions were crowned across divisions on the Tweed Coast.','The 2026 Australian Longboard Titles concluded on the Tweed Coast with national champions crowned across age divisions, reinforcing the region’s role as a major Australian longboarding destination.','Surfing Australia','https://surfingaustralia.com/news/events/','2026-07-31 12:00:00',null,50],
      ['sharksmart-gold-coast-drones','news','Expanded SharkSmart drone patrols across Gold Coast beaches','Queensland’s SharkSmart drone program now monitors more Gold Coast beaches.','Queensland’s shark-spotting drone program operates at a growing list of Gold Coast beaches including Main Beach, Surfers Paradise, Northcliffe, Broadbeach, Kurrawa, Burleigh, Kirra, Coolangatta and Rainbow Bay. If a potentially dangerous shark is spotted, lifeguards can evacuate the water and temporarily close the beach.','Queensland Government','https://www.dpi.qld.gov.au/initiatives/sharksmart/equipment/drones','2026-10-01 12:00:00',null,60],
    ];
}

function cc_seed(PDO $pdo): void {
    $locStmt = $pdo->prepare("INSERT IGNORE INTO locations(slug,name,region,lat,lon,timezone,seo_description,active,featured,sort_order) VALUES(?,?,?,?,?,'Australia/Brisbane',?,?,?,?)");
    $streamStmt = $pdo->prepare("INSERT IGNORE INTO streams(location_id,name,stream_type,stream_url,source_name,source_url,camera_note,active,priority) VALUES(?,?,?,?,?,?,?,?,?)");
    foreach (cc_seed_channels() as $row) {
        [$slug,$name,$region,$lat,$lon,$featured,$sort,$streams] = $row;
        $seo = "Live beach camera, surf forecast, weather, tides and coastal conditions for $name.";
        $locStmt->execute([$slug,$name,$region,$lat,$lon,$seo,1,$featured,$sort]);
        $q = $pdo->prepare("SELECT id FROM locations WHERE slug=?");
        $q->execute([$slug]);
        $locationId = (int)$q->fetchColumn();
        foreach ($streams as $i=>$s) {
            [$streamName,$type,$url,$sourceName,$sourceUrl,$note] = $s;
            $streamStmt->execute([$locationId,$streamName,$type,$url,$sourceName,$sourceUrl,$note,1,$i+1]);
        }
    }
    $newsStmt = $pdo->prepare("INSERT IGNORE INTO news_items(slug,category,title,summary,body,source_name,source_url,published_at,event_date,active,sort_order) VALUES(?,?,?,?,?,?,?,?,?,1,?)");
    foreach (cc_seed_news() as $n) {
        [$slug,$cat,$title,$summary,$body,$source,$url,$published,$event,$sort] = $n;
        $newsStmt->execute([$slug,$cat,$title,$summary,$body,$source,$url,$published,$event,$sort]);
    }
}

function cc_fallback_locations(): array {
    $out = [];
    $id = 1;
    foreach (cc_seed_channels() as $row) {
        [$slug,$name,$region,$lat,$lon,$featured,$sort,$streams] = $row;
        $mapped=[];
        foreach($streams as $i=>$s){$mapped[]=['id'=>$i+1,'name'=>$s[0],'stream_type'=>$s[1],'stream_url'=>$s[2],'source_name'=>$s[3],'source_url'=>$s[4],'camera_note'=>$s[5],'active'=>1,'priority'=>$i+1];}
        $out[] = ['id'=>$id++, 'slug'=>$slug,'name'=>$name,'region'=>$region,'lat'=>$lat,'lon'=>$lon,'featured'=>$featured,'sort_order'=>$sort,'streams'=>$mapped];
    }
    return $out;
}

function cc_locations(): array {
    $pdo = cc_db();
    if (!$pdo) return cc_fallback_locations();
    $locations = $pdo->query("SELECT * FROM locations WHERE active=1 ORDER BY sort_order,name")->fetchAll();
    $s = $pdo->prepare("SELECT * FROM streams WHERE location_id=? AND active=1 ORDER BY priority,id");
    foreach ($locations as &$loc) {
        $s->execute([$loc['id']]);
        $loc['streams'] = $s->fetchAll();
    }
    return $locations;
}

function cc_location(string $slug): ?array { foreach (cc_locations() as $loc) if ($loc['slug'] === $slug) return $loc; return null; }

function cc_popular(int $days=7, int $limit=8): array {
    $pdo = cc_db();
    if (!$pdo) return array_slice(array_filter(cc_locations(), fn($l)=>(int)$l['featured']===1),0,$limit);
    $sql = "SELECT l.*, COALESCE(SUM(v.views),0) AS views FROM locations l LEFT JOIN daily_views v ON v.location_id=l.id AND v.view_date>=DATE_SUB(CURDATE(),INTERVAL ? DAY) WHERE l.active=1 GROUP BY l.id ORDER BY views DESC,l.featured DESC,l.sort_order ASC LIMIT ".(int)$limit;
    $st=$pdo->prepare($sql); $st->execute([$days]); return $st->fetchAll();
}

function cc_record_view(array $loc): void {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $key = 'viewed_'.$loc['slug'].'_'.date('Y-m-d');
    if (!empty($_SESSION[$key])) return;
    $_SESSION[$key]=1;
    $pdo=cc_db(); if(!$pdo) return;
    $st=$pdo->prepare("INSERT INTO daily_views(location_id,view_date,views) VALUES(?,CURDATE(),1) ON DUPLICATE KEY UPDATE views=views+1");
    $st->execute([(int)$loc['id']]);
}

function cc_cache_get(string $key): ?array {
    $pdo=cc_db();
    if ($pdo) {
        $st=$pdo->prepare("SELECT payload FROM forecast_cache WHERE cache_key=? AND expires_at>NOW()"); $st->execute([$key]); $j=$st->fetchColumn(); if ($j) return json_decode($j,true);
    }
    $f=sys_get_temp_dir().'/cc_'.sha1($key).'.json'; if(is_file($f) && filemtime($f)>time()-900){$j=@file_get_contents($f);if($j)return json_decode($j,true);} return null;
}

function cc_cache_put(string $key,array $data,int $ttl=900): void {
    $json=json_encode($data); $pdo=cc_db();
    if($pdo){$st=$pdo->prepare("INSERT INTO forecast_cache(cache_key,payload,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL ? SECOND)) ON DUPLICATE KEY UPDATE payload=VALUES(payload),expires_at=VALUES(expires_at)");$st->execute([$key,$json,$ttl]);}
    @file_put_contents(sys_get_temp_dir().'/cc_'.sha1($key).'.json',$json);
}

function cc_fetch_json(string $url,string $cacheKey,int $ttl=900): ?array {
    $cached=cc_cache_get($cacheKey); if($cached) return $cached;
    $ctx=stream_context_create(['http'=>['timeout'=>5,'user_agent'=>'CostaCams/3.0 (+https://costacams.com)']]); $raw=@file_get_contents($url,false,$ctx); if(!$raw)return null;
    $data=json_decode($raw,true); if(!is_array($data)||isset($data['error']))return null; cc_cache_put($cacheKey,$data,$ttl); return $data;
}

function cc_forecast(array $loc): array {
    $lat=(float)$loc['lat']; $lon=(float)$loc['lon']; $tz=rawurlencode((string)($loc['timezone']??'Australia/Brisbane'));
    $wurl='https://api.open-meteo.com/v1/forecast?latitude='.$lat.'&longitude='.$lon.'&current=temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,rain,showers,weather_code,cloud_cover,wind_speed_10m,wind_direction_10m,wind_gusts_10m&hourly=precipitation_probability,weather_code,wind_speed_10m,wind_direction_10m,wind_gusts_10m&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,precipitation_sum,rain_sum,showers_sum,sunrise,sunset,uv_index_max,wind_speed_10m_max,wind_gusts_10m_max,wind_direction_10m_dominant&timezone='.$tz.'&forecast_days=7';
    $murl='https://marine-api.open-meteo.com/v1/marine?latitude='.$lat.'&longitude='.$lon.'&current=wave_height,wave_direction,wave_period,swell_wave_height,swell_wave_direction,swell_wave_period,sea_surface_temperature,sea_level_height_msl&hourly=wave_height,wave_direction,wave_period,swell_wave_height,swell_wave_direction,swell_wave_period,sea_level_height_msl,sea_surface_temperature&daily=wave_height_max,wave_direction_dominant,wave_period_max,swell_wave_height_max,swell_wave_direction_dominant,swell_wave_period_max&timezone='.$tz.'&forecast_days=7';
    return ['weather'=>cc_fetch_json($wurl,'weather:'.$loc['slug'],900),'marine'=>cc_fetch_json($murl,'marine:'.$loc['slug'],900)];
}

function cc_weather_text(?int $code): string {
    return match(true) {$code===0=>'Clear',in_array($code,[1,2])=>'Mostly clear',$code===3=>'Overcast',in_array($code,[45,48])=>'Fog',in_array($code,[51,53,55,56,57])=>'Drizzle',in_array($code,[61,63,65,66,67,80,81,82])=>'Rain / showers',in_array($code,[95,96,99])=>'Thunderstorm',default=>'Variable'};
}
function cc_compass($deg): string { if($deg===null||$deg==='')return '—';$dirs=['N','NE','E','SE','S','SW','W','NW'];return $dirs[(int)round(((float)$deg)/45)%8]; }
function cc_tides(?array $marine): array {
    if(!$marine||empty($marine['hourly']['time'])||empty($marine['hourly']['sea_level_height_msl']))return [];
    $times=$marine['hourly']['time'];$h=$marine['hourly']['sea_level_height_msl'];$today=date('Y-m-d');$out=[];
    for($i=1;$i<count($h)-1;$i++){if(strpos((string)$times[$i],$today)!==0||$h[$i]===null)continue;$type=null;if($h[$i]>$h[$i-1]&&$h[$i]>$h[$i+1])$type='High';if($h[$i]<$h[$i-1]&&$h[$i]<$h[$i+1])$type='Low';if($type)$out[]=['type'=>$type,'time'=>substr((string)$times[$i],11,5),'height'=>(float)$h[$i]];}return array_slice($out,0,4);
}
function cc_moon_info(?DateTimeImmutable $date=null): array {
    $date=$date?:new DateTimeImmutable('now',new DateTimeZone('Australia/Brisbane'));$knownNew=new DateTimeImmutable('2000-01-06 18:14:00',new DateTimeZone('UTC'));$utc=$date->setTimezone(new DateTimeZone('UTC'));$days=($utc->getTimestamp()-$knownNew->getTimestamp())/86400;$synodic=29.53058867;$age=fmod($days,$synodic);if($age<0)$age+=$synodic;$illum=(1-cos(2*M_PI*$age/$synodic))/2*100;
    $phase=match(true){$age<1.85=>'New Moon',$age<5.54=>'Waxing Crescent',$age<9.23=>'First Quarter',$age<12.92=>'Waxing Gibbous',$age<16.61=>'Full Moon',$age<20.30=>'Waning Gibbous',$age<23.99=>'Last Quarter',$age<27.68=>'Waning Crescent',default=>'New Moon'};$toFull=fmod((14.76529434-$age+$synodic),$synodic);$toNew=$synodic-$age;if($toNew<0.15)$toNew=0;return ['phase'=>$phase,'age'=>$age,'illumination'=>$illum,'next_full'=>$date->modify('+'.round($toFull,2).' days'),'next_new'=>$date->modify('+'.round($toNew,2).' days')];
}
function cc_shark_info(array $loc): array {
    if(str_contains((string)$loc['region'],'Gold Coast')){$monitored=in_array($loc['slug'],['main-beach','surfers-paradise','northcliffe','broadbeach','kurrawa','burleigh','north-burleigh','kirra','coolangatta','greenmount','rainbow-bay']);return ['status'=>$monitored?'SharkSmart monitoring area':'Check local beach warnings','detail'=>$monitored?'Queensland SharkSmart drone/control program operates at or near this beach. CostaCams does not claim “no sharks”; always follow lifeguard closures and warnings.':'Queensland SharkSmart operates shark-control measures across many Gold Coast beaches. Follow lifeguard warnings and beach closures.','source'=>'Queensland SharkSmart'];}
    return ['status'=>'NSW SharkSmart alert region','detail'=>'NSW SharkSmart provides real-time tagged-shark, drone, SMART drumline and beach-safety alerts for this coast. Live alert ingestion is not yet exposed as a public API, so CostaCams links the status to the official alert service rather than inventing a “no alert” result.','source'=>'NSW SharkSmart'];
}
function cc_news(string $category='all',int $limit=12): array {
    $pdo=cc_db();if(!$pdo){$rows=[];$id=1;foreach(cc_seed_news() as $n){[$slug,$cat,$title,$summary,$body,$source,$url,$published,$event,$sort]=$n;if($category!=='all'&&$category!==$cat)continue;$rows[]=['id'=>$id++,'slug'=>$slug,'category'=>$cat,'title'=>$title,'summary'=>$summary,'body'=>$body,'source_name'=>$source,'source_url'=>$url,'published_at'=>$published,'event_date'=>$event,'sort_order'=>$sort];}return array_slice($rows,0,$limit);} $sql="SELECT * FROM news_items WHERE active=1";$params=[];if($category!=='all'){$sql.=" AND category=?";$params[]=$category;}$sql.=" ORDER BY CASE WHEN category='event' AND event_date>=NOW() THEN 0 ELSE 1 END, COALESCE(event_date,published_at) DESC,sort_order ASC LIMIT ".(int)$limit;$st=$pdo->prepare($sql);$st->execute($params);return $st->fetchAll();
}
function cc_send_mail(string $subject,string $body,?string $replyTo=null): bool {
    $cfg=cc_config();$to=$cfg['alert_email']??'';if(!$to||!filter_var($to,FILTER_VALIDATE_EMAIL))return false;$headers="From: CostaCams <noreply@costacams.com>\r\n";$headers.="Reply-To: ".($replyTo&&filter_var($replyTo,FILTER_VALIDATE_EMAIL)?$replyTo:'noreply@costacams.com')."\r\n";return @mail($to,$subject,$body,$headers);
}
