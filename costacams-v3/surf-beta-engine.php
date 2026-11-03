<?php
declare(strict_types=1);

function cc_surf_compass(?float $deg): string {
    if($deg===null)return '—';
    $p=['N','NE','E','SE','S','SW','W','NW'];
    return $p[(int)round(fmod($deg+360,360)/45)%8];
}
function cc_surf_val(array $a,string $k,int $i): ?float {
    return isset($a[$k][$i])&&is_numeric($a[$k][$i])?(float)$a[$k][$i]:null;
}
function cc_surf_day_index(array $daily,string $date): ?int {
    $i=array_search($date,$daily['time']??[],true);return $i===false?null:(int)$i;
}
function cc_surf_uv_est(float $uvMax,int $epoch,int $sunrise,int $sunset): float {
    if($uvMax<=0||$epoch<=$sunrise||$epoch>=$sunset)return 0.0;
    $x=($epoch-$sunrise)/max(1,$sunset-$sunrise);
    return round($uvMax*pow(max(0.0,sin(M_PI*$x)),1.35),1);
}
function cc_surf_angle_diff(float $a,float $b): float {
    $d=abs(fmod(($a-$b)+540,360)-180);return $d;
}
function cc_surf_haversine_km(float $lat1,float $lon1,float $lat2,float $lon2): float {
    $r=6371.0;$dlat=deg2rad($lat2-$lat1);$dlon=deg2rad($lon2-$lon1);
    $a=sin($dlat/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dlon/2)**2;
    return 2*$r*asin(min(1,sqrt($a)));
}
function cc_surf_travel_minutes(float $olat,float $olon,float $lat,float $lon): int {
    $km=cc_surf_haversine_km($olat,$olon,$lat,$lon)*1.28;
    return max(5,(int)round(($km/58)*60+5));
}
function cc_surf_load_spots(PDO $pdo): array {
    $locations=$pdo->query("SELECT id,slug,name,region,lat,lon,timezone FROM locations WHERE active=1 ORDER BY sort_order,id")->fetchAll();
    $cache=[];
    foreach($pdo->query("SELECT cache_key,payload,updated_at FROM forecast_cache WHERE cache_key LIKE 'weather:%' OR cache_key LIKE 'marine:%'")->fetchAll() as $r){
        $j=json_decode((string)$r['payload'],true);if(is_array($j))$cache[(string)$r['cache_key']]=['data'=>$j,'updated_at'=>(string)$r['updated_at']];
    }
    $streams=[];
    foreach($pdo->query("SELECT l.slug,s.stream_type,s.stream_url,s.name,s.priority FROM locations l JOIN streams s ON s.location_id=l.id WHERE l.active=1 AND s.active=1 ORDER BY l.id,s.priority,s.id")->fetchAll() as $r){
        $slug=(string)$r['slug'];if(isset($streams[$slug]))continue;
        if(in_array((string)$r['stream_type'],['hls','iframe'],true)&&!empty($r['stream_url']))$streams[$slug]=['type'=>(string)$r['stream_type'],'url'=>(string)$r['stream_url'],'name'=>(string)$r['name']];
    }
    $right=['burleigh','currumbin','kirra','greenmount','snapper-rocks','the-pass','wategos'];
    $left=['duranbah'];
    $point=['burleigh','currumbin','kirra','greenmount','snapper-rocks','the-pass','wategos'];
    $river=['the-spit','tweed-river','brunswick-heads'];
    $spots=[];
    foreach($locations as $loc){
        $slug=(string)$loc['slug'];$wc=$cache['weather:'.$slug]??null;$mc=$cache['marine:'.$slug]??null;
        if(!$wc||!$mc)continue;$w=$wc['data'];$m=$mc['data'];
        $tzName=(string)($loc['timezone']?:'Australia/Brisbane');try{$tz=new DateTimeZone($tzName);}catch(Throwable $e){$tz=new DateTimeZone('Australia/Brisbane');}
        $wh=$w['hourly']??[];$mh=$m['hourly']??[];$widx=[];foreach(($wh['time']??[]) as $i=>$t)$widx[(string)$t]=(int)$i;
        $hours=[];
        foreach(($mh['time']??[]) as $mi=>$t){
            $wi=$widx[(string)$t]??null;if($wi===null)continue;
            try{$dt=new DateTimeImmutable((string)$t,$tz);}catch(Throwable $e){continue;}
            $date=$dt->format('Y-m-d');$di=cc_surf_day_index($w['daily']??[],$date);
            $uv=cc_surf_val($wh,'uv_index',$wi);
            $uvMax=($di!==null&&isset($w['daily']['uv_index_max'][$di])&&is_numeric($w['daily']['uv_index_max'][$di]))?(float)$w['daily']['uv_index_max'][$di]:0.0;
            if($uv===null){$sr=$ss=null;if($di!==null){try{$sr=new DateTimeImmutable((string)($w['daily']['sunrise'][$di]??''),$tz);$ss=new DateTimeImmutable((string)($w['daily']['sunset'][$di]??''),$tz);}catch(Throwable $e){}}
                $uv=($sr&&$ss)?cc_surf_uv_est($uvMax,$dt->getTimestamp(),$sr->getTimestamp(),$ss->getTimestamp()):0.0;
            }
            $wave=cc_surf_val($mh,'wave_height',$mi);$period=cc_surf_val($mh,'wave_period',$mi);
            $swellH=cc_surf_val($mh,'swell_wave_height',$mi);$swellP=cc_surf_val($mh,'swell_wave_period',$mi);$swellD=cc_surf_val($mh,'swell_wave_direction',$mi);
            $wind=cc_surf_val($wh,'wind_speed_10m',$wi);$windD=cc_surf_val($wh,'wind_direction_10m',$wi);$rain=cc_surf_val($wh,'precipitation_probability',$wi);
            $hours[]=['epoch'=>$dt->getTimestamp(),'local'=>$dt->format('D j M, g:i a'),'date'=>$date,'hour'=>(int)$dt->format('G'),'wave_height'=>$wave,'wave_period'=>$period,'swell_height'=>$swellH,'swell_period'=>$swellP,'swell_direction'=>$swellD,'swell_compass'=>cc_surf_compass($swellD),'wind_speed'=>$wind,'wind_direction'=>$windD,'wind_compass'=>cc_surf_compass($windD),'rain_probability'=>$rain,'uv'=>$uv,'uv_max'=>$uvMax,'energy_kwm'=>($wave!==null&&$period!==null)?round(0.49*$wave*$wave*$period,2):null];
        }
        $hand='both';if(in_array($slug,$right,true))$hand='right';elseif(in_array($slug,$left,true))$hand='left';
        $spots[]=['slug'=>$slug,'name'=>(string)$loc['name'],'region'=>(string)$loc['region'],'lat'=>(float)$loc['lat'],'lon'=>(float)$loc['lon'],'timezone'=>$tzName,'handedness'=>$hand,'break_type'=>in_array($slug,$point,true)?'point':(in_array($slug,$river,true)?'river':'beach'),'offshore_from'=>in_array((string)$loc['region'],['Gold Coast','Tweed Coast'],true)?275:260,'camera'=>$streams[$slug]??null,'forecast_updated_at'=>max((string)$wc['updated_at'],(string)$mc['updated_at']),'hours'=>$hours];
    }
    return $spots;
}
function cc_surf_nearest_hour(array $spot,int $epoch): ?array {
    $best=null;$d=PHP_INT_MAX;foreach($spot['hours']??[] as $h){$x=abs((int)$h['epoch']-$epoch);if($x<$d){$d=$x;$best=$h;}}return $best;
}
function cc_surf_profile_defaults(): array {
    return ['mode'=>'simple','level'=>'competent','frequency'=>'few','board'=>'midlength','board_length'=>7.0,'wave_target'=>0.9,'max_wave'=>1.3,'power'=>'medium','handedness'=>'any','wind_tolerance'=>18,'rain_tolerance'=>'prefer_dry','time_mode'=>'arrive','session_hour'=>17.0,'day_offset'=>0,'email_frequency'=>'morning','origin_lat'=>null,'origin_lon'=>null];
}
function cc_surf_score(array $spot,array $h,array $p): array {
    $score=72.0;$reasons=[];$wave=(float)($h['wave_height']??0);$period=(float)($h['wave_period']??0);$wind=(float)($h['wind_speed']??0);$rain=(float)($h['rain_probability']??0);$energy=(float)($h['energy_kwm']??0);
    $target=(float)($p['wave_target']??0.9);$max=(float)($p['max_wave']??1.3);$diff=abs($wave-$target);$score-=min(34,$diff*24);if($diff<0.22){$score+=8;$reasons[]='wave size suits your preference';}
    if($wave>$max){$pen=min(45,($wave-$max)*35);$score-=$pen;$reasons[]='bigger than your stated comfort zone';}
    $level=(string)($p['level']??'competent');if($level==='learning'&&$wave>1.0)$score-=18;if($level==='learning'&&$period>10)$score-=8;if($level==='very_good'&&$wave>=1.0)$score+=6;
    $freq=(string)($p['frequency']??'few');if($freq==='0'&&$wave>0.9)$score-=12;if($freq==='many')$score+=3;
    if($period>=10){$score+=10;$reasons[]='longer-period swell';}elseif($period>=8){$score+=5;}elseif($period<6.5){$score-=10;$reasons[]='short-period waves';}
    $power=(string)($p['power']??'medium');$idealE=$power==='soft'?1.0:($power==='powerful'?5.0:2.5);$score-=min(20,abs($energy-$idealE)*3.2);
    $windTol=(float)($p['wind_tolerance']??18);if($wind>$windTol){$score-=min(28,($wind-$windTol)*1.7);$reasons[]='wind stronger than you prefer';}
    if(isset($h['wind_direction'])&&$h['wind_direction']!==null){$ad=cc_surf_angle_diff((float)$h['wind_direction'],(float)$spot['offshore_from']);if($ad<45){$score+=10;$reasons[]='favourable offshore wind';}elseif($ad>120&&$wind>8){$score-=10;$reasons[]='more onshore wind';}}
    if(($p['rain_tolerance']??'prefer_dry')==='prefer_dry'&&$rain>50)$score-=8;
    $pref=(string)($p['handedness']??'any');if($pref!=='any'&&$spot['handedness']!=='both'){if($spot['handedness']===$pref)$score+=5;else $score-=6;}
    $board=(string)($p['board']??'midlength');$bl=(float)($p['board_length']??7.0);if(in_array($board,['softboard','longboard'],true)&&$wave<=1.1)$score+=7;if($board==='shortboard'&&$wave>=0.7&&$period>=7)$score+=6;if($bl>=8&&$wave<0.7)$score+=4;if($bl<=6.5&&$wave<0.5)$score-=5;
    return ['score'=>(int)round(max(0,min(100,$score))),'reasons'=>array_slice(array_values(array_unique($reasons)),0,3)];
}
function cc_surf_rank(array $spots,array $profile,int $selectedEpoch): array {
    $originOk=is_numeric($profile['origin_lat']??null)&&is_numeric($profile['origin_lon']??null);$rank=[];
    foreach($spots as $spot){
        $travel=$originOk?cc_surf_travel_minutes((float)$profile['origin_lat'],(float)$profile['origin_lon'],(float)$spot['lat'],(float)$spot['lon']):null;
        $evalEpoch=$selectedEpoch;if(($profile['time_mode']??'arrive')==='leave'&&$travel!==null)$evalEpoch+=$travel*60;
        $h=cc_surf_nearest_hour($spot,$evalEpoch);if(!$h)continue;$sc=cc_surf_score($spot,$h,$profile);
        $rank[]=['spot'=>$spot,'hour'=>$h,'score'=>$sc['score'],'reasons'=>$sc['reasons'],'travel_minutes'=>$travel,'eval_epoch'=>$evalEpoch];
    }
    usort($rank,fn($a,$b)=>$b['score']<=>$a['score']);return $rank;
}
function cc_surf_selected_epoch(array $profile): int {
    $tz=new DateTimeZone('Australia/Brisbane');$now=new DateTimeImmutable('now',$tz);$date=$now->modify('+'.max(0,(int)($profile['day_offset']??0)).' day')->format('Y-m-d');$hour=(float)($profile['session_hour']??17);$h=(int)floor($hour);$m=(int)round(($hour-$h)*60);$dt=new DateTimeImmutable(sprintf('%s %02d:%02d:00',$date,$h,$m),$tz);return $dt->getTimestamp();
}
