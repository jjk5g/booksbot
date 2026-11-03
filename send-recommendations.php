<?php
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); echo json_encode(['ok'=>false]); exit; }
session_start(); if (!empty($_SESSION['last_mail']) && time()-$_SESSION['last_mail']<30) { http_response_code(429); echo json_encode(['ok'=>false,'message'=>'Esperá unos segundos antes de volver a enviar.']); exit; }
$payload=json_decode(file_get_contents('php://input'),true)?:[]; $email=filter_var($payload['email']??'',FILTER_VALIDATE_EMAIL);
if (!$email) { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Ingresá un email válido.']); exit; }
$catalog=[
 'singer-m1605'=>['Singer','M1605'],'janome-311'=>['Janome','311'],'godeco-fantastique'=>['Godeco','Fantastique 32 puntadas'],
 'brother-bm2800'=>['Brother','BM2800'],'singer-4423'=>['Singer','Heavy Duty 4423'],'lumina-overlock'=>['Lumina','Sienna Silver Overlock 4 hilos']
];
$items=array_slice(is_array($payload['items']??null)?$payload['items']:[],0,3); $safe=[];
foreach($items as $i){$id=preg_replace('/[^a-z0-9\-]/','',strtolower($i['id']??''));if(isset($catalog[$id]))$safe[]=['id'=>$id,'brand'=>$catalog[$id][0],'model'=>$catalog[$id][1],'score'=>max(0,min(100,(int)($i['score']??0)))];}
if(!$safe){http_response_code(400);echo json_encode(['ok'=>false,'message'=>'No hay recomendaciones para enviar.']);exit;}
$host=preg_replace('/[^a-z0-9\.\-]/i','',$_SERVER['HTTP_HOST']??'maquinasdecosercordoba.com'); $base='https://'.$host.'/elegi-tu-maquina/';
$profile=trim(strip_tags((string)($payload['profile']??''))); if(strlen($profile)>500)$profile=substr($profile,0,500);
$rows='';
foreach($safe as $n=>$i){$url=$base.'go.php?m='.rawurlencode($i['id']).'&pos='.($n+1).'&score='.$i['score'];$name=htmlspecialchars($i['brand'].' '.$i['model'],ENT_QUOTES,'UTF-8');$rows.='<tr><td style="padding:12px;border-bottom:1px solid #eee"><strong>#'.($n+1).' '.$name.'</strong><br><span style="color:#6d335a">'.$i['score'].'% de compatibilidad</span><br><a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">Ver en Mercado Libre</a></td></tr>';}
$html='<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;color:#282126"><h2>Tus máquinas recomendadas</h2><p>'.htmlspecialchars($profile,ENT_QUOTES,'UTF-8').'</p><table style="width:100%;border-collapse:collapse">'.$rows.'</table><p style="font-size:12px;color:#777">Los precios y la disponibilidad se verifican en Mercado Libre al abrir cada enlace.</p></div>';
$subject='Tus máquinas recomendadas | Máquinas de Coser Córdoba'; $sent=false;
$wp=dirname(__DIR__).'/wp-load.php'; if(is_file($wp)){require_once $wp; if(function_exists('wp_mail'))$sent=wp_mail($email,$subject,$html,['Content-Type: text/html; charset=UTF-8']);}
if(!$sent){$headers="MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";$sent=@mail($email,$subject,$html,$headers);}
if($sent){$_SESSION['last_mail']=time();echo json_encode(['ok'=>true]);}else{http_response_code(500);echo json_encode(['ok'=>false,'message'=>'No se pudo enviar el email desde el servidor.']);}
