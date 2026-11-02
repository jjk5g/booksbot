<?php
declare(strict_types=1);
ob_start();
require __DIR__.'/index.php';
$html=ob_get_clean();
$needle='<nav class="topnav"><a href="/#channels">Channels</a>';
$replacement='<nav class="topnav"><a href="/#channels">Channels</a><a href="/map">Map</a>';
echo str_replace($needle,$replacement,$html);
