<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}

$src=__DIR__;
$dst=dirname(__DIR__,2);
$stamp=date('Ymd-His');
$backupDir=$dst.'/.deploy-backups/'.$stamp;
$homeCache=$dst.'/cache/home.html';

$files=[
  'render-stable.php'=>'render-stable.php',
  'page-warm.php'=>'page-warm.php',
  'surf-match.php'=>'surf-match.php',
  'surf-match-beta-v13.php'=>'surf-match-beta-v13.php',
  'surf-match-beta-v5.php'=>'surf-match-beta-v5.php',
  'surf-match-beta-v4.php'=>'surf-match-beta-v4.php',
  'surf-match-beta-v3.php'=>'surf-match-beta-v3.php',
  'surf-match-beta-v2.php'=>'surf-match-beta-v2.php',
  'surf-beta-v5-api.php'=>'surf-beta-v5-api.php',
  'surf-beta-engine.php'=>'surf-beta-engine.php',
];

function fail_deploy(string $message,int $code=1): never {
  fwrite(STDERR,"SAFE_DEPLOY_BLOCKED: $message\n");exit($code);
}
function php_lint(string $file): array {
  $cmd=escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1';
  exec($cmd,$out,$code);return [$code===0,implode("\n",$out)];
}
function fetch_check(string $url,array $markers,int $minBytes=1200): array {
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_FOLLOWLOCATION=>true,
    CURLOPT_CONNECTTIMEOUT=>5,
    CURLOPT_TIMEOUT=>30,
    CURLOPT_USERAGENT=>'CostaCamsSafeDeploy/1.1',
    CURLOPT_ENCODING=>'',
    CURLOPT_HTTPHEADER=>['Cache-Control: no-cache','Pragma: no-cache'],
  ]);
  $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  if(!is_string($body))$body='';
  if($code<200||$code>=400)return [false,"HTTP $code $err",$body];
  if(strlen($body)<$minBytes)return [false,'body too small '.strlen($body),$body];
  $lower=strtolower($body);
  foreach(['fatal error','parse error','uncaught error','uncaught exception','surf match is recovering'] as $bad){if(str_contains($lower,$bad))return [false,"error marker $bad",$body];}
  foreach($markers as $marker){if(!str_contains($body,$marker))return [false,"missing marker $marker",$body];}
  return [true,'ok',$body];
}
function restore_backup(string $backupDir,string $dst,array $files,string $homeBackup,string $homeCache): void {
  foreach($files as $to){$b=$backupDir.'/'.$to;if(is_file($b))@copy($b,$dst.'/'.$to);}
  if(is_file($homeBackup))@copy($homeBackup,$homeCache);
}

// 1) Candidate source must be syntactically valid before any production write.
foreach($files as $from=>$to){
  $path=$src.'/'.$from;
  if(!is_file($path))fail_deploy("missing candidate $from");
  if(str_ends_with($from,'.php')){[$ok,$detail]=php_lint($path);if(!$ok)fail_deploy("lint failed $from: $detail");}
}
echo "preflight_lint=ok\n";

// 2) Staging/beta must render the exact features being promoted.
// Surf Match runs first because its wrapper validates/repairs the private runtime
// DB configuration; only after that do we test the database-backed homepage.
$nonce=time();
[$ok,$why]=fetch_check('https://beta.costacams.com/surf-match.php?preflight='.$nonce,['SURF MATCH BETA v13','All beaches','ccv13Drive'],18000);
if(!$ok)fail_deploy("beta Surf Match failed: $why");
[$ok,$why]=fetch_check('https://beta.costacams.com/?nocache=1&preflight='.$nonce,['<title>CostaCams','id="channels"','cc-home-explore','id="surf-match"'],20000);
if(!$ok)fail_deploy("beta homepage failed: $why");
echo "preflight_http=ok\n";

// 3) Snapshot production and stage replacement files beside their targets.
if(!is_dir($backupDir)&&!@mkdir($backupDir,0700,true)&&!is_dir($backupDir))fail_deploy('cannot create backup directory');
foreach($files as $from=>$to){
  $dest=$dst.'/'.$to;if(is_file($dest)&&!@copy($dest,$backupDir.'/'.$to))fail_deploy("cannot back up $to");
  $new=$dst.'/.deploy-new-'.$to;
  if(!@copy($src.'/'.$from,$new))fail_deploy("cannot stage $to");
  if(str_ends_with($to,'.php')){[$lintOk,$detail]=php_lint($new);if(!$lintOk)fail_deploy("staged lint failed $to: $detail");}
}
$homeBackup=$backupDir.'/home.html';if(is_file($homeCache))@copy($homeCache,$homeBackup);

// 4) Promote dependencies first; public entry points last. rename() is atomic per file.
$order=[];foreach(array_values($files) as $to){if(!in_array($to,['render-stable.php','surf-match.php'],true))$order[]=$to;}
$order[]='render-stable.php';$order[]='surf-match.php';
foreach($order as $to){$new=$dst.'/.deploy-new-'.$to;if(!@rename($new,$dst.'/'.$to)){restore_backup($backupDir,$dst,array_values($files),$homeBackup,$homeCache);fail_deploy("promotion failed $to");}}
echo "promotion=ok\n";

// Validate the promoted Surf Match entry point before generating any new home cache.
[$prodSurfOk,$prodSurfWhy]=fetch_check('https://costacams.com/surf-match.php?configcheck='.$nonce,['SURF MATCH BETA v13','All beaches','ccv13Drive'],18000);
if(!$prodSurfOk){restore_backup($backupDir,$dst,array_values($files),$homeBackup,$homeCache);fail_deploy("production Surf Match bootstrap failed: $prodSurfWhy");}
echo "production_runtime_bootstrap=ok\n";

// 5) Generate static candidates. Hardened page-warm.php preserves previous cache on any invalid render.
$warmer=$dst.'/page-warm.php';
ob_start();passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg($warmer),$warmCode);$warmOut=ob_get_clean();echo $warmOut;
$home=is_file($homeCache)?(string)file_get_contents($homeCache):'';
$homeLower=strtolower($home);
if(strlen($home)<20000||!str_contains($home,'id="channels"')||!str_contains($home,'id="surf-match"')||!str_contains($homeLower,'see the coast.')||!str_contains($homeLower,'pick your beach.')||str_contains($homeLower,'fatal error')){
  restore_backup($backupDir,$dst,array_values($files),$homeBackup,$homeCache);
  fail_deploy('homepage candidate did not pass cache validation');
}
echo "static_home_validation=ok\n";

// 6) Public post-deploy smoke tests. Any failure restores code + previous homepage cache.
$checks=[
  ['https://costacams.com/',['<title>CostaCams','id="channels"','id="surf-match"','See the coast.','Pick your beach.'],20000],
  ['https://costacams.com/?smoke='.$nonce,['<title>CostaCams','id="channels"','id="surf-match"'],20000],
  ['https://costacams.com/surf-match.php?smoke='.$nonce,['SURF MATCH BETA v13','All beaches','ccv13Drive'],18000],
  ['https://costacams.com/map',['CostaCams'],1200],
  ['https://costacams.com/surfers-paradise',['CostaCams'],1200],
];
foreach($checks as [$url,$markers,$min]){[$checkOk,$checkWhy]=fetch_check($url,$markers,$min);if(!$checkOk){restore_backup($backupDir,$dst,array_values($files),$homeBackup,$homeCache);fail_deploy("post-deploy smoke failed $url: $checkWhy");}}

echo "postdeploy_smoke=ok\nSAFE_DEPLOY_OK backup=$backupDir\n";
exit(0);
