<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/map-health.php';
$html=ob_get_clean();

// Only locations with an actual HLS/iframe stream get a camera pin. Forecast-
// only beaches remain available as pages but are not represented by a fake cam.
$html=str_replace(
    "const visible=cams.filter(c=>region==='all'||c.region===region);",
    "const visible=cams.filter(c=>c.live&&(region==='all'||c.region===region));",
    $html
);
$html=str_replace('<span><i class="dot forecast"></i>Forecast / no verified camera</span>','',$html);
$html=str_replace('Explore every CostaCams location from The Spit to Byron Bay. Camera pins open a live preview. Shark pins show the latest verified notification stored for each beach or locality.','Explore verified camera locations from The Spit to Byron Bay. Camera pins appear only where CostaCams has an actual stream. Shark activity is shown separately by coastal zone.',$html);

echo $html;
