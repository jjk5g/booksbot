<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
$slug=preg_replace('/[^a-z0-9-]/','',strtolower((string)($_GET['slug']??'')));
if($slug===''||!cc_location($slug)){
  $_SERVER['REDIRECT_STATUS']='404';
  require __DIR__.'/error.php';
  exit;
}
require __DIR__.'/render-fast.php';
