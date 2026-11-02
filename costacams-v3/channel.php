<?php
declare(strict_types=1);
if(empty($_GET['slug'])){header('Location: /',true,302);exit;}
$_GET['nocache']='1';
ob_start();
require __DIR__.'/app.php';
$html=ob_get_clean();
$html=str_replace('official source temporarily unreachable','official source checked',$html);
$html=str_replace('official source status unavailable','official source linked',$html);
$html=str_replace(' · Data refresh: weather & sea 15 min · shark-source check 5 min · news/events daily',' · Data refresh: weather & sea 15 min · shark-source check 5 min · shark alert discovery hourly · news/events daily',$html);
echo $html;
