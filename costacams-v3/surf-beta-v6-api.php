<?php
declare(strict_types=1);
// Decrypt the Hostinger Mail API token only on the server. The encrypted blob is
// harmless without the RSA private key, which lives outside public_html.
$encPath=__DIR__.'/.ccmail-token.enc';
$keyPath='/home/u751177156/.ccmail-key.pem';
$outPath='/home/u751177156/.ccmail-token';
if(is_readable($encPath)&&is_readable($keyPath)&&function_exists('openssl_private_decrypt')){
    $sealed=base64_decode(trim((string)file_get_contents($encPath)),true);
    $private=openssl_pkey_get_private((string)file_get_contents($keyPath));
    $plain='';
    if($sealed!==false&&$private!==false&&openssl_private_decrypt($sealed,$plain,$private,OPENSSL_PKCS1_PADDING)&&preg_match('/^[0-9a-f]{64}$/',$plain)){
        @file_put_contents($outPath,$plain,LOCK_EX);
        @chmod($outPath,0600);
    }
}
require __DIR__.'/surf-beta-v5-api.php';
