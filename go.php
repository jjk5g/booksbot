<?php
// Outbound click router. Affiliate-ready: replace any destination below with the official
// Mercado Libre affiliate URL for that model once the account is enrolled.
$destinations = [
  'singer-m1605' => 'https://listado.mercadolibre.com.ar/maquina-singer-m1605',
  'janome-311' => 'https://listado.mercadolibre.com.ar/maquina-de-coser-janome-311',
  'godeco-fantastique' => 'https://listado.mercadolibre.com.ar/maquina-godeco-fantastique',
  'brother-bm2800' => 'https://listado.mercadolibre.com.ar/maquina-brother-bm2800',
  'singer-4423' => 'https://listado.mercadolibre.com.ar/maquina-de-coser-singer-facilita-pro-4423',
  'lumina-overlock' => 'https://listado.mercadolibre.com.ar/overlock-lumina-sienna-silver'
];
$id = isset($_GET['m']) ? preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['m'])) : '';
if (!isset($destinations[$id])) {
  http_response_code(302);
  header('Location: https://listado.mercadolibre.com.ar/maquinas-de-coser');
  exit;
}
$position = isset($_GET['pos']) ? max(1, min(3, intval($_GET['pos']))) : 0;
$score = isset($_GET['score']) ? max(0, min(100, intval($_GET['score']))) : 0;
$line = [gmdate('c'), $id, $position, $score, $_SERVER['HTTP_REFERER'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? ''];
$line = array_map(function($v){ return str_replace(["\r","\n","\t"], ' ', (string)$v); }, $line);
@file_put_contents(__DIR__ . '/affiliate-clicks.tsv', implode("\t", $line) . "\n", FILE_APPEND | LOCK_EX);
header('Cache-Control: no-store');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Location: ' . $destinations[$id], true, 302);
exit;
