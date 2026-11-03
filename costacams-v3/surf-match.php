<?php
declare(strict_types=1);

// Keep the private runtime DB config in sync with the encrypted deployment
// credential. The plaintext password never lives in the repository or web root.
$runtimeConfig='/home/u751177156/.costacams-private.php';
$encryptedConfig=__DIR__.'/server-config.php';
$diag=__DIR__.'/.surf-match-diagnostic.log';
if(is_readable($encryptedConfig)){
    try{
        $cfg=require $encryptedConfig;
        if(is_array($cfg)
            && !empty($cfg['db_name'])
            && !empty($cfg['db_user'])
            && !empty($cfg['db_pass'])){
            $payload="<?php\nreturn ".var_export($cfg,true).";\n";
            @file_put_contents($runtimeConfig,$payload,LOCK_EX);
            @chmod($runtimeConfig,0600);
        }
    }catch(Throwable $e){
        @file_put_contents($diag,date(DATE_ATOM).' CONFIG '.get_class($e).' '.$e->getMessage().' '.$e->getFile().':'.$e->getLine()."\n",FILE_APPEND|LOCK_EX);
        error_log('CostaCams Surf Match config repair: '.$e->getMessage());
    }
}

try{
    require __DIR__.'/surf-match-beta-v10.php';
}catch(Throwable $e){
    $line=date(DATE_ATOM).' FATAL '.get_class($e).' '.$e->getMessage().' '.$e->getFile().':'.$e->getLine()."\n";
    @file_put_contents($diag,$line,FILE_APPEND|LOCK_EX);
    error_log('CostaCams Surf Match fatal: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CostaCams Surf Match</title><style>body{margin:0;background:#050606;color:#eef4f5;font:16px system-ui;display:grid;place-items:center;min-height:100vh}.box{max-width:620px;padding:28px;border:1px solid #273035;border-radius:18px;background:#0d1011}b{color:#50dcff}</style><div class="box"><b>COSTACAMS SURF MATCH</b><h1>Surf Match is recovering</h1><p>The page hit a server-side error. The error has been logged so the beta can recover without exposing server details.</p><p><a style="color:#50dcff" href="/">Return to CostaCams</a></p></div>';
}
