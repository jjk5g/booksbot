<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$privatePath='/home/u751177156/.costacams_dbkey2.pem';
$publicHexPath='/home/u751177156/domains/costacams.com/public_html/costadbpub2.txt';
if(is_file($privatePath)){exit("KEY_EXISTS\n");}
$key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
if(!$key){fwrite(STDERR,"KEYGEN_FAILED\n");exit(1);}
if(!openssl_pkey_export($key,$privatePem)){fwrite(STDERR,"EXPORT_FAILED\n");exit(1);}
$details=openssl_pkey_get_details($key);
if(!$details||empty($details['key'])){fwrite(STDERR,"PUBLIC_FAILED\n");exit(1);}
$publicPem=$details['key'];
$body=preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s+/','',$publicPem);
$der=base64_decode((string)$body,true);
if($der===false){fwrite(STDERR,"DER_FAILED\n");exit(1);}
umask(0077);
if(file_put_contents($privatePath,$privatePem,LOCK_EX)===false){fwrite(STDERR,"PRIVATE_WRITE_FAILED\n");exit(1);}
chmod($privatePath,0600);
if(file_put_contents($publicHexPath,bin2hex($der),LOCK_EX)===false){fwrite(STDERR,"PUBLIC_WRITE_FAILED\n");exit(1);}
echo "KEY_CREATED\n";
