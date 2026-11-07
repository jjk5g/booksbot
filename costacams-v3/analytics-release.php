<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
error_reporting(E_ALL);
$root='/home/u751177156/domains/costacams.com/public_html';
$stage=__DIR__;
$backup='/home/u751177156/.costacams-analytics-backups/'.date('Ymd-His').'-'.getmypid();
$liveHome=$root.'/cache/home.html';
$existing=[];$applied=[];$homePromoted=false;$oldHome=null;
$abort=static function(bool $pass,string $message):void{if(!$pass)throw new RuntimeException($message);};
$get=static function(string $path):string {
  $s=@file_get_contents($path);
  if(!is_string($s))throw new RuntimeException('Unreadable '.$path);
  return $s;
};
$segment=static function(string $source,string $begin,string $end):string {
  $a=strpos($source,$begin);if($a===false)throw new RuntimeException('Staging insertion start mismatch');
  $b=strpos($source,$end,$a);if($b===false||$b<=$a)throw new RuntimeException('Staging insertion end mismatch');
  return substr($source,$a,$b-$a);
};
$atomic=static function(string $path,string $body):void {
  $tmp=dirname($path).'/.cc-analytics-'.getmypid().'-'.basename($path).'.tmp';
  if(file_put_contents($tmp,$body,LOCK_EX)===false)throw new RuntimeException('Temp write '.basename($path));
  @chmod($tmp,0644);
  if(!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('Atomic write '.basename($path));}
};
$patch=static function(string $old,string $needle,string $add):string {
  if(substr_count($old,$needle)!==1)throw new RuntimeException('Live file drift/anchor mismatch');
  if(str_contains($old,'cc-analytics.js'))throw new RuntimeException('Already instrumented; safe stop');
  return str_replace($needle,$needle.$add,$old);
};
try{
  $oldHome=$get($liveHome);
  $abort(strlen($oldHome)>30000 && str_contains($oldHome,'id="channels"')
      && str_contains($oldHome,'id="surf-match"')&&str_contains($oldHome,'id="calendar"'),
      'Known-good static index missing');
  $surfHash=is_file($root.'/surf-match-beta-v13.php')?hash_file('sha256',$root.'/surf-match-beta-v13.php'):'';
  $abort($surfHash!=='','Surf Match v13 missing; do not promote');
  $files=['render-fast.php','surf-match.php','map-clean.php','event.php','admin.php','page-warm.php'];
  $prepared=[];
  foreach($files as $name)$existing[$name]=$get($root.'/'.$name);
  foreach(['cc-analytics.js','analytics-collect.php','analytics-dashboard.php'] as $name){
      $existing[$name]=is_file($root.'/'.$name)?$get($root.'/'.$name):null;
      $abort($existing[$name]===null,'New file already exists; no overwrite allowed: '.$name);
      $prepared[$name]=$get($stage.'/'.$name);
  }
  $rfStart='// Fast first-party analytics';
  $rf=$segment($get($stage.'/render-fast.php'),$rfStart,"\n\$html=str_replace('<meta name=\"description\"'");
  $rfAnchor=<<<'TXT'
$html=str_replace('</head>',$ga.'</head>',$html);
TXT;
  $prepared['render-fast.php']=$patch($existing['render-fast.php'],$rfAnchor,"\n".$rf);
  $surfStage=$get($stage.'/surf-match.php');
  $surf=$segment($surfStage, '    $mobileCss.=', "\n    if(stripos(\$page,");
  $surfAnchor=<<<'TXT'
    $mobileCss='<link rel="stylesheet" href="/surf-match-mobile-v13.css?v=20261103-1" media="(max-width: 920px)">';
TXT;
  $prepared['surf-match.php']=$patch($existing['surf-match.php'],$surfAnchor,"\n".$surf);
  $mapStage=$get($stage.'/map-clean.php');
  $map=$segment($mapStage,'// Track the full map',"\necho \$html;");
  $mapAnchor="\necho \$html;";
  $abort(substr_count($existing['map-clean.php'],$mapAnchor)===1 && !str_contains($existing['map-clean.php'],'cc-analytics.js'),'Map live anchor mismatch');
  $prepared['map-clean.php']=str_replace($mapAnchor,"\n".$map.$mapAnchor,$existing['map-clean.php']);
  $eventStage=$get($stage.'/event.php');
  $eventAnchor=<<<'TXT'
<meta property="og:url" content="<?=$esc($canonical)?>">
TXT;
  $eventBlock=$segment($eventStage,"\n<script async src=\"https://www.googletagmanager.com/gtag/js?id=G-6WLK1YD519\">","\n<style>");
  $prepared['event.php']=$patch($existing['event.php'],$eventAnchor,$eventBlock);
  $adminStage=$get($stage.'/admin.php');
  $adminAnchor=<<<'TXT'
<?php else:?><input type="hidden" id="csrf" value="<?=ae(csrf())?>">
TXT;
  $adminBlock=$segment($adminStage,'<div class="box" style="background:#0d2028"', "\n<div class=\"box\"><h2>Open camera reports");
  $prepared['admin.php']=$patch($existing['admin.php'],$adminAnchor,$adminBlock."\n");
  // Keep the static homepage safety net: its warmer must preserve calendar and analytics.
  $warmNeedle="'cc-home-explore','id=\"surf-match\"','see the coast.','pick your beach.'";
  $abort(substr_count($existing['page-warm.php'],$warmNeedle)===1
      && !str_contains($existing['page-warm.php'],"'cc-analytics.js'"),'Warm validator mismatch');
  $prepared['page-warm.php']=str_replace($warmNeedle,$warmNeedle.",'id=\"calendar\"','cc-analytics.js'",$existing['page-warm.php']);
  foreach($prepared as $name=>$body){
    $abort(strlen($body)>200,'Invalid candidate '.$name);
    if(str_ends_with($name,'.php')){
      $tmp=tempnam(sys_get_temp_dir(),'cc-lint-');
      $abort($tmp!==false,'Cannot temp lint');
      file_put_contents($tmp,$body);
      $out=[];$rc=0;exec('php -l '.escapeshellarg($tmp).' 2>&1',$out,$rc);@unlink($tmp);
      $abort($rc===0,'PHP syntax blocked '.$name.' '.implode(' ',$out));
    }
  }
  // Backup everything before any public changes, including homepage.
  $abort(@mkdir($backup,0700,true)||is_dir($backup),'Cannot establish private backup');
  $abort(file_put_contents($backup.'/home.html',$oldHome,LOCK_EX)!==false,'Home backup failed');
  foreach($existing as $name=>$old){
    if($old!==null)$abort(file_put_contents($backup.'/'.$name,$old,LOCK_EX)!==false,'Backup failed '.$name);
  }
  echo "ANALYTICS_PREFLIGHT_OK files=".count($prepared)." backup=$backup\n";
  // Initialize additive tables before linking tracking to them. Executed in separate PHP process.
  $out=[];$rc=0;
  exec('php '.escapeshellarg($stage.'/analytics-migrate.php').' 2>&1',$out,$rc);
  $abort($rc===0 && str_contains(implode(' ',$out),'ANALYTICS_MIGRATION_OK'),
      'Schema migration blocked: '.implode(' ',$out));
  foreach($prepared as $name=>$body){
    $atomic($root.'/'.$name,$body);$applied[]=$name;
  }
  // The old static index remains online until its entire new version has passed checks.
  $_GET=['nocache'=>'1'];ob_start();
  try{require $root.'/render-stable.php';$candidate=(string)ob_get_clean();}
  catch(Throwable $e){ob_end_clean();throw new RuntimeException('Production candidate crashed: '.get_class($e).' '.$e->getMessage());}
  $checks=['doctype'=>'<!doctype html','full'=>'</html>','hero'=>'Pick your beach',
   'map'=>'cc-home-explore','beaches'=>'id="channels"','surf'=>'id="surf-match"',
   'calendar'=>'id="calendar"','analytics'=>'cc-analytics.js','ga'=>'G-6WLK1YD519'];
  $abort(strlen($candidate)>30000,'Candidate too small');
  foreach($checks as $label=>$marker)$abort(str_contains($candidate,$marker),'Candidate missing '.$label);
  $abort(hash_file('sha256',$root.'/surf-match-beta-v13.php')===$surfHash,'Surf engine changed unexpectedly');
  $atomic($liveHome,$candidate);$homePromoted=true;
  $abort(hash_file('sha256',$liveHome)===hash('sha256',$candidate),'Home hash mismatch');
  echo 'ANALYTICS_RELEASE_OK static_home_bytes='.strlen($candidate).' files='.count($applied).' surf_engine_unchanged=yes backup='.$backup."\n";
}catch(Throwable $e){
  foreach(array_reverse($applied) as $name){
    if($existing[$name]===null)@unlink($root.'/'.$name);
    else @file_put_contents($root.'/'.$name,$existing[$name],LOCK_EX);
  }
  if($homePromoted && $oldHome!==null)@file_put_contents($liveHome,$oldHome,LOCK_EX);
  echo 'ANALYTICS_RELEASE_BLOCKED '.get_class($e).' '.$e->getMessage()."\n";
  exit(1);
}
