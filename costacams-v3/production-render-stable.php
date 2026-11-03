<?php
declare(strict_types=1);

/*
 * Production homepage bridge.
 * Keep the production root aligned with the maintained costacams-v3 renderer
 * instead of allowing a second copy of homepage logic to drift behind.
 */
ob_start();
require __DIR__.'/ccdeploy-v3/costacams-v3/render-stable.php';
$html=ob_get_clean();

if(empty($_GET['slug'])){
    $html=str_replace(
        '<h1>Your coast.<br>Live.</h1>',
        '<h1>See the coast.<br>Pick your beach.</h1>',
        $html
    );
}

echo $html;
