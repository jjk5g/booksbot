<?php
declare(strict_types=1);
// Home presentation wrapper: keep low-quality iframe feeds out of the hero
// recommendation while leaving those beaches available in the full map/catalog.
ob_start();
require __DIR__.'/app.php';
$html=ob_get_clean();
$html=preg_replace_callback('~const candidates=(\[[^;]*\]);~s',function($m){
    $rows=json_decode($m[1],true);if(!is_array($rows))return $m[0];
    $rows=array_values(array_filter($rows,function($r){
        if(!is_array($r))return false;
        if((string)($r['slug']??'')==='kingscliff')return false;
        return true;
    }));
    return 'const candidates='.json_encode($rows,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';';
},$html,1)??$html;
echo $html;
