<?php
date_default_timezone_set('Australia/Brisbane');
$privateDbPassFile='/home/u751177156/.ccdbpass';
return [
  'alert_email' => 'support@costacams.com',
  'db_name' => 'u751177156_costav3site',
  'db_user' => 'u751177156_costav3app',
  'db_pass' => is_file($privateDbPassFile) ? trim((string)file_get_contents($privateDbPassFile)) : '',
];
