<?php
declare(strict_types=1);
if(empty($_GET['slug'])){header('Location: /',true,302);exit;}
// app.php now handles channel caching, freshness labels and shark alerts itself.
// Do not force nocache here: that previously made every beach visit rebuild
// the entire page and was the main cause of slow channel loading.
require __DIR__.'/app.php';
