<?php
declare(strict_types=1);
ob_start();
require __DIR__.'/surf-match-beta-v5.php';
$html=ob_get_clean();
$html=str_replace('Surf Match Beta v5 | CostaCams','Surf Match Beta v6 | CostaCams',$html);
$html=str_replace('SURF MATCH BETA v5','SURF MATCH BETA v6',$html);
$html=str_replace('surf-beta-v5-api.php','surf-beta-v6-api.php',$html);
$html=str_replace('Try a real sample email first, or subscribe and save this profile.','Try a real sample email first, or subscribe and save this profile. Emails are sent from the authenticated CostaCams mailbox to the address you enter.',$html);
echo $html;
