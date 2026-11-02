<?php
declare(strict_types=1);
ob_start();
require __DIR__.'/map.php';
$html=ob_get_clean();
$embedCss=<<<'HTML'
<meta name="robots" content="noindex,nofollow">
<style id="cc-map-embed-style">
html,body{height:100%;overflow:hidden}.top,.hero,.controls,.legend,.footer{display:none!important}.wrap{max-width:none!important;margin:0!important;padding:0!important;height:100vh!important}#map{height:100vh!important;min-height:0!important;border:0!important;border-radius:0!important}
</style>
HTML;
$html=str_replace('</head>',$embedCss.'</head>',$html);
echo $html;
