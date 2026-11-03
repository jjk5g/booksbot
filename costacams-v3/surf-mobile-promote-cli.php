<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$stage=__DIR__;
$root=dirname(dirname(__DIR__));
$srcWrapper=$stage.'/surf-match.php';
$srcCss=$stage.'/surf-match-mobile-v13.css';
$dstWrapper=$root.'/surf-match.php';
$dstCss=$root.'/surf-match-mobile-v13.css';
$home=$root.'/cache/home.html';
$stamp=date('Ymd-His');
$backup=$dstWrapper.'.premobile-'.$stamp;
function fail(string $m,int $c=1): never {fwrite(STDERR,"MOBILE_PROMOTE_BLOCKED $m\n");exit($c);}
function render(string $dir): string {$cmd='cd '.escapeshellarg($dir).' && php surf-match.php 2>&1';return (string)shell_exec($cmd);}
function checkHtml(string $html): array {
  $need=['SURF MATCH BETA v13','All beaches','Waze traffic','Rain radar','surf-match-mobile-v13.css','postcode','ccv13Drive'];
  $out=[];foreach($need as $n)$out[$n]=str_contains($html,$n);return $out;
}
if(!is_file($srcWrapper)||!is_file($srcCss))fail('staging files missing',2);
$lint=shell_exec('php -l '.escapeshellarg($srcWrapper).' 2>&1');if(!str_contains((string)$lint,'No syntax errors'))fail('wrapper lint failed: '.trim((string)$lint),3);
$css=(string)file_get_contents($srcCss);foreach(['input[type=range]','.ccv13-drive-grid','@media(max-width:430px)','.spot-detail'] as $marker){if(!str_contains($css,$marker))fail('mobile CSS marker missing: '.$marker,4);}
if(!is_file($home)||filesize($home)<10000||stripos((string)file_get_contents($home),'<!doctype html')===false)fail('production homepage safety file is not healthy',5);
$stageHtml=render($stage);$checks=checkHtml($stageHtml);foreach($checks as $k=>$v){if(!$v)fail('staging render missing '.$k,6);}
if(!copy($dstWrapper,$backup))fail('could not back up production wrapper',7);
if(!copy($srcCss,$dstCss)||!copy($srcWrapper,$dstWrapper)){@copy($backup,$dstWrapper);fail('copy failed; wrapper rolled back',8);}
@chmod($dstCss,0644);@chmod($dstWrapper,0644);
$prodHtml=render($root);$prodChecks=checkHtml($prodHtml);foreach($prodChecks as $k=>$v){if(!$v){@copy($backup,$dstWrapper);@unlink($dstCss);fail('production render missing '.$k.'; rolled back',9);}}
if(!is_file($home)||filesize($home)<10000) {@copy($backup,$dstWrapper);@unlink($dstCss);fail('homepage safety file changed unexpectedly; rolled back',10);}
echo 'MOBILE_PROMOTE_OK bytes='.strlen($prodHtml).' css='.strlen($css).' checks='.json_encode($prodChecks).' backup='.$backup."\n";
