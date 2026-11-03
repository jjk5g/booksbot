<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
$queries = [
 'singer-m1605' => 'Singer M1605 maquina de coser',
 'janome-311' => 'Janome 311 maquina de coser',
 'godeco-fantastique' => 'Godeco Fantastique maquina de coser',
 'brother-bm2800' => 'Brother BM2800 maquina de coser',
 'singer-4423' => 'Singer 4423 Heavy Duty maquina de coser',
 'lumina-overlock' => 'Lumina Sienna Silver overlock 4 hilos'
];
$id = isset($_GET['m']) ? strtolower(preg_replace('/[^a-z0-9\-]/','',$_GET['m'])) : '';
if (!isset($queries[$id])) { http_response_code(400); echo json_encode(['ok'=>false]); exit; }
$cacheDir = __DIR__ . '/cache'; if (!is_dir($cacheDir)) @mkdir($cacheDir,0755,true);
$cache = $cacheDir . '/meli-' . $id . '.json';
if (is_file($cache) && time()-filemtime($cache)<900) { readfile($cache); exit; }
$url='https://api.mercadolibre.com/sites/MLA/search?q='.rawurlencode($queries[$id]).'&limit=5';
$ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>7,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_HTTPHEADER=>['Accept: application/json','User-Agent: MaquinasDeCoserCordoba/1.0']]);
$token=getenv('MELI_ACCESS_TOKEN'); if ($token) curl_setopt($ch,CURLOPT_HTTPHEADER,['Accept: application/json','Authorization: Bearer '.$token,'User-Agent: MaquinasDeCoserCordoba/1.0']);
$raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
if (!$raw || $code<200 || $code>=300) { echo json_encode(['ok'=>false,'needs_auth'=>$code===401||$code===403]); exit; }
$data=json_decode($raw,true); $r=$data['results'][0]??null;
if (!$r) { echo json_encode(['ok'=>false]); exit; }
$thumb=$r['thumbnail']??''; if ($thumb) $thumb=str_replace('http://','https://',$thumb);
$out=['ok'=>true,'item'=>['id'=>$r['id']??'','title'=>$r['title']??'','price'=>$r['price']??null,'currency'=>$r['currency_id']??'ARS','image'=>$thumb,'url'=>$r['permalink']??'','shipping_free'=>!empty($r['shipping']['free_shipping'])]];
$json=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); @file_put_contents($cache,$json,LOCK_EX); echo $json;
