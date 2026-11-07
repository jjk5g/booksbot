<?php
declare(strict_types=1);
// One-time CLI-only calendar release. Never touch homepage static file if preflight fails.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
error_reporting(E_ALL);
$root='/home/u751177156/domains/costacams.com/public_html';
$stage=__DIR__;
$log=static function(string $x):void{echo $x."\n";};
$assert=static function(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);};
$rootRenderer=$root.'/render-stable.php';
$home=$root.'/cache/home.html';
$backup='/home/u751177156/.costacams-calendar-backups/'.date('Ymd-His');
$moduleNames=['coast-calendar.php','event.php'];
$old=[];
$promoted=false;
try {
    $assert(is_readable($home)&&filesize($home)>20000,'NO_GO current homepage safety cache missing/too small');
    $oldHome=file_get_contents($home);
    $assert(is_string($oldHome)&&str_contains($oldHome,'id="channels"')&&str_contains($oldHome,'id="surf-match"'),'NO_GO existing home lacks safety markers');
    $oldRenderer=file_get_contents($rootRenderer);
    $stageRenderer=file_get_contents($stage.'/render-stable.php');
    $assert(is_string($oldRenderer)&&is_string($stageRenderer),'NO_GO renderer unavailable');
    $assert(substr_count($oldRenderer,"echo \$html;")===1,'NO_GO renderer changed or ambiguous');
    $assert(!str_contains($oldRenderer,"// Optional calendar module."),'NO_GO already promoted');
    $start=strpos($stageRenderer,"// Optional calendar module.");
    $assert($start!==false,'NO_GO calendar insertion absent from stage');
    $tail=substr($stageRenderer,(int)$start);
    $assert(str_contains($tail,"cc_calendar_home()")&&str_contains($tail,"echo \$html;"),'NO_GO incomplete stage insertion');
    $anchor="\necho \$html;";
    $assert(substr_count($oldRenderer,$anchor)===1,'NO_GO live renderer anchor changed');
    $newRenderer=str_replace($anchor,"\n".$tail,$oldRenderer);
    // Required modules must be small and valid PHP; root remains untouched so far.
    foreach($moduleNames as $name){
        $file=$stage.'/'.$name;
        $assert(is_readable($file)&&filesize($file)>1000,'NO_GO missing stage '.$name);
        $old[$name]=is_file($root.'/'.$name)?file_get_contents($root.'/'.$name):null;
    }
    // Validate a stage render independently, while production stays read-only.
    $_GET=['nocache'=>'1'];ob_start();
    try{require $stage.'/render-stable.php';$stageHtml=(string)ob_get_clean();}
    catch(Throwable $e){ob_end_clean();throw new RuntimeException('NO_GO stage render '.get_class($e).': '.$e->getMessage());}
    $checkHtml=static function(string $html):bool {
        if(strlen($html)<30000)return false;
        foreach(['<!doctype html','</html>','cc-home-explore','id="channels"','id="surf-match"','id="events"','id="calendar"','id="ccCalData"','id="cc-cal-script"','Pick your beach'] as $marker){
            if(!str_contains($html,$marker))return false;
        }
        return true;
    };
    $assert($checkHtml($stageHtml),'NO_GO stage homepage validation');
    $log('PREFLIGHT_OK stage_bytes='.strlen($stageHtml));
    $assert(@mkdir($backup,0700,true)||is_dir($backup),'NO_GO private backup directory');
    $assert(file_put_contents($backup.'/render-stable.php',$oldRenderer,LOCK_EX)!==false,'NO_GO renderer backup');
    $assert(file_put_contents($backup.'/home.html',$oldHome,LOCK_EX)!==false,'NO_GO home backup');
    foreach($old as $name=>$bytes){if($bytes!==null)$assert(file_put_contents($backup.'/'.$name,$bytes,LOCK_EX)!==false,'NO_GO file backup '.$name);}
    foreach($moduleNames as $name){
        $tmp=$root.'/'.preg_replace('/\.php$/','.tmp-calendar-'.getmypid().'.php',$name);
        $bytes=file_get_contents($stage.'/'.$name);
        $assert(file_put_contents($tmp,$bytes,LOCK_EX)!==false,'module temp write '.$name);
        $assert(rename($tmp,$root.'/'.$name),'module atomic rename '.$name);
    }
    $temp=$root.'/render-stable.calendar-'.getmypid().'.tmp';
    $assert(file_put_contents($temp,$newRenderer,LOCK_EX)!==false,'renderer temp write');
    $assert(rename($temp,$rootRenderer),'renderer atomic rename');
    // Candidate output is generated before replacing the known-good static homepage.
    $_GET=['nocache'=>'1'];ob_start();
    try{require $rootRenderer;$candidate=(string)ob_get_clean();}
    catch(Throwable $e){ob_end_clean();throw new RuntimeException('prod render '.get_class($e).': '.$e->getMessage());}
    $assert($checkHtml($candidate),'production candidate validation');
    $cFile=$root.'/cache/home.calendar-'.getmypid().'.candidate';
    $assert(file_put_contents($cFile,$candidate,LOCK_EX)!==false,'write candidate');
    $assert(strlen($candidate)>30000&&str_contains($candidate,'id="calendar"'),'candidate incomplete');
    $assert(rename($cFile,$home),'atomic static homepage swap');
    $promoted=true;
    $assert(hash_file('sha256',$home)===hash('sha256',$candidate),'static homepage hash mismatch');
    $assert(str_contains(file_get_contents($rootRenderer),'cc_calendar_home()'),'production renderer marker missing');
    $log('CALENDAR_RELEASE_OK home_bytes='.strlen($candidate).' files=2 backup='.$backup);
} catch(Throwable $e) {
    $log('CALENDAR_RELEASE_BLOCKED '.$e->getMessage());
    // Rollback all touched files to their exact previous content. Never delete the old homepage.
    if(isset($oldRenderer)&&is_string($oldRenderer))@file_put_contents($rootRenderer,$oldRenderer,LOCK_EX);
    foreach($old as $name=>$bytes){
        if($bytes===null)@unlink($root.'/'.$name);
        else @file_put_contents($root.'/'.$name,$bytes,LOCK_EX);
    }
    if($promoted&&isset($oldHome)&&is_string($oldHome))@file_put_contents($home,$oldHome,LOCK_EX);
    exit(1);
}
