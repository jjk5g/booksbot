<?php
declare(strict_types=1);
$cache='/tmp/costacams-map-home-v3.html';
$ttl=60;
if(is_file($cache) && (time()-filemtime($cache))<$ttl){
    header('X-CostaCams-Map-Cache: HIT');
    header('Cache-Control: public, max-age=30, stale-while-revalidate=60');
    readfile($cache);
    exit;
}
ob_start();
require __DIR__.'/map-health.php';
$html=ob_get_clean();
$embedCss=<<<'HTML'
<meta name="robots" content="noindex,nofollow">
<style id="cc-map-embed-style">
html,body{height:100%;overflow:hidden}.top,.hero,.controls,.legend,.footer{display:none!important}.wrap{max-width:none!important;margin:0!important;padding:0!important;height:100vh!important}#map{height:100vh!important;min-height:0!important;border:0!important;border-radius:0!important}
</style>
HTML;
$html=str_replace('</head>',$embedCss.'</head>',$html);
@file_put_contents($cache,$html,LOCK_EX);
header('X-CostaCams-Map-Cache: MISS');
header('Cache-Control: public, max-age=30, stale-while-revalidate=60');
echo $html;
