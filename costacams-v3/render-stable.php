<?php
declare(strict_types=1);

$slug=preg_replace('/[^a-z0-9-]/','',strtolower((string)($_GET['slug']??'')));
ob_start();
require __DIR__.'/render-fast.php';
$html=ob_get_clean();

if($slug===''){
    // Remove the old automatic failover rotator. It could repeatedly switch
    // cameras when a stream was slow to start even though the page itself was fine.
    $html=preg_replace('~<script id="cc-recommended-fallback">.*?</script>~s','',$html)??$html;

    // The normal recommendation renderer is now idempotent: one selection per
    // page load, with no automatic rotation afterwards.
    $html=str_replace(
        'function renderRecommended(){',
        'function renderRecommended(){if(window.__ccRecommendedLocked)return;window.__ccRecommendedLocked=true;',
        $html
    );
    $html=str_replace(
        'Rotates through selected high-quality live coastal cameras.',
        'Selected once from high-quality live coastal cameras.',
        $html
    );

    // Keep first paint light. The camera/map can start shortly after the page is
    // already visible instead of competing with initial HTML rendering.
    $html=str_replace('setTimeout(start,260);','setTimeout(start,700);',$html);
    $html=str_replace("requestIdleCallback(loadMap,{timeout:650})","requestIdleCallback(loadMap,{timeout:1600})",$html);
    $html=str_replace('else setTimeout(loadMap,160);','else setTimeout(loadMap,1000);',$html);
}

echo $html;
