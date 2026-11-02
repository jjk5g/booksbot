<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$ok=false;$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $name=substr(trim(strip_tags($_POST['name']??'')),0,100);
  $email=filter_var($_POST['email']??'',FILTER_VALIDATE_EMAIL);
  $message=substr(trim(strip_tags($_POST['message']??'')),0,3000);
  if($email&&$message){
    $body="CostaCams contact form\n\nName: {$name}\nEmail: {$email}\nTime: ".date('c')."\n\n{$message}";
    $ok=cc_send_mail('[CostaCams] Contact form',$body,(string)$email);
    if(!$ok)$error='Your message could not be delivered right now. Please try again shortly.';
  }else{$error='Please enter a valid email and message.';}
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Contact CostaCams</title><meta name="robots" content="noindex,follow"><style>*{box-sizing:border-box}body{background:#050505;color:#fff;font:16px system-ui;margin:0}.w{max-width:680px;margin:7vh auto;padding:25px}.box{background:#0e1011;border:1px solid #282d2f;border-radius:18px;padding:22px}input,textarea,button{width:100%;padding:13px;margin:7px 0;background:#090b0c;color:white;border:1px solid #343a3d;border-radius:10px}textarea{min-height:170px}button{cursor:pointer;border-color:#4dd5ff}.ok{color:#75e4a6}.err{color:#ff9393}a{color:#4dd5ff}</style></head><body><main class="w"><p><a href="/">← CostaCams</a></p><div class="box"><h1>Contact CostaCams</h1><p>Your message is sent privately to the site administrator. The administrator email address is not published on this page.</p><?php if($ok):?><p class="ok">Thanks — your message has been sent.</p><?php elseif($error):?><p class="err"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></p><?php endif;?><form method="post"><input name="name" maxlength="100" placeholder="Name"><input name="email" type="email" maxlength="190" placeholder="Your email" required><textarea name="message" maxlength="3000" placeholder="Message" required></textarea><button type="submit">Send message</button></form></div></main></body></html>